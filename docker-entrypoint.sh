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

# Execute the CMD
exec "$@"
