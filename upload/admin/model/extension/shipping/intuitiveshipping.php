<?php
//==================================================//
// Product:	Intuitive Shipping              		//
// Author: 	Joel Reeds                        		//
// Company: OpenCart Addons                  		//
// Website: http://www.opencartaddons.com        	//
// Contact: http://www.opencartaddons.com/contact  	//
//==================================================//

class ModelExtensionShippingIntuitiveShipping extends Model { 
	private $db_table		= 'intuitive_shipping';
	
	public function addRate($data) {
		foreach ($this->settings() as $key => $value) {
			$data[$key] = isset($data[$key]) ? $data[$key] : $value;
		}			
		
		$this->db->query("INSERT INTO " . DB_PREFIX . $this->db_table . " SET
			description = '" . $this->db->escape(substr($data['description'], 0, 100)) . "',
			status = '" . (int)$data['status'] . "',
			sort_order = '" . (int)$data['sort_order'] . "',
			`group` = '" . $this->db->escape($data['group']) . "',
			tax_class_id = '" . (int)$data['tax_class_id'] . "',
			total_type = '" . (int)$data['total_type'] . "',
			name = '" . $this->db->escape(json_encode($data['name'])) . "',
			shipping = '" . $this->db->escape(json_encode($data['shipping'])) . "',
			origin = '" . $this->db->escape($data['origin']) . "',
			geocode_lat = '" . (float)$data['geocode_lat'] . "',
			geocode_lng = '" . (float)$data['geocode_lng'] . "',
			ocapps_cost = '" . (int)$data['ocapps_cost'] . "',
			ocapps_requirement = '" . (int)$data['ocapps_requirement'] . "',
			requirement_match = '" . $this->db->escape($data['requirement_match']) . "',
			requirement_cost = '" . $this->db->escape($data['requirement_cost']) . "',
			requirements = '" . $this->db->escape(json_encode($data['requirements'])) . "',
			fail_method = '" . (int)$data['fail_method'] . "',
			date_added = NOW(),
			date_modified = NOW(),
			administrator = '" . $this->db->escape($this->user->getUserName()) . "'
		");
		
		return $this->db->getLastId();
	}
	
	public function editRate($rate_id, $data) {
		foreach ($this->settings() as $key => $value) {
			$data[$key] = isset($data[$key]) ? $data[$key] : $value;
		}
		
		$this->db->query("UPDATE " . DB_PREFIX . $this->db_table . " SET
			description = '" . $this->db->escape(substr($data['description'], 0, 100)) . "',
			status = '" . (int)$data['status'] . "',
			sort_order = '" . (int)$data['sort_order'] . "',
			`group` = '" . $this->db->escape($data['group']) . "',
			tax_class_id = '" . (int)$data['tax_class_id'] . "',
			total_type = '" . (int)$data['total_type'] . "',
			name = '" . $this->db->escape(json_encode($data['name'])) . "',
			shipping = '" . $this->db->escape(json_encode($data['shipping'])) . "',
			origin = '" . $this->db->escape($data['origin']) . "',
			geocode_lat = '" . (float)$data['geocode_lat'] . "',
			geocode_lng = '" . (float)$data['geocode_lng'] . "',
			ocapps_cost = '" . (int)$data['ocapps_cost'] . "',
			ocapps_requirement = '" . (int)$data['ocapps_requirement'] . "',
			requirement_match = '" . $this->db->escape($data['requirement_match']) . "',
			requirement_cost = '" . $this->db->escape($data['requirement_cost']) . "',
			requirements = '" . $this->db->escape(json_encode($data['requirements'])) . "',
			fail_method = '" . (int)$data['fail_method'] . "',
			date_modified = NOW(),
			administrator = '" . $this->db->escape($this->user->getUserName()) . "'
		WHERE rate_id = '" . (int)$rate_id . "'");
	}
	
	public function copyRate($rate_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . $this->db_table . " WHERE rate_id = '" . (int)$rate_id . "'");
		
		if ($query->num_rows) {
			$data = array();
			
			foreach ($query->row as $key => $value) {
				$data[$key] = $this->value($value);
			}
			$data['rate_id'] = $this->addRate($data);
			
			return $data;
		}
	}
	
	public function deleteRate($rate_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . $this->db_table . " WHERE rate_id = '" . (int)$rate_id . "'");
	}
	
	public function deleteAllRates() {
		$this->db->query("TRUNCATE TABLE " . DB_PREFIX . $this->db_table . "");
	}
	
