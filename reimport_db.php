<?php
// Database re-import script for Railway
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Re-import Database</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #1a1a1a; color: #fff; }
        .success { background: #28a745; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #dc3545; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #17a2b8; padding: 15px; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>Database Re-import</h1>
    
<?php
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/includes/database.php';

try {
    $pdo = Database::getInstance()->getConnection();
    echo '<div class="info">✓ Connected to database</div>';
    
    $sqlFile = __DIR__ . '/database_setup.sql';
    if (!file_exists($sqlFile)) {
        die('<div class="error">✗ SQL file not found: ' . $sqlFile . '</div></body></html>');
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Remove CREATE DATABASE and USE statements
    $sql = preg_replace('/CREATE\s+DATABASE\s+.*?;/i', '', $sql);
    $sql = preg_replace('/USE\s+.*?;/i', '', $sql);
    
    // Split into statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && strlen($stmt) > 5 && !preg_match('/^--/', $stmt);
        }
    );
    
    echo '<div class="info">Found ' . count($statements) . ' statements to execute...</div>';
    
    $success = 0;
    $errors = 0;
    
    foreach ($statements as $stmt) {
        try {
            $pdo->exec($stmt);
            $success++;
        } catch (PDOException $e) {
            // Ignore "already exists" errors
            if (strpos($e->getMessage(), 'already exists') === false && 
                strpos($e->getMessage(), 'Duplicate') === false) {
                $errors++;
                echo '<div class="error">⚠ ' . htmlspecialchars(substr($stmt, 0, 100)) . '... - ' . htmlspecialchars($e->getMessage()) . '</div>';
            } else {
                $success++;
            }
        }
    }
    
    echo '<div class="success">';
    echo '<h2>✓ Import Complete!</h2>';
    echo '<p>Successfully executed: ' . $success . ' statements</p>';
    if ($errors > 0) {
        echo '<p>Errors: ' . $errors . '</p>';
    }
    echo '</div>';
    
    // Verify tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo '<div class="info">';
    echo '<h3>Database Tables (' . count($tables) . '):</h3>';
    echo '<ul>';
    foreach ($tables as $table) {
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            echo "<li><strong>$table</strong> - $count rows</li>";
        } catch (Exception $e) {
            echo "<li><strong>$table</strong> - Error</li>";
        }
    }
    echo '</ul>';
    echo '</div>';
    
    echo '<div class="error" style="margin-top: 20px;">';
    echo '<strong>⚠️ DELETE THIS FILE AFTER IMPORTING!</strong>';
    echo '</div>';
    
} catch (Exception $e) {
    echo '<div class="error">';
    echo '<h2>❌ Import Failed</h2>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</div>';
}
?>

</body>
</html>

