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

	// Ensure notifications table exists to avoid 500 on fresh setups
	try {
		$pdo->exec("
			CREATE TABLE IF NOT EXISTS exam_notifications (
				notification_id INT AUTO_INCREMENT PRIMARY KEY,
				student_id INT NOT NULL,
				exam_id INT NULL,
				title VARCHAR(255) NULL,
				message TEXT NULL,
				is_read TINYINT(1) NOT NULL DEFAULT 0,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				INDEX idx_student_id (student_id),
				INDEX idx_exam_id (exam_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
		");
	} catch (Throwable $t) {
		// If creation fails, still continue to attempt read; worst case, return empty list
	}
	
	try {
		$stmt = $pdo->prepare('
			SELECT n.*, e.exam_status
			FROM exam_notifications n
			LEFT JOIN exams e ON n.exam_id = e.exam_id
			WHERE n.student_id = ? AND n.is_read = 0
			ORDER BY n.created_at DESC
		');
		$stmt->execute([$studentId]);
		$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
	} catch (PDOException $pe) {
		// If table still doesn't exist or other DB error, return empty list gracefully
		error_log('Exam notifications query failed: ' . $pe->getMessage());
		$notifications = [];
	}
    
    sendJSONResponse(true, ['notifications' => $notifications], 'Notifications retrieved successfully');
    
} catch (Exception $e) {
    handleAPIError($e, 'Failed to retrieve notifications');
}
?>


