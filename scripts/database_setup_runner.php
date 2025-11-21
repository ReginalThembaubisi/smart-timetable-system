<?php
/**
 * Automated database setup runner.
 * This script executes the SQL statements inside database_setup.sql
 * so that the schema is always created/updated before the web server starts.
 */

declare(strict_types=1);

require __DIR__ . '/../admin/config.php';
require __DIR__ . '/../includes/database.php';

$sqlFile = __DIR__ . '/../database_setup.sql';
if (!file_exists($sqlFile)) {
    fwrite(STDERR, "database_setup.sql not found; skipping auto-run.\n");
    exit(0);
}

try {
    $dsn = sprintf('mysql:host=%s;charset=utf8mb4', DB_HOST);
    $rootPdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo = Database::getInstance()->getConnection();
} catch (Exception $e) {
    fwrite(STDERR, "Unable to connect to the database before setup: " . $e->getMessage() . "\n");
    exit(1);
}

$sql = file_get_contents($sqlFile);
if ($sql === false) {
    fwrite(STDERR, "Failed to read database_setup.sql\n");
    exit(1);
}

// Sanitize the file: remove CREATE DATABASE/USE lines and strip standalone comment lines.
$sql = preg_replace('/CREATE DATABASE.*?;(\s?)/i', '', $sql);
$sql = preg_replace('/USE\s+\S+;(\s?)/i', '', $sql);
$sql = preg_replace('/^\s*--.*$/m', '', $sql);

$statements = array_filter(array_map('trim', explode(';', $sql)));
$executed = 0;
foreach ($statements as $statement) {
    if ($statement === '') {
        continue;
    }

    try {
        $pdo->exec($statement);
        $executed++;
    } catch (PDOException $e) {
        $message = $e->getMessage();
        // Ignore "already exists" errors so re-runs stay idempotent.
        if (stripos($message, 'already exists') !== false) {
            continue;
        }
        fwrite(STDERR, "Failed to execute SQL statement: {$message}\nStatement: {$statement}\n");
        exit(1);
    }
}

fwrite(STDOUT, "Auto database setup completed ({$executed} statements executed).\n");

