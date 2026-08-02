<?php
declare(strict_types=1);

namespace Opencart\Admin\Model\Extension\Advancedshipping\Shipping;

class Advancedshipping extends \Opencart\System\Engine\Model {
	private string $dbTable = 'advanced_shipping';

	public function addRate(array $data): int {
		foreach ($this->settings() as $key => $value) {
			$data[$key] = $data[$key] ?? $value;
		}

		$description   = substr((string)($data['description'] ?? ''), 0, 100);
		$status        = (int)($data['status'] ?? 0);
		$sortOrder     = (int)($data['sort_order'] ?? 0);
		$group         = (string)($data['group'] ?? '');
		$taxClassId    = (int)($data['tax_class_id'] ?? 0);
		$totalType     = (int)($data['total_type'] ?? 0);
		$name          = is_array($data['name'] ?? null) ? json_encode($data['name']) : (string)($data['name'] ?? '');
		$shipping      = is_array($data['shipping'] ?? null) ? json_encode($data['shipping']) : (string)($data['shipping'] ?? '');
		$origin        = (string)($data['origin'] ?? '');
		$geocodeLat    = (float)($data['geocode_lat'] ?? 0);
		$geocodeLng    = (float)($data['geocode_lng'] ?? 0);
		$ocappsCost    = (int)($data['ocapps_cost'] ?? 0);
		$ocappsReq     = (int)($data['ocapps_requirement'] ?? 0);
		$reqMatch      = (string)($data['requirement_match'] ?? 'any');
		$reqCost       = (string)($data['requirement_cost'] ?? 'every');
		$requirements  = is_array($data['requirements'] ?? null) ? json_encode($data['requirements']) : (string)($data['requirements'] ?? '');
		$failMethod    = (int)($data['fail_method'] ?? 0);
		$administrator = (string)$this->user->getUserName();

		$this->db->query("INSERT INTO `" . DB_PREFIX . $this->dbTable . "` SET
			`description` = '" . $this->db->escape($description) . "',
			`status` = '" . $status . "',
			`sort_order` = '" . $sortOrder . "',
			`group` = '" . $this->db->escape($group) . "',
			`tax_class_id` = '" . $taxClassId . "',
			`total_type` = '" . $totalType . "',
			`name` = '" . $this->db->escape($name) . "',
			`shipping` = '" . $this->db->escape($shipping) . "',
			`origin` = '" . $this->db->escape($origin) . "',
			`geocode_lat` = '" . $geocodeLat . "',
			`geocode_lng` = '" . $geocodeLng . "',
			`ocapps_cost` = '" . $ocappsCost . "',
			`ocapps_requirement` = '" . $ocappsReq . "',
			`requirement_match` = '" . $this->db->escape($reqMatch) . "',
			`requirement_cost` = '" . $this->db->escape($reqCost) . "',
			`requirements` = '" . $this->db->escape($requirements) . "',
			`fail_method` = '" . $failMethod . "',
			`date_added` = NOW(),
			`date_modified` = NOW(),
			`administrator` = '" . $this->db->escape($administrator) . "'
		");

		return (int)$this->db->getLastId();
	}

	public function editRate(int $rate_id, array $data): void {
		foreach ($this->settings() as $key => $value) {
			$data[$key] = $data[$key] ?? $value;
		}

		$description   = substr((string)($data['description'] ?? ''), 0, 100);
		$status        = (int)($data['status'] ?? 0);
		$sortOrder     = (int)($data['sort_order'] ?? 0);
		$group         = (string)($data['group'] ?? '');
		$taxClassId    = (int)($data['tax_class_id'] ?? 0);
		$totalType     = (int)($data['total_type'] ?? 0);
		$name          = is_array($data['name'] ?? null) ? json_encode($data['name']) : (string)($data['name'] ?? '');
		$shipping      = is_array($data['shipping'] ?? null) ? json_encode($data['shipping']) : (string)($data['shipping'] ?? '');
		$origin        = (string)($data['origin'] ?? '');
		$geocodeLat    = (float)($data['geocode_lat'] ?? 0);
		$geocodeLng    = (float)($data['geocode_lng'] ?? 0);
		$ocappsCost    = (int)($data['ocapps_cost'] ?? 0);
		$ocappsReq     = (int)($data['ocapps_requirement'] ?? 0);
		$reqMatch      = (string)($data['requirement_match'] ?? 'any');
		$reqCost       = (string)($data['requirement_cost'] ?? 'every');
		$requirements  = is_array($data['requirements'] ?? null) ? json_encode($data['requirements']) : (string)($data['requirements'] ?? '');
		$failMethod    = (int)($data['fail_method'] ?? 0);
		$administrator = (string)$this->user->getUserName();

		$this->db->query("UPDATE `" . DB_PREFIX . $this->dbTable . "` SET
			`description` = '" . $this->db->escape($description) . "',
			`status` = '" . $status . "',
			`sort_order` = '" . $sortOrder . "',
			`group` = '" . $this->db->escape($group) . "',
			`tax_class_id` = '" . $taxClassId . "',
			`total_type` = '" . $totalType . "',
			`name` = '" . $this->db->escape($name) . "',
			`shipping` = '" . $this->db->escape($shipping) . "',
			`origin` = '" . $this->db->escape($origin) . "',
			`geocode_lat` = '" . $geocodeLat . "',
			`geocode_lng` = '" . $geocodeLng . "',
			`ocapps_cost` = '" . $ocappsCost . "',
			`ocapps_requirement` = '" . $ocappsReq . "',
			`requirement_match` = '" . $this->db->escape($reqMatch) . "',
			`requirement_cost` = '" . $this->db->escape($reqCost) . "',
			`requirements` = '" . $this->db->escape($requirements) . "',
			`fail_method` = '" . $failMethod . "',
			`date_modified` = NOW(),
			`administrator` = '" . $this->db->escape($administrator) . "'
		WHERE `rate_id` = '" . $rate_id . "'");
	}

	public function copyRate(int $rate_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . $this->dbTable . "` WHERE `rate_id` = '" . $rate_id . "'");

		if ($query->num_rows) {
			$data = [];
			foreach ($query->row as $key => $value) {
				$data[$key] = $this->value($value);
			}
			$data['rate_id'] = $this->addRate($data);

			return $data;
		}

		return [];
	}

	public function deleteRate(int $rate_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . $this->dbTable . "` WHERE `rate_id` = '" . $rate_id . "'");
	}

