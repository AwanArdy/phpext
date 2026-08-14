<?php
declare(strict_types=1);

namespace Opencart\Catalog\Model\Extension\Advancedshipping\Shipping;

class Advancedshipping extends \Opencart\System\Engine\Model {
	private string $extension = 'advancedshipping';
	private string $type = 'shipping';
	private string $dbTable = 'advanced_shipping';

	private bool $status = true;
	private bool $debugStatus = false;
	private array $cartGeoZones = [];
	private array $cartProducts = [];
	private array $dataCustomer = [];
	private array $dataOther = [];
	private array $savedCart = [];
	private array $ukFormats = [];
	private array $rateTypes = [];
	private mixed $combinations = null;
	private bool $ocappsStatus = false;

	private function construct(array $address): void {
		$this->status       = true;
		$this->debugStatus  = !empty($this->field('debug'));
		$this->cartGeoZones = $this->getGeoZones($address);
		$this->cartProducts = $this->getProducts();
		$this->savedCart    = $this->saveCart();

		$customerInfo = [];
		if ($this->customer->isLogged()) {
			$this->load->model('account/customer');
			$customerInfo = $this->model_account_customer->getCustomer($this->customer->getId());
		}

		$customer = [
			'store'    => (int)$this->config->get('config_store_id'),
			'group'    => $this->customer->isLogged() ? (int)$this->customer->getGroupId() : 0,
			'name'     => trim(($address['firstname'] ?? '') . ' ' . ($address['lastname'] ?? '')),
			'email'    => $this->customer->isLogged() ? trim((string)($customerInfo['email'] ?? '')) : trim((string)($this->session->data['guest']['email'] ?? '')),
			'telephone'=> $this->customer->isLogged() ? trim((string)($customerInfo['telephone'] ?? '')) : trim((string)($this->session->data['guest']['telephone'] ?? '')),
			'fax'      => $this->customer->isLogged() ? trim((string)($customerInfo['fax'] ?? '')) : trim((string)($this->session->data['guest']['fax'] ?? '')),
			'company'  => trim((string)($address['company'] ?? '')),
			'address'  => trim(($address['address_1'] ?? '') . ' ' . ($address['address_2'] ?? '')),
			'city'     => trim((string)($address['city'] ?? '')),
			'postcode' => trim((string)($address['postcode'] ?? '')),
		];

		$customerCustomField = [];
		if (!empty($customerInfo['custom_field'])) {
			$cfDecoded = is_array($customerInfo['custom_field']) ? $customerInfo['custom_field'] : json_decode((string)$customerInfo['custom_field'], true);
			if (is_array($cfDecoded)) {
				$customerCustomField += $cfDecoded;
			}
		}
		if (!empty($this->session->data['guest']['custom_field']) && is_array($this->session->data['guest']['custom_field'])) {
			$customerCustomField += $this->session->data['guest']['custom_field'];
		}
		if (!empty($this->session->data['shipping_address']['custom_field']) && is_array($this->session->data['shipping_address']['custom_field'])) {
			$customerCustomField += $this->session->data['shipping_address']['custom_field'];
		}
		if (!empty($this->session->data['payment_address']['custom_field']) && is_array($this->session->data['payment_address']['custom_field'])) {
			$customerCustomField += $this->session->data['payment_address']['custom_field'];
		}

		$customer['customfield'] = $customerCustomField;

		$currencyCode = $this->session->data['currency'] ?? $this->config->get('config_currency') ?? 'USD';
		$other = [
			'currency' => $currencyCode,
			'day'      => (int)date('w') + 1,
			'date'     => date('Y-m-d'),
			'time'     => date('H:i'),
		];

		$this->dataCustomer = $customer;
		$this->dataOther    = $other;

		$this->ukFormats = [
			['regex' => '/^([A-Z]{2}[0-9]{1}[A-Z]{1}[0-9]{1}[A-Z]{2})$/', 'start' => 'AA0A0AA', 'end' => 'ZZ9Z9ZZ'],
			['regex' => '/^([A-Z]{1}[0-9]{1}[A-Z]{1}[0-9]{1}[A-Z]{2})$/', 'start' => 'A0A0AA',  'end' => 'Z9Z9ZZ'],
			['regex' => '/^([A-Z]{1}[0-9]{2}[A-Z]{2})$/',                 'start' => 'A00AA',   'end' => 'Z99ZZ'],
			['regex' => '/^([A-Z]{1}[0-9]{3}[A-Z]{2})$/',                 'start' => 'A000AA',  'end' => 'Z999ZZ'],
			['regex' => '/^([A-Z]{2}[0-9]{2}[A-Z]{2})$/',                 'start' => 'AA00AA',  'end' => 'ZZ99ZZ'],
			['regex' => '/^([A-Z]{2}[0-9]{3}[A-Z]{2})$/',                 'start' => 'AA000AA', 'end' => 'ZZ999ZZ'],
		];

		$this->rateTypes = ['cart_quantity', 'cart_total', 'cart_weight', 'cart_volume', 'cart_dim_weight', 'cart_distance', 'cart_length', 'cart_width', 'cart_height', 'product_quantity', 'product_total', 'product_weight', 'product_volume', 'product_dim_weight', 'product_length', 'product_width', 'product_height'];

		$this->combinations = $this->field('combinations');
	}

