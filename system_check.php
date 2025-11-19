<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin/login.php');
    exit;
}

require_once 'admin/config.php';
require_once __DIR__ . '/includes/database.php';

$checks = [];

// DB connection
try {
    $pdo = Database::getInstance()->getConnection();
    $checks[] = ['label' => 'Database connection', 'ok' => true, 'detail' => 'Connected'];
} catch (Throwable $e) {
    $checks[] = ['label' => 'Database connection', 'ok' => false, 'detail' => $e->getMessage()];
}

function countTable($pdo, $table) {
    try {
        return (int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    } catch (Throwable $e) {
        return null;
    }
}

$tables = ['students','modules','sessions','lecturers','venues','student_modules','exams'];
foreach ($tables as $t) {
    $cnt = isset($pdo) ? countTable($pdo, $t) : null;
    $checks[] = [
        'label' => "Table: {$t}",
        'ok' => $cnt !== null,
        'detail' => $cnt !== null ? "{$cnt} rows" : 'Missing or inaccessible'
    ];
}

// Required PHP extensions
$requiredExt = ['pdo', 'pdo_mysql', 'json', 'mbstring'];
foreach ($requiredExt as $ext) {
    $checks[] = [
        'label' => "PHP extension: {$ext}",
        'ok' => extension_loaded($ext),
        'detail' => extension_loaded($ext) ? 'Loaded' : 'Not loaded'
    ];
}

// File permissions (writability) for temp/cache dirs if they exist
$paths = [
    'uploads' => __DIR__ . '/uploads',
    'tmp' => sys_get_temp_dir(),
];
foreach ($paths as $name => $path) {
    if (is_dir($path)) {
        $checks[] = [
            'label' => "Dir writable: {$name}",
            'ok' => is_writable($path),
            'detail' => is_writable($path) ? 'Writable' : 'Not writable'
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Check - Smart Timetable</title>
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #0a0a0a; color: #fff; margin: 0; }
        .wrap { max-width: 1100px; margin: 40px auto; padding: 0 20px; }
        .header { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 24px; }
        .title { font-size: 28px; font-weight: 700; }
        .subtitle { color: rgba(255,255,255,0.6); font-size: 14px; }
        .card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 14px; border-bottom: 1px solid rgba(255,255,255,0.06); text-align: left; }
        th { font-size: 12px; color: rgba(255,255,255,0.6); text-transform: uppercase; letter-spacing: 1px; }
        .ok { color: #10b981; font-weight: 600; }
        .fail { color: #ef4444; font-weight: 600; }
        .actions { margin-top: 16px; display: flex; gap: 10px; }
        .btn { padding: 10px 16px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.15); color: #fff; background: rgba(255,255,255,0.05); text-decoration: none; font-weight: 600; }
        .btn:hover { background: rgba(255,255,255,0.1); }
    </style>
    <script>
        function refresh() { window.location.reload(); }
    </script>
</head>
<body>
    <div class="wrap">
        <div class="header">
            <div>
                <div class="title">System Check</div>
                <div class="subtitle">Quick diagnostics for database, tables, PHP extensions, and permissions</div>
            </div>
            <div class="actions">
                <a class="btn" href="admin/index.php">← Back to Dashboard</a>
                <button class="btn" onclick="refresh()">↻ Refresh</button>
            </div>
        </div>
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Check</th>
                        <th>Status</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($checks as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['label']) ?></td>
                        <td class="<?= $c['ok'] ? 'ok' : 'fail' ?>"><?= $c['ok'] ? 'OK' : 'Issue' ?></td>
                        <td><?= htmlspecialchars($c['detail']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>




