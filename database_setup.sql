-- Smart Timetable Database Setup
-- Run this SQL file in phpMyAdmin or MySQL command line
-- Database: smart_timetable

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS smart_timetable;
USE smart_timetable;

-- Drop tables if they exist (in reverse order of dependencies)
DROP TABLE IF EXISTS exam_notifications;
DROP TABLE IF EXISTS student_modules;
DROP TABLE IF EXISTS exams;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS modules;
DROP TABLE IF EXISTS lecturers;
DROP TABLE IF EXISTS venues;

-- Create students table
CREATE TABLE students (
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    student_number VARCHAR(50) UNIQUE NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_student_number (student_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create modules table
CREATE TABLE modules (
    module_id INT AUTO_INCREMENT PRIMARY KEY,
    module_code VARCHAR(50) UNIQUE NOT NULL,
    module_name VARCHAR(255) NOT NULL,
    credits INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_module_code (module_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create lecturers table
CREATE TABLE lecturers (
    lecturer_id INT AUTO_INCREMENT PRIMARY KEY,
    lecturer_name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create venues table
CREATE TABLE venues (
    venue_id INT AUTO_INCREMENT PRIMARY KEY,
    venue_name VARCHAR(255) NOT NULL,
    capacity INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create sessions table (timetable sessions)
CREATE TABLE sessions (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create student_modules table (enrollment)
CREATE TABLE student_modules (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create exams table
CREATE TABLE exams (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create exam_notifications table
CREATE TABLE exam_notifications (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample data (optional - you can remove this section if you want to start fresh)

-- Sample students
INSERT INTO students (student_id, student_number, full_name, email, password) VALUES
(1, '202000001', 'John Doe', 'john.doe@university.edu', 'password123'),
(2, '202000002', 'Jane Smith', 'jane.smith@university.edu', 'password123'),
(3, '202057420', 'Themba', 'themba@university.edu', 'password123');

-- Sample modules
INSERT INTO modules (module_id, module_code, module_name, credits) VALUES
(1, 'CS101', 'Introduction to Computer Science', 3),
(2, 'MATH101', 'Calculus I', 4),
(3, 'ENG101', 'English Composition', 3),
(4, 'PHY101', 'Physics I', 4),
(5, 'CHEM101', 'Chemistry I', 4);

-- Sample lecturers
INSERT INTO lecturers (lecturer_id, lecturer_name, email) VALUES
(1, 'Dr. Sarah Johnson', 'sarah.johnson@university.edu'),
(2, 'Prof. Michael Brown', 'michael.brown@university.edu'),
(3, 'Dr. Emily Davis', 'emily.davis@university.edu');

-- Sample venues
INSERT INTO venues (venue_id, venue_name, capacity) VALUES
(1, 'Lecture Hall A', 200),
(2, 'Lecture Hall B', 150),
(3, 'Lab Room 101', 30),
(4, 'Lab Room 102', 30),
(5, 'Seminar Room 1', 50);

-- Sample timetable sessions
INSERT INTO sessions (session_id, module_id, lecturer_id, venue_id, day_of_week, start_time, end_time) VALUES
(1, 1, 1, 1, 'Monday', '08:00:00', '09:30:00'),
(2, 1, 1, 1, 'Wednesday', '08:00:00', '09:30:00'),
(3, 2, 2, 2, 'Monday', '10:00:00', '11:30:00'),
(4, 2, 2, 2, 'Wednesday', '10:00:00', '11:30:00'),
(5, 3, 3, 3, 'Tuesday', '14:00:00', '15:30:00');

-- Sample student enrollments (student 3 - Themba enrolled in modules)
INSERT INTO student_modules (student_id, module_id, status) VALUES
(3, 1, 'active'),
(3, 2, 'active'),
(3, 3, 'active'),
(3, 4, 'active'),
(3, 5, 'active');

-- Sample exams
INSERT INTO exams (exam_id, module_id, venue_id, exam_date, exam_time, duration) VALUES
(1, 1, 1, '2024-12-15', '09:00:00', 120),
(2, 2, 2, '2024-12-16', '09:00:00', 180),
(3, 3, 3, '2024-12-17', '14:00:00', 120);

-- Display success message
SELECT 'Database setup completed successfully!' AS message;

