<?php
require_once __DIR__ . '/../includes/api_helpers.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	sendJSONResponse(false, null, 'Method not allowed', 405);
}

try {
	$data = getJSONInput();
	validateRequired($data, ['student_id', 'device_token']);

	$studentId = (int) $data['student_id'];
	$deviceToken = trim((string) $data['device_token']);
	$platform = isset($data['platform']) ? trim((string) $data['platform']) : 'unknown';

	if ($studentId <= 0) {
		sendJSONResponse(false, null, 'Invalid student ID', 400);
	}
	if ($deviceToken === '' || strlen($deviceToken) < 20) {
		sendJSONResponse(false, null, 'Invalid device token', 400);
	}

	$pdo = getDBConnection();
	$pdo->exec("CREATE TABLE IF NOT EXISTS student_device_tokens (
		id INT AUTO_INCREMENT PRIMARY KEY,
		student_id INT NOT NULL,
		device_token VARCHAR(512) NOT NULL,
		platform VARCHAR(32) NULL,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		last_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		UNIQUE KEY uniq_student_token (student_id, device_token),
		INDEX idx_student_active (student_id, is_active)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

	$stmt = $pdo->prepare("
		INSERT INTO student_device_tokens (student_id, device_token, platform, is_active)
		VALUES (?, ?, ?, 1)
		ON DUPLICATE KEY UPDATE
			platform = VALUES(platform),
			is_active = 1,
			last_seen_at = CURRENT_TIMESTAMP
	");
	$stmt->execute([$studentId, $deviceToken, $platform]);

	sendJSONResponse(true, ['registered' => true], 'Device token registered successfully');
} catch (Exception $e) {
	handleAPIError($e, 'Failed to register device token');
}
?>
