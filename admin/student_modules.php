<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=smart_timetable", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create student_modules table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_modules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        module_id INT NOT NULL,
        enrollment_date DATE DEFAULT CURRENT_DATE,
        status VARCHAR(20) DEFAULT 'active',
        FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
        FOREIGN KEY (module_id) REFERENCES modules(module_id) ON DELETE CASCADE,
        UNIQUE KEY unique_enrollment (student_id, module_id)
    )");
} catch (PDOException $e) {}

// Handle delete
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM student_modules WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    header('Location: student_modules.php');
    exit;
}

// Handle add/edit enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = (int)$_POST['student_id'];
    $moduleId = (int)$_POST['module_id'];
    $status = $_POST['status'] ?? 'active';
    
    // Check if enrollment already exists
    $stmt = $pdo->prepare("SELECT id FROM student_modules WHERE student_id = ? AND module_id = ?");
    $stmt->execute([$studentId, $moduleId]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        // Update existing enrollment
        $stmt = $pdo->prepare("UPDATE student_modules SET status = ? WHERE id = ?");
        $stmt->execute([$status, $exists['id']]);
    } else {
        // Insert new enrollment
        $stmt = $pdo->prepare("INSERT INTO student_modules (student_id, module_id, status) VALUES (?, ?, ?)");
        $stmt->execute([$studentId, $moduleId, $status]);
    }
    header('Location: student_modules.php');
    exit;
}

// Get all enrollments with student and module info
$enrollments = $pdo->query("
    SELECT sm.*, s.student_number, s.full_name, m.module_code, m.module_name, m.credits
    FROM student_modules sm
    JOIN students s ON sm.student_id = s.student_id
    JOIN modules m ON sm.module_id = m.module_id
    ORDER BY sm.enrollment_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

$students = $pdo->query("SELECT * FROM students ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
$modules = $pdo->query("SELECT * FROM modules ORDER BY module_code")->fetchAll(PDO::FETCH_ASSOC);

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
if ($search) {
    $stmt = $pdo->prepare("
        SELECT sm.*, s.student_number, s.full_name, m.module_code, m.module_name, m.credits
        FROM student_modules sm
        JOIN students s ON sm.student_id = s.student_id
        JOIN modules m ON sm.module_id = m.module_id
        WHERE s.student_number LIKE ? OR s.full_name LIKE ? OR m.module_code LIKE ? OR m.module_name LIKE ?
        ORDER BY sm.enrollment_date DESC
    ");
    $searchTerm = "%{$search}%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Module Enrollment</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <a href="index.php" class="back-link">← Back to Dashboard</a>
        <h2>Student Module Enrollment</h2>
        
        <div class="search-bar">
            <form method="GET" style="display: inline;">
                <input type="text" name="search" placeholder="Search by student number, name, or module code..." value="<?= htmlspecialchars($search) ?>" style="width: 400px;">
                <button type="submit" style="margin: 0;">Search</button>
                <?php if ($search): ?>
                    <a href="student_modules.php" class="btn" style="background: #95a5a6;">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        
        <form method="POST" class="form">
            <div class="form-row">
                <select name="student_id" required>
                    <option value="">Select Student</option>
                    <?php foreach ($students as $student): ?>
                        <option value="<?= $student['student_id'] ?>">
                            <?= htmlspecialchars($student['student_number'] . ' - ' . $student['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="module_id" required>
                    <option value="">Select Module</option>
                    <?php foreach ($modules as $module): ?>
                        <option value="<?= $module['module_id'] ?>">
                            <?= htmlspecialchars($module['module_code'] . ' - ' . $module['module_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <button type="submit">Enroll Student</button>
        </form>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Student</th>
                    <th>Module</th>
                    <th>Credits</th>
                    <th>Enrollment Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($enrollments)): ?>
                    <tr><td colspan="7" style="text-align: center; padding: 30px; color: #7f8c8d;">No enrollments found. Enroll students in modules above.</td></tr>
                <?php else: ?>
                    <?php foreach ($enrollments as $enrollment): ?>
                    <tr>
                        <td><?= $enrollment['id'] ?></td>
                        <td><?= htmlspecialchars($enrollment['student_number'] . ' - ' . $enrollment['full_name']) ?></td>
                        <td><?= htmlspecialchars($enrollment['module_code'] . ' - ' . $enrollment['module_name']) ?></td>
                        <td><?= $enrollment['credits'] ?? '-' ?></td>
                        <td><?= $enrollment['enrollment_date'] ?></td>
                        <td><span style="padding: 5px 10px; border-radius: 3px; background: <?= $enrollment['status'] === 'active' ? '#d4edda' : '#f8d7da' ?>; color: <?= $enrollment['status'] === 'active' ? '#155724' : '#721c24' ?>;"><?= ucfirst($enrollment['status']) ?></span></td>
                        <td>
                            <a href="?delete=1&id=<?= $enrollment['id'] ?>" onclick="return confirm('Are you sure you want to remove this enrollment?')" class="btn-danger" style="padding: 5px 10px; font-size: 12px;">Remove</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

