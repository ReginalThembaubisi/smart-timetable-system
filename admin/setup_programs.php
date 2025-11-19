<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';

$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$messages = [];
$errors = [];

try {
    // Create programs table
    $pdo->exec("CREATE TABLE IF NOT EXISTS programs (
        program_id INT AUTO_INCREMENT PRIMARY KEY,
        program_code VARCHAR(50) UNIQUE NOT NULL,
        program_name VARCHAR(255) NOT NULL,
        description TEXT,
        duration_years INT DEFAULT 4,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_program_code (program_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $messages[] = "Programs table created/verified";

    // Create program_modules table (links modules to programs and years)
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
    $messages[] = "Program modules table created/verified";

    // Add program_id and year_level to students table if they don't exist
    $columns = $pdo->query("SHOW COLUMNS FROM students LIKE 'program_id'")->fetch();
    if (!$columns) {
        $pdo->exec("ALTER TABLE students ADD COLUMN program_id INT NULL AFTER email");
        $pdo->exec("ALTER TABLE students ADD COLUMN year_level INT NULL AFTER program_id");
        $pdo->exec("ALTER TABLE students ADD FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE SET NULL");
        $pdo->exec("ALTER TABLE students ADD INDEX idx_program_id (program_id)");
        $messages[] = "Added program_id and year_level to students table";
    } else {
        $messages[] = "Students table already has program fields";
    }

    // Add program_id and year_level to student_modules if they don't exist
    $columns = $pdo->query("SHOW COLUMNS FROM student_modules LIKE 'program_id'")->fetch();
    if (!$columns) {
        $pdo->exec("ALTER TABLE student_modules ADD COLUMN program_id INT NULL AFTER student_id");
        $pdo->exec("ALTER TABLE student_modules ADD COLUMN year_level INT NULL AFTER program_id");
        $pdo->exec("ALTER TABLE student_modules ADD FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE SET NULL");
        $messages[] = "Added program_id and year_level to student_modules table";
    } else {
        $messages[] = "Student_modules table already has program fields";
    }

} catch (PDOException $e) {
    $errors[] = "Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Programs - Smart Timetable</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #0f0f1e;
            color: #fff;
            padding: 40px;
            margin: 0;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #1e1e2e;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        h1 {
            color: #9b59b6;
            margin-bottom: 30px;
        }
        .message {
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            background: rgba(39, 174, 96, 0.2);
            color: #27ae60;
            border: 1px solid rgba(39, 174, 96, 0.3);
        }
        .error {
            background: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
            border: 1px solid rgba(231, 76, 60, 0.3);
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 20px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(155, 89, 182, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Setup Programs Structure</h1>
        
        <?php foreach ($messages as $msg): ?>
            <div class="message"><?= htmlspecialchars($msg) ?></div>
        <?php endforeach; ?>
        
        <?php foreach ($errors as $error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endforeach; ?>
        
        <a href="programs.php" class="btn">Go to Programs Management</a>
        <a href="students.php" class="btn" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); margin-left: 10px;">Back to Students</a>
    </div>
</body>
</html>

