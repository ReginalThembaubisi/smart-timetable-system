<?php
// Harden session cookie before starting session
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

if (isset($_SESSION['admin_logged_in'])) {
	header('Location: index.php');
	exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$username = $_POST['username'] ?? '';
	$password = $_POST['password'] ?? '';

	try {
		require_once __DIR__ . '/config.php';
		require_once __DIR__ . '/../includes/database.php';
		$pdo = Database::getInstance()->getConnection();

		// Ensure admins table exists (for first-time setup)
		$pdo->exec("CREATE TABLE IF NOT EXISTS admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            email VARCHAR(255),
            full_name VARCHAR(255),
            is_active TINYINT(1) DEFAULT 1,
            last_login TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		// Check database for admin credentials
		$stmt = $pdo->prepare("SELECT id, username, password_hash, is_active, full_name, email 
                               FROM admins 
                               WHERE username = ? AND is_active = 1");
		$stmt->execute([$username]);
		$admin = $stmt->fetch();

		if ($admin && password_verify($password, $admin['password_hash'])) {
			// Valid credentials - update last login
			$updateStmt = $pdo->prepare("UPDATE admins SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
			$updateStmt->execute([$admin['id']]);

			// Set session variables
			$_SESSION['admin_logged_in'] = true;
			$_SESSION['admin_username'] = $admin['username'];
			$_SESSION['admin_id'] = $admin['id'];
			$_SESSION['admin_full_name'] = $admin['full_name'] ?? $admin['username'];

			header('Location: index.php');
			exit;
		} else {
			$error = 'Invalid username or password';
		}
	} catch (Throwable $e) {
		error_log("Admin login error: " . $e->getMessage());
		$error = 'Login failed. Please try again later.';
	}
}

// Optional: lightweight stats for context on the welcome panel
$stats_modules = 0;
$stats_sessions = 0;
try {
	require_once __DIR__ . '/config.php';
	require_once __DIR__ . '/../includes/database.php';
	$pdo = Database::getInstance()->getConnection();
	$stats_modules = (int) $pdo->query("SELECT COUNT(*) FROM modules")->fetchColumn();
	$stats_sessions = (int) $pdo->query("SELECT COUNT(*) FROM sessions")->fetchColumn();
} catch (Throwable $e) {
	// Silently ignore; login should not fail if DB is not reachable here
	error_log("Login page stats error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Admin Login - Smart Timetable</title>
	<link rel="stylesheet" href="../assets/css/theme.css">
	<style>
		body {
			background: var(--bg-primary);
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
		}

		.login-wrap {
			width: 100%;
			max-width: 900px;
			margin: 0 auto;
			padding: 0 16px;
		}

		.login-grid {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 24px;
			align-items: start;
		}

		.login-card {
			background: var(--bg-secondary);
			border: 1px solid var(--border-color);
			border-radius: 24px;
			box-shadow: 0 6px 28px rgba(0, 0, 0, 0.35);
			padding: 32px;
		}

		.login-card h1 {
			color: var(--text-primary);
			font-size: 22px;
			font-weight: 600;
			text-align: center;
			margin: 0 0 4px 0;
		}

		.login-card .subtitle {
			color: var(--text-muted);
			font-size: 13px;
			text-align: center;
			margin: 0 0 24px 0;
		}

		.login-label {
			display: block;
			color: var(--text-muted);
			font-size: 13px;
			font-weight: 500;
			margin-bottom: 8px;
		}

		.login-input {
			width: 100%;
			padding: 12px 16px;
			background: var(--bg-primary);
			border: 1px solid var(--border-color);
			border-radius: 12px;
			color: var(--text-primary);
			font-size: 15px;
			font-family: 'Inter', sans-serif;
			box-sizing: border-box;
			transition: border-color 0.2s;
		}

		.login-input:focus {
			outline: none;
			border-color: rgba(99, 102, 241, 0.5);
			box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
		}

		.login-input::placeholder {
			color: rgba(161, 161, 170, 0.7);
		}

		.login-btn {
			width: 100%;
			padding: 13px 20px;
			background: linear-gradient(135deg, #6366F1 0%, #585CF0 100%);
			border: none;
			border-radius: 12px;
			color: #fff;
			font-size: 15px;
			font-weight: 600;
			font-family: 'Inter', sans-serif;
			cursor: pointer;
			transition: all 0.2s;
		}

		.login-btn:hover {
			background: linear-gradient(135deg, #7C83FF 0%, #6366F1 100%);
			transform: translateY(-1px);
		}

		.login-btn:disabled {
			opacity: 0.65;
			cursor: not-allowed;
		}

		.stat-pill {
			border-radius: 999px;
			border: 1px solid var(--border-color);
			background: var(--bg-primary);
			color: var(--text-muted);
			padding: 8px 16px;
			font-size: 13px;
			display: inline-block;
		}

		.stat-pill strong {
			color: var(--text-primary);
			margin-right: 4px;
		}

		.login-link {
			color: var(--text-muted);
			font-size: 13px;
			text-decoration: none;
			transition: color 0.2s;
		}

		.login-link:hover {
			color: var(--text-primary);
		}

		@media (max-width: 640px) {
			.login-grid {
				grid-template-columns: 1fr;
			}
		}
	</style>
</head>

<body>
	<div class="login-wrap">
		<div class="login-grid">
			<!-- Welcome panel -->
			<div style="padding: 8px;">
				<div style="margin-bottom: 16px; display: flex; align-items: center; gap: 12px;">
					<div
						style="height: 40px; width: 40px; border-radius: 12px; background: linear-gradient(135deg, #6366F1, #585CF0); border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 6px 20px rgba(88,92,240,0.35);">
					</div>
					<span
						style="color: var(--text-muted); font-size: 12px; letter-spacing: 0.1em; text-transform: uppercase;">Smart
						Timetable</span>
				</div>
				<h2
					style="color: var(--text-primary); font-size: 26px; font-weight: 600; letter-spacing: -0.5px; line-height: 1.3; margin: 0 0 8px 0;">
					Design. Control. Clarity.</h2>
				<p style="color: var(--text-muted); font-size: 13px; margin: 0 0 20px 0; max-width: 360px;">Welcome,
					Administrator. Sign in to orchestrate schedules with precision and consistency.</p>
				<div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 24px;">
					<span class="stat-pill"><strong><?= htmlspecialchars((string) $stats_modules) ?></strong>
						Modules</span>
					<span class="stat-pill"><strong><?= htmlspecialchars((string) $stats_sessions) ?></strong>
						Sessions</span>
				</div>
				<div style="display: grid; gap: 8px;">
					<div style="color: var(--text-muted); font-size: 13px;">• Secure access to analytics, data, and
						operational tools.</div>
					<div style="color: var(--text-muted); font-size: 13px;">• Manage students, modules, sessions, and
						exams seamlessly.</div>
				</div>
			</div>
			<!-- Login card -->
			<div class="login-card">
				<h1>Welcome back</h1>
				<p class="subtitle">Sign in to Smart Timetable</p>
				<?php if ($error): ?>
					<div class="alert alert-error" style="margin-bottom: 20px;">
						<?= htmlspecialchars($error) ?>
					</div>
				<?php endif; ?>
				<form method="POST" id="loginForm" style="display: grid; gap: 16px;">
					<div>
						<label class="login-label">Username</label>
						<input class="login-input" type="text" name="username" placeholder="Enter your username"
							required autofocus>
					</div>
					<div>
						<label class="login-label">Password</label>
						<input class="login-input" type="password" name="password" placeholder="Enter your password"
							required>
					</div>
					<button type="submit" id="loginBtn" class="login-btn">Login</button>
				</form>
				<div style="margin-top: 16px; text-align: center;">
					<a href="register.php" class="login-link">Need an admin account? Request access</a>
				</div>
			</div>
		</div>
	</div>
	<script>
		document.getElementById('loginForm').addEventListener('submit', function () {
			const btn = document.getElementById('loginBtn');
			btn.textContent = 'Logging in...';
			btn.disabled = true;
			btn.style.opacity = '0.7';
		});
	</script>
</body>

</html>