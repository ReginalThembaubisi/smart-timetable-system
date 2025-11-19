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

// Create student_modules table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_modules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        module_id INT NOT NULL,
        enrollment_date DATE DEFAULT CURRENT_DATE,
        status VARCHAR(20) DEFAULT 'active',
        FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
        FOREIGN KEY (module_id) REFERENCES modules(module_id) ON DELETE CASCADE,
        UNIQUE KEY unique_enrollment (student_id, module_id)
    )");
} catch (Exception $e) {
    logError($e, 'Creating student_modules table');
}

// Ensure optional columns exist for program/year metadata
try {
    $col = $pdo->query("SHOW COLUMNS FROM student_modules LIKE 'program_id'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE student_modules ADD COLUMN program_id INT NULL AFTER module_id");
        $pdo->exec("ALTER TABLE student_modules ADD COLUMN year_level INT NULL AFTER program_id");
        // Foreign key may fail if programs table missing; ignore errors here
        try { 
            $pdo->exec("ALTER TABLE student_modules ADD CONSTRAINT fk_sm_program FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE SET NULL"); 
        } catch (Exception $e) {
            logError($e, 'Adding foreign key to student_modules');
        }
    }
} catch (Exception $e) {
    logError($e, 'Setting up student_modules columns');
}

// Handle delete
if (isset($_GET['delete']) && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM student_modules WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $_SESSION['success_message'] = 'Enrollment removed successfully';
        logActivity('enrollment_deleted', "Enrollment ID: {$_GET['id']}", getCurrentUserId());
    } catch (Exception $e) {
        logError($e, 'Deleting enrollment');
        $_SESSION['error_message'] = getErrorMessage($e, 'Removing enrollment');
    }
    header('Location: student_modules.php');
    exit;
}

// Handle add/edit enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $studentId = (int)$_POST['student_id'];
        $moduleId = (int)$_POST['module_id'];
        $status = $_POST['status'] ?? 'active';
        $programId = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;
        $yearLevel = !empty($_POST['year_level']) ? (int)$_POST['year_level'] : null;
        
        // Check if enrollment already exists
        $stmt = $pdo->prepare("SELECT id FROM student_modules WHERE student_id = ? AND module_id = ?");
        $stmt->execute([$studentId, $moduleId]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            // Update existing enrollment
            $stmt = $pdo->prepare("UPDATE student_modules SET status = ?, program_id = ?, year_level = ? WHERE id = ?");
            $stmt->execute([$status, $programId, $yearLevel, $exists['id']]);
            $_SESSION['success_message'] = 'Enrollment updated successfully';
        } else {
            // Insert new enrollment
            $stmt = $pdo->prepare("INSERT INTO student_modules (student_id, module_id, program_id, year_level, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$studentId, $moduleId, $programId, $yearLevel, $status]);
            $_SESSION['success_message'] = 'Student enrolled successfully';
        }
    } catch (Exception $e) {
        logError($e, 'Saving enrollment');
        $_SESSION['error_message'] = getErrorMessage($e, 'Saving enrollment');
    }
    // Redirect back to students page if coming from there, otherwise stay on student_modules page
    $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : 'student_modules.php';
    header('Location: ' . $redirect);
    exit;
}

