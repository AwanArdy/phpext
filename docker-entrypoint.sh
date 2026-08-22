#!/bin/bash
set -e

# Seed system/storage dari image jika volume masih kosong
if [ -d /var/www/html/storage-seed ] && [ ! -f /var/www/html/system/storage/.seeded ]; then
    echo "📦 Menyalin storage seed ke volume..."
    mkdir -p /var/www/html/system/storage
    cp -a /var/www/html/storage-seed/. /var/www/html/system/storage/
    touch /var/www/html/system/storage/.seeded
    chown -R www-data:www-data /var/www/html/system/storage
fi

# Wait for MySQL to be ready
echo "⏳ Menunggu MySQL siap..."
DB_PORT="${MYSQL_PORT:-3306}"
until php -r '
$m = @new mysqli(getenv("MYSQL_HOST"), getenv("MYSQL_USER"), getenv("MYSQL_PASSWORD"), getenv("MYSQL_DATABASE"), (int)getenv("MYSQL_PORT") ?: 3306);
exit($m && !$m->connect_errno ? 0 : 1);
'; do
    sleep 2
done
echo "✅ MySQL siap!"

# Check if OpenCart is already installed
if [ ! -f /var/www/html/.installed ]; then
    echo "🚀 Menginstall OpenCart 4..."

    # Pastikan config writable (file kosong dari config-dist)
    touch /var/www/html/config.php /var/www/html/admin/config.php
    chmod 0777 /var/www/html/config.php /var/www/html/admin/config.php
    chmod -R 0777 /var/www/html/system/storage /var/www/html/image

    # Run OpenCart CLI installer
    php install/cli_install.php install \
        --db_hostname "${MYSQL_HOST}" \
        --db_username "${MYSQL_USER}" \
        --db_password "${MYSQL_PASSWORD}" \
        --db_database "${MYSQL_DATABASE}" \
        --db_driver mysqli \
        --db_port "${MYSQL_PORT:-3306}" \
        --db_prefix "oc_" \
        --username admin \
        --password admin123 \
        --email admin@example.com \
        --http_server "http://localhost:8080/"

    # Remove install directory (security)
    rm -rf /var/www/html/install

    # Fix permissions after install
    chown -R www-data:www-data /var/www/html
    chmod 0644 /var/www/html/config.php /var/www/html/admin/config.php

    touch /var/www/html/.installed
    echo ""
    echo "╔══════════════════════════════════════════════════════════════╗"
    echo "║            ✅ OpenCart 4 berhasil diinstall!                ║"
    echo "║                                                            ║"
    echo "║  🌐 Toko:     http://localhost:8080                        ║"
    echo "║  🔧 Admin:    http://localhost:8080/admin                  ║"
    echo "║  📊 PMA:      http://localhost:8081                        ║"
    echo "║                                                            ║"
    echo "║  👤 Admin Login:                                           ║"
    echo "║     Username: admin                                        ║"
    echo "║     Password: admin123                                     ║"
    echo "║                                                            ║"
    echo "╚══════════════════════════════════════════════════════════════╝"
    echo ""
else
    echo "✅ OpenCart sudah terinstall, skip instalasi."
fi

