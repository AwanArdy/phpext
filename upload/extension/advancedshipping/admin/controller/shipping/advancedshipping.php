<?php
declare(strict_types=1);

namespace Opencart\Admin\Controller\Extension\Advancedshipping\Shipping;

class Advancedshipping extends \Opencart\System\Engine\Controller {
	private array $error = [];
	private string $version = '2.0.2';
	private string $type = 'shipping';
	private string $extension = 'advancedshipping';
	private string $route = 'extension/advancedshipping/shipping/advancedshipping';
	private string $dbTable = 'advanced_shipping';
	private string $href = 'https://www.opencartaddons.com/';
	private string $email = 'contact@opencartaddons.com';
	private bool $ocappsStatus = false;

	public function index(): void {
		$this->load->model('extension/advancedshipping/shipping/advancedshipping');

		// Check Updates
		$update = $this->model_extension_advancedshipping_shipping_advancedshipping->update();
		if (!empty($update['status'])) {
			$this->session->data['success'] = (string)$update['log'];
			$this->response->redirect($this->link($this->route));
		}

		// Auto Backup (internal only — must NOT set JSON Content-Type on this HTML page)
		if (!empty($this->field('backup'))) {
			$backupStatus = true;
			$backups = $this->getBackups();
			foreach ($backups as $backup) {
				if (((int)$backup['date'] + 86400) > time()) {
					$backupStatus = false;
					break;
				}
			}
			if ($backupStatus) {
				$this->writeBackup('Automatic Backup');
			}
		}

		$data = [];
		$data['text'] = $this->load->language($this->route);
		// Extension language overwrites shared keys (e.g. text_no_results) used by admin header/notifications
		$this->load->language('default');

		$data['type'] = $this->type;
		$data['extension'] = $this->extension;
		$data['version'] = $this->version;
		$data['user_token'] = $this->session->data['user_token'];
		$data['token'] = 'user_token=' . $this->session->data['user_token'];

		$data['text']['text_footer'] = sprintf($data['text']['text_footer'] ?? 'Advanced Shipping v%s', $this->version);

		// Demo check
		$httpHost = $this->request->server['HTTP_HOST'] ?? '';
		if ($httpHost !== '' && str_contains($httpHost, 'demo.opencartaddons.com')) {
			$data['demo'] = $this->href . 'purchase/?platform=opencart';
		} else {
			$data['demo'] = false;
		}

		$data['debug_download'] = $this->link($this->route . '.downloadDebug');
		$data['debug_clear']    = $this->link($this->route . '.clearDebug');
		$data['debug_reload']   = $this->link($this->route . '.reloadDebug');
		$data['debug_log']      = null;

		$debugFile = DIR_LOGS . $this->extension . '.txt';
		if (file_exists($debugFile)) {
			$debugFileSize = filesize($debugFile);
			if ($debugFileSize >= 5242880) {
				$suffix = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
				$i = 0;
				$calcSize = (float)$debugFileSize;
				while (($calcSize / 1024) > 1 && $i < count($suffix) - 1) {
					$calcSize = $calcSize / 1024;
					$i++;
				}
				$data['warning'] = sprintf($data['text']['text_error_debug'] ?? 'Debug file too large: %s', round($calcSize, 2) . ' ' . $suffix[$i]);
			} else {
				$data['debug_log'] = file_get_contents($debugFile);
			}
		}

		$data['cache_clear'] = $this->link($this->route . '.clearCache');

		$data['backups'] = [];
		$backups = $this->getBackups();
		foreach ($backups as $backup) {
			$data['backups'][] = [
				'file'    => $backup['file'],
				'date'    => date('Y-m-d H:i:s', (int)$backup['date']),
				'comment' => $backup['comment'],
			];
		}

		$data['backup_restore']   = $this->link($this->route . '.restoreBackup');
		$data['backup_clear']     = $this->link($this->route . '.clearBackup');
		$data['backup_clear_all'] = $this->link($this->route . '.clearBackups');

		$data['action_cancel'] = $this->link('marketplace/extension', 'type=shipping');
		$data['action_add']    = $this->link($this->route . '.add');
		$data['action_delete'] = $this->link($this->route . '.delete');
		$data['action_import'] = $this->link($this->route . '.import');
		$data['action_export'] = $this->link($this->route . '.export');

		$data['success'] = $this->session->data['success'] ?? null;
		$data['warning'] = $data['warning'] ?? ($this->session->data['warning'] ?? null);
		$data['error']   = $this->session->data['error'] ?? null;

		unset($this->session->data['success'], $this->session->data['warning'], $this->session->data['error']);

		$fields = ['status', 'test', 'title', 'sort_order', 'ocapps_status', 'sort_quotes', 'display_value', 'debug', 'cache', 'backup', 'gmaps_api_key'];
		foreach ($fields as $field) {
			$data[$field] = $this->request->post[$field] ?? $this->field($field);
		}

		$options = ['sort_quote', 'title_display', 'combination_method'];
		foreach ($options as $option) {
			$x = 0;
			$data['option_' . $option] = [];
			while (isset($data['text'][$option . '_' . $x])) {
				// Populate both prefixed (used by templates) and plain keys for BC
				$data['option_' . $option][$x] = $data['text'][$option . '_' . $x];
				$data[$option][$x] = $data['text'][$option . '_' . $x];
				$x++;
			}
		}

		$data['rate_types'] = $this->ratetypes();
		$data['geo_zones']  = $this->geozones();

		$data['rates'] = [];
		$this->session->data['rates'] = [];

		$rates = $this->model_extension_advancedshipping_shipping_advancedshipping->getRates();
		if (!empty($rates)) {
			foreach ($rates as $rate) {
				foreach ($rate as $key => $val) {
					$rate[$key] = $this->value($val);
				}

				$this->session->data['rates'][] = $rate['rate_id'];

				$adminLang = $this->config->get('config_admin_language') ?? $this->config->get('config_language') ?? 'en-gb';
				$rateName = is_array($rate['name']) ? ($rate['name'][$adminLang] ?? reset($rate['name']) ?: '') : '';
				if ($rateName === '') {
					$rateName = $this->language->get('text_name');
				}

				$data['rates'][] = [
					'rate_id'     => $rate['rate_id'],
					'description' => !empty($rate['description']) ? $rate['description'] : $this->language->get('text_name'),
					'name'        => $rateName,
					'status'      => !empty($rate['status']) ? ($data['text']['text_on'] ?? 'On') : ($data['text']['text_off'] ?? 'Off'),
					'group'       => !empty($rate['group']) ? $rate['group'] : '[None]',
					'edit'        => $this->link($this->route . '.edit', 'rate_id=' . $rate['rate_id']),
				];
			}
		}

		$data['combinations'] = [];
		$combinations = $this->request->post[$this->extension . '_combinations'] ?? $this->config->get('shipping_' . $this->extension . '_combinations');
		if ($combinations) {
			$parsedCombinations = $this->value($combinations);
			if (is_array($parsedCombinations)) {
				foreach ($parsedCombinations as $key => $value) {
					$data['combinations'][$key] = [
						'key'           => $key,
						'sort_order'    => $value['sort_order'] ?? 0,
						'title_display' => $value['title_display'] ?? 0,
						'title'         => $value['title'] ?? [],
						'formula'       => $value['formula'] ?? '',
						'method'        => $value['method'] ?? 0,
					];
				}
			}
		}

		// combination_row tracks the next available row index for JS addCombination()
		$data['combination_row'] = !empty($data['combinations']) ? (max(array_keys($data['combinations'])) + 1) : 1;

		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();
		$data['ocapps_integration'] = $this->ocappsStatus;
		$data['email'] = $this->config->get('config_email');

		// OC4 extension assets live under /extension/{code}/admin/view/... (not admin/view/stylesheet/extension/...)
		$this->document->addStyle('https://fonts.googleapis.com/css?family=Oswald:400,700');
		$this->document->addStyle('../extension/' . $this->extension . '/admin/view/stylesheet/shipping/' . $this->extension . '.css?v=' . $this->version);
		$this->document->setTitle($data['text']['text_name'] ?? 'Advanced Shipping');

		$data['header']      = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer']      = $this->load->controller('common/footer');

		$this->response->addHeader('Content-Type: text/html; charset=utf-8');
		$this->response->setOutput($this->load->view('extension/' . $this->extension . '/shipping/' . $this->extension, $data));
	}

