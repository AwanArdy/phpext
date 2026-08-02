<?php
//==================================================//
// Product:	Intuitive Shipping              		//
// Author: 	Joel Reeds                        		//
// Company: OpenCart Addons                  		//
// Website: http://www.opencartaddons.com        	//
// Contact: http://www.opencartaddons.com/contact  	//
//==================================================//

class ControllerExtensionShippingIntuitiveShipping extends Controller {
	private $error 				= array();

	private $version 			= '1.3.2_oc23';
	private $type 				= 'shipping';
	private $extension 			= 'intuitiveshipping';

	private $db_table			= 'intuitive_shipping';

	private $href				= 'http://www.intuitiveshipping.com/';

	private $email				= 'contact@opencartaddons.com';

	public function index() {
		$this->load->model('extension/' . $this->type . '/' . $this->extension);

		//Check Updates
		$update = $this->{'model_extension_' . $this->type . '_' . $this->extension}->update();
		if ($update['status']) {
			$this->session->data['success'] = $update['log'];
			$this->response->redirect($this->link('extension/' . $this->type . '/' . $this->extension, null));
		}

		//Auto Backup
		if ($this->field('backup')) {
			$backup_status 	= true;
			
			$backups 		= $this->getBackups();
			foreach ($backups as $backup) {
				if (($backup['date'] + 86400) > time()) {
					$backup_status = false;
					break;
				}
			}
			if ($backup_status) {
				$this->createBackup('Automatic Backup');
			}
		}

		$data 								= array();
		$data['text']						= $this->load->language('extension/' . $this->type . '/' . $this->extension);

		$data['type'] 						= $this->type;
		$data['extension'] 					= $this->extension;
		$data['version'] 					= $this->version();

		$data['token']						= ($this->version() >= 3.0) ? 'user_token=' . $this->session->data['user_token'] : 'token=' . $this->session->data['token'];

		$data['text']['text_footer'] 		= sprintf($data['text']['text_footer'], $this->version);

		//Check If Demo
		if (!empty($this->request->server['HTTP_HOST']) && strpos($this->request->server['HTTP_HOST'], 'demo.opencartaddons.com') !== false) {
			$data['demo'] = $this->href . 'purchase/?platform=opencart';
		} else {
			$data['demo'] = false;
		}

		$data['debug_download']				= $this->link('extension/' . $this->type . '/' . $this->extension . '/downloadDebug');
		$data['debug_clear']				= $this->link('extension/' . $this->type . '/' . $this->extension . '/clearDebug');
		$data['debug_reload']				= $this->link('extension/' . $this->type . '/' . $this->extension . '/reloadDebug');
		$data['debug_log'] 					= null;

		$debug_file = DIR_LOGS . $this->extension . '.txt';
		if (file_exists($debug_file)) {
			$debug_file_size = filesize($debug_file);
			if ($debug_file_size >= 5242880) {
				$suffix = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
				$i = 0;
				while (($debug_file_size / 1024) > 1) {
					$debug_file_size = $debug_file_size / 1024;
					$i++;
				}
				$data['warning'] = sprintf($data['text']['text_error_debug'], round(substr($debug_file_size, 0, strpos($debug_file_size, '.') + 4), 2) . $suffix[$i]);
			} else {
				$data['debug_log'] = file_get_contents($debug_file, FILE_USE_INCLUDE_PATH, null);
			}
		}
		
		$data['cache_clear']		= $this->link('extension/' . $this->type . '/' . $this->extension . '/clearCache');

		$data['backups'] = array();
		
		$backups = $this->getBackups();
		foreach ($backups as $backup) {
			$data['backups'][]		= array(
				'file'		=> $backup['file'],
				'date'		=> date('Y-m-d H:i:s', $backup['date']),
				'comment'	=> $backup['comment'],
			);
		}
		
		$data['backup_restore']		= $this->link('extension/' . $this->type . '/' . $this->extension . '/restoreBackup');
		$data['backup_clear']		= $this->link('extension/' . $this->type . '/' . $this->extension . '/clearBackup');
		$data['backup_clear_all']	= $this->link('extension/' . $this->type . '/' . $this->extension . '/clearBackups');
		
		$data['action_cancel']		= ($this->version() >= 3.0) ? $this->link('marketplace/extension', 'type=' . $this->type) : $this->link('extension/extension');
		$data['action_add']			= $this->link('extension/' . $this->type . '/' . $this->extension . '/add');
		$data['action_delete']		= $this->link('extension/' . $this->type . '/' . $this->extension . '/delete');
		$data['action_import']		= $this->link('extension/' . $this->type . '/' . $this->extension . '/import');
		$data['action_export']		= $this->link('extension/' . $this->type . '/' . $this->extension . '/export');

		$data['success']			= isset($this->session->data['success']) ? $this->session->data['success'] : null;
		$data['warning'] 			= isset($this->session->data['warning']) ? $this->session->data['warning'] : null;
		$data['error'] 				= isset($this->session->data['error']) ? $this->session->data['error'] : null;

		unset($this->session->data['success']);
		unset($this->session->data['warning']);
		unset($this->session->data['error']);

  		$fields = array('status', 'test', 'title', 'sort_order', 'ocapps_status', 'sort_quotes', 'display_value', 'debug', 'cache', 'backup');
		foreach ($fields as $field) {
			$data[$field]	= isset($this->request->post[$field]) ? $this->request->post[$field] : $this->field($field);
		}

		$options = array('sort_quote', 'title_display', 'combination_method');
		foreach ($options as $option) {
			$x = 0;
			$data['option_' . $option] = array();
			while (isset($data['text'][$option . '_' . $x])) {
				$data[$option][$x] = $data['text'][$option . '_' . $x];
				$x++;
			}
		}

		$data['rate_types']				= $this->ratetypes();
		$data['geo_zones']				= $this->geozones();

		$data['rates']					= array();
		$this->session->data['rates']	= array();

		$rates 							= $this->{'model_extension_' . $this->type . '_' . $this->extension}->getRates();
		if ($rates) {
			foreach ($rates as $rate) {
				foreach ($rate as $key => $value) {
					$rate[$key] = $this->value($value);
				}

				$this->session->data['rates'][] = $rate['rate_id'];

				$data['rates'][] = array(
					'rate_id'			=> $rate['rate_id'],
					'description'		=> ($rate['description']) ? $rate['description'] : $this->language->get('text_name'),
					'name'				=> !empty($rate['name'][$this->config->get('config_admin_language')]) ? $rate['name'][$this->config->get('config_admin_language')] : $this->language->get('text_name'),
					'status'			=> ($rate['status']) ? $data['text']['text_on'] : $data['text']['text_off'],
					'group'				=> ($rate['group']) ? $rate['group'] : '[None]',
					'edit'				=> $this->link('extension/' . $this->type . '/' . $this->extension . '/edit', 'rate_id=' . $rate['rate_id']),
				);
			}
		}

		$data['combinations'] = array();
		$combinations = isset($this->request->post[$this->extension . '_combinations']) ? $this->request->post[$this->extension . '_combinations'] : $this->config->get((($this->version() >= 3.0) ? $this->type . '_' : '') . $this->extension . '_combinations');
		if ($combinations) {
			foreach ($this->value($combinations) as $key => $value) {
				$data['combinations'][$key] = array(
					'key'				=> $key,
					'sort_order'		=> $value['sort_order'],
					'title_display'		=> $value['title_display'],
					'title'				=> $value['title'],
					'formula'			=> $value['formula'],
					'method'			=> isset($value['method']) ? $value['method'] : 0,
				);
			}
		}
		
		$this->load->model('localisation/language');
		$data['languages'] 	= $this->model_localisation_language->getLanguages();

		$data['ocapps_integration'] = $this->ocapps_status;

		$data['email']				= $this->config->get('config_email');

		$this->document->addStyle(HTTPS_SERVER . 'view/stylesheet/extension/' . $this->type . '/' . $this->extension . '.css?ver=' . rand(1000,9999));
		$this->document->addStyle('https://fonts.googleapis.com/css?family=Raleway:400,600,700');
		$this->document->addStyle('https://fonts.googleapis.com/css?family=Oswald:400,700');

		$this->document->setTitle($data['text']['text_name']);
		$data['header'] 		= $this->load->controller('common/header');
		$data['column_left'] 	= $this->load->controller('common/column_left');
		$data['footer'] 		= $this->load->controller('common/footer');
		$this->response->setOutput($this->load->view('extension/' . $this->type . '/' . $this->extension, $data));
	}

