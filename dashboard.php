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
        
        /* Sidebar - Aceternity Style */
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
        .admin-console-desc {
            font-size: 12px;
            color: rgba(255,255,255,0.4);
            line-height: 1.6;
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
        
        /* Live Status Card - Aceternity Style */
        .live-status-card {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 32px;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }
        .live-status-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 32px;
        }
        .greeting h2 {
            font-size: 32px;
            margin-bottom: 12px;
            font-weight: 700;
            letter-spacing: -1px;
            background: linear-gradient(135deg, #ffffff 0%, rgba(255,255,255,0.8) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .greeting p {
            color: rgba(255,255,255,0.6);
            font-size: 15px;
            line-height: 1.6;
        }
        .status-row {
            display: flex;
            align-items: center;
            gap: 24px;
            margin-top: 28px;
            flex-wrap: wrap;
        }
        .status-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: rgba(255,255,255,0.7);
            font-size: 14px;
            font-weight: 500;
        }
        .status-badge {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
            padding: 8px 20px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
        }
        .stats-mini {
            display: flex;
            gap: 48px;
            align-items: flex-end;
        }
        .stat-mini {
            text-align: right;
        }
        .stat-mini-label {
            font-size: 11px;
            color: rgba(255,255,255,0.5);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 8px;
            font-weight: 600;
        }
        .stat-mini-value {
            font-size: 42px;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1;
            letter-spacing: -2px;
        }
        
        /* Quick Actions - Aceternity Style */
        .quick-actions {
            margin-bottom: 32px;
        }
        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }
        .section-header-icon {
            font-size: 20px;
        }
        .section-header h3 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 4px;
            letter-spacing: -0.5px;
        }
        .section-header p {
            color: rgba(255,255,255,0.5);
            font-size: 14px;
        }
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 16px;
        }
        .action-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            position: relative;
            overflow: hidden;
        }
        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            opacity: 0;
            transition: opacity 0.3s;
        }
        .action-card:hover {
            transform: translateY(-4px);
            border-color: rgba(102, 126, 234, 0.5);
            box-shadow: 0 12px 40px rgba(102, 126, 234, 0.2);
        }
        .action-card:hover::before {
            opacity: 1;
        }
        .action-card-icon {
            font-size: 32px;
            margin-bottom: 16px;
            display: block;
            position: relative;
            z-index: 1;
        }
        .action-card h4 {
            font-size: 15px;
            margin-bottom: 6px;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }
        .action-card p {
            font-size: 13px;
            color: rgba(255,255,255,0.5);
            line-height: 1.5;
            position: relative;
            z-index: 1;
        }
        
        /* Progress Section - Aceternity Style */
        .progress-section {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 32px;
            backdrop-filter: blur(10px);
        }
        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            font-size: 13px;
            color: rgba(255,255,255,0.7);
            font-weight: 500;
        }
        .progress-header strong {
            color: #ffffff;
            font-weight: 700;
        }
        .progress-text {
            font-size: 14px;
            color: rgba(255,255,255,0.5);
            text-align: center;
            margin-top: 20px;
            line-height: 1.6;
        }
        .progress-bar {
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            height: 10px;
            overflow: hidden;
            margin-top: 16px;
            position: relative;
        }
        .progress-fill {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            height: 100%;
            width: 83%;
            border-radius: 12px;
            position: relative;
            overflow: hidden;
        }
        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: shimmer 2s infinite;
        }
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
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
                <p class="admin-console-desc">Navigate every part of the timetable system through a single modern surface.</p>
            </div>
            
            <div class="sidebar-section">
                <div class="sidebar-section-title">Overview</div>
                <nav class="sidebar-nav">
                    <a href="dashboard.php" class="active"><i>📊</i> Dashboard</a>
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
                    <a href="students.php"><i>👥</i> Students</a>
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
                    <a href="view_timetable.php"><i>📋</i> View Timetable</a>
                    <a href="timetable_pdf_parser.php"><i>📤</i> Upload Timetable</a>
                    <a href="admin/exams.php"><i>📆</i> Exam Timetables</a>
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
                <div class="status-row">
                    <div class="status-item">
                        <span>📅</span>
                        <span><?= date('l, F j, Y') ?></span>
                    </div>
                    <div class="status-item">
                        <span>🕐</span>
                        <span><?= date('g:i A') ?></span>
                    </div>
                    <div class="status-badge">
                        <span>🛡️</span>
                        <span>System All Good</span>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-actions">
                <div class="section-header">
                    <span class="section-header-icon">⚙️</span>
                    <div>
                        <h3>Quick Actions</h3>
                        <p>Accelerate your daily tasks</p>
                    </div>
                </div>
                <div class="actions-grid">
                    <a href="admin/students.php" class="action-card">
                        <span class="action-card-icon">👤➕</span>
                        <h4>Add Student</h4>
                        <p>Register new students</p>
                    </a>
                    <a href="admin/modules.php" class="action-card">
                        <span class="action-card-icon">📚➕</span>
                        <h4>Add Module</h4>
                        <p>Create new modules</p>
                    </a>
                    <a href="view_timetable.php" class="action-card">
                        <span class="action-card-icon">📋</span>
                        <h4>View Timetable</h4>
                        <p>See full schedule</p>
                    </a>
                    <a href="timetable_editor.php" class="action-card">
                        <span class="action-card-icon">✏️</span>
                        <h4>Edit Timetable</h4>
                        <p>Inline edit sessions with filters</p>
                    </a>
                    <a href="admin/lecturers.php" class="action-card">
                        <span class="action-card-icon">👥</span>
                        <h4>Manage Lecturers</h4>
                        <p>Add or edit lecturers</p>
                    </a>
                    <a href="admin/venues.php" class="action-card">
                        <span class="action-card-icon">📍</span>
                        <h4>Manage Venues</h4>
                        <p>Add or edit venues</p>
                    </a>
                    <a href="timetable_pdf_parser.php" class="action-card">
                        <span class="action-card-icon">📄</span>
                        <h4>Upload Timetable</h4>
                        <p>Universal parser (PDF & TXT)</p>
                    </a>
                    <a href="#" class="action-card">
                        <span class="action-card-icon">❤️</span>
                        <h4>System Check</h4>
                        <p>View system health</p>
                    </a>
                    <a href="semester_management.php" class="action-card">
                        <span class="action-card-icon">📄⚙️</span>
                        <h4>Semester Management</h4>
                        <p>Clear data & manage semesters</p>
                    </a>
                    <a href="admin/exams.php" class="action-card">
                        <span class="action-card-icon">📆📄</span>
                        <h4>Exam Timetables</h4>
                        <p>Upload & manage exam schedules</p>
                    </a>
                </div>
            </div>
            
            <!-- Progress Section -->
            <div class="progress-section">
                <div class="progress-header">
                    <span>Upload Timetable File</span>
                    <span><strong>AUTOMATED IMPORT</strong></span>
                    <span>System Setup Progress: <strong>83%</strong></span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <div class="progress-text">
                    Upload a PDF or TXT export and our universal parser will populate every session. Then use the <strong>Edit Timetable</strong> page to make inline edits to lecturers, venues, days, and times with real-time updates.
                </div>
            </div>
        </div>
    </div>
</body>
</html>
