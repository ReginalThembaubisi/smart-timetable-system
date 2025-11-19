<?php
require_once __DIR__ . '/includes/api_helpers.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(false, null, 'Method not allowed', 405);
}

try {
    $data = getJSONInput();
    validateRequired($data, ['student_id', 'current_password', 'new_password']);
    
    $studentId = (int)$data['student_id'];
    $currentPassword = $data['current_password'];
    $newPassword = $data['new_password'];
    
    // Validate new password strength
    if (strlen($newPassword) < 6) {
        sendJSONResponse(false, null, 'New password must be at least 6 characters long', 400);
    }
    
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare('SELECT password FROM students WHERE student_id = ?');
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        sendJSONResponse(false, null, 'Student not found', 404);
    }
    
    // Verify current password
    if (!verifyPassword($currentPassword, $student['password'])) {
        sendJSONResponse(false, null, 'Current password is incorrect', 401);
    }
    
    // Hash and update password
    $hashedPassword = hashPassword($newPassword);
    $stmt = $pdo->prepare('UPDATE students SET password = ? WHERE student_id = ?');
    $stmt->execute([$hashedPassword, $studentId]);
    
    logActivity('password_change', "Student ID: $studentId changed password", $studentId);
    
    sendJSONResponse(true, null, 'Password changed successfully');
    
} catch (Exception $e) {
    handleAPIError($e, 'Password change failed');
}
?>