	public function autosave() {
		$json = array();

		if ($this->validate()) {
			if (($this->request->server['REQUEST_METHOD'] == 'POST')) {
				$this->load->model('setting/setting');

				$data = array();
				foreach ($this->request->post as $key => $value) {					
					$setting_code 		= (($this->version() >= 3.0) ? $this->type . '_' : '') . $this->extension;
					$setting_key		= (($this->version() >= 3.0) ? $this->type . '_' : '') . $this->extension . '_' . $key;
					
					$data[$setting_key] = $value;

					$errors = $this->validateSetting($key, $value);
					if (!$errors) {
						$this->model_setting_setting->editSetting($setting_code, $data);
					} else {
						foreach ($errors as $key => $value) {
							$json['error'][$key] = $value;
						}
					}
				}
			} else {
				$json['error'] = true;
			}
		} else {
			$json['error'] =  true;
		}
		$this->response->setOutput(json_encode($json));
	}

	public function filter() {
		$this->load->model('extension/' . $this->type . '/' . $this->extension);

		$this->load->language('extension/' . $this->type . '/' . $this->extension);

		$json = array();
		if (!empty($this->request->post)) {
			$rates = $this->{'model_extension_' . $this->type . '_' . $this->extension}->getRates($this->request->post);
		} else {
			$rates = array();
		}

		$json['rates'] 					= array();
		$this->session->data['rates']	= array();

		if ($rates) {
			$json['success'] = true;

			foreach ($rates as $rate) {
				foreach ($rate as $key => $value) {
					$rate[$key] = $this->value($value);
				}

				$this->session->data['rates'][] = $rate['rate_id'];

				$json['rates'][] = array(
					'rate_id'			=> $rate['rate_id'],
					'description'		=> ($rate['description']) ? $rate['description'] : $this->language->get('text_name'),
					'name'				=> !empty($rate['name'][$this->config->get('config_language')]) ? $rate['name'][$this->config->get('config_language')] : $this->language->get('text_name'),
					'status'			=> ($rate['status']) ? $this->language->get('text_on') : $this->language->get('text_off'),
					'group'				=> ($rate['group']) ? $rate['group'] : '[None]',
					'edit'				=> $this->link('extension/' . $this->type . '/' . $this->extension . '/edit', 'rate_id=' . $rate['rate_id']),
				);
			}
		}

		$this->response->setOutput(json_encode($json));
	}
	
	private function form($rate_id = 0) {
		$this->load->model('extension/' . $this->type . '/' . $this->extension);
		
		$data 								= array();
		$data['text']						= $this->load->language('extension/' . $this->type . '/' . $this->extension);

		$data['type'] 						= $this->type;
		$data['extension'] 					= $this->extension;
		$data['version'] 					= $this->version();

		$data['token']						= ($this->version() >= 3.0) ? 'user_token=' . $this->session->data['user_token'] : 'token=' . $this->session->data['token'];

		$data['text']['text_footer'] 		= sprintf($data['text']['text_footer'], $this->version);

		$data['success']					= isset($this->session->data['success']) ? $this->session->data['success'] : null;
		$data['error'] 						= isset($this->session->data['error']) ? $this->session->data['error'] : null;
		$data['rate_errors'] 				= isset($this->session->data['rate_errors']) ? $this->session->data['rate_errors'] : null;

		unset($this->session->data['success']);
		unset($this->session->data['error']);
		unset($this->session->data['rate_errors']);
		
		$post_data 							= isset($this->session->data['post_data']) ? $this->session->data['post_data'] : array();
		
		unset($this->session->data['post_data']);

		if ($rate_id) {
			$rate_data = $this->{'model_extension_' . $this->type . '_' . $this->extension}->getRate($rate_id);
			if (!$rate_data) {
				$this->session->data['error'] = $data['text']['text_error_rate_get'];
				$this->response->redirect($this->link('extension/' . $this->type . '/' . $this->extension));
			}
		} else {
			$rate_data = $this->{'model_extension_' . $this->type . '_' . $this->extension}->settings();
		}
		
		foreach ($rate_data as $key => $value) {
			$data['data'][$key] 					= isset($post_data[$key]) ? $post_data[$key] : $this->value($value);
		}

		$this->load->model('localisation/language');
		$data['languages'] 							= $this->model_localisation_language->getLanguages();

		$this->load->model('localisation/tax_class');
		$data['tax_classes'] 						= array_merge(array(array('tax_class_id' => 0, 'title' => $data['text']['text_none'])), $this->model_localisation_tax_class->getTaxClasses());

		$this->load->model('localisation/currency');
		$data['currencies']							= $this->model_localisation_currency->getCurrencies();
		$data['config_currency']					= $this->config->get('config_currency');

		$data['rate_types']							= $this->ratetypes();
		$data['geo_zones'] 							= $this->geozones();

		$data['rate_id'] 							= $rate_id;

		$data['text']['entry_shipping_factor']	= sprintf($data['text']['entry_shipping_factor'], $this->length(), $this->weight());

		$data['text']['text_total']				= $this->currency($this->config->get('currency'));
		$data['text']['text_weight']			= $this->weight();
		$data['text']['text_dim_weight']		= $this->weight();
		$data['text']['text_volume']			= $this->length() . '&sup3;';
		$data['text']['text_length']			= $this->length();
		$data['text']['text_width']				= $this->length();
		$data['text']['text_height']			= $this->length();

		$options = array('total_type', 'ocapps_cost', 'ocapps_requirement', 'final_cost');
		foreach ($options as $option) {
			$x = 0;
			$data[$option] = array();
			while (isset($data['text'][$option . '_' . $x])) {
				$data[$option][$x] = $data['text'][$option . '_' . $x];
				$x++;
			}
		}

		$data['requirement_match'] = array();
		foreach (array('any', 'all', 'none') as $param) {
			$data['requirement_match'][$param] = $data['text']['requirement_match_' . $param];
		}

		$data['requirement_cost'] = array();
		foreach (array('every', 'any', 'all', 'none') as $param) {
			$data['requirement_cost'][$param] = $data['text']['requirement_cost_' . $param];
		}

		$rates								= !empty($this->session->data['rates']) ? $this->session->data['rates'] : array();
		$previous_rate_id					= isset($rates[array_search($rate_id, $rates) - 1]) ? $rates[array_search($rate_id, $rates) - 1] : null;
		$next_rate_id						= isset($rates[array_search($rate_id, $rates) + 1]) ? $rates[array_search($rate_id, $rates) + 1] : null;

		$requirements						= $this->requirements();
		$data['requirement_types']			= $requirements['requirement_types'];
		$data['operations']					= $requirements['operations'];
		$data['values']						= $requirements['values'];
		$data['value_types']				= $requirements['value_types'];
		$data['parameters']					= $requirements['parameters'];

		$data['action']						= $this->link('extension/' . $this->type . '/' . $this->extension . '/save', (($rate_id) ? 'rate_id=' . $rate_id : null));
		$data['action_close']				= $this->link('extension/' . $this->type . '/' . $this->extension);
		$data['action_previous']			= ($previous_rate_id) ? $this->link('extension/' . $this->type . '/' . $this->extension . '/edit', 'rate_id=' . $previous_rate_id): false;
		$data['action_next']				= ($next_rate_id) ? $this->link('extension/' . $this->type . '/' . $this->extension . '/edit', 'rate_id=' . $next_rate_id) : false;
		$data['action_copy']				= ($rate_id) ? $this->link('extension/' . $this->type . '/' . $this->extension . '/copy', 'rate_id=' . $rate_id) : null;
		$data['action_delete']				= ($rate_id) ? $this->link('extension/' . $this->type . '/' . $this->extension . '/delete', 'rate_id=' . $rate_id) : null;

		$data['ocapps_integration']			= $this->ocapps_status;

		$this->document->addStyle(HTTPS_SERVER . 'view/stylesheet/extension/' . $this->type . '/' . $this->extension . '.css?ver=' . rand(1000,9999));
		$this->document->addStyle('view/javascript/extension/' . $this->type . '/' . $this->extension . '/jquery.datetimepicker.css');
		$this->document->addStyle('https://fonts.googleapis.com/css?family=Raleway:400,600,700');
		$this->document->addStyle('https://fonts.googleapis.com/css?family=Oswald:400,700');

		$this->document->addScript('view/javascript/extension/' . $this->type . '/' . $this->extension . '/jquery.datetimepicker.js');

		$this->document->setTitle($data['text']['text_name']);
		$data['header'] 		= $this->load->controller('common/header');
		$data['column_left'] 	= $this->load->controller('common/column_left');
		$data['footer'] 		= $this->load->controller('common/footer');
		$this->response->setOutput($this->load->view('extension/' . $this->type . '/' . $this->extension . '_rate', $data));
	}

