<?php
session_start();

// Simple authentication
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';

// Get statistics
try {
    $pdo = new PDO("mysql:host=localhost;dbname=smart_timetable", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stats = [
        'students' => $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn(),
        'modules' => $pdo->query("SELECT COUNT(*) FROM modules")->fetchColumn(),
        'sessions' => $pdo->query("SELECT COUNT(*) FROM sessions")->fetchColumn(),
        'exams' => $pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn(),
        'lecturers' => $pdo->query("SELECT COUNT(*) FROM lecturers")->fetchColumn(),
        'venues' => $pdo->query("SELECT COUNT(*) FROM venues")->fetchColumn(),
    ];
} catch (PDOException $e) {
    $stats = ['students' => 0, 'modules' => 0, 'sessions' => 0, 'exams' => 0, 'lecturers' => 0, 'venues' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Timetable Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .header { background: #2c3e50; color: white; padding: 20px; }
        .header h1 { display: inline-block; }
        .header a { float: right; color: white; text-decoration: none; padding: 10px 20px; background: #e74c3c; border-radius: 5px; }
        .container { max-width: 1200px; margin: 20px auto; padding: 0 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card h3 { color: #7f8c8d; font-size: 14px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1px; }
        .stat-card .number { font-size: 36px; font-weight: bold; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .nav { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: flex; flex-wrap: wrap; gap: 10px; }
        .nav a { display: inline-block; padding: 12px 24px; color: white; text-decoration: none; border-radius: 5px; transition: all 0.3s; font-weight: 500; }
        .nav a:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
    </style>
</head>
<body>
    <div class="header">
        <h1>Smart Timetable Admin Dashboard</h1>
        <a href="logout.php">Logout</a>
    </div>
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Students</h3>
                <div class="number"><?= $stats['students'] ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Modules</h3>
                <div class="number"><?= $stats['modules'] ?></div>
            </div>
            <div class="stat-card">
                <h3>Timetable Sessions</h3>
                <div class="number"><?= $stats['sessions'] ?></div>
            </div>
            <div class="stat-card">
                <h3>Exams</h3>
                <div class="number"><?= $stats['exams'] ?></div>
            </div>
            <div class="stat-card">
                <h3>Lecturers</h3>
                <div class="number"><?= $stats['lecturers'] ?></div>
            </div>
            <div class="stat-card">
                <h3>Venues</h3>
                <div class="number"><?= $stats['venues'] ?></div>
            </div>
        </div>
        
        <div class="nav" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <a href="students.php" style="background: #3498db;">Manage Students</a>
            <a href="student_modules.php" style="background: #9b59b6;">Student Enrollment</a>
            <a href="modules.php" style="background: #e67e22;">Manage Modules</a>
            <a href="timetable.php" style="background: #1abc9c;">Manage Timetable</a>
            <a href="exams.php" style="background: #e74c3c;">Manage Exams</a>
            <a href="lecturers.php" style="background: #f39c12;">Manage Lecturers</a>
            <a href="venues.php" style="background: #16a085;">Manage Venues</a>
            <a href="study_sessions.php" style="background: #27ae60;">Study Sessions</a>
        </div>
    </div>
</body>
</html>

