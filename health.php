<?php
declare(strict_types=1);
require __DIR__ . '/includes/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!$pdo instanceof PDO) {
    http_response_code(503);
    echo json_encode(['status' => 'unavailable', 'database' => 'disconnected']);
    exit;
}

try {
    $pdo->query('SELECT 1');
    echo json_encode(['status' => 'ok', 'database' => 'connected']);
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode(['status' => 'unavailable', 'database' => 'disconnected']);
}
