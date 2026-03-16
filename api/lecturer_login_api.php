<?php
require_once __DIR__ . '/../includes/api_helpers.php';
require_once __DIR__ . '/../includes/lecturer_system.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(false, null, 'Method not allowed', 405);
}

try {
    $data = getJSONInput();
    validateRequired($data, ['login', 'password']);

    $login = sanitize($data['login']);
    $password = $data['password'];

    $pdo = getDBConnection();
    ensureLecturerSystemTables($pdo);

    $stmt = $pdo->prepare("
        SELECT
            la.auth_id,
            la.lecturer_id,
            la.login_identifier,
            la.password_hash,
            la.is_active,
            l.lecturer_name,
            l.email,
            l.lecturer_code
        FROM lecturer_auth la
        JOIN lecturers l ON l.lecturer_id = la.lecturer_id
        WHERE la.login_identifier = ?
        LIMIT 1
    ");
    $stmt->execute([$login]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || !$row['is_active'] || !verifyPassword($password, $row['password_hash'])) {
        sendJSONResponse(false, null, 'Invalid login or password', 401);
    }

    if (strlen($row['password_hash']) < 60) {
        $newHash = hashPassword($password);
        $updateStmt = $pdo->prepare("UPDATE lecturer_auth SET password_hash = ? WHERE auth_id = ?");
        $updateStmt->execute([$newHash, $row['auth_id']]);
    }

    $lastLoginStmt = $pdo->prepare("UPDATE lecturer_auth SET last_login = NOW() WHERE auth_id = ?");
    $lastLoginStmt->execute([$row['auth_id']]);

    sendJSONResponse(true, [
        'lecturer' => [
            'lecturer_id' => (int) $row['lecturer_id'],
            'lecturer_name' => $row['lecturer_name'],
            'email' => $row['email'],
            'lecturer_code' => $row['lecturer_code'],
            'login_identifier' => $row['login_identifier'],
        ]
    ], 'Lecturer login successful');
} catch (Exception $e) {
    handleAPIError($e, 'Lecturer login failed');
}
?>