# Daftarkan Advanced Shipping ke OpenCart 4 (extension_install + extension_path)
# lalu auto-install penuh: baris di tabel `extension` (agar tombol Edit tidak
# tampil pudar/disabled), tabel rate, setting default, dan permission admin.
# OC4 TIDAK scan filesystem — daftar Shipping hanya dari tabel extension_path.
# Catatan: PHP ditulis via heredoc ke file temp agar single-quote di SQL tidak
# merusak quoting bash (bug sebelumnya membuat container crash-loop).
register_advanced_shipping() {
    local EXT_DIR="/var/www/html/extension/advancedshipping"
    local CODE="advancedshipping"
    local REGISTER_PHP="/tmp/register_advanced_shipping.php"

    if [ ! -f "${EXT_DIR}/install.json" ]; then
        echo "⚠️  Advanced Shipping belum di-mount (${EXT_DIR}), skip registrasi."
        return 0
    fi

    if [ ! -f /var/www/html/config.php ]; then
        return 0
    fi

    echo "🔌 Mendaftarkan Advanced Shipping ke database OpenCart..."

    cat > "${REGISTER_PHP}" <<'PHP'
<?php
$extDir = "/var/www/html/extension/advancedshipping";
$code = "advancedshipping";

// Load DB config from OpenCart config.php
$config = file_get_contents("/var/www/html/config.php");
preg_match("/define\(\s*'DB_HOSTNAME'\s*,\s*'([^']*)'/", $config, $h);
preg_match("/define\(\s*'DB_USERNAME'\s*,\s*'([^']*)'/", $config, $u);
preg_match("/define\(\s*'DB_PASSWORD'\s*,\s*'([^']*)'/", $config, $p);
preg_match("/define\(\s*'DB_DATABASE'\s*,\s*'([^']*)'/", $config, $d);
preg_match("/define\(\s*'DB_PORT'\s*,\s*'([^']*)'/", $config, $port);
preg_match("/define\(\s*'DB_PREFIX'\s*,\s*'([^']*)'/", $config, $pref);

$host = $h[1] ?? getenv("MYSQL_HOST") ?: "db";
$user = $u[1] ?? getenv("MYSQL_USER") ?: "opencart";
$pass = $p[1] ?? getenv("MYSQL_PASSWORD") ?: "opencart123";
$dbn  = $d[1] ?? getenv("MYSQL_DATABASE") ?: "opencart";
$dbport = $port[1] ?? getenv("MYSQL_PORT") ?: "3306";
$prefix = $pref[1] ?? "oc_";

$m = @new mysqli($host, $user, $pass, $dbn, (int)$dbport);
if ($m->connect_errno) {
    fwrite(STDERR, "DB connect failed: {$m->connect_error}\n");
    exit(1);
}
$m->set_charset("utf8mb4");

// Skip if already registered
$codeEsc = $m->real_escape_string($code);
$q = $m->query("SELECT extension_install_id FROM `{$prefix}extension_install` WHERE `code` = '{$codeEsc}' LIMIT 1");
if ($q && $q->num_rows > 0) {
    $row = $q->fetch_assoc();
    $installId = (int)$row["extension_install_id"];
    echo "Already registered (extension_install_id={$installId}), refreshing paths...\n";
    $m->query("DELETE FROM `{$prefix}extension_path` WHERE extension_install_id = {$installId}");
} else {
    $name = "Advanced Shipping";
    $version = "2.0.0";
    $author = "OpenCart Addons";
    $link = "";
    if (is_file($extDir . "/install.json")) {
        $meta = json_decode(file_get_contents($extDir . "/install.json"), true) ?: [];
        $name = $meta["name"] ?? $name;
        $version = $meta["version"] ?? $version;
        $author = $meta["author"] ?? $author;
        $link = $meta["link"] ?? $link;
    }
    // Schema OC 4.0.x: extension_id, extension_download_id, name, code, version, author, link, status, date_added
    $nameEsc = $m->real_escape_string($name);
    $versionEsc = $m->real_escape_string($version);
    $authorEsc = $m->real_escape_string($author);
    $linkEsc = $m->real_escape_string($link);
    $m->query(
        "INSERT INTO `{$prefix}extension_install` SET " .
        "extension_id = 0, " .
        "extension_download_id = 0, " .
        "name = '{$nameEsc}', " .
        "code = '{$codeEsc}', " .
        "version = '{$versionEsc}', " .
        "author = '{$authorEsc}', " .
        "link = '{$linkEsc}', " .
        "status = 1, " .
        "date_added = NOW()"
    );
    if ($m->error) {
        fwrite(STDERR, "Insert extension_install failed: {$m->error}\n");
        exit(1);
    }
    $installId = (int)$m->insert_id;
    echo "Registered extension_install_id={$installId}\n";
}

// Register all relative paths under extension/advancedshipping/
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($extDir, FilesystemIterator::SKIP_DOTS)
);
$count = 0;
foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $full = $file->getPathname();
    // path format expected by OC4: advancedshipping/admin/controller/shipping/advancedshipping.php
    $rel = $code . "/" . ltrim(substr($full, strlen($extDir)), "/");
    $relEsc = $m->real_escape_string($rel);
    $m->query(
        "INSERT INTO `{$prefix}extension_path` SET " .
        "extension_install_id = {$installId}, " .
        "path = '{$relEsc}'"
    );
    $count++;
}
echo "Registered {$count} extension paths.\n";

