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

// Ensure programs table and student columns exist
try {
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
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS program_modules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        program_id INT NOT NULL,
        module_id INT NOT NULL,
        year_level INT NOT NULL,
        semester INT,
        is_core TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE CASCADE,
        FOREIGN KEY (module_id) REFERENCES modules(module_id) ON DELETE CASCADE,
        UNIQUE KEY unique_program_module_year (program_id, module_id, year_level),
        INDEX idx_program_id (program_id),
        INDEX idx_module_id (module_id),
        INDEX idx_year_level (year_level)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Add program_id and year_level to students if they don't exist
    $columns = $pdo->query("SHOW COLUMNS FROM students LIKE 'program_id'")->fetch();
    if (!$columns) {
        $pdo->exec("ALTER TABLE students ADD COLUMN program_id INT NULL AFTER email");
        $pdo->exec("ALTER TABLE students ADD COLUMN year_level INT NULL AFTER program_id");
        try {
            $pdo->exec("ALTER TABLE students ADD FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE SET NULL");
        } catch (Exception $e) {
            logError($e, 'Adding foreign key to students table');
        }
        $pdo->exec("ALTER TABLE students ADD INDEX idx_program_id (program_id)");
    }
    
    // Add program_id and year_level to student_modules if they don't exist
    $columns = $pdo->query("SHOW COLUMNS FROM student_modules LIKE 'program_id'")->fetch();
    if (!$columns) {
        $pdo->exec("ALTER TABLE student_modules ADD COLUMN program_id INT NULL AFTER student_id");
        $pdo->exec("ALTER TABLE student_modules ADD COLUMN year_level INT NULL AFTER program_id");
        try {
            $pdo->exec("ALTER TABLE student_modules ADD FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE SET NULL");
        } catch (Exception $e) {
            logError($e, 'Adding foreign key to student_modules table');
        }
    }
} catch (Exception $e) {
    logError($e, 'Setting up students table structure');
}

