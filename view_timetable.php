<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin/login.php');
    exit;
}

require_once 'admin/config.php';

$pdo = new PDO("mysql:host=localhost;dbname=smart_timetable", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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
// Check if any sessions have programme data
$hasProgrammeData = $pdo->query("SELECT COUNT(*) FROM sessions WHERE programme IS NOT NULL AND programme != ''")->fetchColumn();
$programmes = [];
if ($hasProgrammeData > 0) {
    $programmes = $pdo->query("SELECT DISTINCT programme FROM sessions WHERE programme IS NOT NULL AND programme != '' ORDER BY programme")->fetchAll(PDO::FETCH_COLUMN);
}
$days = $pdo->query("SELECT DISTINCT day_of_week FROM sessions ORDER BY day_of_week")->fetchAll(PDO::FETCH_COLUMN);

// Get all programme-year-semester combinations for dynamic filtering
$programmeYearSemester = $pdo->query("
    SELECT DISTINCT programme, year_level, semester 
    FROM sessions 
    WHERE programme IS NOT NULL AND programme != '' 
    AND year_level IS NOT NULL AND year_level != ''
    AND semester IS NOT NULL AND semester != ''
    ORDER BY programme, year_level, semester
")->fetchAll(PDO::FETCH_ASSOC);

// Build data structure for JavaScript
$filterData = [];
foreach ($programmeYearSemester as $row) {
    $prog = trim($row['programme']);
    $year = trim($row['year_level']);
    $sem = trim($row['semester']);
    
    if (empty($prog) || empty($year) || empty($sem)) continue;
    
    if (!isset($filterData[$prog])) {
        $filterData[$prog] = [];
    }
    if (!isset($filterData[$prog][$year])) {
        $filterData[$prog][$year] = [];
    }
    if (!in_array($sem, $filterData[$prog][$year])) {
        $filterData[$prog][$year][] = $sem;
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Timetable - Smart Timetable</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0a0a0a;
            color: #ffffff;
            min-height: 100vh;
            overflow-x: hidden;
        }
        .container { display: flex; min-height: 100vh; }
        
        /* Sidebar - Same as dashboard */
        .sidebar {
            width: 280px;
            background: #0a0a0a;
            padding: 32px 24px;
            border-right: 1px solid rgba(255,255,255,0.08);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        .sidebar::-webkit-scrollbar { width: 6px; }
        .sidebar::-webkit-scrollbar-track { background: transparent; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 3px; }
        
        .sidebar-header {
            margin-bottom: 48px;
            padding-bottom: 24px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .sidebar-header h1 {
            font-size: 22px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }
        .admin-console {
            display: flex;
            align-items: center;
            gap: 8px;
            color: rgba(255,255,255,0.6);
            font-size: 13px;
            margin-bottom: 8px;
            font-weight: 500;
        }
        .sidebar-section {
            margin-bottom: 32px;
        }
        .sidebar-section-title {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: rgba(255,255,255,0.4);
            margin-bottom: 16px;
            padding: 0 12px;
            font-weight: 600;
        }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 4px;
            transition: all 0.2s ease;
            font-size: 14px;
            font-weight: 500;
        }
        .sidebar-nav a:hover {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }
        .sidebar-nav a.active {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%);
            color: #667eea;
            border-left: 3px solid #667eea;
        }
        .sidebar-nav a i {
            margin-right: 12px;
            width: 20px;
            font-style: normal;
            font-size: 16px;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 48px;
            background: #0a0a0a;
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            font-size: 14px;
            padding: 8px 16px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            transition: all 0.2s;
            margin-bottom: 24px;
        }
        .back-btn:hover {
            background: rgba(102, 126, 234, 0.1);
            border-color: rgba(102, 126, 234, 0.3);
            color: #667eea;
        }
        
        .page-header {
            margin-bottom: 32px;
        }
        .page-header h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -1px;
        }
        .page-header p {
            color: rgba(255,255,255,0.6);
            font-size: 14px;
        }
        
        /* Filters */
        .filters {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        .filter-group label {
            font-size: 12px;
            color: rgba(255,255,255,0.5);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
            font-weight: 600;
        }
        .filter-group select,
        .filter-group input {
            padding: 10px 14px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            color: #ffffff;
            font-size: 14px;
        }
        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #667eea;
            background: rgba(255,255,255,0.08);
        }
        .filter-actions {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.8);
        }
        .btn-secondary:hover {
            background: rgba(255,255,255,0.15);
        }
        
        /* Summary */
        .summary {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .summary-text {
            font-size: 14px;
            color: rgba(255,255,255,0.7);
        }
        .summary-count {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        /* Timetable by day */
        .day-section {
            margin-bottom: 32px;
        }
        .day-header {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 16px;
        }
        .day-header h2 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .day-header p {
            font-size: 13px;
            color: rgba(255,255,255,0.6);
        }
        .sessions-grid {
            display: grid;
            gap: 12px;
        }
        .session-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 20px;
            transition: all 0.2s;
        }
        .session-card:hover {
            border-color: rgba(102, 126, 234, 0.5);
            background: rgba(102, 126, 234, 0.05);
            transform: translateY(-2px);
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
            color: #667eea;
        }
        .session-module {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .session-module-code {
            color: rgba(255,255,255,0.5);
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
            color: rgba(255,255,255,0.7);
        }
        .session-detail-icon {
            font-size: 16px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: rgba(255,255,255,0.5);
        }
        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 16px;
        }
        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 8px;
            color: rgba(255,255,255,0.8);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h1>SMART TIMETABLE</h1>
                <div class="admin-console">
                    <span>⚙️</span>
                    <span>Admin Console</span>
                </div>
            </div>
            
            <div class="sidebar-section">
                <div class="sidebar-section-title">Overview</div>
                <nav class="sidebar-nav">
                    <a href="dashboard.php"><i>📊</i> Dashboard</a>
                </nav>
            </div>
            
            <div class="sidebar-section">
                <div class="sidebar-section-title">Academic Structure</div>
                <nav class="sidebar-nav">
                    <a href="#"><i>🏛️</i> Faculties</a>
                    <a href="#"><i>🏫</i> Schools</a>
                    <a href="#"><i>📜</i> Programmes</a>
                    <a href="#"><i>📅</i> Academic Years</a>
                </nav>
            </div>
            
            <div class="sidebar-section">
                <div class="sidebar-section-title">People & Resources</div>
                <nav class="sidebar-nav">
                    <a href="admin/students.php"><i>👥</i> Students</a>
                    <a href="admin/lecturers.php"><i>👤</i> Lecturers</a>
                    <a href="admin/venues.php"><i>📍</i> Venues</a>
                </nav>
            </div>
            
            <div class="sidebar-section">
                <div class="sidebar-section-title">Curriculum & Timetable</div>
                <nav class="sidebar-nav">
                    <a href="admin/modules.php"><i>📚</i> Modules</a>
                    <a href="admin/timetable.php"><i>➕</i> Add Session</a>
                    <a href="timetable_editor.php"><i>✏️</i> Edit Sessions</a>
                    <a href="view_timetable.php" class="active"><i>📋</i> View Timetable</a>
                    <a href="timetable_pdf_parser.php"><i>📤</i> Upload Timetable</a>
                    <a href="admin/exams.php"><i>📆</i> Exam Timetables</a>
                </nav>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <a href="dashboard.php" class="back-btn">
                <span>←</span>
                <span>Back to Dashboard</span>
            </a>
            
            <div class="page-header">
                <h1>View Timetable</h1>
                <p>Browse all timetable sessions organized by day</p>
            </div>
            
            <!-- Filters -->
            <form method="GET" class="filters">
                <div class="filter-group">
                    <label>Programme</label>
                    <select name="programme" id="programmeSelect">
                        <option value="">All Programmes</option>
                        <?php if (empty($programmes)): ?>
                            <option value="" disabled>No programmes found - Please upload timetable file</option>
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
                        <option value="">All Years</option>
                        <?php 
                        // Show years for selected programme if one is selected
                        if ($programmeFilter && isset($filterData[$programmeFilter])) {
                            $yearsForProgramme = array_keys($filterData[$programmeFilter]);
                            sort($yearsForProgramme);
                            foreach ($yearsForProgramme as $year): ?>
                                <option value="<?= htmlspecialchars($year) ?>" <?= $yearFilter === $year ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($year) ?>
                                </option>
                            <?php endforeach;
                        }
                        ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Semester</label>
                    <select name="semester" id="semesterFilter">
                        <option value="">All Semesters</option>
                        <?php 
                        // Show semesters for selected programme and year if both are selected
                        if ($programmeFilter && $yearFilter && isset($filterData[$programmeFilter][$yearFilter])) {
                            $semestersForProgrammeYear = $filterData[$programmeFilter][$yearFilter];
                            sort($semestersForProgrammeYear);
                            foreach ($semestersForProgrammeYear as $semester): ?>
                                <option value="<?= htmlspecialchars($semester) ?>" <?= $semesterFilter === $semester ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($semester) ?>
                                </option>
                            <?php endforeach;
                        }
                        ?>
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
            
            <!-- Summary -->
            <div class="summary">
                <div class="summary-text">
                    Showing <span class="summary-count"><?= $totalSessions ?></span> session<?= $totalSessions !== 1 ? 's' : '' ?>
                    <?php if ($programmeFilter || $yearFilter || $semesterFilter || $dayFilter): ?>
                        (filtered)
                    <?php endif; ?>
                    <?php if ($programmeFilter): ?>
                        <br><span style="font-size: 12px; color: rgba(255,255,255,0.5);">Programme: <?= htmlspecialchars($programmeFilter) ?></span>
                    <?php endif; ?>
                    <?php if ($yearFilter): ?>
                        <span style="font-size: 12px; color: rgba(255,255,255,0.5);"> | Year: <?= htmlspecialchars($yearFilter) ?></span>
                    <?php endif; ?>
                    <?php if ($semesterFilter): ?>
                        <span style="font-size: 12px; color: rgba(255,255,255,0.5);"> | Semester: <?= htmlspecialchars($semesterFilter) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Timetable by Day -->
            <?php if (empty($sessionsByDay)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📅</div>
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
                    <div class="day-section">
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
                                                    <span class="session-detail-icon">📜</span>
                                                    <span><?= htmlspecialchars($session['programme']) ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($session['year_level']): ?>
                                                <div class="session-detail">
                                                    <span class="session-detail-icon">📅</span>
                                                    <span>Year <?= htmlspecialchars($session['year_level']) ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($session['semester']): ?>
                                                <div class="session-detail">
                                                    <span class="session-detail-icon">📆</span>
                                                    <span><?= htmlspecialchars($session['semester']) ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="session-details">
                                        <?php if ($session['lecturer_name']): ?>
                                            <div class="session-detail">
                                                <span class="session-detail-icon">👤</span>
                                                <span><?= htmlspecialchars($session['lecturer_name']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($session['venue_name']): ?>
                                            <div class="session-detail">
                                                <span class="session-detail-icon">📍</span>
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
        // Filter data from PHP
        const filterData = <?= json_encode($filterData) ?>;
        
        console.log('Filter Data:', filterData); // Debug
        
        const programmeSelect = document.getElementById('programmeSelect');
        const yearSelect = document.getElementById('yearFilter');
        const semesterSelect = document.getElementById('semesterFilter');
        
        // Initialize: If programme is already selected on page load, populate years
        if (programmeSelect && programmeSelect.value && filterData[programmeSelect.value]) {
            const years = Object.keys(filterData[programmeSelect.value]).sort();
            years.forEach(year => {
                const option = document.createElement('option');
                option.value = year;
                option.textContent = year;
                if (yearSelect.querySelector(`option[value="${year}"]`)) {
                    yearSelect.querySelector(`option[value="${year}"]`).selected = true;
                } else {
                    yearSelect.appendChild(option);
                }
            });
            
            // If year is also selected, populate semesters
            if (yearSelect.value && filterData[programmeSelect.value][yearSelect.value]) {
                const semesters = filterData[programmeSelect.value][yearSelect.value].sort();
                semesters.forEach(semester => {
                    const option = document.createElement('option');
                    option.value = semester;
                    option.textContent = semester;
                    if (semesterSelect.querySelector(`option[value="${semester}"]`)) {
                        semesterSelect.querySelector(`option[value="${semester}"]`).selected = true;
                    } else {
                        semesterSelect.appendChild(option);
                    }
                });
            }
        }
        
        // Update year dropdown when programme changes
        if (programmeSelect) {
            programmeSelect.addEventListener('change', function() {
                const selectedProgramme = this.value;
                
                // Clear year and semester
                yearSelect.innerHTML = '<option value="">All Years</option>';
                semesterSelect.innerHTML = '<option value="">All Semesters</option>';
                
                if (selectedProgramme && filterData[selectedProgramme]) {
                    // Get years for selected programme
                    const years = Object.keys(filterData[selectedProgramme]).sort();
                    years.forEach(year => {
                        const option = document.createElement('option');
                        option.value = year;
                        option.textContent = year;
                        yearSelect.appendChild(option);
                    });
                }
            });
        }
        
        // Update semester dropdown when year changes
        if (yearSelect) {
            yearSelect.addEventListener('change', function() {
                const selectedProgramme = programmeSelect ? programmeSelect.value : '';
                const selectedYear = this.value;
                
                // Clear semester
                semesterSelect.innerHTML = '<option value="">All Semesters</option>';
                
                if (selectedProgramme && selectedYear && filterData[selectedProgramme] && filterData[selectedProgramme][selectedYear]) {
                    // Get semesters for selected programme and year
                    const semesters = filterData[selectedProgramme][selectedYear].sort();
                    semesters.forEach(semester => {
                        const option = document.createElement('option');
                        option.value = semester;
                        option.textContent = semester;
                        semesterSelect.appendChild(option);
                    });
                }
            });
        }
    </script>
</body>
</html>

