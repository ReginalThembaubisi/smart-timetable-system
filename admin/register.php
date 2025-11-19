<?php
// Optional admin self-registration page (disabled by default for security)
// To enable, set ALLOW_ADMIN_SELF_REGISTER=true in the environment or update a config constant.
if (session_status() === PHP_SESSION_NONE) {
	$secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
	session_set_cookie_params([
		'lifetime' => 0,
		'path' => '/',
		'domain' => '',
		'secure' => $secure,
		'httponly' => true,
		'samesite' => 'Lax',
	]);
}
session_start();

$allowed = false;
// Read from environment first
if (getenv('ALLOW_ADMIN_SELF_REGISTER') === 'true') {
	$allowed = true;
}
// Or from config constant if present
if (!$allowed) {
	@require_once __DIR__ . '/config.php';
	if (defined('ALLOW_ADMIN_SELF_REGISTER') && ALLOW_ADMIN_SELF_REGISTER === true) {
		$allowed = true;
	}
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!$allowed) {
		$error = 'Self-registration is disabled. Please contact a super admin.';
	} else {
		$username = trim($_POST['username'] ?? '');
		$password = $_POST['password'] ?? '';
		$confirm = $_POST['confirm'] ?? '';
		if ($username === '' || $password === '') {
			$error = 'Username and password are required.';
		} elseif ($password !== $confirm) {
			$error = 'Passwords do not match.';
		} else {
			try {
				require_once __DIR__ . '/config.php';
				require_once __DIR__ . '/../includes/database.php';
				$pdo = Database::getInstance()->getConnection();
				$pdo->exec("CREATE TABLE IF NOT EXISTS admins (
					id INT AUTO_INCREMENT PRIMARY KEY,
					username VARCHAR(255) UNIQUE NOT NULL,
					password_hash VARCHAR(255) NOT NULL,
					created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
				$stmt = $pdo->prepare("INSERT INTO admins (username, password_hash) VALUES (:u, :p)");
				$stmt->execute([
					':u' => $username,
					':p' => password_hash($password, PASSWORD_DEFAULT)
				]);
				$success = 'Admin account created. You can now sign in.';
			} catch (Throwable $e) {
				$error = 'Could not create account: ' . htmlspecialchars($e->getMessage());
			}
		}
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Register Admin - Smart Timetable</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<script src="https://cdn.tailwindcss.com"></script>
	<script>
		tailwind.config = {
			theme: {
				extend: { fontFamily: { sans: ['Inter','ui-sans-serif','system-ui','-apple-system','Segoe UI','Roboto','Arial'] } }
			}
		};
	</script>
	<style>html,body{background:#0B0B0F}</style>
	<?php /* Keep style minimal; Tailwind utilities drive the UI */ ?>
</head>
<body class="min-h-screen flex items-center justify-center font-sans antialiased">
	<div class="w-full max-w-md mx-auto px-4">
		<div class="bg-[#111115] border border-[#1F1F25] rounded-2xl shadow-[0_6px_28px_rgba(0,0,0,0.35)] p-8">
			<div class="text-center mb-6">
				<h1 class="text-[#E4E4E7] text-[22px] font-semibold tracking-tight">Create Admin Account</h1>
				<p class="text-[#A1A1AA] text-[13px] mt-1">Use a strong password. This page may be disabled for security.</p>
			</div>
			<?php if ($error): ?>
				<div class="mb-5 rounded-xl border border-[#1F1F25] bg-[#141419] text-[#E4E4E7] px-4 py-3 text-[13px]">
					<?= $error ?>
				</div>
			<?php endif; ?>
			<?php if ($success): ?>
				<div class="mb-5 rounded-xl border border-[#1F1F25] bg-[#0B0B0F] text-[#E4E4E7] px-4 py-3 text-[13px]">
					<?= $success ?>
				</div>
			<?php endif; ?>
			<form method="POST" class="space-y-4">
				<div>
					<label class="block text-[#A1A1AA] text-[13px] font-medium mb-2">Username</label>
					<input name="username" class="w-full rounded-xl border border-[#1F1F25] bg-[#0B0B0F] px-4 py-3 text-[15px] text-[#E4E4E7] placeholder-[#A1A1AA]/70 focus:outline-none focus:ring-2 focus:ring-[rgba(99,102,241,0.25)]" required>
				</div>
				<div>
					<label class="block text-[#A1A1AA] text-[13px] font-medium mb-2">Password</label>
					<input type="password" name="password" class="w-full rounded-xl border border-[#1F1F25] bg-[#0B0B0F] px-4 py-3 text-[15px] text-[#E4E4E7] placeholder-[#A1A1AA]/70 focus:outline-none focus:ring-2 focus:ring-[rgba(99,102,241,0.25)]" required>
				</div>
				<div>
					<label class="block text-[#A1A1AA] text-[13px] font-medium mb-2">Confirm Password</label>
					<input type="password" name="confirm" class="w-full rounded-xl border border-[#1F1F25] bg-[#0B0B0F] px-4 py-3 text-[15px] text-[#E4E4E7] placeholder-[#A1A1AA]/70 focus:outline-none focus:ring-2 focus:ring-[rgba(99,102,241,0.25)]" required>
				</div>
				<button type="submit" class="w-full rounded-xl px-4 py-3 bg-[#6366F1] text-white hover:bg-[#585CF0] transition-all text-[15px] font-medium">
					Create Account
				</button>
			</form>
			<div class="mt-6 text-center">
				<a href="login.php" class="text-[#A1A1AA] text-[13px] hover:text-white transition-colors">Back to login</a>
			</div>
		</div>
	</div>
</body>
</html>


