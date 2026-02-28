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

// Simple authentication
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/crud_helpers.php';

// Ensure logError function exists (fallback if include failed)
if (!function_exists('logError')) {
    function logError($e, $context = 'Error', $additionalData = [])
    {
        error_log("{$context}: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    }
}

// Get statistics
try {
    $pdo = Database::getInstance()->getConnection();

    $stats = [
        'students' => $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn(),
        'modules' => $pdo->query("SELECT COUNT(*) FROM modules")->fetchColumn(),
        'sessions' => $pdo->query("SELECT COUNT(*) FROM sessions")->fetchColumn(),
        'exams' => $pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn(),
        'lecturers' => $pdo->query("SELECT COUNT(*) FROM lecturers")->fetchColumn(),
        'venues' => $pdo->query("SELECT COUNT(*) FROM venues")->fetchColumn(),
    ];

    // Get important additional stats
    try {
        $stats['programs'] = $pdo->query("SELECT COUNT(*) FROM programs")->fetchColumn();
    } catch (Exception $e) {
        $stats['programs'] = 0;
    }

    try {
        $stats['enrollments'] = $pdo->query("SELECT COUNT(*) FROM student_modules")->fetchColumn();
    } catch (Exception $e) {
        $stats['enrollments'] = 0;
    }

    // Get students without enrollments
    try {
        $stats['students_without_modules'] = $pdo->query("
            SELECT COUNT(*) FROM students s 
            LEFT JOIN student_modules sm ON s.student_id = sm.student_id 
            WHERE sm.id IS NULL
        ")->fetchColumn();
    } catch (Exception $e) {
        $stats['students_without_modules'] = 0;
    }

    // Get modules without sessions
    try {
        $stats['modules_without_sessions'] = $pdo->query("
            SELECT COUNT(*) FROM modules m 
            LEFT JOIN sessions s ON m.module_id = s.module_id 
            WHERE s.session_id IS NULL
        ")->fetchColumn();
    } catch (Exception $e) {
        $stats['modules_without_sessions'] = 0;
    }

    // Get upcoming exams (next 7 days)
    try {
        $stats['upcoming_exams'] = $pdo->query("
            SELECT COUNT(*) FROM exams 
            WHERE exam_date >= CURDATE() 
            AND exam_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ")->fetchColumn();
    } catch (Exception $e) {
        $stats['upcoming_exams'] = 0;
    }

    // Get sessions without lecturers or venues
    try {
        $stats['sessions_incomplete'] = $pdo->query("
            SELECT COUNT(*) FROM sessions 
            WHERE lecturer_id IS NULL OR venue_id IS NULL
        ")->fetchColumn();
    } catch (Exception $e) {
        $stats['sessions_incomplete'] = 0;
    }

    // Calculate System Setup Progress (0-100%)
    // Based on: students, modules, lecturers, venues, programs, enrollments, sessions, complete sessions
    $setupProgress = 0;
    $totalSessions = $stats['sessions'];
    $completeSessions = $totalSessions - $stats['sessions_incomplete'];

    // Weighted calculation:
    if ($stats['students'] > 0)
        $setupProgress += 10; // Students exist
    if ($stats['modules'] > 0)
        $setupProgress += 15; // Modules exist
    if ($stats['lecturers'] > 0)
        $setupProgress += 15; // Lecturers exist
    if ($stats['venues'] > 0)
        $setupProgress += 15; // Venues exist
    if (isset($stats['programs']) && $stats['programs'] > 0)
        $setupProgress += 10; // Programs exist
    if (isset($stats['enrollments']) && $stats['enrollments'] > 0)
        $setupProgress += 15; // Students enrolled
    if ($totalSessions > 0)
        $setupProgress += 10; // Sessions exist
    if ($totalSessions > 0 && $completeSessions > 0) {
        // Percentage of complete sessions (max 10%)
        $completionRate = ($completeSessions / $totalSessions) * 10;
        $setupProgress += $completionRate;
    }

    // Cap at 100%
    $stats['setup_progress'] = min(100, round($setupProgress));

} catch (Exception $e) {
    // Log error if function exists, otherwise just use error_log
    if (function_exists('logError')) {
        logError($e, 'Loading dashboard statistics');
    } else {
        error_log('Loading dashboard statistics: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    }
    $stats = [
        'students' => 0,
        'modules' => 0,
        'sessions' => 0,
        'exams' => 0,
        'lecturers' => 0,
        'venues' => 0,
        'programs' => 0,
        'enrollments' => 0,
        'students_without_modules' => 0,
        'modules_without_sessions' => 0,
        'upcoming_exams' => 0,
        'sessions_incomplete' => 0,
        'setup_progress' => 0
    ];
}

// Determine greeting based on time of day
$currentHour = (int) date('H');
if ($currentHour >= 5 && $currentHour < 12) {
    $greeting = 'Good Morning';
    $greetingEmoji = '☀️';
} elseif ($currentHour >= 12 && $currentHour < 17) {
    $greeting = 'Good Afternoon';
    $greetingEmoji = '🌤';
} elseif ($currentHour >= 17 && $currentHour < 21) {
    $greeting = 'Good Evening';
    $greetingEmoji = '🌇';
} else {
    $greeting = 'Good Night';
    $greetingEmoji = '🌙';
}

// Override breadcrumb/title to avoid showing 'Index'
$breadcrumbs = [
    ['label' => 'Dashboard', 'href' => null]
];
?>
<?php include 'header_modern.php'; ?>

<!-- Hero panel -->
<div class="content-card" style="margin-bottom: 24px;">
    <div style="display:flex; flex-wrap: wrap; gap: 24px; align-items: center; justify-content: space-between;">
        <div style="min-width: 280px; flex: 1;">
            <div style="display:flex; align-items:center; gap:12px; margin-bottom: 12px;">
                <span
                    style="padding: 6px 12px; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); border-radius: 8px; color: rgba(220,230,255,0.8); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">LIVE
                    STATUS</span>
            </div>
            <h2 class="page-title" style="margin:0 0 8px 0; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 32px;"><?= $greetingEmoji ?></span>
                <span><span id="greeting-text"><?= htmlspecialchars($greeting) ?></span>, Administrator</span>
            </h2>
            <p
                style="margin: 0 0 20px 0; color: rgba(220,230,255,0.75); font-size: 15px; line-height: 1.7; font-weight: 400; letter-spacing: -0.01em; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; max-width: 600px;">
                Keep your academic year synchronized—manage modules, sessions and assessments with confidence.</p>
            <div style="display:flex; flex-wrap: wrap; gap:8px; margin-top:14px;">
                <div
                    style="display: flex; align-items: center; gap: 6px; border:1px solid var(--border-color); border-radius: 999px; padding:8px 12px; color: var(--text-tertiary); font-size:12px;">
                    <svg viewBox="0 0 24 24" style="width: 14px; height: 14px; color: rgba(255,255,255,0.6);"
                        fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <span id="local-date"><?= date('l, F d, Y') ?></span>
                </div>
                <div
                    style="display: flex; align-items: center; gap: 6px; border:1px solid var(--border-color); border-radius: 999px; padding:8px 12px; color: var(--text-tertiary); font-size:12px;">
                    <svg viewBox="0 0 24 24" style="width: 14px; height: 14px; color: rgba(255,255,255,0.6);"
                        fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    <span id="local-time">--:-- --</span>
                </div>
                <div id="system-status-chip"
                    style="display:flex; align-items:center; gap:8px; border:1px solid rgba(16,185,129,0.35); border-radius: 999px; padding:8px 12px; color: var(--text-tertiary); font-size:12px;">
                    <svg viewBox="0 0 24 24" style="width: 14px; height: 14px; color: #10b981;" fill="none"
                        stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span id="system-status-text">System All Good</span>
                </div>
            </div>
        </div>
        <div class="hero-stats-grid" style="display:flex; flex-wrap:wrap; gap:12px;">
            <?php
            $heroCards = [
                ['label' => 'STUDENTS', 'value' => $stats['students'], 'desc' => 'Registered this semester', 'r' => '99,102,241', 'href' => 'students.php'],
                ['label' => 'MODULES', 'value' => $stats['modules'], 'desc' => 'Active course units', 'r' => '236,72,153', 'href' => 'modules.php'],
                ['label' => 'SESSIONS', 'value' => $stats['sessions'], 'desc' => 'Scheduled teaching slots', 'r' => '59,130,246', 'href' => '../timetable_editor.php'],
                ['label' => 'EXAMS', 'value' => $stats['exams'], 'desc' => 'Exam entries uploaded', 'r' => '234,179,8', 'href' => 'exams.php'],
                ['label' => 'LECTURERS', 'value' => $stats['lecturers'], 'desc' => 'Teaching staff', 'r' => '16,185,129', 'href' => 'lecturers.php'],
                ['label' => 'VENUES', 'value' => $stats['venues'], 'desc' => 'Rooms & lecture halls', 'r' => '168,85,247', 'href' => 'venues.php'],
            ];
            foreach ($heroCards as $c): ?>
                <a href="<?= $c['href'] ?>"
                    style="position:relative;overflow:hidden;background:linear-gradient(135deg,rgba(<?= $c['r'] ?>,0.15)0%,rgba(<?= $c['r'] ?>,0.06)100%);border:1px solid rgba(<?= $c['r'] ?>,0.25);border-radius:16px;padding:18px 20px;min-width:140px;flex:1;backdrop-filter:blur(14px);box-shadow:0 4px 16px rgba(0,0,0,0.2);transition:all 0.3s cubic-bezier(0.4,0,0.2,1);text-decoration:none;"
                    onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 8px 24px rgba(<?= $c['r'] ?>,0.25)'"
                    onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 4px 16px rgba(0,0,0,0.2)'">
                    <div
                        style="color:rgba(255,255,255,0.75);font-size:11px;text-transform:uppercase;letter-spacing:0.12em;margin-bottom:6px;font-weight:700;">
                        <?= $c['label'] ?>
                    </div>
                    <div
                        style="font-size:32px;font-weight:800;color:rgba(255,255,255,0.95);line-height:1;margin-bottom:4px;letter-spacing:-1px;">
                        <?= number_format($c['value']) ?>
                    </div>
                    <div style="color:rgba(220,230,255,0.65);font-size:12px;line-height:1.4;font-weight:400;">
                        <?= $c['desc'] ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>



<div style="padding: 0; background: transparent; border: none; box-shadow: none;">
    <h3 class="page-subtitle"
        style="margin-bottom: 8px; font-size: 22px; font-weight: 700; color: rgba(255,255,255,0.95); letter-spacing: -0.5px;">
        Quick Actions</h3>
    <p style="color: rgba(255,255,255,0.6); font-size: 14px; margin-bottom: 32px;">Accelerate your daily tasks</p>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px;">
        <!-- Add Student - Premium Neo-Glass Style -->
        <a href="students.php" style="
                        display: flex;
                        align-items: center;
                        gap: 14px;
                        background: rgba(255, 255, 255, 0.05);
                        border: 1px solid rgba(255, 255, 255, 0.10);
                        border-radius: 24px;
                        padding: 22px;
                        text-decoration: none;
                        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                        cursor: pointer;
                        backdrop-filter: blur(14px);
                        box-shadow: 0 4px 20px rgba(0,0,0,0.45);
                        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                    "
            onmouseover="this.style.background='rgba(255, 255, 255, 0.08)'; this.style.borderColor='rgba(255, 255, 255, 0.18)'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 24px rgba(0,0,0,0.5)'"
            onmouseout="this.style.background='rgba(255, 255, 255, 0.05)'; this.style.borderColor='rgba(255, 255, 255, 0.10)'; this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 20px rgba(0,0,0,0.45)'">
            <div
                style="width: 48px; height: 48px; background: rgba(255,255,255,0.06); border-radius: 15px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid rgba(255,255,255,0.12);">
                <svg viewBox="0 0 24 24" style="width: 22px; height: 22px; color: #dce3ff;" fill="none"
                    stroke="currentColor" stroke-width="1.7">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
            </div>
            <div style="flex: 1; min-width: 0;">
                <div
                    style="color: #e8edff; font-size: 17px; font-weight: 600; margin-bottom: 4px; letter-spacing: -0.2px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                    Add Student</div>
                <div
                    style="color: rgba(220,230,255,0.65); font-size: 13px; line-height: 1.4; font-weight: 400; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                    Register new students</div>
            </div>
        </a>

        <!-- Upload Timetable - Premium Neo-Glass Style -->
        <a href="../timetable_pdf_parser.php" style="
                        display: flex;
                        align-items: center;
                        gap: 14px;
                        background: rgba(255, 255, 255, 0.05);
                        border: 1px solid rgba(255, 255, 255, 0.10);
                        border-radius: 24px;
                        padding: 22px;
                        text-decoration: none;
                        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                        cursor: pointer;
                        backdrop-filter: blur(14px);
                        box-shadow: 0 4px 20px rgba(0,0,0,0.45);
                        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                    "
            onmouseover="this.style.background='rgba(255, 255, 255, 0.08)'; this.style.borderColor='rgba(255, 255, 255, 0.18)'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 24px rgba(0,0,0,0.5)'"
            onmouseout="this.style.background='rgba(255, 255, 255, 0.05)'; this.style.borderColor='rgba(255, 255, 255, 0.10)'; this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 20px rgba(0,0,0,0.45)'">
            <div
                style="width: 48px; height: 48px; background: rgba(255,255,255,0.06); border-radius: 15px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid rgba(255,255,255,0.12);">
                <svg viewBox="0 0 24 24" style="width: 22px; height: 22px; color: #dce3ff;" fill="none"
                    stroke="currentColor" stroke-width="1.7">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="17 8 12 3 7 8"></polyline>
                    <line x1="12" y1="3" x2="12" y2="15"></line>
                </svg>
            </div>
            <div style="flex: 1; min-width: 0;">
                <div
                    style="color: #e8edff; font-size: 17px; font-weight: 600; margin-bottom: 4px; letter-spacing: -0.2px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                    Upload Timetable</div>
                <div
                    style="color: rgba(220,230,255,0.65); font-size: 13px; line-height: 1.4; font-weight: 400; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                    Universal parser (PDF & TXT)</div>
            </div>
        </a>

        <!-- View Timetable - Premium Neo-Glass Style -->
        <a href="../view_timetable.php" style="
                        display: flex;
                        align-items: center;
                        gap: 14px;
                        background: rgba(255, 255, 255, 0.05);
                        border: 1px solid rgba(255, 255, 255, 0.10);
                        border-radius: 24px;
                        padding: 22px;
                        text-decoration: none;
                        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                        cursor: pointer;
                        backdrop-filter: blur(14px);
                        box-shadow: 0 4px 20px rgba(0,0,0,0.45);
                        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                    "
            onmouseover="this.style.background='rgba(255, 255, 255, 0.08)'; this.style.borderColor='rgba(255, 255, 255, 0.18)'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 24px rgba(0,0,0,0.5)'"
            onmouseout="this.style.background='rgba(255, 255, 255, 0.05)'; this.style.borderColor='rgba(255, 255, 255, 0.10)'; this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 20px rgba(0,0,0,0.45)'">
            <div
                style="width: 48px; height: 48px; background: rgba(255,255,255,0.06); border-radius: 15px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid rgba(255,255,255,0.12);">
                <svg viewBox="0 0 24 24" style="width: 22px; height: 22px; color: #dce3ff;" fill="none"
                    stroke="currentColor" stroke-width="1.7">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
            </div>
            <div style="flex: 1; min-width: 0;">
                <div
                    style="color: #e8edff; font-size: 17px; font-weight: 600; margin-bottom: 4px; letter-spacing: -0.2px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                    View Timetable</div>
                <div
                    style="color: rgba(220,230,255,0.65); font-size: 13px; line-height: 1.4; font-weight: 400; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                    See full schedule</div>
            </div>
        </a>

        <!-- Edit Timetable - Premium Neo-Glass Style -->
        <a href="../timetable_editor.php" style="
                        display: flex;
                        align-items: center;
                        gap: 14px;
                        background: rgba(255, 255, 255, 0.05);
                        border: 1px solid rgba(255, 255, 255, 0.10);
                        border-radius: 24px;
                        padding: 22px;
                        text-decoration: none;
                        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                        cursor: pointer;
                        backdrop-filter: blur(14px);
                        box-shadow: 0 4px 20px rgba(0,0,0,0.45);
                        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                    "
            onmouseover="this.style.background='rgba(255, 255, 255, 0.08)'; this.style.borderColor='rgba(255, 255, 255, 0.18)'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 24px rgba(0,0,0,0.5)'"
            onmouseout="this.style.background='rgba(255, 255, 255, 0.05)'; this.style.borderColor='rgba(255, 255, 255, 0.10)'; this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 20px rgba(0,0,0,0.45)'">
            <div
                style="width: 48px; height: 48px; background: rgba(255,255,255,0.06); border-radius: 15px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid rgba(255,255,255,0.12);">
                <svg viewBox="0 0 24 24" style="width: 22px; height: 22px; color: #dce3ff;" fill="none"
                    stroke="currentColor" stroke-width="1.7">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
            </div>
            <div style="flex: 1; min-width: 0;">
                <div
                    style="color: #e8edff; font-size: 17px; font-weight: 600; margin-bottom: 4px; letter-spacing: -0.2px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                    Edit Timetable</div>
                <div
                    style="color: rgba(220,230,255,0.65); font-size: 13px; line-height: 1.4; font-weight: 400; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                    Edit uploaded sessions</div>
            </div>
        </a>

        <!-- Add Module - Premium Neo-Glass Style -->
        <a href="modules.php" style="
                        display: flex;
                        align-items: center;
                        gap: 14px;
                        background: rgba(255, 255, 255, 0.05);
                        border: 1px solid rgba(255, 255, 255, 0.10);
                        border-radius: 24px;
                        padding: 22px;
                        text-decoration: none;
                        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                        cursor: pointer;
                        backdrop-filter: blur(14px);
                        box-shadow: 0 4px 20px rgba(0,0,0,0.45);
                        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                    "
            onmouseover="this.style.background='rgba(255, 255, 255, 0.08)'; this.style.borderColor='rgba(255, 255, 255, 0.18)'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 24px rgba(0,0,0,0.5)'"
            onmouseout="this.style.background='rgba(255, 255, 255, 0.05)'; this.style.borderColor='rgba(255, 255, 255, 0.10)'; this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 20px rgba(0,0,0,0.45)'">
            <div
                style="width: 48px; height: 48px; background: rgba(255,255,255,0.06); border-radius: 15px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid rgba(255,255,255,0.12);">
                <svg viewBox="0 0 24 24" style="width: 22px; height: 22px; color: #dce3ff;" fill="none"
                    stroke="currentColor" stroke-width="1.7">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <path d="M14 2v6h6"></path>
                    <path d="M12 18v-6"></path>
                    <path d="M9 15h6"></path>
                </svg>
            </div>
            <div style="flex: 1; min-width: 0;">
                <div
                    style="color: #e8edff; font-size: 17px; font-weight: 600; margin-bottom: 4px; letter-spacing: -0.2px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                    Add Module</div>
                <div
                    style="color: rgba(220,230,255,0.65); font-size: 13px; line-height: 1.4; font-weight: 400; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                    Create new modules</div>
            </div>
        </a>

        <!-- Manage Lecturers - Premium Neo-Glass Style -->
        <a href="lecturers.php" style="
                        display: flex;
                        align-items: center;
                        gap: 14px;
                        background: rgba(255, 255, 255, 0.05);
                        border: 1px solid rgba(255, 255, 255, 0.10);
                        border-radius: 24px;
                        padding: 22px;
                        text-decoration: none;
                        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                        cursor: pointer;
                        backdrop-filter: blur(14px);
                        box-shadow: 0 4px 20px rgba(0,0,0,0.45);
                        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                    "
            onmouseover="this.style.background='rgba(255, 255, 255, 0.08)'; this.style.borderColor='rgba(255, 255, 255, 0.18)'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 24px rgba(0,0,0,0.5)'"
            onmouseout="this.style.background='rgba(255, 255, 255, 0.05)'; this.style.borderColor='rgba(255, 255, 255, 0.10)'; this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 20px rgba(0,0,0,0.45)'">
            <div
                style="width: 48px; height: 48px; background: rgba(255,255,255,0.06); border-radius: 15px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid rgba(255,255,255,0.12);">
                <svg viewBox="0 0 24 24" style="width: 22px; height: 22px; color: #dce3ff;" fill="none"
                    stroke="currentColor" stroke-width="1.7">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
            </div>
            <div style="flex: 1; min-width: 0;">
                <div
                    style="color: #e8edff; font-size: 17px; font-weight: 600; margin-bottom: 4px; letter-spacing: -0.2px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                    Manage Lecturers</div>
                <div
                    style="color: rgba(220,230,255,0.65); font-size: 13px; line-height: 1.4; font-weight: 400; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                    Add or edit lecturers</div>
            </div>
        </a>

        <!-- Manage Venues - Premium Neo-Glass Style -->
        <a href="venues.php" style="
                        display: flex;
                        align-items: center;
                        gap: 14px;
                        background: rgba(255, 255, 255, 0.05);
                        border: 1px solid rgba(255, 255, 255, 0.10);
                        border-radius: 24px;
                        padding: 22px;
                        text-decoration: none;
                        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                        cursor: pointer;
                        backdrop-filter: blur(14px);
                        box-shadow: 0 4px 20px rgba(0,0,0,0.45);
                        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                    "
            onmouseover="this.style.background='rgba(255, 255, 255, 0.08)'; this.style.borderColor='rgba(255, 255, 255, 0.18)'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 24px rgba(0,0,0,0.5)'"
            onmouseout="this.style.background='rgba(255, 255, 255, 0.05)'; this.style.borderColor='rgba(255, 255, 255, 0.10)'; this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 20px rgba(0,0,0,0.45)'">
            <div
                style="width: 48px; height: 48px; background: rgba(255,255,255,0.06); border-radius: 15px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid rgba(255,255,255,0.12);">
                <svg viewBox="0 0 24 24" style="width: 22px; height: 22px; color: #dce3ff;" fill="none"
                    stroke="currentColor" stroke-width="1.7">
                    <path d="M20.84 10.61A8 8 0 1 0 3.16 10.6L12 22l8.84-11.39z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                </svg>
            </div>
            <div style="flex: 1; min-width: 0;">
                <div
                    style="color: #e8edff; font-size: 17px; font-weight: 600; margin-bottom: 4px; letter-spacing: -0.2px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                    Manage Venues</div>
                <div
                    style="color: rgba(220,230,255,0.65); font-size: 13px; line-height: 1.4; font-weight: 400; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                    Add or edit venues</div>
            </div>
        </a>

        <!-- System Check - Premium Neo-Glass Style -->
        <a href="health_check.php" style="
                        display: flex;
                        align-items: center;
                        gap: 14px;
                        background: rgba(255, 255, 255, 0.05);
                        border: 1px solid rgba(255, 255, 255, 0.10);
                        border-radius: 24px;
                        padding: 22px;
                        text-decoration: none;
                        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                        cursor: pointer;
                        backdrop-filter: blur(14px);
                        box-shadow: 0 4px 20px rgba(0,0,0,0.45);
                        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                    "
            onmouseover="this.style.background='rgba(255, 255, 255, 0.08)'; this.style.borderColor='rgba(255, 255, 255, 0.18)'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 24px rgba(0,0,0,0.5)'"
            onmouseout="this.style.background='rgba(255, 255, 255, 0.05)'; this.style.borderColor='rgba(255, 255, 255, 0.10)'; this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 20px rgba(0,0,0,0.45)'">
            <div
                style="width: 48px; height: 48px; background: rgba(255,255,255,0.06); border-radius: 15px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid rgba(255,255,255,0.12);">
                <svg viewBox="0 0 24 24" style="width: 22px; height: 22px; color: #dce3ff;" fill="none"
                    stroke="currentColor" stroke-width="1.7">
                    <path
                        d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z">
                    </path>
                </svg>
            </div>
            <div style="flex: 1; min-width: 0;">
                <div
                    style="color: #e8edff; font-size: 17px; font-weight: 600; margin-bottom: 4px; letter-spacing: -0.2px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                    System Check</div>
                <div
                    style="color: rgba(220,230,255,0.65); font-size: 13px; line-height: 1.4; font-weight: 400; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                    View system health</div>
            </div>
        </a>

        <!-- Exam Timetables - Premium Neo-Glass Style -->
        <a href="exams.php" style="
                        display: flex;
                        align-items: center;
                        gap: 14px;
                        background: rgba(255, 255, 255, 0.05);
                        border: 1px solid rgba(255, 255, 255, 0.10);
                        border-radius: 24px;
                        padding: 22px;
                        text-decoration: none;
                        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                        cursor: pointer;
                        backdrop-filter: blur(14px);
                        box-shadow: 0 4px 20px rgba(0,0,0,0.45);
                        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                    "
            onmouseover="this.style.background='rgba(255, 255, 255, 0.08)'; this.style.borderColor='rgba(255, 255, 255, 0.18)'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 24px rgba(0,0,0,0.5)'"
            onmouseout="this.style.background='rgba(255, 255, 255, 0.05)'; this.style.borderColor='rgba(255, 255, 255, 0.10)'; this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 20px rgba(0,0,0,0.45)'">
            <div
                style="width: 48px; height: 48px; background: rgba(255,255,255,0.06); border-radius: 15px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid rgba(255,255,255,0.12);">
                <svg viewBox="0 0 24 24" style="width: 22px; height: 22px; color: #dce3ff;" fill="none"
                    stroke="currentColor" stroke-width="1.7">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <path d="M14 2v6h6"></path>
                    <circle cx="12" cy="13" r="2"></circle>
                    <path d="M12 13v3"></path>
                </svg>
            </div>
            <div style="flex: 1; min-width: 0;">
                <div
                    style="color: #e8edff; font-size: 17px; font-weight: 600; margin-bottom: 4px; letter-spacing: -0.2px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                    Exam Timetables</div>
                <div
                    style="color: rgba(220,230,255,0.65); font-size: 13px; line-height: 1.4; font-weight: 400; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                    Upload & manage exam schedules</div>
            </div>
        </a>

        <!-- Clear Data - Danger styled -->
        <a href="clear_data.php" style="
                        display: flex;
                        align-items: center;
                        gap: 14px;
                        background: rgba(239,68,68,0.06);
                        border: 1px solid rgba(239,68,68,0.3);
                        border-radius: 24px;
                        padding: 22px;
                        text-decoration: none;
                        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                        cursor: pointer;
                        backdrop-filter: blur(14px);
                        box-shadow: 0 4px 20px rgba(0,0,0,0.45);
                        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                    "
            onmouseover="this.style.background='rgba(239,68,68,0.12)'; this.style.borderColor='rgba(239,68,68,0.5)'; this.style.transform='translateY(-2px)'"
            onmouseout="this.style.background='rgba(239,68,68,0.06)'; this.style.borderColor='rgba(239,68,68,0.3)'; this.style.transform='translateY(0)'">
            <div
                style="width: 48px; height: 48px; background: rgba(239,68,68,0.12); border-radius: 15px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid rgba(239,68,68,0.3);">
                <svg viewBox="0 0 24 24" style="width: 22px; height: 22px; color: #fca5a5;" fill="none"
                    stroke="currentColor" stroke-width="1.7">
                    <path d="M3 6h18"></path>
                    <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                    <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                    <line x1="10" y1="11" x2="10" y2="17"></line>
                    <line x1="14" y1="11" x2="14" y2="17"></line>
                </svg>
            </div>
            <div style="flex: 1; min-width: 0;">
                <div
                    style="color: #fca5a5; font-size: 17px; font-weight: 600; margin-bottom: 4px; letter-spacing: -0.2px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                    Clear Data</div>
                <div
                    style="color: rgba(252,165,165,0.7); font-size: 13px; line-height: 1.4; font-weight: 400; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                    ⚠ Remove all system data</div>
            </div>
        </a>
    </div>
</div>

<!-- Bottom utility cards -->
<div style="padding: 0; background: transparent; border: none; box-shadow: none; margin-top: 40px;">
    <div class="utility-grid"
        style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 40px; align-items: start;">
        <!-- Upload Timetable File - Premium Neo-Glass Style -->
        <div style="
                        background: rgba(255, 255, 255, 0.05);
                        border: 1px solid rgba(255, 255, 255, 0.10);
                        border-radius: 24px;
                        padding: 22px;
                        backdrop-filter: blur(14px);
                        box-shadow: 0 4px 20px rgba(0,0,0,0.45);
                        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                    ">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                <div style="display: flex; align-items: center; gap: 14px;">
                    <div
                        style="width: 48px; height: 48px; background: rgba(255,255,255,0.06); border-radius: 15px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid rgba(255,255,255,0.12);">
                        <svg viewBox="0 0 24 24" style="width: 22px; height: 22px; color: #dce3ff;" fill="none"
                            stroke="currentColor" stroke-width="1.7">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <path d="M14 2v6h6"></path>
                            <path d="M12 12v6"></path>
                            <path d="m9 15 3-3 3 3"></path>
                        </svg>
                    </div>
                    <div
                        style="color: #e8edff; font-size: 17px; font-weight: 600; letter-spacing: -0.2px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                        Upload Timetable File</div>
                </div>
                <span
                    style="padding: 6px 12px; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); border-radius: 8px; color: rgba(220,230,255,0.8); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">AUTOMATED
                    IMPORT</span>
            </div>
            <p
                style="color: rgba(220,230,255,0.65); font-size: 13px; line-height: 1.6; margin-bottom: 20px; font-weight: 400; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                Upload a PDF or TXT export and our universal parser will populate every session automatically.</p>
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                <a href="../timetable_pdf_parser.php" style="
                                padding: 10px 20px;
                                background: rgba(255,255,255,0.08);
                                border: 1px solid rgba(255,255,255,0.15);
                                border-radius: 10px;
                                color: #e8edff;
                                font-size: 14px;
                                font-weight: 600;
                                text-decoration: none;
                                display: inline-flex;
                                align-items: center;
                                gap: 8px;
                                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                                cursor: pointer;
                                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                            "
                    onmouseover="this.style.background='rgba(255,255,255,0.12)'; this.style.borderColor='rgba(255,255,255,0.22)'; this.style.transform='translateY(-2px)'"
                    onmouseout="this.style.background='rgba(255,255,255,0.08)'; this.style.borderColor='rgba(255,255,255,0.15)'; this.style.transform='translateY(0)'">
                    <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; color: #dce3ff;" fill="none"
                        stroke="currentColor" stroke-width="1.7">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="17 8 12 3 7 8"></polyline>
                        <line x1="12" y1="3" x2="12" y2="15"></line>
                    </svg>
                    Upload File
                </a>
                <a href="export.php" style="
                                padding: 10px 20px;
                                background: rgba(255,255,255,0.05);
                                border: 1px solid rgba(255,255,255,0.12);
                                border-radius: 10px;
                                color: rgba(220,230,255,0.8);
                                font-size: 14px;
                                font-weight: 600;
                                text-decoration: none;
                                display: inline-flex;
                                align-items: center;
                                gap: 8px;
                                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                                cursor: pointer;
                                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                            "
                    onmouseover="this.style.background='rgba(255,255,255,0.08)'; this.style.borderColor='rgba(255,255,255,0.18)'"
                    onmouseout="this.style.background='rgba(255,255,255,0.05)'; this.style.borderColor='rgba(255,255,255,0.12)'">
                    <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; color: #dce3ff;" fill="none"
                        stroke="currentColor" stroke-width="1.7">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    Export Data
                </a>
            </div>
        </div>

        <!-- System Setup Progress - Premium Neo-Glass Style -->
        <div style="
                        background: rgba(255, 255, 255, 0.05);
                        border: 1px solid rgba(255, 255, 255, 0.10);
                        border-radius: 24px;
                        padding: 22px;
                        backdrop-filter: blur(14px);
                        box-shadow: 0 4px 20px rgba(0,0,0,0.45);
                        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                    ">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                <div style="display: flex; align-items: center; gap: 14px;">
                    <div
                        style="width: 48px; height: 48px; background: rgba(255,255,255,0.06); border-radius: 15px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid rgba(255,255,255,0.12);">
                        <svg viewBox="0 0 24 24" style="width: 22px; height: 22px; color: #dce3ff;" fill="none"
                            stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 8a4 4 0 1 0 4 4 4 4 0 0 0-4-4Z"></path>
                            <path
                                d="M2 12a10 10 0 0 0 .29 2.41l2.1.33a2 2 0 0 1 1.51 1.16l.01.02a2 2 0 0 1-.22 2.09l-1.3 1.81A10 10 0 0 0 12 22a10 10 0 0 0 2.41-.29l.33-2.1a2 2 0 0 1 1.16-1.51h.02a2 2 0 0 1 2.09.22l1.81 1.3A10 10 0 0 0 22 12a10 10 0 0 0-.29-2.41l-2.1-.33a2 2 0 0 1-1.51-1.16v-.02a2 2 0 0 1 .22-2.09l1.3-1.81A10 10 0 0 0 12 2a10 10 0 0 0-2.41.29l-.33 2.1a2 2 0 0 1-1.16 1.51h-.02a2 2 0 0 1-2.09-.22l-1.81-1.3A10 10 0 0 0 2 12Z">
                            </path>
                        </svg>
                    </div>
                    <div
                        style="color: #e8edff; font-size: 17px; font-weight: 600; letter-spacing: -0.2px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                        System Setup Progress</div>
                </div>
                <div
                    style="color: rgba(220,230,255,0.65); font-size: 13px; font-weight: 400; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                    <span id="setupProgressLabel"
                        style="color: #e8edff; font-size: 15px; font-weight: 600;"><?= isset($stats['setup_progress']) ? $stats['setup_progress'] : 0 ?>%</span>
                    complete
                </div>
            </div>
            <div style="
                            width: 100%;
                            height: 10px;
                            background: rgba(255,255,255,0.06);
                            border-radius: 10px;
                            overflow: hidden;
                            position: relative;
                            border: 1px solid rgba(255,255,255,0.08);
                        ">
                <div id="setupProgressBar" style="
                                width: <?= isset($stats['setup_progress']) ? $stats['setup_progress'] : 0 ?>%;
                                height: 100%;
                                background: linear-gradient(90deg, #6366F1 0%, #585CF0 100%);
                                border-radius: 10px;
                                transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);
                                position: relative;
                                overflow: hidden;
                            ">
                    <div style="
                                    position: absolute;
                                    top: 0;
                                    left: 0;
                                    right: 0;
                                    bottom: 0;
                                    background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.2) 50%, transparent 100%);
                                    animation: shimmer 2s infinite;
                                "></div>
                </div>
            </div>
            <style>
                @keyframes shimmer {
                    0% {
                        transform: translateX(-100%);
                    }

                    100% {
                        transform: translateX(100%);
                    }
                }
            </style>
        </div>
    </div>
</div>
<?php include 'footer_modern.php'; ?>