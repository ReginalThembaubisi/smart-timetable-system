<?php
require_once __DIR__ . '/includes/api_helpers.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSONResponse(false, null, 'Method not allowed', 405);
}

try {
    $studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
    
    if ($studentId <= 0) {
        sendJSONResponse(false, null, 'Invalid student ID', 400);
    }
    
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare('
        SELECT s.*, m.module_name, m.module_code, l.lecturer_name, v.venue_name
        FROM sessions s
        JOIN student_modules sm ON s.module_id = sm.module_id
        LEFT JOIN modules m ON s.module_id = m.module_id
        LEFT JOIN lecturers l ON s.lecturer_id = l.lecturer_id
        LEFT JOIN venues v ON s.venue_id = v.venue_id
        WHERE sm.student_id = ?
        ORDER BY s.day_of_week, s.start_time
    ');
    $stmt->execute([$studentId]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendJSONResponse(true, ['sessions' => $sessions], 'Timetable retrieved successfully');
    
} catch (Exception $e) {
    handleAPIError($e, 'Failed to retrieve timetable');
}
?>


