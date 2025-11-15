<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin/login.php');
    exit;
}

require_once 'admin/config.php';

$pdo = new PDO("mysql:host=localhost;dbname=smart_timetable", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Handle delete
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM sessions WHERE session_id = ?");
    $stmt->execute([$_GET['id']]);
    header('Location: timetable_editor.php');
    exit;
}

// Handle bulk lecturer update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_lecturer'])) {
    $sessionIds = $_POST['session_ids'] ?? [];
    $lecturerId = $_POST['lecturer_id'] ?? null;
    
    if (!empty($sessionIds) && $lecturerId) {
        $placeholders = implode(',', array_fill(0, count($sessionIds), '?'));
        $stmt = $pdo->prepare("UPDATE sessions SET lecturer_id = ? WHERE session_id IN ($placeholders)");
        $stmt->execute(array_merge([$lecturerId], $sessionIds));
    }
    header('Location: timetable_editor.php');
    exit;
}

// Handle bulk venue update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_venue'])) {
    $sessionIds = $_POST['session_ids'] ?? [];
    $venueId = $_POST['venue_id'] ?? null;
    
    if (!empty($sessionIds) && $venueId) {
        $placeholders = implode(',', array_fill(0, count($sessionIds), '?'));
        $stmt = $pdo->prepare("UPDATE sessions SET venue_id = ? WHERE session_id IN ($placeholders)");
        $stmt->execute(array_merge([$venueId], $sessionIds));
    }
    header('Location: timetable_editor.php');
    exit;
}

// Handle lecturer name update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_lecturer_name'])) {
    $lecturerId = $_POST['lecturer_id'];
    $newName = $_POST['lecturer_name'];
    
    $stmt = $pdo->prepare("UPDATE lecturers SET lecturer_name = ? WHERE lecturer_id = ?");
    $stmt->execute([$newName, $lecturerId]);
    header('Location: timetable_editor.php');
    exit;
}

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
$searchFilter = $_GET['search'] ?? '';

// Get sessions with joins
$query = "
    SELECT s.*, m.module_code, m.module_name, l.lecturer_name, l.lecturer_id, v.venue_name, v.venue_id
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