// Handle delete
if (isset($_GET['delete']) && isset($_GET['id'])) {
    try {
        $studentId = (int)$_GET['id'];
        
        // First, check if student exists
        $stmt = $pdo->prepare("SELECT student_id, student_number, full_name FROM students WHERE student_id = ?");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            $_SESSION['error_message'] = 'Student not found';
        } else {
            // Delete related records first (student_modules should cascade, but let's be explicit)
            // Check for any other references that might prevent deletion
            $checkEnrollments = $pdo->prepare("SELECT COUNT(*) FROM student_modules WHERE student_id = ?");
            $checkEnrollments->execute([$studentId]);
            $enrollmentCount = $checkEnrollments->fetchColumn();
            
            // Check for exam_notifications if table exists
            $examNotificationsCount = 0;
            try {
                $checkExams = $pdo->prepare("SELECT COUNT(*) FROM exam_notifications WHERE student_id = ?");
                $checkExams->execute([$studentId]);
                $examNotificationsCount = $checkExams->fetchColumn();
                if ($examNotificationsCount > 0) {
                    $deleteExams = $pdo->prepare("DELETE FROM exam_notifications WHERE student_id = ?");
                    $deleteExams->execute([$studentId]);
                }
            } catch (Exception $e) {
                // Table might not exist, ignore
            }
            
            // Delete student_modules records (should cascade, but doing it explicitly for clarity)
            if ($enrollmentCount > 0) {
                $deleteEnrollments = $pdo->prepare("DELETE FROM student_modules WHERE student_id = ?");
                $deleteEnrollments->execute([$studentId]);
            }
            
            // Now delete the student using direct SQL to get better error messages
            try {
                $deleteStmt = $pdo->prepare("DELETE FROM students WHERE student_id = ?");
                $deleteStmt->execute([$studentId]);
                
                if ($deleteStmt->rowCount() > 0) {
                    $successMsg = 'Student deleted successfully';
                    if ($enrollmentCount > 0) {
                        $successMsg .= ' (and ' . $enrollmentCount . ' module enrollment(s) removed)';
                    }
                    if ($examNotificationsCount > 0) {
                        $successMsg .= ' (and ' . $examNotificationsCount . ' exam notification(s) removed)';
                    }
                    $_SESSION['success_message'] = $successMsg;
                    logActivity('student_deleted', "Student ID: {$studentId} ({$student['student_number']} - {$student['full_name']})", getCurrentUserId());
                } else {
                    throw new Exception("No rows were deleted. Student may have already been deleted.");
                }
            } catch (PDOException $e) {
                // Re-throw with more context
                throw new Exception("Database error: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");
            }
        }
    } catch (Exception $e) {
        logError($e, 'Deleting student', ['student_id' => $studentId ?? null]);
        $errorMsg = getErrorMessage($e, 'Deleting student', true); // Show details in dev
        
        // Provide more specific error message
        $errorCode = $e->getCode();
        $errorMessage = $e->getMessage();
        
        if ($errorCode == 23000 || strpos($errorMessage, 'foreign key constraint') !== false || strpos($errorMessage, 'Cannot delete') !== false) {
            $_SESSION['error_message'] = 'Cannot delete this student because they have related records in the system. The system tried to remove enrollments automatically, but there may be other constraints. Please check the error logs for details.';
        } elseif (strpos($errorMessage, '1451') !== false) {
            $_SESSION['error_message'] = 'Cannot delete this student: Foreign key constraint violation. Please remove all related records (enrollments, exam notifications, etc.) first.';
        } elseif (strpos($errorMessage, '1452') !== false) {
            $_SESSION['error_message'] = 'Cannot delete this student: Invalid reference in related records.';
        } else {
            // For development, show more details
            $isDev = defined('APP_ENV') && APP_ENV === 'development';
            if ($isDev) {
                $_SESSION['error_message'] = 'Deleting student failed: ' . htmlspecialchars($errorMessage) . ' (Code: ' . $errorCode . ')';
            } else {
                $_SESSION['error_message'] = 'Deleting student failed. Please try again. If the problem persists, check the error logs.';
            }
        }
    }
    header('Location: students.php');
    exit;
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['student_id']) && $_POST['student_id']) {
            // Update - check if student number already exists (excluding current record)
            $check = $pdo->prepare("SELECT COUNT(*) FROM students WHERE student_number = ? AND student_id != ?");
            $check->execute([$_POST['student_number'], $_POST['student_id']]);
            if ($check->fetchColumn() > 0) {
                $_SESSION['error_message'] = 'Student number already exists';
            } else {
                // Only update password if provided
                $programId = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;
                $yearFilters = isset($_POST['year_filters']) && is_array($_POST['year_filters']) ? array_map('intval', $_POST['year_filters']) : [];
                $yearLevel = !empty($_POST['year_level']) ? (int)$_POST['year_level'] : (count($yearFilters) ? min($yearFilters) : null);
                
                if (!empty($_POST['password'])) {
                    $hashedPassword = hashPassword($_POST['password']);
                    $stmt = $pdo->prepare("UPDATE students SET student_number = ?, full_name = ?, email = ?, password = ?, program_id = ?, year_level = ? WHERE student_id = ?");
                    $stmt->execute([$_POST['student_number'], $_POST['full_name'], $_POST['email'], $hashedPassword, $programId, $yearLevel, $_POST['student_id']]);
                } else {
                    // Don't update password if field is empty
                    $stmt = $pdo->prepare("UPDATE students SET student_number = ?, full_name = ?, email = ?, program_id = ?, year_level = ? WHERE student_id = ?");
                    $stmt->execute([$_POST['student_number'], $_POST['full_name'], $_POST['email'], $programId, $yearLevel, $_POST['student_id']]);
                }
                $_SESSION['success_message'] = 'Student updated successfully';
                logActivity('student_updated', "Student ID: {$_POST['student_id']} updated", getCurrentUserId());
            }
        } else {
            // Check if student number already exists
            $check = $pdo->prepare("SELECT COUNT(*) FROM students WHERE student_number = ?");
            $check->execute([$_POST['student_number']]);
            if ($check->fetchColumn() > 0) {
                $_SESSION['error_message'] = 'Student number already exists';
            } else {
                // Hash password before inserting
                $hashedPassword = hashPassword($_POST['password']);
                $programId = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;
                $yearFilters = isset($_POST['year_filters']) && is_array($_POST['year_filters']) ? array_map('intval', $_POST['year_filters']) : [];
                $yearLevel = !empty($_POST['year_level']) ? (int)$_POST['year_level'] : (count($yearFilters) ? min($yearFilters) : null);
                $stmt = $pdo->prepare("INSERT INTO students (student_number, full_name, email, password, program_id, year_level) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$_POST['student_number'], $_POST['full_name'], $_POST['email'], $hashedPassword, $programId, $yearLevel]);
                $newStudentId = $pdo->lastInsertId();
                
                // Enroll student in selected modules if any
                if ($newStudentId && !empty($_POST['modules']) && is_array($_POST['modules'])) {
                    $enrolledCount = 0;
                    
                    // Get the actual year level for each module from program_modules
                    foreach ($_POST['modules'] as $moduleId) {
                        $moduleId = (int)$moduleId;
                        if ($moduleId > 0) {
                            try {
                                // Find which year(s) this module belongs to for this program
                                $yearStmt = $pdo->prepare("SELECT DISTINCT year_level FROM program_modules WHERE program_id = ? AND module_id = ?");
                                $yearStmt->execute([$programId, $moduleId]);
                                $moduleYears = $yearStmt->fetchAll(PDO::FETCH_COLUMN);
                                
                                // If module not linked to program yet, use the student's year level
                                if (empty($moduleYears) && $yearLevel) {
                                    $moduleYears = [$yearLevel];
                                    // Link module to program/year
                                    $linkStmt = $pdo->prepare("INSERT INTO program_modules (program_id, module_id, year_level, is_core) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE is_core = VALUES(is_core)");
                                    $linkStmt->execute([$programId, $moduleId, $yearLevel, 1]);
                                }
                                
                                // Enroll student for each year the module belongs to (or use student's year if not found)
                                $yearsToEnroll = !empty($moduleYears) ? $moduleYears : ($yearLevel ? [$yearLevel] : []);
                                foreach ($yearsToEnroll as $enrollYear) {
                                    $enrollStmt = $pdo->prepare("INSERT INTO student_modules (student_id, module_id, program_id, year_level, status) VALUES (?, ?, ?, ?, 'active') ON DUPLICATE KEY UPDATE status = 'active'");
                                    $enrollStmt->execute([$newStudentId, $moduleId, $programId, $enrollYear]);
                                }
                                $enrolledCount++;
                            } catch (Exception $e) {
                                // Skip if error (might be duplicate)
                                error_log("Enrollment error for module {$moduleId}: " . $e->getMessage());
                            }
                        }
                    }
                    if ($enrolledCount > 0) {
                        $_SESSION['success_message'] = "Student added successfully and enrolled in {$enrolledCount} module(s)";
                    } else {
                        $_SESSION['success_message'] = 'Student added successfully';
                    }
                } else {
                    $_SESSION['success_message'] = 'Student added successfully';
                }
                logActivity('student_added', "New student: {$_POST['student_number']}", getCurrentUserId());
            }
        }
    } catch (Exception $e) {
        logError($e, 'Saving student');
        $_SESSION['error_message'] = getErrorMessage($e, 'Saving student');
    }
    header('Location: students.php');
    exit;
}

// Get all programs - ensure table exists first
try {
    $programs = getAllRecords('programs', 'program_code');
} catch (Exception $e) {
    // Table might not exist, create it
    try {
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
        $programs = [];
    } catch (Exception $e2) {
        logError($e2, 'Creating programs table in students.php');
        $programs = [];
    }
}

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
if ($search) {
    $stmt = $pdo->prepare("SELECT s.*, COUNT(sm.module_id) as module_count, p.program_code, p.program_name FROM students s LEFT JOIN student_modules sm ON s.student_id = sm.student_id LEFT JOIN programs p ON s.program_id = p.program_id WHERE s.student_number LIKE ? OR s.full_name LIKE ? OR s.email LIKE ? GROUP BY s.student_id ORDER BY s.student_id DESC");
    $searchTerm = "%{$search}%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $students = $pdo->query("SELECT s.*, COUNT(sm.module_id) as module_count, p.program_code, p.program_name FROM students s LEFT JOIN student_modules sm ON s.student_id = sm.student_id LEFT JOIN programs p ON s.program_id = p.program_id GROUP BY s.student_id ORDER BY s.student_id DESC")->fetchAll(PDO::FETCH_ASSOC);
}

