<?php
require_once __DIR__ . '/../includes/api_helpers.php';
require_once __DIR__ . '/../includes/lecturer_system.php';

setCORSHeaders();

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    sendJSONResponse(false, null, 'Method not allowed', 405);
}

try {
    $pdo = getDBConnection();
    ensureLecturerSystemTables($pdo);

    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 500;
    if ($limit <= 0 || $limit > 2000) {
        $limit = 500;
    }

    $stmt = $pdo->prepare("
        SELECT notification_id
        FROM student_assessment_notifications
        WHERE is_sent = 0
          AND scheduled_for <= NOW()
        ORDER BY scheduled_for ASC
        LIMIT ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        sendJSONResponse(true, [
            'processed' => 0,
        ], 'No due assessment notifications');
    }

    $pdo->beginTransaction();
    $updateStmt = $pdo->prepare("
        UPDATE student_assessment_notifications
        SET is_sent = 1,
            sent_at = NOW()
        WHERE notification_id = ?
    ");
    $processed = 0;
    foreach ($rows as $row) {
        $updateStmt->execute([(int) $row['notification_id']]);
        $processed++;
    }
    $pdo->commit();

    sendJSONResponse(true, [
        'processed' => $processed,
    ], 'Assessment notifications processed');
} catch (Exception $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    handleAPIError($e, 'Failed to process assessment notifications');
}
?>
