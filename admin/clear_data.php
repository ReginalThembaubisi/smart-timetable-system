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

// Define tables that can be cleared (in order of dependencies - child tables first)
$clearableTables = [
    'exam_notifications' => ['name' => 'Exam Notifications', 'icon' => '🔔'],
    'student_modules' => ['name' => 'Student Enrollments', 'icon' => '📚'],
    'exams' => ['name' => 'Exams', 'icon' => '📝'],
    'sessions' => ['name' => 'Timetable Sessions', 'icon' => '📅'],
    'program_modules' => ['name' => 'Program-Module Links', 'icon' => '🔗'],
    'students' => ['name' => 'Students', 'icon' => '👥'],
    'modules' => ['name' => 'Modules', 'icon' => '📖'],
    'lecturers' => ['name' => 'Lecturers', 'icon' => '👨‍🏫'],
    'venues' => ['name' => 'Venues', 'icon' => '🏢'],
    'programs' => ['name' => 'Programs', 'icon' => '🎓'],
];

// Tables that should NOT be cleared (for safety)
$protectedTables = ['admins', 'activity_log'];

// Get record counts for all tables
$tableCounts = [];
foreach ($clearableTables as $table => $info) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        $tableCounts[$table] = $count;
    } catch (Exception $e) {
        $tableCounts[$table] = 0;
    }
}

// Handle clear action
$cleared = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug logging
    error_log('Clear Data POST received: ' . print_r($_POST, true));
    
    if (isset($_POST['clear_all_except_students'])) {
        // Clear all tables except students
        $tablesToClear = array_keys($clearableTables);
        $tablesToClear = array_diff($tablesToClear, ['students']);
        error_log('Quick clear: Tables to clear: ' . implode(', ', $tablesToClear));
    } elseif (isset($_POST['clear_tables'])) {
        // Clear selected tables
        $tablesToClear = isset($_POST['tables']) ? $_POST['tables'] : [];
        error_log('Advanced clear: Tables to clear: ' . implode(', ', $tablesToClear));
    } else {
        $tablesToClear = [];
        error_log('No clear action detected in POST');
    }
    
    if (!empty($tablesToClear)) {
        // Temporarily disable foreign key checks to allow TRUNCATE
        try {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            error_log('Foreign key checks disabled');
        } catch (Exception $e) {
            logError($e, "Disabling foreign key checks");
            error_log('Error disabling foreign key checks: ' . $e->getMessage());
        }
        
        // Clear tables in dependency order (child tables first)
        // Use DELETE instead of TRUNCATE because TRUNCATE doesn't work with foreign keys
        foreach ($clearableTables as $table => $info) {
            if (in_array($table, $tablesToClear)) {
                try {
                    // Check if table exists first
                    $tableExists = $pdo->query("SHOW TABLES LIKE '{$table}'")->rowCount() > 0;
                    if (!$tableExists) {
                        error_log("Table {$table} does not exist, skipping");
                        continue;
                    }
                    
                    // Use DELETE FROM instead of TRUNCATE (TRUNCATE fails with foreign keys)
                    $pdo->exec("DELETE FROM `{$table}`");
                    
                    // Reset AUTO_INCREMENT only if table has an AUTO_INCREMENT column
                    try {
                        $pdo->exec("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
                    } catch (Exception $e) {
                        // Table might not have AUTO_INCREMENT, ignore this error
                        error_log("Could not reset AUTO_INCREMENT for {$table}: " . $e->getMessage());
                    }
                    
                    $cleared[] = $info['name'];
                    logActivity('data_cleared', "Cleared table: {$table}", getCurrentUserId());
                    error_log("Successfully cleared table: {$table}");
                } catch (Exception $e) {
                    logError($e, "Clearing table: {$table}");
                    $errorMsg = getErrorMessage($e, 'Clearing data', false);
                    $errors[] = "Error clearing {$info['name']}: {$errorMsg}";
                    error_log("Error clearing table {$table}: " . $e->getMessage());
                }
            }
        }
        
        // Re-enable foreign key checks
        try {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            error_log('Foreign key checks re-enabled');
        } catch (Exception $e) {
            logError($e, "Re-enabling foreign key checks");
            error_log('Error re-enabling foreign key checks: ' . $e->getMessage());
        }
        
        if (!empty($cleared)) {
            $_SESSION['success_message'] = 'Successfully cleared: ' . implode(', ', $cleared);
            error_log('Success message set: ' . $_SESSION['success_message']);
        }
        if (!empty($errors)) {
            $_SESSION['error_message'] = implode('<br>', $errors);
            error_log('Error message set: ' . $_SESSION['error_message']);
        }
        
        // Refresh page to show updated counts
        error_log('Redirecting to clear_data.php');
        header('Location: clear_data.php');
        exit;
    } elseif (isset($_POST['clear_tables']) && empty($tablesToClear)) {
        $errors[] = 'No tables selected to clear.';
        $_SESSION['error_message'] = implode('<br>', $errors);
        error_log('No tables selected error');
    } else {
        error_log('No tables to clear - tablesToClear is empty');
    }
}