// Critical path must exist for Shipping list
$need = $code . "/admin/controller/shipping/advancedshipping.php";
$needEsc = $m->real_escape_string($need);
$chk = $m->query("SELECT path FROM `{$prefix}extension_path` WHERE path = '{$needEsc}' LIMIT 1");
if (!$chk || $chk->num_rows === 0) {
    fwrite(STDERR, "ERROR: missing critical path {$need}\n");
    exit(1);
}
echo "OK: Shipping list will include Advanced Shipping.\n";

// --- Auto-install (sama seperti klik Install di Extensions -> Shipping) ---
// 1) Baris di tabel `extension` membuat tombol Edit aktif
//    (tanpa ini, baris tampil pudar/blurred seperti disabled).
$qExt = $m->query("SELECT * FROM `{$prefix}extension` WHERE `type` = 'shipping' AND `code` = '{$codeEsc}' LIMIT 1");
if ($qExt && $qExt->num_rows === 0) {
    $m->query(
        "INSERT INTO `{$prefix}extension` SET " .
        "`extension` = '{$codeEsc}', " .
        "`type` = 'shipping', " .
        "`code` = '{$codeEsc}'"
    );
    if ($m->error) {
        fwrite(STDERR, "Insert extension failed: {$m->error}\n");
        exit(1);
    }
    echo "Auto-install: shipping extension row added.\n";
}

// 2) Buat tabel rate (skema sama dengan model install()).
$m->query(
    "CREATE TABLE IF NOT EXISTS `{$prefix}advanced_shipping` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

// 3) Setting default dari controller install() (flag backup)
//    + sort_order agar kolom "Sort Order" di Extensions -> Shipping tidak kosong.
$settingDefaults = [
    "shipping_{$codeEsc}_backup"     => '1',
    "shipping_{$codeEsc}_sort_order" => '1',
];
foreach ($settingDefaults as $sKey => $sVal) {
    $sKeyEsc = $m->real_escape_string($sKey);
    $qSet = $m->query("SELECT * FROM `{$prefix}setting` WHERE `code` = 'shipping_{$codeEsc}' AND `key` = '{$sKeyEsc}' LIMIT 1");
    if ($qSet && $qSet->num_rows === 0) {
        $m->query(
            "INSERT INTO `{$prefix}setting` SET " .
            "`store_id` = 0, " .
            "`code` = 'shipping_{$codeEsc}', " .
            "`key` = '{$sKeyEsc}', " .
            "`value` = '{$sVal}', " .
            "`serialized` = 0"
        );
        echo "Auto-install: setting {$sKey} = {$sVal} seeded.\n";
    }
}

// 4) Permission access+modify untuk admin (user_group_id = 1),
//    sama dengan model_user_user_group->addPermission().
$route = 'extension/' . $code . '/shipping/' . $code;
$qGroup = $m->query("SELECT * FROM `{$prefix}user_group` WHERE `user_group_id` = '1' LIMIT 1");
if ($qGroup && $qGroup->num_rows > 0) {
    $groupRow  = $qGroup->fetch_assoc();
    $perm      = json_decode($groupRow['permission'] ?? '', true);
    if (!is_array($perm)) {
        $perm = [];
    }
    $changed = false;
    foreach (['access', 'modify'] as $type) {
        if (!isset($perm[$type]) || !is_array($perm[$type])) {
            $perm[$type] = [];
        }
        if (!in_array($route, $perm[$type], true)) {
            $perm[$type][] = $route;
            $changed = true;
        }
    }
    if ($changed) {
        $permEsc = $m->real_escape_string((string)json_encode($perm));
        $m->query("UPDATE `{$prefix}user_group` SET `permission` = '{$permEsc}' WHERE `user_group_id` = '1'");
        echo "Auto-install: permissions granted for {$route}.\n";
    }
}

echo "OK: Advanced Shipping fully installed.\n";
PHP

    # Jangan biarkan gagal registrasi menghentikan Apache (set -e).
    if php "${REGISTER_PHP}"; then
        echo "✅ Advanced Shipping terdaftar."
    else
        echo "⚠️  Gagal mendaftarkan Advanced Shipping (cek log di atas)."
    fi
    rm -f "${REGISTER_PHP}"
}

register_advanced_shipping

# Execute the CMD
exec "$@"
