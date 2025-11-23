<?php
// Load environment variables (.env) if present
require_once dirname(__DIR__) . '/includes/env.php';

// Database configuration (fallback to sane defaults for XAMPP)
define('DB_HOST', getenv('DB_HOST') !== false ? getenv('DB_HOST') : 'localhost');
define('DB_NAME', getenv('DB_NAME') !== false ? getenv('DB_NAME') : 'smart_timetable');
define('DB_USER', getenv('DB_USER') !== false ? getenv('DB_USER') : 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

// API Configuration
// In production, set this to specific allowed origins (comma-separated)
// Example: API_ALLOWED_ORIGINS=https://yourdomain.com,https://app.yourdomain.com in .env
if (!defined('API_ALLOWED_ORIGINS')) {
	$origins = getenv('API_ALLOWED_ORIGINS');
	if ($origins !== false) {
		define('API_ALLOWED_ORIGINS', $origins);
	}
}
