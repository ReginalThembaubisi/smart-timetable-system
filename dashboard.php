<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin/login.php');
    exit;
}

require_once 'admin/config.php';

$pdo = new PDO("mysql:host=localhost;dbname=smart_timetable", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get statistics
$stats = [
    'modules' => $pdo->query("SELECT COUNT(*) FROM modules")->fetchColumn(),
    'sessions' => $pdo->query("SELECT COUNT(*) FROM sessions")->fetchColumn(),
    'students' => $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn(),
    'lecturers' => $pdo->query("SELECT COUNT(*) FROM lecturers")->fetchColumn(),
    'venues' => $pdo->query("SELECT COUNT(*) FROM venues")->fetchColumn(),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Smart Timetable</title>
    <link rel="stylesheet" href="admin/style.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #e0e0e0;
            min-height: 100vh;
        }
        .container { display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #0f1419 0%, #1a2332 100%);
            padding: 30px 20px;
            border-right: 1px solid rgba(255,255,255,0.1);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        .sidebar-header {
            margin-bottom: 40px;
        }
        .sidebar-header h1 {
            font-size: 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }
        .sidebar-section {
            margin-bottom: 30px;
        }
        .sidebar-section-title {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #7f8c8d;
            margin-bottom: 15px;
            padding: 0 15px;
        }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #b0b0b0;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 5px;
            transition: all 0.3s;
        }
        .sidebar-nav a:hover, .sidebar-nav a.active {
            background: rgba(102, 126, 234, 0.2);
            color: #667eea;
        }
        .sidebar-nav a i {
            margin-right: 12px;
            width: 20px;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 40px;
        }
        
        /* Live Status Card */
        .live-status-card {
            background: linear-gradient(135deg, #1e2746 0%, #2a3a5a 100%);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .live-status-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
        }
        .greeting h2 {
            font-size: 28px;
            margin-bottom: 8px;
        }
        .greeting p {
            color: #a0a0a0;
            font-size: 14px;
        }
        .status-info {
            display: flex;
            gap: 20px;
            align-items: center;
            margin-top: 20px;
        }
        .status-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #b0b0b0;
            font-size: 14px;
        }
        .status-badge {
            background: #27ae60;
            color: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .stats-mini {
            display: flex;
            gap: 30px;
        }
        .stat-mini {
            text-align: right;
        }
        .stat-mini-label {
            font-size: 11px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .stat-mini-value {
            font-size: 32px;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        /* Quick Actions */
        .quick-actions {
            margin-bottom: 30px;
        }
        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }
        .section-header h3 {
            font-size: 20px;
        }
        .section-header p {
            color: #7f8c8d;
            font-size: 13px;
        }
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        .action-card {
            background: linear-gradient(135deg, #1e2746 0%, #2a3a5a 100%);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }
        .action-card:hover {
            transform: translateY(-4px);
            border-color: #667eea;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
        }
        .action-card-icon {
            font-size: 24px;
            margin-bottom: 12px;
        }
        .action-card h4 {
            font-size: 14px;
            margin-bottom: 6px;
        }
        .action-card p {
            font-size: 12px;
            color: #7f8c8d;
        }
        
        /* Progress Bar */
        .progress-section {
            background: linear-gradient(135deg, #1e2746 0%, #2a3a5a 100%);
            border-radius: 12px;
            padding: 25px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .progress-text {
            font-size: 13px;
            color: #7f8c8d;
            text-align: center;
            margin-top: 15px;
        }
        .progress-bar {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            height: 8px;
            overflow: hidden;
            margin-top: 10px;
        }
        .progress-fill {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            height: 100%;
            width: 83%;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h1>SMART TIMETABLE</h1>
                <div style="display: flex; align-items: center; gap: 8px; color: #7f8c8d; font-size: 13px;">
                    <span>⚙️</span>
                    <span>Admin Console</span>
                </div>
                <p style="font-size: 11px; color: #5a5a5a; margin-top: 8px;">Navigate every part of the timetable system through a single modern surface.</p>
            </div>
            
            <div class="sidebar-section">
                <div class="sidebar-section-title">Overview</div>
                <nav class="sidebar-nav">
                    <a href="dashboard.php" class="active">📊 Dashboard</a>
                </nav>
            </div>
            
            <div class="sidebar-section">
                <div class="sidebar-section-title">Academic Structure</div>
                <nav class="sidebar-nav">
                    <a href="#">🏛️ Faculties</a>
                    <a href="#">🏫 Schools</a>
                    <a href="#">📜 Programmes</a>
                    <a href="#">📅 Academic Years</a>
                </nav>
            </div>
            
            <div class="sidebar-section">
                <div class="sidebar-section-title">People & Resources</div>
                <nav class="sidebar-nav">
                    <a href="students.php">👥 Students</a>
                    <a href="admin/lecturers.php">👤 Lecturers</a>
                    <a href="admin/venues.php">📍 Venues</a>
                </nav>
            </div>
            
            <div class="sidebar-section">
                <div class="sidebar-section-title">Curriculum & Timetable</div>
                <nav class="sidebar-nav">
                    <a href="admin/modules.php">📚 Modules</a>
                    <a href="admin/timetable.php">➕ Add Session</a>
                    <a href="timetable_editor.php">✏️ Edit Sessions</a>
                    <a href="#">📋 View Timetable</a>
                    <a href="timetable_pdf_parser.php">📤 Upload Timetable</a>
                    <a href="admin/exams.php">📆 Exam Timetables</a>
                </nav>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Live Status Card -->
            <div class="live-status-card">
                <div class="live-status-header">
                    <div class="greeting">
                        <h2>Good <?= date('H') < 12 ? 'Morning' : (date('H') < 18 ? 'Afternoon' : 'Evening') ?>, Administrator</h2>
                        <p>Keep your academic year synchronized—manage modules, sessions and assessments with confidence.</p>
                    </div>
                    <div class="stats-mini">
                        <div class="stat-mini">
                            <div class="stat-mini-label">Modules</div>
                            <div class="stat-mini-value"><?= $stats['modules'] ?></div>
                        </div>
                        <div class="stat-mini">
                            <div class="stat-mini-label">Sessions</div>
                            <div class="stat-mini-value"><?= $stats['sessions'] ?></div>
                        </div>
                    </div>
                </div>
                <div class="status-info">
                    <div class="status-item">
                        <span>📅</span>
                        <span><?= date('l, F j, Y') ?></span>
                    </div>
                    <div class="status-item">
                        <span>🕐</span>
                        <span><?= date('g:i A') ?></span>
                    </div>
                    <div class="status-badge">System All Good</div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-actions">
                <div class="section-header">
                    <span>⚙️</span>
                    <div>
                        <h3>Quick Actions</h3>
                        <p>Accelerate your daily tasks</p>
                    </div>
                </div>
                <div class="actions-grid">
                    <a href="admin/students.php" class="action-card">
                        <div class="action-card-icon">👤➕</div>
                        <h4>Add Student</h4>
                        <p>Register new students</p>
                    </a>
                    <a href="admin/modules.php" class="action-card">
                        <div class="action-card-icon">📚➕</div>
                        <h4>Add Module</h4>
                        <p>Create new modules</p>
                    </a>
                    <a href="#" class="action-card">
                        <div class="action-card-icon">📋</div>
                        <h4>View Timetable</h4>
                        <p>See full schedule</p>
                    </a>
                    <a href="timetable_editor.php" class="action-card">
                        <div class="action-card-icon">✏️</div>
                        <h4>Edit Timetable</h4>
                        <p>Edit uploaded sessions</p>
                    </a>
                    <a href="admin/lecturers.php" class="action-card">
                        <div class="action-card-icon">👥</div>
                        <h4>Manage Lecturers</h4>
                        <p>Add or edit lecturers</p>
                    </a>
                    <a href="admin/venues.php" class="action-card">
                        <div class="action-card-icon">📍</div>
                        <h4>Manage Venues</h4>
                        <p>Add or edit venues</p>
                    </a>
                    <a href="timetable_pdf_parser.php" class="action-card">
                        <div class="action-card-icon">📄</div>
                        <h4>Upload Timetable</h4>
                        <p>Universal parser (PDF & TXT)</p>
                    </a>
                    <a href="#" class="action-card">
                        <div class="action-card-icon">❤️</div>
                        <h4>System Check</h4>
                        <p>View system health</p>
                    </a>
                    <a href="#" class="action-card">
                        <div class="action-card-icon">📄⚙️</div>
                        <h4>Semester Management</h4>
                        <p>Clear data & manage semesters</p>
                    </a>
                    <a href="admin/exams.php" class="action-card">
                        <div class="action-card-icon">📆📄</div>
                        <h4>Exam Timetables</h4>
                        <p>Upload & manage exam schedules</p>
                    </a>
                </div>
            </div>
            
            <!-- Progress Section -->
            <div class="progress-section">
                <div class="progress-header">
                    <span>Upload Timetable File</span>
                    <span style="font-weight: bold;">AUTOMATED IMPORT</span>
                    <span>System Setup Progress: <strong>83%</strong></span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <div class="progress-text">
                    Upload a PDF or TXT export and our universal parser will populate every session.
                </div>
            </div>
        </div>
    </div>
</body>
</html>

