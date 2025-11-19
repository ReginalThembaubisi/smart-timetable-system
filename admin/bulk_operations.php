<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$action = $_POST['action'] ?? '';
$items = $_POST['items'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($items)) {
    try {
        $pdo->beginTransaction();
        $count = 0;
        
        switch ($action) {
            case 'delete_students':
                $placeholders = implode(',', array_fill(0, count($items), '?'));
                $stmt = $pdo->prepare("DELETE FROM students WHERE student_id IN ($placeholders)");
                $stmt->execute($items);
                $count = $stmt->rowCount();
                logActivity('bulk_delete', "Deleted $count students", getCurrentUserId());
                break;
                
            case 'delete_modules':
                $placeholders = implode(',', array_fill(0, count($items), '?'));
                $stmt = $pdo->prepare("DELETE FROM modules WHERE module_id IN ($placeholders)");
                $stmt->execute($items);
                $count = $stmt->rowCount();
                logActivity('bulk_delete', "Deleted $count modules", getCurrentUserId());
                break;
                
            case 'delete_lecturers':
                $placeholders = implode(',', array_fill(0, count($items), '?'));
                $stmt = $pdo->prepare("DELETE FROM lecturers WHERE lecturer_id IN ($placeholders)");
                $stmt->execute($items);
                $count = $stmt->rowCount();
                logActivity('bulk_delete', "Deleted $count lecturers", getCurrentUserId());
                break;
                
            case 'delete_venues':
                $placeholders = implode(',', array_fill(0, count($items), '?'));
                $stmt = $pdo->prepare("DELETE FROM venues WHERE venue_id IN ($placeholders)");
                $stmt->execute($items);
                $count = $stmt->rowCount();
                logActivity('bulk_delete', "Deleted $count venues", getCurrentUserId());
                break;
        }
        
        $pdo->commit();
        $_SESSION['success_message'] = "Successfully deleted $count item(s)";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = 'Error performing bulk operation: ' . $e->getMessage();
    }
    
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
    exit;
}

header('Location: index.php');
exit;
?>

