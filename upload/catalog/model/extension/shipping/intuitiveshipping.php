<?php
//==================================================//
// Product:	Intuitive Shipping              		//
// Author: 	Joel Reeds                        		//
// Company: OpenCart Addons                  		//
// Website: http://www.opencartaddons.com        	//
// Contact: http://www.opencartaddons.com/contact  	//
//==================================================//

class ModelExtensionShippingIntuitiveShipping extends Model {
	private $extension 				= 'intuitiveshipping';
	private $type 					= 'shipping';
	private $db_table				= 'intuitive_shipping';

	private $status;
	private $debugStatus;

	private $cartGeoZones;
	private $cartProducts;

	private $dataCustomer;
	private $dataOther;

	private $savedCart;

	private $ukFormats;

	private $rateTypes;

	private $rateFormat1;
	private $rateFormat2;
	private $rateFormat3;
	private $rateFormat4;

	private $combinations;

	private function construct($address) {
		$this->status				= true;
		$this->debugStatus			= $this->field('debug');
		$this->cartGeoZones			= $this->getGeoZones($address);
		$this->cartProducts			= $this->getProducts();
		$this->savedCart			= $this->saveCart();

		$customer_info = array();
		if ($this->customer->isLogged()) {
			$this->load->model('account/customer');
			$customer_info = $this->model_account_customer->getCustomer($this->customer->getId());
		}

		$customer = array(
			'store'					=> (int)$this->config->get('config_store_id'),
			'group'					=> ($this->customer->isLogged()) ? (int)$this->customer->getGroupId() : 0,
			'name'					=> (!empty($address['firstname']) ? trim($address['firstname']) : '') . ' ' . (!empty($address['lastname']) ? trim($address['lastname']) : ''),
			'email'					=> ($this->customer->isLogged()) ? trim($customer_info['email']) : (!empty($this->session->data['guest']['email']) ? trim($this->session->data['guest']['email']) : ''),
			'telephone'				=> ($this->customer->isLogged()) ? trim($customer_info['telephone']) : (!empty($this->session->data['guest']['telephone']) ? trim($this->session->data['guest']['telephone']) : ''),
			'fax'					=> ($this->customer->isLogged()) ? trim($customer_info['fax']) : (!empty($this->session->data['guest']['fax']) ? trim($this->session->data['guest']['fax']) : ''),
			'company'				=> (!empty($address['company'])) ? trim($address['company']) : '',
			'address'				=> (!empty($address['address_1']) ? trim($address['address_1']) : '') . ' ' . (!empty($address['address_2']) ? trim($address['address_2']) : ''),
			'city'					=> (!empty($address['city'])) ? trim($address['city']) : '',
			'postcode'				=> (!empty($address['postcode'])) ? trim($address['postcode']) : '',
		);

		$customer['customfield']	= array();
		$customer['customfield']	= $customer['customfield'] + (!empty($customer_info['custom_field']) ? json_decode($customer_info['custom_field'], true) : (!empty($this->session->data['guest']['custom_field']) ? $this->session->data['guest']['custom_field'] : array()));
		$customer['customfield']	= $customer['customfield'] + (!empty($this->session->data['shipping_address']['custom_field']) ? $this->session->data['shipping_address']['custom_field'] : array());
		$customer['customfield']	= $customer['customfield'] + (!empty($this->session->data['payment_address']['custom_field']) ? $this->session->data['payment_address']['custom_field'] : array());

		$other = array(
			'currency'			=> $this->session->data['currency'],
			'day'				=> date('w')+1,
			'date'				=> date('Y-m-d'),
			'time'				=> date('H:i'),
		);

		$this->dataCustomer = $customer;
		$this->dataOther	= $other;

		$uk_formats 	= array();
		$uk_formats[]	= array(
			'regex'	=> '/^([A-Z]{2}[0-9]{1}[A-Z]{1}[0-9]{1}[A-Z]{2})$/',
			'start'	=> 'AA0A0AA',
			'end'	=> 'ZZ9Z9ZZ'
		);
		$uk_formats[]	= array(
			'regex'	=> '/^([A-Z]{1}[0-9]{1}[A-Z]{1}[0-9]{1}[A-Z]{2})$/',
			'start'	=> 'A0A0AA',
			'end'	=> 'Z9Z9ZZ'
		);
		$uk_formats[]	= array(
			'regex'	=> '/^([A-Z]{1}[0-9]{2}[A-Z]{2})$/',
			'start'	=> 'A00AA',
			'end'	=> 'Z99ZZ'
		);
		$uk_formats[]	= array(
			'regex'	=> '/^([A-Z]{1}[0-9]{3}[A-Z]{2})$/',
			'start'	=> 'A000AA',
			'end'	=> 'Z999ZZ'
		);
		$uk_formats[]	= array(
			'regex'	=> '/^([A-Z]{2}[0-9]{2}[A-Z]{2})$/',
			'start'	=> 'AA00AA',
			'end'	=> 'ZZ99ZZ'
		);
		$uk_formats[]	= array(
			'regex'	=> '/^([A-Z]{2}[0-9]{3}[A-Z]{2})$/',
			'start'	=> 'AA000AA',
			'end'	=> 'ZZ999ZZ'
		);
		$this->ukFormats	= $uk_formats;

		$this->rateTypes	= array('cart_quantity', 'cart_total', 'cart_weight', 'cart_volume', 'cart_dim_weight', 'cart_distance', 'cart_length', 'cart_width', 'cart_height', 'product_quantity', 'product_total', 'product_weight', 'product_volume', 'product_dim_weight', 'product_length', 'product_width', 'product_height');

		$this->rateFormat1	= '/^([0-9.]+|~):([0-9.%-]+)$/';
		$this->rateFormat2	= '/^([0-9.]+|~):([0-9.%-]+)\+([0-9.%-]+)$/';
		$this->rateFormat3	= '/^([0-9.]+|~):([0-9.%-]+)\/([0-9.]+)$/';
		$this->rateFormat4	= '/^([0-9.]+|~):([0-9.%-]+)\/([0-9.]+)\+([0-9.%-]+)$/';

		$this->combinations = $this->field('combinations');
	}