	public function deleteAllRates(): void {
		$this->db->query("TRUNCATE TABLE `" . DB_PREFIX . $this->dbTable . "`");
	}

	public function getRate(int $rate_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . $this->dbTable . "` WHERE `rate_id` = '" . $rate_id . "'");

		return $query->row ?: [];
	}

	public function getRates(array $filter = []): array {
		$sql = "SELECT * FROM `" . DB_PREFIX . $this->dbTable . "`";

		if (!empty($filter)) {
			$x = 1;
			foreach (['filter_description', 'filter_name', 'filter_status', 'filter_group'] as $key) {
				if (isset($filter[$key]) && ($filter[$key] !== '' || $filter[$key] === '0')) {
					$sql .= ($x > 1) ? " AND" : " WHERE";
					if ($key === 'filter_description') {
						$sql .= " LOWER(`description`) LIKE '%" . $this->db->escape(mb_strtolower((string)$filter[$key])) . "%'";
					} elseif ($key === 'filter_name') {
						$sql .= " LOWER(`name`) LIKE '%" . $this->db->escape(mb_strtolower((string)$filter[$key])) . "%'";
					} elseif ($key === 'filter_status') {
						$sql .= " `status` = '" . (int)$filter[$key] . "'";
					} else {
						$field = str_replace('filter_', '', $key);
						$sql .= " LOWER(`" . $field . "`) = '" . $this->db->escape(mb_strtolower((string)$filter[$key])) . "'";
					}
					$x++;
				}
			}
		}

		$sql .= " ORDER BY `group`, `rate_id` ASC";

		$query = $this->db->query($sql);

		return $query->rows;
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

	public function settings(): array {
		return [
			'rate_id'            => 0,
			'description'        => '',
			'status'             => 0,
			'sort_order'         => 1,
			'group'              => '',
			'tax_class_id'       => 0,
			'total_type'         => 0,
			'name'               => [],
			'shipping'           => [],
			'origin'             => '',
			'geocode_lat'        => 0.0,
			'geocode_lng'        => 0.0,
			'ocapps_cost'        => 0,
			'ocapps_requirement' => 0,
			'requirement_match'  => 'any',
			'requirement_cost'   => 'every',
			'requirements'       => [],
			'fail_method'        => 0,
			'date_added'         => '0000-00-00 00:00:00',
			'date_modified'      => '0000-00-00 00:00:00',
			'administrator'      => '',
		];
	}

	public function install(): void {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . $this->dbTable . "` (
			`rate_id` INT(11) NOT NULL AUTO_INCREMENT,
			`description` TEXT NOT NULL,
			`status` TINYINT(1) NOT NULL DEFAULT 0,
			`sort_order` INT(3) NOT NULL DEFAULT 0,
			`group` TEXT NOT NULL,
			`tax_class_id` INT(11) NOT NULL DEFAULT 0,
			`total_type` TINYINT(1) NOT NULL DEFAULT 0,
			`name` TEXT NOT NULL,
			`shipping` LONGTEXT NOT NULL,
			`origin` TEXT NOT NULL,
			`geocode_lat` DECIMAL(20,8) NOT NULL DEFAULT 0.00000000,
			`geocode_lng` DECIMAL(20,8) NOT NULL DEFAULT 0.00000000,
			`ocapps_cost` TINYINT(1) NOT NULL DEFAULT 0,
			`ocapps_requirement` TINYINT(1) NOT NULL DEFAULT 0,
			`requirement_match` VARCHAR(10) NOT NULL DEFAULT 'any',
			`requirement_cost` VARCHAR(10) NOT NULL DEFAULT 'every',
			`requirements` LONGTEXT NOT NULL,
			`fail_method` TINYINT(1) NOT NULL DEFAULT 0,
			`date_added` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			`date_modified` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			`administrator` VARCHAR(50) NOT NULL DEFAULT '',
			PRIMARY KEY (`rate_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
	}

	public function update(): array {
		$status = false;
		$log    = 'Success: The following updates have been completed:<br />';
		$customTable = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . $this->dbTable . "`");
		$customColumns = [];

		foreach ($customTable->rows as $result) {
			$customColumns[$result['Field']] = $result;
		}

		if ($customColumns) {
			if (!isset($customColumns['fail_method'])) {
				$this->db->query("ALTER TABLE `" . DB_PREFIX . $this->dbTable . "` ADD `fail_method` TINYINT(1) NOT NULL AFTER `requirements`");
				$status = true;
				$log   .= '[v1.3.0] Fail Method column added<br />';
			}
		}

		return [
			'status' => $status,
			'log'    => $log,
		];
	}

	public function uninstall(): void {
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . $this->dbTable . "`");
	}
}
