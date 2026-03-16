<?php
require_once __DIR__ . '/../includes/api_helpers.php';
require_once __DIR__ . '/../includes/lecturer_system.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(false, null, 'Method not allowed', 405);
}

try {
    $data = getJSONInput();
    validateRequired($data, ['lecturer_id', 'module_id', 'title', 'assessment_date', 'assessment_time']);

    $lecturerId = (int) $data['lecturer_id'];
    $moduleId = (int) $data['module_id'];
    $title = trim((string) $data['title']);
    $assessmentDate = (string) $data['assessment_date'];
    $assessmentTime = (string) $data['assessment_time'];
    $duration = isset($data['duration']) ? (int) $data['duration'] : 60;
    $notes = isset($data['notes']) ? trim((string) $data['notes']) : null;

    if ($lecturerId <= 0 || $moduleId <= 0 || $title === '') {
        sendJSONResponse(false, null, 'Invalid assessment payload', 400);
    }
    if (!validateTimeFormat($assessmentTime)) {
        sendJSONResponse(false, null, 'Invalid assessment_time. Use HH:MM or HH:MM:SS', 400);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $assessmentDate)) {
        sendJSONResponse(false, null, 'Invalid assessment_date. Use YYYY-MM-DD', 400);
    }
    if ($duration < 30 || $duration > 240) {
        sendJSONResponse(false, null, 'Duration must be between 30 and 240 minutes', 400);
    }

    $pdo = getDBConnection();
    ensureLecturerSystemTables($pdo);

    $ownsModuleStmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM sessions
        WHERE lecturer_id = ? AND module_id = ?
    ");
    $ownsModuleStmt->execute([$lecturerId, $moduleId]);
    $ownsModule = (int) $ownsModuleStmt->fetchColumn() > 0;
    if (!$ownsModule) {
        sendJSONResponse(false, null, 'Lecturer is not linked to this module timetable', 403);
    }

    $sharedModuleIds = getSharedCohortModuleIds($pdo, $moduleId);
    $conflictCount = getAssessmentConflictCount($pdo, $sharedModuleIds, $assessmentDate, 2);
    $risk = $conflictCount >= 4 ? 'high' : ($conflictCount >= 2 ? 'medium' : 'low');

    $forcePublish = !empty($data['force_publish']);
    if ($risk === 'high' && !$forcePublish) {
        sendJSONResponse(false, [
            'risk' => $risk,
            'conflict_count' => $conflictCount,
            'shared_module_ids' => $sharedModuleIds,
        ], 'High conflict risk detected. Re-submit with force_publish=true to continue.', 409);
    }

    $pdo->beginTransaction();

    $insertStmt = $pdo->prepare("
        INSERT INTO lecturer_assessments (
            module_id, created_by_lecturer_id, title, assessment_date, assessment_time, duration, status, notes
        ) VALUES (?, ?, ?, ?, ?, ?, 'published', ?)
    ");
    $insertStmt->execute([
        $moduleId,
        $lecturerId,
        $title,
        $assessmentDate,
        $assessmentTime,
        $duration,
        $notes,
    ]);
    $assessmentId = (int) $pdo->lastInsertId();

    $notificationCount = queueAssessmentNotifications(
        $pdo,
        $assessmentId,
        $moduleId,
        $title,
        $assessmentDate,
        $assessmentTime
    );

    $pdo->commit();

    sendJSONResponse(true, [
        'assessment_id' => $assessmentId,
        'risk' => $risk,
        'conflict_count' => $conflictCount,
        'notification_queue_count' => $notificationCount,
    ], 'Assessment published successfully');
} catch (Exception $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    handleAPIError($e, 'Failed to create lecturer assessment');
}
?>
