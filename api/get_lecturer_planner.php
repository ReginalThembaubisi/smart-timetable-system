<?php
require_once __DIR__ . '/../includes/api_helpers.php';
require_once __DIR__ . '/../includes/lecturer_system.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSONResponse(false, null, 'Method not allowed', 405);
}

try {
    $lecturerId = isset($_GET['lecturer_id']) ? (int) $_GET['lecturer_id'] : 0;
    if ($lecturerId <= 0) {
        sendJSONResponse(false, null, 'Invalid lecturer ID', 400);
    }

    $pdo = getDBConnection();
    ensureLecturerSystemTables($pdo);

    $lecturerStmt = $pdo->prepare("
        SELECT lecturer_id, lecturer_name, email
        FROM lecturers
        WHERE lecturer_id = ?
        LIMIT 1
    ");
    $lecturerStmt->execute([$lecturerId]);
    $lecturer = $lecturerStmt->fetch(PDO::FETCH_ASSOC);
    if (!$lecturer) {
        sendJSONResponse(false, null, 'Lecturer not found', 404);
    }

    $timetableStmt = $pdo->prepare("
        SELECT
            s.session_id,
            s.day_of_week,
            s.start_time,
            s.end_time,
            m.module_id,
            m.module_code,
            m.module_name,
            v.venue_name
        FROM sessions s
        JOIN modules m ON m.module_id = s.module_id
        LEFT JOIN venues v ON v.venue_id = s.venue_id
        WHERE s.lecturer_id = ?
        ORDER BY FIELD(s.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), s.start_time
    ");
    $timetableStmt->execute([$lecturerId]);
    $sessions = $timetableStmt->fetchAll(PDO::FETCH_ASSOC);

    $moduleMap = [];
    foreach ($sessions as $session) {
        $moduleId = (int) $session['module_id'];
        if (!isset($moduleMap[$moduleId])) {
            $moduleMap[$moduleId] = [
                'module_id' => $moduleId,
                'module_code' => $session['module_code'],
                'module_name' => $session['module_name'],
            ];
        }
    }
    $moduleIds = array_keys($moduleMap);

    $upcomingExams = [];
    $upcomingAssessments = [];
    $examLoadByDate = [];
    $assessmentLoadByDate = [];
    if (!empty($moduleIds)) {
        $placeholders = implode(',', array_fill(0, count($moduleIds), '?'));
        $examStmt = $pdo->prepare("
            SELECT e.exam_id, e.module_id, e.exam_date, e.exam_time, e.duration, m.module_code, m.module_name
            FROM exams e
            JOIN modules m ON m.module_id = e.module_id
            WHERE e.module_id IN ($placeholders)
              AND e.exam_date >= CURDATE()
            ORDER BY e.exam_date, e.exam_time
        ");
        $examStmt->execute($moduleIds);
        $upcomingExams = $examStmt->fetchAll(PDO::FETCH_ASSOC);

        $assessmentStmt = $pdo->prepare("
            SELECT a.assessment_id, a.module_id, a.title, a.assessment_date, a.assessment_time, a.duration, m.module_code, m.module_name
            FROM lecturer_assessments a
            JOIN modules m ON m.module_id = a.module_id
            WHERE a.module_id IN ($placeholders)
              AND a.assessment_date >= CURDATE()
            ORDER BY a.assessment_date, a.assessment_time
        ");
        $assessmentStmt->execute($moduleIds);
        $upcomingAssessments = $assessmentStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $loadStmt = $pdo->query("
        SELECT exam_date, COUNT(*) AS total_exams
        FROM exams
        WHERE exam_date >= CURDATE()
          AND exam_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        GROUP BY exam_date
    ");
    foreach ($loadStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $examLoadByDate[$row['exam_date']] = (int) $row['total_exams'];
    }
    $assessmentLoadStmt = $pdo->query("
        SELECT assessment_date, COUNT(*) AS total_assessments
        FROM lecturer_assessments
        WHERE assessment_date >= CURDATE()
          AND assessment_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        GROUP BY assessment_date
    ");
    foreach ($assessmentLoadStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $assessmentLoadByDate[$row['assessment_date']] = (int) $row['total_assessments'];
    }

    $teachingDays = [];
    foreach ($sessions as $session) {
        $teachingDays[$session['day_of_week']] = true;
    }

    $recommendations = [];
    for ($i = 3; $i <= 21; $i++) {
        $candidateTs = strtotime("+$i day");
        $candidateDay = date('l', $candidateTs);
        if (!isset($teachingDays[$candidateDay])) {
            continue;
        }

        $candidateDate = date('Y-m-d', $candidateTs);
        $load = ($examLoadByDate[$candidateDate] ?? 0) + ($assessmentLoadByDate[$candidateDate] ?? 0);
        $risk = $load >= 4 ? 'High' : ($load >= 2 ? 'Medium' : 'Low');
        $score = max(0, 10 - ($load * 2));

        $recommendations[] = [
            'date' => $candidateDate,
            'day' => $candidateDay,
            'exam_load' => $load,
            'risk' => $risk,
            'score' => $score,
        ];
    }

    usort($recommendations, function ($a, $b) {
        if ($a['exam_load'] === $b['exam_load']) {
            return strcmp($a['date'], $b['date']);
        }
        return $a['exam_load'] <=> $b['exam_load'];
    });
    $recommendations = array_slice($recommendations, 0, 3);

    sendJSONResponse(true, [
        'lecturer' => $lecturer,
        'sessions' => $sessions,
        'modules' => array_values($moduleMap),
        'upcoming_exams' => $upcomingExams,
        'upcoming_assessments' => $upcomingAssessments,
        'recommendations' => $recommendations,
    ], 'Lecturer planner data retrieved');
} catch (Exception $e) {
    handleAPIError($e, 'Failed to retrieve lecturer planner data');
}
?>
