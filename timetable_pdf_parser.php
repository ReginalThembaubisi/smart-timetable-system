<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin/login.php');
    exit;
}

require_once 'admin/config.php';

$pdo = new PDO("mysql:host=localhost;dbname=smart_timetable", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$message = '';
$messageType = '';
$parsingResults = null;
$previewData = null;
$confirmed = false;

// Handle confirmation and save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_parse'])) {
    $previewDataJson = $_POST['preview_data'];
    $previewData = json_decode($previewDataJson, true);
    
    if ($previewData) {
        $results = saveParsedData($previewData, $pdo);
        $parsingResults = $results;
        $message = "Successfully parsed {$results['total_sessions']} sessions using SESSION format!";
        $messageType = 'success';
        $confirmed = true;
        $previewData = null; // Clear preview after saving
    }
}

// Handle file upload and parsing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['timetable_file']) && !isset($_POST['confirm_parse'])) {
    $file = $_FILES['timetable_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $fileName = $file['name'];
        $fileTmp = $file['tmp_name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if (in_array($fileExt, ['txt', 'pdf'])) {
            $content = '';
            
            if ($fileExt === 'txt') {
                $content = file_get_contents($fileTmp);
            } else {
                // For PDF, we'd need a PDF parser library
                // For now, we'll handle TXT files
                $message = 'PDF parsing not yet implemented. Please upload a TXT file.';
                $messageType = 'error';
            }
            
            if ($content) {
                // Parse and preview only - don't save yet
                $previewData = parseTimetableFilePreview($content);
                $message = "Found {$previewData['total_sessions']} sessions. Review and confirm to save.";
                $messageType = 'info';
            }
        } else {
            $message = 'Invalid file type. Please upload a TXT or PDF file.';
            $messageType = 'error';
        }
    } else {
        $message = 'Error uploading file.';
        $messageType = 'error';
    }
}

function parseTimetableFilePreview($content) {
    // Parse and return preview data without saving
    $lines = explode("\n", $content);
    $sessions = [];
    $currentProgramme = '';
    $currentYear = '';
    $currentSemester = '';
    
    $modulesFound = [];
    $lecturersFound = [];
    $venuesFound = [];
    
    $currentSession = null;
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        if (empty($line)) continue;
        
        // Parse header info
        if (preg_match('/PROGRAMME:\s*(.+)/i', $line, $matches)) {
            $currentProgramme = trim($matches[1]);
        } elseif (preg_match('/YEAR LEVEL:\s*Year\s*(\d+)/i', $line, $matches)) {
            $currentYear = trim($matches[1]);
        } elseif (preg_match('/SEMESTER:\s*(.+)/i', $line, $matches)) {
            $currentSemester = trim($matches[1]);
        }
        
        // Parse session
        if (preg_match('/SESSION\s*\d+:/i', $line)) {
            if ($currentSession && !empty($currentSession['module']) && !empty($currentSession['day'])) {
                $sessions[] = $currentSession;
                
                // Track unique items
                if (!empty($currentSession['module'])) {
                    $modulesFound[$currentSession['module']] = true;
                }
                if (!empty($currentSession['staff'])) {
                    $lecturersFound[$currentSession['staff']] = true;
                }
                if (!empty($currentSession['room_name'])) {
                    $venuesFound[$currentSession['room_name']] = true;
                }
            }
            $currentSession = [
                'programme' => $currentProgramme,
                'year' => $currentYear,
                'semester' => $currentSemester,
            ];
        } elseif ($currentSession) {
            if (preg_match('/Day:\s*(.+)/i', $line, $matches)) {
                $currentSession['day'] = trim($matches[1]);
            } elseif (preg_match('/Time:\s*(\d{2}:\d{2})-(\d{2}:\d{2})/i', $line, $matches)) {
                $currentSession['start_time'] = $matches[1] . ':00';
                $currentSession['end_time'] = $matches[2] . ':00';
            } elseif (preg_match('/Module:\s*(.+)/i', $line, $matches)) {
                $moduleCode = trim($matches[1]);
                if (!empty($moduleCode)) {
                    $currentSession['module'] = $moduleCode;
                }
            } elseif (preg_match('/Staff:\s*(.+)/i', $line, $matches)) {
                $currentSession['staff'] = trim($matches[1]);
            } elseif (preg_match('/Room:\s*(.+)/i', $line, $matches)) {
                $roomInfo = trim($matches[1]);
                // Extract room code and name
                if (preg_match('/^([^\s\(]+)\s*\((.+)\)/', $roomInfo, $roomMatches)) {
                    $currentSession['room_code'] = $roomMatches[1];
                    $currentSession['room_name'] = $roomMatches[2];
                } else {
                    $currentSession['room_name'] = $roomInfo;
                }
            }
        }
    }
    
    // Add last session
    if ($currentSession && !empty($currentSession['module']) && !empty($currentSession['day'])) {
        $sessions[] = $currentSession;
        if (!empty($currentSession['module'])) {
            $modulesFound[$currentSession['module']] = true;
        }
        if (!empty($currentSession['staff'])) {
            $lecturersFound[$currentSession['staff']] = true;
        }
        if (!empty($currentSession['room_name'])) {
            $venuesFound[$currentSession['room_name']] = true;
        }
    }
    
    return [
        'sessions' => $sessions,
        'total_sessions' => count($sessions),
        'programmes' => count(array_unique(array_column($sessions, 'programme'))),
        'modules_count' => count($modulesFound),
        'lecturers_count' => count($lecturersFound),
        'venues_count' => count($venuesFound),
        'modules' => array_keys($modulesFound),
        'lecturers' => array_keys($lecturersFound),
        'venues' => array_keys($venuesFound),
    ];
}

