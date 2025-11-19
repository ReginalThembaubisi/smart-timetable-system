<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin/login.php');
    exit;
}

require_once 'admin/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/helpers.php';

$pdo = Database::getInstance()->getConnection();

// Handle single-session creation (AJAX)
if (
	$_SERVER['REQUEST_METHOD'] === 'POST'
	&& (
		isset($_POST['create_session'])
		|| (isset($_POST['module_id'], $_POST['day_of_week'], $_POST['start_time'], $_POST['end_time']) && !isset($_POST['update_session_inline']))
	)
) {
	header('Content-Type: application/json');
	try {
		$moduleId = $_POST['module_id'] ?? null;
		$lecturerId = $_POST['lecturer_id'] ?? null;
		$venueId = $_POST['venue_id'] ?? null;
		$dayOfWeek = $_POST['day_of_week'] ?? null;
		$startTime = $_POST['start_time'] ?? null;
		$endTime = $_POST['end_time'] ?? null;
		$programme = $_POST['programme'] ?? null;
		$yearLevel = $_POST['year_level'] ?? null;
		$semester = $_POST['semester'] ?? null;
		$lecturerName = trim($_POST['lecturer_name'] ?? '');
		$venueName = trim($_POST['venue_name'] ?? '');

		if (!$moduleId || !$dayOfWeek || !$startTime || !$endTime) {
			http_response_code(400);
			echo json_encode(['success' => false, 'error' => 'Missing required fields']);
			exit;
		}

		// Normalize times to HH:MM:SS
		$normalizeTime = function($t) {
			$t = trim($t);
			if ($t === '') return null;
			if (preg_match('/^\d{2}:\d{2}$/', $t)) return $t . ':00';
			return $t;
		};
		$startTime = $normalizeTime($startTime);
		$endTime = $normalizeTime($endTime);

		// Resolve lecturer by name if provided
		if (!$lecturerId && $lecturerName !== '') {
			$stmt = $pdo->prepare("SELECT lecturer_id FROM lecturers WHERE lecturer_name = ?");
			$stmt->execute([$lecturerName]);
			$row = $stmt->fetch();
			if ($row) {
				$lecturerId = $row['lecturer_id'];
			} else {
				$stmt = $pdo->prepare("INSERT INTO lecturers (lecturer_name, email) VALUES (?, '')");
				$stmt->execute([$lecturerName]);
				$lecturerId = $pdo->lastInsertId();
			}
		}

		// Resolve venue by name if provided
		if (!$venueId && $venueName !== '') {
			$stmt = $pdo->prepare("SELECT venue_id FROM venues WHERE venue_name = ?");
			$stmt->execute([$venueName]);
			$row = $stmt->fetch();
			if ($row) {
				$venueId = $row['venue_id'];
			} else {
				$stmt = $pdo->prepare("INSERT INTO venues (venue_name, capacity) VALUES (?, 0)");
				$stmt->execute([$venueName]);
				$venueId = $pdo->lastInsertId();
			}
		}

		// Detect day column name
		$dayColumn = 'day_of_week';
		try {
			$col = $pdo->query("SHOW COLUMNS FROM sessions LIKE 'day_of_week'")->fetch();
			if (!$col) {
				$colAlt = $pdo->query("SHOW COLUMNS FROM sessions LIKE 'day'")->fetch();
				if ($colAlt) $dayColumn = 'day';
			}
		} catch (Exception $e) {
			// keep default
			logError($e, 'Detecting day column in timetable editor');
		}

		$stmt = $pdo->prepare("
			INSERT INTO sessions (module_id, lecturer_id, venue_id, $dayColumn, start_time, end_time, programme, year_level, semester)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
		");
		$stmt->execute([
			$moduleId,
			$lecturerId ?: null,
			$venueId ?: null,
			$dayOfWeek,
			$startTime,
			$endTime,
			$programme ?: null,
			$yearLevel ?: null,
			$semester ?: null
		]);

		$sessionId = $pdo->lastInsertId();
		logActivity('session_created', "Session ID: {$sessionId}", getCurrentUserId());
		echo json_encode(['success' => true, 'session_id' => $sessionId]);
		exit;
	} catch (Exception $e) {
		logError($e, 'Creating session in timetable editor');
		http_response_code(500);
		echo json_encode(['success' => false, 'error' => getErrorMessage($e, 'Creating session', false)]);
		exit;
	}
}

// Handle delete
if (isset($_GET['delete']) && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM sessions WHERE session_id = ?");
        $stmt->execute([$_GET['id']]);
        logActivity('session_deleted', "Session ID: {$_GET['id']}", getCurrentUserId());
    } catch (Exception $e) {
        logError($e, 'Deleting session');
    }
    header('Location: timetable_editor.php');
    exit;
}

// Handle bulk lecturer update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_lecturer'])) {
    try {
        $sessionIds = $_POST['session_ids'] ?? [];
        $lecturerId = $_POST['lecturer_id'] ?? null;
        
        if (!empty($sessionIds) && $lecturerId) {
            $placeholders = implode(',', array_fill(0, count($sessionIds), '?'));
            $stmt = $pdo->prepare("UPDATE sessions SET lecturer_id = ? WHERE session_id IN ($placeholders)");
            $stmt->execute(array_merge([$lecturerId], $sessionIds));
            logActivity('bulk_lecturer_update', "Updated " . count($sessionIds) . " sessions", getCurrentUserId());
        }
    } catch (Exception $e) {
        logError($e, 'Bulk lecturer update');
    }
    header('Location: timetable_editor.php');
    exit;
}

