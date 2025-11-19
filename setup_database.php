<?php
/**
 * Database Setup Script
 * Run this file in your browser: http://localhost/setup_database.php
 * This will create all necessary tables for the Smart Timetable system
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - Smart Timetable</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border: 1px solid #f5c6cb;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border: 1px solid #bee5eb;
        }
        button {
            background: #3498db;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 5px;
        }
        button:hover {
            background: #2980b9;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border: 1px solid #ffeaa7;
        }
        pre {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Smart Timetable - Database Setup</h1>
        
        <?php
        $dbHost = 'localhost';
        $dbUser = 'root';
        $dbPass = '';
        $dbName = 'smart_timetable';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup'])) {
            try {
                // Connect to MySQL server (without database)
                $pdo = new PDO("mysql:host=$dbHost", $dbUser, $dbPass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Create database
                $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                echo '<div class="success">✓ Database created successfully</div>';
                
                // Use the database
                $pdo->exec("USE $dbName");
                
                // Read and execute SQL file
                $sqlFile = __DIR__ . '/database_setup.sql';
                if (file_exists($sqlFile)) {
                    $sql = file_get_contents($sqlFile);
                    
                    // Remove CREATE DATABASE and USE statements (already done)
                    $sql = preg_replace('/CREATE DATABASE.*?;/i', '', $sql);
                    $sql = preg_replace('/USE.*?;/i', '', $sql);
                    
                    // Split by semicolon and execute each statement
                    $statements = array_filter(array_map('trim', explode(';', $sql)));
                    
                    $successCount = 0;
                    $errorCount = 0;
                    
                    foreach ($statements as $statement) {
                        if (!empty($statement) && !preg_match('/^--/', $statement)) {
                            try {
                                $pdo->exec($statement);
                                $successCount++;
                            } catch (PDOException $e) {
                                if (strpos($e->getMessage(), 'already exists') === false) {
                                    echo '<div class="error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                                    $errorCount++;
                                }
                            }
                        }
                    }
                    
                    echo '<div class="success">✓ Executed ' . $successCount . ' SQL statements successfully</div>';
                    if ($errorCount > 0) {
                        echo '<div class="warning">⚠ ' . $errorCount . ' statements had errors (may be expected if tables already exist)</div>';
                    }
                } else {
                    // If SQL file doesn't exist, create tables directly
                    echo '<div class="info">SQL file not found, creating tables directly...</div>';
                    
                    // Create tables
                    $tables = [
                        "CREATE TABLE IF NOT EXISTS students (
                            student_id INT AUTO_INCREMENT PRIMARY KEY,
                            student_number VARCHAR(50) UNIQUE NOT NULL,
                            full_name VARCHAR(255) NOT NULL,
                            email VARCHAR(255) NOT NULL,
                            password VARCHAR(255) NOT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            INDEX idx_student_number (student_number)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                        
                        "CREATE TABLE IF NOT EXISTS modules (
                            module_id INT AUTO_INCREMENT PRIMARY KEY,
                            module_code VARCHAR(50) UNIQUE NOT NULL,
                            module_name VARCHAR(255) NOT NULL,
                            credits INT DEFAULT 0,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            INDEX idx_module_code (module_code)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                        
                        "CREATE TABLE IF NOT EXISTS lecturers (
                            lecturer_id INT AUTO_INCREMENT PRIMARY KEY,
                            lecturer_name VARCHAR(255) NOT NULL,
                            email VARCHAR(255),
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                        
                        "CREATE TABLE IF NOT EXISTS venues (
                            venue_id INT AUTO_INCREMENT PRIMARY KEY,
                            venue_name VARCHAR(255) NOT NULL,
                            capacity INT DEFAULT 0,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                        
                        "CREATE TABLE IF NOT EXISTS sessions (
                            session_id INT AUTO_INCREMENT PRIMARY KEY,
                            module_id INT NOT NULL,
                            lecturer_id INT,
                            venue_id INT,
                            day_of_week VARCHAR(20) NOT NULL,
                            start_time TIME NOT NULL,
                            end_time TIME NOT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            FOREIGN KEY (module_id) REFERENCES modules(module_id) ON DELETE CASCADE,
                            FOREIGN KEY (lecturer_id) REFERENCES lecturers(lecturer_id) ON DELETE SET NULL,
                            FOREIGN KEY (venue_id) REFERENCES venues(venue_id) ON DELETE SET NULL,
                            INDEX idx_day_time (day_of_week, start_time)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                        
                        "CREATE TABLE IF NOT EXISTS student_modules (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            student_id INT NOT NULL,
                            module_id INT NOT NULL,
                            enrollment_date DATE DEFAULT (CURRENT_DATE),
                            status VARCHAR(20) DEFAULT 'active',
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
                            FOREIGN KEY (module_id) REFERENCES modules(module_id) ON DELETE CASCADE,
                            UNIQUE KEY unique_enrollment (student_id, module_id),
                            INDEX idx_student_id (student_id),
                            INDEX idx_module_id (module_id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                        
                        "CREATE TABLE IF NOT EXISTS exams (
                            exam_id INT AUTO_INCREMENT PRIMARY KEY,
                            module_id INT NOT NULL,
                            venue_id INT,
                            exam_date DATE NOT NULL,
                            exam_time TIME NOT NULL,
                            duration INT DEFAULT 120,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            FOREIGN KEY (module_id) REFERENCES modules(module_id) ON DELETE CASCADE,
                            FOREIGN KEY (venue_id) REFERENCES venues(venue_id) ON DELETE SET NULL,
                            INDEX idx_exam_date (exam_date)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                        
                        "CREATE TABLE IF NOT EXISTS exam_notifications (
                            notification_id INT AUTO_INCREMENT PRIMARY KEY,
                            student_id INT NOT NULL,
                            exam_id INT,
                            message TEXT,
                            is_read TINYINT(1) DEFAULT 0,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
                            FOREIGN KEY (exam_id) REFERENCES exams(exam_id) ON DELETE CASCADE,
                            INDEX idx_student_id (student_id),
                            INDEX idx_is_read (is_read)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                    ];
                    
                    foreach ($tables as $tableSql) {
                        $pdo->exec($tableSql);
                    }
                    
                    echo '<div class="success">✓ All tables created successfully</div>';
                }
                
                // Check if sample data should be inserted
                if (isset($_POST['insert_sample_data'])) {
                    // Insert sample data
                    $sampleData = [
                        "INSERT IGNORE INTO students (student_id, student_number, full_name, email, password) VALUES
                        (1, '202000001', 'John Doe', 'john.doe@university.edu', 'password123'),
                        (2, '202000002', 'Jane Smith', 'jane.smith@university.edu', 'password123'),
                        (3, '202057420', 'Themba', 'themba@university.edu', 'password123')",
                        
                        "INSERT IGNORE INTO modules (module_id, module_code, module_name, credits) VALUES
                        (1, 'CS101', 'Introduction to Computer Science', 3),
                        (2, 'MATH101', 'Calculus I', 4),
                        (3, 'ENG101', 'English Composition', 3),
                        (4, 'PHY101', 'Physics I', 4),
                        (5, 'CHEM101', 'Chemistry I', 4)",
                        
                        "INSERT IGNORE INTO lecturers (lecturer_id, lecturer_name, email) VALUES
                        (1, 'Dr. Sarah Johnson', 'sarah.johnson@university.edu'),
                        (2, 'Prof. Michael Brown', 'michael.brown@university.edu'),
                        (3, 'Dr. Emily Davis', 'emily.davis@university.edu')",
                        
                        "INSERT IGNORE INTO venues (venue_id, venue_name, capacity) VALUES
                        (1, 'Lecture Hall A', 200),
                        (2, 'Lecture Hall B', 150),
                        (3, 'Lab Room 101', 30),
                        (4, 'Lab Room 102', 30),
                        (5, 'Seminar Room 1', 50)"
                    ];
                    
                    foreach ($sampleData as $dataSql) {
                        try {
                            $pdo->exec($dataSql);
                        } catch (PDOException $e) {
                            // Ignore duplicate key errors
                        }
                    }
                    
                    echo '<div class="success">✓ Sample data inserted successfully</div>';
                }
                
                // Verify tables
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                echo '<div class="info"><strong>Created Tables:</strong><br>';
                foreach ($tables as $table) {
                    echo '• ' . htmlspecialchars($table) . '<br>';
                }
                echo '</div>';
                
                echo '<div class="success"><strong>✓ Database setup completed successfully!</strong></div>';
                echo '<div class="info">You can now <a href="admin/login.php">login to the admin panel</a> or use the Flutter app.</div>';
                
            } catch (PDOException $e) {
                echo '<div class="error"><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } else {
            // Show setup form
            ?>
            <div class="info">
                <p>This script will create the <strong>smart_timetable</strong> database and all necessary tables.</p>
                <p><strong>Database Configuration:</strong></p>
                <ul>
                    <li>Host: <?= htmlspecialchars($dbHost) ?></li>
                    <li>User: <?= htmlspecialchars($dbUser) ?></li>
                    <li>Database: <?= htmlspecialchars($dbName) ?></li>
                </ul>
            </div>
            
            <form method="POST">
                <div class="warning">
                    <strong>Warning:</strong> This will create the database and tables. If tables already exist, they will be skipped.
                </div>
                
                <label style="display: block; margin: 15px 0;">
                    <input type="checkbox" name="insert_sample_data" value="1" checked>
                    Insert sample data (test student: 202057420, password: password123)
                </label>
                
                <button type="submit" name="setup" style="background: #27ae60;">
                    Setup Database
                </button>
            </form>
            <?php
        }
        ?>
    </div>
</body>
</html>

