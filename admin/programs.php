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

// Ensure tables exist
try {
    $pdo = Database::getInstance()->getConnection();
    $pdo->exec("CREATE TABLE IF NOT EXISTS programs (
        program_id INT AUTO_INCREMENT PRIMARY KEY,
        program_code VARCHAR(50) UNIQUE NOT NULL,
        program_name VARCHAR(255) NOT NULL,
        description TEXT,
        duration_years INT DEFAULT 4,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_program_code (program_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    logError($e, 'Creating programs table');
}

if (isset($_GET['delete']) && isset($_GET['id'])) {
    try {
        deleteRecord('programs', (int)$_GET['id']);
        $_SESSION['success_message'] = 'Program deleted successfully';
        logActivity('program_deleted', "Program ID: {$_GET['id']}", getCurrentUserId());
    } catch (Exception $e) {
        logError($e, 'Deleting program');
        $_SESSION['error_message'] = getErrorMessage($e, 'Deleting program');
    }
    header('Location: programs.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $result = handleFormSubmission(
            'programs',
            ['program_code', 'program_name', 'description', 'duration_years'],
            'program_code',
            isset($_POST['program_id']) && $_POST['program_id'] ? 'Program updated successfully' : 'Program added successfully'
        );
        
        if ($result['success']) {
            $_SESSION['success_message'] = $result['message'];
            if (isset($result['id'])) {
                logActivity('program_' . (isset($_POST['program_id']) ? 'updated' : 'created'), "Program ID: {$result['id']}", getCurrentUserId());
            }
        } else {
            $_SESSION['error_message'] = $result['message'];
        }
    } catch (Exception $e) {
        logError($e, 'Program form submission');
        $_SESSION['error_message'] = getErrorMessage($e, 'Saving program');
    }
    header('Location: programs.php');
    exit;
}

try {
    $programs = getAllRecords('programs', 'program_code');
    $editProgram = null;
    if (isset($_GET['edit'])) {
        $editProgram = getRecordById('programs', (int)$_GET['edit']);
    }
} catch (Exception $e) {
    logError($e, 'Loading programs');
    $programs = [];
    $editProgram = null;
    if (empty($_SESSION['error_message'])) {
        $_SESSION['error_message'] = getErrorMessage($e, 'Loading programs');
    }
}
?>
<?php
$breadcrumbs = [
    ['label' => 'Dashboard', 'href' => 'index.php'],
    ['label' => 'Programs', 'href' => null],
];
$page_actions = [
    ['label' => 'Add Program', 'href' => '#', 'class' => 'btn-success'],
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
            
            <div class="content-card">
                <h3 style="margin-bottom: 20px; color: #e8edff; font-size: 20px; font-weight: 700; letter-spacing: -0.2px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; display: flex; align-items: center; gap: 10px;">
                    <?php if ($editProgram): ?>
                        <svg viewBox="0 0 24 24" style="width: 20px; height: 20px; color: #dce3ff;" fill="none" stroke="currentColor" stroke-width="1.7">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                    <?php else: ?>
                        <svg viewBox="0 0 24 24" style="width: 20px; height: 20px; color: #dce3ff;" fill="none" stroke="currentColor" stroke-width="1.7">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="8.5" cy="7" r="4"></circle>
                            <line x1="20" y1="8" x2="20" y2="14"></line>
                            <line x1="23" y1="11" x2="17" y2="11"></line>
                        </svg>
                    <?php endif; ?>
                    <?= $editProgram ? 'Edit Program' : 'Add New Program' ?>
                </h3>
                <form method="POST" class="form">
                    <input type="hidden" name="program_id" value="<?= $editProgram['program_id'] ?? '' ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Program Code</label>
                            <input type="text" name="program_code" placeholder="e.g., CS" value="<?= htmlspecialchars($editProgram['program_code'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Program Name</label>
                            <input type="text" name="program_name" placeholder="e.g., Computer Science" value="<?= htmlspecialchars($editProgram['program_name'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Duration (Years)</label>
                            <input type="number" name="duration_years" placeholder="e.g., 4" value="<?= $editProgram['duration_years'] ?? 4 ?>" min="1" max="10" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group" style="flex: 1;">
                            <label>Description</label>
                            <textarea name="description" placeholder="Program description..." rows="3" style="width: 100%; padding: 10px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; color: rgba(255,255,255,0.9); font-size: 14px; font-family: inherit; resize: vertical;"><?= htmlspecialchars($editProgram['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div style="margin-top: 20px;">
                        <button type="submit" class="btn btn-success"><?= $editProgram ? 'Update' : 'Add' ?> Program</button>
                        <?php if ($editProgram): ?>
                            <a href="programs.php" class="btn btn-cancel">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Duration</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($programs)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 60px 40px; color: rgba(220,230,255,0.65); font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                                    <div style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width: 48px; height: 48px; color: rgba(220,230,255,0.4); margin-bottom: 8px;">
                                            <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                        </svg>
                                        <div style="font-size: 15px; font-weight: 600; color: rgba(220,230,255,0.8);">No programs found</div>
                                        <div style="font-size: 13px; color: rgba(220,230,255,0.65);">Add your first program above.</div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($programs as $program): ?>
                            <tr>
                                <td style="color: rgba(220,230,255,0.6); font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;"><?= $program['program_id'] ?></td>
                                <td style="font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;"><strong style="color: #e8edff; font-weight: 600; font-size: 14px;"><?= htmlspecialchars($program['program_code']) ?></strong></td>
                                <td style="color: rgba(220,230,255,0.9); font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;"><?= htmlspecialchars($program['program_name']) ?></td>
                                <td style="color: rgba(220,230,255,0.75); font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;"><?= $program['duration_years'] ?> year<?= $program['duration_years'] != 1 ? 's' : '' ?></td>
                                <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: rgba(220,230,255,0.75); font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;"><?= htmlspecialchars($program['description'] ?? '-') ?></td>
                                <td>
                                    <div class="actions">
                                        <a href="?edit=<?= $program['program_id'] ?>" class="btn" style="padding: 8px 16px; font-size: 12px;">Edit</a>
                                        <a href="program_modules.php?program_id=<?= $program['program_id'] ?>" class="btn" style="padding: 8px 16px; font-size: 12px; background: rgba(155, 89, 182, 0.2); color: #9b59b6; border: 1px solid rgba(155, 89, 182, 0.3);">Modules</a>
                                        <a href="?delete=1&id=<?= $program['program_id'] ?>" class="btn btn-danger" style="padding: 8px 16px; font-size: 12px;">Delete</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

<?php include 'footer_modern.php'; ?>