	public function add() {
		$this->validate();
		
		$this->form();
	}

	public function edit() {
		$this->validate();
		
		$rate_id = isset($this->request->get['rate_id']) ? $this->request->get['rate_id'] : 0;

		$this->form($rate_id);
	}

	public function save() {
		$rate_id = isset($this->request->get['rate_id']) ? $this->request->get['rate_id'] : 0;
		
		$this->load->language('extension/' . $this->type . '/' . $this->extension);
		
		$errors = $this->validateRate($this->request->post);
		if (!$errors) {
			$this->load->model('extension/' . $this->type . '/' . $this->extension);
			
			if ($this->request->post['origin']) {
				$geocode = $this->geocode($this->request->post['origin']);
				$this->request->post['geocode_lat'] = $geocode['lat'];
				$this->request->post['geocode_lng'] = $geocode['lng'];
			}
			if ($rate_id) {
				$this->{'model_extension_' . $this->type . '_' . $this->extension}->editRate($rate_id, $this->request->post);
			} else {
				$rate_id = $this->{'model_extension_' . $this->type . '_' . $this->extension}->addRate($this->request->post);
			}
			
			$this->cache->delete($this->extension . '_rates');
			
			$this->session->data['success'] = $this->language->get('text_success_save');
			$this->response->redirect($this->link('extension/' . $this->type . '/' . $this->extension . '/edit', 'rate_id=' . $rate_id));
		} else {
			foreach ($errors as $key => $value) {
				$this->session->data['rate_errors'][$key] = $value;
			}
			$this->session->data['error'] 		= $this->language->get('text_error_rate');
			$this->session->data['post_data'] 	= $this->request->post;
			if ($rate_id) {
				$this->response->redirect($this->link('extension/' . $this->type . '/' . $this->extension . '/edit', 'rate_id=' . $rate_id));
			} else {
				$this->response->redirect($this->link('extension/' . $this->type . '/' . $this->extension . '/add', null));
			}
		}
	}
	
	public function delete() {
		$this->validate();
		
		$this->createBackup('Backup Created Prior To Rate Deletion');
		
		$this->cache->delete($this->extension . '_rates');

		$this->load->language('extension/' . $this->type . '/' . $this->extension);

		$this->load->model('extension/' . $this->type . '/' . $this->extension);

		$rate_id	= isset($this->request->get['rate_id']) ? $this->request->get['rate_id'] : 0;
		$rates 		= isset($this->request->post['selected']) ? $this->request->post['selected'] : array();

		if ($rate_id) {
			$this->{'model_extension_' . $this->type . '_' . $this->extension}->deleteRate($rate_id);
			$this->session->data['success'] = $this->language->get('text_success_rate_delete');
		} elseif ($rates) {
			foreach ($rates as $rate_id) {
				$this->{'model_extension_' . $this->type . '_' . $this->extension}->deleteRate($rate_id);
			}
			$this->session->data['success'] = $this->language->get('text_success_delete');
		}

		$this->response->redirect($this->link('extension/' . $this->type . '/' . $this->extension));
	}

	public function copy() {
		$this->validate();
		
		$this->cache->delete($this->extension . '_rates');

		$this->load->language('extension/' . $this->type . '/' . $this->extension);

		$this->load->model('extension/' . $this->type . '/' . $this->extension);

		$copy_rate_id	= isset($this->request->get['rate_id']) ? $this->request->get['rate_id'] : 0;
		$rate_info		= $this->{'model_extension_' . $this->type . '_' . $this->extension}->copyRate($copy_rate_id);
		$rate_id		= $rate_info['rate_id'];

		$this->session->data['success'] = $this->language->get('text_success_copy');

		$this->response->redirect($this->link('extension/' . $this->type . '/' . $this->extension . '/edit', 'rate_id=' . $rate_id));
	}

