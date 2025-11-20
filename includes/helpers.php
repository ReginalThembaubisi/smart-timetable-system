<?php
/**
 * Common Helper Functions
 * Utility functions used throughout the system
 */

require_once __DIR__ . '/database.php';

/**
 * Hash a password using BCRYPT
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

/**
 * Verify a password (supports both hashed and plain text for migration)
 * Returns true if password matches, false otherwise
 */
function verifyPassword($inputPassword, $storedPassword) {
    // If stored password is hashed (length >= 60), use password_verify
    if (strlen($storedPassword) >= 60) {
        return password_verify($inputPassword, $storedPassword);
    }
    // Otherwise, compare plain text (for backward compatibility during migration)
    return $inputPassword === $storedPassword;
}

/**
 * Sanitize input to prevent XSS attacks
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Log activity to activity log file
 */
function logActivity($action, $details = '', $userId = null) {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/activity.log';
    $timestamp = date('Y-m-d H:i:s');
    $user = $userId ? "User ID: $userId" : (isset($_SESSION['admin_logged_in']) ? 'Admin' : 'System');
    $logEntry = "$timestamp | Action: $action | User: $user | Details: $details" . PHP_EOL;
    
    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Get current user ID from session
 */
function getCurrentUserId() {
    return $_SESSION['admin_user_id'] ?? null;
}

/**
 * Ensure study_sessions table exists
 */
function ensureStudySessionsTable() {
    try {
        $pdo = Database::getInstance()->getConnection();
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS study_sessions (
            session_id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            day_of_week VARCHAR(20) NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            subject VARCHAR(255),
            location VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
            INDEX idx_student_day (student_id, day_of_week),
            INDEX idx_day_time (day_of_week, start_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {
        // Table might already exist, log but don't fail
        error_log("Study sessions table check: " . $e->getMessage());
    }
}

/**
 * Validate day of week
 */
function validateDayOfWeek($day) {
    $validDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    return in_array(ucfirst(strtolower($day)), $validDays);
}

/**
 * Validate time format (HH:MM:SS or HH:MM)
 */
function validateTimeFormat($time) {
    return preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $time);
}

/**
 * Validate email format
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate student number format (alphanumeric, typically 8-10 characters)
 */
function validateStudentNumber($studentNumber) {
    return preg_match('/^[A-Z0-9]{6,12}$/i', $studentNumber);
}

/**
 * Check if time range is valid (start < end)
 */
function isValidTimeRange($startTime, $endTime) {
    return strtotime($startTime) < strtotime($endTime);
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'Y-m-d') {
    if (empty($date)) return '';
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    return $timestamp ? date($format, $timestamp) : '';
}

/**
 * Format time for display
 */
function formatTime($time, $format = 'H:i') {
    if (empty($time)) return '';
    $timestamp = is_numeric($time) ? $time : strtotime($time);
    return $timestamp ? date($format, $timestamp) : '';
}

/**
 * Log error - fallback function if crud_helpers.php isn't loaded
 * This ensures logError() is always available
 */
if (!function_exists('logError')) {
    function logError($e, $context = 'Error', $additionalData = []) {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/error.log';
        $timestamp = date('Y-m-d H:i:s');
        $user = isset($_SESSION['admin_logged_in']) ? ($_SESSION['admin_user_id'] ?? 'Admin') : 'Guest';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        $logEntry = [
            'timestamp' => $timestamp,
            'context' => $context,
            'user' => $user,
            'ip' => $ip,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'code' => $e->getCode(),
            'trace' => $e->getTraceAsString()
        ];
        
        if (!empty($additionalData)) {
            $logEntry['additional'] = $additionalData;
        }
        
        $logLine = json_encode($logEntry) . PHP_EOL;
        @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
        
        // Also log to PHP error log
        error_log("{$context}: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    }
}
?>
