<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/lecturer_system.php';

$pdo = Database::getInstance()->getConnection();
ensureLecturerSystemTables($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $lecturerId = isset($_POST['lecturer_id']) ? (int) $_POST['lecturer_id'] : 0;
        $loginIdentifier = trim((string) ($_POST['login_identifier'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $lecturerCode = trim((string) ($_POST['lecturer_code'] ?? ''));

        if ($lecturerId <= 0 || $loginIdentifier === '' || $password === '') {
            throw new Exception('Lecturer, login identifier, and password are required.');
        }

        $passwordHash = hashPassword($password);

        $codeStmt = $pdo->prepare("UPDATE lecturers SET lecturer_code = ? WHERE lecturer_id = ?");
        $codeStmt->execute([$lecturerCode !== '' ? $lecturerCode : null, $lecturerId]);

        $checkStmt = $pdo->prepare("SELECT auth_id FROM lecturer_auth WHERE lecturer_id = ?");
        $checkStmt->execute([$lecturerId]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $updateStmt = $pdo->prepare("
                UPDATE lecturer_auth
                SET login_identifier = ?, password_hash = ?, is_active = 1
                WHERE lecturer_id = ?
            ");
            $updateStmt->execute([$loginIdentifier, $passwordHash, $lecturerId]);
            $_SESSION['success_message'] = 'Lecturer credentials updated successfully.';
        } else {
            $insertStmt = $pdo->prepare("
                INSERT INTO lecturer_auth (lecturer_id, login_identifier, password_hash, is_active)
                VALUES (?, ?, ?, 1)
            ");
            $insertStmt->execute([$lecturerId, $loginIdentifier, $passwordHash]);
            $_SESSION['success_message'] = 'Lecturer credentials created successfully.';
        }
    } catch (Exception $e) {
        logError($e, 'Saving lecturer credentials');
        $_SESSION['error_message'] = getErrorMessage($e, 'Saving credentials');
    }
    header('Location: lecturer_credentials.php');
    exit;
}

try {
    $lecturers = $pdo->query("
        SELECT
            l.lecturer_id,
            l.lecturer_name,
            l.email,
            l.lecturer_code,
            la.login_identifier,
            la.is_active,
            la.last_login
        FROM lecturers l
        LEFT JOIN lecturer_auth la ON la.lecturer_id = l.lecturer_id
        ORDER BY l.lecturer_name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    logError($e, 'Loading lecturer credentials');
    $lecturers = [];
    if (empty($_SESSION['error_message'])) {
        $_SESSION['error_message'] = getErrorMessage($e, 'Loading lecturer credentials');
    }
}
?>
<?php
$breadcrumbs = [
    ['label' => 'Dashboard', 'href' => 'index.php'],
    ['label' => 'Lecturer Credentials', 'href' => null],
];
$page_actions = [
    ['label' => 'Lecturer Planner', 'href' => 'lecturer_planner.php'],
];
include 'header_modern.php';
?>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success">
        <?= htmlspecialchars($_SESSION['success_message']) ?>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-error">
        <?= htmlspecialchars($_SESSION['error_message']) ?>
    </div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<div class="content-card" style="margin-bottom: 20px;">
    <h3 style="margin-bottom: 16px; color: #e8edff; font-size: 18px; font-weight: 700;">Create / Reset Lecturer Login</h3>
    <form method="POST" class="form" style="margin-bottom: 0;">
        <div class="form-row">
            <div class="form-group">
                <label>Lecturer</label>
                <select name="lecturer_id" required>
                    <option value="">Select lecturer</option>
                    <?php foreach ($lecturers as $lecturer): ?>
                        <option value="<?= (int) $lecturer['lecturer_id'] ?>">
                            <?= htmlspecialchars($lecturer['lecturer_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Login Identifier</label>
                <input type="text" name="login_identifier" placeholder="e.g. lecturer_code or email" required>
            </div>
            <div class="form-group">
                <label>Lecturer Code (optional)</label>
                <input type="text" name="lecturer_code" placeholder="e.g. LEC001">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" minlength="6" required>
            </div>
        </div>
        <button type="submit" class="btn btn-success">Save Credentials</button>
    </form>
</div>

<div class="table-container card">
    <table class="table compact">
        <thead>
            <tr>
                <th>Lecturer</th>
                <th>Code</th>
                <th>Login Identifier</th>
                <th>Status</th>
                <th>Last Login</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($lecturers)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 40px;">No lecturer records found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($lecturers as $lecturer): ?>
                    <tr>
                        <td><?= htmlspecialchars($lecturer['lecturer_name']) ?></td>
                        <td><?= htmlspecialchars($lecturer['lecturer_code'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($lecturer['login_identifier'] ?? '-') ?></td>
                        <td>
                            <?php if ($lecturer['login_identifier']): ?>
                                <span class="pill <?= (int) ($lecturer['is_active'] ?? 0) === 1 ? 'pill--blue' : 'pill--yellow' ?>">
                                    <?= (int) ($lecturer['is_active'] ?? 0) === 1 ? 'Active' : 'Disabled' ?>
                                </span>
                            <?php else: ?>
                                <span class="pill pill--muted">Not configured</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($lecturer['last_login'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'footer_modern.php'; ?>