// Get updated counts after clearing
foreach ($clearableTables as $table => $info) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        $tableCounts[$table] = $count;
    } catch (Exception $e) {
        $tableCounts[$table] = 0;
    }
}

$breadcrumbs = [
    ['label' => 'Dashboard', 'href' => 'index.php'],
    ['label' => 'Clear Data', 'href' => null],
];
$page_actions = [];
include 'header_modern.php';
?>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success" style="background: linear-gradient(135deg, rgba(39, 174, 96, 0.15) 0%, rgba(39, 174, 96, 0.05) 100%); border: 1px solid rgba(39, 174, 96, 0.3); border-left: 4px solid #27ae60; padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 4px 12px rgba(39, 174, 96, 0.1);">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span style="font-size: 20px;">✅</span>
                        <span style="color: #27ae60; font-weight: 500;"><?= htmlspecialchars($_SESSION['success_message']) ?></span>
                    </div>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-error" style="background: linear-gradient(135deg, rgba(231, 76, 60, 0.15) 0%, rgba(231, 76, 60, 0.05) 100%); border: 1px solid rgba(231, 76, 60, 0.3); border-left: 4px solid #e74c3c; padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 4px 12px rgba(231, 76, 60, 0.1);">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span style="font-size: 20px;">❌</span>
                        <div style="color: #e74c3c; font-weight: 500;"><?= $_SESSION['error_message'] ?></div>
                    </div>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
            
            <!-- Hero Section -->
            <div style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(139, 92, 246, 0.05) 100%); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: 16px; padding: 32px; margin-bottom: 32px; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);">
                <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 16px;">
                    <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 28px; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);">
                        🗑️
                    </div>
                    <div style="flex: 1;">
                        <h2 style="margin: 0 0 8px 0; color: rgba(255,255,255,0.95); font-weight: 700; font-size: 24px; letter-spacing: -0.5px;">Clear System Data</h2>
                        <p style="margin: 0; color: rgba(255,255,255,0.7); font-size: 14px; line-height: 1.6;">
                            Reset your timetable system while preserving student accounts. Admin accounts and activity logs are always protected.
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Quick Clear Section -->
            <div style="background: linear-gradient(135deg, rgba(30, 30, 46, 0.8) 0%, rgba(20, 20, 36, 0.9) 100%); border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; padding: 48px; margin-bottom: 32px; text-align: center; box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3); position: relative; overflow: hidden;">
                <div style="position: absolute; top: -50%; right: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(99, 102, 241, 0.1) 0%, transparent 70%); pointer-events: none;"></div>
                
                <div style="position: relative; z-index: 1;">
                    <div style="margin-bottom: 32px;">
                        <div style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; background: rgba(99, 102, 241, 0.15); border: 1px solid rgba(99, 102, 241, 0.3); border-radius: 20px; margin-bottom: 20px;">
                            <span style="font-size: 16px;">⚡</span>
                            <span style="color: rgba(255,255,255,0.9); font-size: 13px; font-weight: 600; letter-spacing: 0.5px;">QUICK CLEAR</span>
                        </div>
                        <h3 style="margin: 0 0 16px 0; color: rgba(255,255,255,0.95); font-size: 28px; font-weight: 700; letter-spacing: -0.5px;">One-Click Reset</h3>
                        <p style="color: rgba(255,255,255,0.7); font-size: 16px; margin: 0 0 8px 0; line-height: 1.6; max-width: 600px; margin-left: auto; margin-right: auto;">
                            Clear all timetable data, modules, lecturers, venues, and enrollments in one action
                        </p>
                        <div style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: rgba(39, 174, 96, 0.15); border: 1px solid rgba(39, 174, 96, 0.3); border-radius: 12px; margin-top: 12px;">
                            <span style="font-size: 18px;">🛡️</span>
                            <span style="color: #27ae60; font-size: 14px; font-weight: 600;">Students will be preserved</span>
                        </div>
                    </div>
                    
                    <form method="POST" id="quickClearForm" style="display: inline-block;">
                        <button type="submit" name="clear_all_except_students" value="1" style="
                            padding: 18px 48px; 
                            font-size: 16px; 
                            font-weight: 600; 
                            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
                            border: none;
                            border-radius: 12px;
                            color: white;
                            cursor: pointer;
                            box-shadow: 0 8px 24px rgba(231, 76, 60, 0.4);
                            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                            position: relative;
                            overflow: hidden;
                        " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 12px 32px rgba(231, 76, 60, 0.5)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 8px 24px rgba(231, 76, 60, 0.4)'">
                            <span style="display: inline-flex; align-items: center; gap: 10px;">
                                <span style="font-size: 20px;">🗑️</span>
                                <span>Clear All Data (Except Students)</span>
                            </span>
                        </button>
                    </form>
                    
                    <div style="margin-top: 32px; padding: 20px; background: linear-gradient(135deg, rgba(231, 76, 60, 0.15) 0%, rgba(231, 76, 60, 0.05) 100%); border: 1px solid rgba(231, 76, 60, 0.3); border-radius: 12px; max-width: 600px; margin-left: auto; margin-right: auto; box-shadow: 0 4px 12px rgba(231, 76, 60, 0.1);">
                        <div style="display: flex; align-items: start; gap: 12px;">
                            <span style="font-size: 24px; flex-shrink: 0;">⚠️</span>
                            <p style="color: rgba(231, 76, 60, 0.95); font-size: 13px; margin: 0; line-height: 1.6; font-weight: 500;">
                                This will permanently delete all data except students. This action cannot be undone!
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Advanced Options (Collapsible) -->
            <div style="background: var(--bg-card); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 24px; box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);">
                <details style="cursor: pointer;">
                    <summary style="
                        padding: 16px 20px; 
                        background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(139, 92, 246, 0.05) 100%); 
                        border: 1px solid rgba(99, 102, 241, 0.2); 
                        border-radius: 12px; 
                        font-weight: 600; 
                        color: rgba(255,255,255,0.9); 
                        font-size: 15px; 
                        user-select: none;
                        display: flex;
                        align-items: center;
                        gap: 12px;
                        transition: all 0.2s;
                    " onmouseover="this.style.background='linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(139, 92, 246, 0.08) 100%)'" onmouseout="this.style.background='linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(139, 92, 246, 0.05) 100%)'">
                        <span style="font-size: 20px;">🔧</span>
                        <span>Advanced: Select Individual Tables</span>
                    </summary>
                    
                    <form method="POST" id="clearForm" onsubmit="return confirmClear()" style="margin-top: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h3 style="margin: 0; color: rgba(255,255,255,0.9); font-size: 16px; font-weight: 600;">Select Tables to Clear</h3>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" onclick="selectAll()" class="btn" style="padding: 6px 12px; font-size: 12px;">Select All</button>
                                <button type="button" onclick="deselectAll()" class="btn" style="padding: 6px 12px; font-size: 12px;">Deselect All</button>
                            </div>
                        </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; margin-top: 24px;">
                        <?php foreach ($clearableTables as $table => $info): ?>
                            <label style="
                                display: flex; 
                                align-items: center; 
                                gap: 16px; 
                                padding: 20px; 
                                background: linear-gradient(135deg, rgba(255,255,255,0.04) 0%, rgba(255,255,255,0.02) 100%); 
                                border: 2px solid rgba(255,255,255,0.08); 
                                border-radius: 14px; 
                                cursor: pointer; 
                                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                                position: relative;
                                overflow: hidden;
                            " 
                                   onmouseover="this.style.background='linear-gradient(135deg, rgba(99, 102, 241, 0.12) 0%, rgba(139, 92, 246, 0.06) 100%)'; this.style.borderColor='rgba(99, 102, 241, 0.4)'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 24px rgba(99, 102, 241, 0.2)'" 
                                   onmouseout="this.style.background='linear-gradient(135deg, rgba(255,255,255,0.04) 0%, rgba(255,255,255,0.02) 100%)'; this.style.borderColor='rgba(255,255,255,0.08)'; this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                                <input type="checkbox" name="tables[]" value="<?= htmlspecialchars($table) ?>" class="table-checkbox" style="cursor: pointer; width: 22px; height: 22px; flex-shrink: 0; accent-color: #6366f1;">
                                <div style="flex: 1; min-width: 0;">
                                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                        <span style="font-size: 24px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));"><?= $info['icon'] ?></span>
                                        <span style="color: rgba(255,255,255,0.95); font-size: 15px; font-weight: 600; letter-spacing: -0.2px;"><?= htmlspecialchars($info['name']) ?></span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                        <span style="color: rgba(255,255,255,0.6); font-size: 13px; font-weight: 500;"><?= number_format($tableCounts[$table]) ?> record<?= $tableCounts[$table] != 1 ? 's' : '' ?></span>
                                        <?php if ($tableCounts[$table] > 0): ?>
                                            <span style="padding: 4px 10px; background: linear-gradient(135deg, rgba(39, 174, 96, 0.25) 0%, rgba(39, 174, 96, 0.15) 100%); color: #27ae60; border-radius: 6px; font-size: 11px; font-weight: 600; border: 1px solid rgba(39, 174, 96, 0.3);">Active</span>
                                        <?php else: ?>
                                            <span style="padding: 4px 10px; background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.5); border-radius: 6px; font-size: 11px; font-weight: 500; border: 1px solid rgba(255,255,255,0.1);">Empty</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    
                    <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1);">
                        <div style="background: rgba(231, 76, 60, 0.1); border: 1px solid rgba(231, 76, 60, 0.3); border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                            <div style="display: flex; align-items: start; gap: 12px;">
                                <span style="font-size: 20px;">⚠️</span>
                                <div style="flex: 1;">
                                    <p style="color: rgba(231, 76, 60, 0.9); font-size: 14px; font-weight: 600; margin: 0 0 8px 0;">Warning: This action cannot be undone!</p>
                                    <p style="color: rgba(255,255,255,0.7); font-size: 12px; margin: 0; line-height: 1.5;">
                                        All selected data will be permanently deleted. This is useful for resetting the system for testing or starting fresh. 
                                        Admin accounts and activity logs are protected and will not be cleared.
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1);">
                            <button type="submit" name="clear_tables" class="btn btn-danger" style="padding: 10px 20px;">
                                🗑️ Clear Selected Tables
                            </button>
                        </div>
                    </form>
                </details>
            </div>
            
            <div style="background: linear-gradient(135deg, rgba(39, 174, 96, 0.1) 0%, rgba(39, 174, 96, 0.05) 100%); border: 1px solid rgba(39, 174, 96, 0.2); border-radius: 16px; padding: 24px; margin-top: 32px; box-shadow: 0 4px 16px rgba(39, 174, 96, 0.1);">
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                    <div style="width: 40px; height: 40px; background: linear-gradient(135deg, rgba(39, 174, 96, 0.2) 0%, rgba(39, 174, 96, 0.1) 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                        🛡️
                    </div>
                    <div>
                        <h3 style="margin: 0 0 4px 0; color: rgba(255,255,255,0.95); font-size: 18px; font-weight: 700;">Protected Tables</h3>
                        <p style="color: rgba(255,255,255,0.6); font-size: 13px; margin: 0;">
                            These tables are protected and cannot be cleared for security reasons
                        </p>
                    </div>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                    <?php foreach ($protectedTables as $table): ?>
                        <span style="
                            padding: 10px 16px; 
                            background: linear-gradient(135deg, rgba(39, 174, 96, 0.2) 0%, rgba(39, 174, 96, 0.1) 100%); 
                            color: #27ae60; 
                            border: 1px solid rgba(39, 174, 96, 0.3); 
                            border-radius: 10px; 
                            font-size: 13px; 
                            font-weight: 600;
                            display: inline-flex;
                            align-items: center;
                            gap: 8px;
                            box-shadow: 0 2px 8px rgba(39, 174, 96, 0.15);
                        ">
                            <span>🔒</span>
                            <span><?= htmlspecialchars($table) ?></span>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>

            <script>
                function selectAll() {
                    document.querySelectorAll('.table-checkbox').forEach(cb => cb.checked = true);
                }
                
                function deselectAll() {
                    document.querySelectorAll('.table-checkbox').forEach(cb => cb.checked = false);
                }
                
                // Initialize form handlers when DOM is ready
                document.addEventListener('DOMContentLoaded', function() {
                    // Handle quick clear form
                    const quickClearForm = document.getElementById('quickClearForm');
                    if (quickClearForm) {
                        quickClearForm.addEventListener('submit', function(e) {
                            e.preventDefault();
                            const message = 'Are you absolutely sure you want to clear ALL data except students?\n\nThis will delete:\n- All timetable sessions\n- All modules\n- All lecturers\n- All venues\n- All programs\n- All enrollments\n- All exams\n\nStudents will be preserved.\n\nThis action cannot be undone!';
                            
                            const form = this; // Capture form reference
                            showCustomConfirm(message, function() {
                                // Create a temporary form and submit it
                                const tempForm = document.createElement('form');
                                tempForm.method = 'POST';
                                tempForm.action = form.action || window.location.href;
                                
                                // Copy the submit button to preserve the name/value
                                const submitBtn = form.querySelector('button[type="submit"]');
                                if (submitBtn) {
                                    const hiddenInput = document.createElement('input');
                                    hiddenInput.type = 'hidden';
                                    hiddenInput.name = submitBtn.name;
                                    hiddenInput.value = submitBtn.value;
                                    tempForm.appendChild(hiddenInput);
                                }
                                
                                document.body.appendChild(tempForm);
                                tempForm.submit();
                            });
                        });
                    }
                    
                    // Handle advanced clear form
                    const advancedForm = document.querySelector('form[method="POST"]:not(#quickClearForm)');
                    if (advancedForm) {
                        advancedForm.addEventListener('submit', function(e) {
                            e.preventDefault();
                            const checked = document.querySelectorAll('.table-checkbox:checked');
                            if (checked.length === 0) {
                                showCustomPopup('Please select at least one table to clear.', 'warning');
                                return;
                            }
                            
                            const tableNames = Array.from(checked).map(cb => {
                                const label = cb.closest('label');
                                return label.querySelector('span').textContent.trim();
                            }).join(', ');
                            
                            const totalRecords = Array.from(checked).reduce((sum, cb) => {
                                const label = cb.closest('label');
                                const countText = label.querySelector('span[style*="color: rgba(255,255,255,0.5)"]')?.textContent || '0';
                                const count = parseInt(countText.replace(/[^\d]/g, '')) || 0;
                                return sum + count;
                            }, 0);
                            
                            const message = `Are you absolutely sure you want to clear the following tables?\n\n${tableNames}\n\nThis will delete approximately ${totalRecords.toLocaleString()} record(s) and cannot be undone!`;
                            
                            const form = this; // Capture form reference
                            showCustomConfirm(message, function() {
                                // Create a temporary form and submit it
                                const tempForm = document.createElement('form');
                                tempForm.method = 'POST';
                                tempForm.action = form.action || window.location.href;
                                
                                // Copy the submit button to preserve the name/value
                                const submitBtn = form.querySelector('button[type="submit"]');
                                if (submitBtn) {
                                    const hiddenInput = document.createElement('input');
                                    hiddenInput.type = 'hidden';
                                    hiddenInput.name = submitBtn.name;
                                    hiddenInput.value = submitBtn.value;
                                    tempForm.appendChild(hiddenInput);
                                }
                                
                                document.body.appendChild(tempForm);
                                tempForm.submit();
                            });
                        });
                    }
                });
            </script>

<?php include 'footer_modern.php'; ?>

