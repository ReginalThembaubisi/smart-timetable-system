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
    
	// Some databases may not yet have the exam_status column; detect and select conditionally.
	$hasExamStatus = false;
	try {
		$col = $pdo->query("SHOW COLUMNS FROM exams LIKE 'exam_status'")->fetch(PDO::FETCH_ASSOC);
		$hasExamStatus = (bool)$col;
	} catch (Throwable $t) {
		$hasExamStatus = false;
	}
	
	$selectStatus = $hasExamStatus ? 'e.exam_status,' : '';
	// Match exams by module_id OR by module code partial match
	// This handles cases where student modules have combined codes like "APD302_1_S2, DICT312_1_S2"
	// and exams are for individual modules like "APD302"
	$sql = "
		SELECT DISTINCT e.exam_id, e.module_id, e.venue_id, e.exam_date, e.exam_time, e.duration" . ($hasExamStatus ? ", e.exam_status" : "") . "
			   , m.module_name, m.module_code, v.venue_name
		FROM exams e
		LEFT JOIN modules m ON e.module_id = m.module_id
		LEFT JOIN venues v ON e.venue_id = v.venue_id
		WHERE EXISTS (
			SELECT 1 FROM student_modules sm
			JOIN modules smm ON sm.module_id = smm.module_id
			WHERE sm.student_id = ?
			AND (
				e.module_id = sm.module_id
				OR
				(
					m.module_code IS NOT NULL 
					AND (
						smm.module_code LIKE CONCAT('%', SUBSTRING_INDEX(m.module_code, '_', 1), '%')
						OR smm.module_code LIKE CONCAT('%', m.module_code, '%')
					)
				)
			)
		)
		ORDER BY e.exam_date, e.exam_time
	";
	
	$stmt = $pdo->prepare($sql);
    $stmt->execute([$studentId]);
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendJSONResponse(true, ['exams' => $exams], 'Exam timetable retrieved successfully');
    
} catch (Exception $e) {
    handleAPIError($e, 'Failed to retrieve exam timetable');
}
?>