	private function requirements() {
		$cache_key = $this->extension . '_requirements';

		$data = $this->cache->get($cache_key);
		if (!$data || !$this->field('cache')) {
			$requirement_data 				= array();
			$requirement_data['language'] 	= $this->load->language($this->type . '/' . $this->extension);

			//Adjust Text Values
			foreach (array('volume', 'length', 'width', 'height') as $param) {
				foreach (array('cart', 'product') as $type) {
					$requirement_data['language']['text_requirement_type_' . $type . '_' . $param] = sprintf($requirement_data['language']['text_requirement_type_' . $type . '_' . $param], $this->length());
				}
			}

			$requirement_data['language']['text_requirement_type_cart_weight'] 		= sprintf($requirement_data['language']['text_requirement_type_cart_weight'], $this->weight());
			$requirement_data['language']['text_requirement_type_cart_dim_weight'] 	= sprintf($requirement_data['language']['text_requirement_type_cart_dim_weight'], $this->weight());
			$requirement_data['language']['text_requirement_type_product_weight'] 	= sprintf($requirement_data['language']['text_requirement_type_product_weight'], $this->weight());

			foreach (array('total') as $param) {
				foreach (array('cart', 'product') as $type) {
					$requirement_data['language']['text_requirement_type_' . $type . '_' . $param] = sprintf($requirement_data['language']['text_requirement_type_' . $type . '_' . $param], $this->currency($this->config->get('currency')));
				}
			}

			//Requirements
			$requirement_data['requirement_types'] 		= array();
			$requirement_types['cart'] 					= array('quantity', 'total', 'weight', 'volume', 'distance', 'length', 'width', 'height');
			$requirement_types['product'] 				= array('quantity', 'total', 'weight', 'volume', 'length', 'width', 'height', 'name', 'model', 'sku', 'upc', 'ean', 'jan' ,'isbn', 'mpn', 'location', 'stock', 'category', 'manufacturer');
			$requirement_types['product_option'] 		= array();
			$requirement_types['product_attribute'] 	= array();
			$requirement_types['customer'] 				= array('store', 'group', 'name', 'email', 'telephone', 'fax', 'company', 'address', 'city', 'postcode');
			$requirement_types['customer_customfield'] 	= array();
			$requirement_types['other']					= array('currency', 'day', 'date', 'time');
			foreach ($requirement_types as $group => $types) {
				$requirement_data['requirement_types'][$group] = array();
				foreach ($types as $type) {
					$requirement_data['requirement_types'][$group][($group == 'other' ? '' : $group . '_') . $type] = $requirement_data['language']['text_requirement_type_' . ($group == 'other' ? '' : $group . '_') . $type];
				}
			}

			//Operations
			$requirement_data['operations'] = array();

			//Operations Set - Equal, Not Equal
			$params = array('product_category', 'product_manufacturer', 'customer_store', 'customer_group', 'customer_postcode', 'currency', 'day');
			foreach ($params as $param) {
				$requirement_data['operations'][$param] = array();
				$operators = array('eq', 'neq');
				foreach ($operators as $operator) {
					$requirement_data['operations'][$param][$operator] = $requirement_data['language']['text_operator_' . $operator];
				}
			}

			//Operations Set - Equal, Not Equal, Contains, Does Not Contain
			$params = array('product_name', 'product_model', 'product_sku', 'product_upc', 'product_ean', 'product_jan', 'product_isbn', 'product_mpn', 'product_location', 'customer_name', 'customer_email', 'customer_telephone', 'customer_fax', 'customer_company', 'customer_address', 'customer_city');
			foreach ($params as $param) {
				$requirement_data['operations'][$param] = array();
				$operators = array('eq', 'neq', 'strpos', 'nstrpos');
				foreach ($operators as $operator) {
					$requirement_data['operations'][$param][$operator] = $requirement_data['language']['text_operator_' . $operator];
				}
			}

			//Operations Set - Equal, Not Equal, Greater Than or Equal, Less Than or Equal, Add, Subtract
			$params = array('cart_quantity', 'cart_total', 'cart_weight', 'cart_volume', 'cart_dim_weight', 'cart_distance', 'cart_length', 'cart_width', 'cart_height', 'product_quantity', 'product_total', 'product_weight', 'product_volume', 'product_length', 'product_width', 'product_height');
			foreach ($params as $param) {
				$requirement_data['operations'][$param] = array();
				$operators = array('eq', 'neq', 'gte', 'lte', 'add', 'sub');
				foreach ($operators as $operator) {
					$requirement_data['operations'][$param][$operator] = $requirement_data['language']['text_operator_' . $operator];
				}
			}

			//Operations Set - Equal, Not Equal, Greater Than or Equal, Less Than or Equal
			$params = array('product_stock', 'date', 'time');
			foreach ($params as $param) {
				$requirement_data['operations'][$param] = array();
				$operators = array('eq', 'neq', 'gte', 'lte');
				foreach ($operators as $operator) {
					$requirement_data['operations'][$param][$operator] = $requirement_data['language']['text_operator_' . $operator];
				}
			}

			//Values
			$requirement_data['values'] = array();

			//Value Types
			$requirement_data['value_types'] 				= array();
			$requirement_data['value_types']['checkbox']	= array();
			$requirement_data['value_types']['date']		= array('date');
			$requirement_data['value_types']['time']		= array('time');
			$requirement_data['value_types']['datetime']	= array();

			//Categories
			$this->load->model('catalog/category');
			$category_sort = array('sort' => 'name');
			foreach ($this->model_catalog_category->getCategories($category_sort) as $category) {
				$requirement_data['values']['product_category'][$category['category_id']] = preg_replace("/\'\"/", "", $category['name']);
			}
			$requirement_data['value_types']['checkbox'][] = 'product_category';

			//Manufacturers
			$this->load->model('catalog/manufacturer');
			foreach ($this->model_catalog_manufacturer->getManufacturers() as $manufacturer) {
				$requirement_data['values']['product_manufacturer'][$manufacturer['manufacturer_id']] = htmlspecialchars($manufacturer['name'], ENT_QUOTES);
			}
			$requirement_data['value_types']['checkbox'][] = 'product_manufacturer';

			//Stores
			$this->load->model('setting/store');
			$requirement_data['values']['customer_store'][0] = htmlspecialchars($this->config->get('config_name'), ENT_QUOTES);
			foreach ($this->model_setting_store->getStores() as $store) {
				$requirement_data['values']['customer_store'][$store['store_id']] = htmlspecialchars($store['name'], ENT_QUOTES);
			}
			$requirement_data['value_types']['checkbox'][] = 'customer_store';

			//Customer Groups
			$this->load->model('customer/customer_group');
			$requirement_data['values']['customer_group'][0] = $requirement_data['language']['text_guest_checkout'];
			foreach ($this->model_customer_customer_group->getCustomerGroups() as $customer_group) {
				$requirement_data['values']['customer_group'][$customer_group['customer_group_id']] = htmlspecialchars($customer_group['name'], ENT_QUOTES);
			}
			$requirement_data['value_types']['checkbox'][] = 'customer_group';

			//Currencies
			$this->load->model('localisation/currency');
			foreach ($this->model_localisation_currency->getCurrencies() as $currency) {
				$requirement_data['values']['currency'][$currency['code']] = htmlspecialchars($currency['title'], ENT_QUOTES);
			}

			//Days Of Week
			$day = 1;
			while ($day <= 7) {
				$requirement_data['values']['day'][$day] = $requirement_data['language']['day_' . $day];
				$day++;
			}
			$requirement_data['value_types']['checkbox'][] = 'day';

			//Parameters
			$requirement_data['parameters'] = array();

			//Product Parameters
			foreach ($requirement_types['product'] as $param) {
				foreach (array('any', 'all', 'none') as $x) {
					$requirement_data['parameters']['product_' . $param]['match'][$x] = $requirement_data['language']['text_product_match_' . $x];
				}
			}

			//Postal Code Types
			$requirement_data['parameters']['customer_postcode']['type'] 			= array();
			$requirement_data['parameters']['customer_postcode']['type']['other'] 	= $requirement_data['language']['text_postcode_type_other'];
			$requirement_data['parameters']['customer_postcode']['type']['uk'] 		= $requirement_data['language']['text_postcode_type_uk'];

			//Product Options
			$this->load->model('catalog/option');
			foreach ($this->model_catalog_option->getOptions() as $option) {
				$requirement_data['requirement_types']['product_option']['product_option_' . $option['option_id']] = htmlspecialchars($option['name'], ENT_QUOTES);

				//Operations
				$requirement_data['operations']['product_option_' . $option['option_id']] = array();
				if ($option['type'] == 'text') {
					$operators = array('eq', 'neq', 'gte', 'lte', 'strpos', 'nstrpos');
				} elseif ($option['type'] == 'textarea') {
					$operators = array('eq', 'neq', 'strpos', 'nstrpos');
				} elseif ($option['type'] == 'date' || $option['type'] == 'time' || $option['type'] == 'datetime') {
					$operators = array('eq', 'neq', 'gte', 'lte');
				} else {
					$operators = array('eq', 'neq');
				}
				foreach ($operators as $operator) {
					$requirement_data['operations']['product_option_' . $option['option_id']][$operator] = $requirement_data['language']['text_operator_' . $operator];
				}

				//Values
				foreach ($this->model_catalog_option->getOptionValues($option['option_id']) as $option_value) {
					$requirement_data['values']['product_option_' . $option['option_id']][$option_value['option_value_id']] = htmlspecialchars($option_value['name'], ENT_QUOTES);
				}

				//Value Type
				if (isset($requirement_data['value_types'][$option['type']])) {
					$requirement_data['value_types'][$option['type']][] = 'product_option_' . $option['option_id'];
				}

				//Parameters
				foreach (array('any', 'all', 'none') as $x) {
					$requirement_data['parameters']['product_option_' . $option['option_id']]['match'][$x] = $requirement_data['language']['text_product_match_' . $x];
				}
			}

			//Product Attributes
			$this->load->model('catalog/attribute');
			foreach ($this->model_catalog_attribute->getAttributes() as $attribute) {
				$requirement_data['requirement_types']['product_attribute']['product_attribute_' . $attribute['attribute_id']] = htmlspecialchars($attribute['name'], ENT_QUOTES);

				//Operations
				$requirement_data['operations']['product_attribute_' . $attribute['attribute_id']] = array();
				$operators = array('eq', 'neq');
				foreach ($operators as $operator) {
					$requirement_data['operations']['product_attribute_' . $attribute['attribute_id']][$operator] = $requirement_data['language']['text_operator_' . $operator];
				}

				//Parameters
				foreach (array('any', 'all', 'none') as $x) {
					$requirement_data['parameters']['product_attribute_' . $attribute['attribute_id']]['match'][$x] = $requirement_data['language']['text_product_match_' . $x];
				}
			}

			//Custom Fields
			$this->load->model('customer/custom_field');
			foreach ($this->model_customer_custom_field->getCustomFields() as $custom_field) {
				$requirement_data['requirement_types']['customer_customfield']['customer_customfield_' . $custom_field['custom_field_id']] = htmlspecialchars($custom_field['name'], ENT_QUOTES);

				//Operations
				$requirement_data['operations']['customer_customfield_' . $custom_field['custom_field_id']] = array();
				if ($custom_field['type'] == 'text') {
					$operators = array('eq', 'neq', 'gte', 'lte', 'strpos', 'nstrpos');
				} elseif ($custom_field['type'] == 'textarea') {
					$operators = array('eq', 'neq', 'strpos', 'nstrpos');
				} elseif ($custom_field['type'] == 'date' || $custom_field['type'] == 'time' || $custom_field['type'] == 'datetime') {
					$operators = array('eq', 'neq', 'gte', 'lte');
				} else {
					$operators = array('eq', 'neq');
				}
				foreach ($operators as $operator) {
					$requirement_data['operations']['customer_customfield_' . $custom_field['custom_field_id']][$operator] = $requirement_data['language']['text_operator_' . $operator];
				}

				//Values
				foreach ($this->model_customer_custom_field->getCustomFieldValues($custom_field['custom_field_id']) as $custom_field_value) {
					$requirement_data['values']['customer_customfield_' . $custom_field['custom_field_id']][$custom_field_value['custom_field_value_id']] = htmlspecialchars($custom_field_value['name'], ENT_QUOTES);
				}

				//Value Type
				if (isset($requirement_data['value_types'][$custom_field['type']])) {
					$requirement_data['value_types'][$custom_field['type']][] = 'customer_customfield_' . $custom_field['custom_field_id'];
				}
			}

			$data = array('requirement_types' => $requirement_data['requirement_types'], 'operations' => $requirement_data['operations'], 'values' => $requirement_data['values'], 'value_types' => $requirement_data['value_types'], 'parameters' => $requirement_data['parameters']);
			if ($this->field('cache')) { $this->cache->set($this->extension . '_requirements', $data); }
		}
		return $data;
	}

