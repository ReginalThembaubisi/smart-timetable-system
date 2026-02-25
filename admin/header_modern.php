<?php
// Modern unified header/sidebar for admin pages
$current_page = basename($_SERVER['PHP_SELF']);
// Resolve asset base path correctly based on current page URL
// For Railway: files are in /app/admin/admin/, assets are in /app/assets/
// So we need to go up two levels: ../../
$scriptPath = $_SERVER['PHP_SELF'];
$isInAdminSubdir = (strpos($scriptPath, '/admin/admin/') !== false || strpos($scriptPath, '/admin/') !== false);
$baseHref = $isInAdminSubdir ? '../../' : '../';
// Version-bust static assets to prevent stale cache after updates
$themeCssPath = __DIR__ . '/../assets/css/theme.css';
$assetVersion = @filemtime($themeCssPath);
if ($assetVersion === false) {
    $assetVersion = time();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    $pageTitleMap = [
        'index.php' => 'Dashboard',
        'students.php' => 'Students',
        'modules.php' => 'Modules',
        'lecturers.php' => 'Lecturers',
        'venues.php' => 'Venues',
        'programs.php' => 'Programmes',
        'exams.php' => 'Exam Timetables',
        'student_modules.php' => 'Student Modules',
        'clear_data.php' => 'Clear Data',
        'export.php' => 'Export',
        'register.php' => 'Register Admin',
    ];
    $friendlyTitle = $pageTitleMap[$current_page] ?? ucfirst(str_replace(['.php', '_'], ['', ' '], $current_page));
    ?>
    <title><?= htmlspecialchars($friendlyTitle) ?> - Smart Timetable Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link href="<?= $baseHref ?>assets/css/theme.css?v=<?= htmlspecialchars($assetVersion) ?>" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            overflow-x: hidden;
            font-size: 14px;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Premium Sidebar */
        .sidebar {
            width: 280px;
            background: var(--bg-secondary);
            padding: var(--spacing-xl) var(--spacing-lg);
            border-right: 1px solid var(--border-color);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
            backdrop-filter: blur(20px);
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 3px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: var(--border-hover);
        }

        .sidebar-header {
            margin-bottom: var(--spacing-2xl);
            padding-bottom: var(--spacing-lg);
            border-bottom: 1px solid var(--border-color);
        }

        .sidebar-header h1 {
            font-size: 24px;
            font-weight: 800;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: var(--spacing-sm);
            letter-spacing: -0.5px;
        }

        .admin-console {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            color: var(--text-tertiary);
            font-size: 13px;
            margin-bottom: var(--spacing-sm);
            font-weight: 500;
        }

        .admin-console-desc {
            font-size: 12px;
            color: var(--text-muted);
            line-height: 1.6;
        }

        .sidebar-section {
            margin-bottom: var(--spacing-xl);
        }

        .sidebar-section-title {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: rgba(220, 230, 255, 0.6);
            margin-bottom: 16px;
            margin-top: 8px;
            padding: 0 var(--spacing-md);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 14px;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        .sidebar-section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255, 255, 255, 0.12);
            margin-left: 4px;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 12px var(--spacing-md);
            color: rgba(255, 255, 255, 0.6);
            text-decoration: none;
            border-radius: 12px;
            margin-bottom: var(--spacing-xs);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 14px;
            font-weight: 500;
            position: relative;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        .sidebar-nav a::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 0;
            background: var(--accent-primary);
            border-radius: 0 2px 2px 0;
            transition: height 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar-nav a:hover {
            background: rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.9);
            transform: translateX(2px);
        }

        .sidebar-nav a.active {
            background: rgba(255, 255, 255, 0.08);
            color: rgba(255, 255, 255, 0.95);
        }

        .sidebar-nav a.active::before {
            height: 60%;
        }

        .sidebar-nav a i {
            margin-right: var(--spacing-md);
            width: 20px;
            height: 20px;
            font-style: normal;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .icon svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            stroke-width: 1.7;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
            opacity: 0.8;
        }

        .sidebar-nav a.active .icon svg {
            opacity: 1;
        }

        /* Minimalist nav icon (replaces emojis) */
        .nav-icon {
            width: 20px;
            height: 20px;
            border-radius: 6px;
            background: linear-gradient(135deg, var(--accent-primary) 0%, #585CF0 100%);
            opacity: 0.9;
            flex: 0 0 20px;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: var(--spacing-2xl);
            background: var(--bg-primary);
            min-height: 100vh;
        }

        /* Constrain page content width similar to ShadCN demo */
        .ds-main>* {
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        @media (min-width: 1440px) {
            .ds-main>* {
                max-width: 1280px;
            }
        }

        /* Page Header */
        .page-header {
            margin-bottom: var(--spacing-xl);
        }

        .page-header h2 {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: var(--spacing-sm);
            letter-spacing: -1.5px;
            background: linear-gradient(135deg, #ffffff 0%, rgba(255, 255, 255, 0.9) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-header p {
            color: var(--text-tertiary);
            font-size: 15px;
            font-weight: 400;
        }

        /* Breadcrumbs + Actions */
        .page-topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-md);
            flex-wrap: wrap;
        }

        .breadcrumbs {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            font-size: 13px;
        }

        .breadcrumbs a {
            color: var(--text-secondary);
            text-decoration: none;
        }

        .breadcrumbs a:hover {
            color: var(--accent-primary);
            text-decoration: underline;
        }

        .breadcrumbs .sep {
            opacity: 0.4;
        }

        .page-actions {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            flex-wrap: wrap;
        }

        /* Premium Content Cards */
        .content-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-lg);
            /* cleaner modern look - remove heavy blur and glow */
            backdrop-filter: none;
            box-shadow: none;
            transition: border-color .2s, background .2s;
        }

        .content-card:hover {
            background: var(--bg-card-hover);
            border-color: var(--border-hover);
            /* ShadCN-style: subtle, no lift */
            box-shadow: none;
        }

        /* Enhanced Forms */
        .form {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-lg);
            backdrop-filter: none;
            box-shadow: none;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
        }

        .form-group {
            margin-bottom: var(--spacing-lg);
        }

        .form-group label {
            display: block;
            margin-bottom: var(--spacing-sm);
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 600;
        }

        .form input,
        .form select,
        .form textarea {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-size: 14px;
            font-family: inherit;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .form input:focus,
        .form select:focus,
        .form textarea:focus {
            outline: none;
            border-color: var(--accent-primary);
            background: rgba(255, 255, 255, 0.05);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
        }

        .form input::placeholder {
            color: var(--text-muted);
        }

        /* Fix dropdown options visibility across browsers */
        select {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-color: rgba(255, 255, 255, 0.06) !important;
            color: rgba(255, 255, 255, 0.95) !important;
        }

        /* Closed state text color */
        select option,
        body select option {
            background-color: #1e1e2e !important;
            color: rgba(255, 255, 255, 0.95) !important;
        }

        /* Hover/selected states in list */
        body select option:hover,
        body select option:checked,
        body select option:focus {
            background-color: rgba(102, 126, 234, 0.35) !important;
            color: #ffffff !important;
        }

        /* Windows high-contrast fallback */
        @media (forced-colors: active) {

            select,
            select option {
                forced-color-adjust: none;
                background-color: #1e1e2e !important;
                color: #ffffff !important;
            }
        }

        /* Premium Buttons */
        .btn,
        button[type="submit"] {
            padding: 12px 24px;
            background: #6366F1;
            /* solid modern primary */
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--spacing-sm);
            margin-right: var(--spacing-sm);
            font-size: 14px;
            font-weight: 600;
            transition: background .2s ease, opacity .2s ease;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn::before {
            display: none;
        }

        .btn:hover,
        button[type="submit"]:hover {
            background: #585CF0;
        }

        .btn:active {
            opacity: .9;
        }

        .btn-cancel {
            background: rgba(255, 255, 255, 0.06);
            color: var(--text-secondary);
        }

        .btn-cancel:hover {
            background: rgba(255, 255, 255, 0.12);
            color: var(--text-primary);
        }

        .btn-danger {
            background: #ef4444;
        }

        .btn-danger:hover {
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
        }

        .btn-success {
            background: #10b981;
        }

        .btn-success:hover {
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
        }

        /* Premium Tables */
        .table-container {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            margin-bottom: var(--spacing-lg);
            backdrop-filter: blur(10px);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table thead {
            /* ShadCN-style: neutral header */
            background: rgba(255, 255, 255, 0.02);
        }

        table th,
        table td {
            padding: var(--spacing-md);
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        table th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 1px;
            color: var(--text-secondary);
        }

        table td {
            color: var(--text-secondary);
        }

        table tbody tr {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        table tbody tr:hover {
            background: rgba(255, 255, 255, 0.04);
        }

        table tbody tr:nth-child(even) {
            background: rgba(255, 255, 255, 0.02);
        }

        table a {
            color: var(--accent-primary);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
        }

        table a:hover {
            color: var(--accent-hover);
            text-decoration: underline;
        }

        /* Compact table style (ShadCN-like density) + pills */
        .table.compact th,
        .table.compact td {
            padding: 10px 12px;
            font-size: 13px;
        }

        .pill {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            background: rgba(255, 255, 255, 0.02);
        }

        .pill--muted {
            color: var(--text-tertiary);
        }

        .pill--blue {
            background: rgba(52, 152, 219, 0.15);
            border-color: rgba(52, 152, 219, 0.35);
            color: #7dc0e6;
        }

        .pill--yellow {
            background: rgba(241, 196, 15, 0.15);
            border-color: rgba(241, 196, 15, 0.35);
            color: #f3d36b;
        }

        .pill--purple {
            background: rgba(155, 89, 182, 0.15);
            border-color: rgba(155, 89, 182, 0.35);
            color: #caa0da;
        }

        /* Enhanced Search Bar */
        .search-bar {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
            display: flex;
            gap: var(--spacing-md);
            align-items: center;
            flex-wrap: wrap;
            backdrop-filter: blur(10px);
        }

        .search-bar input {
            flex: 1;
            min-width: 300px;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-size: 14px;
            transition: all 0.2s;
        }

        .search-bar input:focus {
            outline: none;
            border-color: var(--accent-primary);
            background: rgba(255, 255, 255, 0.06);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-bar input::placeholder {
            color: var(--text-muted);
        }

        /* Premium Alerts */
        .alert {
            padding: var(--spacing-md) var(--spacing-lg);
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-lg);
            border: 1px solid;
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            animation: slideIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border-color: rgba(16, 185, 129, 0.3);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error);
            border-color: rgba(239, 68, 68, 0.3);
        }

        .alert-info {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info);
            border-color: rgba(59, 130, 246, 0.3);
        }

        /* Actions */
        .actions {
            display: flex;
            gap: var(--spacing-sm);
            flex-wrap: wrap;
        }

        .icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: rgba(255, 255, 255, 0.03);
            color: var(--text-secondary);
            transition: background .2s, border-color .2s, color .2s;
        }

        .icon-btn:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: var(--border-hover);
        }

        .icon-btn svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* Stat Cards */
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: var(--spacing-lg);
            text-align: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(10px);
        }

        .stat-card:hover {
            background: var(--bg-card-hover);
            border-color: var(--accent-primary);
            transform: translateY(-4px);
            box-shadow: var(--shadow-glow);
        }

        .stat-card h3 {
            color: var(--text-muted);
            font-size: 12px;
            margin-bottom: var(--spacing-md);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .stat-card .number {
            font-size: 42px;
            font-weight: 800;
            color: var(--accent-primary);
            /* Solid accent like ShadCN numbers */
        }

        /* Quick Actions layout refinements */
        .qa-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 16px;
        }

        .qa-card {
            display: block;
            text-decoration: none;
            color: inherit;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 16px;
            min-height: 112px;
            transition: border-color .2s, background .2s, box-shadow .2s;
        }

        .qa-card:hover {
            background: rgba(255, 255, 255, 0.03);
            border-color: var(--border-hover);
            box-shadow: none;
            /* no lift */
        }

        .qa-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            background: rgba(99, 102, 241, 0.08);
            border: 1px solid rgba(99, 102, 241, 0.18);
            margin-bottom: 12px;
        }

        .qa-icon svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .qa-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 6px;
        }

        .qa-subtitle {
            font-size: 13px;
            color: var(--text-tertiary);
        }

        /* Utility: subtle badge */
        .badge-muted {
            display: inline-block;
            font-size: 11px;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--text-muted);
            border: 1px solid var(--border-color);
            border-radius: 999px;
            padding: 6px 10px;
        }

        /* Progress */
        .progress {
            width: 100%;
            height: 8px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid var(--border-color);
            border-radius: 999px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.85) 0%, rgba(88, 92, 240, 0.85) 100%);
            box-shadow: 0 0 24px rgba(99, 102, 241, 0.35) inset;
            transition: width .6s ease;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                z-index: 1000;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: var(--spacing-lg);
            }

            .mobile-menu-btn {
                display: block;
                position: fixed;
                top: var(--spacing-lg);
                left: var(--spacing-lg);
                z-index: 1001;
                background: rgba(102, 126, 234, 0.2);
                border: 1px solid var(--border-color);
                color: white;
                padding: 12px;
                border-radius: var(--radius-sm);
                cursor: pointer;
                backdrop-filter: blur(10px);
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .search-bar {
                flex-direction: column;
            }

            .search-bar input {
                width: 100%;
                min-width: auto;
            }

            table {
                font-size: 12px;
            }

            table th,
            table td {
                padding: 10px;
            }
        }

        .mobile-menu-btn {
            display: none;
        }

        /* Loading Overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
        }

        .loading-overlay.active {
            display: flex;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255, 255, 255, 0.1);
            border-top-color: var(--accent-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Confirmation Dialog */
        .confirm-dialog {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            z-index: 10000;
            max-width: 400px;
            width: 90%;
            backdrop-filter: blur(20px);
            box-shadow: var(--shadow-xl);
            animation: fadeIn 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translate(-50%, -48%);
            }

            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }

        .confirm-dialog.active {
            display: block;
        }

        .confirm-dialog h3 {
            margin-bottom: var(--spacing-md);
            color: var(--text-primary);
            font-size: 20px;
            font-weight: 700;
        }

        .confirm-dialog p {
            color: var(--text-secondary);
            margin-bottom: var(--spacing-lg);
            line-height: 1.6;
        }

        .confirm-dialog-actions {
            display: flex;
            gap: var(--spacing-md);
            justify-content: flex-end;
        }
    </style>