if ($searchFilter) {
    $query .= " AND (m.module_code LIKE ? OR m.module_name LIKE ? OR l.lecturer_name LIKE ? OR v.venue_name LIKE ?)";
    $searchTerm = "%{$searchFilter}%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

$query .= " ORDER BY s.day_of_week, s.start_time";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all lecturers and venues for bulk editing
$lecturers = $pdo->query("SELECT * FROM lecturers ORDER BY lecturer_name")->fetchAll(PDO::FETCH_ASSOC);
$venues = $pdo->query("SELECT * FROM venues ORDER BY venue_name")->fetchAll(PDO::FETCH_ASSOC);
$modules = $pdo->query("SELECT * FROM modules ORDER BY module_code")->fetchAll(PDO::FETCH_ASSOC);

// Get programme/year/semester data for filters
$programmes = $pdo->query("SELECT DISTINCT programme FROM sessions WHERE programme IS NOT NULL AND programme != '' ORDER BY programme")->fetchAll(PDO::FETCH_COLUMN);
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timetable Editor - Smart Timetable</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0a0a0a;
            color: #ffffff;
            min-height: 100vh;
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
            display: flex;
            gap: 16px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        .filter-group label {
            display: block;
            font-size: 12px;
            color: rgba(255,255,255,0.5);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }
        .filter-group select,
        .filter-group input {
            width: 100%;
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
        }
        .btn-apply {
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-apply:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(102, 126, 234, 0.4);
        }
        
        /* Bulk Actions */
        .bulk-actions {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        .bulk-actions label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: rgba(255,255,255,0.7);
            font-size: 14px;
            cursor: pointer;
        }
        .bulk-actions input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .bulk-btn {
            padding: 8px 16px;
            background: rgba(102, 126, 234, 0.2);
            border: 1px solid rgba(102, 126, 234, 0.3);
            color: #667eea;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .bulk-btn:hover {
            background: rgba(102, 126, 234, 0.3);
        }
        
        /* Table */
        .table-container {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            overflow: hidden;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead {
            background: rgba(102, 126, 234, 0.1);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        th {
            padding: 16px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: rgba(255,255,255,0.6);
            font-weight: 600;
        }
        td {
            padding: 16px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            color: rgba(255,255,255,0.8);
            font-size: 14px;
        }
        tbody tr:hover {
            background: rgba(255,255,255,0.03);
        }
        .session-module {
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 4px;
        }
        .session-details {
            font-size: 12px;
            color: rgba(255,255,255,0.5);
        }
        .btn-delete {
            padding: 6px 12px;
            background: rgba(231, 76, 60, 0.2);
            border: 1px solid rgba(231, 76, 60, 0.3);
            color: #e74c3c;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            transition: all 0.3s;
        }
        .btn-delete:hover {
            background: rgba(231, 76, 60, 0.3);
        }
        .btn-edit {
            padding: 6px 12px;
            background: rgba(102, 126, 234, 0.2);
            border: 1px solid rgba(102, 126, 234, 0.3);
            color: #667eea;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            transition: all 0.3s;
        }
        .btn-edit:hover {
            background: rgba(102, 126, 234, 0.3);
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: #1a1a1a;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 32px;
            min-width: 400px;
            max-width: 90%;
        }
        .modal-header {
            margin-bottom: 24px;
        }
        .modal-header h3 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .modal-body input {
            width: 100%;
            padding: 12px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            color: #ffffff;
            font-size: 14px;
            margin-bottom: 20px;
        }
        .modal-body input:focus {
            outline: none;
            border-color: #667eea;
        }
        .modal-footer {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.7);
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
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
                    <a href="students.php"><i>👥</i> Students</a>
                    <a href="admin/lecturers.php"><i>👤</i> Lecturers</a>
                    <a href="admin/venues.php"><i>📍</i> Venues</a>
                </nav>
            </div>
            
            <div class="sidebar-section">
                <div class="sidebar-section-title">Curriculum & Timetable</div>
                <nav class="sidebar-nav">
                    <a href="admin/modules.php"><i>📚</i> Modules</a>
                    <a href="admin/timetable.php"><i>➕</i> Add Session</a>
                    <a href="timetable_editor.php" class="active"><i>✏️</i> Edit Sessions</a>
                    <a href="view_timetable.php"><i>📋</i> View Timetable</a>
                    <a href="timetable_pdf_parser.php"><i>📤</i> Upload Timetable</a>
                    <a href="admin/exams.php"><i>📆</i> Exam Timetables</a>
                </nav>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div style="margin-bottom: 24px;">
                <a href="dashboard.php" style="display: inline-flex; align-items: center; gap: 8px; color: rgba(255,255,255,0.7); text-decoration: none; font-size: 14px; padding: 8px 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; transition: all 0.2s;" onmouseover="this.style.background='rgba(102, 126, 234, 0.1)'; this.style.borderColor='rgba(102, 126, 234, 0.3)'; this.style.color='#667eea';" onmouseout="this.style.background='rgba(255,255,255,0.05)'; this.style.borderColor='rgba(255,255,255,0.1)'; this.style.color='rgba(255,255,255,0.7)';">
                    <span>←</span>
                    <span>Back to Dashboard</span>
                </a>
            </div>
            <div class="page-header">
                <h1>Timetable Editor</h1>
                <p>Edit, update, and manage timetable sessions with bulk operations</p>
            </div>
            
            <!-- Filters -->
            <div class="filters">
                <div class="filter-group">
                    <label>Programme</label>
                    <select name="programme" id="programmeFilter">
                        <option value="">All programmes</option>
                    </select>
                </div>
                <div class="filter-group" style="flex: 2;">
                    <label>Search</label>
                    <input type="text" id="searchFilter" placeholder="Module, lecturer, venue..." value="<?= htmlspecialchars($searchFilter) ?>">
                </div>
                <button type="button" class="btn-apply" onclick="applyFilters()">Apply filters</button>
            </div>
            
            <!-- Bulk Actions -->
            <div class="bulk-actions">
                <label>
                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                    Select all sessions in view
                </label>
                <button type="button" class="bulk-btn" onclick="openBulkLecturerModal()">New lecturer</button>
                <button type="button" class="bulk-btn" onclick="openBulkVenueModal()">New venue</button>
                <button type="button" class="bulk-btn" onclick="openBulkLecturerModal()">Bulk venue</button>
                <button type="button" class="bulk-btn" onclick="openBulkLecturerModal()">Bulk lecturer</button>
            </div>
            
            <!-- Sessions Table -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th></th>
                            <th>Module</th>
                            <th>Lecturer</th>
                            <th>Venue</th>
                            <th>Day</th>
                            <th>Start</th>
                            <th>End</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sessions)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: rgba(255,255,255,0.5);">
                                No sessions found. Upload a timetable file or add sessions manually.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($sessions as $session): ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="session-checkbox" value="<?= $session['session_id'] ?>">
                            </td>
                            <td>
                                <div class="session-module"><?= htmlspecialchars($session['module_code'] ?? '-') ?></div>
                                <div class="session-details"><?= htmlspecialchars($session['module_name'] ?? '') ?></div>
                            </td>
                            <td>
                                <span style="cursor: pointer;" onclick="openEditLecturerModal(<?= $session['lecturer_id'] ?>, '<?= htmlspecialchars($session['lecturer_name'] ?? '') ?>')">
                                    <?= htmlspecialchars($session['lecturer_name'] ?? '-') ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($session['venue_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($session['day_of_week']) ?></td>
                            <td><?= htmlspecialchars(substr($session['start_time'], 0, 5)) ?></td>
                            <td><?= htmlspecialchars(substr($session['end_time'], 0, 5)) ?></td>
                            <td>
                                <a href="?delete=1&id=<?= $session['session_id'] ?>" class="btn-delete" onclick="return confirm('Are you sure?')">🗑️ Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Edit Lecturer Modal -->
    <div class="modal" id="editLecturerModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>localhost says</h3>
                <p>Update Lecturer name</p>
            </div>
            <form method="POST" id="lecturerForm">
                <div class="modal-body">
                    <input type="hidden" name="lecturer_id" id="lecturerId">
                    <input type="text" name="lecturer_name" id="lecturerName" placeholder="Lecturer name" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="update_lecturer_name" class="btn btn-primary">OK</button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditLecturerModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Bulk Lecturer Modal -->
    <div class="modal" id="bulkLecturerModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Bulk Update Lecturer</h3>
                <p>Select lecturer for selected sessions</p>
            </div>
            <form method="POST" id="bulkLecturerForm">
                <div class="modal-body">
                    <input type="hidden" name="session_ids" id="bulkSessionIds">
                    <select name="lecturer_id" id="bulkLecturerId" required style="width: 100%; padding: 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: #ffffff; font-size: 14px;">
                        <option value="">Select Lecturer</option>
                        <?php foreach ($lecturers as $lecturer): ?>
                        <option value="<?= $lecturer['lecturer_id'] ?>"><?= htmlspecialchars($lecturer['lecturer_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="bulk_lecturer" class="btn btn-primary">Update</button>
                    <button type="button" class="btn btn-secondary" onclick="closeBulkLecturerModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Bulk Venue Modal -->
    <div class="modal" id="bulkVenueModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Bulk Update Venue</h3>
                <p>Select venue for selected sessions</p>
            </div>
            <form method="POST" id="bulkVenueForm">
                <div class="modal-body">
                    <input type="hidden" name="session_ids" id="bulkVenueSessionIds">
                    <select name="venue_id" id="bulkVenueId" required style="width: 100%; padding: 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: #ffffff; font-size: 14px;">
                        <option value="">Select Venue</option>
                        <?php foreach ($venues as $venue): ?>
                        <option value="<?= $venue['venue_id'] ?>"><?= htmlspecialchars($venue['venue_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="bulk_venue" class="btn btn-primary">Update</button>
                    <button type="button" class="btn btn-secondary" onclick="closeBulkVenueModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.session-checkbox');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
        }
        
        // Filter data from PHP
        const filterData = <?= json_encode($filterData) ?>;
        
        const programmeSelect = document.getElementById('programmeFilter');
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
        
        function applyFilters() {
            const programme = document.getElementById('programmeFilter').value;
            const year = document.getElementById('yearFilter').value;
            const semester = document.getElementById('semesterFilter').value;
            const search = document.getElementById('searchFilter').value;
            
            const params = new URLSearchParams();
            if (programme) params.append('programme', programme);
            if (year) params.append('year', year);
            if (semester) params.append('semester', semester);
            if (search) params.append('search', search);
            
            window.location.href = 'timetable_editor.php' + (params.toString() ? '?' + params.toString() : '');
        }
        
        function openEditLecturerModal(lecturerId, lecturerName) {
            document.getElementById('lecturerId').value = lecturerId || '';
            document.getElementById('lecturerName').value = lecturerName || '';
            document.getElementById('editLecturerModal').classList.add('active');
        }
        
        function closeEditLecturerModal() {
            document.getElementById('editLecturerModal').classList.remove('active');
        }
        
        function openBulkLecturerModal() {
            const checked = Array.from(document.querySelectorAll('.session-checkbox:checked')).map(cb => cb.value);
            if (checked.length === 0) {
                alert('Please select at least one session');
                return;
            }
            document.getElementById('bulkSessionIds').value = JSON.stringify(checked);
            document.getElementById('bulkLecturerModal').classList.add('active');
        }
        
        function closeBulkLecturerModal() {
            document.getElementById('bulkLecturerModal').classList.remove('active');
        }
        
        function openBulkVenueModal() {
            const checked = Array.from(document.querySelectorAll('.session-checkbox:checked')).map(cb => cb.value);
            if (checked.length === 0) {
                alert('Please select at least one session');
                return;
            }
            document.getElementById('bulkVenueSessionIds').value = JSON.stringify(checked);
            document.getElementById('bulkVenueModal').classList.add('active');
        }
        
        function closeBulkVenueModal() {
            document.getElementById('bulkVenueModal').classList.remove('active');
        }
        
        // Close modal on outside click
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.classList.remove('active');
                }
            });
        }
        
        // Handle form submission for bulk operations
        document.getElementById('bulkLecturerForm').addEventListener('submit', function(e) {
            const sessionIds = JSON.parse(document.getElementById('bulkSessionIds').value);
            sessionIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'session_ids[]';
                input.value = id;
                this.appendChild(input);
            });
        });
        
        document.getElementById('bulkVenueForm').addEventListener('submit', function(e) {
            const sessionIds = JSON.parse(document.getElementById('bulkVenueSessionIds').value);
            sessionIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'session_ids[]';
                input.value = id;
                this.appendChild(input);
            });
        });
    </script>
</body>
</html>