	public function getQuote(array $address): array {
		$this->construct($address);

		if (!empty($this->field('test')) && mb_strtolower($this->dataCustomer['name']) !== 'advanced shipping') {
			$this->writeDebug('The system is currently in testing mode. To test shipping calculation, set the Customer Name of the order to Advanced Shipping');
			$this->writeDebug($this->dataCustomer);
			return [];
		}

		$rates = $this->rates();

		if ($this->status && !empty($this->field('status')) && $rates && $this->cartProducts && $address) {
			$languageCode = $this->session->data['language'] ?? $this->config->get('config_language') ?? 'en-gb';
			$destination  = $this->getDestination($address);

			$quote_data   = [];
			$method_data  = [];
			$combined_data= [];

			if ($rates) {
				foreach ($rates as $rate_info) {
					$this->load->language('extension/advancedshipping/shipping/' . $this->extension);

					$rate = [];
					$debug = [];
					$debug['RateID']      = $rate_info['rate_id'];
					$debug['Description'] = mb_strtoupper((string)$rate_info['description']);

					if ($this->ocappsStatus && !empty($this->field('ocapps_status'))) {
						$debug['PerProductShippingIntegration'] = true;
					}

					foreach ($rate_info as $key => $value) {
						$rate[$key] = $this->value($value);
					}

					if (!empty($rate['status'])) {
						$debug['Status'] = true;
						$status = true;

						$rate_name     = $this->language->get('text_name');
						$shipping_name = $this->language->get('text_name');
						$heading       = $this->language->get('text_title');
						$adjusted_values = [];

						$debug['cartGeoZones'] = $this->cartGeoZones;
						$origin = [
							'origin' => (string)($rate['origin'] ?? ''),
							'lat'    => (float)($rate['geocode_lat'] ?? 0),
							'lng'    => (float)($rate['geocode_lng'] ?? 0),
						];

						$products = $this->cartProducts;
						$cart     = [];
						$customer = $this->dataCustomer;
						$other    = $this->dataOther;

						// Remove products with Per Product Shipping if needed
						$ocapps_products = [];
						if ($this->ocappsStatus && !empty($this->field('ocapps_status')) && $products && empty($rate['ocapps_requirement'])) {
							foreach ($products as $key => $product) {
								foreach ($this->cartGeoZones as $geo_zone) {
									if (!empty($rate['shipping'][$geo_zone['geo_zone_id']]['cost']) || !empty($rate['shipping'][$geo_zone['geo_zone_id']]['shipping_method'])) {
										if ($this->checkPerProductShipping((int)$product['product_id'], (int)$geo_zone['geo_zone_id'])) {
											$debug['removeProduct'][$key]['Product'] = $product['name'];
											if (($rate['ocapps_cost'] ?? 0) == 2) {
												$ocapps_products[$key] = $products[$key];
											}
											unset($products[$key]);
											break;
										}
									}
								}
							}
						}

						// Requirements
						$temp_requirements = [];
						if (!empty($rate['requirements']) && is_array($rate['requirements'])) {
							foreach ($rate['requirements'] as $key => $requirement) {
								$op = (string)($requirement['operation'] ?? '');
								$reqType = (string)($requirement['type'] ?? '');
								$reqVal = $requirement['value'] ?? '';

								if (!in_array($op, ['add', 'sub'], true)) {
									if (str_starts_with($reqType, 'cart_') || str_starts_with($reqType, 'product_') || str_starts_with($reqType, 'customer_')) {
										$parts = explode('_', $reqType);
										$group = $parts[0];
									} else {
										$group = 'other';
									}
									$temp_requirements[$group][$reqType][$key] = [
										'operation' => $op,
										'value'     => $reqVal,
										'parameter' => $requirement['parameter'] ?? [],
									];
								} else {
									$numVal = (float)$reqVal;
									if ($op === 'sub') {
										$numVal = -$numVal;
									}
									$adjusted_values[$reqType] = ($adjusted_values[$reqType] ?? 0.0) + $numVal;
								}
							}
						}

						$requirements = [
							'product'  => $temp_requirements['product'] ?? [],
							'cart'     => $temp_requirements['cart'] ?? [],
							'customer' => $temp_requirements['customer'] ?? [],
							'other'    => $temp_requirements['other'] ?? [],
						];

						if ($requirements['product'] || $requirements['customer'] || $requirements['other']) {
							$requirement_status = [];

							if (!empty($requirements['product']) && $products) {
								$product_requirement_status = [];
								$product_status = [];
								foreach ($requirements['product'] as $type => $values) {
									foreach ($values as $requirement_key => $value) {
										foreach ($products as $product_key => $product) {
											$temp_status = $this->checkRequirementProduct($product, 'product', $type, (string)$value['operation'], $value['value'], (array)($value['parameter'] ?? []), $debug);
											$product_requirement_status[$requirement_key][] = $temp_status;
											$product_status[$product_key][] = $temp_status;
										}
									}
								}

								$reqCostMethod = (string)($rate['requirement_cost'] ?? 'every');
								foreach ($product_status as $product_key => $valArr) {
									if ($reqCostMethod === 'all' && in_array(false, $valArr, true)) {
										unset($products[$product_key]);
									}
									if ($reqCostMethod === 'any' && !in_array(true, $valArr, true)) {
										unset($products[$product_key]);
									}
									if ($reqCostMethod === 'none' && in_array(true, $valArr, true)) {
										unset($products[$product_key]);
									}
								}

								foreach ($requirements['product'] as $type => $values) {
									foreach ($values as $requirement_key => $value) {
										$temp_status = false;
										$match = (string)($value['parameter']['match'] ?? 'all');
										$arr = $product_requirement_status[$requirement_key] ?? [];
										if ($match === 'all' && !in_array(false, $arr, true)) {
											$temp_status = true;
										}
										if ($match === 'any' && in_array(true, $arr, true)) {
											$temp_status = true;
										}
										if ($match === 'none' && !in_array(true, $arr, true)) {
											$temp_status = true;
										}
										$requirement_status[] = $temp_status;
									}
								}
							}

							if (!empty($requirements['customer'])) {
								foreach ($requirements['customer'] as $type => $values) {
									foreach ($values as $value) {
										$requirement_status[] = $this->checkRequirementCustomer($customer, 'customer', $type, (string)$value['operation'], $value['value'], (array)($value['parameter'] ?? []), $debug);
									}
								}
							}

							if (!empty($requirements['other'])) {
								foreach ($requirements['other'] as $type => $values) {
									foreach ($values as $value) {
										$requirement_status[] = $this->checkRequirementOther($other, 'other', $type, (string)$value['operation'], $value['value'], (array)($value['parameter'] ?? []), $debug);
									}
								}
							}

							$matchMethod = (string)($rate['requirement_match'] ?? 'all');
							if ($matchMethod === 'all' && in_array(false, $requirement_status, true)) {
								$status = false;
							}
							if ($matchMethod === 'any' && !in_array(true, $requirement_status, true) && empty($requirements['cart'])) {
								$status = false;
							}
							if ($matchMethod === 'none' && in_array(true, $requirement_status, true)) {
								$status = false;
							}

							if (!$status && !empty($rate['fail_method']) && $products) {
								$this->status = false;
							}
						}

						if (!$status) {
							continue;
						}

						if ($products) {
							foreach ($this->cartGeoZones as $geo_zone) {
								$gzId = (int)$geo_zone['geo_zone_id'];
								if (!empty($rate['shipping'][$gzId]) && (!empty($rate['shipping'][$gzId]['rates']) || !empty($rate['shipping'][$gzId]['shipping_method']))) {
									$shippingInfo = $rate['shipping'][$gzId];
									$rateTypeSetting = (string)($shippingInfo['rate_type'] ?? '');
									$cart_dist_calc = false;

									if (in_array($rateTypeSetting, $this->rateTypes, true) && empty($shippingInfo['rates'])) {
										$status = false;
									}
									if (str_contains($rateTypeSetting, 'dim_weight') && empty($shippingInfo['shipping_factor'])) {
										$status = false;
									}
									if (str_contains($rateTypeSetting, 'distance') && empty($rate['origin'])) {
										$status = false;
									}
									if (str_contains($rateTypeSetting, 'distance')) {
										$cart_dist_calc = true;
									}

									if (!$status) {
										continue;
									}

									// Cart requirements
									$requirement_status = [];
									if (!empty($requirements['cart'])) {
										if (!$cart_dist_calc && !empty($origin['origin']) && array_key_exists('cart_distance', $requirements['cart'])) {
											$cart_dist_calc = true;
										}
										$cart = $this->calculateCart($products, $adjusted_values, (float)($shippingInfo['shipping_factor'] ?? 0), $origin, $destination, (string)($shippingInfo['currency'] ?? ''), (int)($rate['total_type'] ?? 0), $cart_dist_calc, $debug);
										foreach ($requirements['cart'] as $type => $values) {
											foreach ($values as $value) {
												$requirement_status[] = $this->checkRequirementCart($cart, 'cart', $type, (string)$value['operation'], $value['value'], (array)($value['parameter'] ?? []), $debug);
											}
										}

										if ($matchMethod === 'all' && in_array(false, $requirement_status, true)) {
											$status = false;
										}
										if ($matchMethod === 'any' && !in_array(true, $requirement_status, true)) {
											$status = false;
										}
										if ($matchMethod === 'none' && in_array(true, $requirement_status, true)) {
											$status = false;
										}

										if (!$status && !empty($rate['fail_method']) && $products) {
											$this->status = false;
										}
									} else {
										$cart = $this->calculateCart($products, $adjusted_values, (float)($shippingInfo['shipping_factor'] ?? 0), $origin, $destination, (string)($shippingInfo['currency'] ?? ''), (int)($rate['total_type'] ?? 0), $cart_dist_calc, $debug);
									}

									if (!$status) {
										continue;
									}

									$cost = '';
									if ($products) {
										if (in_array($rateTypeSetting, $this->rateTypes, true)) {
											if (str_starts_with($rateTypeSetting, 'product_')) {
												$propKey = str_replace('product_', '', $rateTypeSetting);
												foreach ($products as $product) {
													$val = $product[$propKey] ?? 0;
													$finalCostMethod = (int)($shippingInfo['final_cost'] ?? 0);
													$costData = ($finalCostMethod === 1) ? $this->getRateCumulative((float)$val, (array)$shippingInfo['rates'], (float)$product['total'], $debug) : $this->getRateSingle((float)$val, (array)$shippingInfo['rates'], (float)$product['total'], $debug);

													if ((string)$costData !== '') {
														$cost = (float)$cost;
														if ($rateTypeSetting !== 'product_quantity') {
															$cost += (float)$costData * (int)$product['quantity'];
														} else {
															$cost += (float)$costData;
														}
													}
												}
											} else {
												$propKey = str_replace('cart_', '', $rateTypeSetting);
												$val = (float)($cart[$propKey] ?? 0);
												if (!empty($shippingInfo['split'])) {
													$max_rate = $this->getRateMax((array)$shippingInfo['rates']);
													$max_rate = (str_starts_with((string)$max_rate, '~')) ? 1.0 : (float)$max_rate;
													$divide = ceil($val / max($max_rate, 1));
												} else {
													$divide = 1;
													$max_rate = $val;
												}
												for ($x = 1; $x <= $divide; $x++) {
													$split_value = (!empty($shippingInfo['split'])) ? ($divide === $x ? $val - ($max_rate * ($x - 1)) : $max_rate) : $val;
													$finalCostMethod = (int)($shippingInfo['final_cost'] ?? 0);
													$costData = ($finalCostMethod === 1) ? $this->getRateCumulative((float)$split_value, (array)$shippingInfo['rates'], (float)($cart['total'] ?? 0), $debug) : $this->getRateSingle((float)$split_value, (array)$shippingInfo['rates'], (float)($cart['total'] ?? 0), $debug);
													if ((string)$costData !== '') {
														$cost  = (float)$cost;
														$cost += (float)$costData;
													}
												}
											}
										} elseif (!empty($shippingInfo['shipping_method'])) {
											$costData = $this->getShipping($rateTypeSetting, (string)$shippingInfo['shipping_method'], $address, $debug);
											if ((string)$costData !== '') {
												$cost  = (float)$cost;
												$cost += (float)$costData;
											}
										}
									}

									if ((string)$cost !== '') {
										$cost = (float)$cost;
										$costMin = $shippingInfo['cost']['min'] ?? null;
										if ($costMin !== null && $costMin !== '' && $cost < (float)$costMin) {
											$cost = (float)$costMin;
										}

										$costMax = $shippingInfo['cost']['max'] ?? null;
										if ($costMax !== null && $costMax !== '' && $cost > (float)$costMax) {
											$cost = (float)$costMax;
										}

										$costAdd = $shippingInfo['cost']['add'] ?? null;
										if ($costAdd !== null && $costAdd !== '') {
											$costAddStr = (string)$costAdd;
											if (str_contains($costAddStr, '%')) {
												$cost += $cost * ((float)$costAddStr / 100);
											} else {
												$cost += (float)$costAddStr;
											}
										}

										$freightFee = $shippingInfo['freight_fee'] ?? null;
										if ($freightFee !== null && $freightFee !== '') {
											$freightFeeStr = (string)$freightFee;
											if (str_contains($freightFeeStr, '%')) {
												$cost += $cost * ((float)$freightFeeStr / 100);
											} else {
												$cost += (float)$freightFeeStr;
											}
										}

										// Convert Currency safely for OC4
										if (in_array($rateTypeSetting, $this->rateTypes, true)) {
											$rateCurrency = (string)($shippingInfo['currency'] ?? $this->config->get('config_currency'));
											$configCurrency = (string)$this->config->get('config_currency');
											if ($rateCurrency !== $configCurrency) {
												$cost = $this->convertCurrency($cost, $rateCurrency, $configCurrency);
											}
										}

										$rateNameLang     = is_array($rate['name'] ?? null) ? ($rate['name'][$languageCode] ?? reset($rate['name']) ?: '') : '';
										$rate_name        = $rateNameLang !== '' ? $rateNameLang : $rate_name;
										$shipping_name    = $rateNameLang !== '' ? $rateNameLang : $shipping_name;

										$group_status = false;
										if (!empty($rate['group']) && $this->combinations) {
											$groups = explode(',', (string)$rate['group']);
											foreach ($groups as $grp) {
												$grp = trim(mb_strtoupper($grp));
												if (is_array($this->combinations)) {
													foreach ($this->combinations as $comb) {
														if (str_contains(mb_strtoupper((string)($comb['formula'] ?? '')), '{' . $grp . '}')) {
															$group_status = true;
															break;
														}
													}
												}
												if ($group_status) {
													break;
												}
											}
										}

										if ($group_status && isset($groups)) {
											foreach ($groups as $grpKey) {
												$grpKey = trim(mb_strtoupper($grpKey));
												$combined_data[$grpKey][] = [
													'title'        => $shipping_name,
													'sort_order'   => (int)($rate['sort_order'] ?? 0),
													'tax_class_id' => (int)($rate['tax_class_id'] ?? 0),
													'cost'         => $cost,
												];
											}
										} else {
											$rate_data = [
												'title'        => $shipping_name,
												'sort_order'   => (int)($rate['sort_order'] ?? 0),
												'tax_class_id' => (int)($rate['tax_class_id'] ?? 0),
												'cost'         => $cost,
												'code'         => $rate['rate_id'] . '_' . $gzId,
											];
											$quote_data[$this->extension . '_' . $rate['rate_id'] . '_' . $gzId] = $this->getQuoteData($rate_data);
										}
									}
								}
							}
						}
					}
					$this->writeDebug($debug);
				}
			}

			// Combinations
			$combine_regex = [
				'sum' => '/SUM\(([A-Z0-9\,\.\{\}]+)\)/',
				'avg' => '/AVG\(([A-Z0-9\,\.\{\}]+)\)/',
				'min' => '/MIN\(([A-Z0-9\,\.\{\}]+)\)/',
				'max' => '/MAX\(([A-Z0-9\,\.\{\}]+)\)/',
			];

			$combination_row = 1;
			if ($this->status && $this->combinations && is_array($this->combinations) && !empty($combined_data)) {
				$debug = [];

				foreach ($this->combinations as $combKey => $value) {
					$titleDisplay = (int)($value['title_display'] ?? 0);
					$title = ($titleDisplay === 4) ? (!empty($value['title'][$languageCode]) ? (string)$value['title'][$languageCode] : $this->language->get('text_title')) : '';
					$tax_class_id = 0;

					$formula = mb_strtoupper(preg_replace('/\s+/', '', (string)($value['formula'] ?? '')));
					$combine_status = true;

					if ($formula !== '' && substr_count($formula, '(') === substr_count($formula, ')') && substr_count($formula, '{') === substr_count($formula, '}')) {
						while ($combine_status && preg_match('/(SUM|AVG|MIN|MAX)\(([A-Z0-9\,\.\{\}]+)\)/', $formula)) {
							$before = $formula;
							foreach ($combine_regex as $regex_key => $regex_value) {
								preg_match($regex_value, $formula, $matches);
								$x = 1;
								while (isset($matches[$x])) {
									if (!preg_match('/^([0-9\,\.\+\-\*\/\(\)]+)$/', $matches[$x])) {
										$rate_groups = explode(',', $matches[$x]);
										foreach ($rate_groups as $rate_group) {
											if (preg_match('/^\{([A-Z0-9]+)\}$/', $rate_group)) {
												$rate_group_key = str_replace(['{', '}'], '', $rate_group);
												if (isset($combined_data[$rate_group_key])) {
													$rate_group_value = 0.0;
													$arrCosts = [];
													foreach ($combined_data[$rate_group_key] as $cData) {
														if (($titleDisplay === 0 && $title === '') || $titleDisplay === 1) {
															$title = $cData['title'];
														} elseif ($titleDisplay === 2 || $titleDisplay === 3) {
															$title .= ($title !== '') ? ' + ' . $cData['title'] : $cData['title'];
															if ($titleDisplay === 3) {
																$title .= '(' . $this->currency->format((float)$this->tax->calculate((float)$cData['cost'], (int)$cData['tax_class_id'], (bool)$this->config->get('config_tax')), (string)$this->session->data['currency']) . ')';
															}
														}
														$tax_class_id = (int)$cData['tax_class_id'];
														$arrCosts[]   = (float)$cData['cost'];
													}

													$rate_group_val_calc = match ($regex_key) {
														'sum' => array_sum($arrCosts),
														'avg' => array_sum($arrCosts) / count($arrCosts),
														'min' => min($arrCosts),
														'max' => max($arrCosts),
														default => 0.0,
													};

													$matches[$x] = str_replace($rate_group, (string)$rate_group_val_calc, $matches[$x]);
												} else {
													if (!empty($value['method'])) {
														$matches[$x] = str_replace($rate_group, '0', $matches[$x]);
													} else {
														$combine_status = false;
														break;
													}
												}
											}
										}
									}
									if (!$combine_status) {
										break;
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
								if (!$combine_status) {
									break;
								}
							}
							// Guard against malformed formulas (e.g. SUM({A}{B})) that never resolve
							if ($formula === $before) {
								$combine_status = false;
							}
						}
					}

					if (preg_match('/^([0-9\,\.\+\-\*\/\(\)]+)$/', $formula) && substr_count($formula, '(') === substr_count($formula, ')')) {
						// Guard: reject any /0 pattern to prevent DivisionByZeroError
						if (!preg_match('/\/\s*0(?:[^.]|$)/', $formula)) {
							try {
								$evalRes = @eval('return(' . $formula . ');');
								if ($evalRes !== false && is_numeric($evalRes)) {
									$cost = (float)$evalRes;
									$rate_data = [
										'title'        => $title,
										'sort_order'   => (int)($value['sort_order'] ?? 0),
										'tax_class_id' => $tax_class_id,
										'cost'         => $cost,
										'code'         => 'C' . $combination_row,
									];
									$quote_data[$this->extension . '_C' . $combination_row] = $this->getQuoteData($rate_data);
								}
							} catch (\Throwable $e) {
								// Ignore mathematical eval errors
							}
						}
					}
					$combination_row++;
				}
			}

			if ($this->status && $quote_data) {
				$sort_order = [];
				$sort_cost  = [];
				foreach ($quote_data as $k => $v) {
					$sort_order[$k] = $v['sort_order'];
					$sort_cost[$k]  = $v['value'];
				}

				$sortMode = (int)$this->field('sort_quotes');
				if ($sortMode === 1) {
					array_multisort($sort_order, SORT_DESC, $quote_data);
				} elseif ($sortMode === 2) {
					array_multisort($sort_cost, SORT_ASC, $quote_data);
				} elseif ($sortMode === 3) {
					array_multisort($sort_cost, SORT_DESC, $quote_data);
				} else {
					array_multisort($sort_order, SORT_ASC, $quote_data);
				}

				$titleData = $this->field('title');
				$titleHeading = is_array($titleData) ? ($titleData[$languageCode] ?? reset($titleData) ?: '') : '';
				if ($titleHeading === '') {
					$titleHeading = $heading;
				}

				$method_data = [
					'id'         => $this->extension,
					'code'       => $this->extension . '.' . $this->extension,
					'name'       => $titleHeading,
					'title'      => $titleHeading,
					'quote'      => $quote_data,
					'sort_order' => (int)$this->field('sort_order'),
					'error'      => false,
				];
			}

			return $method_data;
		}

		return [];
	}

	private function rates(): array {
		$rates = [];
		if (!empty($this->field('cache')) && $this->cache->get($this->extension . '_rates')) {
			$rates = (array)$this->cache->get($this->extension . '_rates');
		}

		if (!$rates) {
			$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . $this->dbTable . "` WHERE `status` = '1' ORDER BY `sort_order`, `rate_id` ASC");
			$rates = $query->rows;
			if (!empty($this->field('cache'))) {
				$this->cache->set($this->extension . '_rates', $rates);
			}
		}

		return $rates;
	}

	private function field(string $field): mixed {
		$val = $this->config->get('shipping_' . $this->extension . '_' . $field);
		return $this->value($val);
	}

	private function value(mixed $value): mixed {
		if (is_string($value)) {
			$decoded = json_decode($value, true);
			if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
				return $decoded;
			}
		}

		return $value;
	}