function saveParsedData($previewData, $pdo) {
    // Save the parsed data to database
    $modulesCreated = [];
    $lecturersCreated = [];
    $venuesCreated = [];
    
    $createdCount = 0;
    $skippedCount = 0;
    
    foreach ($previewData['sessions'] as $session) {
        if (empty($session['module']) || empty($session['day']) || empty($session['start_time'])) {
            $skippedCount++;
            continue;
        }
        
        // Get or create module
        $moduleCode = $session['module'];
        $moduleId = null;
        
        if (!isset($modulesCreated[$moduleCode])) {
            $stmt = $pdo->prepare("SELECT module_id FROM modules WHERE module_code = ?");
            $stmt->execute([$moduleCode]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $moduleId = $existing['module_id'];
            } else {
                // Create module
                $stmt = $pdo->prepare("INSERT INTO modules (module_code, module_name, credits) VALUES (?, ?, ?)");
                $stmt->execute([$moduleCode, $moduleCode, 0]);
                $moduleId = $pdo->lastInsertId();
            }
            $modulesCreated[$moduleCode] = $moduleId;
        } else {
            $moduleId = $modulesCreated[$moduleCode];
        }
        
        // Get or create lecturer
        $lecturerName = $session['staff'] ?? '';
        $lecturerId = null;
        
        if (!empty($lecturerName)) {
            if (!isset($lecturersCreated[$lecturerName])) {
                $stmt = $pdo->prepare("SELECT lecturer_id FROM lecturers WHERE lecturer_name = ?");
                $stmt->execute([$lecturerName]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    $lecturerId = $existing['lecturer_id'];
                } else {
                    $stmt = $pdo->prepare("INSERT INTO lecturers (lecturer_name, email) VALUES (?, ?)");
                    $stmt->execute([$lecturerName, '']);
                    $lecturerId = $pdo->lastInsertId();
                }
                $lecturersCreated[$lecturerName] = $lecturerId;
            } else {
                $lecturerId = $lecturersCreated[$lecturerName];
            }
        }
        
        // Get or create venue
        $venueName = $session['room_name'] ?? $session['room_code'] ?? '';
        $venueId = null;
        
        if (!empty($venueName)) {
            if (!isset($venuesCreated[$venueName])) {
                $stmt = $pdo->prepare("SELECT venue_id FROM venues WHERE venue_name = ?");
                $stmt->execute([$venueName]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    $venueId = $existing['venue_id'];
                } else {
                    $stmt = $pdo->prepare("INSERT INTO venues (venue_name, capacity) VALUES (?, ?)");
                    $stmt->execute([$venueName, 0]);
                    $venueId = $pdo->lastInsertId();
                }
                $venuesCreated[$venueName] = $venueId;
            } else {
                $venueId = $venuesCreated[$venueName];
            }
        }
        
        // Check if session already exists
        $stmt = $pdo->prepare("SELECT session_id FROM sessions WHERE module_id = ? AND day_of_week = ? AND start_time = ?");
        $stmt->execute([$moduleId, $session['day'], $session['start_time']]);
        $existing = $stmt->fetch();
        
        if (!$existing) {
            // Insert session
            $stmt = $pdo->prepare("INSERT INTO sessions (module_id, lecturer_id, venue_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $moduleId,
                $lecturerId,
                $venueId,
                $session['day'],
                $session['start_time'],
                $session['end_time']
            ]);
            $createdCount++;
        } else {
            $skippedCount++;
        }
    }
    
    return [
        'total_sessions' => $previewData['total_sessions'],
        'created' => $createdCount,
        'skipped' => $skippedCount,
        'programmes' => $previewData['programmes'],
        'modules' => count($modulesCreated),
        'lecturers' => count($lecturersCreated),
        'venues' => count($venuesCreated),
    ];
}

