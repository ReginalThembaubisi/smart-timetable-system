<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=smart_timetable", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (isset($_GET['delete']) && isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM exams WHERE exam_id = ?");
    $stmt->execute([$_GET['id']]);
    header('Location: exams.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['exam_id']) && $_POST['exam_id']) {
        $stmt = $pdo->prepare("UPDATE exams SET module_id = ?, venue_id = ?, exam_date = ?, exam_time = ?, duration = ? WHERE exam_id = ?");
        $stmt->execute([$_POST['module_id'], $_POST['venue_id'] ?: null, $_POST['exam_date'], $_POST['exam_time'], $_POST['duration'], $_POST['exam_id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO exams (module_id, venue_id, exam_date, exam_time, duration) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['module_id'], $_POST['venue_id'] ?: null, $_POST['exam_date'], $_POST['exam_time'], $_POST['duration']]);
    }
    header('Location: exams.php');
    exit;
}

$exams = $pdo->query("SELECT e.*, m.module_code, m.module_name, v.venue_name FROM exams e LEFT JOIN modules m ON e.module_id = m.module_id LEFT JOIN venues v ON e.venue_id = v.venue_id ORDER BY e.exam_date, e.exam_time")->fetchAll(PDO::FETCH_ASSOC);
$modules = $pdo->query("SELECT * FROM modules ORDER BY module_code")->fetchAll(PDO::FETCH_ASSOC);
$venues = $pdo->query("SELECT * FROM venues ORDER BY venue_name")->fetchAll(PDO::FETCH_ASSOC);

$editExam = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM exams WHERE exam_id = ?");
    $stmt->execute([$_GET['edit']]);
    $editExam = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Exams</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <h2>Manage Exams</h2>
        <form method="POST" class="form">
            <input type="hidden" name="exam_id" value="<?= $editExam['exam_id'] ?? '' ?>">
            <select name="module_id" required>
                <option value="">Select Module</option>
                <?php foreach ($modules as $module): ?>
                    <option value="<?= $module['module_id'] ?>" <?= ($editExam['module_id'] ?? '') == $module['module_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($module['module_code'] . ' - ' . $module['module_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="venue_id">
                <option value="">Select Venue</option>
                <?php foreach ($venues as $venue): ?>
                    <option value="<?= $venue['venue_id'] ?>" <?= ($editExam['venue_id'] ?? '') == $venue['venue_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($venue['venue_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="exam_date" value="<?= $editExam['exam_date'] ?? '' ?>" required>
            <input type="time" name="exam_time" value="<?= $editExam['exam_time'] ?? '' ?>" required>
            <input type="number" name="duration" placeholder="Duration (minutes)" value="<?= $editExam['duration'] ?? '' ?>" required>
            <button type="submit"><?= $editExam ? 'Update' : 'Add' ?> Exam</button>
            <?php if ($editExam): ?>
                <a href="exams.php" class="btn-cancel">Cancel</a>
            <?php endif; ?>
        </form>
        <table>
            <thead>
                <tr><th>ID</th><th>Module</th><th>Date</th><th>Time</th><th>Duration</th><th>Venue</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($exams as $exam): ?>
                <tr>
                    <td><?= $exam['exam_id'] ?></td>
                    <td><?= htmlspecialchars($exam['module_code'] . ' - ' . $exam['module_name']) ?></td>
                    <td><?= htmlspecialchars($exam['exam_date']) ?></td>
                    <td><?= htmlspecialchars($exam['exam_time']) ?></td>
                    <td><?= $exam['duration'] ?> min</td>
                    <td><?= htmlspecialchars($exam['venue_name'] ?? '-') ?></td>
                    <td>
                        <a href="?edit=<?= $exam['exam_id'] ?>">Edit</a> |
                        <a href="?delete=1&id=<?= $exam['exam_id'] ?>" onclick="return confirm('Are you sure?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

