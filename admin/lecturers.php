<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';
require_once __DIR__ . '/../includes/crud_helpers.php';
require_once __DIR__ . '/../includes/helpers.php';

// Deletions are locked for data integrity

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $result = handleFormSubmission(
            'lecturers',
            ['lecturer_name', 'email'],
            null,
            isset($_POST['lecturer_id']) && $_POST['lecturer_id'] ? 'Lecturer updated successfully' : 'Lecturer added successfully'
        );
        
        if ($result['success']) {
            $_SESSION['success_message'] = $result['message'];
            if (isset($result['id'])) {
                logActivity('lecturer_' . (isset($_POST['lecturer_id']) ? 'updated' : 'created'), "Lecturer ID: {$result['id']}", getCurrentUserId());
            }
        } else {
            $_SESSION['error_message'] = $result['message'];
        }
    } catch (Exception $e) {
        logError($e, 'Lecturer form submission');
        $_SESSION['error_message'] = getErrorMessage($e, 'Saving lecturer');
    }
    header('Location: lecturers.php');
    exit;
}

try {
    $lecturers = getAllRecords('lecturers', 'lecturer_name');
    $editLecturer = null;
    if (isset($_GET['edit'])) {
        $editLecturer = getRecordById('lecturers', (int)$_GET['edit']);
    }
} catch (Exception $e) {
    logError($e, 'Loading lecturers');
    $lecturers = [];
    $editLecturer = null;
    if (empty($_SESSION['error_message'])) {
        $_SESSION['error_message'] = getErrorMessage($e, 'Loading lecturers');
    }
}
?>
<?php
$breadcrumbs = [
    ['label' => 'Dashboard', 'href' => 'index.php'],
    ['label' => 'Lecturers', 'href' => null],
];
$page_actions = [];
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
            
            <!-- Info banner: Lecturers are auto-created from timetable uploads -->
            <div class="content-card" style="margin-bottom: 24px; background: rgba(59, 130, 246, 0.12); border: 1px solid rgba(59, 130, 246, 0.25); border-radius: 16px; padding: 20px 24px; backdrop-filter: blur(10px);">
                <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                    <div style="width: 40px; height: 40px; background: rgba(59, 130, 246, 0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid rgba(59, 130, 246, 0.3);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" style="width:20px;height:20px; color: #93c5fd; flex-shrink: 0;">
                            <circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>
                        </svg>
                    </div>
                    <div style="flex: 1; min-width: 200px;">
                        <strong style="color: #e8edff; font-size: 15px; font-weight: 700; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; display: block; margin-bottom: 4px;">Auto-created from Timetable Uploads</strong>
                        <p style="color: rgba(220,230,255,0.75); font-size: 13px; margin: 0; line-height: 1.5; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                            Lecturers are automatically created when you upload timetable TXT files. Upload your timetable file to populate this list.
                        </p>
                    </div>
                    <a href="../timetable_pdf_parser.php" class="btn" style="margin-left: auto; padding: 10px 20px; font-size: 14px; font-weight: 600; text-decoration: none; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Upload Timetable</a>
                </div>
            </div>
            
            <div class="table-container card">
                <table class="table compact">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lecturers)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 60px 40px; color: rgba(220,230,255,0.65); font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                                    <div style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width: 48px; height: 48px; color: rgba(220,230,255,0.4); margin-bottom: 8px;">
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                                        </svg>
                                        <div style="font-size: 15px; font-weight: 600; color: rgba(220,230,255,0.8);">No lecturers found</div>
                                        <div style="font-size: 13px; color: rgba(220,230,255,0.65); max-width: 400px;">Upload a timetable TXT file to automatically create lecturers.</div>
                                        <a href="../timetable_pdf_parser.php" class="btn" style="margin-top: 8px; display: inline-block; padding: 10px 20px; font-size: 14px; font-weight: 600; text-decoration: none; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Upload Timetable File</a>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($lecturers as $lecturer): ?>
                            <tr>
                                <td style="color: rgba(220,230,255,0.6); font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;"><?= $lecturer['lecturer_id'] ?></td>
                                <td style="font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;"><strong style="color: #e8edff; font-weight: 600; font-size: 14px;"><?= htmlspecialchars($lecturer['lecturer_name']) ?></strong></td>
                                <td style="color: rgba(220,230,255,0.75); font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;"><?= !empty($lecturer['email']) ? htmlspecialchars($lecturer['email']) : '<span style="color: rgba(220,230,255,0.4);">—</span>' ?></td>
                                <td><span class="pill pill--muted" title="Auto-created from timetable upload">Auto-created</span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

<?php include 'footer_modern.php'; ?>

