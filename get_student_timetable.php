<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
    
    if ($studentId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
        exit;
    }
    
    $pdo = new PDO('mysql:host=localhost;dbname=smart_timetable', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
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
    
    echo json_encode([
        'success' => true,
        'sessions' => $sessions
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>