	public function requirement() {
		$json = array();

		$this->validate();

		$requirements 	= $this->requirements();

		$data = array();
		$data = array_merge($data, $this->load->language('extension/' . $this->type . '/' . $this->extension));

		$type = isset($this->request->get['type']) ? $this->request->get['type'] : false;
		if ($type) {
			$json['success'] = true;

			$json['requirement_type_group'] = null;
			foreach ($requirements['requirement_types'] as $group => $types) {
				foreach ($types as $param_key => $param) {
					if ($param_key == $type) {
						$json['requirement_type_group'] = substr($data['text_requirement_group_' . $group], 0, -1);
						break;
					}
				}
				if ($json['requirement_type_group']) { break; }
			}

			if (!empty($requirements['operations'][$type])) {
				$json['operations'] = array();
				foreach ($requirements['operations'][$type] as $key => $value) {
					$json['operations'][$key] = $value;
				}
			}

			if (!empty($requirements['values'][$type])) {
				$json['values'] = array();
				foreach ($requirements['values'][$type] as $key => $value) {
					//Add A Space In Front Of Value To Prevent Browsers From Sorting
					$json['values'][' ' . $key] = $value;
				}
			}

			if (!empty($requirements['parameters'][$type])) {
				$json['parameters'] = array();
				foreach ($requirements['parameters'][$type] as $key => $param) {
					foreach ($param as $param_key => $value) {
						$json['parameters'][$key][$param_key] = $value;
					}
					$json['parameter_tooltip'] = isset($data['tooltip_' . $type . '_' . $key]) ? $data['tooltip_' . $type . '_' . $key] : null;
				}
			}

			$json['value_type'] = 'select';
			foreach ($requirements['value_types'] as $key => $values) {
				if (in_array($type, $values)) {
					$json['value_type'] = $key;
					$json['value_tooltip'] = $data['tooltip_' . $key];
				}
			}

			if (isset($data['tooltip_' . $type])) {
				$json['value_tooltip'] = $data['tooltip_' . $type];
			} elseif (!empty($json['values'])) {
				$json['value_tooltip'] = $data['tooltip_' . $json['value_type']];
			} else {
				$json['value_tooltip'] = $data['tooltip_text'];
			}
		}
		$this->response->setOutput(json_encode($json));
	}

	private function ratetypes() {
		$this->load->language('extension/' . $this->type . '/' . $this->extension);

		$rate_types				= array();
		$rate_type['cart'] 		= array('quantity', 'total', 'weight', 'dim_weight', 'volume', 'length', 'width', 'height', 'distance');
		$rate_type['product'] 	= array('quantity', 'total', 'weight', 'volume', 'length', 'width', 'height');
		foreach ($rate_type as $group => $types) {
			foreach ($types as $type) {
				$rate_types[$group][$group . '_' . $type] = $this->language->get('text_rate_type_' . $group . '_' . $type);
			}
		}

		//Get Installed Shipping Methods
		$rate_types['other'] 	= array();
		$this->load->model(($this->version() >= 3.0) ? 'setting/extension' : 'extension/extension');
		$shipping_methods = $this->{'model_' . (($this->version() >= 3.0) ? 'setting_extension' : 'extension_extension')}->getInstalled('shipping');
		foreach ($shipping_methods as $shipping_method) {
			if ($shipping_method !== $this->extension && $shipping_method !== 'ocapps') {
				$this->load->language('extension/shipping/' . $shipping_method);
				$rate_types['other'][$shipping_method] = strip_tags($this->language->get('heading_title'));
			}
		}

		return $rate_types;
	}

