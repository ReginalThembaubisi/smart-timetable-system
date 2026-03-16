<?php
require_once __DIR__ . '/../includes/api_helpers.php';
require_once __DIR__ . '/../includes/lecturer_system.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
	sendJSONResponse(false, null, 'Method not allowed', 405);
}

try {
	$studentId = isset($_GET['student_id']) ? (int) $_GET['student_id'] : 0;

	if ($studentId <= 0) {
		sendJSONResponse(false, null, 'Invalid student ID', 400);
	}

	$pdo = getDBConnection();
	ensureLecturerSystemTables($pdo);

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
		$examStmt = $pdo->prepare("
			SELECT
				n.notification_id,
				'Exam' AS type,
				COALESCE(n.title, 'Exam update') AS title,
				COALESCE(n.message, 'An exam update is available') AS message,
				COALESCE(e.exam_status, 'final') AS timetable_status,
				'Exam Timetable' AS timetable_title,
				n.created_at
			FROM exam_notifications n
			LEFT JOIN exams e ON n.exam_id = e.exam_id
			WHERE n.student_id = ? AND n.is_read = 0
		");
		$examStmt->execute([$studentId]);
		$examNotifications = $examStmt->fetchAll(PDO::FETCH_ASSOC);

		$assessmentStmt = $pdo->prepare("
			SELECT
				(notification_id + 1000000000) AS notification_id,
				'Assessment' AS type,
				title,
				message,
				'published' AS timetable_status,
				'Lecturer Planner' AS timetable_title,
				COALESCE(sent_at, created_at) AS created_at
			FROM student_assessment_notifications
			WHERE student_id = ?
			  AND is_sent = 1
			  AND is_read = 0
		");
		$assessmentStmt->execute([$studentId]);
		$assessmentNotifications = $assessmentStmt->fetchAll(PDO::FETCH_ASSOC);

		$notifications = array_merge($examNotifications, $assessmentNotifications);
		usort($notifications, function ($a, $b) {
			return strcmp((string) $b['created_at'], (string) $a['created_at']);
		});
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