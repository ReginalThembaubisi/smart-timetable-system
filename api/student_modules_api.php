<?php
require_once __DIR__ . '/../includes/api_helpers.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSONResponse(false, null, 'Method not allowed', 405);
}

try {
    $studentId = isset($_GET['student_id']) ? (int) $_GET['student_id'] : 0;

    if ($studentId <= 0) {
        sendJSONResponse(false, null, 'Invalid student ID', 400);
    }

    $pdo = getDBConnection();

    $stmt = $pdo->prepare('
        SELECT m.*
        FROM modules m
        JOIN student_modules sm ON m.module_id = sm.module_id
        WHERE sm.student_id = ?
        ORDER BY m.module_code
    ');
    $stmt->execute([$studentId]);
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendJSONResponse(true, ['modules' => $modules], 'Modules retrieved successfully');

} catch (Exception $e) {
    handleAPIError($e, 'Failed to retrieve modules');
}
?>