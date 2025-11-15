<?php
// Script to add programme, year_level, and semester columns to sessions table
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin/login.php');
    exit;
}

require_once 'admin/config.php';

$pdo = new PDO("mysql:host=localhost;dbname=smart_timetable", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$message = '';
$messageType = '';

// Check if columns exist and add them if they don't
try {
    // Check if columns exist
    $columns = $pdo->query("SHOW COLUMNS FROM sessions LIKE 'programme'")->fetch();
    
    if (!$columns) {
        $pdo->exec("ALTER TABLE sessions ADD COLUMN programme VARCHAR(255) NULL AFTER session_id");
        $pdo->exec("ALTER TABLE sessions ADD COLUMN year_level VARCHAR(50) NULL AFTER programme");
        $pdo->exec("ALTER TABLE sessions ADD COLUMN semester VARCHAR(50) NULL AFTER year_level");
        $pdo->exec("ALTER TABLE sessions ADD INDEX idx_programme (programme)");
        $pdo->exec("ALTER TABLE sessions ADD INDEX idx_year_level (year_level)");
        $pdo->exec("ALTER TABLE sessions ADD INDEX idx_semester (semester)");
        
        $message = 'Successfully added programme, year_level, and semester columns to sessions table!';
        $messageType = 'success';
    } else {
        $message = 'Columns already exist. No changes needed.';
        $messageType = 'info';
    }
} catch (PDOException $e) {
    $message = 'Error: ' . $e->getMessage();
    $messageType = 'error';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Programme Fields</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; background: #0a0a0a; color: #fff; }
        .message { padding: 20px; border-radius: 8px; margin: 20px 0; }
        .success { background: rgba(39, 174, 96, 0.2); border: 1px solid rgba(39, 174, 96, 0.3); color: #27ae60; }
        .error { background: rgba(231, 76, 60, 0.2); border: 1px solid rgba(231, 76, 60, 0.3); color: #e74c3c; }
        .info { background: rgba(52, 152, 219, 0.2); border: 1px solid rgba(52, 152, 219, 0.3); color: #3498db; }
        a { color: #667eea; text-decoration: none; }
    </style>
</head>
<body>
    <h1>Add Programme Fields to Sessions Table</h1>
    <div class="message <?= $messageType ?>">
        <?= htmlspecialchars($message) ?>
    </div>
    <a href="dashboard.php">← Back to Dashboard</a>
</body>
</html>