// Get modules for each student (for modal display)
$studentModules = [];
foreach ($students as $student) {
    $stmt = $pdo->prepare("
        SELECT sm.id, m.module_id, m.module_code, m.module_name, m.credits, sm.status, sm.enrollment_date
        FROM student_modules sm
        JOIN modules m ON sm.module_id = m.module_id
        WHERE sm.student_id = ?
        ORDER BY m.module_code
    ");
    $stmt->execute([$student['student_id']]);
    $studentModules[$student['student_id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$editStudent = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmt->execute([$_GET['edit']]);
    $editStudent = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get all modules for the add module modal (will be filtered by program and year in JS)
$allModules = $pdo->query("SELECT * FROM modules ORDER BY module_code")->fetchAll(PDO::FETCH_ASSOC);

// Get program_modules mapping - ensure year_level is integer for consistent key matching
$programModules = [];
$stmt = $pdo->query("SELECT pm.*, m.module_code, m.module_name, m.credits FROM program_modules pm JOIN modules m ON pm.module_id = m.module_id ORDER BY pm.program_id, pm.year_level, m.module_code");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($results as $pm) {
    $programId = (int)$pm['program_id'];
    $yearLevel = (int)$pm['year_level']; // Ensure year_level is integer
    
    if (!isset($programModules[$programId])) {
        $programModules[$programId] = [];
    }
    if (!isset($programModules[$programId][$yearLevel])) {
        $programModules[$programId][$yearLevel] = [];
    }
    $programModules[$programId][$yearLevel][] = $pm;
}
?>
<?php
// Breadcrumbs and actions for header
$breadcrumbs = [
    ['label' => 'Dashboard', 'href' => 'index.php'],
    ['label' => 'Students', 'href' => null],
];
$page_actions = [
    ['label' => 'Add Student', 'href' => '#add-student-form', 'class' => 'btn-success'],
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
            
            <!-- Info Banner: Mock Registration System -->
            <div class="content-card" style="margin-top: 20px; margin-bottom: 20px; background: rgba(59, 130, 246, 0.12); border: 1px solid rgba(59, 130, 246, 0.25); border-radius: 16px; padding: 18px 22px; backdrop-filter: blur(10px);">
                <div style="display: flex; align-items: flex-start; gap: 14px;">
                    <div style="width: 36px; height: 36px; background: rgba(59, 130, 246, 0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid rgba(59, 130, 246, 0.3);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" style="width:18px;height:18px; color: #93c5fd;">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M12 16v-4"></path>
                            <path d="M12 8h.01"></path>
                        </svg>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <strong style="color: #e8edff; font-size: 14px; font-weight: 700; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; display: block; margin-bottom: 6px;">Mock Registration System</strong>
                        <p style="color: rgba(220,230,255,0.85); font-size: 13px; margin: 0; line-height: 1.6; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                            This is a <strong style="color: rgba(220,230,255,0.95);">mock version</strong> of the school registration system. In the final implementation with API access, students would be <strong style="color: rgba(220,230,255,0.95);">automatically synced</strong> from the main registration system. For now, students need to be added manually using the form below.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="content-card card" id="add-student-form" style="padding: 20px 24px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0; cursor: pointer;" onclick="toggleStudentForm()">
                    <h3 style="margin: 0; color: #e8edff; font-size: 18px; font-weight: 700; letter-spacing: -0.2px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; display: flex; align-items: center; gap: 10px;">
                        <svg viewBox="0 0 24 24" style="width: 18px; height: 18px; color: #dce3ff;" fill="none" stroke="currentColor" stroke-width="1.7">
                            <?php if ($editStudent): ?>
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            <?php else: ?>
                                <path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            <?php endif; ?>
                        </svg>
                        <?= $editStudent ? 'Edit Student' : 'Add New Student' ?>
                    </h3>
                    <svg id="formToggleIcon" viewBox="0 0 24 24" style="width: 20px; height: 20px; color: #dce3ff; transition: transform 0.3s ease;" fill="none" stroke="currentColor" stroke-width="1.7">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </div>
                <form method="POST" class="form" id="studentFormContent" style="display: <?= $editStudent ? 'block' : 'none' ?>; margin-top: 20px;">
                    <input type="hidden" name="student_id" value="<?= $editStudent['student_id'] ?? '' ?>">
                    <div class="form-row" style="gap: 12px; margin-bottom: 12px;">
                        <div class="form-group" style="flex: 1;">
                            <label style="color: rgba(220,230,255,0.8); font-size: 12px; font-weight: 600; margin-bottom: 6px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Student Number</label>
                            <input type="text" name="student_number" placeholder="e.g., 202057420" value="<?= htmlspecialchars($editStudent['student_number'] ?? '') ?>" required style="padding: 8px 14px; font-size: 13px;">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label style="color: rgba(220,230,255,0.8); font-size: 12px; font-weight: 600; margin-bottom: 6px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Full Name</label>
                            <input type="text" name="full_name" placeholder="Full Name" value="<?= htmlspecialchars($editStudent['full_name'] ?? '') ?>" required style="padding: 8px 14px; font-size: 13px;">
                        </div>
                    </div>
                    <div class="form-row" style="gap: 12px; margin-bottom: 12px;">
                        <div class="form-group" style="flex: 1;">
                            <label style="color: rgba(220,230,255,0.8); font-size: 12px; font-weight: 600; margin-bottom: 6px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Email</label>
                            <input type="text" name="email" placeholder="email@example.com (optional for demo)" value="<?= htmlspecialchars($editStudent['email'] ?? '') ?>" style="padding: 8px 14px; font-size: 13px;">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label style="color: rgba(220,230,255,0.8); font-size: 12px; font-weight: 600; margin-bottom: 6px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Password</label>
                            <input type="password" name="password" placeholder="<?= $editStudent ? 'Leave blank to keep current password' : 'Password' ?>" <?= $editStudent ? '' : 'required' ?> style="padding: 8px 14px; font-size: 13px;">
                            <?php if ($editStudent): ?>
                                <small style="color: rgba(220,230,255,0.65); font-size: 11px; display: block; margin-top: 4px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Leave blank to keep current password</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="form-row" style="gap: 12px; margin-bottom: 12px;">
                        <div class="form-group" style="flex: 1;">
                            <label style="color: rgba(220,230,255,0.8); font-size: 12px; font-weight: 600; margin-bottom: 6px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Program</label>
                            <select name="program_id" id="formProgramId" onchange="loadFormModules()" style="width: 100%; padding: 8px 14px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); border-radius: 10px; color: #e8edff; font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
                                <option value="">Select Program</option>
                                <?php if (empty($programs)): ?>
                                    <option value="" disabled>No programs found</option>
                                <?php else: ?>
                                    <?php foreach ($programs as $program): ?>
                                        <option value="<?= $program['program_id'] ?>" <?= (isset($editStudent['program_id']) && $editStudent['program_id'] == $program['program_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($program['program_code'] . ' - ' . $program['program_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <?php if (empty($programs)): ?>
                                <small style="color: rgba(220,230,255,0.65); font-size: 11px; display: block; margin-top: 4px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                                    <a href="../timetable_pdf_parser.php" style="color: #dce3ff; text-decoration: underline;">Upload timetable</a> or <a href="programs.php" style="color: #dce3ff; text-decoration: underline;">create programs</a> first.
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (!$editStudent): // Only show module selection for new students ?>
                    <div id="modulesSelectionSection" style="margin-top: 16px; display: none;">
                        <div class="form-group" style="flex: 1;">
                            <label style="color: rgba(220,230,255,0.8); font-size: 12px; font-weight: 600; margin-bottom: 8px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Select Modules (Optional)</label>
                            <div style="margin-bottom: 12px;">
                                <div style="color: rgba(220,230,255,0.8); font-size: 12px; margin-bottom: 8px; font-weight: 600; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Select Year(s) to view modules:</div>
                                <div id="yearCheckboxes" style="display: flex; flex-wrap: wrap; gap: 8px;">
                                    <?php for ($i = 1; $i <= 6; $i++): ?>
                                        <label style="display: flex; align-items: center; gap: 6px; padding: 6px 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); border-radius: 8px; cursor: pointer; font-size: 12px; color: rgba(220,230,255,0.9); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;" onmouseover="this.style.background='rgba(255,255,255,0.08)'; this.style.borderColor='rgba(255,255,255,0.18)'" onmouseout="this.style.background='rgba(255,255,255,0.05)'; this.style.borderColor='rgba(255,255,255,0.12)'">
                                            <input type="checkbox" name="year_filters[]" class="year-checkbox" value="<?= $i ?>" style="cursor: pointer; width: 14px; height: 14px;" onchange="loadFormModules()">
                                            <span>Year <?= $i ?></span>
                                        </label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div id="formModulesContainer" style="max-height: 280px; overflow-y: auto; border: 1px solid rgba(255,255,255,0.12); border-radius: 12px; padding: 12px; background: rgba(255,255,255,0.03); backdrop-filter: blur(10px);">
                                <p style="color: rgba(220,230,255,0.65); font-size: 12px; margin: 0; text-align: center; padding: 16px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Select program and year(s) to load modules</p>
                            </div>
                            <small style="color: rgba(220,230,255,0.65); font-size: 11px; display: block; margin-top: 6px; line-height: 1.4; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                                Only modules linked to this program and the selected year(s) will appear. You can select multiple years to see modules from different year levels. If no modules appear, they need to be linked first via timetable upload or the Modules page.
                            </small>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div style="margin-top: 16px; display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-success" style="padding: 10px 20px; font-size: 13px;"><?= $editStudent ? 'Update' : 'Add' ?> Student</button>
                        <?php if ($editStudent): ?>
                            <a href="students.php" class="btn btn-cancel" style="padding: 10px 20px; font-size: 13px;">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <div class="content-card card toolbar" style="gap: 16px; align-items: center; margin-top: 16px; flex-wrap: wrap;">
                <label style="color: rgba(220,230,255,0.8); font-size: 13px; font-weight: 600; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Program</label>
                <select data-filter="program" style="min-width: 220px; padding: 10px 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); border-radius: 10px; color: #e8edff; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
                    <option value="">All</option>
                    <?php foreach ($programs as $program): ?>
                        <option value="<?= htmlspecialchars($program['program_code']) ?>"><?= htmlspecialchars($program['program_code']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label style="color: rgba(220,230,255,0.8); font-size: 13px; font-weight: 600; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Year</label>
                <select data-filter="year" style="min-width: 140px; padding: 10px 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); border-radius: 10px; color: #e8edff; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
                    <option value="">All</option>
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                        <option value="<?= $i ?>">Year <?= $i ?></option>
                    <?php endfor; ?>
                </select>
                <label style="color: rgba(220,230,255,0.8); font-size: 13px; font-weight: 600; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Rows</label>
                <select data-page-size style="min-width: 100px; padding: 8px 14px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); border-radius: 10px; color: #e8edff; font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
                    <option>10</option>
                    <option>25</option>
                    <option>50</option>
                    <option>All</option>
                </select>
            </div>

            <div class="table-container card">
                <form id="bulkForm" method="POST" action="bulk_operations.php">
                    <input type="hidden" name="action" value="delete_students" id="bulkAction">
                    <div id="bulkToolbar" class="bulkbar" style="display: none;">
                        <span style="color: rgba(220,230,255,0.8); font-size: 13px; font-weight: 600; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;"><span data-selected-count>0</span> selected</span>
                        <button class="btn btn-danger" data-bulk-delete>Delete</button>
                        <button class="btn" data-bulk-export>Export CSV</button>
                    </div>
                    <table id="studentsTable" data-table class="table compact">
                        <thead>
                            <tr>
                                <th style="width: 40px;"><input type="checkbox" id="selectAll" style="cursor: pointer;"></th>
                                <th data-sort>ID</th>
                                <th data-sort>Student Number</th>
                                <th data-sort>Full Name</th>
                                <th>Email</th>
                                <th data-sort>Program</th>
                                <th data-sort data-numeric>Year</th>
                                <th data-sort data-numeric>Modules</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 60px 40px; color: rgba(220,230,255,0.65); font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                                        <div style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
                                            <svg viewBox="0 0 24 24" style="width: 48px; height: 48px; color: rgba(220,230,255,0.4); margin-bottom: 8px;" fill="none" stroke="currentColor" stroke-width="1.5">
                                                <path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"></path>
                                                <circle cx="9" cy="7" r="4"></circle>
                                                <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                            </svg>
                                            <div style="font-size: 15px; font-weight: 600; color: rgba(220,230,255,0.8);">No students found</div>
                                            <div style="font-size: 13px; color: rgba(220,230,255,0.65);"><?= $search ? 'Try a different search term.' : 'Add your first student above.' ?></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><input type="checkbox" name="items[]" value="<?= $student['student_id'] ?>" class="item-checkbox" style="cursor: pointer;"></td>
                                    <td data-value="<?= (int)$student['student_id'] ?>" style="color: rgba(220,230,255,0.6); font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;"><?= $student['student_id'] ?></td>
                                    <td data-value="<?= htmlspecialchars($student['student_number']) ?>" style="font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;"><strong style="color: #e8edff; font-weight: 600; font-size: 14px;"><?= htmlspecialchars($student['student_number']) ?></strong></td>
                                    <td data-value="<?= htmlspecialchars($student['full_name']) ?>" style="color: rgba(220,230,255,0.9); font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;"><?= htmlspecialchars($student['full_name']) ?></td>
                                    <td data-value="<?= htmlspecialchars($student['email']) ?>" style="color: rgba(220,230,255,0.75); font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;"><?= htmlspecialchars($student['email']) ?></td>
                                    <td data-value="<?= htmlspecialchars($student['program_code'] ?: '') ?>">
                                        <?php if ($student['program_code']): ?>
                                            <span class="pill pill--blue"><?= htmlspecialchars($student['program_code']) ?></span>
                                        <?php else: ?>
                                            <span class="pill pill--muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-value="<?= (int)($student['year_level'] ?: 0) ?>">
                                        <?php if ($student['year_level']): ?>
                                            <span class="pill pill--yellow">Year <?= $student['year_level'] ?></span>
                                        <?php else: ?>
                                            <span class="pill pill--muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-value="<?= (int)$student['module_count'] ?>">
                                        <span class="pill pill--purple"><?= (int)$student['module_count'] ?> module<?= (int)$student['module_count'] != 1 ? 's' : '' ?></span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <button type="button" onclick="openModulesModal(<?= $student['student_id'] ?>)" class="icon-btn" title="Assign modules" aria-label="Assign modules">
                                                <svg viewBox="0 0 24 24"><path d="M16 6v14"></path><path d="M8 6v14"></path><path d="M12 6v14"></path><path d="M3 6h18"></path></svg>
                                            </button>
                                            <a href="?edit=<?= $student['student_id'] ?>" class="icon-btn" title="Edit" aria-label="Edit">
                                                <svg viewBox="0 0 24 24"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"></path></svg>
                                            </a>
                                            <a href="?delete=1&id=<?= $student['student_id'] ?>" class="icon-btn" title="Delete" aria-label="Delete" onclick="event.preventDefault(); const deleteUrl = this.getAttribute('href'); showCustomConfirm('Are you sure you want to delete this student? This will also remove all their module enrollments. This action cannot be undone.', function(){ window.location.href = deleteUrl; }); return false;">
                                                <svg viewBox="0 0 24 24"><path d="M3 6h18"></path><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path></svg>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </form>
            </div>
            <div class="content-card card" style="display:flex; justify-content:flex-end; padding: 16px 24px;">
                <div data-pagination style="font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;"></div>
            </div>
            
            <!-- Modules Modal - Premium Neo-Glass Style -->
            <div id="modulesModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.75); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center;">
                <div style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.10); border-radius: 24px; padding: 32px; max-width: 900px; width: 90%; max-height: 80vh; overflow-y: auto; box-shadow: 0 8px 32px rgba(0,0,0,0.6); backdrop-filter: blur(20px); font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.12);">
                        <h3 style="color: #e8edff; font-size: 22px; font-weight: 700; margin: 0; letter-spacing: -0.2px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;" id="modalStudentName">Student Modules</h3>
                        <button onclick="closeModulesModal()" style="background: rgba(239,68,68,0.15); color: #fca5a5; border: 1px solid rgba(239,68,68,0.3); padding: 8px 16px; border-radius: 10px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;" onmouseover="this.style.background='rgba(239,68,68,0.2)'; this.style.borderColor='rgba(239,68,68,0.4)'" onmouseout="this.style.background='rgba(239,68,68,0.15)'; this.style.borderColor='rgba(239,68,68,0.3)'">✕ Close</button>
                    </div>
                    
                    <div id="programYearSelection" style="margin-bottom: 24px; padding: 20px; background: rgba(255,255,255,0.05); border-radius: 16px; border: 1px solid rgba(255,255,255,0.12); backdrop-filter: blur(10px);">
                        <h4 style="color: #e8edff; font-size: 17px; font-weight: 700; margin-bottom: 16px; letter-spacing: -0.1px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Select Program and Year</h4>
                        <div style="display: flex; gap: 12px;">
                            <div style="flex: 1;">
                                <label style="display: block; color: rgba(220,230,255,0.8); font-size: 13px; margin-bottom: 8px; font-weight: 600; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Program *</label>
                                <select id="modalProgramId" required onchange="autoLoadModulesIfReady()" style="width: 100%; padding: 10px 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); border-radius: 10px; color: #e8edff; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
                                    <option value="">Select Program</option>
                                    <?php if (empty($programs)): ?>
                                        <option value="" disabled>No programs found. Please upload a timetable or create programs first.</option>
                                    <?php else: ?>
                                        <?php foreach ($programs as $program): ?>
                                            <option value="<?= $program['program_id'] ?>">
                                                <?= htmlspecialchars($program['program_code'] . ' - ' . $program['program_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <?php if (empty($programs)): ?>
                                    <p style="margin-top: 8px; color: rgba(241, 196, 15, 0.8); font-size: 12px;">
                                        💡 <a href="../timetable_pdf_parser.php" style="color: rgba(241, 196, 15, 0.9); text-decoration: underline;">Upload a timetable</a> to automatically create programs, or <a href="programs.php" style="color: rgba(241, 196, 15, 0.9); text-decoration: underline;">create programs manually</a>.
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div style="flex: 1;">
                                <label style="display: block; color: rgba(220,230,255,0.8); font-size: 13px; margin-bottom: 8px; font-weight: 600; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Year Level *</label>
                                <select id="modalYearLevel" required onchange="autoLoadModulesIfReady()" style="width: 100%; padding: 10px 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); border-radius: 10px; color: #e8edff; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
                                    <option value="">Select Year</option>
                                    <?php for ($i = 1; $i <= 6; $i++): ?>
                                        <option value="<?= $i ?>">Year <?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div style="display: flex; align-items: flex-end;">
                                <button type="button" onclick="loadModulesForProgram()" class="btn" style="padding: 10px 20px; font-size: 14px;">Load Modules</button>
                            </div>
                        </div>
                        <p id="programYearMessage" style="margin-top: 10px; color: rgba(255,255,255,0.5); font-size: 12px; display: none;">Please select a program and year to view available modules.</p>
                    </div>
                    
                    <div id="modulesList" style="margin-bottom: 20px; display: none;">
                        <!-- Modules will be loaded here -->
                    </div>
                    
                    <div id="addModuleSection" style="border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px; margin-top: 20px; display: none;">
                        <h4 style="color: #e8edff; font-size: 17px; font-weight: 700; margin-bottom: 16px; letter-spacing: -0.1px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Add Module</h4>
                        <form id="addModuleForm" method="POST" style="display: flex; gap: 12px; align-items: flex-end;">
                            <input type="hidden" name="student_id" id="modalStudentId">
                            <input type="hidden" name="program_id" id="modalFormProgramId">
                            <input type="hidden" name="year_level" id="modalFormYearLevel">
                            <div style="flex: 1;">
                                <label style="display: block; color: rgba(220,230,255,0.8); font-size: 13px; margin-bottom: 8px; font-weight: 600; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Select Module</label>
                                <select name="module_id" id="modalModuleSelect" required style="width: 100%; padding: 10px 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); border-radius: 10px; color: #e8edff; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
                                    <option value="">Choose a module...</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-success" style="padding: 10px 20px; font-size: 14px;">Add Module</button>
                        </form>
                    </div>
                </div>
            </div>

            <script>
                const studentModulesData = <?= json_encode($studentModules) ?>;
                const studentsData = <?= json_encode($students) ?>;
                const programModulesData = <?= json_encode($programModules) ?>;
                const allModulesData = <?= json_encode($allModules) ?>;
                let currentStudentId = null;
                
                // Load modules in the registration form when program/year changes
                function loadFormModules() {
                    const programId = parseInt(document.getElementById('formProgramId')?.value || 0);
                    const container = document.getElementById('formModulesContainer');
                    const section = document.getElementById('modulesSelectionSection');
                    const yearCheckboxes = document.querySelectorAll('.year-checkbox');
                    
                    if (!container || !section) return; // Not on add student form
                    
                    if (!programId) {
                        section.style.display = 'none';
                        return;
                    }
                    
                    section.style.display = 'block';
                    
                    // Get selected years from checkboxes
                    const selectedYears = [];
                    yearCheckboxes.forEach(cb => {
                        if (cb.checked) {
                            selectedYears.push(parseInt(cb.value));
                        }
                    });
                    
                    if (selectedYears.length === 0) {
                        container.innerHTML = '<p style="color: rgba(255,255,255,0.5); font-size: 13px; margin: 0; padding: 20px; text-align: center;">Please select at least one year level to view modules.</p>';
                        return;
                    }
                    
                    // Collect modules ONLY from selected years that are linked in program_modules
                    // Group modules by year for better organization
                    const modulesByYear = {};
                    let totalModules = 0;
                    
                    selectedYears.forEach(year => {
                        modulesByYear[year] = [];
                        if (programModulesData[programId] && programModulesData[programId][year]) {
                            modulesByYear[year] = programModulesData[programId][year];
                            totalModules += modulesByYear[year].length;
                        }
                    });
                    
                    // Build checkbox list grouped by year
                    if (totalModules === 0) {
                        // No modules linked - show helpful message with options
                        const yearsText = selectedYears.sort((a,b) => a-b).map(y => `Year ${y}`).join(', ');
                        container.innerHTML = `
                            <div style="padding: 20px; text-align: center; background: rgba(241, 196, 15, 0.1); border: 1px solid rgba(241, 196, 15, 0.3); border-radius: 8px;">
                                <p style="color: rgba(241, 196, 15, 0.9); font-size: 14px; margin: 0 0 12px 0; font-weight: 500;">
                                    ⚠️ No modules are linked to this program for ${yearsText}
                                </p>
                                <p style="color: rgba(255,255,255,0.6); font-size: 12px; margin: 0 0 16px 0;">
                                    To see modules here, they need to be linked to this program and year level first.
                                </p>
                                <div style="display: flex; gap: 8px; justify-content: center; flex-wrap: wrap;">
                                    <a href="../timetable_pdf_parser.php" target="_blank" style="padding: 8px 16px; background: rgba(102, 126, 234, 0.2); color: #667eea; border: 1px solid rgba(102, 126, 234, 0.4); border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 500;">📤 Upload Timetable</a>
                                    <a href="modules.php" target="_blank" style="padding: 8px 16px; background: rgba(102, 126, 234, 0.2); color: #667eea; border: 1px solid rgba(102, 126, 234, 0.4); border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 500;">🔗 Link Modules</a>
                                </div>
                            </div>
                        `;
                    } else {
                        let html = '<div style="display: flex; flex-direction: column; gap: 16px; max-height: 400px; overflow-y: auto; padding: 5px;">';
                        
                        // Show modules grouped by year
                        selectedYears.sort((a,b) => a-b).forEach(year => {
                            const yearModules = modulesByYear[year];
                            if (yearModules.length > 0) {
                                html += `
                                    <div style="border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 12px;">
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                                            <span style="padding: 4px 12px; background: rgba(102, 126, 234, 0.2); color: #667eea; border-radius: 6px; font-size: 12px; font-weight: 600;">Year ${year}</span>
                                            <span style="color: rgba(255,255,255,0.5); font-size: 11px;">${yearModules.length} module${yearModules.length !== 1 ? 's' : ''}</span>
                                        </div>
                                        <div style="display: flex; flex-direction: column; gap: 6px;">
                                `;
                                
                                yearModules.forEach(module => {
                                    const moduleCode = escapeHtml((module.module_code || '').trim());
                                    let moduleName = escapeHtml((module.module_name || '').trim());
                                    
                                    // If name is empty or same as code, don't show it separately
                                    if (!moduleName || moduleName === moduleCode || moduleName.toLowerCase() === moduleCode.toLowerCase()) {
                                        moduleName = '';
                                    }
                                    
                                    // Format display text
                                    let displayText = moduleCode;
                                    if (moduleName) {
                                        displayText += ' - ' + moduleName;
                                    }
                                    if (module.credits) {
                                        displayText += ` (${module.credits} credits)`;
                                    }
                                    
                                    html += `
                                        <label style="display: flex; align-items: center; gap: 10px; padding: 8px 10px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 6px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='rgba(102, 126, 234, 0.12)'; this.style.borderColor='rgba(102, 126, 234, 0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.03)'; this.style.borderColor='rgba(255,255,255,0.06)'">
                                            <input type="checkbox" name="modules[]" value="${module.module_id}" style="cursor: pointer; width: 18px; height: 18px; flex-shrink: 0;">
                                            <span style="color: rgba(255,255,255,0.9); font-size: 13px; line-height: 1.4; flex: 1;">
                                                ${displayText}
                                            </span>
                                        </label>
                                    `;
                                });
                                
                                html += `
                                        </div>
                                    </div>
                                `;
                            }
                        });
                        
                        html += '</div>';
                        container.innerHTML = html;
                    }
                }
                
                // Auto-load modules if program and year are already selected (e.g., on page load for edit)
                document.addEventListener('DOMContentLoaded', function() {
                    const programId = document.getElementById('formProgramId')?.value;
                    if (programId) {
                        loadFormModules();
                    }
                });
                
                function openModulesModal(studentId) {
                    currentStudentId = studentId;
                    const modal = document.getElementById('modulesModal');
                    const student = studentsData.find(s => s.student_id == studentId);
                    const modules = studentModulesData[studentId] || [];
                    
                    document.getElementById('modalStudentName').textContent = `${student.full_name} - Modules`;
                    document.getElementById('modalStudentId').value = studentId;
                    
                    // Set program and year if student has them
                    if (student.program_id) {
                        document.getElementById('modalProgramId').value = student.program_id;
                    }
                    if (student.year_level) {
                        document.getElementById('modalYearLevel').value = student.year_level;
                    }
                    
                    // Show existing modules if any
                    const modulesList = document.getElementById('modulesList');
                    if (modules.length > 0) {
                        modulesList.style.display = 'block';
                        modulesList.innerHTML = `
                            <h4 style="color: rgba(255,255,255,0.9); font-size: 16px; font-weight: 600; margin-bottom: 15px;">Enrolled Modules</h4>
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                                        <th style="text-align: left; padding: 12px; color: rgba(255,255,255,0.7); font-size: 13px; font-weight: 600;">Module Code</th>
                                        <th style="text-align: left; padding: 12px; color: rgba(255,255,255,0.7); font-size: 13px; font-weight: 600;">Module Name</th>
                                        <th style="text-align: left; padding: 12px; color: rgba(255,255,255,0.7); font-size: 13px; font-weight: 600;">Credits</th>
                                        <th style="text-align: left; padding: 12px; color: rgba(255,255,255,0.7); font-size: 13px; font-weight: 600;">Status</th>
                                        <th style="text-align: left; padding: 12px; color: rgba(255,255,255,0.7); font-size: 13px; font-weight: 600;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${modules.map(module => `
                                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                            <td style="padding: 12px; color: rgba(255,255,255,0.9);"><strong>${escapeHtml(module.module_code)}</strong></td>
                                            <td style="padding: 12px; color: rgba(255,255,255,0.8);">${escapeHtml(module.module_name)}</td>
                                            <td style="padding: 12px; color: rgba(255,255,255,0.7);">${module.credits || '-'}</td>
                                            <td style="padding: 12px;">
                                                <span style="padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 500; background: ${module.status === 'active' ? 'rgba(39, 174, 96, 0.2)' : 'rgba(231, 76, 60, 0.2)'}; color: ${module.status === 'active' ? '#27ae60' : '#e74c3c'};">
                                                    ${module.status === 'active' ? 'Active' : 'Inactive'}
                                                </span>
                                            </td>
                                            <td style="padding: 12px;">
                                                <a href="student_modules.php?delete=1&id=${module.id}" class="btn btn-danger" style="padding: 6px 12px; font-size: 11px;" onclick="event.preventDefault(); showCustomConfirm('Remove this module enrollment?', function(){ window.location.href = this.getAttribute('href'); }.bind(this)); return false;">Remove</a>
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        `;
                    } else {
                        modulesList.style.display = 'none';
                    }
                    
                    // Auto-load modules if program and year are set
                    if (student.program_id && student.year_level) {
                        loadModulesForProgram();
                    } else {
                        document.getElementById('programYearMessage').style.display = 'block';
                    }
                    
                    modal.style.display = 'flex';
                }
                
                // Auto-load modules when both program and year are selected
                function autoLoadModulesIfReady() {
                    const programId = parseInt(document.getElementById('modalProgramId').value);
                    const yearLevel = parseInt(document.getElementById('modalYearLevel').value);
                    
                    // Only auto-load if both are selected
                    if (programId && yearLevel) {
                        loadModulesForProgram();
                    } else {
                        // Clear the module select and hide sections if either is missing
                        const moduleSelect = document.getElementById('modalModuleSelect');
                        if (moduleSelect) {
                            moduleSelect.innerHTML = '<option value="">Choose a module...</option>';
                        }
                        document.getElementById('addModuleSection').style.display = 'none';
                        document.getElementById('programYearMessage').style.display = 'block';
                        document.getElementById('programYearMessage').textContent = 'Please select both program and year to view available modules.';
                        document.getElementById('programYearMessage').style.color = 'rgba(255,255,255,0.5)';
                    }
                }
                
                function loadModulesForProgram() {
                    const programId = parseInt(document.getElementById('modalProgramId').value);
                    const yearLevel = parseInt(document.getElementById('modalYearLevel').value);
                    
                    if (!programId || !yearLevel) {
                        showCustomPopup('Please select both program and year level', 'warning');
                        return;
                    }
                    
                    // Update hidden form fields
                    document.getElementById('modalFormProgramId').value = programId;
                    document.getElementById('modalFormYearLevel').value = yearLevel;
                    
                    // Get modules for this program and year
                    let availableModules = [];
                    if (programModulesData[programId] && programModulesData[programId][yearLevel]) {
                        availableModules = programModulesData[programId][yearLevel];
                    }
                    
                    // Debug: Log what we found (can remove in production)
                    console.log('Program ID:', programId, 'Year Level:', yearLevel);
                    console.log('Available modules for this year:', availableModules.length);
                    if (programModulesData[programId]) {
                        console.log('Available years for this program:', Object.keys(programModulesData[programId]));
                    }
                    
                    // Populate module select
                    const moduleSelect = document.getElementById('modalModuleSelect');
                    moduleSelect.innerHTML = '<option value="">Choose a module...</option>';
                    
                    if (availableModules.length === 0) {
                        // Check if there are ANY modules for this program (any year)
                        let hasAnyModules = false;
                        if (programModulesData[programId]) {
                            for (let year in programModulesData[programId]) {
                                if (programModulesData[programId][year].length > 0) {
                                    hasAnyModules = true;
                                    break;
                                }
                            }
                        }
                        
                        if (hasAnyModules) {
                            // Show message that no modules for this specific year
                            document.getElementById('programYearMessage').textContent = `No modules are linked to this program for Year ${yearLevel}. Try selecting a different year level, or add modules below to link them to this year.`;
                            document.getElementById('programYearMessage').style.display = 'block';
                            document.getElementById('programYearMessage').style.color = 'rgba(231, 76, 60, 0.8)';
                        } else {
                            // No modules linked to this program at all
                            document.getElementById('programYearMessage').textContent = 'No modules are linked to this program. You can add any module below, and it will be automatically linked to this program and year.';
                            document.getElementById('programYearMessage').style.display = 'block';
                            document.getElementById('programYearMessage').style.color = 'rgba(241, 196, 15, 0.8)';
                        }
                        
                        // Still show all modules so user can add them
                        allModulesData.forEach(module => {
                            const option = document.createElement('option');
                            option.value = module.module_id;
                            option.textContent = `${module.module_code} - ${module.module_name}`;
                            moduleSelect.appendChild(option);
                        });
                    } else {
                        // Show only modules for this specific year
                        availableModules.forEach(module => {
                            const option = document.createElement('option');
                            option.value = module.module_id;
                            option.textContent = `${module.module_code} - ${module.module_name}`;
                            moduleSelect.appendChild(option);
                        });
                        document.getElementById('programYearMessage').textContent = `Showing ${availableModules.length} module(s) for Year ${yearLevel}.`;
                        document.getElementById('programYearMessage').style.display = 'block';
                        document.getElementById('programYearMessage').style.color = 'rgba(39, 174, 96, 0.8)';
                    }
                    
                    // Show add module section
                    document.getElementById('addModuleSection').style.display = 'block';
                }
                
                function closeModulesModal() {
                    document.getElementById('modulesModal').style.display = 'none';
                }
                
                function escapeHtml(text) {
                    const div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                }
                
                // Close modal when clicking outside
                document.getElementById('modulesModal').addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeModulesModal();
                    }
                });
                
                // Handle add module form submission
                document.getElementById('addModuleForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    formData.append('status', 'active');
                    
                    // Ensure program_id and year_level are included
                    const programId = formData.get('program_id');
                    const yearLevel = formData.get('year_level');
                    const moduleId = formData.get('module_id');
                    
                    if (!programId || !yearLevel || !moduleId) {
                        showCustomPopup('Please select program, year level, and module first', 'warning');
                        return;
                    }
                    
                    // First, ensure the module is linked to the program and year
                    fetch('link_program_module.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `program_id=${programId}&module_id=${moduleId}&year_level=${yearLevel}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        // Then enroll the student
                        formData.append('redirect', 'students.php');
                        return fetch('student_modules.php', {
                            method: 'POST',
                            body: formData
                        });
                    })
                    .then(response => {
                        if (response.ok) {
                            location.reload();
                        } else {
                            showCustomPopup('Error adding module. Please try again.', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showCustomPopup('Error adding module. Please try again.', 'error');
                    });
                });
                
                // Bulk operations
                document.addEventListener('DOMContentLoaded', function() {
                    const selectAll = document.getElementById('selectAll');
                    const checkboxes = document.querySelectorAll('.item-checkbox');
                    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
                    const bulkForm = document.getElementById('bulkForm');
                    
                    if (selectAll) {
                        selectAll.addEventListener('change', function() {
                            checkboxes.forEach(cb => cb.checked = this.checked);
                            updateBulkButton();
                        });
                    }
                    
                    checkboxes.forEach(cb => {
                        cb.addEventListener('change', function() {
                            updateBulkButton();
                            if (selectAll) {
                                selectAll.checked = checkboxes.length === document.querySelectorAll('.item-checkbox:checked').length;
                            }
                        });
                    });
                    
                    function updateBulkButton() {
                        const checked = document.querySelectorAll('.item-checkbox:checked').length;
                        if (bulkDeleteBtn) {
                            bulkDeleteBtn.style.display = checked > 0 ? 'inline-block' : 'none';
                            bulkDeleteBtn.textContent = `🗑️ Delete Selected (${checked})`;
                        }
                    }
                    
                    if (bulkDeleteBtn) {
                        bulkDeleteBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            const checked = document.querySelectorAll('.item-checkbox:checked').length;
                            if (checked === 0) return;
                            
                            showCustomConfirm(`Are you sure you want to delete ${checked} student(s)? This action cannot be undone.`, function() {
                                showLoading();
                                bulkForm.submit();
                            });
                        });
                    }
                });
            </script>
            <script>
                // Wire modern table, filters, bulk toolbar
                document.addEventListener('DOMContentLoaded', function() {
                    UI.initTable(
                        document.getElementById('studentsTable'),
                        {
                            searchSelector: '[data-table-search]',
                            perPageSelector: '[data-page-size]',
                            paginationSelector: '[data-pagination]',
                            filterSelectors: {
                                program: 'td:nth-child(6)',
                                year: 'td:nth-child(7)'
                            }
                        }
                    );
                    UI.initBulkToolbar('#bulkToolbar', '#studentsTable', '#bulkForm');
                });
                
                // Toggle student form
                function toggleStudentForm() {
                    const formContent = document.getElementById('studentFormContent');
                    const toggleIcon = document.getElementById('formToggleIcon');
                    
                    if (formContent.style.display === 'none' || formContent.style.display === '') {
                        formContent.style.display = 'block';
                        toggleIcon.style.transform = 'rotate(180deg)';
                    } else {
                        formContent.style.display = 'none';
                        toggleIcon.style.transform = 'rotate(0deg)';
                    }
                }
            </script>

<?php include 'footer_modern.php'; ?>

