<?php
require_once __DIR__ . '/includes/api_helpers.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(false, null, 'Method not allowed', 405);
}

try {
    $data = getJSONInput();
    validateRequired($data, ['student_id', 'full_name', 'email']);
    
    $studentId = (int)$data['student_id'];
    $fullName = sanitize($data['full_name']);
    $email = sanitize($data['email']);
    
    // Validate email
    if (!validateEmail($email)) {
        sendJSONResponse(false, null, 'Invalid email format', 400);
    }
    
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare('UPDATE students SET full_name = ?, email = ? WHERE student_id = ?');
    $stmt->execute([$fullName, $email, $studentId]);
    
    if ($stmt->rowCount() > 0) {
        logActivity('profile_update', "Student ID: $studentId updated profile", $studentId);
        sendJSONResponse(true, null, 'Profile updated successfully');
    } else {
        sendJSONResponse(false, null, 'No changes made', 200);
    }
    
} catch (Exception $e) {
    handleAPIError($e, 'Profile update failed');
}
?>


