#!/usr/bin/env bash
# Prepare isolated V2 on the existing AWS host. Does NOT switch Apache or edit V1.
set -euo pipefail
umask 077
if [[ $EUID -ne 0 ]]; then echo 'Run with sudo.' >&2; exit 1; fi
src=$(cd -- "$(dirname -- "$0")/.." && pwd)
v1=/var/www/sbb
v2=/var/www/sbb-v2
db=sakura_beaded_bags_v2
dbuser=sbb_v2_app
backup=/var/backups/sbb/pre-v2-$(date +%Y%m%d-%H%M%S)
[[ -f "$v1/includes/config.php" && -f "$v1/includes/db.local.php" ]] || { echo 'V1 configuration missing; stop.' >&2; exit 1; }
[[ ! -e "$v2" ]] || { echo 'V2 directory already exists; stop without overwriting.' >&2; exit 1; }
if [[ $(mysql -Nse "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='$db'") != 0 ]]; then echo 'V2 database already exists; stop without overwriting.' >&2; exit 1; fi
if [[ $(mysql -Nse "SELECT COUNT(*) FROM mysql.user WHERE user='$dbuser'") != 0 ]]; then echo 'V2 database account already exists; stop without overwriting.' >&2; exit 1; fi
echo 'Checking capacity...'
free -h; df -h /
[[ $(df -Pk / | awk 'NR==2{print $4}') -ge 1048576 ]] || { echo 'Less than 1 GiB free; stop.' >&2; exit 1; }
echo 'Backing up V1 (live transactional snapshot)...'
mkdir -p "$backup"
mysqldump --single-transaction --no-tablespaces --triggers sakura_beaded_bags > "$backup/database.sql"
tar -czf "$backup/application.tar.gz" -C /var/www sbb
tar -czf "$backup/apache-config.tar.gz" -C /etc/apache2 sites-available sites-enabled
test -s "$backup/database.sql" && gzip -t "$backup/application.tar.gz" && gzip -t "$backup/apache-config.tar.gz"
echo "Backup saved: $backup"
echo 'Linting V2 PHP source...'
find "$src/admin" "$src/includes" "$src/scripts" -name '*.php' -print0 | xargs -0 -r -n1 php -l > "$backup/php-lint.txt"
echo 'Creating separate empty V2 database...'
mysql -e "CREATE DATABASE $db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql "$db" < "$src/database/schema.sql"
work=$(mktemp -d /var/tmp/sbb-v2-migrate.XXXXXX)
trap 'rm -rf -- "$work"' EXIT
cp -a "$src/includes" "$src/scripts" "$work/"
printf "<?php return ['host'=>'localhost','port'=>3306,'database'=>'%s','username'=>'root','password'=>''];\n" "$db" > "$work/includes/db.local.php"
SBB_ALLOW_CLOUD_V2=1 php "$work/scripts/setup_v2_local.php"
echo 'Creating separate runtime database account...'
pw=$(openssl rand -hex 24)
mysql <<SQL
CREATE USER '$dbuser'@'127.0.0.1' IDENTIFIED BY '$pw';
GRANT SELECT, INSERT, UPDATE, DELETE ON $db.* TO '$dbuser'@'127.0.0.1';
SQL
echo 'Preparing private V2 application directory...'
install -d -m 0750 -o root -g www-data "$v2"
cp -a "$src/admin" "$src/assets" "$src/includes" "$v2/"
install -m 0640 -o root -g www-data "$v1/includes/config.php" "$v2/includes/config.php"
printf "<?php return ['host'=>'127.0.0.1','port'=>3306,'database'=>'%s','username'=>'%s','password'=>'%s'];\n" "$db" "$dbuser" "$pw" > "$v2/includes/db.local.php"
unset pw
chown -R root:www-data "$v2"
find "$v2" -type d -exec chmod 0750 {} +
find "$v2" -type f -exec chmod 0640 {} +
install -d -o www-data -g www-data -m 0750 "$v2/uploads" "$v2/uploads/product-photos"
echo 'Verifying runtime database and authentication mode...'
sudo -u www-data php -r 'require "/var/www/sbb-v2/includes/db.php"; require "/var/www/sbb-v2/includes/config.php"; if (DEMO_MODE || USE_SAMPLE_DATA) { fwrite(STDERR,"Unsafe demo configuration; stop.\n"); exit(1); } $p=sbb_db(); if($p->query("SELECT DATABASE()")->fetchColumn()!=="sakura_beaded_bags_v2")exit(1); echo "Database: ".$p->query("SELECT DATABASE()")->fetchColumn().PHP_EOL; echo "Base tables: ".$p->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type=\"BASE TABLE\"")->fetchColumn().PHP_EOL; echo "Orders: ".$p->query("SELECT COUNT(*) FROM customer_orders")->fetchColumn().PHP_EOL; echo "Demo mode: disabled".PHP_EOL;'
echo 'SUCCESS: V2 prepared. V1 is still live. No Apache change made.'
echo "Recovery backup: $backup"