// Handle bulk venue update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_venue'])) {
    try {
        $sessionIds = $_POST['session_ids'] ?? [];
        $venueId = $_POST['venue_id'] ?? null;
        
        if (!empty($sessionIds) && $venueId) {
            $placeholders = implode(',', array_fill(0, count($sessionIds), '?'));
            $stmt = $pdo->prepare("UPDATE sessions SET venue_id = ? WHERE session_id IN ($placeholders)");
            $stmt->execute(array_merge([$venueId], $sessionIds));
            logActivity('bulk_venue_update', "Updated " . count($sessionIds) . " sessions", getCurrentUserId());
        }
    } catch (Exception $e) {
        logError($e, 'Bulk venue update');
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

// Handle inline session updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_session_inline'])) {
    $sessionId = $_POST['session_id'];
    $lecturerId = $_POST['lecturer_id'] ?? null;
    $venueId = $_POST['venue_id'] ?? null;
    $dayOfWeek = $_POST['day_of_week'] ?? null;
    $startTime = $_POST['start_time'] ?? null;
    $endTime = $_POST['end_time'] ?? null;
    
    // If lecturer name is provided but no ID, find or create lecturer
    if (isset($_POST['lecturer_name']) && !empty($_POST['lecturer_name']) && !$lecturerId) {
        $lecturerName = trim($_POST['lecturer_name']);
        $stmt = $pdo->prepare("SELECT lecturer_id FROM lecturers WHERE lecturer_name = ?");
        $stmt->execute([$lecturerName]);
        $existing = $stmt->fetch();
        if ($existing) {
            $lecturerId = $existing['lecturer_id'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO lecturers (lecturer_name, email) VALUES (?, '')");
            $stmt->execute([$lecturerName]);
            $lecturerId = $pdo->lastInsertId();
        }
    }
    
    // If venue name is provided but no ID, find or create venue
    if (isset($_POST['venue_name']) && !empty($_POST['venue_name']) && !$venueId) {
        $venueName = trim($_POST['venue_name']);
        $stmt = $pdo->prepare("SELECT venue_id FROM venues WHERE venue_name = ?");
        $stmt->execute([$venueName]);
        $existing = $stmt->fetch();
        if ($existing) {
            $venueId = $existing['venue_id'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO venues (venue_name, capacity) VALUES (?, 0)");
            $stmt->execute([$venueName]);
            $venueId = $pdo->lastInsertId();
        }
    }
    
    try {
        // Some databases might use 'day' instead of 'day_of_week' - detect column name
        $dayColumn = 'day_of_week';
        try {
            $col = $pdo->query("SHOW COLUMNS FROM sessions LIKE 'day_of_week'")->fetch();
            if (!$col) {
                $colAlt = $pdo->query("SHOW COLUMNS FROM sessions LIKE 'day'")->fetch();
                if ($colAlt) {
                    $dayColumn = 'day';
                }
            }
        } catch (Exception $e) {
            // ignore; default to day_of_week
            logError($e, 'Detecting day column for update');
        }
        // Build dynamic UPDATE query based on what fields are provided
        $updates = [];
        $params = [];
        
        if (isset($_POST['lecturer_id']) || isset($_POST['lecturer_name'])) {
            $updates[] = "lecturer_id = ?";
            $params[] = $lecturerId;
        }
        if (isset($_POST['venue_id']) || isset($_POST['venue_name'])) {
            $updates[] = "venue_id = ?";
            $params[] = $venueId;
        }
        if (isset($_POST['day_of_week'])) {
            $updates[] = "$dayColumn = ?";
            $params[] = $dayOfWeek;
        }
        if (isset($_POST['start_time'])) {
            $updates[] = "start_time = ?";
            $params[] = $startTime;
        }
        if (isset($_POST['end_time'])) {
            $updates[] = "end_time = ?";
            $params[] = $endTime;
        }
        
        if (empty($updates)) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No fields to update']);
            exit;
        }
        
        $params[] = $sessionId;
        $sql = "UPDATE sessions SET " . implode(', ', $updates) . " WHERE session_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        logActivity('session_updated', "Session ID: {$sessionId}", getCurrentUserId());
        header('Content-Type: application/json');
        
        // Return the updated value for the field that was changed
        $updatedValue = null;
        if (isset($_POST['day_of_week'])) {
            $updatedValue = $_POST['day_of_week'];
        } elseif (isset($_POST['start_time'])) {
            $updatedValue = substr($_POST['start_time'], 0, 5); // Return HH:MM format
        } elseif (isset($_POST['end_time'])) {
            $updatedValue = substr($_POST['end_time'], 0, 5); // Return HH:MM format
        } elseif (isset($_POST['lecturer_name']) || isset($_POST['lecturer_id'])) {
            // Fetch the lecturer name
            if (isset($_POST['lecturer_id']) && $_POST['lecturer_id']) {
                $stmt = $pdo->prepare("SELECT lecturer_name FROM lecturers WHERE lecturer_id = ?");
                $stmt->execute([$_POST['lecturer_id']]);
                $lecturer = $stmt->fetch();
                $updatedValue = $lecturer ? $lecturer['lecturer_name'] : ($_POST['lecturer_name'] ?? '');
            } else {
                $updatedValue = $_POST['lecturer_name'] ?? '';
            }
        } elseif (isset($_POST['venue_name']) || isset($_POST['venue_id'])) {
            // Fetch the venue name
            if (isset($_POST['venue_id']) && $_POST['venue_id']) {
                $stmt = $pdo->prepare("SELECT venue_name FROM venues WHERE venue_id = ?");
                $stmt->execute([$_POST['venue_id']]);
                $venue = $stmt->fetch();
                $updatedValue = $venue ? $venue['venue_name'] : ($_POST['venue_name'] ?? '');
            } else {
                $updatedValue = $_POST['venue_name'] ?? '';
            }
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Session updated successfully',
            'updated_value' => $updatedValue
        ]);
        exit;
    } catch (Exception $e) {
        logError($e, 'Updating session in timetable editor');
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => getErrorMessage($e, 'Updating session', false)]);
        exit;
    }
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
} catch (Exception $e) {
    // Columns might already exist, ignore error
    logError($e, 'Setting up sessions table columns in timetable editor');
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

// Define days of week for inline editing
$daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

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
<?php
$breadcrumbs = [
    ['label' => 'Dashboard', 'href' => 'admin/index.php'],
    ['label' => 'Edit Sessions', 'href' => null],
];
$page_actions = [
    ['label' => 'Upload Timetable', 'href' => 'timetable_pdf_parser.php'],
    ['label' => 'View Timetable', 'href' => 'view_timetable.php'],
];
include 'admin/header_modern.php';
?>
            
            <!-- Filters -->
            <div class="filters" style="display:flex; flex-wrap:wrap; gap:16px; align-items:flex-end; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.10); border-radius: 24px; padding: 24px; backdrop-filter: blur(14px); box-shadow: 0 4px 20px rgba(0,0,0,0.45); margin-bottom: 20px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                <div class="filter-group" style="min-width:260px;">
                    <label style="font-weight:600; color: rgba(220,230,255,0.8); font-size: 12px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Programme</label>
                    <select name="programme" id="programmeFilter" style="width:100%; padding:10px 16px; background: rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.12); border-radius: 10px; color: #e8edff; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
                        <option value="">All Programmes</option>
                        <?php foreach ($programmes as $programme): ?>
                            <option value="<?= htmlspecialchars($programme) ?>" <?= $programmeFilter === $programme ? 'selected' : '' ?>>
                                <?= htmlspecialchars($programme) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group" style="min-width:180px;">
                    <label style="font-weight:600; color: rgba(220,230,255,0.8); font-size: 12px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Year Level <span style="color: rgba(220,230,255,0.65); font-weight: 500; text-transform: none; letter-spacing: 0;">(required for exact timetable)</span></label>
                    <select name="year" id="yearFilter" title="Pick a year to view a precise timetable for a programme" style="width:100%; padding:10px 16px; background: rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.12); border-radius: 10px; color: #e8edff; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
                        <option value="">All Years</option>
                        <?php 
                        // Get all unique years from all programmes
                        $allYears = [];
                        foreach ($filterData as $prog => $years) {
                            foreach (array_keys($years) as $year) {
                                if (!in_array($year, $allYears)) {
                                    $allYears[] = $year;
                                }
                            }
                        }
                        sort($allYears);
                        foreach ($allYears as $year): ?>
                            <option value="<?= htmlspecialchars($year) ?>" <?= $yearFilter === $year ? 'selected' : '' ?>>
                                <?= htmlspecialchars($year) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group" style="min-width:180px;">
                    <label style="font-weight:600; color: rgba(220,230,255,0.8); font-size: 12px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Semester</label>
                    <select name="semester" id="semesterFilter" style="width:100%; padding:10px 16px; background: rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.12); border-radius: 10px; color: #e8edff; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
                        <option value="">All Semesters</option>
                        <?php 
                        // Get all unique semesters
                        $allSemesters = [];
                        foreach ($filterData as $prog => $years) {
                            foreach ($years as $year => $semesters) {
                                foreach ($semesters as $semester) {
                                    if (!in_array($semester, $allSemesters)) {
                                        $allSemesters[] = $semester;
                                    }
                                }
                            }
                        }
                        sort($allSemesters);
                        foreach ($allSemesters as $semester): ?>
                            <option value="<?= htmlspecialchars($semester) ?>" <?= $semesterFilter === $semester ? 'selected' : '' ?>>
                                <?= htmlspecialchars($semester) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group" style="flex: 2; min-width:280px;">
                    <label style="font-weight:600; color: rgba(220,230,255,0.8); font-size: 12px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Search</label>
                    <input type="text" id="searchFilter" data-table-search placeholder="Module, lecturer, venue..." value="<?= htmlspecialchars($searchFilter) ?>" style="width:100%; padding:10px 16px; background: rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.12); border-radius: 10px; color: #e8edff; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
                </div>
                <div class="filter-group" style="min-width:140px;">
                    <label style="font-weight:600; color: rgba(220,230,255,0.8); font-size: 12px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Rows</label>
                    <select data-page-size style="width:100%; padding:10px 16px; background: rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.12); border-radius: 10px; color: #e8edff; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
                        <option>10</option>
                        <option>25</option>
                        <option>50</option>
                        <option>All</option>
                    </select>
                </div>
                <div style="display:flex; gap:10px; margin-left:auto;">
                    <button type="button" class="btn-apply" onclick="applyFilters()" style="background: rgba(59, 130, 246, 0.25); color: #dce3ff; border: 1px solid rgba(59, 130, 246, 0.4); padding: 10px 20px; border-radius: 10px; font-size: 14px; font-weight: 600; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer;" onmouseover="this.style.background='rgba(59, 130, 246, 0.35)'; this.style.borderColor='rgba(59, 130, 246, 0.5)'" onmouseout="this.style.background='rgba(59, 130, 246, 0.25)'; this.style.borderColor='rgba(59, 130, 246, 0.4)'">Apply filters</button>
                        <button type="button" class="btn" style="background: rgba(59, 130, 246, 0.25); color: #dce3ff; border: 1px solid rgba(59, 130, 246, 0.4); padding: 10px 20px; border-radius: 10px; font-size: 14px; font-weight: 600; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);" onclick="openCreateSession()" onmouseover="this.style.background='rgba(59, 130, 246, 0.35)'; this.style.borderColor='rgba(59, 130, 246, 0.5)'" onmouseout="this.style.background='rgba(59, 130, 246, 0.25)'; this.style.borderColor='rgba(59, 130, 246, 0.4)'">New Session</button>
                </div>
            </div>
            
            <!-- Create Session Modal -->
            <div id="createSessionModal" class="modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); backdrop-filter: blur(8px); z-index:60; align-items:center; justify-content:center;">
                <div class="card" style="width:100%; max-width:720px; padding:32px; border:1px solid rgba(255,255,255,0.10); background: rgba(255, 255, 255, 0.05); border-radius: 24px; backdrop-filter: blur(20px); box-shadow: 0 8px 32px rgba(0,0,0,0.6); font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                        <h3 class="text-lg" style="color: #e8edff; font-size: 20px; font-weight:700; letter-spacing: -0.2px; display: flex; align-items: center; gap: 10px;">
                            <svg viewBox="0 0 24 24" style="width: 20px; height: 20px; color: #dce3ff;" fill="none" stroke="currentColor" stroke-width="1.7">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            Create Session
                        </h3>
                        <button type="button" class="btn btn-cancel" onclick="closeCreateSession()" style="background: rgba(255,255,255,0.05); color: rgba(220,230,255,0.8); border: 1px solid rgba(255,255,255,0.12); padding: 10px 20px; border-radius: 10px; font-size: 14px; font-weight: 600; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);" onmouseover="this.style.background='rgba(255,255,255,0.08)'; this.style.borderColor='rgba(255,255,255,0.18)'" onmouseout="this.style.background='rgba(255,255,255,0.05)'; this.style.borderColor='rgba(255,255,255,0.12)'">Close</button>
                    </div>
                    <form id="createSessionForm" class="grid" style="display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap:12px;">
                        <div class="form-group" style="grid-column: span 2;">
                            <label style="color: rgba(220,230,255,0.8); font-size: 13px; font-weight:600; margin-bottom: 8px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Module</label>
                            <select name="module_id" required style="width:100%; padding:10px 16px; background: rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.12); border-radius: 10px; color: #e8edff; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
                                <?php foreach ($modules as $m): ?>
                                    <option value="<?= $m['module_id'] ?>"><?= htmlspecialchars($m['module_code'] . ' — ' . $m['module_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label style="color: rgba(220,230,255,0.8); font-size: 13px; font-weight:600; margin-bottom: 8px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Lecturer</label>
                            <select name="lecturer_id" style="width:100%; padding:10px 16px; background: rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.12); border-radius: 10px; color: #e8edff; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
                                <option value="">Select…</option>
                                <?php foreach ($lecturers as $l): ?>
                                    <option value="<?= $l['lecturer_id'] ?>"><?= htmlspecialchars($l['lecturer_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="lecturer_name" placeholder="Or type new lecturer name" style="margin-top:8px; width:100%; padding:10px 16px; background: rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.12); border-radius: 10px; color: #e8edff; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
                        </div>
                        <div class="form-group">
                            <label style="color: rgba(220,230,255,0.8); font-size: 13px; font-weight:600; margin-bottom: 8px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Venue</label>
                            <select name="venue_id" style="width:100%; padding:10px 16px; background: rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.12); border-radius: 10px; color: #e8edff; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
                                <option value="">Select…</option>
                                <?php foreach ($venues as $v): ?>
                                    <option value="<?= $v['venue_id'] ?>"><?= htmlspecialchars($v['venue_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="venue_name" placeholder="Or type new venue" style="margin-top:8px; width:100%; padding:10px 16px; background: rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.12); border-radius: 10px; color: #e8edff; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
                        </div>
                        <div class="form-group">
                            <label style="color: rgba(220,230,255,0.8); font-size: 13px; font-weight:600; margin-bottom: 8px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Day</label>
                            <select name="day_of_week" required style="width:100%; padding:10px 16px; background: rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.12); border-radius: 10px; color: #e8edff; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
                                <?php foreach ($daysOfWeek as $d): ?>
                                    <option><?= $d ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label style="color: rgba(220,230,255,0.8); font-size: 13px; font-weight:600; margin-bottom: 8px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Start</label>
                            <input type="time" name="start_time" required style="width:100%; padding:10px 16px; background: rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.12); border-radius: 10px; color: #e8edff; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
                        </div>
                        <div class="form-group">
                            <label style="color: rgba(220,230,255,0.8); font-size: 13px; font-weight:600; margin-bottom: 8px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">End</label>
                            <input type="time" name="end_time" required style="width:100%; padding:10px 16px; background: rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.12); border-radius: 10px; color: #e8edff; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
                        </div>
                        <div class="form-group">
                            <label style="color: rgba(220,230,255,0.8); font-size: 13px; font-weight:600; margin-bottom: 8px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Programme</label>
                            <input type="text" name="programme" value="<?= htmlspecialchars($programmeFilter) ?>" placeholder="Optional" style="width:100%; padding:10px 16px; background: rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.12); border-radius: 10px; color: #e8edff; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
                        </div>
                        <div class="form-group">
                            <label style="color: rgba(220,230,255,0.8); font-size: 13px; font-weight:600; margin-bottom: 8px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Year Level</label>
                            <input type="text" name="year_level" value="<?= htmlspecialchars($yearFilter) ?>" placeholder="Optional" style="width:100%; padding:10px 16px; background: rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.12); border-radius: 10px; color: #e8edff; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
                        </div>
                        <div class="form-group">
                            <label style="color: rgba(220,230,255,0.8); font-size: 13px; font-weight:600; margin-bottom: 8px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Semester</label>
                            <input type="text" name="semester" value="<?= htmlspecialchars($semesterFilter) ?>" placeholder="Optional" style="width:100%; padding:10px 16px; background: rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.12); border-radius: 10px; color: #e8edff; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
                        </div>
                        <div style="grid-column: span 2; display:flex; justify-content:flex-end; gap:10px; margin-top:8px;">
                            <button type="button" class="btn btn-cancel" onclick="closeCreateSession()" style="background: rgba(255,255,255,0.05); color: rgba(220,230,255,0.8); border: 1px solid rgba(255,255,255,0.12); padding: 10px 20px; border-radius: 10px; font-size: 14px; font-weight: 600; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Cancel</button>
                            <button type="submit" class="btn" style="background: rgba(59, 130, 246, 0.25); color: #dce3ff; border: 1px solid rgba(59, 130, 246, 0.4); padding: 10px 20px; border-radius: 10px; font-size: 14px; font-weight: 600; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Create</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Delete Confirm Modal -->
            <div id="deleteConfirmModal" class="modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); backdrop-filter: blur(8px); z-index:60; align-items:center; justify-content:center;">
                <div class="card" style="width:100%; max-width:420px; padding:28px; border:1px solid rgba(255,255,255,0.10); background: rgba(255, 255, 255, 0.05); border-radius: 24px; backdrop-filter: blur(20px); box-shadow: 0 8px 32px rgba(0,0,0,0.6); font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                    <h3 class="text-lg" style="color: #e8edff; font-size: 20px; font-weight:700; letter-spacing: -0.2px; margin-bottom:12px;">Delete session?</h3>
                    <p style="color: rgba(220,230,255,0.75); margin-bottom:20px; font-size: 14px;">This action cannot be undone.</p>
                    <div style="display:flex; justify-content:flex-end; gap:10px;">
                        <button type="button" class="btn btn-cancel" onclick="closeDeleteConfirm()" style="background: rgba(255,255,255,0.05); color: rgba(220,230,255,0.8); border: 1px solid rgba(255,255,255,0.12); padding: 10px 20px; border-radius: 10px; font-size: 14px; font-weight: 600; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Cancel</button>
                        <button id="deleteConfirmActionBtn" type="button" class="btn" style="background:rgba(239, 68, 68, 0.25); color:#fca5a5; border: 1px solid rgba(239, 68, 68, 0.4); padding: 10px 20px; border-radius: 10px; font-size: 14px; font-weight: 600; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Delete</button>
                    </div>
                </div>
            </div>
            
            <!-- Bulk Actions -->
            <div class="content-card toolbar" style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.10); border-radius: 16px; padding: 16px 20px; backdrop-filter: blur(14px); box-shadow: 0 2px 8px rgba(0,0,0,0.2); font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                <label style="display: flex; align-items: center; gap: 10px; color: rgba(220,230,255,0.8); font-size: 14px; font-weight: 600; cursor: pointer;">
                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()" style="width: 18px; height: 18px; cursor: pointer;">
                    Select all sessions in view
                </label>
            </div>
            
            <!-- Sessions Table -->
            <div class="table-container card">
                <table id="editorTable" data-table class="table compact">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Module</th>
                            <th>Lecturer</th>
                            <th>Venue</th>
                            <th>Day</th>
                            <th>Start</th>
                            <th>End</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sessions)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 60px 40px; color: rgba(220,230,255,0.65); font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                                <div style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width: 48px; height: 48px; color: rgba(220,230,255,0.4); margin-bottom: 8px;">
                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                        <line x1="16" y1="2" x2="16" y2="6"></line>
                                        <line x1="8" y1="2" x2="8" y2="6"></line>
                                        <line x1="3" y1="10" x2="21" y2="10"></line>
                                    </svg>
                                    <div style="font-size: 15px; font-weight: 600; color: rgba(220,230,255,0.8);">No sessions found</div>
                                    <div style="font-size: 13px; color: rgba(220,230,255,0.65);">Upload a timetable file or add sessions manually.</div>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($sessions as $session): ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="session-checkbox" value="<?= $session['session_id'] ?>">
                            </td>
                            <td style="font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                                <div class="session-module" style="color: #e8edff; font-weight: 600; font-size: 14px; margin-bottom: 4px;"><?= htmlspecialchars($session['module_code'] ?? '-') ?></div>
                                <div class="session-details" style="color: rgba(220,230,255,0.65); font-size: 12px;"><?= htmlspecialchars($session['module_name'] ?? '') ?></div>
                            </td>
                            <td>
                                <span class="editable-field" 
                                      data-field="lecturer" 
                                      data-session-id="<?= $session['session_id'] ?>"
                                      data-lecturer-id="<?= $session['lecturer_id'] ?? '' ?>"
                                      data-current-value="<?= htmlspecialchars($session['lecturer_name'] ?? '-') ?>"
                                      style="cursor: pointer; padding: 6px 10px; border-radius: 8px; display: inline-block; min-width: 100px; color: rgba(220,230,255,0.9); font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.2s ease;"
                                      onmouseover="this.style.background='rgba(255,255,255,0.08)'; this.style.border='1px solid rgba(255,255,255,0.12)'"
                                      onmouseout="this.style.background='transparent'; this.style.border='none'">
                                    <?= htmlspecialchars($session['lecturer_name'] ?? '-') ?>
                                </span>
                            </td>
                            <td>
                                <span class="editable-field" 
                                      data-field="venue" 
                                      data-session-id="<?= $session['session_id'] ?>"
                                      data-venue-id="<?= $session['venue_id'] ?? '' ?>"
                                      data-current-value="<?= htmlspecialchars($session['venue_name'] ?? '-') ?>"
                                      style="cursor: pointer; padding: 6px 10px; border-radius: 8px; display: inline-block; min-width: 100px; color: rgba(220,230,255,0.9); font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.2s ease;"
                                      onmouseover="this.style.background='rgba(255,255,255,0.08)'; this.style.border='1px solid rgba(255,255,255,0.12)'"
                                      onmouseout="this.style.background='transparent'; this.style.border='none'">
                                    <?= htmlspecialchars($session['venue_name'] ?? '-') ?>
                                </span>
                            </td>
                            <td>
                                <span class="editable-field" 
                                      data-field="day" 
                                      data-session-id="<?= $session['session_id'] ?>"
                                      data-current-value="<?= htmlspecialchars($session['day_of_week']) ?>"
                                      style="cursor: pointer; padding: 6px 10px; border-radius: 8px; display: inline-block; min-width: 80px; color: rgba(220,230,255,0.9); font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.2s ease;"
                                      onmouseover="this.style.background='rgba(255,255,255,0.08)'; this.style.border='1px solid rgba(255,255,255,0.12)'"
                                      onmouseout="this.style.background='transparent'; this.style.border='none'">
                                    <?= htmlspecialchars($session['day_of_week']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="editable-field" 
                                      data-field="start_time" 
                                      data-session-id="<?= $session['session_id'] ?>"
                                      data-current-value="<?= htmlspecialchars(substr($session['start_time'], 0, 5)) ?>"
                                      style="cursor: pointer; padding: 6px 10px; border-radius: 8px; display: inline-block; min-width: 60px; color: rgba(220,230,255,0.9); font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.2s ease;"
                                      onmouseover="this.style.background='rgba(255,255,255,0.08)'; this.style.border='1px solid rgba(255,255,255,0.12)'"
                                      onmouseout="this.style.background='transparent'; this.style.border='none'">
                                    <?= htmlspecialchars(substr($session['start_time'], 0, 5)) ?>
                                </span>
                            </td>
                            <td>
                                <span class="editable-field" 
                                      data-field="end_time" 
                                      data-session-id="<?= $session['session_id'] ?>"
                                      data-current-value="<?= htmlspecialchars(substr($session['end_time'], 0, 5)) ?>"
                                      style="cursor: pointer; padding: 6px 10px; border-radius: 8px; display: inline-block; min-width: 60px; color: rgba(220,230,255,0.9); font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.2s ease;"
                                      onmouseover="this.style.background='rgba(255,255,255,0.08)'; this.style.border='1px solid rgba(255,255,255,0.12)'"
                                      onmouseout="this.style.background='transparent'; this.style.border='none'">
                                    <?= htmlspecialchars(substr($session['end_time'], 0, 5)) ?>
                                </span>
                            </td>
                            <td>
                                <a href="?delete=1&id=<?= $session['session_id'] ?>" class="icon-btn" title="Delete session" aria-label="Delete session" onclick="return openDeleteConfirm(event, <?= (int)$session['session_id'] ?>)">
                                    <svg viewBox="0 0 24 24"><path d="M3 6h18"></path><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path></svg>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="content-card" style="display:flex; justify-content:flex-end;">
                <div data-pagination></div>
            </div>
<?php /* footer moved to end to include modals before closing body */ ?>
    
    <!-- Modals removed as per request -->
    
    <script>
        // Non-blocking notification system
        function showNotification(message, type = 'info') {
            // Remove any existing notifications
            const existing = document.querySelector('.inline-notification');
            if (existing) {
                existing.remove();
            }
            
            const notification = document.createElement('div');
            notification.className = 'inline-notification';
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 16px 20px;
                border-radius: 12px;
                backdrop-filter: blur(14px);
                box-shadow: 0 4px 20px rgba(0,0,0,0.5);
                z-index: 10000;
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                font-size: 14px;
                font-weight: 500;
                max-width: 400px;
                display: flex;
                align-items: center;
                gap: 12px;
                animation: slideIn 0.3s ease-out;
            `;
            
            // Set colors based on type
            if (type === 'error') {
                notification.style.background = 'rgba(239, 68, 68, 0.15)';
                notification.style.border = '1px solid rgba(239, 68, 68, 0.3)';
                notification.style.color = '#fca5a5';
            } else if (type === 'success') {
                notification.style.background = 'rgba(16, 185, 129, 0.15)';
                notification.style.border = '1px solid rgba(16, 185, 129, 0.3)';
                notification.style.color = '#6ee7b7';
            } else {
                notification.style.background = 'rgba(59, 130, 246, 0.15)';
                notification.style.border = '1px solid rgba(59, 130, 246, 0.3)';
                notification.style.color = '#93c5fd';
            }
            
            // Add icon
            const icon = document.createElement('div');
            icon.innerHTML = type === 'error' ? '✕' : (type === 'success' ? '✓' : 'ℹ');
            icon.style.cssText = 'font-size: 18px; flex-shrink: 0;';
            notification.appendChild(icon);
            
            // Add message
            const messageEl = document.createElement('div');
            messageEl.textContent = message;
            messageEl.style.flex = '1';
            notification.appendChild(messageEl);
            
            // Add close button
            const closeBtn = document.createElement('button');
            closeBtn.innerHTML = '×';
            closeBtn.style.cssText = `
                background: transparent;
                border: none;
                color: inherit;
                font-size: 20px;
                cursor: pointer;
                padding: 0;
                width: 24px;
                height: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                opacity: 0.7;
                transition: opacity 0.2s;
            `;
            closeBtn.onmouseover = () => closeBtn.style.opacity = '1';
            closeBtn.onmouseout = () => closeBtn.style.opacity = '0.7';
            closeBtn.onclick = () => notification.remove();
            notification.appendChild(closeBtn);
            
            document.body.appendChild(notification);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.style.animation = 'slideOut 0.3s ease-in';
                    setTimeout(() => notification.remove(), 300);
                }
            }, 5000);
        }
        
        // Add CSS animations
        if (!document.getElementById('notification-styles')) {
            const style = document.createElement('style');
            style.id = 'notification-styles';
            style.textContent = `
                @keyframes slideIn {
                    from {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
                @keyframes slideOut {
                    from {
                        transform: translateX(0);
                        opacity: 1;
                    }
                    to {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
        }
        
        // Functions that need to be globally accessible (called from inline handlers)
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.session-checkbox');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
        }
        
        // Bulk modals removed
        
        function applyFilters() {
            const programme = document.getElementById('programmeFilter').value;
            const year = document.getElementById('yearFilter').value;
            const semester = document.getElementById('semesterFilter').value;
            const search = document.getElementById('searchFilter').value;
            
            // If a programme is selected but no year selected, ask for a year to get an exact timetable
            if (programme && !year) {
                const yearEl = document.getElementById('yearFilter');
                // Visual nudge
                yearEl.style.borderColor = '#e74c3c';
                yearEl.focus();
                // Subtle inline message (temporary)
                const oldTitle = yearEl.title;
                yearEl.title = 'Select a year to view the exact timetable for the selected programme';
                setTimeout(() => {
                    yearEl.style.borderColor = '';
                    yearEl.title = oldTitle || 'Pick a year to view a precise timetable for a programme';
                }, 1800);
                return; // stop until user picks a year
            }
            
            const params = new URLSearchParams();
            if (programme) params.append('programme', programme);
            if (year) params.append('year', year);
            if (semester) params.append('semester', semester);
            if (search) params.append('search', search);
            
            window.location.href = 'timetable_editor.php' + (params.toString() ? '?' + params.toString() : '');
        }
        
        // Create Session modal helpers
        function openCreateSession() {
            const modal = document.getElementById('createSessionModal');
            if (modal) modal.style.display = 'flex';
        }
        function closeCreateSession() {
            const modal = document.getElementById('createSessionModal');
            if (modal) modal.style.display = 'none';
        }
        
        // Delete confirm modal helpers
        let pendingDeleteUrl = null;
        function openDeleteConfirm(e, sessionId) {
            e.preventDefault();
            const modal = document.getElementById('deleteConfirmModal');
            pendingDeleteUrl = '?delete=1&id=' + encodeURIComponent(sessionId);
            if (modal) modal.style.display = 'flex';
            return false;
        }
        function closeDeleteConfirm() {
            const modal = document.getElementById('deleteConfirmModal');
            if (modal) modal.style.display = 'none';
            pendingDeleteUrl = null;
        }
        
        // Wait for DOM to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {
        // Hook delete confirm button
        const deleteBtn = document.getElementById('deleteConfirmActionBtn');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', function() {
                if (pendingDeleteUrl) {
                    const base = pendingDeleteUrl.indexOf('?') !== -1 ? pendingDeleteUrl + '&' : pendingDeleteUrl + '?';
                    window.location.href = base + 'notice=session_deleted';
                } else {
                    closeDeleteConfirm();
                }
            });
        }
        // ESC to close modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeCreateSession();
            }
        });
        
        // Create session form submit
        const createForm = document.getElementById('createSessionForm');
        if (createForm) {
            createForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(createForm);
                formData.append('create_session', '1');
                
                try {
                    const res = await fetch('timetable_editor.php', { method: 'POST', body: formData, cache: 'no-cache', headers: { 'Accept': 'application/json' } });
                    // If session expired and server redirects to login, navigate there
                    if (res.redirected || (res.url && res.url.indexOf('login.php') !== -1)) {
                        window.location.href = res.url;
                        return;
                    }
                    let data;
                    const contentType = res.headers.get('content-type') || '';
                    if (contentType.includes('application/json')) {
                        data = await res.json();
                    } else {
                        const text = await res.text();
                        throw new Error(text.slice(0, 400));
                    }
                    if (data && data.success) {
                        // Preserve filters
                        const urlParams = new URLSearchParams(window.location.search);
                        const base = 'timetable_editor.php' + (urlParams.toString() ? '?' + urlParams.toString() + '&' : '?');
                        window.location.href = base + 'notice=session_created';
                    } else {
                        showNotification('Failed to create session: ' + (data.error || 'Unknown error'), 'error');
                    }
                } catch (err) {
                    showNotification('Failed to create session: ' + err.message, 'error');
                }
            });
        }
        
        // Filter data from PHP
        <?php
        try {
            $filterDataJson = json_encode($filterData ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            if ($filterDataJson === false) {
                $filterDataJson = '{}';
            }
        } catch (Exception $e) {
            $filterDataJson = '{}';
        }
        ?>
        const filterData = <?= $filterDataJson ?>;
        
        const programmeSelect = document.getElementById('programmeFilter');
        const yearSelect = document.getElementById('yearFilter');
        const semesterSelect = document.getElementById('semesterFilter');
        
        // Function to populate year dropdown
        function populateYears(programme) {
            if (!yearSelect) return;
            
            // Clear existing options except "All Years"
            yearSelect.innerHTML = '<option value="">All Years</option>';
            
            if (programme && filterData[programme]) {
                const years = Object.keys(filterData[programme]).sort();
                years.forEach(year => {
                    const option = document.createElement('option');
                    option.value = year;
                    option.textContent = year;
                    yearSelect.appendChild(option);
                });
            }
        }
        
        // Function to populate semester dropdown
        function populateSemesters(programme, year) {
            if (!semesterSelect) return;
            
            // Clear existing options except "All Semesters"
            semesterSelect.innerHTML = '<option value="">All Semesters</option>';
            
            if (programme && year && filterData[programme] && filterData[programme][year]) {
                const semesters = filterData[programme][year].sort();
                semesters.forEach(semester => {
                    const option = document.createElement('option');
                    option.value = semester;
                    option.textContent = semester;
                    semesterSelect.appendChild(option);
                });
            }
        }
        
        // Initialize: If programme is already selected on page load, populate years
        if (programmeSelect && programmeSelect.value) {
            populateYears(programmeSelect.value);
            
            // If year is also selected, populate semesters
            if (yearSelect && yearSelect.value) {
                populateSemesters(programmeSelect.value, yearSelect.value);
            }
        }
        
        // Update year dropdown when programme changes and auto-apply filters
        if (programmeSelect) {
            programmeSelect.addEventListener('change', function() {
                const selectedProgramme = this.value;
                
                // If a programme is selected, filter years to show only relevant ones
                // Otherwise, keep all years visible
                if (selectedProgramme && filterData[selectedProgramme]) {
                    populateYears(selectedProgramme);
                } else {
                    // Show all years if no programme selected
                    if (yearSelect) {
                        yearSelect.innerHTML = '<option value="">All Years</option>';
                        // Get all unique years
                        const allYears = new Set();
                        Object.values(filterData).forEach(years => {
                            Object.keys(years).forEach(year => allYears.add(year));
                        });
                        Array.from(allYears).sort().forEach(year => {
                            const option = document.createElement('option');
                            option.value = year;
                            option.textContent = year;
                            yearSelect.appendChild(option);
                        });
                    }
                }
                
                // Clear semester when programme changes
                if (semesterSelect) {
                    semesterSelect.innerHTML = '<option value="">All Semesters</option>';
                    // Show all semesters if no programme/year selected
                    if (!selectedProgramme) {
                        const allSemesters = new Set();
                        Object.values(filterData).forEach(years => {
                            Object.values(years).forEach(semesters => {
                                semesters.forEach(sem => allSemesters.add(sem));
                            });
                        });
                        Array.from(allSemesters).sort().forEach(semester => {
                            const option = document.createElement('option');
                            option.value = semester;
                            option.textContent = semester;
                            semesterSelect.appendChild(option);
                        });
                    }
                }
                
                // Auto-apply filters when programme changes
                applyFilters();
            });
        }
        
        // Update semester dropdown when year changes and auto-apply filters
        if (yearSelect) {
            yearSelect.addEventListener('change', function() {
                const selectedProgramme = programmeSelect ? programmeSelect.value : '';
                const selectedYear = this.value;
                
                if (selectedProgramme && selectedYear) {
                    populateSemesters(selectedProgramme, selectedYear);
                } else if (selectedYear) {
                    // If year selected but no programme, show all semesters for that year
                    if (semesterSelect) {
                        semesterSelect.innerHTML = '<option value="">All Semesters</option>';
                        const allSemesters = new Set();
                        Object.values(filterData).forEach(years => {
                            if (years[selectedYear]) {
                                years[selectedYear].forEach(sem => allSemesters.add(sem));
                            }
                        });
                        Array.from(allSemesters).sort().forEach(semester => {
                            const option = document.createElement('option');
                            option.value = semester;
                            option.textContent = semester;
                            semesterSelect.appendChild(option);
                        });
                    }
                } else {
                    // Show all semesters if no year selected
                    if (semesterSelect) {
                        semesterSelect.innerHTML = '<option value="">All Semesters</option>';
                        const allSemesters = new Set();
                        Object.values(filterData).forEach(years => {
                            Object.values(years).forEach(semesters => {
                                semesters.forEach(sem => allSemesters.add(sem));
                            });
                        });
                        Array.from(allSemesters).sort().forEach(semester => {
                            const option = document.createElement('option');
                            option.value = semester;
                            option.textContent = semester;
                            semesterSelect.appendChild(option);
                        });
                    }
                }
                
                // Auto-apply filters when year changes
                applyFilters();
            });
        }
        
        // Auto-apply filters when semester changes
        if (semesterSelect) {
            semesterSelect.addEventListener('change', function() {
                applyFilters();
            });
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
        
        // Initialize compact table pagination/sort/search using shared UI helper
        if (typeof UI !== 'undefined') {
            try {
                UI.initTable(
                    document.getElementById('editorTable'),
                    {
                        searchSelector: '#searchFilter',
                        perPageSelector: '[data-page-size]',
                        paginationSelector: '[data-pagination]'
                    }
                );
            } catch (e) {
                console.warn('Table init warning:', e);
            }
        } else {
            // Lightweight client-side search fallback
            const table = document.getElementById('editorTable');
            const searchInput = document.getElementById('searchFilter');
            if (table && searchInput) {
                const rows = Array.from(table.querySelectorAll('tbody tr'));
                const filterRows = () => {
                    const q = searchInput.value.trim().toLowerCase();
                    rows.forEach(row => {
                        if (!q) {
                            row.style.display = '';
                            return;
                        }
                        const text = row.textContent.toLowerCase();
                        row.style.display = text.indexOf(q) !== -1 ? '' : 'none';
                    });
                };
                searchInput.addEventListener('input', filterRows);
            }
        }
        // Shortcut: press '/' to focus search
        document.addEventListener('keydown', function(e) {
            if (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
                e.preventDefault();
                const input = document.getElementById('searchFilter');
                if (input) input.focus();
            }
        });
        
        // Handle form submission for bulk operations
        // Removed bulk form listeners (no longer present)
        
        // Inline editing functionality
        const daysOfWeek = <?= json_encode($daysOfWeek ?? ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const lecturersList = <?= json_encode(array_column($lecturers ?? [], 'lecturer_name', 'lecturer_id'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const venuesList = <?= json_encode(array_column($venues ?? [], 'venue_name', 'venue_id'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        
        // Make sure editable fields are clickable
        const editableFields = document.querySelectorAll('.editable-field');
        if (editableFields.length > 0) {
            console.log('Found', editableFields.length, 'editable fields');
        }
        
        editableFields.forEach(field => {
            field.addEventListener('click', function(e) {
                e.stopPropagation(); // Prevent event bubbling
                console.log('Editable field clicked:', this.dataset.field);
                const fieldType = this.dataset.field;
                const sessionId = this.dataset.sessionId;
                const currentValue = this.dataset.currentValue;
                const originalHTML = this.innerHTML;
                const originalStyle = this.getAttribute('style') || '';
                
                // Store original style for restoration
                this.setAttribute('data-original-style', originalStyle);
                
                // Create input based on field type
                let input;
                if (fieldType === 'lecturer') {
                    // Use a text input with datalist so user can pick existing or type new
                    input = document.createElement('input');
                    input.type = 'text';
                    input.value = currentValue !== '-' ? currentValue : '';
                    input.setAttribute('list', 'lecturers-datalist');
                    input.style.cssText = 'padding: 8px 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); border-radius: 8px; color: #e8edff; width: 100%; font-size: 13px; font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);';
                    // Build datalist if not present
                    if (!document.getElementById('lecturers-datalist')) {
                        const dl = document.createElement('datalist');
                        dl.id = 'lecturers-datalist';
                        Object.entries(lecturersList).forEach(([id, name]) => {
                            const option = document.createElement('option');
                            option.value = name;
                            dl.appendChild(option);
                        });
                        document.body.appendChild(dl);
                    }
                } else if (fieldType === 'venue') {
                    // Use a text input with datalist so user can pick existing or type new
                    input = document.createElement('input');
                    input.type = 'text';
                    input.value = currentValue !== '-' ? currentValue : '';
                    input.setAttribute('list', 'venues-datalist');
                    input.style.cssText = 'padding: 8px 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); border-radius: 8px; color: #e8edff; width: 100%; font-size: 13px; font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);';
                    // Build datalist if not present
                    if (!document.getElementById('venues-datalist')) {
                        const dl = document.createElement('datalist');
                        dl.id = 'venues-datalist';
                        Object.entries(venuesList).forEach(([id, name]) => {
                            const option = document.createElement('option');
                            option.value = name;
                            dl.appendChild(option);
                        });
                        document.body.appendChild(dl);
                    }
                } else if (fieldType === 'day') {
                    input = document.createElement('select');
                    input.style.cssText = 'padding: 8px 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); border-radius: 8px; color: #e8edff; width: 100%; font-size: 13px; font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);';
                    daysOfWeek.forEach(day => {
                        const option = document.createElement('option');
                        option.value = day;
                        option.textContent = day;
                        if (day === currentValue) option.selected = true;
                        input.appendChild(option);
                    });
                } else if (fieldType === 'start_time' || fieldType === 'end_time') {
                    input = document.createElement('input');
                    input.type = 'time';
                    input.value = currentValue;
                    input.style.cssText = 'padding: 8px 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); border-radius: 8px; color: #e8edff; width: 100%; font-size: 13px; font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);';
                }
                
                // Store reference to field for escape handler
                const fieldElement = this;
                
                // Replace span with input
                fieldElement.innerHTML = '';
                fieldElement.appendChild(input);
                
                // Prevent immediate blur when clicking on select/input
                let isSaving = false;
                const initialValue = currentValue; // used to detect real changes for selects
                
                // Safety timeout: if field is stuck in editing state for more than 30 seconds, reset it
                const safetyTimeout = setTimeout(() => {
                    if (fieldElement.querySelector('input, select')) {
                        console.warn('Field editing timeout - resetting field');
                        isSaving = false;
                        fieldElement.innerHTML = originalHTML;
                        fieldElement.className = 'editable-field';
                        const originalStyle = fieldElement.getAttribute('data-original-style') || '';
                        if (originalStyle) {
                            fieldElement.setAttribute('style', originalStyle);
                        }
                        fieldElement.removeAttribute('data-original-style');
                    }
                }, 30000);
                
                // Clear safety timeout when field is successfully saved or cancelled
                const clearSafetyTimeout = () => {
                    if (safetyTimeout) clearTimeout(safetyTimeout);
                };
                
                // Focus after a small delay to ensure it's clickable
                setTimeout(() => {
                    input.focus();
                    // Do not auto-open selects; it can cause unintended selection of first option
                }, 10);
                
                // Handle save on blur or Enter
                const saveField = () => {
                    if (isSaving) return;
                    isSaving = true;
                    let value = input.value;
                    let lecturerId = fieldElement.dataset.lecturerId || '';
                    let venueId = fieldElement.dataset.venueId || '';
                    let lecturerName = '';
                    let venueName = '';
                    
                    if (fieldType === 'lecturer') {
                        // If the typed value matches an existing lecturer name, use the ID; otherwise create new by name
                        const matchId = Object.entries(lecturersList).find(([id, name]) => name === value)?.[0] || '';
                        if (matchId) {
                            lecturerId = matchId;
                        } else if (value && value !== '-') {
                            lecturerName = value.trim();
                            lecturerId = '';
                        } else {
                            lecturerId = '';
                        }
                    } else if (fieldType === 'venue') {
                        const matchId = Object.entries(venuesList).find(([id, name]) => name === value)?.[0] || '';
                        if (matchId) {
                            venueId = matchId;
                        } else if (value && value !== '-') {
                            venueName = value.trim();
                            venueId = '';
                        } else {
                            venueId = '';
                        }
                    }
                    
                    // Save via AJAX
                    const formData = new FormData();
                    formData.append('update_session_inline', '1');
                    formData.append('session_id', sessionId);
                    if (fieldType === 'lecturer') {
                        if (lecturerId) formData.append('lecturer_id', lecturerId);
                        if (lecturerName) formData.append('lecturer_name', lecturerName);
                    } else if (fieldType === 'venue') {
                        if (venueId) formData.append('venue_id', venueId);
                        if (venueName) formData.append('venue_name', venueName);
                    } else if (fieldType === 'day') {
                        // Validate against allowed days to prevent accidental defaulting
                        const validDays = new Set(daysOfWeek);
                        let v = (value || '').toString().trim();
                        if (!validDays.has(v)) {
                            const candidate = daysOfWeek.find(d => d.toLowerCase() === v.toLowerCase());
                            v = candidate || v;
                        }
                        formData.append('day_of_week', v);
                    } else if (fieldType === 'start_time') {
                        formData.append('start_time', value + ':00');
                    } else if (fieldType === 'end_time') {
                        formData.append('end_time', value + ':00');
                    }
                    
                    fetch('timetable_editor.php', {
                        method: 'POST',
                        body: formData,
                        cache: 'no-cache'
                    })
                    .then(async (response) => {
                        if (!response.ok) {
                            const text = await response.text().catch(() => '');
                            throw new Error(text || 'Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        clearSafetyTimeout(); // Clear safety timeout on success
                        if (data.success) {
                            // Update the field in place without reloading the page
                            const newValue = data.updated_value || value;
                            isSaving = false;
                            
                            // Restore the field with the new value
                            fieldElement.innerHTML = newValue;
                            fieldElement.setAttribute('data-current-value', newValue);
                            
                            // Remove the input/select and restore the span styling
                            fieldElement.className = 'editable-field';
                            const originalStyle = fieldElement.getAttribute('data-original-style') || '';
                            
                            // Restore original style and attributes
                            if (originalStyle) {
                                fieldElement.setAttribute('style', originalStyle);
                            }
                            
                            // Restore hover handlers based on field type
                            const minWidth = fieldType === 'day' ? '80px' : (fieldType === 'start_time' || fieldType === 'end_time' ? '60px' : '100px');
                            fieldElement.setAttribute('onmouseover', "this.style.background='rgba(255,255,255,0.08)'; this.style.border='1px solid rgba(255,255,255,0.12)'");
                            fieldElement.setAttribute('onmouseout', "this.style.background='transparent'; this.style.border='none'");
                            
                            // Remove the data-original-style attribute
                            fieldElement.removeAttribute('data-original-style');
                            
                            // The click handler is already attached via event delegation, so no need to re-attach
                        } else {
                            // Reset state first before showing error
                            isSaving = false;
                            fieldElement.innerHTML = originalHTML;
                            fieldElement.className = 'editable-field';
                            const originalStyle = fieldElement.getAttribute('data-original-style') || '';
                            if (originalStyle) {
                                fieldElement.setAttribute('style', originalStyle);
                            }
                            fieldElement.removeAttribute('data-original-style');
                            
                            // Show non-blocking error notification
                            showNotification('Error updating field: ' + (data.error || 'Unknown error'), 'error');
                        }
                    })
                    .catch(error => {
                        clearSafetyTimeout(); // Clear safety timeout on error
                        console.error('Error:', error);
                        // Reset state first before showing error
                        isSaving = false;
                        fieldElement.innerHTML = originalHTML;
                        fieldElement.className = 'editable-field';
                        const originalStyle = fieldElement.getAttribute('data-original-style') || '';
                        if (originalStyle) {
                            fieldElement.setAttribute('style', originalStyle);
                        }
                        fieldElement.removeAttribute('data-original-style');
                        
                        // Show non-blocking error notification
                        showNotification('Error updating field: ' + error.message, 'error');
                    });
                };
                
                // For select elements, support change and blur saves
                if (input.tagName === 'SELECT') {
                    // Only save when the user actually picks a different value
                    input.addEventListener('change', function(e) {
                        e.stopPropagation();
                        const newVal = input.value;
                        if (!isSaving && newVal !== initialValue) {
                            saveField();
                        }
                    });
                    // Also save on blur if changed
                    input.addEventListener('blur', function() {
                        setTimeout(() => {
                            if (!isSaving && input.value !== initialValue) {
                                saveField();
                            }
                        }, 150);
                    });
                    // Add a save button for selects
                    const saveBtn = document.createElement('button');
                    saveBtn.textContent = '✓ Save';
                    saveBtn.style.cssText = 'margin-left: 4px; padding: 4px 12px; background: #667eea; border: none; border-radius: 4px; color: white; cursor: pointer; font-size: 12px; font-weight: 600;';
                    saveBtn.onclick = function(e) {
                        e.stopPropagation();
                        e.preventDefault();
                        if (!isSaving) {
                            saveField();
                        }
                        return false;
                    };
                    fieldElement.appendChild(saveBtn);
                    
                    // Add cancel button
                    const cancelBtn = document.createElement('button');
                    cancelBtn.textContent = '✕';
                    cancelBtn.style.cssText = 'margin-left: 8px; padding: 6px 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); border-radius: 8px; color: rgba(220,230,255,0.8); cursor: pointer; font-size: 12px; font-weight: 600; font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);';
                    cancelBtn.onclick = function(e) {
                        e.stopPropagation();
                        e.preventDefault();
                        clearSafetyTimeout(); // Clear safety timeout when cancelled
                        isSaving = false;
                        fieldElement.innerHTML = originalHTML;
                        fieldElement.className = 'editable-field';
                        const originalStyle = fieldElement.getAttribute('data-original-style') || '';
                        if (originalStyle) {
                            fieldElement.setAttribute('style', originalStyle);
                        }
                        fieldElement.removeAttribute('data-original-style');
                        return false;
                    };
                    fieldElement.appendChild(cancelBtn);
                    
                    // Allow Enter key to save
                    input.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            if (!isSaving) {
                                saveField();
                            }
                        } else if (e.key === 'Escape') {
                            clearSafetyTimeout(); // Clear safety timeout when escaped
                            isSaving = false;
                            fieldElement.innerHTML = originalHTML;
                            fieldElement.className = 'editable-field';
                            const originalStyle = fieldElement.getAttribute('data-original-style') || '';
                            if (originalStyle) {
                                fieldElement.setAttribute('style', originalStyle);
                            }
                            fieldElement.removeAttribute('data-original-style');
                        }
                    });
                } else {
                    // For input elements, show Save/Cancel and also support blur/Enter
                    const saveBtn = document.createElement('button');
                    saveBtn.textContent = '✓ Save';
                    saveBtn.style.cssText = 'margin-left: 4px; padding: 4px 12px; background: #667eea; border: none; border-radius: 4px; color: white; cursor: pointer; font-size: 12px; font-weight: 600;';
                    saveBtn.onclick = function(e) {
                        e.stopPropagation();
                        e.preventDefault();
                        if (!isSaving) {
                            saveField();
                        }
                        return false;
                    };
                    fieldElement.appendChild(saveBtn);
                    
                    const cancelBtn = document.createElement('button');
                    cancelBtn.textContent = '✕';
                    cancelBtn.style.cssText = 'margin-left: 8px; padding: 6px 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); border-radius: 8px; color: rgba(220,230,255,0.8); cursor: pointer; font-size: 12px; font-weight: 600; font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);';
                    cancelBtn.onclick = function(e) {
                        e.stopPropagation();
                        e.preventDefault();
                        clearSafetyTimeout(); // Clear safety timeout when cancelled
                        isSaving = false;
                        fieldElement.innerHTML = originalHTML;
                        fieldElement.className = 'editable-field';
                        const originalStyle = fieldElement.getAttribute('data-original-style') || '';
                        if (originalStyle) {
                            fieldElement.setAttribute('style', originalStyle);
                        }
                        fieldElement.removeAttribute('data-original-style');
                        return false;
                    };
                    fieldElement.appendChild(cancelBtn);
                    
                    // For select elements, use 'change' event to save immediately when selection is made
                    if (input.tagName === 'SELECT') {
                        let selectBlurTimeout = null;
                        input.addEventListener('change', function() {
                            // Clear any pending blur timeout
                            if (selectBlurTimeout) {
                                clearTimeout(selectBlurTimeout);
                                selectBlurTimeout = null;
                            }
                            // Save immediately when selection changes
                            if (!isSaving) {
                                saveField();
                            }
                        });
                        // Handle blur with delay - only if no change occurred
                        input.addEventListener('blur', function() {
                            selectBlurTimeout = setTimeout(() => {
                                // Only restore if value didn't change (user clicked away without selecting)
                                if (!isSaving && input.value === initialValue) {
                                    clearSafetyTimeout(); // Clear safety timeout
                                    fieldElement.innerHTML = originalHTML;
                                    fieldElement.className = 'editable-field';
                                    const originalStyle = fieldElement.getAttribute('data-original-style') || '';
                                    if (originalStyle) {
                                        fieldElement.setAttribute('style', originalStyle);
                                    }
                                    fieldElement.removeAttribute('data-original-style');
                                }
                            }, 200);
                        });
                    } else {
                        // For text inputs and time inputs, use blur
                        input.addEventListener('blur', function() {
                            setTimeout(() => {
                                if (!isSaving) {
                                    saveField();
                                }
                            }, 200);
                        });
                    }
                    
                    input.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            if (!isSaving) {
                                saveField();
                            }
                        } else if (e.key === 'Escape') {
                            clearSafetyTimeout(); // Clear safety timeout when escaped
                            isSaving = false;
                            fieldElement.innerHTML = originalHTML;
                            fieldElement.className = 'editable-field';
                            const originalStyle = fieldElement.getAttribute('data-original-style') || '';
                            if (originalStyle) {
                                fieldElement.setAttribute('style', originalStyle);
                            }
                            fieldElement.removeAttribute('data-original-style');
                        }
                    });
                }
                
                // Prevent blur when clicking inside the input/select
                input.addEventListener('mousedown', function(e) {
                    e.stopPropagation();
                });
                
                // Prevent the field click event from bubbling
                fieldElement.addEventListener('click', function(e) {
                    e.stopPropagation();
                }, true);
            });
        });
        
        }); // End DOMContentLoaded
    </script>
<?php include 'admin/footer_modern.php'; ?>

