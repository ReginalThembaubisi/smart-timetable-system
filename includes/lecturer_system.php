<?php
require_once __DIR__ . '/database.php';

function ensureLecturerSystemTables(PDO $pdo): void
{
    $hasLecturerCode = false;
    try {
        $colStmt = $pdo->query("SHOW COLUMNS FROM lecturers LIKE 'lecturer_code'");
        $hasLecturerCode = (bool) $colStmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $t) {
        $hasLecturerCode = false;
    }
    if (!$hasLecturerCode) {
        $pdo->exec("ALTER TABLE lecturers ADD COLUMN lecturer_code VARCHAR(50) NULL");
    }

    $hasLecturerCodeIndex = false;
    try {
        $indexStmt = $pdo->query("SHOW INDEX FROM lecturers WHERE Column_name = 'lecturer_code'");
        $hasLecturerCodeIndex = (bool) $indexStmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $t) {
        $hasLecturerCodeIndex = false;
    }
    if (!$hasLecturerCodeIndex) {
        $pdo->exec("CREATE UNIQUE INDEX idx_lecturer_code_unique ON lecturers (lecturer_code)");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS lecturer_auth (
            auth_id INT AUTO_INCREMENT PRIMARY KEY,
            lecturer_id INT NOT NULL,
            login_identifier VARCHAR(255) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            last_login TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_lecturer_auth_lecturer (lecturer_id),
            UNIQUE KEY unique_lecturer_auth_identifier (login_identifier),
            INDEX idx_lecturer_auth_active (is_active),
            CONSTRAINT fk_lecturer_auth_lecturer
                FOREIGN KEY (lecturer_id) REFERENCES lecturers(lecturer_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS lecturer_assessments (
            assessment_id INT AUTO_INCREMENT PRIMARY KEY,
            module_id INT NOT NULL,
            created_by_lecturer_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            assessment_date DATE NOT NULL,
            assessment_time TIME NOT NULL,
            duration INT NOT NULL DEFAULT 60,
            status VARCHAR(20) NOT NULL DEFAULT 'published',
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_assessment_module (module_id),
            INDEX idx_assessment_lecturer (created_by_lecturer_id),
            INDEX idx_assessment_date (assessment_date),
            CONSTRAINT fk_assessment_module
                FOREIGN KEY (module_id) REFERENCES modules(module_id) ON DELETE CASCADE,
            CONSTRAINT fk_assessment_lecturer
                FOREIGN KEY (created_by_lecturer_id) REFERENCES lecturers(lecturer_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS student_assessment_notifications (
            notification_id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            assessment_id INT NOT NULL,
            notification_type VARCHAR(30) NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            scheduled_for DATETIME NOT NULL,
            sent_at DATETIME NULL,
            is_sent TINYINT(1) NOT NULL DEFAULT 0,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_san_student (student_id),
            INDEX idx_san_assessment (assessment_id),
            INDEX idx_san_schedule (is_sent, scheduled_for),
            CONSTRAINT fk_san_student
                FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
            CONSTRAINT fk_san_assessment
                FOREIGN KEY (assessment_id) REFERENCES lecturer_assessments(assessment_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function getSharedCohortModuleIds(PDO $pdo, int $moduleId): array
{
    $stmt = $pdo->prepare("
        SELECT DISTINCT sm2.module_id
        FROM student_modules sm
        JOIN student_modules sm2 ON sm.student_id = sm2.student_id
        WHERE sm.module_id = ?
    ");
    $stmt->execute([$moduleId]);

    $ids = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ids[] = (int) $row['module_id'];
    }

    if (!in_array($moduleId, $ids, true)) {
        $ids[] = $moduleId;
    }

    return array_values(array_unique($ids));
}

function getAssessmentConflictCount(PDO $pdo, array $sharedModuleIds, string $candidateDate, int $windowDays = 2): int
{
    if (empty($sharedModuleIds)) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($sharedModuleIds), '?'));
    $startDate = date('Y-m-d', strtotime($candidateDate . " -{$windowDays} day"));
    $endDate = date('Y-m-d', strtotime($candidateDate . " +{$windowDays} day"));

    $assessmentSql = "
        SELECT COUNT(*) AS total
        FROM lecturer_assessments
        WHERE module_id IN ($placeholders)
          AND assessment_date BETWEEN ? AND ?
    ";
    $stmt = $pdo->prepare($assessmentSql);
    $stmt->execute(array_merge($sharedModuleIds, [$startDate, $endDate]));
    $assessmentCount = (int) $stmt->fetchColumn();

    $examSql = "
        SELECT COUNT(*) AS total
        FROM exams
        WHERE module_id IN ($placeholders)
          AND exam_date BETWEEN ? AND ?
    ";
    $stmt = $pdo->prepare($examSql);
    $stmt->execute(array_merge($sharedModuleIds, [$startDate, $endDate]));
    $examCount = (int) $stmt->fetchColumn();

    return $assessmentCount + $examCount;
}

function queueAssessmentNotifications(
    PDO $pdo,
    int $assessmentId,
    int $moduleId,
    string $title,
    string $assessmentDate,
    string $assessmentTime
): int {
    $studentsStmt = $pdo->prepare("
        SELECT DISTINCT student_id
        FROM student_modules
        WHERE module_id = ?
    ");
    $studentsStmt->execute([$moduleId]);
    $studentRows = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

    $assessmentDateTime = strtotime($assessmentDate . ' ' . $assessmentTime);
    $now = time();

    $notificationPlans = [
        [
            'type' => 'immediate',
            'scheduled_for' => date('Y-m-d H:i:s', $now),
            'title' => 'New test published',
            'message' => "A lecturer published \"$title\" for {$assessmentDate} at " . substr($assessmentTime, 0, 5) . '.',
            'is_sent' => 1,
            'sent_at' => date('Y-m-d H:i:s', $now),
        ],
        [
            'type' => 'd7',
            'scheduled_for' => date('Y-m-d H:i:s', strtotime('-7 day', $assessmentDateTime)),
            'title' => 'Test reminder (7 days)',
            'message' => "Reminder: \"$title\" is in 7 days ({$assessmentDate}).",
            'is_sent' => 0,
            'sent_at' => null,
        ],
        [
            'type' => 'd1',
            'scheduled_for' => date('Y-m-d H:i:s', strtotime('-1 day', $assessmentDateTime)),
            'title' => 'Test reminder (1 day)',
            'message' => "Reminder: \"$title\" is tomorrow at " . substr($assessmentTime, 0, 5) . '.',
            'is_sent' => 0,
            'sent_at' => null,
        ],
    ];

    $insertStmt = $pdo->prepare("
        INSERT INTO student_assessment_notifications (
            student_id, assessment_id, notification_type, title, message, scheduled_for, is_sent, sent_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $inserted = 0;
    foreach ($studentRows as $studentRow) {
        $studentId = (int) $studentRow['student_id'];
        foreach ($notificationPlans as $plan) {
            if (strtotime($plan['scheduled_for']) <= $now && $plan['type'] !== 'immediate') {
                continue;
            }
            $insertStmt->execute([
                $studentId,
                $assessmentId,
                $plan['type'],
                $plan['title'],
                $plan['message'],
                $plan['scheduled_for'],
                $plan['is_sent'],
                $plan['sent_at'],
            ]);
            $inserted++;
        }
    }

    return $inserted;
}