// Get all enrollments with student and module info
$enrollments = $pdo->query("
    SELECT sm.*, s.student_number, s.full_name, m.module_code, m.module_name, m.credits
    FROM student_modules sm
    JOIN students s ON sm.student_id = s.student_id
    JOIN modules m ON sm.module_id = m.module_id
    ORDER BY sm.enrollment_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

$students = $pdo->query("SELECT * FROM students ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
$modules = $pdo->query("SELECT * FROM modules ORDER BY module_code")->fetchAll(PDO::FETCH_ASSOC);

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
if ($search) {
    $stmt = $pdo->prepare("
        SELECT sm.*, s.student_number, s.full_name, m.module_code, m.module_name, m.credits
        FROM student_modules sm
        JOIN students s ON sm.student_id = s.student_id
        JOIN modules m ON sm.module_id = m.module_id
        WHERE s.student_number LIKE ? OR s.full_name LIKE ? OR m.module_code LIKE ? OR m.module_name LIKE ?
        ORDER BY sm.enrollment_date DESC
    ");
    $searchTerm = "%{$search}%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<?php
$breadcrumbs = [
    ['label' => 'Dashboard', 'href' => 'index.php'],
    ['label' => 'Student Enrollment', 'href' => null],
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
            
            <div class="content-card">
                <div class="search-bar">
                    <form method="GET" style="display: flex; gap: 12px; flex: 1; align-items: center;">
                        <input type="text" name="search" placeholder="Search by student number, name, or module code..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn">Search</button>
                        <?php if ($search): ?>
                            <a href="student_modules.php" class="btn btn-cancel">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
            <div class="content-card">
                <h3 style="margin-bottom: 20px; color: rgba(255,255,255,0.9); font-size: 18px; font-weight: 600;">Enroll Student in Module</h3>
                <form method="POST" class="form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Student *</label>
                            <select name="student_id" required>
                                <option value="">Select Student</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?= $student['student_id'] ?>">
                                        <?= htmlspecialchars($student['student_number'] . ' - ' . $student['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Module *</label>
                            <select name="module_id" required>
                                <option value="">Select Module</option>
                                <?php foreach ($modules as $module): ?>
                                    <option value="<?= $module['module_id'] ?>">
                                        <?= htmlspecialchars($module['module_code'] . ' - ' . $module['module_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div style="margin-top: 20px;">
                        <button type="submit" class="btn btn-success">Enroll Student</button>
                    </div>
                </form>
            </div>

            <div class="content-card">
                <h3 style="margin-bottom: 12px; color: rgba(255,255,255,0.9); font-size: 18px; font-weight: 600;">Bulk Enroll (10k+ ready)</h3>
                <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 10px;">Paste student numbers (one per line or comma-separated), choose a module, and enroll in fast batches. Existing enrollments are safely skipped.</p>
                <div class="form">
                    <div class="form-row">
                        <div class="form-group" style="flex:2">
                            <label>Student Numbers</label>
                            <textarea id="bulkStudentNumbers" rows="4" placeholder="e.g.\n20201234\n20205678, 20207890" style="width:100%; padding:12px; background: var(--bg-primary); border:1px solid var(--border-color); border-radius: var(--radius-md); color: var(--text-primary);"></textarea>
                        </div>
                        <div class="form-group" style="flex:1">
                            <label>Module *</label>
                            <select id="bulkModuleId">
                                <option value="">Select Module</option>
                                <?php foreach ($modules as $module): ?>
                                    <option value="<?= $module['module_id'] ?>">
                                        <?= htmlspecialchars($module['module_code'] . ' - ' . $module['module_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div style="margin-top: 12px; display:flex; gap:10px;">
                        <button type="button" class="btn" onclick="startBulkEnroll()">Start Bulk Enroll</button>
                        <span id="bulkStatus" style="color: var(--text-muted); font-size:12px;"></span>
                    </div>
                </div>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student</th>
                            <th>Module</th>
                            <th>Credits</th>
                            <th>Enrollment Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($enrollments)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: rgba(255,255,255,0.5);">
                                    No enrollments found. <?= $search ? 'Try a different search term.' : 'Enroll students in modules above.' ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($enrollments as $enrollment): ?>
                            <tr>
                                <td><?= $enrollment['id'] ?></td>
                                <td><strong><?= htmlspecialchars($enrollment['student_number']) ?></strong><br><small style="color: rgba(255,255,255,0.5);"><?= htmlspecialchars($enrollment['full_name']) ?></small></td>
                                <td><strong><?= htmlspecialchars($enrollment['module_code']) ?></strong><br><small style="color: rgba(255,255,255,0.5);"><?= htmlspecialchars($enrollment['module_name']) ?></small></td>
                                <td><?= $enrollment['credits'] ?? '-' ?></td>
                                <td><?= $enrollment['enrollment_date'] ?></td>
                                <td>
                                    <span style="padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 500; background: <?= $enrollment['status'] === 'active' ? 'rgba(39, 174, 96, 0.2)' : 'rgba(231, 76, 60, 0.2)' ?>; color: <?= $enrollment['status'] === 'active' ? '#27ae60' : '#e74c3c' ?>;">
                                        <?= ucfirst($enrollment['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="?delete=1&id=<?= $enrollment['id'] ?>" class="btn btn-danger" style="padding: 8px 16px; font-size: 12px;">Remove</a>
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
async function startBulkEnroll(){
    const ta = document.getElementById('bulkStudentNumbers');
    const moduleId = document.getElementById('bulkModuleId').value;
    const statusEl = document.getElementById('bulkStatus');
    if (!moduleId){ showCustomPopup('Select a module', 'warning'); return; }
    const raw = (ta.value || '').trim();
    if (!raw){ showCustomPopup('Paste student numbers', 'warning'); return; }
    const nums = raw.split(/[\s,;]+/).map(s => s.trim()).filter(Boolean);
    if (!nums.length){ showCustomPopup('No valid numbers', 'warning'); return; }
    statusEl.textContent = 'Preparing...';
    try{
        const res = await fetch('enrollment_bulk_api.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json','Accept':'application/json'},
            body: JSON.stringify({ module_id: parseInt(moduleId,10), student_numbers: nums })
        });
        const data = await res.json().catch(()=>({success:false,error:'Bad response'}));
        if (!res.ok || !data.success){ throw new Error(data.error || 'Bulk enroll failed'); }
        statusEl.textContent = `Done: inserted ${data.inserted}, skipped ${data.skipped}, failed ${data.failed}`;
        if (window.UI){ UI.showToast('Bulk enrollment completed','success'); }
        setTimeout(()=>{ window.location.reload(); }, 800);
    }catch(e){
        statusEl.textContent = 'Error: ' + e.message;
        if (window.UI){ UI.showToast('Bulk enrollment failed','error'); }
    }
}
</script>