	public function autosave(): void {
		$json = [];

		if ($this->validate(true)) {
			if ($this->request->server['REQUEST_METHOD'] === 'POST') {
				$this->load->model('setting/setting');

				$data = [];
				$settingCode = 'shipping_' . $this->extension;
				$errors = [];

				foreach ($this->request->post as $key => $value) {
					$settingKey = 'shipping_' . $this->extension . '_' . $key;
					$data[$settingKey] = is_array($value) ? json_encode($value) : $value;

					$fieldErrors = $this->validateSetting($key, $value);
					foreach ($fieldErrors as $errKey => $errVal) {
						$errors[$errKey] = $errVal;
						$json['error'][$errKey] = $errVal;
					}
				}

				// Save all settings in a single query only if no validation errors
				if (empty($errors)) {
					$this->model_setting_setting->editSetting($settingCode, $data);
				}
			} else {
				$json['error'] = true;
			}
		} else {
			$json['error'] = true;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function filter(): void {
		$this->load->model('extension/advancedshipping/shipping/advancedshipping');
		$this->load->language($this->route);

		$json = [];
		if (!empty($this->request->post)) {
			$rates = $this->model_extension_advancedshipping_shipping_advancedshipping->getRates($this->request->post);
		} else {
			$rates = [];
		}

		$json['rates'] = [];
		$this->session->data['rates'] = [];

		if ($rates) {
			$json['success'] = true;

			foreach ($rates as $rate) {
				foreach ($rate as $key => $value) {
					$rate[$key] = $this->value($value);
				}

				$this->session->data['rates'][] = $rate['rate_id'];

				$langCode = $this->config->get('config_admin_language') ?? $this->config->get('config_language') ?? 'en-gb';
				$rateName = is_array($rate['name']) ? ($rate['name'][$langCode] ?? reset($rate['name']) ?: '') : '';
				if ($rateName === '') {
					$rateName = $this->language->get('text_name');
				}

				$json['rates'][] = [
					'rate_id'     => $rate['rate_id'],
					'description' => !empty($rate['description']) ? $rate['description'] : $this->language->get('text_name'),
					'name'        => $rateName,
					'status'      => !empty($rate['status']) ? $this->language->get('text_on') : $this->language->get('text_off'),
					'group'       => !empty($rate['group']) ? $rate['group'] : '[None]',
					'edit'        => $this->link($this->route . '.edit', 'rate_id=' . $rate['rate_id']),
				];
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function form(int $rate_id = 0): void {
		$this->load->model('extension/advancedshipping/shipping/advancedshipping');

		$data = [];
		$data['text'] = $this->load->language($this->route);
		$this->load->language('default');

		$data['type']       = $this->type;
		$data['extension']  = $this->extension;
		$data['version']    = $this->version;
		$data['user_token'] = $this->session->data['user_token'];
		$data['token']      = 'user_token=' . $this->session->data['user_token'];

		$data['text']['text_footer'] = sprintf($data['text']['text_footer'] ?? 'Advanced Shipping v%s', $this->version);

		$data['success']     = $this->session->data['success'] ?? null;
		$data['error']       = $this->session->data['error'] ?? null;
		$data['rate_errors'] = $this->session->data['rate_errors'] ?? null;

		unset($this->session->data['success'], $this->session->data['error'], $this->session->data['rate_errors']);

		$post_data = $this->session->data['post_data'] ?? [];
		unset($this->session->data['post_data']);

		if ($rate_id > 0) {
			$rate_data = $this->model_extension_advancedshipping_shipping_advancedshipping->getRate($rate_id);
			if (!$rate_data) {
				$this->session->data['error'] = $data['text']['text_error_rate_get'] ?? 'Error fetching rate';
				$this->response->redirect($this->link($this->route));
				return;
			}
		} else {
			$rate_data = $this->model_extension_advancedshipping_shipping_advancedshipping->settings();
		}

		foreach ($rate_data as $key => $value) {
			$data['data'][$key] = $post_data[$key] ?? $this->value($value);
		}

		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();

		$this->load->model('localisation/tax_class');
		$taxClasses = $this->model_localisation_tax_class->getTaxClasses();
		$data['tax_classes'] = array_merge([['tax_class_id' => 0, 'title' => $data['text']['text_none'] ?? 'None']], $taxClasses);

		$this->load->model('localisation/currency');
		$data['currencies']      = $this->model_localisation_currency->getCurrencies();
		$data['config_currency'] = $this->config->get('config_currency');

		$data['rate_types'] = $this->ratetypes();
		$data['geo_zones']  = $this->geozones();
		$data['rate_id']    = $rate_id;

		$data['text']['entry_shipping_factor'] = sprintf($data['text']['entry_shipping_factor'] ?? 'Factor (%s/%s)', $this->lengthUnit(), $this->weightUnit());

		$data['text']['text_total']      = $this->currencySymbol((string)$this->config->get('config_currency'));
		$data['text']['text_weight']     = $this->weightUnit();
		$data['text']['text_dim_weight'] = $this->weightUnit();
		$data['text']['text_volume']     = $this->lengthUnit() . '&sup3;';
		$data['text']['text_length']     = $this->lengthUnit();
		$data['text']['text_width']      = $this->lengthUnit();
		$data['text']['text_height']     = $this->lengthUnit();

		$options = ['total_type', 'ocapps_cost', 'ocapps_requirement', 'final_cost'];
		foreach ($options as $option) {
			$x = 0;
			$data[$option] = [];
			while (isset($data['text'][$option . '_' . $x])) {
				// Populate both plain and option_ prefixed keys (templates use option_* for ocapps)
				$data[$option][$x] = $data['text'][$option . '_' . $x];
				$data['option_' . $option][$x] = $data['text'][$option . '_' . $x];
				$x++;
			}
		}

		$data['requirement_match'] = [];
		foreach (['any', 'all', 'none'] as $param) {
			$data['requirement_match'][$param] = $data['text']['requirement_match_' . $param] ?? $param;
		}

		$data['requirement_cost'] = [];
		foreach (['every', 'any', 'all', 'none'] as $param) {
			$data['requirement_cost'][$param] = $data['text']['requirement_cost_' . $param] ?? $param;
		}

		$rates = !empty($this->session->data['rates']) ? $this->session->data['rates'] : [];
		$rateIdx = array_search($rate_id, $rates, true);
		$previous_rate_id = ($rateIdx !== false && isset($rates[$rateIdx - 1])) ? $rates[$rateIdx - 1] : null;
		$next_rate_id     = ($rateIdx !== false && isset($rates[$rateIdx + 1])) ? $rates[$rateIdx + 1] : null;

		$requirements = $this->requirements();
		$data['requirement_types'] = $requirements['requirement_types'];
		$data['operations']        = $requirements['operations'];
		$data['values']            = $requirements['values'];
		$data['value_types']       = $requirements['value_types'];
		$data['parameters']        = $requirements['parameters'];

		$data['action']          = $this->link($this->route . '.save', ($rate_id > 0 ? 'rate_id=' . $rate_id : ''));
		$data['action_close']    = $this->link($this->route);
		$data['action_previous'] = $previous_rate_id ? $this->link($this->route . '.edit', 'rate_id=' . $previous_rate_id) : false;
		$data['action_next']     = $next_rate_id ? $this->link($this->route . '.edit', 'rate_id=' . $next_rate_id) : false;
		$data['action_copy']     = ($rate_id > 0) ? $this->link($this->route . '.copy', 'rate_id=' . $rate_id) : null;
		$data['action_delete']   = ($rate_id > 0) ? $this->link($this->route . '.delete', 'rate_id=' . $rate_id) : null;

		$data['ocapps_integration'] = $this->ocappsStatus;

		$this->document->addStyle('https://fonts.googleapis.com/css?family=Oswald:400,700');
		$this->document->addStyle('../extension/' . $this->extension . '/admin/view/stylesheet/shipping/' . $this->extension . '.css?v=' . $this->version);
		$this->document->addStyle('../extension/' . $this->extension . '/admin/view/javascript/shipping/' . $this->extension . '/jquery.datetimepicker.css');
		$this->document->addScript('../extension/' . $this->extension . '/admin/view/javascript/shipping/' . $this->extension . '/jquery.datetimepicker.js');

		$this->document->setTitle($data['text']['text_name'] ?? 'Advanced Shipping');

		$data['header']      = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer']      = $this->load->controller('common/footer');

		$this->response->addHeader('Content-Type: text/html; charset=utf-8');
		$this->response->setOutput($this->load->view('extension/' . $this->extension . '/shipping/' . $this->extension . '_rate', $data));
	}

	public function add(): void {
		$this->validate();
		$this->form();
	}

	public function edit(): void {
		$this->validate();
		$rate_id = (int)($this->request->get['rate_id'] ?? 0);
		$this->form($rate_id);
	}

	public function save(): void {
		$rate_id = (int)($this->request->get['rate_id'] ?? 0);
		$this->load->language($this->route);

		$errors = $this->validateRate($this->request->post);
		if (!$errors) {
			$this->load->model('extension/advancedshipping/shipping/advancedshipping');

			if (!empty($this->request->post['origin'])) {
				$geocode = $this->geocode((string)$this->request->post['origin']);
				if ($geocode !== false) {
					$this->request->post['geocode_lat'] = $geocode['lat'];
					$this->request->post['geocode_lng'] = $geocode['lng'];
				}
			}

			if ($rate_id > 0) {
				$this->model_extension_advancedshipping_shipping_advancedshipping->editRate($rate_id, $this->request->post);
			} else {
				$rate_id = $this->model_extension_advancedshipping_shipping_advancedshipping->addRate($this->request->post);
			}

			$this->cache->delete($this->extension . '_rates');
			$this->session->data['success'] = $this->language->get('text_success_save');
			$this->response->redirect($this->link($this->route . '.edit', 'rate_id=' . $rate_id));
		} else {
			foreach ($errors as $key => $value) {
				$this->session->data['rate_errors'][$key] = $value;
			}
			$this->session->data['error']     = $this->language->get('text_error_rate');
			$this->session->data['post_data'] = $this->request->post;

			if ($rate_id > 0) {
				$this->response->redirect($this->link($this->route . '.edit', 'rate_id=' . $rate_id));
			} else {
				$this->response->redirect($this->link($this->route . '.add'));
			}
		}
	}

	public function delete(): void {
		$this->validate();
		$this->writeBackup('Backup Created Prior To Rate Deletion');
		$this->cache->delete($this->extension . '_rates');

		$this->load->language($this->route);
		$this->load->model('extension/advancedshipping/shipping/advancedshipping');

		$rate_id = (int)($this->request->get['rate_id'] ?? 0);
		$rates   = (array)($this->request->post['selected'] ?? []);

		if ($rate_id > 0) {
			$this->model_extension_advancedshipping_shipping_advancedshipping->deleteRate($rate_id);
			$this->session->data['success'] = $this->language->get('text_success_rate_delete');
		} elseif (!empty($rates)) {
			foreach ($rates as $rId) {
				$this->model_extension_advancedshipping_shipping_advancedshipping->deleteRate((int)$rId);
			}
			$this->session->data['success'] = $this->language->get('text_success_delete');
		}

		$this->response->redirect($this->link($this->route));
	}

	public function copy(): void {
		$this->validate();
		$this->cache->delete($this->extension . '_rates');
		$this->load->language($this->route);
		$this->load->model('extension/advancedshipping/shipping/advancedshipping');

		$copy_rate_id = (int)($this->request->get['rate_id'] ?? 0);
		$rate_info    = $this->model_extension_advancedshipping_shipping_advancedshipping->copyRate($copy_rate_id);
		$rate_id      = (int)($rate_info['rate_id'] ?? 0);

		$this->session->data['success'] = $this->language->get('text_success_copy');
		$this->response->redirect($this->link($this->route . '.edit', 'rate_id=' . $rate_id));
	}

	private function requirements(): array {
		$cacheKey = $this->extension . '_requirements';
		$data = $this->cache->get($cacheKey);

		if (!$data || empty($this->field('cache'))) {
			$requirementData = [];
			$requirementData['language'] = $this->load->language($this->route);

			foreach (['volume', 'length', 'width', 'height'] as $param) {
				foreach (['cart', 'product'] as $type) {
					$key = 'text_requirement_type_' . $type . '_' . $param;
					$requirementData['language'][$key] = sprintf($requirementData['language'][$key] ?? '%s', $this->lengthUnit());
				}
			}

			$requirementData['language']['text_requirement_type_cart_weight']       = sprintf($requirementData['language']['text_requirement_type_cart_weight'] ?? '%s', $this->weightUnit());
			$requirementData['language']['text_requirement_type_cart_dim_weight']   = sprintf($requirementData['language']['text_requirement_type_cart_dim_weight'] ?? '%s', $this->weightUnit());
			$requirementData['language']['text_requirement_type_product_weight']    = sprintf($requirementData['language']['text_requirement_type_product_weight'] ?? '%s', $this->weightUnit());

			foreach (['total'] as $param) {
				foreach (['cart', 'product'] as $type) {
					$key = 'text_requirement_type_' . $type . '_' . $param;
					$requirementData['language'][$key] = sprintf($requirementData['language'][$key] ?? '%s', $this->currencySymbol((string)$this->config->get('config_currency')));
				}
			}

			$requirementData['requirement_types'] = [];
			$reqTypes = [
				'cart'                   => ['quantity', 'total', 'weight', 'dim_weight', 'volume', 'distance', 'length', 'width', 'height'],
				'product'                => ['quantity', 'total', 'weight', 'dim_weight', 'volume', 'length', 'width', 'height', 'name', 'model', 'sku', 'upc', 'ean', 'jan', 'isbn', 'mpn', 'location', 'stock', 'category', 'manufacturer'],
				'product_option'         => [],
				'product_attribute'      => [],
				'customer'               => ['store', 'group', 'name', 'email', 'telephone', 'fax', 'company', 'address', 'city', 'postcode'],
				'customer_customfield'   => [],
				'other'                  => ['currency', 'day', 'date', 'time'],
			];

			foreach ($reqTypes as $group => $types) {
				$requirementData['requirement_types'][$group] = [];
				foreach ($types as $type) {
					$reqKey = ($group === 'other' ? '' : $group . '_') . $type;
					$requirementData['requirement_types'][$group][$reqKey] = $requirementData['language']['text_requirement_type_' . $reqKey] ?? $type;
				}
			}

			$requirementData['operations'] = [];

			$paramsSet1 = ['product_category', 'product_manufacturer', 'customer_store', 'customer_group', 'customer_postcode', 'currency', 'day'];
			foreach ($paramsSet1 as $param) {
				$requirementData['operations'][$param] = [];
				foreach (['eq', 'neq'] as $operator) {
					$requirementData['operations'][$param][$operator] = $requirementData['language']['text_operator_' . $operator] ?? $operator;
				}
			}

			$paramsSet2 = ['product_name', 'product_model', 'product_sku', 'product_upc', 'product_ean', 'product_jan', 'product_isbn', 'product_mpn', 'product_location', 'customer_name', 'customer_email', 'customer_telephone', 'customer_fax', 'customer_company', 'customer_address', 'customer_city'];
			foreach ($paramsSet2 as $param) {
				$requirementData['operations'][$param] = [];
				foreach (['eq', 'neq', 'strpos', 'nstrpos'] as $operator) {
					$requirementData['operations'][$param][$operator] = $requirementData['language']['text_operator_' . $operator] ?? $operator;
				}
			}

			$paramsSet3 = ['cart_quantity', 'cart_total', 'cart_weight', 'cart_volume', 'cart_dim_weight', 'cart_distance', 'cart_length', 'cart_width', 'cart_height', 'product_quantity', 'product_total', 'product_weight', 'product_volume', 'product_length', 'product_width', 'product_height'];
			foreach ($paramsSet3 as $param) {
				$requirementData['operations'][$param] = [];
				foreach (['eq', 'neq', 'gte', 'lte', 'add', 'sub'] as $operator) {
					$requirementData['operations'][$param][$operator] = $requirementData['language']['text_operator_' . $operator] ?? $operator;
				}
			}

			$paramsSet4 = ['product_stock', 'date', 'time'];
			foreach ($paramsSet4 as $param) {
				$requirementData['operations'][$param] = [];
				foreach (['eq', 'neq', 'gte', 'lte'] as $operator) {
					$requirementData['operations'][$param][$operator] = $requirementData['language']['text_operator_' . $operator] ?? $operator;
				}
			}

			$requirementData['values'] = [];
			$requirementData['value_types'] = [
				'checkbox' => [],
				'date'     => ['date'],
				'time'     => ['time'],
				'datetime' => [],
			];

			$this->load->model('catalog/category');
			$categories = $this->model_catalog_category->getCategories(['sort' => 'name']);
			foreach ($categories as $category) {
				$requirementData['values']['product_category'][$category['category_id']] = preg_replace("/\'\"/", "", $category['name']);
			}
			$requirementData['value_types']['checkbox'][] = 'product_category';

			$this->load->model('catalog/manufacturer');
			$manufacturers = $this->model_catalog_manufacturer->getManufacturers();
			foreach ($manufacturers as $manufacturer) {
				$requirementData['values']['product_manufacturer'][$manufacturer['manufacturer_id']] = htmlspecialchars($manufacturer['name'], ENT_QUOTES, 'UTF-8');
			}
			$requirementData['value_types']['checkbox'][] = 'product_manufacturer';

			$this->load->model('setting/store');
			$requirementData['values']['customer_store'][0] = htmlspecialchars((string)$this->config->get('config_name'), ENT_QUOTES, 'UTF-8');
			$stores = $this->model_setting_store->getStores();
			foreach ($stores as $store) {
				$requirementData['values']['customer_store'][$store['store_id']] = htmlspecialchars($store['name'], ENT_QUOTES, 'UTF-8');
			}
			$requirementData['value_types']['checkbox'][] = 'customer_store';

			$this->load->model('customer/customer_group');
			$requirementData['values']['customer_group'][0] = $requirementData['language']['text_guest_checkout'] ?? 'Guest Checkout';
			$customerGroups = $this->model_customer_customer_group->getCustomerGroups();
			foreach ($customerGroups as $customerGroup) {
				$requirementData['values']['customer_group'][$customerGroup['customer_group_id']] = htmlspecialchars($customerGroup['name'], ENT_QUOTES, 'UTF-8');
			}
			$requirementData['value_types']['checkbox'][] = 'customer_group';

			$this->load->model('localisation/currency');
			$currencies = $this->model_localisation_currency->getCurrencies();
			foreach ($currencies as $currency) {
				$requirementData['values']['currency'][$currency['code']] = htmlspecialchars($currency['title'], ENT_QUOTES, 'UTF-8');
			}

			for ($day = 1; $day <= 7; $day++) {
				$requirementData['values']['day'][$day] = $requirementData['language']['day_' . $day] ?? (string)$day;
			}
			$requirementData['value_types']['checkbox'][] = 'day';

			$requirementData['parameters'] = [];
			foreach ($reqTypes['product'] as $param) {
				foreach (['any', 'all', 'none'] as $x) {
					$requirementData['parameters']['product_' . $param]['match'][$x] = $requirementData['language']['text_product_match_' . $x] ?? $x;
				}
			}

			$requirementData['parameters']['customer_postcode']['type'] = [
				'other' => $requirementData['language']['text_postcode_type_other'] ?? 'Non UK',
				'uk'    => $requirementData['language']['text_postcode_type_uk'] ?? 'UK Format',
			];

			$this->load->model('catalog/option');
			$options = $this->model_catalog_option->getOptions();
			foreach ($options as $option) {
				$optKey = 'product_option_' . $option['option_id'];
				$requirementData['requirement_types']['product_option'][$optKey] = htmlspecialchars($option['name'], ENT_QUOTES, 'UTF-8');

				$operators = match ($option['type']) {
					'text' => ['eq', 'neq', 'gte', 'lte', 'strpos', 'nstrpos'],
					'textarea' => ['eq', 'neq', 'strpos', 'nstrpos'],
					'date', 'time', 'datetime' => ['eq', 'neq', 'gte', 'lte'],
					default => ['eq', 'neq'],
				};

				foreach ($operators as $op) {
					$requirementData['operations'][$optKey][$op] = $requirementData['language']['text_operator_' . $op] ?? $op;
				}

				// OpenCart 4 renamed getOptionValues() → getValues()
				$optionValues = $this->model_catalog_option->getValues((int)$option['option_id']);
				foreach ($optionValues as $optVal) {
					$requirementData['values'][$optKey][$optVal['option_value_id']] = htmlspecialchars($optVal['name'], ENT_QUOTES, 'UTF-8');
				}

				if (isset($requirementData['value_types'][$option['type']])) {
					$requirementData['value_types'][$option['type']][] = $optKey;
				}

				foreach (['any', 'all', 'none'] as $x) {
					$requirementData['parameters'][$optKey]['match'][$x] = $requirementData['language']['text_product_match_' . $x] ?? $x;
				}
			}

			$this->load->model('catalog/attribute');
			$attributes = $this->model_catalog_attribute->getAttributes();
			foreach ($attributes as $attr) {
				$attrKey = 'product_attribute_' . $attr['attribute_id'];
				$requirementData['requirement_types']['product_attribute'][$attrKey] = htmlspecialchars($attr['name'], ENT_QUOTES, 'UTF-8');

				foreach (['eq', 'neq'] as $op) {
					$requirementData['operations'][$attrKey][$op] = $requirementData['language']['text_operator_' . $op] ?? $op;
				}

				foreach (['any', 'all', 'none'] as $x) {
					$requirementData['parameters'][$attrKey]['match'][$x] = $requirementData['language']['text_product_match_' . $x] ?? $x;
				}
			}

			$this->load->model('customer/custom_field');
			$customFields = $this->model_customer_custom_field->getCustomFields();
			foreach ($customFields as $cf) {
				$cfKey = 'customer_customfield_' . $cf['custom_field_id'];
				$requirementData['requirement_types']['customer_customfield'][$cfKey] = htmlspecialchars($cf['name'], ENT_QUOTES, 'UTF-8');

				$operators = match ($cf['type']) {
					'text' => ['eq', 'neq', 'gte', 'lte', 'strpos', 'nstrpos'],
					'textarea' => ['eq', 'neq', 'strpos', 'nstrpos'],
					'date', 'time', 'datetime' => ['eq', 'neq', 'gte', 'lte'],
					default => ['eq', 'neq'],
				};

				foreach ($operators as $op) {
					$requirementData['operations'][$cfKey][$op] = $requirementData['language']['text_operator_' . $op] ?? $op;
				}

				// OpenCart 4 renamed getCustomFieldValues() → getValues()
				$cfValues = $this->model_customer_custom_field->getValues((int)$cf['custom_field_id']);
				foreach ($cfValues as $cfVal) {
					$requirementData['values'][$cfKey][$cfVal['custom_field_value_id']] = htmlspecialchars($cfVal['name'], ENT_QUOTES, 'UTF-8');
				}

				if (isset($requirementData['value_types'][$cf['type']])) {
					$requirementData['value_types'][$cf['type']][] = $cfKey;
				}
			}

			$data = [
				'requirement_types' => $requirementData['requirement_types'],
				'operations'        => $requirementData['operations'],
				'values'            => $requirementData['values'],
				'value_types'       => $requirementData['value_types'],
				'parameters'        => $requirementData['parameters'],
			];

			if (!empty($this->field('cache'))) {
				$this->cache->set($cacheKey, $data);
			}
		}

		return $data;
	}

	public function requirement(): void {
		$json = [];
		if ($this->validate(true)) {
			$requirements = $this->requirements();
			$data = $this->load->language($this->route);
			$type = (string)($this->request->get['type'] ?? '');

			if ($type !== '') {
				$json['success'] = true;
				$json['requirement_type_group'] = null;

				foreach ($requirements['requirement_types'] as $group => $types) {
					foreach ($types as $paramKey => $param) {
						if ($paramKey === $type) {
							$groupText = $data['text_requirement_group_' . $group] ?? '';
							$json['requirement_type_group'] = substr($groupText, 0, -1);
							break;
						}
					}
					if ($json['requirement_type_group']) {
						break;
					}
				}

				if (!empty($requirements['operations'][$type])) {
					$json['operations'] = [];
					foreach ($requirements['operations'][$type] as $key => $val) {
						$json['operations'][$key] = $val;
					}
				}

				if (!empty($requirements['values'][$type])) {
					$json['values'] = [];
					foreach ($requirements['values'][$type] as $key => $val) {
						$json['values'][' ' . $key] = $val;
					}
				}

				if (!empty($requirements['parameters'][$type])) {
					$json['parameters'] = [];
					foreach ($requirements['parameters'][$type] as $key => $param) {
						foreach ($param as $paramKey => $val) {
							$json['parameters'][$key][$paramKey] = $val;
						}
						$json['parameter_tooltip'] = $data['tooltip_' . $type . '_' . $key] ?? null;
					}
				}

				$json['value_type'] = 'select';
				foreach ($requirements['value_types'] as $key => $values) {
					if (in_array($type, $values, true)) {
						$json['value_type'] = $key;
						$json['value_tooltip'] = $data['tooltip_' . $key] ?? null;
					}
				}

				if (isset($data['tooltip_' . $type])) {
					$json['value_tooltip'] = $data['tooltip_' . $type];
				} elseif (!empty($json['values'])) {
					$json['value_tooltip'] = $data['tooltip_' . $json['value_type']] ?? null;
				} else {
					$json['value_tooltip'] = $data['tooltip_text'] ?? null;
				}
			}
		} else {
			$json['error'] = $this->language->get('text_error_not_valid');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function ratetypes(): array {
		$this->load->language($this->route);
		$rateTypes = [];
		$groups = [
			'cart'    => ['quantity', 'total', 'weight', 'dim_weight', 'volume', 'length', 'width', 'height', 'distance'],
			'product' => ['quantity', 'total', 'weight', 'volume', 'length', 'width', 'height'],
		];

		foreach ($groups as $group => $types) {
			foreach ($types as $type) {
				$rateTypes[$group][$group . '_' . $type] = $this->language->get('text_rate_type_' . $group . '_' . $type);
			}
		}

		$rateTypes['other'] = [];
		$this->load->model('setting/extension');
		// OpenCart 4: getExtensions() takes no type; use getExtensionsByType()
		$shippingMethods = $this->model_setting_extension->getExtensionsByType('shipping');

		foreach ($shippingMethods as $sm) {
			$code = $sm['code'] ?? '';
			$ext  = $sm['extension'] ?? 'opencart';
			if ($code !== '' && $code !== $this->extension && $code !== 'ocapps') {
				$this->load->language('extension/' . $ext . '/shipping/' . $code);
				$rateTypes['other'][$code] = strip_tags($this->language->get('heading_title'));
			}
		}

		return $rateTypes;
	}

	private function geozones(): array {
		$this->load->language($this->route);
		$this->load->model('localisation/geo_zone');

		$geoZones = [];
		$dbZones  = $this->model_localisation_geo_zone->getGeoZones();
		foreach ($dbZones as $gz) {
			$geoZones[$gz['geo_zone_id']] = $gz['name'];
		}
		$geoZones[0] = $this->language->get('text_all_other_zones') ?: 'Rest of World';

		return $geoZones;
	}

	private function weightUnit(): string {
		$this->load->model('localisation/weight_class');
		$weightClassId = (int)$this->config->get('config_weight_class_id');
		$weightClass   = $this->model_localisation_weight_class->getWeightClass($weightClassId);

		return $weightClass['unit'] ?? (string)$weightClassId;
	}

	private function lengthUnit(): string {
		$this->load->model('localisation/length_class');
		$lengthClassId = (int)$this->config->get('config_length_class_id');
		$lengthClass   = $this->model_localisation_length_class->getLengthClass($lengthClassId);

		return $lengthClass['unit'] ?? (string)$lengthClassId;
	}

	private function currencySymbol(string $currency): string {
		$query = $this->db->query("SELECT DISTINCT * FROM `" . DB_PREFIX . "currency` WHERE `code` = '" . $this->db->escape($currency) . "'");
		if (!empty($query->row['symbol_left'])) {
			return $query->row['symbol_left'];
		}
		if (!empty($query->row['symbol_right'])) {
			return $query->row['symbol_right'];
		}

		return '';
	}

	private function geocode(string $origin): array|bool {
		$origin = trim($origin);
		if ($origin === '') {
			return false;
		}

		$apiKey = (string)$this->field('gmaps_api_key');
		$url = 'https://maps.googleapis.com/maps/api/geocode/xml?address=' . urlencode($origin) . '&sensor=false';
		if ($apiKey !== '') {
			$url .= '&key=' . urlencode($apiKey);
		}

		try {
			$context = stream_context_create(['http' => ['timeout' => 5]]);
			$xmlContent = @file_get_contents($url, false, $context);
			if ($xmlContent !== false) {
				$response = @simplexml_load_string($xmlContent);
				if ($response && (string)$response->status === 'OK') {
					return [
						'lat' => (float)$response->result->geometry->location->lat,
						'lng' => (float)$response->result->geometry->location->lng,
					];
				}
			}
		} catch (\Throwable $e) {
			// Ignore network errors gracefully
		}

		return false;
	}

	private function csv(): array {
		$instructions = 'ADDING OR REMOVING COLUMNS MAY CAUSE PROBLEMS WHEN ATTEMPTING TO IMPORT YOUR SHIPPING RATES';
		$fields = [];
		$this->load->model('extension/advancedshipping/shipping/advancedshipping');
		$data = $this->model_extension_advancedshipping_shipping_advancedshipping->settings();

		foreach ($data as $key => $val) {
			$fields[] = $key;
		}

		return [
			'instructions' => $instructions,
			'fields'       => $fields,
			'row_offset'   => 1,
			'col_offset'   => 0,
		];
	}

	public function import(string $file = ''): void {
		$this->validate();
		$this->load->language($this->route);

		if ($file !== '') {
			$file = DIR_LOGS . $file;
		} elseif (isset($this->request->files['import']) && is_uploaded_file($this->request->files['import']['tmp_name'])) {
			$file = $this->request->files['import']['tmp_name'];
		}

		if ($file !== '' && file_exists($file)) {
			$this->load->model('extension/advancedshipping/shipping/advancedshipping');

			$changes  = ['added' => 0, 'updated' => 0];
			$csvInfo  = $this->csv();
			$row      = 0;
			$fields   = [];

			if (($handle = fopen($file, 'r')) !== false) {
				while (($data = fgetcsv($handle, 4000, ',')) !== false) {
					if ($row > $csvInfo['row_offset']) {
						$col = $csvInfo['col_offset'];
						$rateInfo = [];
						foreach ($fields as $field) {
							$val = isset($data[$col]) ? $this->value($data[$col]) : '';
							$col++;
							$rateInfo[trim($field)] = $val;
						}

						$rateId = (int)($rateInfo['rate_id'] ?? 0);
						if ($rateId > 0 && $this->model_extension_advancedshipping_shipping_advancedshipping->getRate($rateId)) {
							$this->model_extension_advancedshipping_shipping_advancedshipping->editRate($rateId, $rateInfo);
							$changes['updated']++;
						} else {
							$this->model_extension_advancedshipping_shipping_advancedshipping->addRate($rateInfo);
							$changes['added']++;
						}
						$row++;
					} elseif ($row === $csvInfo['row_offset']) {
						$fields = $data;
						$row++;
					} else {
						$row++;
					}
				}
				fclose($handle);
			}

			$this->session->data['success'] = sprintf($this->language->get('text_success_import'), $changes['added'], $changes['updated']);
		} else {
			$this->session->data['error'] = $this->language->get('text_error_import');
		}

		$this->response->redirect($this->link($this->route));
	}

	public function export(bool $return = false): ?string {
		$this->validate();
		$this->load->model('extension/advancedshipping/shipping/advancedshipping');
		$rates = $this->model_extension_advancedshipping_shipping_advancedshipping->getRates();

		if (!empty($rates)) {
			$csvInfo = $this->csv();
			$output  = '"' . str_replace('"', '""', $csvInfo['instructions']) . '"' . "\n";

			$x = 1;
			foreach ($csvInfo['fields'] as $field) {
				$output .= ($x > 1 ? ',"' : '"') . str_replace('"', '""', (string)$field) . '"';
				$x++;
			}
			$output .= "\n";

			foreach ($rates as $rate) {
				$rateInfo = $this->model_extension_advancedshipping_shipping_advancedshipping->getRate((int)$rate['rate_id']);
				if ($rateInfo) {
					$x = 1;
					foreach ($csvInfo['fields'] as $field) {
						$val = is_array($rateInfo[$field] ?? '') ? json_encode($rateInfo[$field]) : (string)($rateInfo[$field] ?? '');
						$output .= ($x > 1 ? ',"' : '"') . str_replace('"', '""', $val) . '"';
						$x++;
					}
					$output .= "\n";
				}
			}

			if ($return) {
				return $output;
			}

			$this->response->addHeader('Pragma: public');
			$this->response->addHeader('Expires: 0');
			$this->response->addHeader('Content-Description: File Transfer');
			$this->response->addHeader('Content-Type: text/csv');
			$this->response->addHeader('Content-Disposition: attachment; filename=' . date('Y-m-d_H-i-s') . '_' . $this->extension . '.csv');
			$this->response->addHeader('Content-Transfer-Encoding: binary');
			$this->response->setOutput($output);
		}

		return null;
	}

	public function downloadDebug(): void {
		$this->validate();
		$debugFile = DIR_LOGS . $this->extension . '.txt';

		if (file_exists($debugFile)) {
			if (filesize($debugFile) >= 5242880) {
				$debug = 'Debug Log Filesize Is Too Large';
			} else {
				$debug = file_get_contents($debugFile);
			}
		} else {
			$debug = 'Debug Log Is Empty';
		}

		$this->response->addHeader('Pragma: public');
		$this->response->addHeader('Expires: 0');
		$this->response->addHeader('Content-Description: File Transfer');
		$this->response->addHeader('Content-Type: text/plain');
		$this->response->addHeader('Content-Disposition: attachment; filename=' . $this->extension . '.txt');
		$this->response->addHeader('Content-Transfer-Encoding: binary');
		$this->response->setOutput($debug);
	}

	public function clearDebug(): void {
		$this->validate(true);
		$debugFile = DIR_LOGS . $this->extension . '.txt';
		file_put_contents($debugFile, '');

		$this->load->language($this->route);
		$json = ['success' => $this->language->get('text_success_debug_clear')];

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function reloadDebug(): void {
		$this->validate(true);
		$this->load->language($this->route);
		$debugFile = DIR_LOGS . $this->extension . '.txt';

		if (file_exists($debugFile)) {
			if (filesize($debugFile) >= 5242880) {
				$debug = 'Debug Log Filesize Is Too Large';
			} else {
				$debug = file_get_contents($debugFile);
			}
		} else {
			$debug = 'Debug Log Is Empty';
		}

		$json = [
			'debug_log' => $debug,
			'success'   => $this->language->get('text_success_debug_reload'),
		];

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function clearCache(): void {
		$this->validate(true);
		$this->cache->delete($this->extension . '_rates');
		$this->cache->delete($this->extension . '_requirements');

		$this->load->language($this->route);
		$json = ['success' => $this->language->get('text_success_cache_clear')];

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * AJAX endpoint: create backup and return JSON.
	 * Do not call this from HTML page actions — use writeBackup() instead so
	 * Content-Type: application/json is not left on an HTML response.
	 */
	public function createBackup(string $comment = ''): void {
		$this->validate(true);
		$this->load->language($this->route);

		$comment = !empty($this->request->get['comment']) ? (string)$this->request->get['comment'] : $comment;
		$this->writeBackup($comment);

		$json = ['success' => $this->language->get('text_success_backup')];
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Write a backup file without touching response headers/output.
	 * Safe to call from index(), delete(), restoreBackup(), etc.
	 */
	private function writeBackup(string $comment = ''): bool {
		if (empty($this->field('backup'))) {
			return false;
		}

		$cleanComment = preg_replace('/[^\w\-]/', '_', $comment) ?: 'backup';
		$file         = DIR_LOGS . $this->extension . '_backup_' . time() . '_' . $cleanComment . '.csv';
		$backupData   = $this->export(true);

		if ($backupData) {
			file_put_contents($file, $backupData);

			return true;
		}

		return false;
	}

	public function getBackups(): array {
		$backupData = [];
		$files = array_slice(scandir(DIR_LOGS), 2);

		if ($files) {
			foreach ($files as $file) {
				$fileData = explode('_', str_replace('.csv', '', $file));
				if (($fileData[0] ?? '') === $this->extension && ($fileData[1] ?? '') === 'backup') {
					$backupData[] = [
						'file'    => $file,
						'date'    => $fileData[2] ?? '',
						'comment' => $fileData[3] ?? '',
					];
				}
			}
		}

		return $backupData;
	}

	public function restoreBackup(): void {
		$this->validate();
		// basename() prevents path traversal (e.g. ../../config.php via GET param)
		$file = !empty($this->request->get['file']) ? basename((string)$this->request->get['file']) : null;

		// Only allow files that belong to this extension
		if ($file && str_starts_with($file, $this->extension . '_backup_') && file_exists(DIR_LOGS . $file)) {
			$this->writeBackup('Backup Created Prior To Restore');
			$this->load->model('extension/advancedshipping/shipping/advancedshipping');
			$this->model_extension_advancedshipping_shipping_advancedshipping->deleteAllRates();
			$this->import($file);
		}
	}

	public function clearBackup(): void {
		$this->validate();
		// basename() prevents path traversal
		$file = !empty($this->request->get['file']) ? basename((string)$this->request->get['file']) : null;

		// Only allow files that belong to this extension
		if ($file && str_starts_with($file, $this->extension . '_backup_') && file_exists(DIR_LOGS . $file)) {
			unlink(DIR_LOGS . $file);
		}

		$this->session->data['success'] = $this->language->get('text_success_backup_clear');
		$this->response->redirect($this->link($this->route));
	}

	public function clearBackups(): void {
		$this->validate();
		$backups = $this->getBackups();
		foreach ($backups as $backup) {
			if (file_exists(DIR_LOGS . $backup['file'])) {
				unlink(DIR_LOGS . $backup['file']);
			}
		}

		$this->session->data['success'] = $this->language->get('text_success_backup_clear');
		$this->response->redirect($this->link($this->route));
	}

	public function support(): void {
		$json = [];
		$this->load->language($this->route);

		if ($this->validate(true) && isset($this->request->post)) {
			if (!empty($this->request->post['email']) && !empty($this->request->post['order_id']) && !empty($this->request->post['enquiry'])) {
				$text  = "Extension: " . $this->language->get('text_name') . "\n";
				$text .= "Version: " . $this->version . "\n";
				$text .= defined('VERSION') ? "OpenCart Version: " . VERSION . "\n" : "OpenCart Version: N/A \n";
				$text .= "Website: " . HTTP_CATALOG . "\n";
				$text .= "Email: " . $this->request->post['email'] . "\n";
				$text .= "Order ID: " . $this->request->post['order_id'] . "\n\n";
				$text .= "Support Question:\n";
				$text .= $this->request->post['enquiry'];

				try {
					$mailOption = [
						'engine'        => $this->config->get('config_mail_engine') ?? 'mail',
						'smtp_hostname' => $this->config->get('config_smtp_host'),
						'smtp_username' => $this->config->get('config_smtp_username'),
						'smtp_password' => $this->config->get('config_smtp_password'),
						'smtp_port'     => $this->config->get('config_smtp_port'),
						'smtp_timeout'  => $this->config->get('config_smtp_timeout'),
					];
					$mail = new \Opencart\System\Library\Mail($mailOption['engine'], $mailOption);
					$mail->setTo($this->email);
					$mail->setFrom((string)$this->request->post['email']);
					$mail->setSender((string)$this->config->get('config_name'));
					$mail->setSubject($this->language->get('text_name') . ' Support Request');
					$mail->setText(html_entity_decode($text, ENT_QUOTES, 'UTF-8'));
					$mail->send();
				} catch (\Throwable $e) {
					// Graceful exception catch for mail engine
				}

				$json['success'] = $this->language->get('text_success_support');
			} else {
				$json['error'] = $this->language->get('text_error_support');
			}
		} else {
			$json['error'] = $this->language->get('text_error_not_valid');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function link(string $route, string $params = ''): string {
		$args = 'user_token=' . $this->session->data['user_token'];
		if ($params !== '') {
			$args .= '&' . ltrim($params, '&');
		}

		return $this->url->link($route, $args);
	}

	private function field(string $field): mixed {
		$key = 'shipping_' . $this->extension . '_' . $field;
		$val = $this->config->get($key);

		return $this->value($val);
	}

	private function value(mixed $val): mixed {
		if (is_string($val)) {
			$decoded = json_decode($val, true);
			if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
				return $decoded;
			}
		}

		return $val;
	}

	private function validateSetting(string $key, mixed $value): array {
		$errors = [];
		$this->load->language($this->route);

		$combineRegex = [
			'sum' => '/SUM\(([A-Z0-9\,\.\{\}]+)\)/',
			'avg' => '/AVG\(([A-Z0-9\,\.\{\}]+)\)/',
			'min' => '/MIN\(([A-Z0-9\,\.\{\}]+)\)/',
			'max' => '/MAX\(([A-Z0-9\,\.\{\}]+)\)/',
		];

		if ($key === 'combinations' && is_array($value)) {
			foreach ($value as $combination_key => $result) {
				$formula = strtoupper(preg_replace('/\s+/', '', $result['formula'] ?? ''));
				$combine_status = true;
				if ($formula !== '') {
					if (substr_count($formula, '(') === substr_count($formula, ')') && substr_count($formula, '{') === substr_count($formula, '}')) {
						while ($combine_status && preg_match('/(SUM|AVG|MIN|MAX)\(([A-Z0-9\,\.\{\}]+)\)/', $formula)) {
							$before = $formula;
							foreach ($combineRegex as $regex_key => $regex_value) {
								preg_match($regex_value, $formula, $matches);
								$x = 1;
								while (isset($matches[$x])) {
									$rate_groups = explode(',', $matches[$x]);
									foreach ($rate_groups as $rate_group) {
										$rate_group_value = 0.0;
										$cost = 4.0;
										if ($regex_key === 'sum') {
											$rate_group_value += $cost;
										}
										if ($regex_key === 'avg') {
											$rate_group_value = $cost;
										}
										if ($regex_key === 'min' || $regex_key === 'max') {
											$rate_group_value = $cost;
										}
										$matches[$x] = str_replace($rate_group, (string)$rate_group_value, $matches[$x]);
									}
									if (preg_match('/^([0-9\,\.\+\-\*\/\(\)]+)$/', $matches[$x])) {
										$parts = array_map('floatval', explode(',', $matches[$x]));
										$rate_group_cost = match ($regex_key) {
											'sum' => array_sum($parts),
											'avg' => array_sum($parts) / count($parts),
											'min' => min($parts),
											'max' => max($parts),
											default => 0.0,
										};
										$formula = str_replace($matches[0], (string)$rate_group_cost, $formula);
									}
									$x++;
								}
							}
							// Guard against malformed formulas (e.g. SUM({A}{B})) that never resolve
							if ($formula === $before) {
								$combine_status = false;
							}
						}
						if (!preg_match('/^([0-9\,\.\+\-\*\/\(\)]+)$/', $formula) || substr_count($formula, '(') !== substr_count($formula, ')')) {
							$errors['combinations_' . $combination_key . '_formula'] = $this->language->get('text_error_combinations_formula');
						}
					} else {
						$errors['combinations_' . $combination_key . '_formula'] = $this->language->get('text_error_combinations_formula_brackets');
					}
				}
			}
		}

		return $errors;
	}

	private function validateRate(array $value): array {
		$rate_errors = [];

		$postcode_formats = [
			'/^([0-9a-zA-Z]+)$/',
			'/^([0-9a-zA-Z]+):([0-9a-zA-Z]+)$/',
		];

		$uk_formats = [
			'/^([a-zA-Z]{2}[0-9]{1}[a-zA-Z]{1}[0-9]{1}[a-zA-Z]{2})$/',
			'/^([a-zA-Z]{1}[0-9]{1}[a-zA-Z]{1}[0-9]{1}[a-zA-Z]{2})$/',
			'/^([a-zA-Z]{1}[0-9]{2}[a-zA-Z]{2})$/',
			'/^([a-zA-Z]{1}[0-9]{3}[a-zA-Z]{2})$/',
			'/^([a-zA-Z]{2}[0-9]{2}[a-zA-Z]{2})$/',
			'/^([a-zA-Z]{2}[0-9]{3}[a-zA-Z]{2})$/',
		];

		if (!empty($value['group']) && !preg_match('/^([a-zA-Z0-9\,]+)$/', (string)$value['group'])) {
			$rate_errors['group'] = sprintf($this->language->get('text_error_rate_group'));
		}

		if (empty($value['shipping']) || !is_array($value['shipping'])) {
			$rate_errors['shipping'] = sprintf($this->language->get('text_error_shipping'));
		} else {
			foreach ($value['shipping'] as $geo_zone_id => $shipping) {
				if (is_array($shipping)) {
					$rateType = (string)($shipping['rate_type'] ?? '');
					if ((str_contains($rateType, 'dim_weight')) && empty($shipping['shipping_factor'])) {
						$rate_errors['shipping_' . $geo_zone_id . '_shipping_factor'] = sprintf($this->language->get('text_error_rate_shipping_factor'));
					}
					if (str_contains($rateType, 'distance') && empty($value['origin'])) {
						$rate_errors['origin'] = sprintf($this->language->get('text_error_rate_origin'));
					}
					if (str_starts_with($rateType, 'cart_') || str_starts_with($rateType, 'product_')) {
						if (!empty($shipping['rates']) && is_array($shipping['rates'])) {
							foreach ($shipping['rates'] as $rate_row => $rate) {
								if (empty($rate['max']) && ($rate['max'] ?? '') !== '0' && ($rate['max'] ?? '') !== 0) {
									$rate_errors['shipping_' . $geo_zone_id . '_rates_max_' . $rate_row] = sprintf($this->language->get('text_error_rate_rates_max'));
								}
								if (empty($rate['cost']) && ($rate['cost'] ?? '') !== '0' && ($rate['cost'] ?? '') !== 0) {
									$rate_errors['shipping_' . $geo_zone_id . '_rates_cost_' . $rate_row] = sprintf($this->language->get('text_error_rate_rates_cost'));
								}
							}
						}
					} elseif (empty($shipping['shipping_method'])) {
						$rate_errors['shipping_' . $geo_zone_id . '_shipping_method'] = $this->language->get('text_error_rate_shipping_method');
					}
				}
			}
		}

		if (!empty($value['requirements']) && is_array($value['requirements'])) {
			foreach ($value['requirements'] as $key => $requirement) {
				$reqType = (string)($requirement['type'] ?? '');
				if ($reqType === 'customer_postcode') {
					if (!empty($requirement['value'])) {
						$postcode_ranges = explode(',', (string)$requirement['value']);
						foreach ($postcode_ranges as $postcode_range) {
							$postcode_format_match = false;
							$postcode_range = trim($postcode_range);
							foreach ($postcode_formats as $postcode_format) {
								if (preg_match($postcode_format, $postcode_range)) {
									$postcode_format_match = true;
									$paramType = (string)($requirement['parameter']['type'] ?? '');
									if ($paramType === 'uk') {
										$postcodes = explode(':', $postcode_range);
										$postcodes[0] = trim($postcodes[0]);
										$postcode_uk_format_match = false;
										foreach ($uk_formats as $uk_format) {
											if (preg_match($uk_format, $postcodes[0])) {
												$postcode_uk_format_match = true;
												break;
											}
										}
										if (!$postcode_uk_format_match) {
											$rate_errors['requirement_' . $key] = sprintf($this->language->get('text_error_rate_postcode_formatting'), $postcodes[0]);
										}
										if (!empty($postcodes[1])) {
											$postcodes[1] = trim($postcodes[1]);
											$postcode_uk_format_match2 = false;
											foreach ($uk_formats as $uk_format) {
												if (preg_match($uk_format, $postcodes[1])) {
													$postcode_uk_format_match2 = true;
													break;
												}
											}
											if (!$postcode_uk_format_match2) {
												$rate_errors['requirement_' . $key] = sprintf($this->language->get('text_error_rate_postcode_formatting'), $postcodes[1]);
											}
										}
									}
									break;
								}
							}
							if (!$postcode_format_match) {
								$rate_errors['requirement_' . $key] = sprintf($this->language->get('text_error_rate_postcode_range_formatting'), $postcode_range);
							}
						}
					} else {
						$rate_errors['requirement_' . $key] = $this->language->get('text_error_rate_requirement');
					}
				} elseif ($reqType === 'cart_distance' && empty($value['origin'])) {
					$rate_errors['origin'] = $this->language->get('text_error_rate_origin');
				} elseif (empty($requirement['value'])) {
					$rate_errors['requirement_' . $key] = $this->language->get('text_error_rate_requirement');
				}
			}
		}

		return $rate_errors;
	}

	private function validate(bool $isJson = false): bool {
		$this->load->language($this->route);
		if (!$this->user->hasPermission('modify', $this->route)) {
			$this->session->data['error'] = $this->language->get('text_error_not_valid');
			if (!$isJson) {
				$this->response->redirect($this->link($this->route));
			}
			return false;
		}

		return true;
	}

	public function install(): void {
		$this->load->model('extension/advancedshipping/shipping/advancedshipping');
		$this->model_extension_advancedshipping_shipping_advancedshipping->install();

		$this->load->model('setting/setting');
		$settingCode = 'shipping_' . $this->extension;
		$settingKey  = 'shipping_' . $this->extension . '_backup';
		$this->model_setting_setting->editSetting($settingCode, [$settingKey => 1]);
	}

	public function uninstall(): void {
		$this->load->model('extension/advancedshipping/shipping/advancedshipping');
		$this->model_extension_advancedshipping_shipping_advancedshipping->uninstall();
	}
}
