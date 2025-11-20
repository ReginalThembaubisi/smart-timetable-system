<?php
// Direct database import - bypasses all routing
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>DB Import</title><style>body{font-family:Arial;padding:20px;background:#1a1a1a;color:#fff;} .ok{color:#0f0;} .err{color:#f00;}</style></head><body>";
echo "<h1>Database Import</h1>";

$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'railway';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

echo "<p>Connecting: $host / $dbname</p>";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "<p class='ok'>✓ Connected!</p>";
} catch (PDOException $e) {
    die("<p class='err'>✗ Connection failed: " . htmlspecialchars($e->getMessage()) . "</p></body></html>");
}

$sqlFile = __DIR__ . '/database_setup.sql';
if (!file_exists($sqlFile)) {
    die("<p class='err'>✗ SQL file not found</p></body></html>");
}

$sql = file_get_contents($sqlFile);
$sql = preg_replace('/CREATE\s+DATABASE\s+.*?;/i', '', $sql);
$sql = preg_replace('/USE\s+.*?;/i', '', $sql);

$statements = array_filter(array_map('trim', explode(';', $sql)), function($s) {
    return !empty($s) && strlen($s) > 5 && !preg_match('/^--/', $s);
});

echo "<p>Executing " . count($statements) . " statements...</p>";

$ok = 0;
foreach ($statements as $stmt) {
    try {
        $pdo->exec($stmt);
        $ok++;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') === false && strpos($e->getMessage(), 'Duplicate') === false) {
            echo "<p class='err'>⚠ " . htmlspecialchars(substr($stmt, 0, 50)) . "...</p>";
        } else {
            $ok++;
        }
    }
}

echo "<h2 class='ok'>✓ Done! ($ok statements)</h2>";

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "<h3>Tables:</h3><ul>";
foreach ($tables as $table) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        echo "<li><strong>$table</strong> - $count rows</li>";
    } catch (Exception $e) {
        echo "<li><strong>$table</strong></li>";
    }
}
echo "</ul>";
echo "<p class='err'><strong>⚠️ DELETE THIS FILE!</strong></p>";
echo "</body></html>";
?>

