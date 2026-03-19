<?php
declare(strict_types=1);

require __DIR__ . '/../includes/database.php';
require __DIR__ . '/../includes/lecturer_system.php';

try {
    $pdo = Database::getInstance()->getConnection();
    ensureLecturerSystemTables($pdo);
    echo "Lecturer system tables ensured.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Failed to ensure lecturer tables: " . $e->getMessage() . "\n");
    exit(1);
}

