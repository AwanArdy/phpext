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

# Daftarkan Advanced Shipping ke OpenCart 4 (extension_install + extension_path).
# OC4 TIDAK scan filesystem — daftar Shipping hanya dari tabel extension_path.
# Tanpa ini, file di extension/advancedshipping/ tidak akan muncul di admin.
register_advanced_shipping() {
    local EXT_DIR="/var/www/html/extension/advancedshipping"
    local CODE="advancedshipping"

    if [ ! -f "${EXT_DIR}/install.json" ]; then
        echo "⚠️  Advanced Shipping belum di-mount (${EXT_DIR}), skip registrasi."
        return 0
    fi

    if [ ! -f /var/www/html/config.php ]; then
        return 0
    fi

    echo "🔌 Mendaftarkan Advanced Shipping ke database OpenCart..."

    php -r '
$extDir = "/var/www/html/extension/advancedshipping";
$code = "advancedshipping";

// Load DB config from OpenCart config.php
$config = file_get_contents("/var/www/html/config.php");
preg_match("/define\(\s*'\''DB_HOSTNAME'\''\s*,\s*'\''([^'\'']*)'\''/", $config, $h);
preg_match("/define\(\s*'\''DB_USERNAME'\''\s*,\s*'\''([^'\'']*)'\''/", $config, $u);
preg_match("/define\(\s*'\''DB_PASSWORD'\''\s*,\s*'\''([^'\'']*)'\''/", $config, $p);
preg_match("/define\(\s*'\''DB_DATABASE'\''\s*,\s*'\''([^'\'']*)'\''/", $config, $d);
preg_match("/define\(\s*'\''DB_PORT'\''\s*,\s*'\''([^'\'']*)'\''/", $config, $port);
preg_match("/define\(\s*'\''DB_PREFIX'\''\s*,\s*'\''([^'\'']*)'\''/", $config, $pref);

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
$q = $m->query("SELECT extension_install_id FROM `{$prefix}extension_install` WHERE `code` = '" . $m->real_escape_string($code) . "' LIMIT 1");
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
    $m->query(
        "INSERT INTO `{$prefix}extension_install` SET " .
        "extension_id = 0, " .
        "extension_download_id = 0, " .
        "name = '" . $m->real_escape_string($name) . "', " .
        "code = '" . $m->real_escape_string($code) . "', " .
        "version = '" . $m->real_escape_string($version) . "', " .
        "author = '" . $m->real_escape_string($author) . "', " .
        "link = '" . $m->real_escape_string($link) . "', " .
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
    $m->query(
        "INSERT INTO `{$prefix}extension_path` SET " .
        "extension_install_id = {$installId}, " .
        "path = '" . $m->real_escape_string($rel) . "'"
    );
    $count++;
}
echo "Registered {$count} extension paths.\n";

// Critical path must exist for Shipping list
$need = $code . "/admin/controller/shipping/advancedshipping.php";
$chk = $m->query("SELECT path FROM `{$prefix}extension_path` WHERE path = '" . $m->real_escape_string($need) . "' LIMIT 1");
if (!$chk || $chk->num_rows === 0) {
    fwrite(STDERR, "ERROR: missing critical path {$need}\n");
    exit(1);
}
echo "OK: Shipping list will include Advanced Shipping.\n";
'

    if [ $? -eq 0 ]; then
        echo "✅ Advanced Shipping terdaftar."
    else
        echo "⚠️  Gagal mendaftarkan Advanced Shipping (cek log di atas)."
    fi
}

register_advanced_shipping

# Execute the CMD
exec "$@"
