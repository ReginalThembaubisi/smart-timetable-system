<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = Database::getInstance()->getConnection();

$type = $_GET['type'] ?? '';
$format = $_GET['format'] ?? 'csv';

if (empty($type)) {
    header('Location: index.php');
    exit;
}

try {
    switch ($type) {
        case 'students':
            $data = $pdo->query("SELECT student_id, student_number, full_name, email, created_at FROM students ORDER BY student_number")->fetchAll(PDO::FETCH_ASSOC);
            $filename = 'students_export_' . date('Y-m-d') . '.csv';
            $headers = ['ID', 'Student Number', 'Full Name', 'Email', 'Created At'];
            break;
            
        case 'modules':
            $data = $pdo->query("SELECT module_id, module_code, module_name, credits, created_at FROM modules ORDER BY module_code")->fetchAll(PDO::FETCH_ASSOC);
            $filename = 'modules_export_' . date('Y-m-d') . '.csv';
            $headers = ['ID', 'Module Code', 'Module Name', 'Credits', 'Created At'];
            break;
            
        case 'lecturers':
            $data = $pdo->query("SELECT lecturer_id, lecturer_name, email, created_at FROM lecturers ORDER BY lecturer_name")->fetchAll(PDO::FETCH_ASSOC);
            $filename = 'lecturers_export_' . date('Y-m-d') . '.csv';
            $headers = ['ID', 'Lecturer Name', 'Email', 'Created At'];
            break;
            
        case 'venues':
            $data = $pdo->query("SELECT venue_id, venue_name, capacity, created_at FROM venues ORDER BY venue_name")->fetchAll(PDO::FETCH_ASSOC);
            $filename = 'venues_export_' . date('Y-m-d') . '.csv';
            $headers = ['ID', 'Venue Name', 'Capacity', 'Created At'];
            break;
            
        case 'enrollments':
            $data = $pdo->query("
                SELECT sm.id, s.student_number, s.full_name, m.module_code, m.module_name, sm.status, sm.enrollment_date
                FROM student_modules sm
                JOIN students s ON sm.student_id = s.student_id
                JOIN modules m ON sm.module_id = m.module_id
                ORDER BY sm.enrollment_date DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            $filename = 'enrollments_export_' . date('Y-m-d') . '.csv';
            $headers = ['ID', 'Student Number', 'Student Name', 'Module Code', 'Module Name', 'Status', 'Enrollment Date'];
            break;
            
        default:
            header('Location: index.php');
            exit;
    }
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8 Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write headers
    fputcsv($output, $headers);
    
    // Write data
    foreach ($data as $row) {
        fputcsv($output, array_values($row));
    }
    
    fclose($output);
    exit;
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Error exporting data: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}
?>