</head>

<body>
    <button class="mobile-menu-btn" onclick="toggleSidebar()" aria-label="Open menu">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
            stroke-linejoin="round" style="width:22px;height:22px;display:block;">
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
        </svg>
    </button>

    <div class="container">
        <!-- Premium Sidebar -->
        <div class="sidebar ds-sidebar" id="sidebar">
            <div class="sidebar-header">
                <div
                    style="padding: 10px 18px; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); border-radius: 12px; text-align: center; margin-bottom: 28px; backdrop-filter: blur(10px); box-shadow: 0 2px 8px rgba(0,0,0,0.15);">
                    <span
                        style="color: rgba(255,255,255,0.98); font-size: 13px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; display: inline-block;">SMART
                        TIMETABLE</span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                    <svg viewBox="0 0 24 24" style="width: 24px; height: 24px; color: rgba(255,255,255,0.8);"
                        fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                        <path d="M2 17l10 5 10-5"></path>
                        <path d="M2 12l10 5 10-5"></path>
                    </svg>
                    <h1
                        style="margin: 0; font-size: 24px; font-weight: 700; color: rgba(255,255,255,0.95); letter-spacing: -0.5px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                        Admin Console</h1>
                </div>
                <p class="admin-console-desc"
                    style="color: rgba(220,230,255,0.75); font-size: 14px; line-height: 1.7; font-weight: 400; letter-spacing: -0.02em; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; margin: 0;">
                    Navigate every part of the timetable system through a single modern surface.</p>
            </div>

            <div class="sidebar-section">
                <div class="sidebar-section-title">OVERVIEW</div>
                <nav class="sidebar-nav">
                    <a href="<?= $baseHref ?>admin/index.php"
                        class="<?= $current_page === 'index.php' ? 'active' : '' ?>"><i class="icon">
                            <!-- Grid icon (3x3) -->
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <rect x="3" y="3" width="5" height="5" rx="1"></rect>
                                <rect x="10" y="3" width="5" height="5" rx="1"></rect>
                                <rect x="17" y="3" width="5" height="5" rx="1"></rect>
                                <rect x="3" y="10" width="5" height="5" rx="1"></rect>
                                <rect x="10" y="10" width="5" height="5" rx="1"></rect>
                                <rect x="17" y="10" width="5" height="5" rx="1"></rect>
                                <rect x="3" y="17" width="5" height="5" rx="1"></rect>
                                <rect x="10" y="17" width="5" height="5" rx="1"></rect>
                                <rect x="17" y="17" width="5" height="5" rx="1"></rect>
                            </svg>
                        </i> Dashboard</a>
                </nav>
            </div>

            <div class="sidebar-section">
                <div class="sidebar-section-title">ACADEMIC STRUCTURE</div>
                <nav class="sidebar-nav">
                    <a href="<?= $baseHref ?>admin/programs.php"
                        class="<?= $current_page === 'programs.php' ? 'active' : '' ?>"><i class="icon">
                            <!-- Chain/link icon -->
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                                <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                            </svg>
                        </i> Programmes</a>
                </nav>
            </div>

            <div class="sidebar-section">
                <div class="sidebar-section-title">PEOPLE & RESOURCES</div>
                <nav class="sidebar-nav">
                    <a href="<?= $baseHref ?>admin/students.php"
                        class="<?= $current_page === 'students.php' ? 'active' : '' ?>"><i class="icon">
                            <!-- lucide:users -->
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                        </i> Students</a>
                    <a href="<?= $baseHref ?>admin/lecturers.php"
                        class="<?= $current_page === 'lecturers.php' ? 'active' : '' ?>"><i class="icon">
                            <!-- lucide:users -->
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </i> Lecturers</a>
                    <a href="<?= $baseHref ?>admin/venues.php"
                        class="<?= $current_page === 'venues.php' ? 'active' : '' ?>"><i class="icon">
                            <!-- lucide:map-pin -->
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M20.84 10.61A8 8 0 1 0 3.16 10.6L12 22l8.84-11.39z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                        </i> Venues</a>
                </nav>
            </div>

            <div class="sidebar-section">
                <div class="sidebar-section-title">CURRICULUM & TIMETABLE</div>
                <nav class="sidebar-nav">
                    <a href="<?= $baseHref ?>admin/modules.php"
                        class="<?= $current_page === 'modules.php' ? 'active' : '' ?>"><i class="icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                            </svg>
                        </i> Modules</a>
                    <a href="<?= $baseHref ?>timetable_pdf_parser.php"
                        class="<?= $current_page === 'timetable_pdf_parser.php' ? 'active' : '' ?>"><i class="icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                                <line x1="12" y1="16" x2="12" y2="12"></line>
                                <line x1="10" y1="14" x2="14" y2="14"></line>
                            </svg>
                        </i> Add Session</a>
                    <a href="<?= $baseHref ?>timetable_editor.php"
                        class="<?= $current_page === 'timetable_editor.php' ? 'active' : '' ?>"><i class="icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                        </i> Edit Sessions</a>
                    <a href="<?= $baseHref ?>view_timetable.php"
                        class="<?= $current_page === 'view_timetable.php' ? 'active' : '' ?>"><i class="icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                        </i> View Timetable</a>
                </nav>
            </div>

            <div class="sidebar-section">
                <div class="sidebar-section-title">ASSESSMENTS</div>
                <nav class="sidebar-nav">
                    <a href="<?= $baseHref ?>admin/exams.php"
                        class="<?= $current_page === 'exams.php' ? 'active' : '' ?>"><i class="icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <path d="M14 2v6h6"></path>
                                <circle cx="12" cy="13" r="2"></circle>
                                <path d="M12 13v3"></path>
                            </svg>
                        </i> Exam Timetables</a>
                    <a href="<?= $baseHref ?>admin/student_modules.php"
                        class="<?= $current_page === 'student_modules.php' ? 'active' : '' ?>"><i class="icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <line x1="19" y1="8" x2="19" y2="14"></line>
                                <line x1="22" y1="11" x2="16" y2="11"></line>
                            </svg>
                        </i> Student Modules</a>
                </nav>
            </div>

            <div class="sidebar-section">
                <div class="sidebar-section-title">SYSTEM</div>
                <nav class="sidebar-nav">
                    <a href="clear_data.php" class="<?= $current_page === 'clear_data.php' ? 'active' : '' ?>"
                        style="color: rgba(252,165,165,0.8);"><i class="icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M3 6h18"></path>
                                <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                                <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                                <line x1="10" y1="11" x2="10" y2="17"></line>
                                <line x1="14" y1="11" x2="14" y2="17"></line>
                            </svg>
                        </i> Clear Data</a>
                    <a href="<?= $baseHref ?>admin/logout.php"><i class="icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                <path d="M16 17l5-5-5-5"></path>
                                <path d="M21 12H9"></path>
                            </svg>
                        </i> Logout</a>
                </nav>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content ds-main">
            <?php
            // Build default breadcrumbs if not provided
            if (!isset($breadcrumbs) || !is_array($breadcrumbs) || empty($breadcrumbs)) {
                $pageTitle = ucfirst(str_replace(['.php', '_'], ['', ' '], $current_page));
                $breadcrumbs = [
                    ['label' => 'Dashboard', 'href' => $baseHref . 'admin/index.php'],
                    ['label' => $pageTitle, 'href' => null]
                ];
            }
            // Actions bar: expects $page_actions = [['label'=>'Add','href'=>'#', 'class'=>'btn-success']]
            $page_actions = isset($page_actions) && is_array($page_actions) ? $page_actions : [];
            ?>
            <div class="page-topbar toolbar">
                <nav class="breadcrumbs ds-breadcrumbs">
                    <?php foreach ($breadcrumbs as $i => $bc): ?>
                        <?php if ($i > 0): ?><span class="sep">/</span><?php endif; ?>
                        <?php if (!empty($bc['href'])): ?>
                            <?php $href = preg_match('/^(https?:|#|\\/)/', $bc['href']) ? $bc['href'] : ($baseHref . $bc['href']); ?>
                            <a href="<?= htmlspecialchars($href) ?>"><?= htmlspecialchars($bc['label']) ?></a>
                        <?php else: ?>
                            <span><?= htmlspecialchars($bc['label']) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
                <div class="page-actions">
                    <?php foreach ($page_actions as $act): ?>
                        <?php
                        $href = $act['href'] ?? '#';
                        $href = preg_match('/^(https?:|#|\\/)/', $href) ? $href : ($baseHref . $href);
                        $cls = 'btn ' . htmlspecialchars($act['class'] ?? '');
                        ?>
                        <a href="<?= htmlspecialchars($href) ?>" class="<?= $cls ?>">
                            <?= htmlspecialchars($act['label'] ?? 'Action') ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="page-header" style="
                background: rgba(255, 255, 255, 0.05);
                border: 1px solid rgba(255, 255, 255, 0.10);
                border-radius: 24px;
                padding: 24px 28px;
                margin-bottom: 24px;
                backdrop-filter: blur(14px);
                box-shadow: 0 4px 20px rgba(0,0,0,0.45);
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            ">
                <?php
                $currentSectionLabel = end($breadcrumbs)['label'];
                $subtitleMap = [
                    'Dashboard' => 'Overview and quick actions',
                    'Students' => '',
                    'Modules' => 'Create and maintain course modules',
                    'Lecturers' => 'Manage lecturer profiles and assignments',
                    'Venues' => 'Manage rooms and capacities',
                    'Exam Timetables' => 'Plan and publish exam schedules',
                ];
                $pageSubtitle = $subtitleMap[$currentSectionLabel] ?? 'Operate your timetable with clarity and control';
                ?>
                <h2 class="page-title" style="
                    margin: 0 0 <?= !empty($pageSubtitle) ? '8px' : '0' ?> 0;
                    color: #e8edff;
                    font-size: 28px;
                    font-weight: 800;
                    letter-spacing: -0.5px;
                    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                "><?= htmlspecialchars($currentSectionLabel) ?></h2>
                <?php if (!empty($pageSubtitle)): ?>
                    <p class="page-subtitle" style="
                        margin: 0;
                        color: rgba(220,230,255,0.75);
                        font-size: 14px;
                        line-height: 1.6;
                        font-weight: 400;
                        letter-spacing: -0.01em;
                        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                    "><?= htmlspecialchars($pageSubtitle) ?></p>
                <?php endif; ?>
            </div>