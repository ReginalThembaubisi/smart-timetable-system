<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=smart_timetable", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (isset($_GET['delete']) && isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM sessions WHERE session_id = ?");
    $stmt->execute([$_GET['id']]);
    header('Location: timetable.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['session_id']) && $_POST['session_id']) {
        $stmt = $pdo->prepare("UPDATE sessions SET module_id = ?, lecturer_id = ?, venue_id = ?, day_of_week = ?, start_time = ?, end_time = ? WHERE session_id = ?");
        $stmt->execute([$_POST['module_id'], $_POST['lecturer_id'] ?: null, $_POST['venue_id'] ?: null, $_POST['day_of_week'], $_POST['start_time'], $_POST['end_time'], $_POST['session_id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO sessions (module_id, lecturer_id, venue_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['module_id'], $_POST['lecturer_id'] ?: null, $_POST['venue_id'] ?: null, $_POST['day_of_week'], $_POST['start_time'], $_POST['end_time']]);
    }
    header('Location: timetable.php');
    exit;
}

$sessions = $pdo->query("SELECT s.*, m.module_code, m.module_name, l.lecturer_name, v.venue_name FROM sessions s LEFT JOIN modules m ON s.module_id = m.module_id LEFT JOIN lecturers l ON s.lecturer_id = l.lecturer_id LEFT JOIN venues v ON s.venue_id = v.venue_id ORDER BY s.day_of_week, s.start_time")->fetchAll(PDO::FETCH_ASSOC);
$modules = $pdo->query("SELECT * FROM modules ORDER BY module_code")->fetchAll(PDO::FETCH_ASSOC);
$lecturers = $pdo->query("SELECT * FROM lecturers ORDER BY lecturer_name")->fetchAll(PDO::FETCH_ASSOC);
$venues = $pdo->query("SELECT * FROM venues ORDER BY venue_name")->fetchAll(PDO::FETCH_ASSOC);

$editSession = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM sessions WHERE session_id = ?");
    $stmt->execute([$_GET['edit']]);
    $editSession = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Timetable</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <a href="index.php" class="back-link">← Back to Dashboard</a>
        <h2>Manage Timetable Sessions</h2>
        <form method="POST" class="form">
            <input type="hidden" name="session_id" value="<?= $editSession['session_id'] ?? '' ?>">
            <select name="module_id" required>
                <option value="">Select Module</option>
                <?php foreach ($modules as $module): ?>
                    <option value="<?= $module['module_id'] ?>" <?= ($editSession['module_id'] ?? '') == $module['module_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($module['module_code'] . ' - ' . $module['module_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="lecturer_id">
                <option value="">Select Lecturer</option>
                <?php foreach ($lecturers as $lecturer): ?>
                    <option value="<?= $lecturer['lecturer_id'] ?>" <?= ($editSession['lecturer_id'] ?? '') == $lecturer['lecturer_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($lecturer['lecturer_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="venue_id">
                <option value="">Select Venue</option>
                <?php foreach ($venues as $venue): ?>
                    <option value="<?= $venue['venue_id'] ?>" <?= ($editSession['venue_id'] ?? '') == $venue['venue_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($venue['venue_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="day_of_week" required>
                <option value="">Select Day</option>
                <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day): ?>
                    <option value="<?= $day ?>" <?= ($editSession['day_of_week'] ?? '') == $day ? 'selected' : '' ?>><?= $day ?></option>
                <?php endforeach; ?>
            </select>
            <input type="time" name="start_time" placeholder="Start Time" value="<?= $editSession['start_time'] ?? '' ?>" required>
            <input type="time" name="end_time" placeholder="End Time" value="<?= $editSession['end_time'] ?? '' ?>" required>
            <button type="submit"><?= $editSession ? 'Update' : 'Add' ?> Session</button>
            <?php if ($editSession): ?>
                <a href="timetable.php" class="btn-cancel">Cancel</a>
            <?php endif; ?>
        </form>
        <table>
            <thead>
                <tr><th>ID</th><th>Module</th><th>Day</th><th>Time</th><th>Lecturer</th><th>Venue</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($sessions as $session): ?>
                <tr>
                    <td><?= $session['session_id'] ?></td>
                    <td><?= htmlspecialchars($session['module_code'] . ' - ' . $session['module_name']) ?></td>
                    <td><?= htmlspecialchars($session['day_of_week']) ?></td>
                    <td><?= htmlspecialchars($session['start_time'] . ' - ' . $session['end_time']) ?></td>
                    <td><?= htmlspecialchars($session['lecturer_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($session['venue_name'] ?? '-') ?></td>
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

