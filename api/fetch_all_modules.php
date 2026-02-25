<?php
require_once __DIR__ . '/../includes/api_helpers.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSONResponse(false, null, 'Method not allowed', 405);
}

try {
    $pdo = getDBConnection();

    $stmt = $pdo->query('SELECT * FROM modules ORDER BY module_code');
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendJSONResponse(true, ['modules' => $modules], 'Modules retrieved successfully');

} catch (Exception $e) {
    handleAPIError($e, 'Failed to retrieve modules');
}
?>