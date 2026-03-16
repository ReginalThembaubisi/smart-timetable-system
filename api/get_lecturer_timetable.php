<?php
require_once __DIR__ . '/../includes/api_helpers.php';
require_once __DIR__ . '/../includes/lecturer_system.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSONResponse(false, null, 'Method not allowed', 405);
}

try {
    $lecturerId = isset($_GET['lecturer_id']) ? (int) $_GET['lecturer_id'] : 0;
    if ($lecturerId <= 0) {
        sendJSONResponse(false, null, 'Invalid lecturer ID', 400);
    }

    $pdo = getDBConnection();
    ensureLecturerSystemTables($pdo);

    $lecturerStmt = $pdo->prepare("
        SELECT lecturer_id, lecturer_name, email, lecturer_code
        FROM lecturers
        WHERE lecturer_id = ?
        LIMIT 1
    ");
    $lecturerStmt->execute([$lecturerId]);
    $lecturer = $lecturerStmt->fetch(PDO::FETCH_ASSOC);
    if (!$lecturer) {
        sendJSONResponse(false, null, 'Lecturer not found', 404);
    }

    $timetableStmt = $pdo->prepare("
        SELECT
            s.session_id,
            s.day_of_week,
            s.start_time,
            s.end_time,
            m.module_id,
            m.module_code,
            m.module_name,
            v.venue_name
        FROM sessions s
        JOIN modules m ON m.module_id = s.module_id
        LEFT JOIN venues v ON v.venue_id = s.venue_id
        WHERE s.lecturer_id = ?
        ORDER BY FIELD(s.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), s.start_time
    ");
    $timetableStmt->execute([$lecturerId]);
    $sessions = $timetableStmt->fetchAll(PDO::FETCH_ASSOC);

    sendJSONResponse(true, [
        'lecturer' => $lecturer,
        'sessions' => $sessions,
    ], 'Lecturer timetable retrieved successfully');
} catch (Exception $e) {
    handleAPIError($e, 'Failed to retrieve lecturer timetable');
}
?>
