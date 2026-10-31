#!/usr/bin/env bash
set -euo pipefail

app_path=/home/abhilash/propeller
config_path=/etc/apache2/conf-available/propeller-logistics.conf

if [ "$(id -u)" -ne 0 ]; then
    echo "Run: sudo bash $app_path/configure-apache.sh"
    exit 1
fi

if [ -e "$config_path" ] && ! cmp -s "$app_path/apache-propeller.conf" "$config_path"; then
    echo "An existing, different $config_path needs manual review; nothing changed."
    exit 1
fi

apt-get update
apt-get install -y php8.3-sqlite3 acl

# Permit Apache to reach this app without opening the home directory to everyone.
setfacl -m u:www-data:--x /home/abhilash
setfacl -m u:www-data:r-x "$app_path"
setfacl -R -m u:www-data:rwX "$app_path/storage" "$app_path/bootstrap/cache" "$app_path/database"
find "$app_path/storage" "$app_path/bootstrap/cache" "$app_path/database" -type d -exec setfacl -m d:u:www-data:rwx {} +

install -m 644 "$app_path/apache-propeller.conf" "$config_path"
a2enmod rewrite
a2enconf propeller-logistics
apachectl configtest
systemctl reload apache2

response_code=$(curl --silent --output /dev/null --write-out '%{http_code}' http://192.168.31.250/propeller/login)
if [ "$response_code" != 200 ]; then
    echo "Apache reloaded, but the login page returned HTTP $response_code. Check /var/log/apache2/error.log."
    exit 1
fi
echo "Login verified: http://192.168.31.250/propeller/login"
echo "Create your first administrator as your normal user:"
echo "cd $app_path && php artisan logistics:admin your@email.com"