	private function geozones() {
		$this->load->language('extension/' . $this->type . '/' . $this->extension);

		$this->load->model('localisation/geo_zone');

		$geo_zones 		= array();
		foreach ($this->model_localisation_geo_zone->getGeoZones() as $geo_zone) {
			$geo_zones[$geo_zone['geo_zone_id']] = $geo_zone['name'];
		}

		$geo_zones[0]	= $this->language->get('text_all_other_zones');

		return $geo_zones;
	}

	private function weight() {
		$this->load->model('localisation/weight_class');

		$weight_class = $this->model_localisation_weight_class->getWeightClass($this->config->get('config_weight_class_id'));
		$weight_units = isset($weight_class['unit']) ? $weight_class['unit'] : $this->config->get('config_weight_class_id');

		return $weight_units;
	}

	private function length() {
		$this->load->model('localisation/length_class');

		$length_class = $this->model_localisation_length_class->getLengthClass($this->config->get('config_length_class_id'));
		$length_units = isset($length_class['unit']) ? $length_class['unit'] : $this->config->get('config_length_class_id');

		return $length_units;
	}

	private function currency($currency) {
		$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "currency WHERE code = '" . $this->db->escape($currency) . "'");
		if (!empty($query->row['symbol_left'])) {
			return $query->row['symbol_left'];
		} elseif (!empty($query->row['symbol_right'])) {
			return $query->row['symbol_right'];
		} else {
			return '';
		}
	}

	private function geocode($origin) {
		$url = 'https://maps.googleapis.com/maps/api/geocode/xml?address=' . $origin . '&sensor=false';
		$response = simplexml_load_file($url);
		if ($response->status == 'OK') {
			return array(
				'lat'	=> $response->result->geometry->location->lat,
				'lng'	=> $response->result->geometry->location->lng
			);
		} else {
			return false;
		}
	}

	private function csv() {
		$instructions 	= 'ADDING OR REMOVING COLUMNS MAY CAUSE PROBLEMS WHEN ATTEMPTING TO IMPORT YOUR SHIPPING RATES';

		$row_offset 	= 1;
		$col_offset 	= 0;

		$fields 		= array();
		$this->load->model('extension/' . $this->type . '/' . $this->extension);
		$data = $this->{'model_extension_' . $this->type . '_' . $this->extension}->settings();
		foreach ($data as $key => $value) {
			$fields[] = $key;
		}

		return array(
			'instructions'	=> $instructions,
			'fields'		=> $fields,
			'row_offset'	=> $row_offset,
			'col_offset'	=> $col_offset,
		);
	}

	public function import($file = '') {
		$this->validate();

		$this->language->load('extension/' . $this->type . '/' . $this->extension);

		if ($file) {
			$file = DIR_LOGS . $file;
		} elseif (isset($this->request->files['import']) && is_uploaded_file($this->request->files['import']['tmp_name'])) {
			$file = $this->request->files['import']['tmp_name'];
		}
		
		if ($file) {
			$this->load->model('extension/' . $this->type . '/' . $this->extension);

			$changes = array(
				'added'		=> 0,
				'updated'	=> 0
			);

			$csv_info 	= $this->csv();

			$row = 0;
			if (($handle = fopen($file, "r")) !== false) {
				while (($data = fgetcsv($handle, 4000, ",")) !== false) {
					if ($row > $csv_info['row_offset']) {
						$col = $csv_info['col_offset'];
						foreach ($fields as $field) {
							$value 				= $this->value($data[$col++]);
							$key				= trim($field);
							$rate_info[$key] 	= $value;
						}

						if ($rate_info['rate_id'] && $this->{'model_extension_' . $this->type . '_' . $this->extension}->getRate($rate_info['rate_id'])) {
							$this->{'model_extension_' . $this->type . '_' . $this->extension}->editRate($rate_info['rate_id'], $rate_info);
							$changes['updated']++;
						} else {
							$this->{'model_extension_' . $this->type . '_' . $this->extension}->addRate($rate_info);
							$changes['added']++;
						}

						$row++;
					} elseif ($row == $csv_info['row_offset']) {
						$fields = array();
						$fields = array_merge($data);

						$row++;
					} else {
						$row++;
					}
				}
			}
			$this->session->data['success'] = sprintf($this->language->get('text_success_import'), $changes['added'], $changes['updated']);
		} else {
			$this->session->data['error']	= $this->language->get('text_error_import');
		}
		$this->response->redirect($this->link('extension/' . $this->type . '/' . $this->extension));
	}

	public function export($return = false) {
		$this->validate();

		$this->load->model('extension/' . $this->type . '/' . $this->extension);
		$rates = $this->{'model_extension_' . $this->type . '_' . $this->extension}->getRates();

		if ($rates) {
			$csv_info = $this->csv();

			$output = str_replace('"', '""', $csv_info['instructions']);
			$output .= "\n";

			$x = 1;
			foreach ($csv_info['fields'] as $field) {
				$output	.= ($x > 1) ? ',"' . str_replace('"', '""', $field) . '"' : '"' . str_replace('"', '""', $field) . '"';
				$x++;
			}
			$output .= "\n";

			foreach ($rates as $rate) {
				$rate_info = $this->{'model_extension_' . $this->type . '_' . $this->extension}->getRate($rate['rate_id']);

				foreach ($rate_info as $key => $value) {
					$data[$key] = $value;
				}

				if ($rate_info) {
					$x = 1;
					foreach ($csv_info['fields'] as $field) {
						$output	.= ($x > 1) ? ',"' . str_replace('"', '""', $data[$field]) . '"' : '"' . str_replace('"', '""', $data[$field]) . '"';
						$x++;
					}
					$output .= "\n";
				}
			}

			if ($return) {
				return $output;
			} else {
				$this->response->addheader('Pragma: public');
				$this->response->addheader('Expires: 0');
				$this->response->addheader('Content-Description: File Transfer');
				$this->response->addheader('Content-Type: text/csv');
				$this->response->addheader('Content-Disposition: attachment; filename=' . date('Y-m-d_H-i-s', time()) .'_' . $this->extension . '.csv');
				$this->response->addheader('Content-Transfer-Encoding: binary');
			
				$this->response->setOutput($output);
			}
		}
	}
	
	public function downloadDebug() {
		$this->validate();

		$debug_file = DIR_LOGS . $this->extension . '.txt';
		if (file_exists($debug_file)) {
			$file_size = filesize($debug_file);
			if ($file_size >= 5242880) {
				$debug = 'Debug Log Filesize Is Too Large';
			} else {
				$debug = file_get_contents($debug_file);
			}
		} else {
			$debug = 'Debug Log Is Empty';
		}

		$this->response->addheader('Pragma: public');
		$this->response->addheader('Expires: 0');
		$this->response->addheader('Content-Description: File Transfer');
		$this->response->addheader('Content-Type: text/csv');
		$this->response->addheader('Content-Disposition: attachment; filename=' . $this->extension . '.txt');
		$this->response->addheader('Content-Transfer-Encoding: binary');
		$this->response->setOutput($debug);
	}

	public function clearDebug() {
		$this->validate();

		$debug_file = DIR_LOGS . $this->extension . '.txt';
		file_put_contents($debug_file, '');

		$this->load->language('extension/' . $this->type . '/' . $this->extension);

		$json = array();
		$json['success'] 	= $this->language->get('text_success_debug_clear');
		$this->response->setOutput(json_encode($json));
	}

	public function reloadDebug() {
		$this->validate();

		$this->load->language('extension/' . $this->type . '/' . $this->extension);

		$debug_file = DIR_LOGS . $this->extension . '.txt';
		if (file_exists($debug_file)) {
			$file_size = filesize($debug_file);
			if ($file_size >= 5242880) {
				$debug = 'Debug Log Filesize Is Too Large';
			} else {
				$debug = file_get_contents($debug_file);
			}
		} else {
			$debug = 'Debug Log Is Empty';
		}

		$json = array();
		$json['debug_log'] 	= $debug;
		$json['success'] 	= $this->language->get('text_success_debug_reload');
		$this->response->setOutput(json_encode($json));
	}

	public function clearCache() {
		$this->validate();

		$this->cache->delete($this->extension . '_rates');
		$this->cache->delete($this->extension . '_requirements');
		
		$this->load->language('extension/' . $this->type . '/' . $this->extension);

		$json = array();
		$json['success'] 	= $this->language->get('text_success_cache_clear');
		$this->response->setOutput(json_encode($json));
	}

	public function createBackup($comment = '') {
		$this->validate();
		
		$this->load->language('extension/' . $this->type . '/' . $this->extension);
		
		if ($this->field('backup')) {
			$comment		= !empty($this->request->get['comment']) ? $this->request->get['comment'] : $comment;
			$file 			= DIR_LOGS . $this->extension . '_backup_' . time() . '_' . $comment . '.csv';
			$backup_data	= $this->export(true);		
			
			if ($backup_data) {
				file_put_contents($file, $backup_data);
			}
		}
		
		$json = array();
		$json['success'] = $this->language->get('text_success_backup');
		$this->response->setOutput(json_encode($json));
	}

	public function getBackups() {
		$this->validate();
		
		$backup_data = array();
		
		$files = array_slice(scandir(DIR_LOGS), 2);
		if ($files) {
			foreach ($files as $file) {
				$file_data = explode('_', str_replace('.csv', '', $file));
				if ((!empty($file_data[0]) && $file_data[0] == $this->extension) && (!empty($file_data[1]) && $file_data[1] == 'backup')) {
					$backup_data[] = array(
						'file'		=> $file,
						'date'		=> (!empty($file_data[2])) ? $file_data[2] : '',
						'comment'	=> !empty($file_data[3]) ? $file_data[3] : '',
					);
				}
			}
		}
		
		return $backup_data;
	}
	
	public function restoreBackup() {
		$this->validate();
		
		$file = !empty($this->request->get['file']) ? $this->request->get['file'] : null;
		if ($file) {
			$this->createBackup('Backup Created Prior To Restore');
			
			$this->load->model('extension/' . $this->type . '/' . $this->extension);
			$this->{'model_extension_' . $this->type . '_' . $this->extension}->deleteAllRates();
			
			$this->import($file);
		}
	}

	public function clearBackup() {
		$this->validate();
		
		$file = !empty($this->request->get['file']) ? $this->request->get['file'] : null;
		if ($file) {
			unlink(DIR_LOGS . $file);
		}
		
		$this->session->data['success'] = $this->language->get('text_success_backup_clear');
		$this->response->redirect($this->link('extension/' . $this->type . '/' . $this->extension));
	}

	public function clearBackups() {
		$this->validate();
		
		$backups = $this->getBackups();
		foreach ($backups as $backup) {
			unlink(DIR_LOGS . $backup['file']);
		}
		
		$this->session->data['success'] = $this->language->get('text_success_backup_clear');
		$this->response->redirect($this->link('extension/' . $this->type . '/' . $this->extension));
	}
	
	public function support() {
		$json = array();

		$this->load->language('extension/' . $this->type . '/' . $this->extension);

		if ($this->validate() && isset($this->request->post)) {
			if ($this->request->post['email'] && $this->request->post['order_id'] && $this->request->post['enquiry']) {
				$text  = "Extension: " . $this->language->get('text_name') . "\n";
				$text .= "Version: " . $this->version . "\n";
				$text .= (defined('VERSION')) ? "OpenCart Version: " . VERSION . "\n" : "OpenCart Version: N/A \n";
				$text .= "Website: " . HTTP_CATALOG . "\n";
				$text .= "Email: " . $this->request->post['email'] . "\n";
				$text .= "Order ID: " . $this->request->post['order_id'] . "\n";
				$text .= "\n";
				$text .= "Support Question: \n";
				$text .= $this->request->post['enquiry'];

				$mail = new Mail();
				$mail->protocol = $this->config->get('config_mail_protocol');
				$mail->parameter = $this->config->get('config_mail_parameter');
				$mail->hostname = $this->config->get('config_smtp_host');
				$mail->username = $this->config->get('config_smtp_username');
				$mail->password = $this->config->get('config_smtp_password');
				$mail->port = $this->config->get('config_smtp_port');
				$mail->timeout = $this->config->get('config_smtp_timeout');
				$mail->setFrom($this->request->post['email']);
				$mail->setSender($this->config->get('config_name'));
				$mail->setSubject($this->language->get('text_name') . ' Support Request');
				$mail->setText(html_entity_decode($text, ENT_QUOTES, 'UTF-8'));
				$mail->setTo($this->email);
				$mail->send();

				$json['success'] = $this->language->get('text_success_support');
			} else {
				$json['error'] = $this->language->get('text_error_support');
			}
		} else {
			$json['error'] = $this->language->get('error_permission');
		}
		$this->response->setOutput(json_encode($json));
	}

	private function version() {
		return (defined('VERSION')) ? (float)VERSION : '';
	}

	private function link($route, $params = '') {
		$data  = ($this->version() >= 3.0) ? 'user_token=' . $this->session->data['user_token'] : 'token=' . $this->session->data['token'];
		$data .= ($params) ? '&' . $params : '';

		return $this->url->link($route, $data, true);
	}

	private function field($field) {
		$key = (($this->version() >= 3.0) ? $this->type . '_' : '') . $this->extension . '_' . $field;
		return $this->config->get($key);
	}
	
	private function value($value) {
		return $value = (!is_array($value) && is_array(json_decode($value, true))) ? json_decode($value, true) : $value;
	}

	private function validateSetting($key, $value) {
		$errors = array();

		$this->load->language($this->type . '/' . $this->extension);

		$combine_regex['sum'] = '/SUM\(([A-Z0-9\,\.\{\}]+)\)/';
		$combine_regex['avg'] = '/AVG\(([A-Z0-9\,\.\{\}]+)\)/';
		$combine_regex['min'] = '/MIN\(([A-Z0-9\,\.\{\}]+)\)/';
		$combine_regex['max'] = '/MAX\(([A-Z0-9\,\.\{\}]+)\)/';

		if ($key == 'combinations') {
			foreach ($value as $combination_key => $result) {
				$formula = strtoupper(preg_replace('/\s+/', '', $result['formula']));
				$combine_status = true;
				if ($formula) {
					if (substr_count($formula, '(') == substr_count($formula, ')') && substr_count($formula, '{') == substr_count($formula, '}')) {
						while (preg_match('/([SUM|AVG|MIN|MAX])\(([A-Z0-9\,\.\{\}]+)\)/', $formula) && $combine_status) {
							foreach ($combine_regex as $regex_key => $regex_value) {
								preg_match($regex_value, $formula, $matches);
								$x = 1;
								while (isset($matches[$x])) {
									$rate_groups = explode(',', $matches[$x]);
									foreach ($rate_groups as $rate_group) {
										$rate_group_value = '';
										$cost = 4;
										if ($regex_key == 'sum') { $rate_group_value += $cost; }
										if ($regex_key == 'avg') { $rate_group_value .= ($rate_group_value) ? ',' . $cost : $cost; }
										if ($regex_key == 'min' && ($rate_group_value > $cost || (string)$rate_group_value == '')) { $rate_group_value = $cost; }
										if ($regex_key == 'max' && ($rate_group_value < $cost || (string)$rate_group_value == '')) { $rate_group_value = $cost; }
										$matches[$x] = str_replace(array($rate_group), array($rate_group => $rate_group_value), $matches[$x]);
									}
									if (preg_match('/^([0-9\,\.\+\-\*\/\(\)]+)$/', $matches[$x])) {
										if ($regex_key == 'sum') { $rate_group_cost = array_sum(explode(',', $matches[$x])); }
										if ($regex_key == 'avg') { $rate_group_cost = array_sum(explode(',', $matches[$x])) / count(explode(',', $matches[$x])); }
										if ($regex_key == 'min') { $rate_group_cost = min(explode(',', $matches[$x])); }
										if ($regex_key == 'max') { $rate_group_cost = max(explode(',', $matches[$x])); }
										$formula = str_replace(array($matches[0]), array($matches[0] => $rate_group_cost), $formula);
									}
									$x++;
								}
							}
						}
						if (!preg_match('/^([0-9\,\.\+\-\*\/\(\)]+)$/', $formula) || substr_count($formula, '(') != substr_count($formula, ')')) {
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

	private function validateRate($value) {
		$rate_errors = array();

		$postcode_formats 	= array();
		$postcode_formats[] = '/^([0-9a-zA-Z]+)$/';
		$postcode_formats[] = '/^([0-9a-zA-Z]+):([0-9a-zA-Z]+)$/';

		$uk_formats	= array();
		$uk_formats[] = '/^([a-zA-Z]{2}[0-9]{1}[a-zA-Z]{1}[0-9]{1}[a-zA-Z]{2})$/';
		$uk_formats[] = '/^([a-zA-Z]{1}[0-9]{1}[a-zA-Z]{1}[0-9]{1}[a-zA-Z]{2})$/';
		$uk_formats[] = '/^([a-zA-Z]{1}[0-9]{2}[a-zA-Z]{2})$/';
		$uk_formats[] = '/^([a-zA-Z]{1}[0-9]{3}[a-zA-Z]{2})$/';
		$uk_formats[] = '/^([a-zA-Z]{2}[0-9]{2}[a-zA-Z]{2})$/';
		$uk_formats[] = '/^([a-zA-Z]{2}[0-9]{3}[a-zA-Z]{2})$/';

		if ($value['group'] && !preg_match('/^([a-zA-Z0-9\,]+)$/', $value['group'])) {
			$rate_errors['group'] = sprintf($this->language->get('text_error_rate_group'));
		}
		if (empty($value['shipping'])) {
			$rate_errors['shipping'] = sprintf($this->language->get('text_error_shipping'));
		} else {
			foreach ($value['shipping'] as $geo_zone_id => $shipping) {
				if (($shipping['rate_type'] == 'cart_dim_weight' || $shipping['rate_type'] == 'product_dim_weight') && !$shipping['shipping_factor']) {
					$rate_errors['shipping_' . $geo_zone_id . '_shipping_factor'] = sprintf($this->language->get('text_error_rate_shipping_factor'));
				}
				if ($shipping['rate_type'] == 'cart_distance' && !$value['origin']) {
					$rate_errors['origin'] = sprintf($this->language->get('text_error_rate_origin'));
				}
				if (strpos($shipping['rate_type'], 'cart_') === 0 || strpos($shipping['rate_type'], 'product_') === 0) {
					if (!empty($shipping['rates'])) {
						$rates = array();
						foreach ($shipping['rates'] as $rate_row => $rate) {
							if (empty($rate['max'])) { $rate_errors['shipping_' . $geo_zone_id . '_rates_max_' . $rate_row] = sprintf($this->language->get('text_error_rate_rates_max')); }
							if (empty($rate['cost']) && $rate['cost'] !== '0') { $rate_errors['shipping_' . $geo_zone_id . '_rates_cost_' . $rate_row] = sprintf($this->language->get('text_error_rate_rates_cost')); }
						}
					}
				} elseif (empty($shipping['shipping_method'])) {
					$rate_errors['shipping_' . $geo_zone_id . '_shipping_method'] = $this->language->get('text_error_rate_shipping_method');
				}
			}
		}
		if (!empty($value['requirements'])) {
			foreach ($value['requirements'] as $key => $requirement) {
				if ($requirement['type'] == 'customer_postcode') {
					if ($requirement['value']) {
						$postcode_ranges = explode(',', $requirement['value']);
						foreach ($postcode_ranges as $postcode_range) {
							$postcode_format_match = false;
							$postcode_range = trim($postcode_range);
							foreach ($postcode_formats as $postcode_format) {
								if (preg_match($postcode_format, $postcode_range)) {
									$postcode_format_match = true;
									if ($requirement['parameter']['type'] == 'uk') {
										$postcode_uk_format_match = false;
										$postcodes = explode(':', $postcode_range);
										$postcodes[0] = trim($postcodes[0]);
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
											$postcode_uk_format_match = false;
											$postcodes[1] = trim($postcodes[1]);
											foreach ($uk_formats as $uk_format) {
												if (preg_match($uk_format, $postcodes[1])) {
													$postcode_uk_format_match = true;
													break;
												}
											}
											if (!$postcode_uk_format_match) {
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
				} elseif ($requirement['type'] == 'cart_distance' && !$value['origin']) {
					$rate_errors['origin'] = $this->language->get('text_error_rate_origin');
				} elseif (empty($requirement['value'])) {
					$rate_errors['requirement_' . $key] = $this->language->get('text_error_rate_requirement');
				}
			}
		}
		return $rate_errors;
	}

	private function validate() {
		$this->load->language('extension/' . $this->type . '/' . $this->extension);

		if (!$this->user->hasPermission('modify', 'extension/' . $this->type . '/' . $this->extension)) {
			$this->session->data['error']		= $this->language->get('text_error_not_valid');
			$this->response->redirect($this->link('extension/' . $this->type . '/' . $this->extension));
		} else {
			return true;
		}
	}

	public function install() {
		$this->load->model('extension/' . $this->type . '/' . $this->extension);
		$this->{'model_extension_' . $this->type . '_' . $this->extension}->install();

		$this->load->model('setting/setting');
		
		$field_key = (($this->version() >= 3.0) ? $this->type . '_' : '') . $this->extension . '_';
		$this->model_setting_setting->editSetting($this->extension, array($field_key . '_backup' => 1));
	}

	public function uninstall() {
		$this->load->model('extension/' . $this->type . '/' . $this->extension);
		$this->{'model_extension_' . $this->type . '_' . $this->extension}->uninstall();
	}

	private $ocapps_status		= false;
}