<?php
declare(strict_types=1);
// DB_* works on any host. Railway's MySQL variables are accepted as fallbacks.
$dbHost = getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: '127.0.0.1');
$dbPort = getenv('DB_PORT') ?: (getenv('MYSQLPORT') ?: '8889');
$dbName = getenv('DB_NAME') ?: (getenv('MYSQLDATABASE') ?: 'db_northwind');
$dbUser = getenv('DB_USER') ?: (getenv('MYSQLUSER') ?: 'root');
$dbPass = getenv('DB_PASS') ?: (getenv('MYSQLPASSWORD') ?: 'root');
$pdo = null;
try {
    $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $exception) {
    // The interface shows a setup hint; keep database credentials out of page output.
}
