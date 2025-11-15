<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['student_number']) || !isset($data['password'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    $studentNumber = $data['student_number'];
    $password = $data['password'];
    
    $pdo = new PDO('mysql:host=localhost;dbname=smart_timetable', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare('SELECT * FROM students WHERE student_number = ?');
    $stmt->execute([$studentNumber]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Invalid student number or password']);
        exit;
    }
    
    // Verify password (assuming passwords are stored as plain text or hashed)
    if ($student['password'] !== $password && !password_verify($password, $student['password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid student number or password']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'student' => [
            'student_id' => (int)$student['student_id'],
            'student_number' => $student['student_number'],
            'full_name' => $student['full_name'],
            'email' => $student['email'],
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>