	public function getRate($rate_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . $this->db_table . " WHERE rate_id = '" . (int)$rate_id . "'");
		
		return $query->row;
	}
	
	public function getRates($filter = array()) {
		$sql  = "SELECT * FROM " . DB_PREFIX . $this->db_table;
		
		if ($filter) {
			$x = 1;
			foreach (array('filter_description', 'filter_name', 'filter_status', 'filter_group') as $key) {
				if (!empty($filter[$key]) || $filter[$key] == '0') {
					$sql .= ($x > 1) ? " AND" : " WHERE";
					if ($key == 'filter_description') {
						$sql .= " LOWER(description) LIKE '%" . $this->db->escape(strtolower($filter[$key])) . "%'";
					} elseif ($key == 'filter_name') {
						$sql .= " LOWER(name) LIKE '%" . $this->db->escape(strtolower($filter[$key])) . "%'";
					} elseif ($key == 'filter_status') {
						$sql .= " status = '" . (int)$filter[$key] . "'";
					} else {
						$sql .= " LOWER(`" . str_replace('filter_', '', $key) . "`) = '" . $this->db->escape(strtolower($filter[$key])) . "'";
					}
					$x++;
				}
			}
		}

		$sql .= " ORDER BY `group`, rate_id ASC";
		
		$query = $this->db->query($sql);
		
		return $query->rows;
	}
	
	private function value($value) {
		return $value = (!is_array($value) && is_array(json_decode($value, true))) ? json_decode($value, true) : $value;
	}
	
	public function settings() {
		return array(
			'rate_id'			=> 0,
			'description'		=> '',
			'status'			=> 0,
			'sort_order'		=> 1,
			'group'				=> '',
			'tax_class_id'		=> 0,
			'total_type'		=> 0,
			'name'				=> array(),
			'shipping'			=> array(),
			'origin'			=> '',
			'geocode_lat'		=> 0,
			'geocode_lng'		=> 0,
			'ocapps_cost'		=> 0,
			'ocapps_requirement'=> 0,
			'requirement_match'	=> 'any',
			'requirement_cost'	=> 'every',
			'requirements'		=> array(),
			'fail_method'		=> 0,
			'date_added'		=> '0000-00-00 00:00:00',
			'date_modified'		=> '0000-00-00 00:00:00',
			'administrator'		=> ''
		);
	}
	
	public function install() {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . $this->db_table . "` (
			`rate_id` int(11) NOT NULL AUTO_INCREMENT,
			`description` text NOT NULL,
			`status` tinyint(1) NOT NULL DEFAULT 0,
			`sort_order` int(3) NOT NULL,
			`group` text NOT NULL,
			`tax_class_id` int(11) NOT NULL,
			`total_type` tinyint(1) NOT NULL,
			`name` text NOT NULL,
			`shipping` longtext NOT NULL,
			`origin` text NOT NULL,
			`geocode_lat` decimal(20,8) NOT NULL,
			`geocode_lng` decimal(20,8) NOT NULL,
			`ocapps_cost` tinyint(1) NOT NULL,
			`ocapps_requirement` tinyint(1) NOT NULL,
			`requirement_match` varchar(10) NOT NULL,
			`requirement_cost` varchar(10) NOT NULL,
			`requirements` longtext NOT NULL,
			`fail_method` tinyint(1) NOT NULL,
			`date_added` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			`date_modified` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			`administrator` varchar(50) NOT NULL,
			PRIMARY KEY (`rate_id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;");
		
		$this->db->query("ALTER TABLE `" . DB_PREFIX . "order` MODIFY COLUMN shipping_method text NOT NULL");
	}
	
	public function update() {
		$status 		= false;
		$log 			= 'Success: The following updates have been completed:<br />';
		$custom_table	= $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . $this->db_table. "`");
		$custom_columns	= array();
		foreach ($custom_table->rows as $result) { 
		  $custom_columns[$result['Field']] = $result; 
		}
		
		if ($custom_columns) {
			// v1.3.0
			if (!isset($custom_columns['fail_method'])) {
				$this->db->query("ALTER TABLE `" . DB_PREFIX . $this->db_table . "` ADD `fail_method` TINYINT(1) NOT NULL after requirements");
				$status	= true;
				$log   .= '[v1.3.0] Fail Method column added<br />';
			}
		}
		
		return array(
			'status'	=> $status,
			'log'		=> $log
		);
	}
	
	public function uninstall() {
		$this->db->query("DROP TABLE " . DB_PREFIX . $this->db_table . "");
	}
}
?>