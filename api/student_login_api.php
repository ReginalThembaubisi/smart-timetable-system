<?php
require_once __DIR__ . '/../includes/api_helpers.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(false, null, 'Method not allowed', 405);
}

try {
    $data = getJSONInput();
    validateRequired($data, ['student_number', 'password']);

    $studentNumber = sanitize($data['student_number']);
    $password = $data['password'];

    $pdo = getDBConnection();

    $stmt = $pdo->prepare('SELECT * FROM students WHERE student_number = ?');
    $stmt->execute([$studentNumber]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        sendJSONResponse(false, null, 'Invalid student number or password', 401);
    }

    // Verify password (supports both hashed and plain text for migration)
    if (!verifyPassword($password, $student['password'])) {
        sendJSONResponse(false, null, 'Invalid student number or password', 401);
    }

    // If password is plain text, hash it for future use (migration)
    if (strlen($student['password']) < 60 || !password_verify($password, $student['password'])) {
        // Password appears to be plain text, hash it
        $hashedPassword = hashPassword($password);
        $updateStmt = $pdo->prepare('UPDATE students SET password = ? WHERE student_id = ?');
        $updateStmt->execute([$hashedPassword, $student['student_id']]);
    }

    sendJSONResponse(true, [
        'student' => [
            'student_id' => (int) $student['student_id'],
            'student_number' => $student['student_number'],
            'full_name' => $student['full_name'],
            'email' => $student['email'],
        ]
    ], 'Login successful');

} catch (Exception $e) {
    handleAPIError($e, 'Login failed');
}
?>