function parseTimetableFile($content, $pdo) {
    $lines = explode("\n", $content);
    $sessions = [];
    $currentProgramme = '';
    $currentYear = '';
    $currentSemester = '';
    
    $modulesCreated = [];
    $lecturersCreated = [];
    $venuesCreated = [];
    
    $sessionCount = 0;
    $createdCount = 0;
    $skippedCount = 0;
    
    $currentSession = null;
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        if (empty($line)) continue;
        
        // Parse header info
        if (preg_match('/PROGRAMME:\s*(.+)/i', $line, $matches)) {
            $currentProgramme = trim($matches[1]);
        } elseif (preg_match('/YEAR LEVEL:\s*Year\s*(\d+)/i', $line, $matches)) {
            $currentYear = trim($matches[1]);
        } elseif (preg_match('/SEMESTER:\s*(.+)/i', $line, $matches)) {
            $currentSemester = trim($matches[1]);
        }
        
        // Parse session
        if (preg_match('/SESSION\s*\d+:/i', $line)) {
            if ($currentSession) {
                $sessions[] = $currentSession;
            }
            $currentSession = [
                'programme' => $currentProgramme,
                'year' => $currentYear,
                'semester' => $currentSemester,
            ];
        } elseif ($currentSession) {
            if (preg_match('/Day:\s*(.+)/i', $line, $matches)) {
                $currentSession['day'] = trim($matches[1]);
            } elseif (preg_match('/Time:\s*(\d{2}:\d{2})-(\d{2}:\d{2})/i', $line, $matches)) {
                $currentSession['start_time'] = $matches[1] . ':00';
                $currentSession['end_time'] = $matches[2] . ':00';
            } elseif (preg_match('/Module:\s*(.+)/i', $line, $matches)) {
                $moduleCode = trim($matches[1]);
                if (!empty($moduleCode)) {
                    $currentSession['module'] = $moduleCode;
                }
            } elseif (preg_match('/Staff:\s*(.+)/i', $line, $matches)) {
                $currentSession['staff'] = trim($matches[1]);
            } elseif (preg_match('/Room:\s*(.+)/i', $line, $matches)) {
                $roomInfo = trim($matches[1]);
                // Extract room code and name
                if (preg_match('/^([^\s\(]+)\s*\((.+)\)/', $roomInfo, $roomMatches)) {
                    $currentSession['room_code'] = $roomMatches[1];
                    $currentSession['room_name'] = $roomMatches[2];
                } else {
                    $currentSession['room_name'] = $roomInfo;
                }
            }
        }
    }
    
    // Add last session
    if ($currentSession) {
        $sessions[] = $currentSession;
    }
    
    // Process sessions and insert into database
    foreach ($sessions as $session) {
        if (empty($session['module']) || empty($session['day']) || empty($session['start_time'])) {
            $skippedCount++;
            continue;
        }
        
        $sessionCount++;
        
        // Get or create module
        $moduleCode = $session['module'];
        $moduleId = null;
        
        if (!isset($modulesCreated[$moduleCode])) {
            $stmt = $pdo->prepare("SELECT module_id FROM modules WHERE module_code = ?");
            $stmt->execute([$moduleCode]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $moduleId = $existing['module_id'];
            } else {
                // Create module
                $stmt = $pdo->prepare("INSERT INTO modules (module_code, module_name, credits) VALUES (?, ?, ?)");
                $stmt->execute([$moduleCode, $moduleCode, 0]);
                $moduleId = $pdo->lastInsertId();
            }
            $modulesCreated[$moduleCode] = $moduleId;
        } else {
            $moduleId = $modulesCreated[$moduleCode];
        }
        
        // Get or create lecturer
        $lecturerName = $session['staff'] ?? '';
        $lecturerId = null;
        
        if (!empty($lecturerName)) {
            if (!isset($lecturersCreated[$lecturerName])) {
                $stmt = $pdo->prepare("SELECT lecturer_id FROM lecturers WHERE lecturer_name = ?");
                $stmt->execute([$lecturerName]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    $lecturerId = $existing['lecturer_id'];
                } else {
                    $stmt = $pdo->prepare("INSERT INTO lecturers (lecturer_name, email) VALUES (?, ?)");
                    $stmt->execute([$lecturerName, '']);
                    $lecturerId = $pdo->lastInsertId();
                }
                $lecturersCreated[$lecturerName] = $lecturerId;
            } else {
                $lecturerId = $lecturersCreated[$lecturerName];
            }
        }
        
        // Get or create venue
        $venueName = $session['room_name'] ?? $session['room_code'] ?? '';
        $venueId = null;
        
        if (!empty($venueName)) {
            if (!isset($venuesCreated[$venueName])) {
                $stmt = $pdo->prepare("SELECT venue_id FROM venues WHERE venue_name = ?");
                $stmt->execute([$venueName]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    $venueId = $existing['venue_id'];
                } else {
                    $stmt = $pdo->prepare("INSERT INTO venues (venue_name, capacity) VALUES (?, ?)");
                    $stmt->execute([$venueName, 0]);
                    $venueId = $pdo->lastInsertId();
                }
                $venuesCreated[$venueName] = $venueId;
            } else {
                $venueId = $venuesCreated[$venueName];
            }
        }
        
        // Check if session already exists
        $stmt = $pdo->prepare("SELECT session_id FROM sessions WHERE module_id = ? AND day_of_week = ? AND start_time = ?");
        $stmt->execute([$moduleId, $session['day'], $session['start_time']]);
        $existing = $stmt->fetch();
        
        if (!$existing) {
            // Insert session
            $stmt = $pdo->prepare("INSERT INTO sessions (module_id, lecturer_id, venue_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $moduleId,
                $lecturerId,
                $venueId,
                $session['day'],
                $session['start_time'],
                $session['end_time']
            ]);
            $createdCount++;
        } else {
            $skippedCount++;
        }
    }
    
    return [
        'total_sessions' => $sessionCount,
        'created' => $createdCount,
        'skipped' => $skippedCount,
        'programmes' => count(array_unique(array_column($sessions, 'programme'))),
        'modules' => count($modulesCreated),
        'lecturers' => count($lecturersCreated),
        'venues' => count($venuesCreated),
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timetable Parser - Smart Timetable</title>
    <link rel="stylesheet" href="admin/style.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #e0e0e0;
            min-height: 100vh;
        }
        .container { display: flex; min-height: 100vh; }
        
        /* Sidebar - Same as dashboard */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #0f1419 0%, #1a2332 100%);
            padding: 30px 20px;
            border-right: 1px solid rgba(255,255,255,0.1);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        .sidebar-header {
            margin-bottom: 40px;
        }
        .sidebar-header h1 {
            font-size: 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }
        .sidebar-section {
            margin-bottom: 30px;
        }
        .sidebar-section-title {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #7f8c8d;
            margin-bottom: 15px;
            padding: 0 15px;
        }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #b0b0b0;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 5px;
            transition: all 0.3s;
        }
        .sidebar-nav a:hover, .sidebar-nav a.active {
            background: rgba(102, 126, 234, 0.2);
            color: #667eea;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 40px;
        }
        
        .upload-card {
            background: linear-gradient(135deg, #1e2746 0%, #2a3a5a 100%);
            border-radius: 16px;
            padding: 40px;
            margin-bottom: 30px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .upload-card h2 {
            font-size: 24px;
            margin-bottom: 12px;
        }
        .upload-card p {
            color: #a0a0a0;
            margin-bottom: 25px;
            line-height: 1.6;
        }
        .tags {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
        }
        .tag {
            background: rgba(102, 126, 234, 0.2);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            color: #667eea;
        }
        .upload-area {
            border: 2px dashed rgba(102, 126, 234, 0.5);
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            background: rgba(102, 126, 234, 0.05);
            transition: all 0.3s;
        }
        .upload-area:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }
        .upload-area input[type="file"] {
            display: none;
        }
        .upload-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
        }
        .upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(102, 126, 234, 0.4);
        }
        
        .success-banner {
            background: #27ae60;
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .results-card {
            background: linear-gradient(135deg, #1e2746 0%, #2a3a5a 100%);
            border-radius: 16px;
            padding: 30px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        .results-header h3 {
            font-size: 20px;
        }
        .results-header p {
            color: #a0a0a0;
            font-size: 14px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: rgba(102, 126, 234, 0.1);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        .stat-card-large {
            grid-column: span 1;
        }
        .stat-label {
            font-size: 12px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .stat-value {
            font-size: 36px;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #27ae60;
            color: white;
        }
        .btn-secondary {
            background: #7f8c8d;
            color: white;
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
                <div style="display: flex; align-items: center; gap: 8px; color: #7f8c8d; font-size: 13px;">
                    <span>⚙️</span>
                    <span>Admin Console</span>
                </div>
            </div>
            
            <div class="sidebar-section">
                <div class="sidebar-section-title">Overview</div>
                <nav class="sidebar-nav">
                    <a href="dashboard.php">📊 Dashboard</a>
                </nav>
            </div>
            
            <div class="sidebar-section">
                <div class="sidebar-section-title">Academic Structure</div>
                <nav class="sidebar-nav">
                    <a href="#">🏛️ Faculties</a>
                    <a href="#">🏫 Schools</a>
                    <a href="#">📜 Programmes</a>
                    <a href="#">📅 Academic Years</a>
                </nav>
            </div>
            
            <div class="sidebar-section">
                <div class="sidebar-section-title">People & Resources</div>
                <nav class="sidebar-nav">
                    <a href="students.php">👥 Students</a>
                    <a href="admin/lecturers.php">👤 Lecturers</a>
                    <a href="admin/venues.php">📍 Venues</a>
                </nav>
            </div>
            
            <div class="sidebar-section">
                <div class="sidebar-section-title">Curriculum & Timetable</div>
                <nav class="sidebar-nav">
                    <a href="admin/modules.php">📚 Modules</a>
                    <a href="admin/timetable.php">➕ Add Session</a>
                    <a href="timetable_editor.php">✏️ Edit Sessions</a>
                    <a href="#">📋 View Timetable</a>
                    <a href="timetable_pdf_parser.php" class="active">📤 Upload Timetable</a>
                    <a href="admin/exams.php">📆 Exam Timetables</a>
                </nav>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="upload-card">
                <h2>Upload PDFs Or TXT Files For Instant Parsing</h2>
                <p>Our universal parser auto-detects SESSION and CELCAT formats. Drop the official timetable file here and we will populate programmes, modules, venues and sessions automatically.</p>
                <div class="tags">
                    <span class="tag">✨ Automatic format detection</span>
                    <span class="tag">🛡️ Validates modules, lecturers and venues</span>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="upload-area">
                        <div style="font-size: 48px; margin-bottom: 15px;">📄</div>
                        <p style="margin-bottom: 15px;">Drag and drop your file here or click to browse</p>
                        <input type="file" name="timetable_file" id="fileInput" accept=".txt,.pdf" required>
                        <label for="fileInput" class="upload-btn">Choose File</label>
                        <div id="fileName" style="margin-top: 15px; color: #667eea;"></div>
                    </div>
                    <button type="submit" class="upload-btn" style="width: 100%; margin-top: 20px;">Parse Timetable</button>
                </form>
                <div style="text-align: right; margin-top: 20px;">
                    <a href="#" class="btn btn-secondary" style="font-size: 12px; padding: 8px 16px;">📆 Need exam timetables?</a>
                </div>
            </div>
            
            <?php if ($message): ?>
            <div class="success-banner" style="background: <?= $messageType === 'error' ? '#e74c3c' : ($messageType === 'info' ? '#3498db' : '#27ae60') ?>;">
                <span><?= $messageType === 'error' ? '❌' : ($messageType === 'info' ? 'ℹ️' : '✅') ?></span>
                <span><?= htmlspecialchars($message) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($previewData && !$confirmed): ?>
            <!-- Preview Card -->
            <div class="results-card">
                <div class="results-header">
                    <div>
                        <h3>Preview: Ready to Import</h3>
                        <p>Review the import summary below. Click "Confirm & Import" to save to database.</p>
                    </div>
                    <span class="tag">✨ SESSION format detected</span>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card stat-card-large">
                        <div class="stat-label">Total Sessions</div>
                        <div class="stat-value"><?= $previewData['total_sessions'] ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Programmes</div>
                        <div class="stat-value" style="font-size: 28px;"><?= $previewData['programmes'] ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Modules</div>
                        <div class="stat-value" style="font-size: 28px;"><?= $previewData['modules_count'] ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Lecturers</div>
                        <div class="stat-value" style="font-size: 28px;"><?= $previewData['lecturers_count'] ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Venues</div>
                        <div class="stat-value" style="font-size: 28px;"><?= $previewData['venues_count'] ?></div>
                    </div>
                </div>
                
                <form method="POST" style="margin-top: 25px;">
                    <input type="hidden" name="preview_data" value="<?= htmlspecialchars(json_encode($previewData)) ?>">
                    <div class="action-buttons">
                        <button type="submit" name="confirm_parse" class="btn btn-primary">✅ Confirm & Import</button>
                        <a href="timetable_pdf_parser.php" class="btn btn-secondary">❌ Cancel</a>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <?php if ($parsingResults): ?>
            <div class="results-card">
                <div class="results-header">
                    <div>
                        <h3>Parsing complete</h3>
                        <p>Review the import summary below. Skipped sessions usually indicate duplicates or missing metadata.</p>
                    </div>
                    <span class="tag">✨ SESSION format detected</span>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card stat-card-large">
                        <div class="stat-label">Total Sessions</div>
                        <div class="stat-value"><?= $parsingResults['total_sessions'] ?></div>
                    </div>
                    <div class="stat-card stat-card-large">
                        <div class="stat-label">Created</div>
                        <div class="stat-value" style="color: #27ae60;"><?= $parsingResults['created'] ?></div>
                    </div>
                    <div class="stat-card stat-card-large">
                        <div class="stat-label">Skipped</div>
                        <div class="stat-value" style="color: #e74c3c;"><?= $parsingResults['skipped'] ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Programmes</div>
                        <div class="stat-value" style="font-size: 28px;"><?= $parsingResults['programmes'] ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Modules</div>
                        <div class="stat-value" style="font-size: 28px;"><?= $parsingResults['modules'] ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Lecturers</div>
                        <div class="stat-value" style="font-size: 28px;"><?= $parsingResults['lecturers'] ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Venues</div>
                        <div class="stat-value" style="font-size: 28px;"><?= $parsingResults['venues'] ?></div>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <a href="dashboard.php" class="btn btn-primary">📊 Go to dashboard</a>
                    <a href="timetable_editor.php" class="btn btn-primary">✏️ Edit Sessions</a>
                    <a href="timetable_pdf_parser.php" class="btn btn-secondary">📄 Parse another file</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        document.getElementById('fileInput').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || '';
            document.getElementById('fileName').textContent = fileName ? 'Selected: ' + fileName : '';
        });
    </script>
</body>
</html>

