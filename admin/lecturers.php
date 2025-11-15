<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=smart_timetable", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (isset($_GET['delete']) && isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM lecturers WHERE lecturer_id = ?");
    $stmt->execute([$_GET['id']]);
    header('Location: lecturers.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['lecturer_id']) && $_POST['lecturer_id']) {
        $stmt = $pdo->prepare("UPDATE lecturers SET lecturer_name = ?, email = ? WHERE lecturer_id = ?");
        $stmt->execute([$_POST['lecturer_name'], $_POST['email'], $_POST['lecturer_id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO lecturers (lecturer_name, email) VALUES (?, ?)");
        $stmt->execute([$_POST['lecturer_name'], $_POST['email']]);
    }
    header('Location: lecturers.php');
    exit;
}

$lecturers = $pdo->query("SELECT * FROM lecturers ORDER BY lecturer_name")->fetchAll(PDO::FETCH_ASSOC);
$editLecturer = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM lecturers WHERE lecturer_id = ?");
    $stmt->execute([$_GET['edit']]);
    $editLecturer = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Lecturers</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <h2>Manage Lecturers</h2>
        <form method="POST" class="form">
            <input type="hidden" name="lecturer_id" value="<?= $editLecturer['lecturer_id'] ?? '' ?>">
            <input type="text" name="lecturer_name" placeholder="Lecturer Name" value="<?= htmlspecialchars($editLecturer['lecturer_name'] ?? '') ?>" required>
            <input type="email" name="email" placeholder="Email" value="<?= htmlspecialchars($editLecturer['email'] ?? '') ?>">
            <button type="submit"><?= $editLecturer ? 'Update' : 'Add' ?> Lecturer</button>
            <?php if ($editLecturer): ?>
                <a href="lecturers.php" class="btn-cancel">Cancel</a>
            <?php endif; ?>
        </form>
        <table>
            <thead>
                <tr><th>ID</th><th>Name</th><th>Email</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($lecturers as $lecturer): ?>
                <tr>
                    <td><?= $lecturer['lecturer_id'] ?></td>
                    <td><?= htmlspecialchars($lecturer['lecturer_name']) ?></td>
                    <td><?= htmlspecialchars($lecturer['email'] ?? '-') ?></td>
                    <td>
                        <a href="?edit=<?= $lecturer['lecturer_id'] ?>">Edit</a> |
                        <a href="?delete=1&id=<?= $lecturer['lecturer_id'] ?>" onclick="return confirm('Are you sure?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

