<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=smart_timetable", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (isset($_GET['delete']) && isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM modules WHERE module_id = ?");
    $stmt->execute([$_GET['id']]);
    header('Location: modules.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['module_id']) && $_POST['module_id']) {
        $stmt = $pdo->prepare("UPDATE modules SET module_code = ?, module_name = ?, credits = ? WHERE module_id = ?");
        $stmt->execute([$_POST['module_code'], $_POST['module_name'], $_POST['credits'], $_POST['module_id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO modules (module_code, module_name, credits) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['module_code'], $_POST['module_name'], $_POST['credits']]);
    }
    header('Location: modules.php');
    exit;
}

$modules = $pdo->query("SELECT * FROM modules ORDER BY module_code")->fetchAll(PDO::FETCH_ASSOC);
$editModule = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM modules WHERE module_id = ?");
    $stmt->execute([$_GET['edit']]);
    $editModule = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Modules</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <a href="index.php" class="back-link">← Back to Dashboard</a>
        <h2>Manage Modules</h2>
        <form method="POST" class="form">
            <input type="hidden" name="module_id" value="<?= $editModule['module_id'] ?? '' ?>">
            <input type="text" name="module_code" placeholder="Module Code" value="<?= htmlspecialchars($editModule['module_code'] ?? '') ?>" required>
            <input type="text" name="module_name" placeholder="Module Name" value="<?= htmlspecialchars($editModule['module_name'] ?? '') ?>" required>
            <input type="number" name="credits" placeholder="Credits" value="<?= $editModule['credits'] ?? '' ?>" required>
            <button type="submit"><?= $editModule ? 'Update' : 'Add' ?> Module</button>
            <?php if ($editModule): ?>
                <a href="modules.php" class="btn-cancel">Cancel</a>
            <?php endif; ?>
        </form>
        <table>
            <thead>
                <tr><th>ID</th><th>Code</th><th>Name</th><th>Credits</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($modules as $module): ?>
                <tr>
                    <td><?= $module['module_id'] ?></td>
                    <td><?= htmlspecialchars($module['module_code']) ?></td>
                    <td><?= htmlspecialchars($module['module_name']) ?></td>
                    <td><?= $module['credits'] ?></td>
                    <td>
                        <a href="?edit=<?= $module['module_id'] ?>">Edit</a> |
                        <a href="?delete=1&id=<?= $module['module_id'] ?>" onclick="return confirm('Are you sure?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

