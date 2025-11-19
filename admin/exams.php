<?php
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

if (isset($_GET['delete']) && isset($_GET['id'])) {
    try {
        deleteRecord('exams', (int)$_GET['id']);
        $_SESSION['success_message'] = 'Exam deleted successfully';
        logActivity('exam_deleted', "Exam ID: {$_GET['id']}", getCurrentUserId());
    } catch (Exception $e) {
        logError($e, 'Deleting exam');
        $_SESSION['error_message'] = getErrorMessage($e, 'Deleting exam');
    }
    header('Location: exams.php');
    exit;
}

// Bulk delete handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete']) && !empty($_POST['exam_ids']) && is_array($_POST['exam_ids'])) {
	try {
		$ids = array_map('intval', $_POST['exam_ids']);
		if (!empty($ids)) {
			$placeholders = implode(',', array_fill(0, count($ids), '?'));
			$stmt = $pdo->prepare("DELETE FROM exams WHERE exam_id IN ($placeholders)");
			$stmt->execute($ids);
			$_SESSION['success_message'] = 'Selected exams deleted successfully';
			logActivity('exams_bulk_deleted', "Deleted " . count($ids) . " exams", getCurrentUserId());
		}
	} catch (Exception $e) {
		logError($e, 'Bulk deleting exams');
		$_SESSION['error_message'] = getErrorMessage($e, 'Deleting selected exams');
	}
	header('Location: exams.php');
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $result = handleFormSubmission(
            'exams',
            ['module_id', 'venue_id', 'exam_date', 'exam_time', 'duration'],
            null,
            isset($_POST['exam_id']) && $_POST['exam_id'] ? 'Exam updated successfully' : 'Exam added successfully'
        );
        
        if ($result['success']) {
            $_SESSION['success_message'] = $result['message'];
            if (isset($result['id'])) {
                logActivity('exam_' . (isset($_POST['exam_id']) ? 'updated' : 'created'), "Exam ID: {$result['id']}", getCurrentUserId());
            }
        } else {
            $_SESSION['error_message'] = $result['message'];
        }
    } catch (Exception $e) {
        logError($e, 'Exam form submission');
        $_SESSION['error_message'] = getErrorMessage($e, 'Saving exam');
    }
    header('Location: exams.php');
    exit;
}