	public function getQuote($address) {
		$this->construct($address);

		if ($this->field('test') && strtolower($this->dataCustomer['name']) != 'intuitive shipping') {
			$this->writeDebug('The system is currently in testing mode. To test the shipping calculation, set the Customer Name of the order to Intuitive Shipping');
			$this->writeDebug($this->dataCustomer);
			return array();
		}

		if ($this->status && $this->field('status') && $this->rates() && $this->cartProducts && $address) {
			$language_code = !empty($this->session->data['language']) ? $this->session->data['language'] : $this->config->get('config_language');

			$destination = $this->getDestination($address);

			$rates = $this->rates();

			$quote_data		= array();
			$method_data	= array();
			$combined_data	= array();

			if ($rates) {
				foreach ($rates as $rate_info) {
					$this->load->language('extension/' . $this->type . '/' . $this->extension);

					$rate 									= array();
					$debug  								= array();
					$debug['RateID'] 						= $rate_info['rate_id'];
					$debug['Description']					= strtoupper($rate_info['description']);

					if ($this->ocapps_status && $this->field('ocapps_status')) {
						$debug['PerProductShippingIntegration']	= true;
					}

					foreach ($rate_info as $key => $value) {
						$rate[$key] = $this->value($value);
					}

					if ($rate['status']) {
						$debug['Status'] = true;

						$status = true;

						$instruction 	= '';
						$rate_name 		= $this->language->get('text_name');
						$shipping_name	= $this->language->get('text_name');
						$heading		= $this->language->get('text_title');

						$adjusted_values = array();

						$debug['cartGeoZones'] = $this->cartGeoZones;

						$origin = array(
							'origin'	=> $rate['origin'],
							'lat' 		=> $rate['geocode_lat'],
							'lng' 		=> $rate['geocode_lng'],
						);

						//Build Values
						$products 	= $this->cartProducts;
						$cart 		= array();
						$customer	= $this->dataCustomer;
						$other		= $this->dataOther;

						//Remove Products With Per Product Shipping Assigned
						$ocapps_products = array();
						if ($this->ocapps_status && $this->field('ocapps_status') && $products && !$rate['ocapps_requirement']) {
							foreach ($products as $key => $product) {
								foreach ($this->cartGeoZones as $geo_zone) {
									if (!empty($rate['shipping'][$geo_zone['geo_zone_id']]['cost']) || !empty($rate['shipping'][$geo_zone['geo_zone_id']]['shipping_method'])) {
										if ($this->checkPerProductShipping($product['product_id'], $geo_zone['geo_zone_id'])) {
											$debug['removeProduct'][$key]['Product']		= $product['name'];
											$debug['removeProduct'][$key]['Notes'][]		= 'Per Product Shipping Costs Found';
											$debug['removeProduct'][$key]['Notes'][]		= 'Removed From Requirements Check';
											if ($rate['ocapps_cost'] == 2) {
												$ocapps_products[$key] = $products[$key];
												$debug['removeProduct'][$key]['Notes'][]	= 'Added To ocapps_products';
											}
											unset($products[$key]);
											break;
										}
									}
								}
							}
						}

						//Build Requirements
						$temp_requirements = array();
						if (!empty($rate['requirements'])) {
							foreach ($rate['requirements'] as $key => $requirement) {
								if (!in_array($requirement['operation'], array('add', 'sub'))) {
									if (strpos($requirement['type'], 'cart_') === 0 || strpos($requirement['type'], 'product_') === 0 || strpos($requirement['type'], 'customer_') === 0) {
										$group = explode('_', $requirement['type']);
										$group = $group[0];
									} else {
										$group = 'other';
									}
									$temp_requirements[$group][$requirement['type']][$key] = array(
										'operation'		=> $requirement['operation'],
										'value'			=> $requirement['value'],
										'parameter'		=> $requirement['parameter'],
									);
								} else {
									if (isset($adjusted_values[$requirement['type']])) {
										$adjusted_values[$requirement['type']] += ($requirement['operation'] == 'add') ? $requirement['value'] : '-' . $requirement['value'];
									} else {
										$adjusted_values[$requirement['type']] = ($requirement['operation'] == 'add') ? $requirement['value'] : '-' . $requirement['value'];
									}
								}
							}
						}
						
						$this->log->write('requirements built');

						$requirements = array();
						$requirements['product'] 	= (!empty($temp_requirements['product'])) ? $temp_requirements['product'] : array();
						$requirements['cart'] 		= (!empty($temp_requirements['cart'])) ? $temp_requirements['cart'] : array();
						$requirements['customer'] 	= (!empty($temp_requirements['customer'])) ? $temp_requirements['customer'] : array();
						$requirements['other'] 		= (!empty($temp_requirements['other'])) ? $temp_requirements['other'] : array();

						if ($requirements['product'] || $requirements['customer'] || $requirements['other']) {
							
							$this->log->write('requirements start');
							
							$requirement_status = array();

							if (!empty($requirements['product']) && $products) {
								$product_requirement_status = array();
								$product_status				= array();
								foreach ($requirements['product'] as $type => $values) {
									foreach ($values as $requirement_key => $value) {
										foreach ($products as $product_key => $product) {
											$temp_status = $this->checkRequirementProduct($product, 'product', $type, $value['operation'], $value['value'], $value['parameter'], $debug);

											$product_requirement_status[$requirement_key][] = $temp_status;
											$product_status[$product_key][] = $temp_status;
										}
									}
								}

								$debug['Requirements']['RequirementCost']['Method'] = $rate['requirement_cost'];
								foreach ($product_status as $product_key => $value) {
									if ($rate['requirement_cost'] == 'all' && in_array(false, $value)) { $debug['Requirements']['RequirementCost']['removeProduct'][$product_key]['Product'] = $products[$product_key]['name']; unset($products[$product_key]); }
									if ($rate['requirement_cost'] == 'any' && !in_array(true, $value)) {$debug['Requirements']['RequirementCost']['removeProduct'][$product_key]['Product'] = $products[$product_key]['name']; unset($products[$product_key]); }
									if ($rate['requirement_cost'] == 'none' && in_array(true, $value)) { $debug['Requirements']['RequirementCost']['removeProduct'][$product_key]['Product'] = $products[$product_key]['name']; unset($products[$product_key]); }
								}

								foreach ($requirements['product'] as $type => $values) {
									foreach ($values as $requirement_key => $value) {
										$temp_status = false;
										if ($value['parameter']['match'] == 'all' && !in_array(false, $product_requirement_status[$requirement_key])) { $temp_status = true; }
										if ($value['parameter']['match'] == 'any' && in_array(true, $product_requirement_status[$requirement_key])) { $temp_status = true; }
										if ($value['parameter']['match'] == 'none' && !in_array(true, $product_requirement_status[$requirement_key])) { $temp_status = true; }

										$debug['Requirements']['RequirementCheck'][$requirement_key] = array(
											'Type'		=> $type,
											'Match'		=> $value['parameter']['match'],
											'Status'	=> $temp_status,
										);

										$requirement_status[] = $temp_status;
									}
								}
							}

							if (!empty($requirements['customer'])) {
								foreach ($requirements['customer'] as $type => $values) {
									foreach ($values as $value) {
										$requirement_status[] = $this->checkRequirementCustomer($customer, 'customer', $type, $value['operation'], $value['value'], $value['parameter'], $debug);
									}
								}
							}

							if (!empty($requirements['other'])) {
								foreach ($requirements['other'] as $type => $values) {
									foreach ($values as $value) {
										$requirement_status[] = $this->checkRequirementOther($other, 'other', $type, $value['operation'], $value['value'], $value['parameter'], $debug);
									}
								}
							}

							if ($rate['requirement_match'] == 'all' && in_array(false, $requirement_status)) { $status = false; }
							if ($rate['requirement_match'] == 'any' && !in_array(true, $requirement_status) && empty($requirements['cart'])) { $status = false; }
							if ($rate['requirement_match'] == 'none' && in_array(true, $requirement_status)) { $status = false; }

							$debug['Requirements']['RequirementMatch'] = array(
								'Method'	=> $rate['requirement_match'],
								'Status'	=> $status,
							);

							if (!$status && $rate['fail_method'] && $products) { $this->status = false; }
						}

						if (!$status) { continue; }

						if ($products) {
							foreach ($this->cartGeoZones as $geo_zone) {
								if (!empty($rate['shipping'][$geo_zone['geo_zone_id']]) && (!empty($rate['shipping'][$geo_zone['geo_zone_id']]['rates']) || !empty($rate['shipping'][$geo_zone['geo_zone_id']]['shipping_method']))) {
									$cart_dist_calc = false;

									if (in_array($rate['shipping'][$geo_zone['geo_zone_id']]['rate_type'], $this->rateTypes) && empty($rate['shipping'][$geo_zone['geo_zone_id']]['rates'])) { $status = false; }
									if (strpos($rate['shipping'][$geo_zone['geo_zone_id']]['rate_type'], 'dim_weight') !== false && empty($rate['shipping'][$geo_zone['geo_zone_id']]['shipping_factor'])) { $status = false; }
									if (strpos($rate['shipping'][$geo_zone['geo_zone_id']]['rate_type'], 'distance') !== false && empty($rate['origin'])) { $status = false; }
									if (strpos($rate['shipping'][$geo_zone['geo_zone_id']]['rate_type'], 'distance') !== false) { $cart_dist_calc = true; }

									if (!$status) { continue; }

									//Check Cart Requirements
									$requirement_status = array();
									if (!empty($requirements['cart'])) {
										if (!$cart_dist_calc && !empty($origin['origin'])) {
											if (array_key_exists('cart_distance', $requirements['cart'])) {
												$cart_dist_calc = true;
											}
										}
										$cart = $this->calculateCart($products, $adjusted_values, $rate['shipping'][$geo_zone['geo_zone_id']]['shipping_factor'], $origin, $destination, $rate['shipping'][$geo_zone['geo_zone_id']]['currency'], $rate['total_type'], $cart_dist_calc, $debug);
										foreach ($requirements['cart'] as $type => $values) {
											foreach ($values as $value) {
												$requirement_status[] = $this->checkRequirementCart($cart, 'cart', $type, $value['operation'], $value['value'], $value['parameter'], $debug);
											}
										}

										if ($rate['requirement_match'] == 'all' && in_array(false, $requirement_status)) { $status = false; }
										if ($rate['requirement_match'] == 'any' && !in_array(true, $requirement_status)) { $status = false; }
										if ($rate['requirement_match'] == 'none' && in_array(true, $requirement_status)) { $status = false; }

										if (!$status && $rate['fail_method'] && $products) { $this->status = false; }
									} else {
										$cart = $this->calculateCart($products, $adjusted_values, $rate['shipping'][$geo_zone['geo_zone_id']]['shipping_factor'], $origin, $destination, $rate['shipping'][$geo_zone['geo_zone_id']]['currency'], $rate['total_type'], $cart_dist_calc, $debug);
									}

									if (!$status) { continue; }

									//Remove Products With Per Product Shipping Assigned For Calculation
									if ($this->ocapps_status && $this->field('ocapps_status') && $products) {
										if ($rate['ocapps_cost'] != 2) {
											foreach ($products as $key => $product) {
												if ($this->checkPerProductShipping($product['product_id'], $geo_zone['geo_zone_id'])) {
													$debug['Shipping'][$geo_zone['name']]['removeProduct'][$key]['Product'] = $products[$key]['name'];
													$debug['Shipping'][$geo_zone['name']]['removeProduct'][$key]['Notes'][] = 'Removed From Calculation';
													unset($products[$key]);
													break;
												}
											}
										} elseif ($ocapps_products) {
											foreach ($ocapps_products as $key => $product) {
												if (!isset($products[$key])) {
													$products[$key] = $product;
													$debug['Shipping'][$geo_zone['name']]['removeProduct'][$key]['Product'] = $product['name'];
													$debug['Shipping'][$geo_zone['name']]['removeProduct'][$key]['Notes'][] = 'Added Back To Calculation';
												}
											}
										}
										$cart = $this->calculateCart($products, $adjusted_values, $rate['shipping'][$geo_zone['geo_zone_id']]['shipping_factor'], $origin, $destination, $rate['shipping'][$geo_zone['geo_zone_id']]['currency'], $rate['total_type'], $cart_dist_calc, $debug['Shipping'][$geo_zone['name']]);
									} elseif (empty($cart)) {
										$cart = $this->calculateCart($products, $adjusted_values, $rate['shipping'][$geo_zone['geo_zone_id']]['shipping_factor'], $origin, $destination, $rate['shipping'][$geo_zone['geo_zone_id']]['currency'], $rate['total_type'], $cart_dist_calc, $debug['Shipping'][$geo_zone['name']]);
									}

									$cost = '';

									if ($products) {
										if (in_array($rate['shipping'][$geo_zone['geo_zone_id']]['rate_type'], $this->rateTypes)) {
											$debug['Shipping'][$geo_zone['name']]['RateType'] = strtoupper(str_replace('_', ' ', $rate['shipping'][$geo_zone['geo_zone_id']]['rate_type']));
											if (strpos($rate['shipping'][$geo_zone['geo_zone_id']]['rate_type'], 'product_') === 0) {
												$value = str_replace('product_', '', $rate['shipping'][$geo_zone['geo_zone_id']]['rate_type']);
												foreach ($products as $product) {
													$value  = $product[str_replace('product_', '', $rate['shipping'][$geo_zone['geo_zone_id']]['rate_type'])];
													$cost_data = ($rate['shipping'][$geo_zone['geo_zone_id']]['final_cost'] == 1) ? $this->getRateCumulative($value, $rate['shipping'][$geo_zone['geo_zone_id']]['rates'], $product['total'], $debug['Shipping'][$geo_zone['name']]) : $this->getRateSingle($value, $rate['shipping'][$geo_zone['geo_zone_id']]['rates'], $product['total'], $debug['Shipping'][$geo_zone['name']]);
													if ((string)$cost_data != '') {
														$cost = (float)$cost;
														if ($rate['shipping'][$geo_zone['geo_zone_id']]['rate_type'] !== 'product_quantity') {
															$cost += $cost_data * $product['quantity'];
														} else {
															$cost += $cost_data;
														}
													}
												}
											} else {
												$value = $cart[str_replace('cart_', '', $rate['shipping'][$geo_zone['geo_zone_id']]['rate_type'])];
												if ($rate['shipping'][$geo_zone['geo_zone_id']]['split']) {
													$debug['Shipping'][$geo_zone['name']]['Split'] = true;
													$max_rate	= $this->getRateMax($rate['shipping'][$geo_zone['geo_zone_id']]['rates']);
													$max_rate	= (strpos($max_rate, '~') === 0) ? 1 : $max_rate;
													$divide 	= ceil($value / $max_rate);
												} else {
													$debug['Shipping'][$geo_zone['name']]['Split'] = false;
													$divide		= 1;
												}
												$x = 1;
												while ($divide >= $x) {
													$split_value	= ($rate['shipping'][$geo_zone['geo_zone_id']]['split']) ? ($divide == $x) ? $value - ($max_rate * ($x - 1)) : $max_rate: $value;
													$cost_data 		= ($rate['shipping'][$geo_zone['geo_zone_id']]['final_cost'] == 1) ? $this->getRateCumulative($split_value, $rate['shipping'][$geo_zone['geo_zone_id']]['rates'], $cart['total'], $debug['Shipping'][$geo_zone['name']]) : $this->getRateSingle($split_value, $rate['shipping'][$geo_zone['geo_zone_id']]['rates'], $cart['total'], $debug['Shipping'][$geo_zone['name']]);
													if ((string)$cost_data != '') {
														$cost  = (float)$cost;
														$cost += $cost_data;
													}
													$x++;
												}
											}
										} elseif (!empty($rate['shipping'][$geo_zone['geo_zone_id']]['shipping_method'])) {
											$debug['Shipping'][$geo_zone['name']]['RateType'] = strtoupper(str_replace('_', ' ', $rate['shipping'][$geo_zone['geo_zone_id']]['rate_type']));

											//Temporarily Adjust Products In Cart
											$this->cart->clear();
											foreach ($products as $product) {
												$option_data = array();
												foreach ($product['option'] as $option) {
													if (in_array($option['type'], array('select', 'radio', 'image'))) {
														$option_data[$option['product_option_id']] = $option['product_option_value_id'];
													} elseif ($option['type'] == 'checkbox') {
														$option_data[$option['product_option_id']][] = $option['product_option_value_id'];
														$option_data[$option['option_id']][] = $option['value'];
													} elseif (in_array($option['type'], array('text', 'textarea', 'file', 'date', 'datetime', 'time'))) {
														$option_data[$option['product_option_id']] = $option['value'];
													}
												}
												$quantity		= $product['quantity'] + (!empty($adjusted_values['product_quantity']) ? $adjusted_values['product_quantity'] : 0);
												$recurring_id 	= ($product['recurring']) ? $product['recurring']['recurring_id'] : 0;
												$this->cart->add($product['product_id'], $quantity, $option_data, $recurring_id);
											}

											$cost_data = $this->getShipping($rate['shipping'][$geo_zone['geo_zone_id']]['rate_type'], $rate['shipping'][$geo_zone['geo_zone_id']]['shipping_method'], $address, $debug);
											if ((string)$cost_data != '') {
												$cost  = (float)$cost;
												$cost += $cost_data;
											}

											//Restore Cart
											$this->cart->clear();
											$this->restoreCart();
										}
									}

									//Get Per Product Shipping Costs
									if ($this->ocapps_status && $this->field('ocapps_status') && $rate['ocapps_cost'] != 2) {
										$pps_cost = $this->getPerProductShipping($address, $geo_zone['geo_zone_id']);
										if ((string)$pps_cost != '') {
											$cost = (float)$cost;
											if ($rate['ocapps_cost'] == 1) {
												$cost	 = $pps_cost;
												$debug['Shipping'][$geo_zone['name']]['OverridePerProductShipping'] = $pps_cost;
											} else {
												$cost	+= $pps_cost;
												$debug['Shipping'][$geo_zone['name']]['AddPerProductShipping'] = $pps_cost;
											}

										}
									}

									if ((string)$cost != '') {
										if ($rate['shipping'][$geo_zone['geo_zone_id']]['cost']['min']) {
											if ($cost < $rate['shipping'][$geo_zone['geo_zone_id']]['cost']['min']) {
												$cost 	= $rate['shipping'][$geo_zone['geo_zone_id']]['cost']['min'];
												$debug['Shipping'][$geo_zone['name']]['CostMin'] = $rate['shipping'][$geo_zone['geo_zone_id']]['cost']['min'];
											}
										}

										if ($rate['shipping'][$geo_zone['geo_zone_id']]['cost']['max']) {
											if ($cost > $rate['shipping'][$geo_zone['geo_zone_id']]['cost']['max']) {
												$cost 	= $rate['shipping'][$geo_zone['geo_zone_id']]['cost']['max'];
												$debug['Shipping'][$geo_zone['name']]['CostMax'] = $rate['shipping'][$geo_zone['geo_zone_id']]['cost']['max'];
											}
										}

										if ($rate['shipping'][$geo_zone['geo_zone_id']]['cost']['add']) {
											if (strpos($rate['shipping'][$geo_zone['geo_zone_id']]['cost']['add'], '%')) {
												$cost += $cost * ($rate['shipping'][$geo_zone['geo_zone_id']]['cost']['add'] / 100);
											} else {
												$cost  += $rate['shipping'][$geo_zone['geo_zone_id']]['cost']['add'];
											}
											$debug['Shipping'][$geo_zone['name']]['CostAdd'] = $rate['shipping'][$geo_zone['geo_zone_id']]['cost']['add'];
										}

										if ($rate['shipping'][$geo_zone['geo_zone_id']]['freight_fee']) {
											$pos = strpos($rate['shipping'][$geo_zone['geo_zone_id']]['freight_fee'], '%');
											if ($pos) {
												$cost += $cost * ($rate['shipping'][$geo_zone['geo_zone_id']]['freight_fee'] / 100);
											} else {
												$cost += $rate['shipping'][$geo_zone['geo_zone_id']]['freight_fee'];
											}
											$debug['Shipping'][$geo_zone['name']]['FreightFee'] = $rate['shipping'][$geo_zone['geo_zone_id']]['freight_fee'];
										}

										//Convert Currency
										if (in_array($rate['shipping'][$geo_zone['geo_zone_id']]['rate_type'], $this->rateTypes)) {
											$debug['Shipping'][$geo_zone['name']]['Total']['BeforeCurrencyConversion'] = $cost;
											$cost = $this->currency->convert($cost, $rate['shipping'][$geo_zone['geo_zone_id']]['currency'], $this->config->get('config_currency'));
											$debug['Shipping'][$geo_zone['name']]['Total']['AfterCurrencyConversion'] = $cost;
										}

										$rate_name  	= !empty($rate['name'][$language_code]) ? $rate['name'][$language_code] : $rate_name;
										$shipping_name 	= !empty($rate['name'][$language_code]) ? $rate['name'][$language_code] : $shipping_name;

										if ($this->field('display_value') && in_array($rate['shipping'][$geo_zone['geo_zone_id']]['rate_type'], $this->rateTypes) && strpos($rate['shipping'][$geo_zone['geo_zone_id']]['rate_type'], 'cart_') === 0) {
											if (strpos($rate['shipping'][$geo_zone['geo_zone_id']]['rate_type'], 'quantity') !== false) {
												$name_value = $value;
											} elseif (strpos($rate['shipping'][$geo_zone['geo_zone_id']]['rate_type'], 'total') !== false) {
												$name_value = $this->currency->format($value, $rate['currency'], 1);
											} elseif (strpos($rate['shipping'][$geo_zone['geo_zone_id']]['rate_type'], 'weight') !== false) {
												$name_value = $this->weight->format($value, $this->config->get('config_' . $this->weight()));
											} elseif (strpos($rate['shipping'][$geo_zone['geo_zone_id']]['rate_type'], 'volume') !== false) {
												$name_value = $this->length->format($value, $this->config->get('config_' . $this->length())) . '&sup3;';
											} elseif (strpos($rate['shipping'][$geo_zone['geo_zone_id']]['rate_type'], 'distance') !== false) {
												$name_value = round($value, 2) . 'km';
											}
											$shipping_name .= ' (' . $name_value . ')';
										}

										$debug['Shipping'][$geo_zone['name']]['Name'] = $rate_name;
										
										$group_status = false;
										if ($rate['group'] && $this->combinations) {
											$groups = explode(',', $rate['group']);
											
											foreach ($groups as $group) {
												$group = trim(strtoupper($group));
												
												foreach ($this->combinations as $key => $value) {
													if (strpos(strtoupper($value['formula']), '{' . $group . '}') !== false) {
														$group_status = true;
														break;
													}
												}
												if ($group_status) { break; }
											}
										}
										
										$debug['Shipping'][$geo_zone['name']]['RateGroupStatus'] = $group_status;

										if ($group_status) {											
											foreach ($groups as $key) {
												$key = trim(strtoupper($key));
												
												$combined_data[$key][] = array(
													'title'			=> $shipping_name,
													'sort_order'	=> $rate['sort_order'],
													'tax_class_id'	=> $rate['tax_class_id'],
													'cost'			=> $cost
												);
											}
											$debug['Shipping'][$geo_zone['name']]['RateGroup'] = $rate['group'];
										} else {
											$rate_data = array(
												'title'			=> $shipping_name,
												'sort_order'	=> $rate['sort_order'],
												'tax_class_id'	=> $rate['tax_class_id'],
												'cost'			=> $cost,
												'code'			=> $rate['rate_id'] . '_' . $geo_zone['geo_zone_id'],
											);
											$quote_data[$this->extension . '_' . $rate['rate_id'] . '_' . $geo_zone['geo_zone_id']] = $this->getQuoteData($rate_data);
											$debug['Shipping'][$geo_zone['name']]['RateGroup'] = 'None';
										}
									} else {
										$debug['Shipping'][$geo_zone['name']]['Calculation'] = 'Cost Not Found';
									}
								}
							}
						}
					} else {
						$debug['Status'] = false;
					}
					$this->writeDebug($debug);
				}
			}

			$combine_regex['sum'] = '/SUM\(([A-Z0-9\,\.\{\}]+)\)/';
			$combine_regex['avg'] = '/AVG\(([A-Z0-9\,\.\{\}]+)\)/';
			$combine_regex['min'] = '/MIN\(([A-Z0-9\,\.\{\}]+)\)/';
			$combine_regex['max'] = '/MAX\(([A-Z0-9\,\.\{\}]+)\)/';

			$this->load->language('extension/' . $this->type . '/' . $this->extension);

			$combination_row = 1;
			if ($this->status && $this->combinations && !empty($combined_data)) {
				$debug = array();

				foreach ($this->combinations as $key => $value) {
					$title 			= ($value['title_display'] == 4) ? (!empty($value['title'][$language_code]) ? $value['title'][$language_code] : $this->language->get('text_title')) : '';
					$tax_class_id	= '';

					$formula = strtoupper(preg_replace('/\s+/', '', $value['formula']));
					$combine_status = true;
					if ($formula && substr_count($formula, '(') == substr_count($formula, ')') && substr_count($formula, '{') == substr_count($formula, '}')) {
						while (preg_match('/([SUM|AVG|MIN|MAX])\(([A-Z0-9\,\.\{\}]+)\)/', $formula) && $combine_status) {
							foreach ($combine_regex as $regex_key => $regex_value) {
								preg_match($regex_value, $formula, $matches);
								$x = 1;
								while (isset($matches[$x])) {
									if (!preg_match('/^([0-9\,\.\+\-\*\/\(\)]+)$/', $matches[$x])) {
										$rate_groups = explode(',', $matches[$x]);
										foreach ($rate_groups as $rate_group) {
											if (preg_match('/^\{([A-Z0-9]+)\}$/', $rate_group)) {
												$rate_group_key = str_replace(array('{','}'), array('',''), $rate_group);
												if (isset($combined_data[$rate_group_key])) {
													$debug['CombineRates']['CombinedData'][$rate_group_key][] = $combined_data[$rate_group_key];
													$rate_group_value = '';
													foreach ($combined_data[$rate_group_key] as $data) {
														if (($value['title_display'] == 0 && !$title) || $value['title_display'] == 1) {
															$title 			= $data['title'];
														} elseif ($value['title_display'] == 2 || $value['title_display'] == 3) {
															$title			.= ($title) ? ' + ' . $data['title'] : $data['title'];
															$title			.= ($value['title_display'] == 3) ? '(' . $this->currency->format($this->tax->calculate($data['cost'], $data['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']) . ')' : '';
														}
														$tax_class_id = $data['tax_class_id'];
														if ($regex_key == 'sum') { $rate_group_value += $data['cost']; }
														if ($regex_key == 'avg') { $rate_group_value .= ($rate_group_value) ? ',' . $data['cost'] : $data['cost']; }
														if ($regex_key == 'min' && ($rate_group_value > $data['cost'] || (string)$rate_group_value == '')) { $rate_group_value = $data['cost']; }
														if ($regex_key == 'max' && ($rate_group_value < $data['cost'] || (string)$rate_group_value == '')) { $rate_group_value = $data['cost']; }
													}
													$matches[$x] = str_replace(array($rate_group), array($rate_group => $rate_group_value), $matches[$x]);
												} else {
													if ($value['method']) {
														$rate_group_value 	= 0;
														$matches[$x] 		= str_replace(array($rate_group), array($rate_group => $rate_group_value), $matches[$x]);
													} else {
														$combine_status = false;
														break;
													}
												}
											}
										}
									}
									if (!$combine_status) { break; }
									if (preg_match('/^([0-9\,\.\+\-\*\/\(\)]+)$/', $matches[$x])) {
										if ($regex_key == 'sum') { $rate_group_cost = array_sum(explode(',', $matches[$x])); }
										if ($regex_key == 'avg') { $rate_group_cost = array_sum(explode(',', $matches[$x])) / count(explode(',', $matches[$x])); }
										if ($regex_key == 'min') { $rate_group_cost = min(explode(',', $matches[$x])); }
										if ($regex_key == 'max') { $rate_group_cost = max(explode(',', $matches[$x])); }
										$formula = str_replace(array($matches[0]), array($matches[0] => $rate_group_cost), $formula);
									}
									$x++;
								}
								if (!$combine_status) { break; }
							}
						}
					}
					
					if (preg_match('/^([0-9\,\.\+\-\*\/\(\)]+)$/', $formula) && substr_count($formula, '(') == substr_count($formula, ')')) {
						$cost = @eval('return((float)' . $formula . ');');

						if ((string)$cost != '') {
							$rate_data = array(
								'title'			=> $title,
								'sort_order'	=> $value['sort_order'],
								'tax_class_id'	=> $tax_class_id,
								'cost'			=> $cost,
								'code'			=> 'C' . $combination_row,
							);

							$debug['CombineRates']['Rates'][] = $rate_data;

							$quote_data[$this->extension . '_C' . $combination_row] = $this->getQuoteData($rate_data);
						}
					}
					$this->writeDebug($debug);
					$combination_row++;
				}
			}

			if ($this->status && $quote_data) {
				$sort_order = array();
				foreach ($quote_data as $key => $value) {
					$sort_order[$key] = $value['sort_order'];
					$sort_cost[$key] = $value['value'];
				}

				if ($this->field('sort_quotes') == 0) {
					array_multisort($sort_order, SORT_ASC, $quote_data);
				} elseif ($this->field('sort_quotes') == 1) {
					array_multisort($sort_order, SORT_DESC, $quote_data);
				} elseif ($this->field('sort_quotes') == 2) {
					array_multisort($sort_cost, SORT_ASC, $quote_data);
				} elseif ($this->field('sort_quotes') == 3) {
					array_multisort($sort_cost, SORT_DESC, $quote_data);
				} else {
					array_multisort($sort_order, SORT_ASC, $quote_data);
				}

				$title_data = $this->field('title');

				$method_data = array(
					'id'       		=> $this->extension,
					'code'       	=> $this->extension,
					'title'      	=> !empty($title_data[$language_code]) ? $title_data[$language_code] : $heading,
					'quote'      	=> $quote_data,
					'sort_order' 	=> $this->field('sort_order'),
					'error'      	=> false
				);
			}
			return $method_data;
		} else {
			$debug  = $this->language->get('text_title');
			$debug .= ' | FAILED TO INITIALIZE';
			if ($this->field('status')) {
				$debug .= ' | ExtensionStatus: ENABLED';
			} else {
				$debug .= ' | ExtensionStatus: DISABLED';
			}
			if (!$this->rates()) {
				$debug .= ' | Rates: EMPTY';
			} else {
				$debug .= ' | Rates: ' . count($this->rates()) . ' FOUND';
			}
			if (!$this->cartProducts) {
				$debug .= ' | ProductsInCart: EMPTY';
			} else {
				$debug .= ' | ProductsInCart: ' . count($this->cart->hasProducts()) . ' FOUND';
			}
			if ($address) {
				$debug .= ' | CustomerAddress: NOT FOUND';
			} else {
				$debug .= ' | CustomerAddress: FOUND';
			}
			$this->writeDebug($debug);
		}
	}

	private function version() {
		return (defined('VERSION')) ? (float)VERSION : '';
	}

	private function rates() {
		$rates = array();
		if ($this->field('cache') && $this->cache->get($this->extension . '_rates')) {
			$rates = $this->cache->get($this->extension . '_rates');
		}
		if (!$rates) {
			$query = $this->db->query("SELECT * FROM " . DB_PREFIX . $this->db_table . " WHERE status = '1' ORDER BY sort_order, rate_id ASC");
			$rates = $query->rows;
			if ($this->field('cache')) { $this->cache->set($this->extension . '_rates', $rates); }
		}
		return $rates;
	}

	private function field($field) {
		$value = $this->config->get((($this->version() >= 3.0) ? $this->type . '_' : '') . $this->extension . '_' . $field);
		return $value = (!is_array($value) && is_array(json_decode($value, true))) ? json_decode($value, true) : $value;
	}

	private function value($value) {
		return $value = (!is_array($value) && is_array(json_decode($value, true))) ? json_decode($value, true) : $value;
	}

	private function saveCart() {
		$products = array();

		$cart_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "cart WHERE customer_id = '" . (int)$this->customer->getId() . "' AND session_id = '" . $this->db->escape($this->session->getId()) . "'");
		foreach ($cart_query->rows as $cart) {
			$products[] = array(
				'cart_id'		=> $cart['cart_id'],
				'product_id'	=> $cart['product_id'],
				'quantity'		=> $cart['quantity'],
				'option'		=> json_decode($cart['option'], true),
				'recurring_id'	=> $cart['recurring_id'],
			);
		}
		return $products;
	}
	
