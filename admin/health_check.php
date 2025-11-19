<?php
header('Content-Type: application/json');
http_response_code(200);
$status = 'ok';
try {
	require_once __DIR__ . '/config.php';
	require_once __DIR__ . '/../includes/database.php';
	$pdo = Database::getInstance()->getConnection();
	$pdo->query('SELECT 1');
} catch (Throwable $e) {
	$status = 'degraded';
	error_log("Health check failed: " . $e->getMessage());
}
echo json_encode([
	'status' => $status,
	'time' => gmdate('c')
]);

