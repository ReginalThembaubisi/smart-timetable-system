<?php
// This page is deprecated in favor of the parser and view pages.
header('Location: ../view_timetable.php');
exit;
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/crud_helpers.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = Database::getInstance()->getConnection();

// Deletions disabled - sessions are managed via parser/editor

// Manual add/update disabled - sessions are created via parser or editor

try {
    $sessions = $pdo->query("SELECT s.*, m.module_code, m.module_name, l.lecturer_name, v.venue_name FROM sessions s LEFT JOIN modules m ON s.module_id = m.module_id LEFT JOIN lecturers l ON s.lecturer_id = l.lecturer_id LEFT JOIN venues v ON s.venue_id = v.venue_id ORDER BY s.day_of_week, s.start_time")->fetchAll(PDO::FETCH_ASSOC);
    $modules = getAllRecords('modules', 'module_code');
    $lecturers = getAllRecords('lecturers', 'lecturer_name');
    $venues = getAllRecords('venues', 'venue_name');
    
    $editSession = null;
    if (isset($_GET['edit'])) {
        $editSession = getRecordById('sessions', (int)$_GET['edit']);
    }
} catch (Exception $e) {
    logError($e, 'Loading timetable sessions');
    $sessions = [];
    $modules = [];
    $lecturers = [];
    $venues = [];
    $editSession = null;
    if (empty($_SESSION['error_message'])) {
        $_SESSION['error_message'] = getErrorMessage($e, 'Loading timetable');
    }
}
?>
<?php
$breadcrumbs = [
    ['label' => 'Dashboard', 'href' => 'index.php'],
    ['label' => 'Sessions', 'href' => null],
];
$page_actions = [
    ['label' => 'View Timetable', 'href' => '../view_timetable.php'],
    ['label' => 'Upload Timetable', 'href' => '../timetable_pdf_parser.php'],
];
include 'header_modern.php';
?>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($_SESSION['success_message']) ?>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($_SESSION['error_message']) ?>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
            
            <!-- Storage-only: no manual add/update form -->
            
            <div class="table-container card">
                <table class="table compact">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Module</th>
                            <th>Day</th>
                            <th>Time</th>
                            <th>Lecturer</th>
                            <th>Venue</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sessions)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 60px 40px; color: rgba(220,230,255,0.65); font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                                    <div style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width: 48px; height: 48px; color: rgba(220,230,255,0.4); margin-bottom: 8px;">
                                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                            <line x1="16" y1="2" x2="16" y2="6"></line>
                                            <line x1="8" y1="2" x2="8" y2="6"></line>
                                            <line x1="3" y1="10" x2="21" y2="10"></line>
                                        </svg>
                                        <div style="font-size: 15px; font-weight: 600; color: rgba(220,230,255,0.8);">No sessions found</div>
                                        <div style="font-size: 13px; color: rgba(220,230,255,0.65);">Upload a timetable file or add sessions manually.</div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sessions as $session): ?>
                            <tr>
                                <td style="color: rgba(220,230,255,0.6); font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;"><?= $session['session_id'] ?></td>
                                <td style="font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                                    <strong style="color: #e8edff; font-weight: 600; font-size: 14px;"><?= htmlspecialchars($session['module_code']) ?></strong><br>
                                    <small style="color: rgba(220,230,255,0.65); font-size: 12px;"><?= htmlspecialchars($session['module_name']) ?></small>
                                </td>
                                <td style="color: rgba(220,230,255,0.75); font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;"><?= htmlspecialchars($session['day_of_week']) ?></td>
                                <td style="color: rgba(220,230,255,0.75); font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;"><?= htmlspecialchars($session['start_time'] . ' - ' . $session['end_time']) ?></td>
                                <td style="color: rgba(220,230,255,0.75); font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;"><?= htmlspecialchars($session['lecturer_name'] ?? '-') ?></td>
                                <td style="color: rgba(220,230,255,0.75); font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;"><?= htmlspecialchars($session['venue_name'] ?? '-') ?></td>
                                <td><span class="pill pill--muted">View only</span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

<?php include 'footer_modern.php'; ?>

