<?php
/**
 * Shared PDO connection for Sakura Beaded Bags.
 * Safe to commit: credentials live only in ignored db.local.php.
 */
function sbb_db(): PDO
{
    static $connection = null;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $localConfig = __DIR__ . '/db.local.php';
    if (!is_file($localConfig)) {
        throw new RuntimeException('Missing includes/db.local.php. Copy db.local.php.example and edit the values.');
    }
    $config = require $localConfig;
    if (!is_array($config)) {
        throw new RuntimeException('Database configuration must return an array.');
    }

    $host = $config['host'] ?? '127.0.0.1';
    $port = (int) ($config['port'] ?? 3306);
    $database = $config['database'] ?? 'sakura_beaded_bags';
    $username = $config['username'] ?? '';
    $password = $config['password'] ?? '';

    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
    $connection = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $connection;
}
