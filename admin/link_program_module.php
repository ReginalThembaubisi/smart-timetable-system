<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    exit;
}

require_once 'config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/crud_helpers.php';

$pdo = Database::getInstance()->getConnection();

// Ensure program_modules table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS program_modules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        program_id INT NOT NULL,
        module_id INT NOT NULL,
        year_level INT NOT NULL,
        semester INT,
        is_core TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE CASCADE,
        FOREIGN KEY (module_id) REFERENCES modules(module_id) ON DELETE CASCADE,
        UNIQUE KEY unique_program_module_year (program_id, module_id, year_level),
        INDEX idx_program_id (program_id),
        INDEX idx_module_id (module_id),
        INDEX idx_year_level (year_level)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $programId = (int)($_POST['program_id'] ?? 0);
    $moduleId = (int)($_POST['module_id'] ?? 0);
    $yearLevel = (int)($_POST['year_level'] ?? 0);
    
    if ($programId && $moduleId && $yearLevel) {
        try {
            $stmt = $pdo->prepare("INSERT INTO program_modules (program_id, module_id, year_level, is_core) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE is_core = VALUES(is_core)");
            $stmt->execute([$programId, $moduleId, $yearLevel, 1]);
            http_response_code(200);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            logError($e, 'Linking program module');
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => getErrorMessage($e, 'Linking module', false)]);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

