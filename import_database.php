<?php
/**
 * Database Import Script for Railway
 * 
 * IMPORTANT: Delete this file after importing your database!
 * This script will import your database_setup.sql file.
 * 
 * Usage: Visit https://your-app.railway.app/admin/import_database.php
 */

// Security: Only allow this in development/testing
// In production, you should remove this file or add authentication
if (getenv('RAILWAY_ENVIRONMENT') === 'production' && !isset($_GET['confirm'])) {
    die('This script is disabled in production. Add ?confirm=yes to proceed.');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Import</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #1a1a1a; color: #fff; }
        .success { background: #28a745; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #dc3545; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #17a2b8; padding: 15px; border-radius: 5px; margin: 10px 0; }
        button { background: #6c5ce7; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #5a4fcf; }
        pre { background: #2d2d2d; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Database Import Tool</h1>
    
<?php
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/includes/database.php';

try {
    $pdo = Database::getInstance()->getConnection();
    
    // Read the SQL file
    $sqlFile = __DIR__ . '/database_setup.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    if (empty($sql)) {
        throw new Exception("SQL file is empty");
    }
    
    // Remove BOM if present
    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);
    
    // Remove CREATE DATABASE and USE statements (Railway already has a database)
    $sql = preg_replace('/CREATE\s+DATABASE\s+.*?;/i', '', $sql);
    $sql = preg_replace('/USE\s+.*?;/i', '', $sql);
    
    // Split SQL into individual statements
    // Remove comments and empty lines
    $sql = preg_replace('/--.*$/m', '', $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
    
    // Split by semicolons, but keep semicolons inside quotes
    $statements = [];
    $current = '';
    $inQuotes = false;
    $quoteChar = null;
    
    for ($i = 0; $i < strlen($sql); $i++) {
        $char = $sql[$i];
        $current .= $char;
        
        if (($char === '"' || $char === "'" || $char === '`') && ($i === 0 || $sql[$i-1] !== '\\')) {
            if (!$inQuotes) {
                $inQuotes = true;
                $quoteChar = $char;
            } elseif ($char === $quoteChar) {
                $inQuotes = false;
                $quoteChar = null;
            }
        }
        
        if (!$inQuotes && $char === ';') {
            $statement = trim($current);
            if (!empty($statement)) {
                $statements[] = $statement;
            }
            $current = '';
        }
    }
    
    // Add any remaining statement
    if (!empty(trim($current))) {
        $statements[] = trim($current);
    }
    
    $executed = 0;
    $errors = [];
    
    echo '<div class="info">Found ' . count($statements) . ' SQL statements to execute...</div>';
    
    // Execute each statement
    foreach ($statements as $index => $statement) {
        $statement = trim($statement);
        
        // Skip empty statements
        if (empty($statement) || strlen($statement) < 5) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            $executed++;
        } catch (PDOException $e) {
            // Ignore "table already exists" errors if we're re-running
            if (strpos($e->getMessage(), 'already exists') === false && 
                strpos($e->getMessage(), 'Duplicate') === false) {
                $errors[] = [
                    'statement' => substr($statement, 0, 100) . '...',
                    'error' => $e->getMessage()
                ];
            }
        }
    }
    
    // Show results
    echo '<div class="success">';
    echo '<h2>✅ Import Complete!</h2>';
    echo '<p><strong>Executed:</strong> ' . $executed . ' statements</p>';
    
    if (!empty($errors)) {
        echo '<p><strong>Errors:</strong> ' . count($errors) . '</p>';
        echo '<details><summary>View Errors</summary><pre>';
        foreach ($errors as $error) {
            echo "Statement: " . htmlspecialchars($error['statement']) . "\n";
            echo "Error: " . htmlspecialchars($error['error']) . "\n\n";
        }
        echo '</pre></details>';
    } else {
        echo '<p>✅ All statements executed successfully!</p>';
    }
    echo '</div>';
    
    // Verify tables were created
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo '<div class="info">';
    echo '<h3>Database Tables:</h3>';
    echo '<ul>';
    foreach ($tables as $table) {
        $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        echo "<li><strong>$table</strong> - $count rows</li>";
    }
    echo '</ul>';
    echo '</div>';
    
    echo '<div class="info">';
    echo '<h3>⚠️ Security Reminder:</h3>';
    echo '<p>Please <strong>delete this file</strong> after importing to prevent unauthorized access!</p>';
    echo '</div>';
    
} catch (Exception $e) {
    echo '<div class="error">';
    echo '<h2>❌ Import Failed</h2>';
    echo '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    echo '</div>';
}
?>

</body>
</html>

