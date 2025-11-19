<?php
require_once __DIR__ . '/includes/api_helpers.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(false, null, 'Method not allowed', 405);
}

try {
    $data = getJSONInput();
    validateRequired($data, ['notification_id', 'student_id']);
    
    $notificationId = (int)$data['notification_id'];
    $studentId = (int)$data['student_id'];
    
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare('UPDATE exam_notifications SET is_read = 1 WHERE notification_id = ? AND student_id = ?');
    $stmt->execute([$notificationId, $studentId]);
    
    if ($stmt->rowCount() > 0) {
        sendJSONResponse(true, null, 'Notification marked as read');
    } else {
        sendJSONResponse(false, null, 'Notification not found', 404);
    }
    
} catch (Exception $e) {
    handleAPIError($e, 'Failed to mark notification as read');
}
?>


