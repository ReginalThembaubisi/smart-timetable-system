<?php
// Quick database check script
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head><title>Database Check</title><style>body{font-family:Arial;padding:20px;background:#1a1a1a;color:#fff;} .ok{color:#0f0;} .err{color:#f00;}</style></head>
<body>
<h1>Database Check</h1>

<?php
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/includes/database.php';

try {
    $pdo = Database::getInstance()->getConnection();
    echo "<p class='ok'>✓ Connected to database!</p>";
    
    // Check tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<h2>Tables (" . count($tables) . "):</h2><ul>";
    foreach ($tables as $table) {
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            echo "<li><strong>$table</strong> - $count rows</li>";
        } catch (Exception $e) {
            echo "<li><strong>$table</strong> - Error: " . $e->getMessage() . "</li>";
        }
    }
    echo "</ul>";
    
    // Check specific data
    echo "<h2>Data Check:</h2>";
    $checks = [
        'students' => "SELECT COUNT(*) FROM students",
        'modules' => "SELECT COUNT(*) FROM modules",
        'sessions' => "SELECT COUNT(*) FROM sessions",
        'lecturers' => "SELECT COUNT(*) FROM lecturers",
        'venues' => "SELECT COUNT(*) FROM venues"
    ];
    
    foreach ($checks as $name => $query) {
        try {
            $count = $pdo->query($query)->fetchColumn();
            $status = $count > 0 ? 'ok' : 'err';
            echo "<p class='$status'>$name: $count rows</p>";
        } catch (Exception $e) {
            echo "<p class='err'>$name: Table doesn't exist or error - " . $e->getMessage() . "</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p class='err'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<p style="color:red;font-weight:bold;margin-top:20px;">⚠️ DELETE THIS FILE AFTER CHECKING!</p>
</body>
</html>

