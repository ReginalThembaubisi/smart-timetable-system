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
    
    // Default admin credentials (change these!)
    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid username or password';
    }
}

// Optional: lightweight stats for context on the welcome panel
$stats_modules = 0;
$stats_sessions = 0;
try {
	require_once __DIR__ . '/config.php';
	require_once __DIR__ . '/../includes/database.php';
	$pdo = Database::getInstance()->getConnection();
	$stats_modules = (int)$pdo->query("SELECT COUNT(*) FROM modules")->fetchColumn();
	$stats_sessions = (int)$pdo->query("SELECT COUNT(*) FROM sessions")->fetchColumn();
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
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<script src="https://cdn.tailwindcss.com"></script>
	<script>
		// Tailwind config: set Inter as the sans font
		tailwind.config = {
			theme: {
				extend: {
					fontFamily: {
						sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'Arial']
					}
				}
			}
		};
	</script>
</head>
<body class="bg-[#0B0B0F] min-h-screen flex items-center justify-center font-sans antialiased">
	<div class="w-full max-w-4xl mx-auto px-4">
		<div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
			<!-- Welcome panel -->
			<div class="p-2 md:p-3">
				<div class="mb-4 flex items-center gap-3">
					<div class="h-10 w-10 rounded-xl bg-gradient-to-br from-[#6366F1] to-[#585CF0] ring-1 ring-white/10 shadow-[0_6px_20px_rgba(88,92,240,0.35)]"></div>
					<span class="text-[#A1A1AA] text-xs tracking-wide uppercase">Smart Timetable</span>
				</div>
				<h2 class="text-[#E4E4E7] text-[26px] font-semibold tracking-tight leading-tight">Design. Control. Clarity.</h2>
				<p class="text-[#A1A1AA] text-[13px] mt-2 max-w-md">Welcome, Administrator. Sign in to orchestrate schedules with precision and consistency.</p>
				<div class="mt-5 flex flex-wrap items-center gap-3">
					<div class="rounded-full border border-[#1F1F25] bg-[#0B0B0F] text-[#A1A1AA] px-4 py-2 text-[13px]">
						<strong class="text-[#E4E4E7] mr-1"><?= htmlspecialchars((string)$stats_modules) ?></strong> Modules
					</div>
					<div class="rounded-full border border-[#1F1F25] bg-[#0B0B0F] text-[#A1A1AA] px-4 py-2 text-[13px]">
						<strong class="text-[#E4E4E7] mr-1"><?= htmlspecialchars((string)$stats_sessions) ?></strong> Sessions
					</div>
				</div>
				<div class="mt-6 grid gap-2">
					<div class="text-[#A1A1AA] text-[13px]">• Secure access to analytics, data, and operational tools.</div>
					<div class="text-[#A1A1AA] text-[13px]">• Manage students, modules, sessions, and exams seamlessly.</div>
				</div>
			</div>
			<!-- Login card -->
			<div class="bg-[#111115] border border-[#1F1F25] rounded-2xl shadow-[0_6px_28px_rgba(0,0,0,0.35)] p-8">
				<div class="text-center mb-7">
					<h1 class="text-[#E4E4E7] text-[22px] leading-tight font-semibold tracking-tight">Welcome back</h1>
					<p class="text-[#A1A1AA] text-[13px] mt-1">Sign in to Smart Timetable</p>
				</div>
				<?php if ($error): ?>
					<div class="mb-5 rounded-xl border border-[#1F1F25] bg-[#141419] text-[#E4E4E7] px-4 py-3 text-[13px]">
						<?= htmlspecialchars($error) ?>
					</div>
				<?php endif; ?>
				<form method="POST" id="loginForm" class="space-y-4">
					<div>
						<label class="block text-[#A1A1AA] text-[13px] font-medium mb-2">Username</label>
						<input class="w-full rounded-xl border border-[#1F1F25] bg-[#0B0B0F] px-4 py-3 text-[15px] text-[#E4E4E7] placeholder-[#A1A1AA]/70 focus:outline-none focus:ring-2 focus:ring-[rgba(99,102,241,0.25)]"
							type="text" name="username" placeholder="Enter your username" required autofocus>
					</div>
					<div>
						<label class="block text-[#A1A1AA] text-[13px] font-medium mb-2">Password</label>
						<input class="w-full rounded-xl border border-[#1F1F25] bg-[#0B0B0F] px-4 py-3 text-[15px] text-[#E4E4E7] placeholder-[#A1A1AA]/70 focus:outline-none focus:ring-2 focus:ring-[rgba(99,102,241,0.25)]"
							type="password" name="password" placeholder="Enter your password" required>
					</div>
					<button type="submit"
						class="w-full rounded-xl px-4 py-3 bg-[#6366F1] text-white hover:bg-[#585CF0] transition-all text-[15px] font-medium">
						Login
					</button>
				</form>
				<div class="mt-4 text-center">
					<a href="register.php" class="text-[#A1A1AA] text-[13px] hover:text-white transition-colors">Need an admin account? Request access</a>
				</div>
			</div>
		</div>
	</div>
    <script>
		document.getElementById('loginForm').addEventListener('submit', function() {
			const btn = this.querySelector('button[type="submit"]');
			btn.textContent = 'Logging in...';
			btn.disabled = true;
			btn.classList.add('opacity-70','cursor-not-allowed');
		});
    </script>
</body>
</html>