	private function saveCart(): array {
		$products = [];
		$cart_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "cart` WHERE `customer_id` = '" . (int)$this->customer->getId() . "' AND `session_id` = '" . $this->db->escape($this->session->getId()) . "'");

		foreach ($cart_query->rows as $cart) {
			$products[] = [
				'cart_id'      => $cart['cart_id'],
				'product_id'   => $cart['product_id'],
				'quantity'     => $cart['quantity'],
				'option'       => json_decode($cart['option'], true),
				'recurring'      => $cart['subscription_plan_id'] ?? '',
			];
		}

		return $products;
	}

	private function getGeoZones(array $address): array {
		$geo_zones = [];
		$country_id = (int)($address['country_id'] ?? 0);
		$zone_id    = (int)($address['zone_id'] ?? 0);

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "geo_zone` ORDER BY `name`");
		foreach ($query->rows as $result) {
			$query_z2g = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . (int)$result['geo_zone_id'] . "' AND `country_id` = '" . $country_id . "' AND (`zone_id` = '" . $zone_id . "' OR `zone_id` = '0')");
			if ($query_z2g->num_rows) {
				$geo_zones[] = [
					'geo_zone_id' => $result['geo_zone_id'],
					'name'        => $result['name'],
				];
			}
		}

		if (!$geo_zones) {
			$geo_zones[] = [
				'geo_zone_id' => 0,
				'name'        => 'All Other Zones',
			];
		}

		return $geo_zones;
	}

	private function getProducts(): array {
		$products = [];
		$this->load->model('catalog/product');

		foreach ($this->cart->getProducts() as $product) {
			if (!empty($product['shipping'])) {
				$product_info = $this->model_catalog_product->getProduct((int)$product['product_id']);
				if ($product_info) {
					$qty = max((int)$product['quantity'], 1);
					$lengthUnit = 'length_class_id';
					$weightUnit = 'weight_class_id';

					$lengthVal = $this->length->convert((float)$product['length'], (string)($product[$lengthUnit] ?? 0), (string)$this->config->get('config_length_class_id'));
					$widthVal  = $this->length->convert((float)$product['width'], (string)($product[$lengthUnit] ?? 0), (string)$this->config->get('config_length_class_id'));
					$heightVal = $this->length->convert((float)$product['height'], (string)($product[$lengthUnit] ?? 0), (string)$this->config->get('config_length_class_id'));
					$weightVal = $this->weight->convert((float)$product['weight'], (string)($product[$weightUnit] ?? 0), (string)$this->config->get('config_weight_class_id')) / $qty;

					$products[uniqid((string)rand(), true)] = [
						'key'          => $product['key'] ?? '',
						'product_id'   => $product['product_id'],
						'quantity'     => $qty,
						'price'        => (float)$product['price'],
						'total'        => (float)$product['total'] / $qty,
						'tax_class_id' => (int)$product['tax_class_id'],
						'length'       => $lengthVal,
						'width'        => $widthVal,
						'height'       => $heightVal,
						'volume'       => $lengthVal * $widthVal * $heightVal,
						'weight'       => $weightVal,
						'category'     => $this->model_catalog_product->getCategories((int)$product['product_id']),
						'attribute'    => $this->model_catalog_product->getAttributes((int)$product['product_id']),
						'name'         => (string)$product['name'],
						'model'        => (string)$product['model'],
						'sku'          => (string)($product_info['sku'] ?? ''),
						'upc'          => (string)($product_info['upc'] ?? ''),
						'ean'          => (string)($product_info['ean'] ?? ''),
						'jan'          => (string)($product_info['jan'] ?? ''),
						'isbn'         => (string)($product_info['isbn'] ?? ''),
						'mpn'          => (string)($product_info['mpn'] ?? ''),
						'location'     => (string)($product_info['location'] ?? ''),
						'stock'        => (int)($product_info['quantity'] ?? 0),
						'manufacturer' => (int)($product_info['manufacturer_id'] ?? 0),
						'option'       => (array)($product['option'] ?? []),
						'recurring'    => $product['recurring'] ?? '',
					];
				}
			}
		}

		return $products;
	}

	private function getDestination(array $address): array {
		$destination = $address;
		if (!empty($address)) {
			$country_id = (int)($address['country_id'] ?? 0);
			$zone_id    = (int)($address['zone_id'] ?? 0);

			$country_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "country` WHERE `country_id` = '" . $country_id . "'");
			$zone_query    = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone` WHERE `zone_id` = '" . $zone_id . "'");

			if ($country_query->num_rows) {
				$destination['zone']    = $zone_query->row['name'] ?? '';
				$destination['country'] = $country_query->row['name'] ?? '';
			}
		}

		return $destination;
	}

	private function getDistance(array $origin, array $destination, array &$debug): float {
		if ($origin && $destination) {
			$directions = $this->getDirections((string)$origin['origin'], $destination, $debug);
			if ($directions !== false) {
				return (float)$directions['value'];
			}

			// Fallback: Haversine distance
			$geocode = $this->getGeoCode($destination, $debug);
			if ($geocode !== false) {
				$r     = 6371;
				$lat1  = deg2rad((float)$origin['lat']);
				$lat2  = deg2rad((float)$geocode['lat']);
				$lng1  = deg2rad((float)$origin['lng']);
				$lng2  = deg2rad((float)$geocode['lng']);
				$dlat  = $lat2 - $lat1;
				$dlng  = $lng2 - $lng1;
				$a     = sin($dlat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dlng / 2) ** 2;
				$c     = 2 * atan2(sqrt($a), sqrt(1 - $a));

				return (float)($r * $c);
			}
		}

		return 0.0;
	}

	private function getDirections(string $origin, array $destination, array &$debug): array|bool {
		$cache_key = $this->extension . '_directions_' . preg_replace('/[^\w]/', '', $origin) . '_' . preg_replace('/[^\w]/', '', ($destination['postcode'] ?? '') . ($destination['zone'] ?? '') . ($destination['country'] ?? ''));

		if (!empty($this->field('cache')) && $this->cache->get($cache_key)) {
			return (array)$this->cache->get($cache_key);
		}

		$apiKey = (string)$this->field('gmaps_api_key');
		$dest_1 = ($destination['address_1'] ?? '') . ', ' . ($destination['city'] ?? '') . ', ' . ($destination['postcode'] ?? '') . ', ' . ($destination['zone'] ?? '') . ', ' . ($destination['country'] ?? '');

		if (!empty($destination['address_1'])) {
			$url = 'https://maps.googleapis.com/maps/api/directions/xml?origin=' . urlencode($origin) . '&destination=' . urlencode($dest_1) . '&sensor=false';
			if ($apiKey !== '') {
				$url .= '&key=' . urlencode($apiKey);
			}

			try {
				$context = stream_context_create(['http' => ['timeout' => 5]]);
				$xmlContent = @file_get_contents($url, false, $context);
				if ($xmlContent !== false) {
					$response = @simplexml_load_string($xmlContent);
					if ($response && (string)$response->status === 'OK' && isset($response->route->leg->distance)) {
						$value = [
							'value' => (float)$response->route->leg->distance->value / 1000,
							'text'  => (string)$response->route->leg->distance->text,
						];
						if (!empty($this->field('cache'))) {
							$this->cache->set($cache_key, $value);
						}
						return $value;
					}
				}
			} catch (\Throwable $e) {
				// Ignore
			}
		}

		return false;
	}

	private function getGeoCode(array $destination, array &$debug): array|bool {
		$cache_key = $this->extension . '_geocode_' . preg_replace('/[^\w]/', '', ($destination['postcode'] ?? '') . ($destination['zone'] ?? '') . ($destination['country'] ?? ''));

		if (!empty($this->field('cache')) && $this->cache->get($cache_key)) {
			return (array)$this->cache->get($cache_key);
		}

		$apiKey = (string)$this->field('gmaps_api_key');
		$dest = ($destination['postcode'] ?? '') . ' ' . ($destination['zone'] ?? '') . ' ' . ($destination['country'] ?? '');
		$url  = 'https://maps.googleapis.com/maps/api/geocode/xml?address=' . urlencode($dest) . '&sensor=false';
		if ($apiKey !== '') {
			$url .= '&key=' . urlencode($apiKey);
		}

		try {
			$context = stream_context_create(['http' => ['timeout' => 5]]);
			$xmlContent = @file_get_contents($url, false, $context);
			if ($xmlContent !== false) {
				$response = @simplexml_load_string($xmlContent);
				if ($response && (string)$response->status === 'OK') {
					$value = [
						'lat' => (float)$response->result->geometry->location->lat,
						'lng' => (float)$response->result->geometry->location->lng,
					];
					if (!empty($this->field('cache'))) {
						$this->cache->set($cache_key, $value);
					}
					return $value;
				}
			}
		} catch (\Throwable $e) {
			// Ignore
		}

		return false;
	}

	private function calculateCart(array $products, array $adjusted_values, float $shipping_factor, array $origin = [], array $destination = [], string $currency = '', int $total_type = 0, bool $cart_dist_calc = false, array &$debug = []): array {
		$cart = [
			'quantity'   => 0,
			'total'      => 0.0,
			'weight'     => 0.0,
			'dim_weight' => 0.0,
			'volume'     => 0.0,
			'length'     => 0.0,
			'width'      => 0.0,
			'height'     => 0.0,
			'distance'   => 0.0,
		];

		if ($products) {
			// Total = Sub-Total + Tax + any other OC totals (coupon, voucher, reward, etc.)
			// cart->getTotal() sums all total-extension rows (subtotal, tax, coupon, voucher, etc.)
			$grandTotal = ($total_type === 2) ? (float)$this->cart->getTotal() : 0.0;

			foreach ($products as $product) {
				$qty = (int)$product['quantity'];
				$adjQty = $adjusted_values['product_quantity'] ?? null;
				if ($adjQty !== null && $adjQty !== '') {
					$adjStr = (string)$adjQty;
					$itemQty = str_contains($adjStr, '%') ? $qty + ($qty * ((float)$adjStr / 100)) : $qty + (float)$adjStr;
				} else {
					$itemQty = $qty;
				}
				$cart['quantity'] += (int)ceil($itemQty);

				$price = (float)$product['price'];
				$adjTotal = $adjusted_values['product_total'] ?? null;
				if ($adjTotal !== null && $adjTotal !== '') {
					$adjStr = (string)$adjTotal;
					$unitPrice = str_contains($adjStr, '%') ? $price + ($price * ((float)$adjStr / 100)) : $price + (float)$adjStr;
				} else {
					$unitPrice = $price;
				}

				if ($total_type === 0) {
					$cart['total'] += $unitPrice * $qty;
				} elseif ($total_type === 1) {
					$cart['total'] += (float)$this->tax->calculate($unitPrice, (int)$product['tax_class_id'], (bool)$this->config->get('config_tax')) * $qty;
				} elseif ($total_type === 2) {
					// Full cart total already computed once before the loop
					$cart['total'] = $grandTotal;
				}

				$weight = (float)$product['weight'];
				$adjWeight = $adjusted_values['product_weight'] ?? null;
				if ($adjWeight !== null && $adjWeight !== '') {
					$adjStr = (string)$adjWeight;
					$unitWeight = str_contains($adjStr, '%') ? $weight + ($weight * ((float)$adjStr / 100)) : $weight + (float)$adjStr;
				} else {
					$unitWeight = $weight;
				}
				$cart['weight'] += $unitWeight * $qty;

				$volume = (float)$product['volume'];
				if ($shipping_factor > 0) {
					$dimW = ($volume / $shipping_factor > $weight) ? ($volume / $shipping_factor) : $weight;
					$cart['dim_weight'] += $dimW * $qty;
				}

				$cart['volume'] += $volume * $qty;
				$cart['length'] += (float)$product['length'] * $qty;
				$cart['width']  += (float)$product['width'] * $qty;
				$cart['height'] += (float)$product['height'] * $qty;
			}

			if ($cart_dist_calc && !empty($origin['origin']) && !empty($origin['lat']) && !empty($origin['lng']) && !empty($destination)) {
				$cart['distance'] = $this->getDistance($origin, $destination, $debug);
			}

			foreach ($cart as $key => $val) {
				$adjCart = $adjusted_values['cart_' . $key] ?? null;
				if ($adjCart !== null && $adjCart !== '') {
					$adjStr = (string)$adjCart;
					if ($key === 'quantity') {
						$cart[$key] += (int)ceil(str_contains($adjStr, '%') ? $val * ((float)$adjStr / 100) : (float)$adjStr);
					} else {
						$cart[$key] += str_contains($adjStr, '%') ? $val * ((float)$adjStr / 100) : (float)$adjStr;
					}
				}
			}

			$configCurrency = (string)$this->config->get('config_currency');
			if ($currency !== '' && $currency !== $configCurrency) {
				$cart['total'] = $this->convertCurrency((float)$cart['total'], $configCurrency, $currency);
			}
		}

		return $cart;
	}

	private function checkRequirementProduct(array $product, string $group, string $type, string $operation, mixed $value, array $parameter, array &$debug): bool {
		$type   = str_replace($group . '_', '', $type);
		$values = array_map('mb_strtolower', is_array($value) ? $value : explode(',', (string)$value));
		$status = in_array($operation, ['neq', 'nstrpos'], true);

		if (str_contains($type, 'option')) {
			$parts    = explode('_', $type);
			$optionId = (int)($parts[1] ?? 0);
			$type     = 'option';
			if (!empty($product['option']) && is_array($product['option'])) {
				foreach ($product['option'] as $pOpt) {
					if ((int)($pOpt['option_id'] ?? 0) === $optionId) {
						$optVal = mb_strtolower((string)($pOpt['option_value_id'] ?? $pOpt['value'] ?? ''));
						if ($operation === 'eq' && in_array($optVal, $values, true)) { $status = true; break; }
						if ($operation === 'neq' && in_array($optVal, $values, true)) { $status = false; break; }
						if (in_array($operation, ['strpos', 'nstrpos', 'gte', 'lte'], true)) {
							foreach ($values as $param) {
								if ($operation === 'strpos' && str_contains($optVal, $param)) { $status = true; break; }
								if ($operation === 'nstrpos' && str_contains($optVal, $param)) { $status = false; break; }
								if ($operation === 'gte' && $optVal >= $param) { $status = true; break; }
								if ($operation === 'lte' && $optVal <= $param) { $status = true; break; }
							}
						}
					}
				}
			}
		} elseif (str_contains($type, 'attribute')) {
			$parts   = explode('_', $type);
			$attrId  = (int)($parts[1] ?? 0);
			if (!empty($product['attribute']) && is_array($product['attribute'])) {
				foreach ($product['attribute'] as $attrGroup) {
					if (!empty($attrGroup['attribute']) && is_array($attrGroup['attribute'])) {
						foreach ($attrGroup['attribute'] as $pAttr) {
							if ((int)($pAttr['attribute_id'] ?? 0) === $attrId) {
								if (in_array(mb_strtolower((string)($pAttr['text'] ?? '')), $values, true)) {
									$status = ($operation === 'eq');
								}
							}
						}
					}
				}
			}
		} elseif ($type === 'category') {
			if (!empty($product['category']) && is_array($product['category'])) {
				foreach ($product['category'] as $cat) {
					$cId = is_array($cat) ? (int)($cat['category_id'] ?? 0) : (int)$cat;
					if (in_array((string)$cId, $values, true)) {
						$status = ($operation === 'eq');
					}
				}
			}
		} elseif ($type === 'manufacturer') {
			$mId = (string)($product['manufacturer'] ?? 0);
			if (in_array($mId, $values, true)) {
				$status = ($operation === 'eq');
			}
		} else {
			$productVal = mb_strtolower((string)($product[$type] ?? ''));
			foreach ($values as $val) {
				$val = trim($val);
				$rangeStatus = false;
				if (str_contains($val, ':')) {
					$range = explode(':', $val);
					if (isset($range[0], $range[1])) {
						$rangeStatus = ((float)$productVal >= (float)trim($range[0]) && (float)$productVal <= (float)trim($range[1]));
					}
				}

				if ($operation === 'eq' && ($productVal === $val || $rangeStatus)) { $status = true; break; }
				if ($operation === 'neq' && ($productVal === $val || $rangeStatus)) { $status = false; break; }
				if ($operation === 'strpos' && str_contains($productVal, $val)) { $status = true; break; }
				if ($operation === 'nstrpos' && str_contains($productVal, $val)) { $status = false; break; }
				if ($operation === 'gte' && (float)$productVal >= (float)$val) { $status = true; break; }
				if ($operation === 'lte' && (float)$productVal <= (float)$val) { $status = true; break; }
			}
		}

		return $status;
	}

	private function checkRequirementCart(array $cart, string $group, string $type, string $operation, mixed $value, array $parameter, array &$debug): bool {
		$type   = str_replace($group . '_', '', $type);
		$valStr = mb_strtolower(trim((string)$value));
		$status = in_array($operation, ['neq', 'nstrpos'], true);
		$cartVal = (float)($cart[$type] ?? 0.0);
		$rangeStatus = false;

		if (str_contains($valStr, ':')) {
			$range = explode(':', $valStr);
			if (isset($range[0], $range[1])) {
				$rangeStatus = ($cartVal >= (float)trim($range[0]) && $cartVal <= (float)trim($range[1]));
			}
		}

		if ($operation === 'eq' && ($cartVal == (float)$valStr || $rangeStatus)) { $status = true; }
		if ($operation === 'neq' && ($cartVal != (float)$valStr && !$rangeStatus)) { $status = true; }
		if ($operation === 'gte' && $cartVal >= (float)$valStr) { $status = true; }
		if ($operation === 'lte' && $cartVal <= (float)$valStr) { $status = true; }

		return $status;
	}

	private function checkRequirementCustomer(array $customer, string $group, string $type, string $operation, mixed $value, array $parameter, array &$debug): bool {
		$type   = str_replace($group . '_', '', $type);
		$values = is_array($value) ? $value : explode(',', mb_strtolower((string)$value));
		$status = in_array($operation, ['neq', 'nstrpos'], true);

		if (str_contains($type, 'customfield')) {
			$parts = explode('_', $type);
			$cfId  = (int)($parts[1] ?? 0);
			$userVal = $customer['customfield'][$cfId] ?? null;
			if ($userVal !== null) {
				$userValStr = mb_strtolower((string)$userVal);
				if ($operation === 'eq' && in_array($userValStr, $values, true)) { $status = true; }
				if ($operation === 'neq' && in_array($userValStr, $values, true)) { $status = false; }
				if (in_array($operation, ['strpos', 'nstrpos', 'gte', 'lte'], true)) {
					foreach ($values as $param) {
						if ($operation === 'strpos' && str_contains($userValStr, $param)) { $status = true; break; }
						if ($operation === 'nstrpos' && str_contains($userValStr, $param)) { $status = false; break; }
						if ($operation === 'gte' && $userValStr >= $param) { $status = true; break; }
						if ($operation === 'lte' && $userValStr <= $param) { $status = true; break; }
					}
				}
			}
		} else {
			$custVal = mb_strtolower((string)($customer[$type] ?? ''));
			foreach ($values as $val) {
				$val = trim($val);
				if ($type === 'postcode') {
					$postcodeStatus = $this->checkPostalCodes($custVal, $val, (string)($parameter['type'] ?? 'other'), $debug);
					if ($postcodeStatus) {
						if ($operation === 'eq') { $status = true; }
						if ($operation === 'neq') { $status = false; }
						break;
					}
				} else {
					if ($operation === 'eq' && $custVal === $val) { $status = true; break; }
					if ($operation === 'neq' && $custVal === $val) { $status = false; break; }
					if ($operation === 'strpos' && str_contains($custVal, $val)) { $status = true; break; }
					if ($operation === 'nstrpos' && str_contains($custVal, $val)) { $status = false; break; }
				}
			}
		}

		return $status;
	}

	private function checkRequirementOther(array $other, string $group, string $type, string $operation, mixed $value, array $parameter, array &$debug): bool {
		$type   = str_replace($group . '_', '', $type);
		$values = is_array($value) ? $value : explode(',', (string)$value);
		$otherVal = trim((string)($other[$type] ?? ''));
		$status = false;

		if ($type === 'day') {
			if ($operation === 'eq' && in_array($otherVal, $values, true)) {
				$status = true;
			}
			if ($operation === 'neq' && !in_array($otherVal, $values, true)) {
				$status = true;
			}
		} else {
			foreach ($values as $val) {
				$val = trim($val);
				if ($operation === 'eq' && $otherVal === $val) { $status = true; break; }
				if ($operation === 'neq' && $otherVal !== $val) { $status = true; break; }
				if ($operation === 'gte' && $otherVal >= $val) { $status = true; break; }
				if ($operation === 'lte' && $otherVal <= $val) { $status = true; break; }
			}
		}

		return $status;
	}

	private function checkPerProductShipping(int $product_id, int $geo_zone_id): bool {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_shipping` WHERE `product_id` = '" . $product_id . "' AND `geo_zone_id` = '" . $geo_zone_id . "'");
		return ($query->row && !empty($query->row['first']));
	}

	private function checkPostalCodes(string $postcode, string $rangeStr, string $type, array &$debug): bool {
		$status = false;

		if ($postcode !== '' && $rangeStr !== '') {
			$range = explode(':', $rangeStr);
			$postcode = trim(preg_replace('/[\s\-]/', '', mb_strtoupper($postcode)));

			if (isset($range[0], $range[1])) {
				$start = trim(preg_replace('/[\s\-]/', '', mb_strtoupper($range[0])));
				$end   = trim(preg_replace('/[\s\-]/', '', mb_strtoupper($range[1])));

				if ($type === 'uk') {
					foreach ($this->ukFormats as $format) {
						if (preg_match($format['regex'], $postcode) && (preg_match($format['regex'], $start) || preg_match($format['regex'], $end))) {
							if (strnatcmp($start, $postcode) <= 0 && strnatcmp($end, $postcode) >= 0) {
								$status = true;
							}
						}
					}
				} else {
					$x = max(strlen($start), strlen($end));
					$modPostcode = substr($postcode, 0, $x);
					if (strnatcmp($start, $modPostcode) <= 0 && strnatcmp($end, $modPostcode) >= 0) {
						$status = true;
					}
				}
			} else {
				$r0 = trim(preg_replace('/[\s\-]/', '', mb_strtoupper($range[0])));
				if (str_starts_with($postcode, $r0)) {
					$status = true;
				}
			}
		} elseif ($rangeStr === '') {
			$status = true;
		}

		return $status;
	}

	private function getRateMax(array $rates): mixed {
		$rate = end($rates);
		return !empty($rate['max']) ? $rate['max'] : 0;
	}

	private function getRateSingle(float $value, array $rates, float $total, array &$debug): float|string {
		$quote = '';
		foreach ($rates as $rate) {
			$maxVal = $rate['max'] ?? 0;
			if ((float)$maxVal >= $value || $maxVal === '~') {
				$costStr = (string)($rate['cost'] ?? 0);
				$cost    = str_contains($costStr, '%') ? $total * ((float)$costStr / 100) : (float)$costStr;
				$perVal  = (float)($rate['per'] ?? 0);
				$quote   = ($perVal > 0) ? ceil($value / $perVal) * $cost : $cost;
				break;
			}
		}

		return $quote;
	}

	private function getRateCumulative(float $value, array $rates, float $total, array &$debug): float|string {
		$quote     = '';
		$prev      = 0.0;
		$maxFound  = false;

		foreach ($rates as $rate) {
			$maxVal = $rate['max'] ?? 0;
			$costStr= (string)($rate['cost'] ?? 0);
			$cost   = str_contains($costStr, '%') ? $total * ((float)$costStr / 100) : (float)$costStr;
			$perVal = (float)($rate['per'] ?? 0);

			if ($maxVal !== '~' && (float)$maxVal < $value) {
				$quote = (float)$quote;
				$quote += ($perVal > 0) ? ceil(((float)$maxVal - $prev) / $perVal) * $cost : $cost;
				$prev  = (float)$maxVal;
			} else {
				$quote = (float)$quote;
				$quote += ($perVal > 0) ? ceil(($value - $prev) / $perVal) * $cost : $cost;
				$maxFound = true;
				break;
			}
		}

		if (!$maxFound) {
			$quote = '';
		}

		return $quote;
	}

	private function getShipping(string $code, string $rates, array $address, array &$debug): float|string {
		$cost = '';

		try {
			$this->load->model('extension/' . $code . '/shipping/' . $code);
			$modelName = 'model_extension_' . $code . '_shipping_' . $code;
			if (isset($this->{$modelName})) {
				$shipping_method = $this->{$modelName}->getQuote($address);
				if (!empty($shipping_method['quote'])) {
					foreach ($shipping_method['quote'] as $quote) {
						if (count($shipping_method['quote']) > 1) {
							if ($rates !== '' && (str_contains(mb_strtolower((string)$quote['code']), mb_strtolower($rates)) || str_contains(mb_strtolower((string)$quote['title']), mb_strtolower($rates)))) {
								$cost  = (float)$cost;
								$cost += (float)$quote['cost'];
								break;
							}
						} else {
							$cost  = (float)$cost;
							$cost += (float)$quote['cost'];
						}
					}
				}
			}
		} catch (\Throwable $e) {
			// Fail gracefully if sub-shipping extension is not available
		}

		return $cost;
	}

	private function getQuoteData(array $data): array {
		$decimalPlace = (int)$this->currency->getDecimalPlace((string)$this->session->data['currency']);
		$cost = round((float)$data['cost'], $decimalPlace);

		return [
			'id'           => $this->extension . '.' . $this->extension . '_' . $data['code'],
			'code'         => $this->extension . '.' . $this->extension . '_' . $data['code'],
			'name'         => (string)$data['title'],
			'title'        => (string)$data['title'],
			'cost'         => $cost,
			'value'        => $cost,
			'text'         => $this->currency->format((float)$this->tax->calculate($cost, (int)$data['tax_class_id'], (bool)$this->config->get('config_tax')), (string)$this->session->data['currency']),
			'sort_order'   => (int)$data['sort_order'],
			'tax_class_id' => (int)$data['tax_class_id'],
		];
	}

	private function convertCurrency(float $amount, string $from, string $to): float {
		if ($from === $to) {
			return $amount;
		}

		$fromValue = (float)$this->currency->getValue($from);
		$toValue   = (float)$this->currency->getValue($to);

		if ($fromValue > 0.0) {
			return ($amount / $fromValue) * $toValue;
		}

		return $amount;
	}

	private function writeDebug(mixed $debug): void {
		if ($this->debugStatus && $debug) {
			$write  = date('Y-m-d H:i:s') . ' - ';
			$write .= print_r($debug, true) . "\n";
			$file   = DIR_LOGS . $this->extension . '.txt';

			file_put_contents($file, $write, FILE_APPEND);
		}
	}
}
