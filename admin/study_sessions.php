<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=smart_timetable", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS study_sessions (
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
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {}

if (isset($_GET['delete']) && isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM study_sessions WHERE session_id = ?");
    $stmt->execute([$_GET['id']]);
    header('Location: study_sessions.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['session_id']) && $_POST['session_id']) {
        $stmt = $pdo->prepare("UPDATE study_sessions SET student_id = ?, title = ?, module_name = ?, day_of_week = ?, start_time = ?, end_time = ?, duration = ?, session_type = ?, notes = ? WHERE session_id = ?");
        $stmt->execute([$_POST['student_id'], $_POST['title'], $_POST['module_name'] ?: null, $_POST['day_of_week'], $_POST['start_time'], $_POST['end_time'], $_POST['duration'] ?: null, $_POST['session_type'] ?: null, $_POST['notes'] ?: null, $_POST['session_id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO study_sessions (student_id, title, module_name, day_of_week, start_time, end_time, duration, session_type, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['student_id'], $_POST['title'], $_POST['module_name'] ?: null, $_POST['day_of_week'], $_POST['start_time'], $_POST['end_time'], $_POST['duration'] ?: null, $_POST['session_type'] ?: null, $_POST['notes'] ?: null]);
    }
    header('Location: study_sessions.php');
    exit;
}

$sessions = $pdo->query("SELECT ss.*, s.student_number, s.full_name FROM study_sessions ss LEFT JOIN students s ON ss.student_id = s.student_id ORDER BY ss.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$students = $pdo->query("SELECT * FROM students ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

$editSession = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM study_sessions WHERE session_id = ?");
    $stmt->execute([$_GET['edit']]);
    $editSession = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Study Sessions</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <a href="index.php" class="back-link">← Back to Dashboard</a>
        <h2>Manage Study Sessions</h2>
        <form method="POST" class="form">
            <input type="hidden" name="session_id" value="<?= $editSession['session_id'] ?? '' ?>">
            <select name="student_id" required>
                <option value="">Select Student</option>
                <?php foreach ($students as $student): ?>
                    <option value="<?= $student['student_id'] ?>" <?= ($editSession['student_id'] ?? '') == $student['student_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($student['student_number'] . ' - ' . $student['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="title" placeholder="Session Title" value="<?= htmlspecialchars($editSession['title'] ?? '') ?>" required>
            <input type="text" name="module_name" placeholder="Module Name" value="<?= htmlspecialchars($editSession['module_name'] ?? '') ?>">
            <select name="day_of_week" required>
                <option value="">Select Day</option>
                <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day): ?>
                    <option value="<?= $day ?>" <?= ($editSession['day_of_week'] ?? '') == $day ? 'selected' : '' ?>><?= $day ?></option>
                <?php endforeach; ?>
            </select>
            <input type="time" name="start_time" value="<?= $editSession['start_time'] ?? '' ?>" required>
            <input type="time" name="end_time" value="<?= $editSession['end_time'] ?? '' ?>" required>
            <input type="number" name="duration" placeholder="Duration (minutes)" value="<?= $editSession['duration'] ?? '' ?>">
            <input type="text" name="session_type" placeholder="Session Type" value="<?= htmlspecialchars($editSession['session_type'] ?? '') ?>">
            <textarea name="notes" placeholder="Notes"><?= htmlspecialchars($editSession['notes'] ?? '') ?></textarea>
            <button type="submit"><?= $editSession ? 'Update' : 'Add' ?> Session</button>
            <?php if ($editSession): ?>
                <a href="study_sessions.php" class="btn-cancel">Cancel</a>
            <?php endif; ?>
        </form>
        <table>
            <thead>
                <tr><th>ID</th><th>Student</th><th>Title</th><th>Module</th><th>Day</th><th>Time</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($sessions as $session): ?>
                <tr>
                    <td><?= $session['session_id'] ?></td>
                    <td><?= htmlspecialchars($session['student_number'] . ' - ' . $session['full_name']) ?></td>
                    <td><?= htmlspecialchars($session['title']) ?></td>
                    <td><?= htmlspecialchars($session['module_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($session['day_of_week']) ?></td>
                    <td><?= htmlspecialchars($session['start_time'] . ' - ' . $session['end_time']) ?></td>
                    <td>
                        <a href="?edit=<?= $session['session_id'] ?>">Edit</a> |
                        <a href="?delete=1&id=<?= $session['session_id'] ?>" onclick="return confirm('Are you sure?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