try {
    $exams = $pdo->query("SELECT e.*, m.module_code, m.module_name, v.venue_name FROM exams e LEFT JOIN modules m ON e.module_id = m.module_id LEFT JOIN venues v ON e.venue_id = v.venue_id ORDER BY e.exam_date, e.exam_time")->fetchAll(PDO::FETCH_ASSOC);
    $modules = getAllRecords('modules', 'module_code');
    $venues = getAllRecords('venues', 'venue_name');
    
    $editExam = null;
    if (isset($_GET['edit'])) {
        $editExam = getRecordById('exams', (int)$_GET['edit']);
    }
} catch (Exception $e) {
    logError($e, 'Loading exams');
    $exams = [];
    $modules = [];
    $venues = [];
    $editExam = null;
    if (empty($_SESSION['error_message'])) {
        $_SESSION['error_message'] = getErrorMessage($e, 'Loading exams');
    }
}
?>
<?php
$breadcrumbs = [
    ['label' => 'Dashboard', 'href' => 'index.php'],
    ['label' => 'Exams', 'href' => null],
];
$page_actions = [
    ['label' => 'Add Exam', 'href' => '#', 'class' => 'btn-success'],
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
            
            <?php if (isset($_SESSION['exam_import_skip_reasons']) && !empty($_SESSION['exam_import_skip_reasons'])): ?>
                <div class="content-card" style="margin-bottom: 20px; background: rgba(255, 193, 7, 0.08); border: 1px solid rgba(255, 193, 7, 0.2);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <h3 style="margin: 0; color: #ffc107; font-size: 18px; font-weight: 700; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; display: flex; align-items: center; gap: 10px;">
                            <svg viewBox="0 0 24 24" style="width: 20px; height: 20px; color: #ffc107;" fill="none" stroke="currentColor" stroke-width="1.7">
                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                                <line x1="12" y1="9" x2="12" y2="13"></line>
                                <line x1="12" y1="17" x2="12.01" y2="17"></line>
                            </svg>
                            Skipped Entries (<?= count($_SESSION['exam_import_skip_reasons']) ?>)
                        </h3>
                        <button type="button" onclick="this.parentElement.parentElement.querySelector('.skip-details').style.display = this.parentElement.parentElement.querySelector('.skip-details').style.display === 'none' ? 'block' : 'none'; this.textContent = this.textContent === 'Hide Details' ? 'Show Details' : 'Hide Details';" style="background: rgba(255, 193, 7, 0.15); color: #ffc107; border: 1px solid rgba(255, 193, 7, 0.3); padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Show Details</button>
                    </div>
                    <div class="skip-details" style="display: none; max-height: 400px; overflow-y: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                            <thead>
                                <tr style="border-bottom: 1px solid rgba(255, 193, 7, 0.2);">
                                    <th style="text-align: left; padding: 10px; color: #ffc107; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Reason</th>
                                    <th style="text-align: left; padding: 10px; color: #ffc107; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Module</th>
                                    <th style="text-align: left; padding: 10px; color: #ffc107; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Date</th>
                                    <th style="text-align: left; padding: 10px; color: #ffc107; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Time</th>
                                    <th style="text-align: left; padding: 10px; color: #ffc107; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Venue</th>
                                    <th style="text-align: left; padding: 10px; color: #ffc107; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Existing Exam ID</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $reasonCounts = [];
                                foreach ($_SESSION['exam_import_skip_reasons'] as $skip): 
                                    $reason = $skip['reason'];
                                    $reasonCounts[$reason] = ($reasonCounts[$reason] ?? 0) + 1;
                                ?>
                                <tr style="border-bottom: 1px solid rgba(255, 193, 7, 0.1);">
                                    <td style="padding: 10px; color: rgba(255, 193, 7, 0.9); font-size: 13px;"><?= htmlspecialchars($skip['reason']) ?></td>
                                    <td style="padding: 10px; color: rgba(255, 255, 255, 0.7); font-size: 13px;"><?= htmlspecialchars($skip['module'] ?? 'N/A') ?></td>
                                    <td style="padding: 10px; color: rgba(255, 255, 255, 0.7); font-size: 13px;"><?= htmlspecialchars($skip['date'] ?? 'N/A') ?></td>
                                    <td style="padding: 10px; color: rgba(255, 255, 255, 0.7); font-size: 13px;"><?= htmlspecialchars($skip['time'] ?? 'N/A') ?></td>
                                    <td style="padding: 10px; color: rgba(255, 255, 255, 0.7); font-size: 13px;"><?= htmlspecialchars($skip['venue'] ?? 'N/A') ?></td>
                                    <td style="padding: 10px; color: rgba(255, 255, 255, 0.7); font-size: 13px;">
                                        <?php if (isset($skip['existing_exam_id'])): ?>
                                            <a href="?edit=<?= $skip['existing_exam_id'] ?>" style="color: #4a9eff; text-decoration: none; font-weight: 600;">#<?= htmlspecialchars($skip['existing_exam_id']) ?></a>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid rgba(255, 193, 7, 0.2);">
                            <div style="color: rgba(255, 193, 7, 0.8); font-size: 13px; font-weight: 600; margin-bottom: 8px;">Summary:</div>
                            <?php foreach ($reasonCounts as $reason => $count): ?>
                                <div style="color: rgba(255, 255, 255, 0.7); font-size: 12px; margin-left: 16px; margin-bottom: 4px;">
                                    • <?= htmlspecialchars($reason) ?>: <strong style="color: #ffc107;"><?= $count ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php 
                unset($_SESSION['exam_import_skip_reasons']);
                unset($_SESSION['exam_import_skip_count']);
                ?>
            <?php endif; ?>
            
            <div class="content-card" style="margin-bottom: 20px;">
                <h3 style="margin-bottom: 16px; color: #e8edff; font-size: 20px; font-weight: 700; letter-spacing: -0.2px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; display: flex; align-items: center; gap: 10px;">
                    <svg viewBox="0 0 24 24" style="width: 20px; height: 20px; color: #dce3ff;" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="17 8 12 3 7 8"></polyline>
                        <line x1="12" y1="3" x2="12" y2="15"></line>
                    </svg>
                    Automatic Import (PDF/TXT)
                </h3>
                <form method="POST" action="../exam_import_api.php" enctype="multipart/form-data" class="form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Upload Exam Timetable File *</label>
                            <input type="file" name="file" accept=".pdf,.txt" required>
                            <small style="display:block; color: rgba(255,255,255,0.6); margin-top: 6px;">
                                Supports “final_nov...” PDFs and TXT. Entries will be parsed and inserted automatically.
                            </small>
                        </div>
                        <div class="form-group">
                            <label>Exam Release</label>
                            <select name="exam_status">
                                <option value="final">Final</option>
                                <option value="draft">Draft</option>
                            </select>
                        </div>
                    </div>
                    <div style="margin-top: 10px;">
                        <button type="submit" class="btn btn-success">Auto-Import Exams</button>
                    </div>
                </form>
            </div>
            
            <!-- Removed manual Add/Edit form as requested -->
            
            <div class="content-card" style="margin-bottom: 20px;">
                <h3 style="margin-bottom: 12px; color: #e8edff; font-size: 20px; font-weight: 700; letter-spacing: -0.2px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; display: flex; align-items: center; gap: 10px;">
                    <svg viewBox="0 0 24 24" style="width: 20px; height: 20px; color: #dce3ff;" fill="none" stroke="currentColor" stroke-width="1.7">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    Bulk Removal
                </h3>
                <form method="POST" id="bulkDeleteForm">
                    <input type="hidden" name="bulk_delete" value="1">
                    <button type="submit" id="bulkDeleteBtn" class="btn btn-danger" disabled>Delete Selected</button>
                </form>
            </div>

            <div class="table-container">
                <table id="examsTable">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll"></th>
                            <th>ID</th>
                            <th>Module</th>
                            <th>Session</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Venue</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($exams)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 60px 40px; color: rgba(220,230,255,0.65); font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                                    <div style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width: 48px; height: 48px; color: rgba(220,230,255,0.4); margin-bottom: 8px;">
                                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                            <line x1="16" y1="2" x2="16" y2="6"></line>
                                            <line x1="8" y1="2" x2="8" y2="6"></line>
                                            <line x1="3" y1="10" x2="21" y2="10"></line>
                                        </svg>
                                        <div style="font-size: 15px; font-weight: 600; color: rgba(220,230,255,0.8);">No exams found</div>
                                        <div style="font-size: 13px; color: rgba(220,230,255,0.65);">Import your first exam timetable above.</div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($exams as $exam): ?>
                            <tr>
                                <td><input type="checkbox" class="row-check" form="bulkDeleteForm" name="exam_ids[]" value="<?= $exam['exam_id'] ?>"></td>
                                <td style="color: rgba(220,230,255,0.6); font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;"><?= $exam['exam_id'] ?></td>
                                <td style="font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                                    <strong style="color: #e8edff; font-weight: 600; font-size: 14px;"><?= htmlspecialchars($exam['module_code']) ?></strong><br>
                                    <small style="color: rgba(220,230,255,0.65); font-size: 12px;"><?= htmlspecialchars($exam['module_name']) ?></small>
                                </td>
                                <td style="color: rgba(220,230,255,0.75); font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;"><?= htmlspecialchars($exam['exam_date'] . ' ' . substr($exam['exam_time'],0,5)) ?></td>
                                <td style="color: rgba(220,230,255,0.75); font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;"><?= htmlspecialchars($exam['exam_date']) ?></td>
                                <td style="color: rgba(220,230,255,0.75); font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;"><?= htmlspecialchars($exam['exam_time']) ?></td>
                                <td style="color: rgba(220,230,255,0.75); font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;"><?= $exam['duration'] ?> min</td>
                                <td><span class="tag" style="background: <?= ($exam['exam_status'] ?? 'final') === 'draft' ? 'rgba(241, 196, 15, 0.15)' : 'rgba(39, 174, 96, 0.15)' ?>; color: <?= ($exam['exam_status'] ?? 'final') === 'draft' ? '#f1c40f' : '#27ae60' ?>;">
                                    <?= htmlspecialchars(strtoupper($exam['exam_status'] ?? 'final')) ?>
                                </span></td>
                                <td style="color: rgba(220,230,255,0.75); font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;"><?= htmlspecialchars($exam['venue_name'] ?? '-') ?></td>
                                <td>
                                    <div class="actions">
                                        <a href="?edit=<?= $exam['exam_id'] ?>" class="btn" style="padding: 8px 16px; font-size: 12px;">Edit</a>
                                        <a href="?delete=1&id=<?= $exam['exam_id'] ?>" class="btn btn-danger" style="padding: 8px 16px; font-size: 12px;">Delete</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

<?php include 'footer_modern.php'; ?>

<script>
    (function() {
        const selectAll = document.getElementById('selectAll');
        const checks = document.querySelectorAll('.row-check');
        const btn = document.getElementById('bulkDeleteBtn');
        function updateBtn() {
            let any = false;
            document.querySelectorAll('.row-check').forEach(c => { if (c.checked) any = true; });
            btn.disabled = !any;
        }
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                checks.forEach(c => c.checked = selectAll.checked);
                updateBtn();
            });
        }
        checks.forEach(c => c.addEventListener('change', updateBtn));
        updateBtn();
    })();
</script>

