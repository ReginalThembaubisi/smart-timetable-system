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
        // Credits default to 0 if not provided (not essential for this system)
        $credits = isset($_POST['credits']) && $_POST['credits'] !== '' ? (int)$_POST['credits'] : 0;
        $_POST['credits'] = $credits; // Ensure it's set for handleFormSubmission
        
        $result = handleFormSubmission(
            'modules',
            ['module_code', 'module_name', 'credits'],
            'module_code',
            isset($_POST['module_id']) && $_POST['module_id'] ? 'Module updated successfully' : 'Module added successfully'
        );
        
        if ($result['success']) {
            $_SESSION['success_message'] = $result['message'];
            if (isset($result['id'])) {
                logActivity('module_' . (isset($_POST['module_id']) ? 'updated' : 'created'), "Module ID: {$result['id']}", getCurrentUserId());
            }
        } else {
            $_SESSION['error_message'] = $result['message'];
        }
    } catch (Exception $e) {
        logError($e, 'Module form submission');
        $_SESSION['error_message'] = getErrorMessage($e, 'Saving module');
    }
    header('Location: modules.php');
    exit;
}

try {
    $modules = getAllRecords('modules', 'module_code');
    $editModule = null;
    if (isset($_GET['edit'])) {
        $editModule = getRecordById('modules', (int)$_GET['edit']);
    }
} catch (Exception $e) {
    logError($e, 'Loading modules');
    $modules = [];
    $editModule = null;
    if (empty($_SESSION['error_message'])) {
        $_SESSION['error_message'] = getErrorMessage($e, 'Loading modules');
    }
}
?>
<?php
$breadcrumbs = [
    ['label' => 'Dashboard', 'href' => 'index.php'],
    ['label' => 'Modules', 'href' => null],
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
            
            <!-- Info banner: Modules are auto-created from timetable uploads -->
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
                            Modules are automatically created when you upload timetable TXT files. Upload your timetable file to populate this list.
                        </p>
                    </div>
                    <a href="../timetable_pdf_parser.php" class="btn" style="margin-left: auto; padding: 10px 20px; font-size: 14px; font-weight: 600; text-decoration: none; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Upload Timetable</a>
                </div>
            </div>

            <div class="table-container card">
                <table class="table compact">
                    <thead>
                        <tr>
                            <th data-sort>ID</th>
                            <th data-sort>Code</th>
                            <th data-sort>Name</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($modules)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 60px 40px; color: rgba(220,230,255,0.65); font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                                    <div style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width: 48px; height: 48px; color: rgba(220,230,255,0.4); margin-bottom: 8px;">
                                            <path d="M4 22h16a2 2 0 0 0 2-2V7l-5-5H6a2 2 0 0 0-2 2v16Z"/><path d="M14 2v6h6"/>
                                        </svg>
                                        <div style="font-size: 15px; font-weight: 600; color: rgba(220,230,255,0.8);">No modules found</div>
                                        <div style="font-size: 13px; color: rgba(220,230,255,0.65); max-width: 400px;">Upload a timetable TXT file to automatically create modules.</div>
                                        <a href="../timetable_pdf_parser.php" class="btn" style="margin-top: 8px; display: inline-block; padding: 10px 20px; font-size: 14px; font-weight: 600; text-decoration: none; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Upload Timetable File</a>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($modules as $module): ?>
                            <tr>
                                <td data-value="<?= (int)$module['module_id'] ?>" style="color: rgba(220,230,255,0.6); font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;"><?= $module['module_id'] ?></td>
                                <td data-value="<?= htmlspecialchars($module['module_code']) ?>" style="font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;"><strong style="color: #e8edff; font-weight: 600; font-size: 14px;"><?= htmlspecialchars($module['module_code']) ?></strong></td>
                                <td data-value="<?= htmlspecialchars($module['module_name']) ?>" style="color: rgba(220,230,255,0.9); font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;"><?= htmlspecialchars($module['module_name']) ?></td>
                                <td><span class="pill pill--muted" title="Auto-created from timetable upload">Auto-created</span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            

            

<?php include 'footer_modern.php'; ?>

