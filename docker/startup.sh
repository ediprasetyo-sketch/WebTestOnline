#!/bin/sh
set -eu

mkdir -p \
  /var/www/html/storage/backups \
  /var/www/html/storage/update_uploads \
  /var/www/html/storage/update_staging \
  /var/www/html/storage/uploads
chown -R www-data:www-data /var/www/html/storage || true

echo "[startup] Waiting for database..."
until php -r '
require "/var/www/html/config.php";
try { db()->query("SELECT 1"); exit(0); }
catch (Throwable $e) { fwrite(STDERR, $e->getMessage()."\n"); exit(1); }
'; do
  sleep 2
done

echo "[startup] Marking bundled schema migrations as baseline..."
php -r '
require "/var/www/html/config.php";
$pdo = db();
$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(64) PRIMARY KEY, checksum CHAR(64) NOT NULL, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$baseline = ["006_matrix_disc_questions","6.3.0","6.3.6","6.3.6.3","6.3.7","6.3.10","6.3.16","6.3.71"];
foreach ($baseline as $version) {
  $file = "/var/www/html/migrations/{$version}.sql";
  if (!is_file($file)) continue;
  $sql = trim((string)file_get_contents($file));
  $checksum = hash("sha256", $sql);
  $stmt = $pdo->prepare("INSERT IGNORE INTO schema_migrations(version, checksum) VALUES(?, ?)");
  $stmt->execute([$version, $checksum]);
}
'

echo "[startup] Creating default accounts when absent..."
php /var/www/html/seed.php

echo "[startup] Starting web server..."
exec "$@"
