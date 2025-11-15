<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=smart_timetable", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (isset($_GET['delete']) && isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM venues WHERE venue_id = ?");
    $stmt->execute([$_GET['id']]);
    header('Location: venues.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['venue_id']) && $_POST['venue_id']) {
        $stmt = $pdo->prepare("UPDATE venues SET venue_name = ?, capacity = ? WHERE venue_id = ?");
        $stmt->execute([$_POST['venue_name'], $_POST['capacity'] ?: null, $_POST['venue_id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO venues (venue_name, capacity) VALUES (?, ?)");
        $stmt->execute([$_POST['venue_name'], $_POST['capacity'] ?: null]);
    }
    header('Location: venues.php');
    exit;
}

$venues = $pdo->query("SELECT * FROM venues ORDER BY venue_name")->fetchAll(PDO::FETCH_ASSOC);
$editVenue = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM venues WHERE venue_id = ?");
    $stmt->execute([$_GET['edit']]);
    $editVenue = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Venues</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <a href="index.php" class="back-link">← Back to Dashboard</a>
        <h2>Manage Venues</h2>
        <form method="POST" class="form">
            <input type="hidden" name="venue_id" value="<?= $editVenue['venue_id'] ?? '' ?>">
            <input type="text" name="venue_name" placeholder="Venue Name" value="<?= htmlspecialchars($editVenue['venue_name'] ?? '') ?>" required>
            <input type="number" name="capacity" placeholder="Capacity" value="<?= $editVenue['capacity'] ?? '' ?>">
            <button type="submit"><?= $editVenue ? 'Update' : 'Add' ?> Venue</button>
            <?php if ($editVenue): ?>
                <a href="venues.php" class="btn-cancel">Cancel</a>
            <?php endif; ?>
        </form>
        <table>
            <thead>
                <tr><th>ID</th><th>Venue Name</th><th>Capacity</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($venues as $venue): ?>
                <tr>
                    <td><?= $venue['venue_id'] ?></td>
                    <td><?= htmlspecialchars($venue['venue_name']) ?></td>
                    <td><?= $venue['capacity'] ?? '-' ?></td>
                    <td>
                        <a href="?edit=<?= $venue['venue_id'] ?>">Edit</a> |
                        <a href="?delete=1&id=<?= $venue['venue_id'] ?>" onclick="return confirm('Are you sure?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