	private function restoreCart() {
		foreach ($this->savedCart as $product) {
			$this->db->query("INSERT " . DB_PREFIX . "cart SET cart_id = '" . (int)$product['cart_id'] . "', api_id = '" . (isset($this->session->data['api_id']) ? (int)$this->session->data['api_id'] : 0) . "', customer_id = '" . (int)$this->customer->getId() . "', session_id = '" . $this->db->escape($this->session->getId()) . "', product_id = '" . (int)$product['product_id'] . "', recurring_id = '" . (int)$product['recurring_id'] . "', `option` = '" . $this->db->escape(json_encode($product['option'])) . "', quantity = '" . (int)$product['quantity'] . "', date_added = NOW()");
		}
	}

	private function getGeoZones($address) {
		$geo_zones = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "geo_zone ORDER BY name");
		foreach ($query->rows as $result) {
			$query_z2g = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$result['geo_zone_id'] . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");
			if ($query_z2g->num_rows) {
				$geo_zones[] = array(
					'geo_zone_id'	=> $result['geo_zone_id'],
					'name'			=> $result['name'],
				);
			}
		}

		if (!$geo_zones) {
			$geo_zones[] = array(
				'geo_zone_id'	=> 0,
				'name'			=> 'All Other Zones',
			);
		}

		return $geo_zones;
	}

	private function getProducts() {
		$products = array();

		$this->load->model('catalog/product');
		foreach ($this->cart->getProducts() as $product) {
			if ($product['shipping']) {
				$product_info = $this->model_catalog_product->getProduct($product['product_id']);
				$products[uniqid(rand())] = array(
					'key'			=> !empty($product['key']) ? $product['key'] : '',
					'product_id'	=> $product['product_id'],
					'quantity'		=> $product['quantity'],
					'price'			=> $product['price'],
					'total'			=> $product['total'] / $product['quantity'],
					'tax_class_id'	=> $product['tax_class_id'],
					'length'		=> $this->length->convert($product['length'], $product[$this->length()], $this->config->get('config_' . $this->length())),
					'width' 		=> $this->length->convert($product['width'], $product[$this->length()], $this->config->get('config_' . $this->length())),
					'height'		=> $this->length->convert($product['height'], $product[$this->length()], $this->config->get('config_' . $this->length())),
					'volume'		=> $this->length->convert($product['length'], $product[$this->length()], $this->config->get('config_' . $this->length())) * $this->length->convert($product['width'], $product[$this->length()], $this->config->get('config_' . $this->length())) * $this->length->convert($product['height'], $product[$this->length()], $this->config->get('config_' . $this->length())),
					'weight'		=> $this->weight->convert($product['weight'], $product[$this->weight()], $this->config->get('config_' . $this->weight())) / $product['quantity'],
					'category'		=> $this->model_catalog_product->getCategories($product['product_id']),
					'attribute'		=> $this->model_catalog_product->getProductAttributes($product['product_id']),
					'name'			=> $product['name'],
					'model'			=> $product['model'],
					'sku'			=> $product_info['sku'],
					'upc'			=> !empty($product_info['upc']) ? $product_info['upc'] : '',
					'ean'			=> !empty($product_info['ean']) ? $product_info['ean'] : '',
					'jan'			=> !empty($product_info['jan']) ? $product_info['jan'] : '',
					'isbn'			=> !empty($product_info['isbn']) ? $product_info['isbn'] : '',
					'mpn'			=> !empty($product_info['mpn']) ? $product_info['mpn'] : '',
					'location'		=> $product_info['location'],
					'stock'			=> $product_info['quantity'],
					'manufacturer'	=> $product_info['manufacturer_id'],
					'option'		=> $product['option'],
					'recurring'		=> !empty($product['recurring']) ? $product['recurring'] : '',
				);
			}
		}
		return $products;
	}

	private function getDestination($address) {
		$destination = $address;

		if (!empty($address)) {
			$country_query 	= $this->db->query("SELECT * FROM " . DB_PREFIX . "country WHERE country_id = '" . (int)$address['country_id'] . "'");
			$zone_query		= $this->db->query("SELECT * FROM " . DB_PREFIX . "zone WHERE zone_id = '" . (int)$address['zone_id'] . "'");
			if ($country_query) {
				$destination['zone']	= !empty($zone_query->row['name']) ? $zone_query->row['name'] : array();
				$destination['country'] = !empty($country_query->row['name']) ? $country_query->row['name'] : array();
			}
		}
		return $destination;
	}

	private function getDistance($origin, $destination, &$debug) {
		$distance = 0;

		if ($origin && $destination) {
			$directions = $this->getDirections($origin['origin'], $destination, $debug);
			if ($directions) {
				$debug['getDistance'] = 'getDirections';
				return (float)$directions['value'];
			} else {
				$debug['getDistance'] = 'getGeoCode';
				$geocode = $this->getGeoCode($destination, $debug);
				if ($geocode) {
					$r 		= 6371;
					$lat1	= deg2rad($origin['lat']);
					$lat2	= deg2rad($geocode['lat']);
					$lng1	= deg2rad($origin['lng']);
					$lng2	= deg2rad($geocode['lng']);
					$dlat	= $lat2 - $lat1;
					$dlng	= $lng2 - $lng1;
					$a		= sin($dlat/2) * sin($dlat/2) + cos($lat1) * cos($lat2) * sin($dlng/2) * sin($dlng/2);
					$c		= 2 * atan2(sqrt($a), sqrt(1-$a));
					$distance = $r * $c;
					return (float)$distance;
				}
			}
		}
		return (float)$distance;
	}

	private function getDirections($origin, $destination, &$debug) {
		$cache_key = $this->extension . '_directions_' . preg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $origin) . '_' .  preg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $destination['postcode'] . ':' . $destination['zone'] . ':' . $destination['country']);

		if ($this->cache->get($cache_key) && $this->field('cache')) {
			$directions = $this->cache->get($cache_key);
			$debug['getDirections']['Method'] 		= 'Cache';
			$debug['getDirections']['Destination'] 	= $destination;
			$debug['getDirections']['Response'] 	= $directions;
			return array(
				'value'	=> $directions['value'],
				'text'	=> $directions['text'],
			);
		} else {
			$dest_1 = $destination['address_1'] . ', ' . $destination['city'] . ', ' . $destination['postcode'] . ', ' . $destination['zone'] . ', ' . $destination['country'];
			$dest_2 = $destination['postcode'] . ', ' . $destination['zone'] . ', ' . $destination['country'];

			if ($destination['address_1']) {
				$url = 'https://maps.googleapis.com/maps/api/directions/xml?origin=' . urlencode($origin) . '&destination=' . urlencode($dest_1) . '&sensor=false';
				$response = simplexml_load_file($url);
				$debug['getDirections']['Method'] 		= 'API';
				$debug['getDirections']['Destination'] 	= $dest_1;
				$debug['getDirections']['Response'] 	= $response;
				if (isset($response->status) && $response->status == 'OK' && isset($response->route->leg->distance)) {
					$value = array(
						'value'	=> (float)$response->route->leg->distance->value / 1000,
						'text'	=> (string)$response->route->leg->distance->text,
					);
					if ($this->field('cache')) { $this->cache->set($cache_key, $value); }
					return $value;
				}
			}

			$url = 'https://maps.googleapis.com/maps/api/directions/xml?origin=' . urlencode($origin) . '&destination=' . urlencode($dest_2) . '&sensor=false';
			$response = simplexml_load_file($url);
			$debug['getDirections']['Method'] 		= 'API';
			$debug['getDirections']['Destination'] 	= $dest_2;
			$debug['getDirections']['Response'] 	= $response;
			if (isset($response->status) && $response->status == 'OK' && isset($response->route->leg->distance)) {
				$value = array(
					'value'	=> (float)$response->route->leg->distance->value / 1000,
					'text'	=> (string)$response->route->leg->distance->text,
				);
				if ($this->field('cache')) { $this->cache->set($cache_key, $value); }
				return $value;
			}

			return false;
		}
	}

	private function getGeoCode($destination, &$debug) {
		$cache_key = $this->extension . '_geocode_' .  preg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $destination['postcode'] . ':' . $destination['zone'] . ':' . $destination['country']);

		if ($this->cache->get($cache_key) && $this->field('cache')) {
			$geocode = $this->cache->get($cache_key);
			$debug['getGeoCode']['Method'] 		= 'Cache';
			$debug['getGeoCode']['Destination'] = $destination;
			$debug['getGeoCode']['Response'] 	= $geocode;
			return array(
				'lat'	=> $geocode['lat'],
				'lng'	=> $geocode['lng']
			);
		} else {
			$dest_1 = $destination['address_1'] . ' ' . $destination['city'] . ' ' . $destination['postcode'] . ' ' . $destination['zone'] . ' ' . $destination['country'];
			$dest_2 = $destination['postcode'] . ' ' . $destination['zone'] . ' ' . $destination['country'];

			if ($destination['address_1']) {
				$url = 'https://maps.googleapis.com/maps/api/geocode/xml?address=' . urlencode($dest_1) . '&sensor=false';
				$response = simplexml_load_file($url);
				$debug['getGeoCode']['Method'] 		= 'API';
				$debug['getGeoCode']['Destination'] = $dest_1;
				$debug['getGeoCode']['Response'] 	= $response;
				if (isset($response->status) && $response->status == 'OK') {
					$value = array(
						'lat'	=> (float)$response->result->geometry->location->lat,
						'lng'	=> (float)$response->result->geometry->location->lng
					);
					if ($this->field('cache')) { $this->cache->set($cache_key, $value); }
					return $value;
				}
			}

			$url = 'https://maps.googleapis.com/maps/api/geocode/xml?address=' . urlencode($dest_2) . '&sensor=false';
			$response = simplexml_load_file($url);
			$debug['getGeoCode']['Method'] 		= 'API';
			$debug['getGeoCode']['Destination'] = $dest_2;
			$debug['getGeoCode']['Response'] 	= $response;
			if (isset($response->status) && $response->status == 'OK') {
				$value = array(
					'lat'	=> (float)$response->result->geometry->location->lat,
					'lng'	=> (float)$response->result->geometry->location->lng
				);
				if ($this->field('cache')) { $this->cache->set($cache_key, $value); }
				return $value;
			}

			return false;
		}
	}

	private function calculateCart($products, $adjusted_values, $shipping_factor, $origin = array(), $destination = array(), $currency, $total_type, $cart_dist_calc, &$debug) {
		$cart = array(
			'quantity'		=> 0,
			'total'			=> 0,
			'weight'		=> 0,
			'dim_weight'	=> 0,
			'volume'		=> 0,
			'length'		=> 0,
			'width'			=> 0,
			'height'		=> 0,
			'distance'		=> 0,
		);

		if ($products) {
			foreach ($products as $product) {
				$cart['quantity']	+= ceil((!empty($adjusted_values['product_quantity'])) ? $product['quantity'] + (strpos($adjusted_values['product_quantity'], '%') !== false ? $product['quantity'] * ($adjusted_values['product_quantity'] / 100) : $adjusted_values['product_quantity']) : $product['quantity']);

				if ($total_type == 0) {
					$cart['total']	+= (float)(!empty($adjusted_values['product_total'])) ? ($product['price'] + (strpos($adjusted_values['product_total'], '%') !== false ? $product['price'] * ($adjusted_values['product_total'] / 100) : $adjusted_values['product_total'])) * $product['quantity'] : $product['price'] * $product['quantity'];
				} elseif ($total_type == 1) {
					$cart['total']	+= (float)(!empty($adjusted_values['product_total'])) ? ($this->tax->calculate($product['price'], $product['tax_class_id']) + (strpos($adjusted_values['product_total'], '%') !== false ? $product['price'] * ($adjusted_values['product_total'] / 100) : $adjusted_values['product_total'])) * $product['quantity'] : $this->tax->calculate($product['price'], $product['tax_class_id']) * $product['quantity'];
				}

				$cart['weight']		+= (float)(!empty($adjusted_values['product_weight'])) ? ($product['weight'] + (strpos($adjusted_values['product_weight'], '%') !== false ? $product['weight'] * ($adjusted_values['product_weight'] / 100) : $adjusted_values['product_weight'])) * $product['quantity'] : $product['weight'] * $product['quantity'];

				if ($shipping_factor > 0) {
					if ($product['volume'] / $shipping_factor > $product['weight']) {
						$cart['dim_weight'] += (float)(!empty($adjusted_values['product_weight'])) ? ($product['volume'] / $shipping_factor * $product['quantity']) + (strpos($adjusted_values['product_weight'], '%') !== false ? ($product['volume'] / $shipping_factor * $product['quantity']) * ($adjusted_values['product_weight'] / 100) : $adjusted_values['product_weight']) : $product['volume'] / $shipping_factor * $product['quantity'];
					} else {
						$cart['dim_weight'] += (float)(!empty($adjusted_values['product_weight'])) ? ($product['weight'] * $product['quantity']) + (strpos($adjusted_values['product_weight'], '%') !== false ? $product['weight'] * ($adjusted_values['product_weight'] / 100) : $adjusted_values['product_weight']) : $product['weight'] * $product['quantity'];
					}
				}

				$cart['volume']		+= (float)(!empty($adjusted_values['product_volume'])) ? ($product['volume'] + (strpos($adjusted_values['product_volume'], '%') !== false ? $product['volume'] * ($adjusted_values['product_volume'] / 100) : $adjusted_values['product_volume'])) * $product['quantity'] : $product['volume'] * $product['quantity'];
				$cart['length']		+= (float)(!empty($adjusted_values['product_length'])) ? ($product['length'] + (strpos($adjusted_values['product_length'], '%') !== false ? $product['length'] * ($adjusted_values['product_length'] / 100) : $adjusted_values['product_length'])) * $product['quantity'] : $product['length'] * $product['quantity'];
				$cart['width']		+= (float)(!empty($adjusted_values['product_width'])) ? ($product['width'] + (strpos($adjusted_values['product_width'], '%') !== false ? $product['width'] * ($adjusted_values['product_width'] / 100) : $adjusted_values['product_width'])) * $product['quantity'] : $product['width'] * $product['quantity'];
				$cart['height']		+= (float)(!empty($adjusted_values['product_height'])) ? ($product['height'] + (strpos($adjusted_values['product_height'], '%') !== false ? $product['height'] * ($adjusted_values['product_height'] / 100) : $adjusted_values['product_height'])) * $product['quantity'] : $product['height'] * $product['quantity'];
			}

			if ($cart_dist_calc && !empty($origin['origin']) && !empty($origin['lat']) && !empty($origin['lng']) && !empty($destination)) {
				$cart['distance'] = (float)$this->getDistance($origin, $destination, $debug);
			}

			if ($total_type == 2) {
				//Temporarily Adjust Products In Cart
				$this->cart->clear();
				foreach ($products as $product) {
					$option_data = array();
					foreach ($product['option'] as $option) {
						if (in_array($option['type'], array('select', 'radio', 'image'))) {
							$option_data[$option['product_option_id']] = $option['product_option_value_id'];
						} elseif ($option['type'] == 'checkbox') {
							$option_data[$option['product_option_id']][] = $option['product_option_value_id'];
							$option_data[$option['option_id']][] = $option['value'];
						} elseif (in_array($option['type'], array('text', 'textarea', 'file', 'date', 'datetime', 'time'))) {
							$option_data[$option['product_option_id']] = $option['value'];
						}
					}
					$quantity		= $product['quantity'] + (!empty($adjusted_values['product_quantity']) ? $adjusted_values['product_quantity'] : 0);
					$recurring_id 	= ($product['recurring']) ? $product['recurring']['recurring_id'] : 0;
					$this->cart->add($product['product_id'], $quantity, $option_data, $recurring_id);
				}

				$totals	= array();
				$total 	= 0;
				$taxes 	= $this->cart->getTaxes();

				$total_data = array(
					'totals'	=> &$totals,
					'taxes'		=> &$taxes,
					'total'		=> &$total,
				);

				$this->load->model((($this->version() >= 3.0) ? 'setting' : 'extension') . '/extension');
				$results = $this->{'model_' . (($this->version() >= 3.0) ? 'setting' : 'extension') . '_extension'}->getExtensions('total');
				
				$sort_order = array();
				foreach ($results as $key => $value) {
					$sort_order[$key] = $this->config->get((($this->version() >= 3.0) ? 'total_' : '') . $value['code'] . '_sort_order');
				}
				array_multisort($sort_order, SORT_ASC, $results);
				foreach ($results as $result) {
					if ($result['code'] == 'shipping') {
						break;
					} else {
						if ($this->config->get((($this->version() >= 3.0) ? 'total_' : '') . $result['code'] . '_status')) {
							$this->load->model('extension/total/' . $result['code']);
							$this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
						}
					}
				}
				$cart['total'] = (float)$total;
				
				//Restore Cart
				$this->cart->clear();
				$this->restoreCart();
			}

			foreach ($cart as $key => $value) {
				if ($key == 'quantity') {
					$cart[$key] += ceil((!empty($adjusted_values['cart_' . $key])) ? (strpos($adjusted_values['cart_' . $key], '%') !== false ? $value * ($adjusted_values['cart_' . $key] / 100) : $adjusted_values['cart_' . $key]) : 0);
				} else {
					$cart[$key] += (float)(!empty($adjusted_values['cart_' . $key])) ? (strpos($adjusted_values['cart_' . $key], '%') !== false ? $value * ($adjusted_values['cart_' . $key] / 100) : $adjusted_values['cart_' . $key]) : 0;
				}
			}

			if ($currency !== $this->config->get('config_currency')) {
				$cart['total'] = $this->currency->convert($cart['total'], $this->config->get('config_currency'), $currency);
			}

			$debug['Cart'] = $cart;
		} else {
			$debug['Cart'] = 'No Products Found';
		}

		return $cart;
	}

	private function checkRequirementProduct($product, $group, $type, $operation, $value, $parameter, &$debug) {
		$type 			= str_replace($group . '_', '', $type);
		$values 		= array_map('strtolower', (is_array($value)) ? $value : explode(',', $value));
		$status 		= ($operation == 'neq' || $operation == 'nstrpos') ? true : false;
		
		if (strpos($type, 'option') !== false) {
			$option_data 	= explode('_', $type);
			$option_id		= $option_data[1];
			$type			= 'option';
			foreach ($product[$type] as $product_option) {
				if ($product_option['option_id'] == $option_id) {
					$product_option_value = ($product_option['option_value_id']) ? $product_option['option_value_id'] : $product_option['value'];
					if ($operation == 'eq' && in_array(strtolower($product_option_value), $values)) { $status = true; break; }
					if ($operation == 'neq' && in_array(strtolower($product_option_value), $values)) { $status = false; break; }
					if ($operation == 'strpos' || $operation == 'nstrpos' || $operation == 'gte' || $operation == 'lte') {
						foreach ($values as $param) {
							if ($operation == 'strpos' && strpos(strtolower($product_option_value), $param) !== false) { $status = true; break; }
							if ($operation == 'nstrpos' && strpos(strtolower($product_option_value), $param) !== false) { $status = false; break; }
							if ($operation == 'gte' && strtolower($product_option_value) >= $param) { $status = true; break; }
							if ($operation == 'lte' && strtolower($product_option_value) <= $param) { $status = true; break; }		
						}
					}
				}
			}
		} elseif (strpos($type, 'attribute') !== false) {
			$attribute_data 	= explode('_', $type);
			$attribute_id		= $attribute_data[1];
			$type				= 'attribute';
			foreach ($product[$type] as $product_attribute_group) {
				foreach ($product_attribute_group['attribute'] as $product_attribute) {
					if ($product_attribute['attribute_id'] == $attribute_id) {
						if (in_array(strtolower($product_attribute['text']), $values)) {
							$status = ($operation == 'eq') ? true : false;
						}
					}
				}
			}
		} elseif ($type == 'category') {
			foreach ($product[$type] as $category) {
				if (in_array($category['category_id'], $values)) {
					$status = ($operation == 'eq') ? true : false;
				}
			}
		} elseif ($type == 'manufacturer') {
			if (in_array($product['manufacturer'], $values)) {
				$status = ($operation == 'eq') ? true : false;
			}
		} else {
			foreach ($values as $value) {
				$value			= trim(strtolower($value));
				$product[$type]	= trim(strtolower($product[$type]));
				$range_status	= false;
				if (strpos($value, ':') !== false) {
					$range = explode(':', $value);
					if (!empty($range[0]) && !empty($range[1])) {
						$range_status = ($product[$type] >= trim($range[0]) && $product[$type] <= trim($range[1])) ? true : false;
					}
				}
				if ($operation == 'eq' && ($product[$type] == $value || $range_status)) { $status = true; break; }
				if ($operation == 'neq' && ($product[$type] == $value || $range_status)) { $status = false; break; }
				if ($operation == 'strpos' && strpos($product[$type], $value) !== false) { $status = true; break; }
				if ($operation == 'nstrpos' && strpos($product[$type], $value) !== false) { $status = false; break; }
				if ($operation == 'gte' && $product[$type] >= $value) { $status = true; }
				if ($operation == 'lte' && $product[$type] <= $value) { $status = true; break; }
			}
		}

		$debug['Requirements']['RequirementCheck']['Product'][] = array(
			'Product'			=> $product['name'],
			'Type'				=> isset($option_id) ? $type . '_' . $option_id : $type,
			'ID'				=> isset($option_id) ? $option_id : (isset($attribute_id) ? $attribute_id : ''),
			'Operation'			=> $operation,
			'ProductValue'		=> $product[$type],
			'RequirementValue'	=> $values,
			'Status'			=> $status,
		);

		return $status;
	}

	private function checkRequirementCart($cart, $group, $type, $operation, $value, $parameter, &$debug) {
		$type 			= str_replace($group . '_', '', $type);
		$value			= trim(strtolower($value));
		$status 		= ($operation == 'neq' || $operation == 'nstrpos') ? true : false;
		$range_status	= false;

		if (strpos($value, ':') !== false) {
			$range = explode(':', $value);
			if (!empty($range[0]) && !empty($range[1])) {
				$range_status = ($cart[$type] >= trim($range[0]) && $cart[$type] <= trim($range[1])) ? true : false;
			}
		}

		if ($operation == 'eq' && ($cart[$type] == $value || $range_status)) { $status = true; }
		if ($operation == 'neq' && ($cart[$type] != $value && !$range_status)) { $status = true; }
		if ($operation == 'gte' && $cart[$type] >= $value) { $status = true; }
		if ($operation == 'lte' && $cart[$type] <= $value) { $status = true; }

		$debug['Requirements']['RequirementCheck']['Cart'][] = array(
			'Type'				=> $type,
			'Operation'			=> $operation,
			'CartValue'			=> $cart[$type],
			'RequirementValue'	=> $value,
			'Status'			=> $status,
			'RangeStatus'		=> $range_status,
		);

		return $status;
	}

	private function checkRequirementCustomer($customer, $group, $type, $operation, $value, $parameter, &$debug) {
		$type 		= str_replace($group . '_', '', $type);
		$values 	= (is_array($value)) ? $value : explode(',', strtolower($value));
		$status 	= ($operation == 'neq' || $operation == 'nstrpos') ? true : false;

		if (strpos($type, 'customfield') !== false) {
			$custom_field_data 	= explode('_', $type);
			$custom_field_id	= $custom_field_data[1];
			$type				= 'customfield';
			$value				= !empty($customer[$type][$custom_field_id]) ? strtolower($customer[$type][$custom_field_id]) : null;
			
			if ($value) {				
				if ($operation == 'eq' && ((is_array($value) && array_intersect($value, $values)) || in_array($value, $values))) { $status = true; }
				if ($operation == 'neq' && ((is_array($value) && array_intersect($value, $values)) || in_array($value, $values))) { $status = false; }
				if ($operation == 'strpos' || $operation == 'nstrpos' || $operation == 'gte' || $operation == 'lte') {
					foreach ($values as $param) {
						if ($operation == 'strpos' && strpos($value, $param) !== false) { $status = true; break; }
						if ($operation == 'nstrpos' && strpos($value, $param) !== false) { $status = false; break; }
						if ($operation == 'gte' && $value >= $param) { $status = true; break; }
						if ($operation == 'lte' && $value <= $param) { $status = true; break; }		
					}
				}
			}
		} else {
			foreach ($values as $value) {
				$value				= trim(strtolower($value));
				$customer[$type]	= trim(strtolower($customer[$type]));
				if ($type == 'postcode') {
					$postcode_status = $this->checkPostalCodes($customer['postcode'], $value, $parameter['type'], $debug);
					if ($postcode_status) {
						if ($operation == 'eq') { $status = true; }
						if ($operation == 'neq') { $status = false; }
						break;
					}
				} else {
					if ($operation == 'eq' && (string)$customer[$type] == (string)$value) { $status = true; break; }
					if ($operation == 'neq' && (string)$customer[$type] == (string)$value) { $status = false; break; }
					if ($operation == 'strpos' && strpos($customer[$type], $value) !== false) { $status = true; break; }
					if ($operation == 'nstrpos' && strpos($customer[$type], $value) !== false) { $status = false; break; }
				}
			}
		}

		$debug['Requirements']['RequirementCheck']['Customer'][] = array(
			'Type'				=> isset($custom_field_id) ? $type . '_' . $custom_field_id : $type,
			'Operation'			=> $operation,
			'CustomerValue'		=> $customer[$type],
			'RequirementValue'	=> $values,
			'Status'			=> $status,
		);

		return $status;
	}

	private function checkRequirementOther($other, $group, $type, $operation, $value, $parameter) {
		$type 			= str_replace($group . '_', '', $type);
		$values 		= (is_array($value)) ? $value : explode(',', $value);
		$other[$type]	= trim($other[$type]);

		if ($type == 'day') {
			$status = false;
			if (in_array($other[$type], $values)) {
				if ($operation == 'eq') { $status = true; }
			}
		} else {
			if ($operation == 'eq' && $other[$type] == $value) { $status = true; }
			if ($operation == 'neq' && $other[$type] !== $value) { $status = true; }
			if ($operation == 'gte' && $other[$type] >= $value) { $status = true; }
			if ($operation == 'lte' && $other[$type] <= $value) { $status = true; }
		}

		$debug['Requirements']['RequirementCheck']['Other'][] = array(
			'Type'				=> $type,
			'Operation'			=> $operation,
			'OtherValue'		=> $other[$type],
			'RequirementValue'	=> $values,
			'Status'			=> $status,
		);

		return $status;
	}

	private function weight() {
		return 'weight_class_id';
	}

	private function length() {
		return 'length_class_id';
	}

	private function checkPerProductShipping($product_id, $geo_zone_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_shipping WHERE product_id = '" . (int)$product_id . "' AND geo_zone_id = '" . (int)$geo_zone_id . "'");
		if ($query->row && !empty($query->row['first'])) {
			return true;
		} else {
			return false;
		}
	}

	private function checkPostalCodes($postcode, $range, $type, &$debug) {
		$status = false;

		if ($postcode && $range) {
			$range = explode(':', $range);
			$postcode = trim(preg_replace('/[\s\-]/', '', strtoupper($postcode)), ' ');

			$debug['checkPostalCodes']['PostCode'] 	= $postcode;
			$debug['checkPostalCodes']['Type'] 		= $type;

			if (isset($range[0]) && isset($range[1])) {
				$start 	= trim(preg_replace('/[\s\-]/', '', strtoupper($range[0])), ' ');
				$end 	= trim(preg_replace('/[\s\-]/', '', strtoupper($range[1])), ' ');
				$x 		= (strlen($start) > strlen($end)) ? strlen($start) : strlen($end);

				$debug['checkPostalCodes']['PostCodeRange'][] = array(
					'Start'		=> $start,
					'End'		=> $end,
					'Length'	=> $x,
				);

				if ($type == 'uk') {
					foreach ($this->ukFormats as $format) {
						if (preg_match($format['regex'], $postcode) && (preg_match($format['regex'], $start) || preg_match($format['regex'], $end))) {
							if (strnatcmp($start, $postcode) <= 0 && strnatcmp($end, $postcode) >= 0) {
								$status = true;
								$debug['checkPostalCodes']['RangeFound'] = trim(preg_replace('/[\s\-]/', '', strtoupper($range[0])), ' ') . ':' . trim(preg_replace('/[\s\-]/', '', strtoupper($range[1])), ' ');
							}
						}
					}
				} else {
					$modified_postcode = substr($postcode, 0, $x);
					if ($this->validatePostalCode($start, $modified_postcode, $x, $debug)) {
						if (strnatcmp($start, $modified_postcode) <= 0 && strnatcmp($end, $modified_postcode) >= 0) {
							$status = true;
							$debug['checkPostalCodes']['RangeFound'] = trim(strtoupper($range[0]), ' ') . ':' . trim(strtoupper($range[1]), ' ');
						}
					}
				}
			} else {
				$range[0] = trim(preg_replace('/[\s\-]/', '', strtoupper($range[0])), ' ');
				if ($range[0] === substr($postcode, 0, strlen($range[0]))) {
					$status = true;
					$debug['checkPostalCodes']['RangeFound'] = $range[0];
				}
			}
		} elseif (!$range) {
			$debug['checkPostalCodes']['Note'] = 'No Range Found';
			$status = true;
		} else {
			$debug['checkPostalCodes']['Note'] = 'Customer Post Code Not Found';
		}
		$debug['checkPostalCodes']['Status'] = $status;

		return $status;
	}

	private function validatePostalCode($start = 0, $postcode = 0, $x = 0, $debug) {
		$debug['checkPostalCodes']['validatePostalCode']['PostCode'] = $postcode;

		$start 		= str_split($start);
		$postcode 	= str_split($postcode);
		$i 			= 0;

		$status = false;
		if ($start && $postcode && $x) {
			while ($i <= ($x - 1)) {
				$a	= isset($start[$i]) ? $start[$i] : 0;
				$b 	= isset($postcode[$i]) ? $postcode[$i] : 0;

				if (is_numeric($a) && is_numeric($b)) {
					$status = true;
				} elseif (!is_numeric($a) && !is_numeric($b)) {
					$status = true;
				} elseif ($a == $b) {
					$status = true;
				} else {
					$status = false;
					break;
				}
				$i ++;
			}
		}

		$debug['checkPostalCodes']['validatePostalCode']['Status'] = $status;

		return $status;
	}

	private function getRateMax($rates) {
		$rate 	= end($rates);
		return !empty($rate['max']) ? $rate['max'] : 0;
	}

	private function getRateSingle($value, $rates, $total, &$debug) {
		$quote 	= '';

		foreach ($rates as $key => $rate) {
			if ($rate['max'] >= $value || $rate['max'] == '~') {
				$cost 	= (strpos($rate['cost'], '%')) ? $total * ($rate['cost'] / 100) : $rate['cost'];
				$quote	= ($rate['per']) ? ceil($value / $rate['per']) * $cost : $cost;
				$debug['Single']['Rate'] = $rate;
				break;
			}
		}

		$debug['Single']['Value'] = $value;
		$debug['Single']['Quote'] = $quote;

		return $quote;
	}

	private function getRateCumulative($value, $rates, $total, &$debug) {
		$quote 			= '';
		$prev 			= 0;
		$max_found		= false;

		foreach ($rates as $key => $rate) {
			if ($rate['max'] < $value && $rate['max'] !== '~') {
				$quote		 = (float)$quote;
				$cost 		 = (strpos($rate['cost'], '%')) ? $total * ($rate['cost'] / 100) : $rate['cost'];
				$quote		+= ($rate['per']) ? ceil(($rate['max'] - $prev) / $rate['per']) * $cost : $cost;
				$prev		 = $rate['max'];
				$debug['Cumulative']['Rates'][] = $rate;
			} else {
				$quote		 = (float)$quote;
				$cost 	 	 = (strpos($rate['cost'], '%')) ? $total * ($rate['cost'] / 100) : $rate['cost'];
				$quote		+= ($rate['per']) ? ceil(($value - $prev) / $rate['per']) * $cost : $cost;
				$max_found	 = true;
				$debug['Cumulative']['Rates'][] = $rate;
				break;
			}
		}
		if (!$max_found) {
			$quote	= '';
			$debug['Cumulative']['Rates'][] = 'Value Exceeds Maximum Rate';
		}

		$debug['Cumulative']['Value'] = $value;
		$debug['Cumulative']['Quote'] = $quote;

		return $quote;
	}

	private function getShipping($code, $rates, $address, &$debug) {
		$cost 				= '';
		$shipping_method 	= array();

		$this->load->model('extension/shipping/' . $code);
		$shipping_method = $this->{'model_extension_shipping_' . $code}->getQuote($address);

		if ($shipping_method && empty($shipping_method['error'])) {
			foreach ($shipping_method['quote'] as $quote) {
				if (count($shipping_method['quote']) > 1) {
					if ($rates && (strpos(strtolower($quote['code']), strtolower($rates)) !== false || strpos(strtolower($quote['title']), strtolower($rates)) !== false)) {
						$cost  = (float)$cost;
						$cost += $quote['cost'];
						$debug['getShipping'] = array(
							'Code'	=> strtoupper($code),
							'Rates'	=> ucfirst($rates),
							'Cost'	=> $quote['cost'],
						);
						break;
					}
				} else {
					$cost  = (float)$cost;
					$cost += $quote['cost'];
					$debug['getShipping'] = array(
						'Code'	=> strtoupper($code),
						'Cost'	=> $quote['cost'],
					);
				}
			}
		} elseif ($this->debugStatus) {
			if ($shipping_method) {
				$debug['getShipping'] = array(
					'Code'	=> strtoupper($code),
					'Error'	=> !empty($shipping_method['error']) ? ucfirst($shipping_method['error']) : 'Unknown',
				);
			} else {
				$debug['getShipping'] = array(
					'Code'	=> strtoupper($code),
					'Error'	=> 'Empty',
				);
			}
		}
		return $cost;
	}

	private function getPerProductShipping($address, $geo_zone) {
		$cost = '';

		if ($this->ocapps_status && $this->field('ocapps_status')) {
			$this->load->model('extension/shipping/ocapps');
			$method_data = $this->{'model_extension_shipping_ocapps'}->getQuote($address);
			if ($method_data) {
				foreach ($method_data['quote'] as $quote_data) {
					$pps_geo_zone = explode('_', $quote_data['code']);
					if ($pps_geo_zone[1] == $geo_zone) {
						$cost  = (float)$cost;
						$cost += (float)$quote_data['cost'];
					}
				}
			}
		}

		return $cost;
	}

	private function getQuoteData($data) {
		$data['cost'] = round($data['cost'], (int)$this->currency->getDecimalPlace($this->session->data['currency']));
		
		return array(
			'id'		   => $this->extension . '.' . $this->extension . '_' . $data['code'],
			'code'		   => $this->extension . '.' . $this->extension . '_' . $data['code'],
			'title'        => $data['title'],
			'cost'         => $data['cost'],
			'value'        => $data['cost'],
			'text'         => $this->currency->format($this->tax->calculate($data['cost'], $data['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']),
			'sort_order'   => $data['sort_order'],
			'tax_class_id' => $data['tax_class_id']
		);
	}

	private function writeDebug($debug) {
		if ($this->debugStatus && $debug) {
			$write 	= date('Y-m-d h:i:s');
			$write .= ' - ';
			$write .= print_r($debug, true);
			$write .= "\n";

			$file	= DIR_LOGS . $this->extension . '.txt';

			file_put_contents ($file, $write, FILE_APPEND);
		}
	}

	private $ocapps_status = false;
}
?>