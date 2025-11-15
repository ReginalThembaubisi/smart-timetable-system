<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';

$pdo = new PDO("mysql:host=localhost;dbname=smart_timetable", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Handle delete
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM students WHERE student_id = ?");
    $stmt->execute([$_GET['id']]);
    header('Location: students.php');
    exit;
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['student_id']) && $_POST['student_id']) {
        // Update
        $stmt = $pdo->prepare("UPDATE students SET student_number = ?, full_name = ?, email = ?, password = ? WHERE student_id = ?");
        $stmt->execute([$_POST['student_number'], $_POST['full_name'], $_POST['email'], $_POST['password'], $_POST['student_id']]);
    } else {
        // Insert
        $stmt = $pdo->prepare("INSERT INTO students (student_number, full_name, email, password) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['student_number'], $_POST['full_name'], $_POST['email'], $_POST['password']]);
    }
    header('Location: students.php');
    exit;
}

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
if ($search) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE student_number LIKE ? OR full_name LIKE ? OR email LIKE ? ORDER BY student_id DESC");
    $searchTerm = "%{$search}%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $students = $pdo->query("SELECT * FROM students ORDER BY student_id DESC")->fetchAll(PDO::FETCH_ASSOC);
}
$editStudent = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmt->execute([$_GET['edit']]);
    $editStudent = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Students</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <a href="index.php" class="back-link">← Back to Dashboard</a>
        <h2>Manage Students</h2>
        
        <div class="search-bar">
            <form method="GET" style="display: inline;">
                <input type="text" name="search" placeholder="Search by student number, name, or email..." value="<?= htmlspecialchars($search) ?>" style="width: 400px;">
                <button type="submit" style="margin: 0;">Search</button>
                <?php if ($search): ?>
                    <a href="students.php" class="btn" style="background: #95a5a6;">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        
        <form method="POST" class="form">
            <input type="hidden" name="student_id" value="<?= $editStudent['student_id'] ?? '' ?>">
            <input type="text" name="student_number" placeholder="Student Number" value="<?= htmlspecialchars($editStudent['student_number'] ?? '') ?>" required>
            <input type="text" name="full_name" placeholder="Full Name" value="<?= htmlspecialchars($editStudent['full_name'] ?? '') ?>" required>
            <input type="email" name="email" placeholder="Email" value="<?= htmlspecialchars($editStudent['email'] ?? '') ?>" required>
            <input type="text" name="password" placeholder="Password" value="<?= htmlspecialchars($editStudent['password'] ?? '') ?>" required>
            <button type="submit"><?= $editStudent ? 'Update' : 'Add' ?> Student</button>
            <?php if ($editStudent): ?>
                <a href="students.php" class="btn-cancel">Cancel</a>
            <?php endif; ?>
        </form>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Student Number</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                <tr>
                    <td><?= $student['student_id'] ?></td>
                    <td><?= htmlspecialchars($student['student_number']) ?></td>
                    <td><?= htmlspecialchars($student['full_name']) ?></td>
                    <td><?= htmlspecialchars($student['email']) ?></td>
                    <td>
                        <a href="?edit=<?= $student['student_id'] ?>">Edit</a> |
                        <a href="?delete=1&id=<?= $student['student_id'] ?>" onclick="return confirm('Are you sure?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

