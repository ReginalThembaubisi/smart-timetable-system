<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=smart_timetable', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS study_sessions (
            session_id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            module_name VARCHAR(255),
            day_of_week VARCHAR(20) NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            duration INT,
            session_type VARCHAR(50),
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
            INDEX idx_student_id (student_id),
            INDEX idx_day_time (day_of_week, start_time)
        )
    ");
    
    $method = $_SERVER['REQUEST_METHOD'];
    $data = json_decode(file_get_contents('php://input'), true);
    
    switch ($method) {
        case 'GET':
            $studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
            if ($studentId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
                exit;
            }
            
            $stmt = $pdo->prepare('SELECT * FROM study_sessions WHERE student_id = ? ORDER BY day_of_week, start_time');
            $stmt->execute([$studentId]);
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'sessions' => $sessions]);
            break;
            
        case 'POST':
            if (!isset($data['student_id']) || !isset($data['title']) || !isset($data['day_of_week']) || 
                !isset($data['start_time']) || !isset($data['end_time'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                exit;
            }
            
            $stmt = $pdo->prepare('
                INSERT INTO study_sessions (student_id, title, module_name, day_of_week, start_time, end_time, duration, session_type, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $data['student_id'],
                $data['title'],
                $data['module_name'] ?? null,
                $data['day_of_week'],
                $data['start_time'],
                $data['end_time'],
                $data['duration'] ?? null,
                $data['session_type'] ?? null,
                $data['notes'] ?? null
            ]);
            
            $sessionId = $pdo->lastInsertId();
            echo json_encode(['success' => true, 'session_id' => $sessionId, 'message' => 'Study session created']);
            break;
            
        case 'PUT':
            if (!isset($data['session_id']) || !isset($data['student_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                exit;
            }
            
            $stmt = $pdo->prepare('
                UPDATE study_sessions 
                SET title = ?, module_name = ?, day_of_week = ?, start_time = ?, end_time = ?, 
                    duration = ?, session_type = ?, notes = ?
                WHERE session_id = ? AND student_id = ?
            ');
            $stmt->execute([
                $data['title'] ?? '',
                $data['module_name'] ?? null,
                $data['day_of_week'] ?? '',
                $data['start_time'] ?? '',
                $data['end_time'] ?? '',
                $data['duration'] ?? null,
                $data['session_type'] ?? null,
                $data['notes'] ?? null,
                $data['session_id'],
                $data['student_id']
            ]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Study session updated']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Session not found']);
            }
            break;
            
        case 'DELETE':
            $sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : (isset($data['session_id']) ? (int)$data['session_id'] : 0);
            $studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : (isset($data['student_id']) ? (int)$data['student_id'] : 0);
            
            if ($sessionId <= 0 || $studentId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                exit;
            }
            
            $stmt = $pdo->prepare('DELETE FROM study_sessions WHERE session_id = ? AND student_id = ?');
            $stmt->execute([$sessionId, $studentId]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Study session deleted']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Session not found']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>

