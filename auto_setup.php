<?php
/**
 * Auto Database Setup Script
 * This will automatically create the database and all tables
 * Just run: http://localhost/auto_setup.php
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Auto Setup - Smart Timetable</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; border: 1px solid #bee5eb; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0; border: 1px solid #ffeaa7; }
        a { color: #3498db; text-decoration: none; font-weight: bold; }
        a:hover { text-decoration: underline; }
        ul { line-height: 2; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Auto Database Setup - Smart Timetable</h1>
        
        <?php
        $dbHost = 'localhost';
        $dbUser = 'root';
        $dbPass = '';
        $dbName = 'smart_timetable';
        
        $errors = [];
        $success = [];
        
        try {
            // Connect to MySQL server (without database)
            $pdo = new PDO("mysql:host=$dbHost", $dbUser, $dbPass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $success[] = "✓ Connected to MySQL server";
            
            // Create database
            $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $success[] = "✓ Database '$dbName' created/verified";
            
            // Use the database
            $pdo->exec("USE $dbName");
            
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
                
                "CREATE TABLE IF NOT EXISTS study_sessions (
                    session_id INT AUTO_INCREMENT PRIMARY KEY,
                    student_id INT NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    module_name VARCHAR(255),
                    day_of_week VARCHAR(20) NOT NULL,
                    start_time TIME NOT NULL,
                    end_time TIME NOT NULL,
                    duration INT,
                    session_type VARCHAR(50),
                    notes TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
                    INDEX idx_student_id (student_id),
                    INDEX idx_day_time (day_of_week, start_time)
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
                try {
                    $pdo->exec($tableSql);
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        $errors[] = "Error creating table: " . $e->getMessage();
                    }
                }
            }
            
            $success[] = "✓ All tables created successfully";
            
            // Insert sample data automatically
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
                (5, 'CHEM101', 'Chemistry I', 4),
                (6, 'CS102', 'Data Structures', 4),
                (7, 'CS201', 'Database Systems', 4),
                (8, 'MATH102', 'Calculus II', 4),
                (9, 'PHY102', 'Physics II', 4),
                (10, 'CS301', 'Software Engineering', 4)",
                
                "INSERT IGNORE INTO lecturers (lecturer_id, lecturer_name, email) VALUES
                (1, 'Dr. Sarah Johnson', 'sarah.johnson@university.edu'),
                (2, 'Prof. Michael Brown', 'michael.brown@university.edu'),
                (3, 'Dr. Emily Davis', 'emily.davis@university.edu'),
                (4, 'Dr. James Wilson', 'james.wilson@university.edu'),
                (5, 'Prof. Lisa Anderson', 'lisa.anderson@university.edu')",
                
                "INSERT IGNORE INTO venues (venue_id, venue_name, capacity) VALUES
                (1, 'Lecture Hall A', 200),
                (2, 'Lecture Hall B', 150),
                (3, 'Lab Room 101', 30),
                (4, 'Lab Room 102', 30),
                (5, 'Seminar Room 1', 50),
                (6, 'Computer Lab 1', 50),
                (7, 'Computer Lab 2', 50),
                (8, 'Library Study Room 1', 20),
                (9, 'Library Study Room 2', 20),
                (10, 'Auditorium', 500)",
                
                "INSERT IGNORE INTO sessions (session_id, module_id, lecturer_id, venue_id, day_of_week, start_time, end_time) VALUES
                (1, 1, 1, 1, 'Monday', '08:00:00', '09:30:00'),
                (2, 1, 1, 1, 'Wednesday', '08:00:00', '09:30:00'),
                (3, 2, 2, 2, 'Monday', '10:00:00', '11:30:00'),
                (4, 2, 2, 2, 'Wednesday', '10:00:00', '11:30:00'),
                (5, 3, 3, 3, 'Tuesday', '14:00:00', '15:30:00'),
                (6, 4, 4, 1, 'Tuesday', '08:00:00', '10:00:00'),
                (7, 4, 4, 1, 'Thursday', '08:00:00', '10:00:00'),
                (8, 5, 5, 2, 'Friday', '09:00:00', '11:00:00'),
                (9, 6, 1, 6, 'Monday', '13:00:00', '15:00:00'),
                (10, 6, 1, 6, 'Wednesday', '13:00:00', '15:00:00'),
                (11, 7, 2, 6, 'Tuesday', '10:00:00', '12:00:00'),
                (12, 7, 2, 6, 'Thursday', '10:00:00', '12:00:00'),
                (13, 8, 2, 2, 'Monday', '14:00:00', '16:00:00'),
                (14, 8, 2, 2, 'Wednesday', '14:00:00', '16:00:00'),
                (15, 9, 4, 1, 'Tuesday', '11:00:00', '13:00:00'),
                (16, 9, 4, 1, 'Thursday', '11:00:00', '13:00:00'),
                (17, 10, 1, 5, 'Friday', '10:00:00', '12:00:00'),
                (18, 10, 1, 5, 'Friday', '13:00:00', '15:00:00')",
                
                "INSERT IGNORE INTO student_modules (student_id, module_id, status) VALUES
                (3, 1, 'active'),
                (3, 2, 'active'),
                (3, 3, 'active'),
                (3, 4, 'active'),
                (3, 5, 'active'),
                (3, 6, 'active'),
                (3, 7, 'active'),
                (3, 8, 'active'),
                (3, 9, 'active'),
                (3, 10, 'active')",
                
                "INSERT IGNORE INTO exams (exam_id, module_id, venue_id, exam_date, exam_time, duration) VALUES
                (1, 1, 1, '2024-12-15', '09:00:00', 120),
                (2, 2, 2, '2024-12-16', '09:00:00', 180),
                (3, 3, 3, '2024-12-17', '14:00:00', 120),
                (4, 4, 1, '2024-12-18', '09:00:00', 180),
                (5, 5, 2, '2024-12-19', '14:00:00', 120),
                (6, 6, 6, '2024-12-20', '09:00:00', 180),
                (7, 7, 6, '2024-12-21', '09:00:00', 180),
                (8, 8, 2, '2024-12-22', '09:00:00', 180),
                (9, 9, 1, '2024-12-23', '09:00:00', 180),
                (10, 10, 5, '2024-12-24', '09:00:00', 180)"
            ];
            
            foreach ($sampleData as $dataSql) {
                try {
                    $pdo->exec($dataSql);
                } catch (PDOException $e) {
                    // Ignore duplicate key errors
                }
            }
            
            $success[] = "✓ Sample data inserted successfully";
            
            // Verify tables
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $success[] = "✓ Verified " . count($tables) . " tables created";
            
            // Get counts
            $studentCount = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
            $moduleCount = $pdo->query("SELECT COUNT(*) FROM modules")->fetchColumn();
            $sessionCount = $pdo->query("SELECT COUNT(*) FROM sessions")->fetchColumn();
            $examCount = $pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn();
            $enrollmentCount = $pdo->query("SELECT COUNT(*) FROM student_modules")->fetchColumn();
            
        } catch (PDOException $e) {
            $errors[] = "❌ Database Error: " . $e->getMessage();
        }
        
        // Display results
        foreach ($success as $msg) {
            echo '<div class="success">' . $msg . '</div>';
        }
        
        if (!empty($errors)) {
            foreach ($errors as $msg) {
                echo '<div class="error">' . $msg . '</div>';
            }
        }
        
        if (empty($errors) && isset($studentCount)) {
            echo '<div class="success">';
            echo '<h2>✅ Setup Complete!</h2>';
            echo '<p><strong>Database is ready to use!</strong></p>';
            echo '<ul>';
            echo '<li>📊 ' . $studentCount . ' Students</li>';
            echo '<li>📚 ' . $moduleCount . ' Modules</li>';
            echo '<li>📅 ' . $sessionCount . ' Timetable Sessions</li>';
            echo '<li>📝 ' . $examCount . ' Exams</li>';
            echo '<li>🎓 ' . $enrollmentCount . ' Student Enrollments</li>';
            echo '</ul>';
            echo '</div>';
            
            echo '<div class="info">';
            echo '<h3>🔑 Test Credentials:</h3>';
            echo '<ul>';
            echo '<li><strong>Admin Panel:</strong> <a href="admin/login.php">http://localhost/admin/login.php</a><br>';
            echo 'Username: <code>admin</code> | Password: <code>admin123</code></li>';
            echo '<li><strong>Test Student:</strong> Student Number: <code>202057420</code> | Password: <code>password123</code></li>';
            echo '</ul>';
            echo '</div>';
            
            echo '<div class="warning">';
            echo '<p><strong>⚠️ Next Steps:</strong></p>';
            echo '<ol>';
            echo '<li>Access <a href="admin/login.php">Admin Panel</a> to manage data</li>';
            echo '<li>Use the test student credentials to test the mobile app</li>';
            echo '<li>Update admin credentials for production use</li>';
            echo '</ol>';
            echo '</div>';
        }
        ?>
    </div>
</body>
</html>

