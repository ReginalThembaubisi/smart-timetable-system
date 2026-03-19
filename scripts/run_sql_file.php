<?php
declare(strict_types=1);

require __DIR__ . '/../admin/config.php';
require __DIR__ . '/../includes/database.php';

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/run_sql_file.php <sql-file-path>\n");
    exit(1);
}

$inputPath = $argv[1];
$sqlFile = realpath($inputPath);
if ($sqlFile === false || !file_exists($sqlFile)) {
    fwrite(STDERR, "SQL file not found: {$inputPath}\n");
    exit(1);
}

try {
    $pdo = Database::getInstance()->getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    fwrite(STDERR, "Database connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

$sql = file_get_contents($sqlFile);
if ($sql === false) {
    fwrite(STDERR, "Failed to read SQL file: {$sqlFile}\n");
    exit(1);
}

// Remove full-line comments and blank lines for simpler statement parsing.
$lines = preg_split('/\R/', $sql) ?: [];
$clean = [];
foreach ($lines as $line) {
    $trimmed = trim($line);
    if ($trimmed === '' || str_starts_with($trimmed, '--')) {
        continue;
    }
    $clean[] = $line;
}
$sql = implode("\n", $clean);

$statements = array_filter(array_map('trim', explode(';', $sql)));
$executed = 0;

try {
    foreach ($statements as $statement) {
        if ($statement === '') {
            continue;
        }
        $leading = strtoupper(strtok(ltrim($statement), " \t\r\n"));
        if (in_array($leading, ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN'], true)) {
            $stmt = $pdo->query($statement);
            if ($stmt instanceof PDOStatement) {
                $stmt->fetchAll();
                $stmt->closeCursor();
            }
        } else {
            $pdo->exec($statement);
        }
        $executed++;
    }
    fwrite(STDOUT, "SQL execution complete. Statements executed: {$executed}\n");
} catch (PDOException $e) {
    fwrite(STDERR, "SQL execution failed after {$executed} statements.\n");
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    fwrite(STDERR, "Statement: {$statement}\n");
    exit(1);
}

