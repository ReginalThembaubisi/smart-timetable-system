<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Import</h1>";

// Get database credentials from environment
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'railway';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

echo "<p>Connecting to: $host / $dbname</p>";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "<p style='color:green'>✓ Connected to database!</p>";
} catch (PDOException $e) {
    die("<p style='color:red'>✗ Connection failed: " . $e->getMessage() . "</p>");
}

// Read SQL file
$sqlFile = __DIR__ . '/database_setup.sql';
if (!file_exists($sqlFile)) {
    die("<p style='color:red'>✗ SQL file not found: $sqlFile</p>");
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

echo "<p>Found " . count($statements) . " statements to execute...</p>";
echo "<hr>";

$success = 0;
$errors = 0;

foreach ($statements as $stmt) {
    if (empty(trim($stmt))) continue;
    
    try {
        $pdo->exec($stmt);
        $success++;
    } catch (PDOException $e) {
        // Ignore "already exists" errors
        if (strpos($e->getMessage(), 'already exists') === false && 
            strpos($e->getMessage(), 'Duplicate') === false) {
            $errors++;
            echo "<p style='color:orange'>⚠ " . substr($stmt, 0, 50) . "... - " . $e->getMessage() . "</p>";
        } else {
            $success++;
        }
    }
}

echo "<hr>";
echo "<h2 style='color:green'>✓ Import Complete!</h2>";
echo "<p>Successfully executed: $success statements</p>";
if ($errors > 0) {
    echo "<p style='color:orange'>Errors: $errors</p>";
}

// Show tables
echo "<h3>Database Tables:</h3>";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "<ul>";
foreach ($tables as $table) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        echo "<li><strong>$table</strong> - $count rows</li>";
    } catch (Exception $e) {
        echo "<li><strong>$table</strong> - Error counting</li>";
    }
}
echo "</ul>";

echo "<p style='color:red;font-weight:bold'>⚠️ DELETE THIS FILE AFTER IMPORT!</p>";
?>

