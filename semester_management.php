<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin/login.php');
    exit;
}

require_once 'admin/config.php';
require_once __DIR__ . '/includes/database.php';

$pdo = Database::getInstance()->getConnection();

$message = '';
$messageType = '';

// Handle data clearing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_data'])) {
    $clearType = $_POST['clear_type'] ?? '';
    
    if (!isset($_POST['confirm_checkbox'])) {
        $message = 'Please check the confirmation box to proceed.';
        $messageType = 'error';
    } else {
        try {
            $pdo->beginTransaction();
            
            if ($clearType === 'timetable') {
                // Clear timetable data only
                $pdo->exec("DELETE FROM sessions");
                $pdo->exec("DELETE FROM modules");
                $pdo->exec("DELETE FROM lecturers");
                $pdo->exec("DELETE FROM venues");
                $pdo->exec("DELETE FROM student_modules");
                $pdo->exec("DELETE FROM exams");
                $message = 'Timetable data cleared successfully! (Sessions, Modules, Lecturers, Venues, Enrollments, Exams)';
            } elseif ($clearType === 'all') {
                // Clear everything including students
                $pdo->exec("DELETE FROM sessions");
                $pdo->exec("DELETE FROM student_modules");
                $pdo->exec("DELETE FROM exams");
                $pdo->exec("DELETE FROM study_sessions");
                $pdo->exec("DELETE FROM exam_notifications");
                $pdo->exec("DELETE FROM modules");
                $pdo->exec("DELETE FROM lecturers");
                $pdo->exec("DELETE FROM venues");
                $pdo->exec("DELETE FROM students");
                $message = 'All data cleared successfully!';
            } elseif ($clearType === 'sessions_only') {
                // Clear only sessions (keep modules, lecturers, venues)
                $pdo->exec("DELETE FROM sessions");
                $pdo->exec("DELETE FROM student_modules");
                $message = 'Sessions and enrollments cleared! (Modules, Lecturers, and Venues kept)';
            }
            
            $pdo->commit();
            $messageType = 'success';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = 'Error clearing data: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get current statistics
$stats = [
    'modules' => $pdo->query("SELECT COUNT(*) FROM modules")->fetchColumn(),
    'sessions' => $pdo->query("SELECT COUNT(*) FROM sessions")->fetchColumn(),
    'students' => $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn(),
    'lecturers' => $pdo->query("SELECT COUNT(*) FROM lecturers")->fetchColumn(),
    'venues' => $pdo->query("SELECT COUNT(*) FROM venues")->fetchColumn(),
    'enrollments' => $pdo->query("SELECT COUNT(*) FROM student_modules")->fetchColumn(),
    'exams' => $pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn(),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semester Management - Smart Timetable</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0a0a0a;
            color: #ffffff;
            min-height: 100vh;
            overflow-x: hidden;
        }
        .container { display: flex; min-height: 100vh; }
        
        /* Sidebar - Same as dashboard */
        .sidebar {
            width: 280px;
            background: #0a0a0a;
            padding: 32px 24px;
            border-right: 1px solid rgba(255,255,255,0.08);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        .sidebar::-webkit-scrollbar { width: 6px; }
        .sidebar::-webkit-scrollbar-track { background: transparent; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 3px; }
        
        .sidebar-header {
            margin-bottom: 48px;
            padding-bottom: 24px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .sidebar-header h1 {
            font-size: 22px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }
        .admin-console {
            display: flex;
            align-items: center;
            gap: 8px;
            color: rgba(255,255,255,0.6);
            font-size: 13px;
            margin-bottom: 8px;
            font-weight: 500;
        }
        .sidebar-section {
            margin-bottom: 32px;
        }
        .sidebar-section-title {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: rgba(255,255,255,0.4);
            margin-bottom: 16px;
            padding: 0 12px;
            font-weight: 600;
        }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 4px;
            transition: all 0.2s ease;
            font-size: 14px;
            font-weight: 500;
        }
        .sidebar-nav a:hover {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }
        .sidebar-nav a.active {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%);
            color: #667eea;
            border-left: 3px solid #667eea;
        }
        .sidebar-nav a i {
            margin-right: 12px;
            width: 20px;
            font-style: normal;
            font-size: 16px;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 48px;
            background: #0a0a0a;
        }
        
        .page-header {
            margin-bottom: 32px;
        }
        .page-header h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -1px;
        }
        .page-header p {
            color: rgba(255,255,255,0.6);
            font-size: 14px;
        }
        
        /* Back button */
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            font-size: 14px;
            padding: 8px 16px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            transition: all 0.2s;
            margin-bottom: 24px;
        }
        .back-btn:hover {
            background: rgba(102, 126, 234, 0.1);
            border-color: rgba(102, 126, 234, 0.3);
            color: #667eea;
        }
        
        /* Message banner */
        .message-banner {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 500;
        }
        .message-banner.success {
            background: rgba(39, 174, 96, 0.2);
            border: 1px solid rgba(39, 174, 96, 0.3);
            color: #27ae60;
        }
        .message-banner.error {
            background: rgba(231, 76, 60, 0.2);
            border: 1px solid rgba(231, 76, 60, 0.3);
            color: #e74c3c;
        }
        
        /* Stats grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }
        .stat-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 20px;
        }
        .stat-label {
            font-size: 12px;
            color: rgba(255,255,255,0.5);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
            font-weight: 600;
        }
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        /* Clear data card */
        .clear-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 24px;
        }
        .clear-card h2 {
            font-size: 24px;
            margin-bottom: 12px;
            font-weight: 700;
        }
        .clear-card p {
            color: rgba(255,255,255,0.6);
            margin-bottom: 24px;
            line-height: 1.6;
        }
        .clear-options {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-bottom: 24px;
        }
        .clear-option {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .clear-option:hover {
            border-color: rgba(102, 126, 234, 0.5);
            background: rgba(102, 126, 234, 0.05);
        }
        .clear-option input[type="radio"] {
            margin-right: 12px;
            cursor: pointer;
        }
        .clear-option label {
            cursor: pointer;
            font-weight: 500;
            display: block;
            margin-bottom: 8px;
        }
        .clear-option-desc {
            font-size: 13px;
            color: rgba(255,255,255,0.5);
            margin-left: 28px;
        }
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(231, 76, 60, 0.3);
        }
        .btn-danger:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h1>SMART TIMETABLE</h1>
                <div class="admin-console">
                    <span>⚙️</span>
                    <span>Admin Console</span>
                </div>
            </div>
            
            <div class="sidebar-section">
                <div class="sidebar-section-title">Overview</div>
                <nav class="sidebar-nav">
                    <a href="admin/index.php"><i>📊</i> Dashboard</a>
                </nav>
            </div>
            
            <div class="sidebar-section">
                <div class="sidebar-section-title">Academic Structure</div>
                <nav class="sidebar-nav">
                    <a href="#"><i>🏛️</i> Faculties</a>
                    <a href="#"><i>🏫</i> Schools</a>
                    <a href="#"><i>📜</i> Programmes</a>
                    <a href="#"><i>📅</i> Academic Years</a>
                </nav>
            </div>
            
            <div class="sidebar-section">
                <div class="sidebar-section-title">People & Resources</div>
                <nav class="sidebar-nav">
                    <a href="admin/students.php"><i>👥</i> Students</a>
                    <a href="admin/lecturers.php"><i>👤</i> Lecturers</a>
                    <a href="admin/venues.php"><i>📍</i> Venues</a>
                </nav>
            </div>
            
            <div class="sidebar-section">
                <div class="sidebar-section-title">Curriculum & Timetable</div>
                <nav class="sidebar-nav">
                    <a href="admin/modules.php"><i>📚</i> Modules</a>
                    <a href="admin/timetable.php"><i>➕</i> Add Session</a>
                    <a href="timetable_editor.php"><i>✏️</i> Edit Sessions</a>
                    <a href="#"><i>📋</i> View Timetable</a>
                    <a href="timetable_pdf_parser.php"><i>📤</i> Upload Timetable</a>
                    <a href="admin/exams.php"><i>📆</i> Exam Timetables</a>
                </nav>
            </div>
            
            <div class="sidebar-section">
                <div class="sidebar-section-title">System</div>
                <nav class="sidebar-nav">
                    <a href="semester_management.php" class="active"><i>📄⚙️</i> Semester Management</a>
                </nav>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <a href="admin/index.php" class="back-btn">
                <span>←</span>
                <span>Back to Dashboard</span>
            </a>
            
            <div class="page-header">
                <h1>Semester Management</h1>
                <p>Clear data and manage academic periods</p>
            </div>
            
            <?php if ($message): ?>
            <div class="message-banner <?= $messageType ?>">
                <span><?= $messageType === 'error' ? '❌' : '✅' ?></span>
                <span><?= htmlspecialchars($message) ?></span>
            </div>
            <?php endif; ?>
            
            <!-- Current Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Modules</div>
                    <div class="stat-value"><?= $stats['modules'] ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Sessions</div>
                    <div class="stat-value"><?= $stats['sessions'] ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Students</div>
                    <div class="stat-value"><?= $stats['students'] ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Lecturers</div>
                    <div class="stat-value"><?= $stats['lecturers'] ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Venues</div>
                    <div class="stat-value"><?= $stats['venues'] ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Enrollments</div>
                    <div class="stat-value"><?= $stats['enrollments'] ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Exams</div>
                    <div class="stat-value"><?= $stats['exams'] ?></div>
                </div>
            </div>
            
            <!-- Clear Data Card -->
            <div class="clear-card">
                <h2>⚠️ Clear Data</h2>
                <p>Use this section to clear data before testing the parser or starting a new semester. <strong>This action cannot be undone!</strong></p>
                
                <form method="POST" onsubmit="return confirmClear()">
                    <div class="clear-options">
                        <div class="clear-option">
                            <input type="radio" name="clear_type" id="sessions_only" value="sessions_only" required>
                            <label for="sessions_only">Clear Sessions Only</label>
                            <div class="clear-option-desc">Removes all timetable sessions and student enrollments. Keeps modules, lecturers, and venues.</div>
                        </div>
                        
                        <div class="clear-option">
                            <input type="radio" name="clear_type" id="timetable" value="timetable" required>
                            <label for="timetable">Clear All Timetable Data</label>
                            <div class="clear-option-desc">Removes sessions, modules, lecturers, venues, enrollments, and exams. Keeps students.</div>
                        </div>
                        
                        <div class="clear-option">
                            <input type="radio" name="clear_type" id="all" value="all" required>
                            <label for="all">Clear Everything</label>
                            <div class="clear-option-desc">Removes all data including students. Complete reset.</div>
                        </div>
                    </div>
                    
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px; padding: 16px; background: rgba(231, 76, 60, 0.1); border: 1px solid rgba(231, 76, 60, 0.3); border-radius: 8px;">
                        <input 
                            type="checkbox" 
                            name="confirm_checkbox" 
                            id="confirm_checkbox"
                            required
                            style="width: 20px; height: 20px; cursor: pointer;"
                        >
                        <label for="confirm_checkbox" style="cursor: pointer; font-weight: 500; color: rgba(255,255,255,0.9);">
                            I understand this action cannot be undone
                        </label>
                    </div>
                    
                    <button type="submit" name="clear_data" class="btn-danger" id="clearBtn" disabled>Clear Data</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Enable/disable clear button based on checkbox
        document.addEventListener('DOMContentLoaded', function() {
            const checkbox = document.getElementById('confirm_checkbox');
            const clearBtn = document.getElementById('clearBtn');
            
            checkbox.addEventListener('change', function() {
                clearBtn.disabled = !this.checked;
            });
        });
        
        function confirmClear() {
            const clearType = document.querySelector('input[name="clear_type"]:checked')?.value;
            const isConfirmed = document.getElementById('confirm_checkbox').checked;
            
            if (!isConfirmed) {
                alert('Please check the confirmation box to proceed.');
                return false;
            }
            
            let message = '';
            if (clearType === 'sessions_only') {
                message = 'Are you sure you want to clear all sessions and enrollments? This cannot be undone!';
            } else if (clearType === 'timetable') {
                message = 'Are you sure you want to clear ALL timetable data (modules, sessions, lecturers, venues, enrollments, exams)? This cannot be undone!';
            } else if (clearType === 'all') {
                message = '⚠️ WARNING: This will delete EVERYTHING including all students! Are you absolutely sure?';
            }
            
            return confirm(message);
        }
    </script>
</body>
</html>

