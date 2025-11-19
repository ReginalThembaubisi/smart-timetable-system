<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin/login.php');
    exit;
}

require_once 'admin/config.php';
require_once __DIR__ . '/includes/database.php';

$pdo = Database::getInstance()->getConnection();

// Ensure programme, year_level, and semester columns exist
try {
    $columns = $pdo->query("SHOW COLUMNS FROM sessions LIKE 'programme'")->fetch();
    if (!$columns) {
        $pdo->exec("ALTER TABLE sessions ADD COLUMN programme VARCHAR(255) NULL AFTER session_id");
        $pdo->exec("ALTER TABLE sessions ADD COLUMN year_level VARCHAR(50) NULL AFTER programme");
        $pdo->exec("ALTER TABLE sessions ADD COLUMN semester VARCHAR(50) NULL AFTER year_level");
        $pdo->exec("ALTER TABLE sessions ADD INDEX idx_programme (programme)");
        $pdo->exec("ALTER TABLE sessions ADD INDEX idx_year_level (year_level)");
        $pdo->exec("ALTER TABLE sessions ADD INDEX idx_semester (semester)");
    }
} catch (PDOException $e) {
    // Columns might already exist, ignore error
}

// Get filter values
$programmeFilter = $_GET['programme'] ?? '';
$yearFilter = $_GET['year'] ?? '';
$semesterFilter = $_GET['semester'] ?? '';
$dayFilter = $_GET['day'] ?? '';

// Build query
$query = "
    SELECT s.*, m.module_code, m.module_name, l.lecturer_name, v.venue_name
    FROM sessions s
    LEFT JOIN modules m ON s.module_id = m.module_id
    LEFT JOIN lecturers l ON s.lecturer_id = l.lecturer_id
    LEFT JOIN venues v ON s.venue_id = v.venue_id
    WHERE 1=1
";

$params = [];

if ($programmeFilter) {
    $query .= " AND s.programme = ?";
    $params[] = $programmeFilter;
}

if ($yearFilter) {
    $query .= " AND s.year_level = ?";
    $params[] = $yearFilter;
}

if ($semesterFilter) {
    $query .= " AND s.semester = ?";
    $params[] = $semesterFilter;
}

if ($dayFilter) {
    $query .= " AND s.day_of_week = ?";
    $params[] = $dayFilter;
}

$query .= " ORDER BY 
    CASE s.day_of_week
        WHEN 'Monday' THEN 1
        WHEN 'Tuesday' THEN 2
        WHEN 'Wednesday' THEN 3
        WHEN 'Thursday' THEN 4
        WHEN 'Friday' THEN 5
        WHEN 'Saturday' THEN 6
        WHEN 'Sunday' THEN 7
        ELSE 8
    END,
    s.start_time";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique values for filters
// Get programmes (even if year_level or semester are missing)
$programmes = $pdo->query("SELECT DISTINCT programme FROM sessions WHERE programme IS NOT NULL AND programme != '' ORDER BY programme")->fetchAll(PDO::FETCH_COLUMN);
$days = $pdo->query("SELECT DISTINCT day_of_week FROM sessions ORDER BY day_of_week")->fetchAll(PDO::FETCH_COLUMN);

