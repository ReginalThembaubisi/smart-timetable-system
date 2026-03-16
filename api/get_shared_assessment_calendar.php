<?php
require_once __DIR__ . '/../includes/api_helpers.php';
require_once __DIR__ . '/../includes/lecturer_system.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSONResponse(false, null, 'Method not allowed', 405);
}

try {
    $moduleId = isset($_GET['module_id']) ? (int) $_GET['module_id'] : 0;
    if ($moduleId <= 0) {
        sendJSONResponse(false, null, 'Invalid module ID', 400);
    }

    $days = isset($_GET['days']) ? (int) $_GET['days'] : 30;
    if ($days < 7 || $days > 120) {
        $days = 30;
    }

    $pdo = getDBConnection();
    ensureLecturerSystemTables($pdo);

    $sharedModuleIds = getSharedCohortModuleIds($pdo, $moduleId);
    if (empty($sharedModuleIds)) {
        sendJSONResponse(true, [
            'shared_module_ids' => [],
            'items' => [],
        ], 'No shared modules found');
    }

    $placeholders = implode(',', array_fill(0, count($sharedModuleIds), '?'));
    $endDate = date('Y-m-d', strtotime("+{$days} day"));

    $examSql = "
        SELECT
            'exam' AS item_type,
            e.exam_id AS item_id,
            e.module_id,
            m.module_code,
            m.module_name,
            e.exam_date AS item_date,
            e.exam_time AS item_time,
            e.duration,
            NULL AS lecturer_name,
            'exam_timetable' AS source
        FROM exams e
        JOIN modules m ON m.module_id = e.module_id
        WHERE e.module_id IN ($placeholders)
          AND e.exam_date BETWEEN CURDATE() AND ?
    ";
    $examStmt = $pdo->prepare($examSql);
    $examStmt->execute(array_merge($sharedModuleIds, [$endDate]));
    $examRows = $examStmt->fetchAll(PDO::FETCH_ASSOC);

    $assessmentSql = "
        SELECT
            'assessment' AS item_type,
            a.assessment_id AS item_id,
            a.module_id,
            m.module_code,
            m.module_name,
            a.assessment_date AS item_date,
            a.assessment_time AS item_time,
            a.duration,
            l.lecturer_name,
            'lecturer_planner' AS source
        FROM lecturer_assessments a
        JOIN modules m ON m.module_id = a.module_id
        JOIN lecturers l ON l.lecturer_id = a.created_by_lecturer_id
        WHERE a.module_id IN ($placeholders)
          AND a.assessment_date BETWEEN CURDATE() AND ?
    ";
    $assessmentStmt = $pdo->prepare($assessmentSql);
    $assessmentStmt->execute(array_merge($sharedModuleIds, [$endDate]));
    $assessmentRows = $assessmentStmt->fetchAll(PDO::FETCH_ASSOC);

    $items = array_merge($examRows, $assessmentRows);
    usort($items, function ($a, $b) {
        $dateCmp = strcmp($a['item_date'], $b['item_date']);
        if ($dateCmp !== 0) {
            return $dateCmp;
        }
        return strcmp((string) $a['item_time'], (string) $b['item_time']);
    });

    sendJSONResponse(true, [
        'shared_module_ids' => $sharedModuleIds,
        'items' => $items,
    ], 'Shared assessment calendar retrieved');
} catch (Exception $e) {
    handleAPIError($e, 'Failed to retrieve shared assessment calendar');
}
?>