// Get all programme-year-semester combinations for dynamic filtering
// More flexible: get combinations even if some fields are missing
$programmeYearSemester = $pdo->query("
    SELECT DISTINCT programme, year_level, semester 
    FROM sessions 
    WHERE programme IS NOT NULL AND programme != ''
    ORDER BY programme, year_level, semester
")->fetchAll(PDO::FETCH_ASSOC);

// Build data structure for JavaScript - more flexible
$filterData = [];
foreach ($programmeYearSemester as $row) {
    $prog = trim($row['programme'] ?? '');
    $year = trim($row['year_level'] ?? '');
    $sem = trim($row['semester'] ?? '');
    
    // Skip if programme is empty
    if (empty($prog)) continue;
    
    // Initialize programme if not exists
    if (!isset($filterData[$prog])) {
        $filterData[$prog] = [];
    }
    
    // If year is provided, add it
    if (!empty($year)) {
        if (!isset($filterData[$prog][$year])) {
            $filterData[$prog][$year] = [];
        }
        
        // If semester is provided, add it
        if (!empty($sem) && !in_array($sem, $filterData[$prog][$year])) {
            $filterData[$prog][$year][] = $sem;
        }
    }
}

// Debug: Check if we have any filter data
if (empty($filterData)) {
    // Try to see what data we actually have
    $debugQuery = $pdo->query("SELECT programme, year_level, semester, COUNT(*) as cnt FROM sessions GROUP BY programme, year_level, semester LIMIT 10");
    $debugData = $debugQuery->fetchAll(PDO::FETCH_ASSOC);
}

// Group sessions by day
$sessionsByDay = [];
foreach ($sessions as $session) {
    $day = $session['day_of_week'] ?? 'Unknown';
    if (!isset($sessionsByDay[$day])) {
        $sessionsByDay[$day] = [];
    }
    $sessionsByDay[$day][] = $session;
}

$totalSessions = count($sessions);
?>
<?php
// Use the shared modern admin shell
$breadcrumbs = [
    ['label' => 'Dashboard', 'href' => 'admin/index.php'],
    ['label' => 'View Timetable', 'href' => null],
];
$page_actions = [];
include 'admin/header_modern.php';
?>
            <!-- Local styles specific to this page -->
            <style>
        /* Filters */
        .filters {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 16px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        .filter-group label {
            font-size: 12px;
            color: rgba(220,230,255,0.8);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
            font-weight: 600;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        .filter-group select,
        .filter-group input {
            padding: 10px 16px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 10px;
            color: #e8edff;
            font-size: 14px;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: rgba(59, 130, 246, 0.5);
            background: rgba(255,255,255,0.08);
        }
        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: flex-end;
            justify-content: flex-end;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        .btn-primary {
            background: rgba(59, 130, 246, 0.25);
            color: #dce3ff;
            border: 1px solid rgba(59, 130, 246, 0.4);
            font-weight: 600;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        .btn-primary:hover {
            background: rgba(59, 130, 246, 0.35);
            border-color: rgba(59, 130, 246, 0.5);
        }
        .btn-secondary {
            background: rgba(255,255,255,0.05);
            color: rgba(220,230,255,0.8);
            border: 1px solid rgba(255,255,255,0.12);
            font-weight: 600;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        .btn-secondary:hover {
            background: rgba(255,255,255,0.08);
            border-color: rgba(255,255,255,0.18);
        }
        
        /* Summary */
        .summary {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.10);
            border-radius: 20px;
            padding: 20px 24px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            backdrop-filter: blur(14px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        .summary-text {
            font-size: 14px;
            color: rgba(220,230,255,0.75);
        }
        .summary-count {
            font-size: 24px;
            font-weight: 700;
            color: #dce3ff;
        }
        /* Day chips */
        .day-chips { display:flex; gap:8px; flex-wrap:wrap; margin-bottom: 16px; }
        .chip {
            display:inline-flex; align-items:center; gap:8px;
            border:1px solid rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.05);
            color: rgba(220,230,255,0.75);
            font-size:12px; padding:8px 14px; border-radius:12px;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            backdrop-filter: blur(10px);
        }
        .chip svg { width:14px; height:14px; opacity:.85; color: rgba(220,230,255,0.8); }
        
        /* Timetable by day */
        .day-section {
            margin-bottom: 32px;
        }
        .day-header {
            position: sticky; top: 64px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.10);
            border-radius: 20px;
            padding: 18px 24px;
            margin-bottom: 16px;
            z-index: 10;
            backdrop-filter: blur(14px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        .day-header h2 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 6px;
            color: #e8edff;
            letter-spacing: -0.2px;
        }
        .day-header p {
            font-size: 13px;
            color: rgba(220,230,255,0.75);
        }
        .sessions-grid {
            display: grid;
            gap: 12px;
        }
        .session-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.10);
            border-radius: 16px;
            padding: 18px 20px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(10px);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        .session-card:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.18);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        .session-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .session-time {
            font-size: 18px;
            font-weight: 700;
            color: #dce3ff;
        }
        .session-module {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #e8edff;
        }
        .session-module-code {
            color: rgba(220,230,255,0.65);
            font-size: 13px;
            font-weight: 400;
        }
        .session-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-top: 12px;
        }
        .session-detail {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: rgba(220,230,255,0.75);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        .session-detail-icon {
            font-size: 16px;
            color: rgba(220,230,255,0.6);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: rgba(220,230,255,0.65);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 16px;
            color: rgba(220,230,255,0.4);
        }
        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 8px;
            color: rgba(220,230,255,0.8);
            font-weight: 600;
        }
    </style>
            <?php if (empty($programmes) && $totalSessions > 0): ?>
                <div class="content-card" style="margin-bottom:20px; border: 1px solid rgba(241, 196, 15, 0.3); background: rgba(241, 196, 15, 0.12); border-radius: 16px; padding: 18px 22px; backdrop-filter: blur(10px); font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                    <div style="display:flex; gap:14px; align-items:flex-start;">
                        <div style="width: 36px; height: 36px; background: rgba(241, 196, 15, 0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid rgba(241, 196, 15, 0.3);">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" style="width:18px;height:18px; color: #fcd34d;">
                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                                <line x1="12" y1="9" x2="12" y2="13"></line>
                                <line x1="12" y1="17" x2="12.01" y2="17"></line>
                            </svg>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <strong style="color: #fcd34d; font-size: 14px; font-weight: 700; display: block; margin-bottom: 8px;">Notice:</strong>
                            <div style="color: rgba(252, 211, 77, 0.9); font-size: 13px; line-height: 1.6;">
                                Your sessions don't have programme/year/semester data.<br>
                                • Upload timetable via <strong style="color: rgba(252, 211, 77, 0.95);">Upload Timetable</strong> to automatically extract programme data, OR<br>
                                • Manually add programme/year/semester when creating sessions.
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Filters -->
            <form method="GET" class="filters" style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.10); border-radius: 24px; padding: 24px; backdrop-filter: blur(14px); box-shadow: 0 4px 20px rgba(0,0,0,0.45); margin-bottom: 20px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                <div class="filter-group">
                    <label>Programme</label>
                    <select name="programme" id="programmeSelect">
                        <option value="">All Programmes</option>
                        <?php if (empty($programmes)): ?>
                            <option value="" disabled>⚠️ No programmes found - Upload timetable to add programmes</option>
                        <?php else: ?>
                            <?php foreach ($programmes as $programme): ?>
                                <option value="<?= htmlspecialchars($programme) ?>" <?= $programmeFilter === $programme ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($programme) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Year Level</label>
                    <select name="year" id="yearFilter">
                        <option value="">All Year Levels</option>
                        <?php 
                        // Get all unique year levels from database
                        $allYears = [];
                        if ($programmeFilter) {
                            // If programme selected, get years for that programme
                            $stmt = $pdo->prepare("SELECT DISTINCT year_level FROM sessions WHERE programme = ? AND year_level IS NOT NULL AND year_level != '' ORDER BY year_level");
                            $stmt->execute([$programmeFilter]);
                            $allYears = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        } else {
                            // Get all unique years from all programmes
                            $allYears = $pdo->query("SELECT DISTINCT year_level FROM sessions WHERE year_level IS NOT NULL AND year_level != '' ORDER BY year_level")->fetchAll(PDO::FETCH_COLUMN);
                        }
                        foreach ($allYears as $year): ?>
                            <option value="<?= htmlspecialchars($year) ?>" <?= $yearFilter === $year ? 'selected' : '' ?>>
                                <?= htmlspecialchars($year) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Semester <span style="color: rgba(255,255,255,0.4); font-weight: normal; font-size: 11px;">(Optional)</span></label>
                    <select name="semester" id="semesterFilter">
                        <option value="">All Semesters</option>
                        <?php 
                        // Get semesters based on filters
                        $allSemesters = [];
                        if ($programmeFilter && $yearFilter) {
                            // If both programme and year selected, get semesters for that combination
                            $stmt = $pdo->prepare("SELECT DISTINCT semester FROM sessions WHERE programme = ? AND year_level = ? AND semester IS NOT NULL AND semester != '' ORDER BY semester");
                            $stmt->execute([$programmeFilter, $yearFilter]);
                            $allSemesters = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        } elseif ($programmeFilter) {
                            // If only programme selected, get all semesters for that programme
                            $stmt = $pdo->prepare("SELECT DISTINCT semester FROM sessions WHERE programme = ? AND semester IS NOT NULL AND semester != '' ORDER BY semester");
                            $stmt->execute([$programmeFilter]);
                            $allSemesters = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        } else {
                            // Get all unique semesters
                            $allSemesters = $pdo->query("SELECT DISTINCT semester FROM sessions WHERE semester IS NOT NULL AND semester != '' ORDER BY semester")->fetchAll(PDO::FETCH_COLUMN);
                        }
                        foreach ($allSemesters as $semester): ?>
                            <option value="<?= htmlspecialchars($semester) ?>" <?= $semesterFilter === $semester ? 'selected' : '' ?>>
                                <?= htmlspecialchars($semester) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Day</label>
                    <select name="day">
                        <option value="">All Days</option>
                        <?php foreach ($days as $day): ?>
                            <option value="<?= htmlspecialchars($day) ?>" <?= $dayFilter === $day ? 'selected' : '' ?>>
                                <?= htmlspecialchars($day) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="view_timetable.php" class="btn btn-secondary">Clear</a>
                </div>
            </form>
            
            <div style="margin-top: 16px; margin-bottom: 16px; padding: 18px 22px; background: rgba(59, 130, 246, 0.12); border: 1px solid rgba(59, 130, 246, 0.25); border-radius: 16px; backdrop-filter: blur(10px); font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                <div style="display:flex; gap:14px; align-items:flex-start;">
                    <div style="width: 36px; height: 36px; background: rgba(59, 130, 246, 0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid rgba(59, 130, 246, 0.3);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" style="width:18px;height:18px; color: #93c5fd;">
                            <path d="M12 3a7 7 0 0 0-4 12.83V18a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2v-2.17A7 7 0 0 0 12 3Z"/><path d="M9 18h6"/><path d="M10 22h4"/>
                        </svg>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <strong style="color: #e8edff; font-size: 14px; font-weight: 700; display: block; margin-bottom: 8px;">Filter Guide:</strong>
                        <div style="color: rgba(220,230,255,0.85); font-size: 13px; line-height: 1.6;">
                            • Select a <strong style="color: rgba(220,230,255,0.95);">Programme</strong> (e.g., "Diploma in ICT") and <strong style="color: rgba(220,230,255,0.95);">Year Level</strong> (e.g., "Level 1") to filter the timetable<br>
                            • <strong style="color: rgba(220,230,255,0.95);">Semester</strong> is optional - use it to further filter by specific semester (e.g., "Semester 1", "Semester 2")<br>
                            • Leave filters empty to view all sessions
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Summary -->
            <div class="summary">
                <div class="summary-text">
                    Showing <span class="summary-count"><?= $totalSessions ?></span> session<?= $totalSessions !== 1 ? 's' : '' ?>
                    <?php if ($programmeFilter || $yearFilter || $semesterFilter || $dayFilter): ?>
                        (filtered)
                    <?php endif; ?>
                    <?php if ($programmeFilter): ?>
                        <br><span style="font-size: 12px; color: rgba(220,230,255,0.65);">Programme: <?= htmlspecialchars($programmeFilter) ?></span>
                    <?php endif; ?>
                    <?php if ($yearFilter): ?>
                        <span style="font-size: 12px; color: rgba(220,230,255,0.65);"> | Year: <?= htmlspecialchars($yearFilter) ?></span>
                    <?php endif; ?>
                    <?php if ($semesterFilter): ?>
                        <span style="font-size: 12px; color: rgba(220,230,255,0.65);"> | Semester: <?= htmlspecialchars($semesterFilter) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Quick day chips for fast scroll -->
            <?php if (!empty($sessionsByDay)): ?>
            <div class="day-chips">
                <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $d): if (!isset($sessionsByDay[$d])) continue; ?>
                <a class="chip" href="#day-<?= strtolower($d) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"/></svg>
                    <?= $d ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Timetable by Day -->
            <?php if (empty($sessionsByDay)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon" aria-hidden="true" style="color:#6366F1;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" style="width:64px; height:64px;"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
                    </div>
                    <h3>No Sessions Found</h3>
                    <p>No timetable sessions match your filters. Try adjusting your search criteria or upload a timetable file.</p>
                </div>
            <?php else: ?>
                <?php 
                $dayOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                foreach ($dayOrder as $day): 
                    if (!isset($sessionsByDay[$day])) continue;
                    $daySessions = $sessionsByDay[$day];
                ?>
                    <div class="day-section" id="day-<?= strtolower($day) ?>">
                        <div class="day-header">
                            <h2><?= htmlspecialchars($day) ?></h2>
                            <p><?= count($daySessions) ?> session<?= count($daySessions) !== 1 ? 's' : '' ?></p>
                        </div>
                        
                        <div class="sessions-grid">
                            <?php foreach ($daySessions as $session): ?>
                                <div class="session-card">
                                    <div class="session-header">
                                        <div class="session-time">
                                            <?= date('g:i A', strtotime($session['start_time'])) ?> - 
                                            <?= date('g:i A', strtotime($session['end_time'])) ?>
                                        </div>
                                    </div>
                                    
                                    <div class="session-module">
                                        <?= htmlspecialchars($session['module_name'] ?? 'Unknown Module') ?>
                                        <span class="session-module-code">
                                            (<?= htmlspecialchars($session['module_code'] ?? 'N/A') ?>)
                                        </span>
                                    </div>
                                    
                                    <?php if ($session['programme'] || $session['year_level'] || $session['semester']): ?>
                                        <div class="session-details" style="margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.1);">
                                            <?php if ($session['programme']): ?>
                                                <div class="session-detail">
                                                    <span class="session-detail-icon" aria-hidden="true">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px; height:16px;"><path d="M8 2h8a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"/><path d="M8 6h8"/><path d="M8 10h8"/><path d="M8 14h6"/></svg>
                                                    </span>
                                                    <span><?= htmlspecialchars($session['programme']) ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($session['year_level']): ?>
                                                <div class="session-detail">
                                                    <span class="session-detail-icon" aria-hidden="true">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px; height:16px;"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
                                                    </span>
                                                    <span>Year <?= htmlspecialchars($session['year_level']) ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($session['semester']): ?>
                                                <div class="session-detail">
                                                    <span class="session-detail-icon" aria-hidden="true">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px; height:16px;"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/><path d="M8 14h4"/></svg>
                                                    </span>
                                                    <span><?= htmlspecialchars($session['semester']) ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="session-details">
                                        <?php if ($session['lecturer_name']): ?>
                                            <div class="session-detail">
                                                <span class="session-detail-icon" aria-hidden="true">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px; height:16px;"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>
                                                </span>
                                                <span><?= htmlspecialchars($session['lecturer_name']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($session['venue_name']): ?>
                                            <div class="session-detail">
                                                <span class="session-detail-icon" aria-hidden="true">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px; height:16px;"><path d="M12 21s-6-5.33-6-10a6 6 0 1 1 12 0c0 4.67-6 10-6 10z"/><circle cx="12" cy="11" r="2"/></svg>
                                                </span>
                                                <span><?= htmlspecialchars($session['venue_name']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Simple auto-submit on filter change - PHP handles dropdown population
        const programmeSelect = document.getElementById('programmeSelect');
        const yearSelect = document.getElementById('yearFilter');
        const semesterSelect = document.getElementById('semesterFilter');
        
        // Auto-apply filters when programme changes
        if (programmeSelect) {
            programmeSelect.addEventListener('change', function() {
                // Reset year and semester when programme changes
                if (yearSelect) yearSelect.value = '';
                if (semesterSelect) semesterSelect.value = '';
                // Submit form to reload with new filters
                document.querySelector('form[method="get"]').submit();
            });
        }
        
        // Auto-apply filters when year changes
        if (yearSelect) {
            yearSelect.addEventListener('change', function() {
                // Reset semester when year changes
                if (semesterSelect) semesterSelect.value = '';
                // Submit form to reload with new filters
                document.querySelector('form[method="get"]').submit();
            });
        }
        
        // Auto-apply filters when semester changes
        if (semesterSelect) {
            semesterSelect.addEventListener('change', function() {
                // Submit form to reload with new filters
                document.querySelector('form[method="get"]').submit();
            });
        }
    </script>
<?php include 'admin/footer_modern.php'; ?>

