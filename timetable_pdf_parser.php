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

$message = '';
$messageType = '';
$parsingResults = null;
$previewData = null;
$confirmed = false;

// Handle confirmation and save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_parse'])) {
    $previewDataJson = $_POST['preview_data'] ?? '';
    $previewData = json_decode($previewDataJson, true);
    
    if ($previewData && is_array($previewData) && isset($previewData['sessions'])) {
        try {
            $results = saveParsedData($previewData, $pdo);
            $parsingResults = $results;
            $message = "Successfully imported {$results['created']} sessions! " . 
                      ($results['skipped'] > 0 ? "({$results['skipped']} skipped - duplicates or invalid)" : "");
            $messageType = 'success';
            $confirmed = true;
            $previewData = null; // Clear preview after saving
            logActivity('timetable_imported', "Imported {$results['created']} sessions, {$results['skipped']} skipped", getCurrentUserId());
        } catch (Exception $e) {
            logError($e, 'Saving parsed timetable data');
            $message = 'Error saving data: ' . getErrorMessage($e, 'Saving timetable');
            $messageType = 'error';
        }
    } else {
        $message = 'Invalid preview data. Please try uploading again.';
        $messageType = 'error';
    }
}

// Handle file upload and parsing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['timetable_file']) && !isset($_POST['confirm_parse'])) {
    $file = $_FILES['timetable_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $fileName = $file['name'];
        $fileTmp = $file['tmp_name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $fileSize = $file['size'];
        
        // Validate file size (max 10MB)
        if ($fileSize > 10 * 1024 * 1024) {
            $message = 'File size exceeds 10MB limit. Please upload a smaller file.';
            $messageType = 'error';
        } elseif (in_array($fileExt, ['txt', 'pdf'])) {
            $content = '';
            
            if ($fileExt === 'txt') {
                $content = @file_get_contents($fileTmp);
                if ($content === false) {
                    $message = 'Error reading file. Please ensure the file is not corrupted.';
                    $messageType = 'error';
                } elseif (empty(trim($content))) {
                    $message = 'File appears to be empty. Please check your file.';
                    $messageType = 'error';
                } else {
                    // Parse and preview only - don't save yet
                    try {
                        $previewData = parseTimetableFilePreview($content);
                        if ($previewData['total_sessions'] > 0) {
                            $message = "Found {$previewData['total_sessions']} sessions. Review and confirm to save.";
                            $messageType = 'info';
                        } else {
                            $message = 'No sessions found in file. Please check the file format matches SESSION format.';
                            $messageType = 'error';
                        }
                    } catch (Exception $e) {
                        logError($e, 'Parsing timetable file');
                        $message = 'Error parsing file: ' . getErrorMessage($e, 'Parsing timetable');
                        $messageType = 'error';
                    }
                }
            } else {
                // For PDF, we'd need a PDF parser library
                // For now, we'll handle TXT files
                $message = 'PDF parsing not yet implemented. Please upload a TXT file.';
                $messageType = 'error';
            }
        } else {
            $message = 'Invalid file type. Please upload a TXT or PDF file.';
            $messageType = 'error';
        }
    } else {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive.',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive.',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension.'
        ];
        $message = 'Error uploading file: ' . ($uploadErrors[$file['error']] ?? 'Unknown error');
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
        } elseif (preg_match('/YEAR\s*LEVEL:\s*(.+)/i', $line, $matches)) {
            // Match various formats: "Year 1", "Level 1", "1", "First Year", etc.
            $yearText = trim($matches[1]);
            // Extract number if present, otherwise use the full text
            if (preg_match('/(\d+)/', $yearText, $numMatches)) {
                $currentYear = $numMatches[1];
            } else {
                // If no number, use the text as-is (e.g., "First Year", "Level One")
                $currentYear = $yearText;
            }
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
                    // Keep multiple modules together as single module name
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
    // Ensure all tables exist
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS programs (
            program_id INT AUTO_INCREMENT PRIMARY KEY,
            program_code VARCHAR(50) UNIQUE NOT NULL,
            program_name VARCHAR(255) NOT NULL,
            description TEXT,
            duration_years INT DEFAULT 4,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_program_code (program_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS program_modules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            program_id INT NOT NULL,
            module_id INT NOT NULL,
            year_level INT NOT NULL,
            semester INT,
            is_core TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE CASCADE,
            FOREIGN KEY (module_id) REFERENCES modules(module_id) ON DELETE CASCADE,
            UNIQUE KEY unique_program_module_year (program_id, module_id, year_level),
            INDEX idx_program_id (program_id),
            INDEX idx_module_id (module_id),
            INDEX idx_year_level (year_level)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
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
        // Tables/columns might already exist, ignore error
        logError($e, 'Setting up timetable parser tables');
    }
    
    // Save the parsed data to database
    $modulesCreated = [];
    $lecturersCreated = [];
    $venuesCreated = [];
    $programsCreated = [];
    $programModulesLinked = [];
    
    $createdCount = 0;
    $skippedCount = 0;
    $skippedDetails = [];
    
    foreach ($previewData['sessions'] as $session) {
        if (empty($session['module']) || empty($session['day']) || empty($session['start_time'])) {
            $skippedCount++;
            $skippedDetails[] = [
                'module' => $session['module'] ?? '',
                'day' => $session['day'] ?? '',
                'start_time' => $session['start_time'] ?? '',
                'reason' => 'Missing required fields (module/day/start time)'
            ];
            continue;
        }
        
        // Get or create module - stores in modules table
        $moduleCode = trim($session['module']);
        
        if (empty($moduleCode)) {
            $skippedCount++;
            $skippedDetails[] = [
                'module' => '',
                'day' => $session['day'],
                'start_time' => $session['start_time'],
                'reason' => 'Empty module code'
            ];
            continue;
        }
        
        $moduleId = null;
        
        if (!isset($modulesCreated[$moduleCode])) {
            // Check if module exists in modules table
            $stmt = $pdo->prepare("SELECT module_id FROM modules WHERE module_code = ?");
            $stmt->execute([$moduleCode]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $moduleId = $existing['module_id'];
            } else {
                // Create new module in modules table
                try {
                    $stmt = $pdo->prepare("INSERT INTO modules (module_code, module_name, credits) VALUES (?, ?, ?)");
                    $stmt->execute([$moduleCode, $moduleCode, 0]);
                    $moduleId = $pdo->lastInsertId();
                } catch (Exception $e) {
                    // If duplicate, try to fetch it
                    if ($e->getCode() == 23000 || $e->getCode() == '23000') {
                        $stmt = $pdo->prepare("SELECT module_id FROM modules WHERE module_code = ?");
                        $stmt->execute([$moduleCode]);
                        $existing = $stmt->fetch();
                        if ($existing) {
                            $moduleId = $existing['module_id'];
                        } else {
                            // Skip this session if we can't create or find module
                            $skippedCount++;
                            $skippedDetails[] = [
                                'module' => $moduleCode,
                                'day' => $session['day'],
                                'start_time' => $session['start_time'],
                                'reason' => 'Failed to create module record'
                            ];
                            continue;
                        }
                    } else {
                        // Skip this session on other errors
                        $skippedCount++;
                        $skippedDetails[] = [
                            'module' => $moduleCode,
                            'day' => $session['day'],
                            'start_time' => $session['start_time'],
                            'reason' => 'Module creation error'
                        ];
                        continue;
                    }
                }
            }
            if ($moduleId) {
                $modulesCreated[$moduleCode] = $moduleId;
            }
        } else {
            $moduleId = $modulesCreated[$moduleCode];
        }
        
        if (!$moduleId) {
            $skippedCount++;
            continue;
        }
        
        // Get or create program - stores in programs table
        $programmeName = trim($session['programme'] ?? '');
        $programId = null;
        
        if (!empty($programmeName)) {
            // Generate program code from name (first letters of words or first 10 chars)
            $programCode = '';
            $words = explode(' ', $programmeName);
            if (count($words) > 1) {
                // Use first letter of each word
                foreach ($words as $word) {
                    if (!empty(trim($word))) {
                        $programCode .= strtoupper(substr(trim($word), 0, 1));
                    }
                }
                $programCode = substr($programCode, 0, 10); // Limit to 10 chars
            } else {
                // Use first 10 characters
                $programCode = strtoupper(substr($programmeName, 0, 10));
            }
            
            // Clean program code (remove special chars, keep only alphanumeric)
            $programCode = preg_replace('/[^A-Z0-9]/', '', $programCode);
            if (empty($programCode)) {
                $programCode = 'PROG' . substr(md5($programmeName), 0, 6);
            }
            
            if (!isset($programsCreated[$programmeName])) {
                // Check if program exists
                $stmt = $pdo->prepare("SELECT program_id FROM programs WHERE program_name = ? OR program_code = ?");
                $stmt->execute([$programmeName, $programCode]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    $programId = $existing['program_id'];
                } else {
                    // Create new program
                    try {
                        $stmt = $pdo->prepare("INSERT INTO programs (program_code, program_name, description) VALUES (?, ?, ?)");
                        $stmt->execute([$programCode, $programmeName, 'Imported from timetable']);
                        $programId = $pdo->lastInsertId();
                    } catch (Exception $e) {
                        // If duplicate code, try to find by name
                        if ($e->getCode() == 23000 || $e->getCode() == '23000') {
                            $stmt = $pdo->prepare("SELECT program_id FROM programs WHERE program_name = ?");
                            $stmt->execute([$programmeName]);
                            $existing = $stmt->fetch();
                            if ($existing) {
                                $programId = $existing['program_id'];
                            } else {
                                // Try with modified code
                                $programCode = $programCode . substr(md5($programmeName), 0, 4);
                                $stmt = $pdo->prepare("INSERT INTO programs (program_code, program_name, description) VALUES (?, ?, ?)");
                                $stmt->execute([$programCode, $programmeName, 'Imported from timetable']);
                                $programId = $pdo->lastInsertId();
                            }
                        }
                    }
                }
                $programsCreated[$programmeName] = $programId;
            } else {
                $programId = $programsCreated[$programmeName];
            }
            
            // Link module to program and year in program_modules table
            $yearLevel = null;
            if (!empty($session['year'])) {
                $yearText = trim($session['year']);
                // Extract number from year text (handles "Year 1", "1", "First Year", etc.)
                if (preg_match('/(\d+)/', $yearText, $numMatches)) {
                    $yearLevel = (int)$numMatches[1];
                } elseif (preg_match('/first|one|1st/i', $yearText)) {
                    $yearLevel = 1;
                } elseif (preg_match('/second|two|2nd/i', $yearText)) {
                    $yearLevel = 2;
                } elseif (preg_match('/third|three|3rd/i', $yearText)) {
                    $yearLevel = 3;
                } elseif (preg_match('/fourth|four|4th/i', $yearText)) {
                    $yearLevel = 4;
                } elseif (preg_match('/fifth|five|5th/i', $yearText)) {
                    $yearLevel = 5;
                } elseif (preg_match('/sixth|six|6th/i', $yearText)) {
                    $yearLevel = 6;
                }
            }
            
            if ($programId && $moduleId && $yearLevel && $yearLevel > 0) {
                $linkKey = "{$programId}_{$moduleId}_{$yearLevel}";
                if (!isset($programModulesLinked[$linkKey])) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO program_modules (program_id, module_id, year_level, is_core) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE is_core = VALUES(is_core)");
                        $stmt->execute([$programId, $moduleId, $yearLevel, 1]);
                        $programModulesLinked[$linkKey] = true;
                    } catch (Exception $e) {
                        // Log error but continue - might be duplicate or constraint issue
                        logError($e, 'Linking module to program in timetable parser');
                    }
                }
            }
        }
        
        // Get or create lecturer - stores in lecturers table
        $lecturerName = trim($session['staff'] ?? '');
        $lecturerId = null;
        
        if (!empty($lecturerName)) {
            if (!isset($lecturersCreated[$lecturerName])) {
                // Check if lecturer exists in lecturers table
                $stmt = $pdo->prepare("SELECT lecturer_id FROM lecturers WHERE lecturer_name = ?");
                $stmt->execute([$lecturerName]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    $lecturerId = $existing['lecturer_id'];
                } else {
                    // Create new lecturer in lecturers table
                    $stmt = $pdo->prepare("INSERT INTO lecturers (lecturer_name, email) VALUES (?, ?)");
                    $stmt->execute([$lecturerName, '']);
                    $lecturerId = $pdo->lastInsertId();
                }
                $lecturersCreated[$lecturerName] = $lecturerId;
            } else {
                $lecturerId = $lecturersCreated[$lecturerName];
            }
        }
        
        // Get or create venue - stores in venues table
        $venueName = trim($session['room_name'] ?? $session['room_code'] ?? '');
        $venueId = null;
        
        if (!empty($venueName)) {
            if (!isset($venuesCreated[$venueName])) {
                // Check if venue exists in venues table
                $stmt = $pdo->prepare("SELECT venue_id FROM venues WHERE venue_name = ?");
                $stmt->execute([$venueName]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    $venueId = $existing['venue_id'];
                } else {
                    // Create new venue in venues table
                    $stmt = $pdo->prepare("INSERT INTO venues (venue_name, capacity) VALUES (?, ?)");
                    $stmt->execute([$venueName, 0]);
                    $venueId = $pdo->lastInsertId();
                }
                $venuesCreated[$venueName] = $venueId;
            } else {
                $venueId = $venuesCreated[$venueName];
            }
        }
        
        // Check if session already exists in sessions table
        $stmt = $pdo->prepare("SELECT session_id FROM sessions WHERE module_id = ? AND day_of_week = ? AND start_time = ?");
        $stmt->execute([$moduleId, $session['day'], $session['start_time']]);
        $existing = $stmt->fetch();
        
        if (!$existing) {
            // Insert session into sessions table with proper foreign keys
            // module_id → links to modules table
            // lecturer_id → links to lecturers table (can be NULL)
            // venue_id → links to venues table (can be NULL)
            // programme, year_level, semester → from parsed data
            $stmt = $pdo->prepare("INSERT INTO sessions (module_id, lecturer_id, venue_id, day_of_week, start_time, end_time, programme, year_level, semester) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            // Get programme, year, and semester from session data
            $programme = !empty($session['programme']) ? trim($session['programme']) : null;
            $yearLevel = !empty($session['year']) ? trim($session['year']) : null;
            $semester = !empty($session['semester']) ? trim($session['semester']) : null;
            
            $stmt->execute([
                $moduleId,
                $lecturerId,  // Can be NULL if no lecturer
                $venueId,     // Can be NULL if no venue
                $session['day'],
                $session['start_time'],
                $session['end_time'],
                $programme,
                $yearLevel,
                $semester
            ]);
            $createdCount++;
        } else {
            $skippedCount++;
            $skippedDetails[] = [
                'module' => $moduleCode,
                'day' => $session['day'],
                'start_time' => $session['start_time'],
                'reason' => 'Duplicate session already exists'
            ];
        }
    }
    
    return [
        'total_sessions' => $previewData['total_sessions'],
        'created' => $createdCount,
        'skipped' => $skippedCount,
        'programmes' => count($programsCreated),
        'modules' => count($modulesCreated),
        'lecturers' => count($lecturersCreated),
        'venues' => count($venuesCreated),
        'program_modules_linked' => count($programModulesLinked),
        'skipped_details' => $skippedDetails,
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
        } elseif (preg_match('/YEAR\s*LEVEL:\s*(.+)/i', $line, $matches)) {
            // Match various formats: "Year 1", "Level 1", "1", "First Year", etc.
            $yearText = trim($matches[1]);
            // Extract number if present, otherwise use the full text
            if (preg_match('/(\d+)/', $yearText, $numMatches)) {
                $currentYear = $numMatches[1];
            } else {
                // If no number, use the text as-is (e.g., "First Year", "Level One")
                $currentYear = $yearText;
            }
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
                logError($e, 'Adding columns to sessions table');
            }
            
            // Insert session with programme, year_level, and semester
            $stmt = $pdo->prepare("INSERT INTO sessions (module_id, lecturer_id, venue_id, day_of_week, start_time, end_time, programme, year_level, semester) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $programme = !empty($session['programme']) ? trim($session['programme']) : null;
            $yearLevel = !empty($session['year']) ? trim($session['year']) : null;
            $semester = !empty($session['semester']) ? trim($session['semester']) : null;
            
            $stmt->execute([
                $moduleId,
                $lecturerId,
                $venueId,
                $session['day'],
                $session['start_time'],
                $session['end_time'],
                $programme,
                $yearLevel,
                $semester
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
<?php
$breadcrumbs = [
    ['label' => 'Dashboard', 'href' => 'admin/index.php'],
    ['label' => 'Upload Timetable', 'href' => null],
];
$page_actions = [];
include 'admin/header_modern.php';
?>
            <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
        }
        /* Page spacing within shared shell */
        .ds-main { padding-bottom: 24px; }
        
        .upload-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 24px;
            padding: 32px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.10);
            box-shadow: 0 4px 20px rgba(0,0,0,0.45);
            backdrop-filter: blur(14px);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        .upload-card h2 {
            font-size: 24px;
            margin-bottom: 12px;
            color: #e8edff;
            font-weight: 700;
            letter-spacing: -0.3px;
        }
        .upload-card p {
            color: rgba(220,230,255,0.75);
            margin-bottom: 25px;
            line-height: 1.6;
            font-size: 14px;
            font-weight: 400;
            letter-spacing: -0.01em;
        }
        .tags {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .tag {
            display:inline-flex; align-items:center; gap:8px;
            background: rgba(59, 130, 246, 0.15);
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 12px;
            color: rgba(220,230,255,0.9);
            border: 1px solid rgba(59, 130, 246, 0.3);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        .upload-area {
            border: 2px dashed rgba(59, 130, 246, 0.4);
            border-radius: 16px;
            padding: 40px;
            text-align: center;
            background: rgba(59, 130, 246, 0.08);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .upload-area:hover {
            border-color: rgba(59, 130, 246, 0.6);
            background: rgba(59, 130, 246, 0.12);
        }
        .upload-area input[type="file"] {
            display: none;
        }
        .upload-btn {
            background: rgba(59, 130, 246, 0.25);
            color: #dce3ff;
            padding: 14px 24px;
            border: 1px solid rgba(59, 130, 246, 0.4);
            border-radius: 12px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            margin-top: 20px;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .upload-btn:hover {
            background: rgba(59, 130, 246, 0.35);
            border-color: rgba(59, 130, 246, 0.5);
        }
        
        .success-banner {
            background: rgba(255, 255, 255, 0.05);
            color: rgba(220,230,255,0.9);
            padding: 16px 20px;
            border-radius: 16px;
            margin-bottom: 16px;
            border: 1px solid rgba(255, 255, 255, 0.10);
            display: flex;
            align-items: center;
            gap: 12px;
            backdrop-filter: blur(10px);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        
        .results-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 24px;
            padding: 28px;
            border: 1px solid rgba(255, 255, 255, 0.10);
            box-shadow: 0 4px 20px rgba(0,0,0,0.45);
            backdrop-filter: blur(14px);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
        }
        .results-header h3 {
            font-size: 20px;
            color: #e8edff;
            font-weight: 700;
            letter-spacing: -0.2px;
        }
        .results-header p {
            color: rgba(220,230,255,0.75);
            font-size: 14px;
        }
        .info-hint {
            margin-top: 8px;
            padding: 12px 16px;
            border-radius: 12px;
            border: 1px solid rgba(59, 130, 246, 0.3);
            background: rgba(59, 130, 246, 0.12);
            color: rgba(220,230,255,0.85);
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            font-size: 13px;
        }
        .info-hint svg { width:18px; height:18px; color: #93c5fd; margin-top: 2px; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 12px;
        }
        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.10);
            border-radius: 16px;
            padding: 18px;
            text-align: center;
            backdrop-filter: blur(10px);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        .stat-card-large {
            grid-column: span 1;
        }
        .stat-label {
            font-size: 12px;
            color: rgba(220,230,255,0.75);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
            font-weight: 600;
        }
        .stat-value {
            font-size: 28px;
            font-weight: 800;
            color: #dce3ff;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 16px;
        }
        .details-toggle {
            margin-top: 8px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: rgba(220,230,255,0.75);
            cursor: pointer;
            user-select: none;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        .details-box {
            margin-top: 10px;
            border: 1px solid rgba(255, 255, 255, 0.10);
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.03);
            padding: 12px;
            display: none;
            backdrop-filter: blur(10px);
        }
        .details-row {
            display: grid;
            grid-template-columns: 1.2fr .8fr .6fr 1.6fr;
            gap: 10px;
            padding: 8px 6px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            font-size: 13px;
            color: rgba(220,230,255,0.75);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        .details-row.header {
            color: #e8edff;
            font-weight: 600;
        }
        .details-row:last-child { border-bottom: none; }
        .btn {
            padding: 10px 20px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        .btn-primary {
            background: rgba(59, 130, 246, 0.25);
            color: #dce3ff;
            border-color: rgba(59, 130, 246, 0.4);
        }
        .btn-secondary {
            background: rgba(255,255,255,0.05);
            color: rgba(220,230,255,0.8);
            border-color: rgba(255,255,255,0.12);
        }
        .btn:hover {
            background: rgba(59, 130, 246, 0.35);
            border-color: rgba(59, 130, 246, 0.5);
        }
        .btn-secondary:hover {
            background: rgba(255,255,255,0.08);
            border-color: rgba(255,255,255,0.18);
        }
    </style>
        
            <!-- Content -->
            <div class="upload-card">
                <h2 style="display: flex; align-items: center; gap: 12px;">
                    <svg viewBox="0 0 24 24" style="width: 24px; height: 24px; color: #dce3ff;" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="17 8 12 3 7 8"></polyline>
                        <line x1="12" y1="3" x2="12" y2="15"></line>
                    </svg>
                    Upload PDFs Or TXT Files For Instant Parsing
                </h2>
                <p style="color: rgba(220,230,255,0.75); font-size: 14px; line-height: 1.6; margin-bottom: 24px; font-weight: 400; letter-spacing: -0.01em;">Our universal parser auto-detects SESSION and CELCAT formats. Drop the official timetable file here and we will populate programmes, modules, venues and sessions automatically.</p>
                <div class="tags" style="display: flex; gap: 10px; margin-bottom: 24px; flex-wrap: wrap;">
                    <span class="tag" style="display:inline-flex; align-items:center; gap:8px; background: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.3); padding: 8px 14px; border-radius: 10px; font-size: 12px; color: rgba(220,230,255,0.9); font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" style="width:14px;height:14px; color: #93c5fd;"><path d="M12 2v4"/><path d="M20 12h4"/><path d="M0 12h4"/><path d="M18.36 5.64 15.5 8.5"/><path d="M5.64 5.64 8.5 8.5"/></svg> Automatic format detection
                    </span>
                    <span class="tag" style="display:inline-flex; align-items:center; gap:8px; background: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.3); padding: 8px 14px; border-radius: 10px; font-size: 12px; color: rgba(220,230,255,0.9); font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" style="width:14px;height:14px; color: #93c5fd;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> Validates modules, lecturers and venues
                    </span>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="upload-area" style="border: 2px dashed rgba(59, 130, 246, 0.4); border-radius: 16px; padding: 40px; text-align: center; background: rgba(59, 130, 246, 0.08); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
                        <div style="font-size: 48px; margin-bottom: 16px; color: #93c5fd;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:48px;height:48px;"><path d="M4 22h16a2 2 0 0 0 2-2V7l-5-5H6a2 2 0 0 0-2 2v16Z"/><path d="M14 2v6h6"/></svg>
                        </div>
                        <p style="margin-bottom: 16px; color: rgba(220,230,255,0.85); font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">Drag and drop your file here or click to browse</p>
                        <input type="file" name="timetable_file" id="fileInput" accept=".txt,.pdf" required>
                        <label for="fileInput" class="upload-btn" style="background: rgba(59, 130, 246, 0.2); color: #dce3ff; border: 1px solid rgba(59, 130, 246, 0.4); padding: 12px 24px; border-radius: 10px; cursor: pointer; font-size: 14px; font-weight: 600; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: inline-block; margin-top: 8px;" onmouseover="this.style.background='rgba(59, 130, 246, 0.3)'; this.style.borderColor='rgba(59, 130, 246, 0.5)'" onmouseout="this.style.background='rgba(59, 130, 246, 0.2)'; this.style.borderColor='rgba(59, 130, 246, 0.4)'">Choose File</label>
                        <div id="fileName" style="margin-top: 16px; color: #93c5fd; font-size: 13px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;"></div>
                    </div>
                    <button type="submit" class="upload-btn" style="width: 100%; margin-top: 20px; background: rgba(59, 130, 246, 0.25); color: #dce3ff; border: 1px solid rgba(59, 130, 246, 0.4); padding: 14px 24px; border-radius: 12px; cursor: pointer; font-size: 15px; font-weight: 600; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);" onmouseover="this.style.background='rgba(59, 130, 246, 0.35)'; this.style.borderColor='rgba(59, 130, 246, 0.5)'" onmouseout="this.style.background='rgba(59, 130, 246, 0.25)'; this.style.borderColor='rgba(59, 130, 246, 0.4)'">Parse Timetable</button>
                </form>
				<div style="text-align: right; margin-top: 20px;">
					<a href="exam_pdf_parser.php" class="btn btn-secondary" style="font-size: 13px; padding: 10px 18px; display: inline-flex; align-items: center; gap: 6px;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" style="width:14px;height:14px; color: rgba(220,230,255,0.8);"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>Need exam timetables?</a>
				</div>
            </div>
            
            <?php if ($message): ?>
            <div class="success-banner" style="background: <?= $messageType === 'error' ? 'rgba(239, 68, 68, 0.15)' : ($messageType === 'info' ? 'rgba(59, 130, 246, 0.15)' : 'rgba(16, 185, 129, 0.15)') ?>; border: 1px solid <?= $messageType === 'error' ? 'rgba(239, 68, 68, 0.3)' : ($messageType === 'info' ? 'rgba(59, 130, 246, 0.3)' : 'rgba(16, 185, 129, 0.3)') ?>; border-radius: 16px; padding: 16px 20px; margin-bottom: 20px; backdrop-filter: blur(10px); display: flex; align-items: center; gap: 12px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                <span aria-hidden="true" style="color: <?= $messageType === 'error' ? '#fca5a5' : ($messageType === 'info' ? '#93c5fd' : '#6ee7b7') ?>; flex-shrink: 0;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" style="width:20px;height:20px;">
                        <?php if ($messageType === 'error'): ?>
                        <path d="M18 6 6 18"/><path d="M6 6l12 12"/>
                        <?php elseif ($messageType === 'info'): ?>
                        <circle cx="12" cy="12" r="10"/><path d="M12 8h.01"/><path d="M11 12h1v4h1"/>
                        <?php else: ?>
                        <path d="M20 6 9 17l-5-5"/>
                        <?php endif; ?>
                    </svg>
                </span>
                <span style="color: rgba(220,230,255,0.9); font-size: 14px; font-weight: 500;"><?= htmlspecialchars($message) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($previewData && !$confirmed): ?>
            <!-- Preview Card -->
            <div class="results-card" style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.10); border-radius: 24px; padding: 28px; backdrop-filter: blur(14px); box-shadow: 0 4px 20px rgba(0,0,0,0.45); font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                <div class="results-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; flex-wrap: wrap; gap: 16px;">
                    <div>
                        <h3 style="color: #e8edff; font-size: 20px; font-weight: 700; letter-spacing: -0.2px; margin-bottom: 12px; display: flex; align-items: center; gap: 10px;">
                            <svg viewBox="0 0 24 24" style="width: 20px; height: 20px; color: #dce3ff;" fill="none" stroke="currentColor" stroke-width="1.7">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                <polyline points="10 9 9 9 8 9"></polyline>
                            </svg>
                            Preview: Ready to Import
                        </h3>
                        <div class="info-hint">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8h.01"/><path d="M11 12h1v4h1"/></svg>
                            <div>Review the import summary below. Click “Confirm & Import” to save to database.</div>
                        </div>
                    </div>
                    <span class="tag"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;"><path d="M12 2a10 10 0 1 0 10 10A10.011 10.011 0 0 0 12 2Z"/><path d="M12 6v6l4 2"/></svg> SESSION format detected</span>
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
                        <button type="submit" name="confirm_parse" class="btn btn-primary">Confirm & Import</button>
                        <a href="timetable_pdf_parser.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <?php if ($parsingResults): ?>
            <div class="results-card">
                <div class="results-header">
                    <div>
                        <h3 style="color: #e8edff; font-size: 20px; font-weight: 700; letter-spacing: -0.2px; margin-bottom: 12px; display: flex; align-items: center; gap: 10px;">
                            <svg viewBox="0 0 24 24" style="width: 20px; height: 20px; color: #dce3ff;" fill="none" stroke="currentColor" stroke-width="1.7">
                                <path d="M20 6 9 17l-5-5"/>
                            </svg>
                            Parsing complete
                        </h3>
                        <div class="info-hint">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/><path d="M12 8h.01"/><path d="M11 12h1v4h1"/></svg>
                            <div>Review the import summary below. <strong>Skipped</strong> sessions usually indicate duplicates or missing metadata.</div>
                        </div>
                    </div>
                    <span class="tag"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;"><path d="M12 2a10 10 0 1 0 10 10A10.011 10.011 0 0 0 12 2Z"/><path d="M12 6v6l4 2"/></svg> SESSION format detected</span>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card stat-card-large">
                        <div class="stat-label">Total Sessions</div>
                        <div class="stat-value"><?= $parsingResults['total_sessions'] ?></div>
                    </div>
                    <div class="stat-card stat-card-large">
                        <div class="stat-label">Created</div>
                        <div class="stat-value" style="color: #6ee7b7;"><?= $parsingResults['created'] ?></div>
                    </div>
                    <div class="stat-card stat-card-large">
                        <div class="stat-label">Skipped</div>
                        <div class="stat-value" style="color: #fca5a5;"><?= $parsingResults['skipped'] ?></div>
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
                    <?php if (isset($parsingResults['program_modules_linked'])): ?>
                    <div class="stat-card">
                        <div class="stat-label">Module Links</div>
                        <div class="stat-value" style="font-size: 28px; color: #9b59b6;"><?= $parsingResults['program_modules_linked'] ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($parsingResults['skipped'])): ?>
                <div>
                    <div class="details-toggle" onclick="toggleSkippedDetails()">
                        <svg id="skipChevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px; transition: transform .2s;"><path d="m6 9 6 6 6-6"/></svg>
                        View skipped details (<?= (int)$parsingResults['skipped'] ?>)
                    </div>
                    <div id="skippedDetails" class="details-box">
                        <div class="details-row header">
                            <div>Module</div><div>Day</div><div>Start</div><div>Reason</div>
                        </div>
                        <?php foreach (($parsingResults['skipped_details'] ?? []) as $row): ?>
                        <div class="details-row">
                            <div><?= htmlspecialchars($row['module'] ?: '—') ?></div>
                            <div><?= htmlspecialchars($row['day'] ?: '—') ?></div>
                            <div><?= htmlspecialchars($row['start_time'] ?: '—') ?></div>
                            <div><?= htmlspecialchars($row['reason'] ?: '—') ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="action-buttons">
                    <a href="admin/index.php" class="btn btn-primary">Go to dashboard</a>
                    <a href="timetable_editor.php" class="btn btn-primary">Edit Sessions</a>
                    <a href="timetable_pdf_parser.php" class="btn btn-secondary">Parse another file</a>
                </div>
            </div>
            <?php endif; ?>
            <script>
        document.getElementById('fileInput').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || '';
            document.getElementById('fileName').textContent = fileName ? 'Selected: ' + fileName : '';
        });
        function toggleSkippedDetails(){
            const box = document.getElementById('skippedDetails');
            const chev = document.getElementById('skipChevron');
            if (!box) return;
            const visible = box.style.display === 'block';
            box.style.display = visible ? 'none' : 'block';
            if (chev) chev.style.transform = visible ? 'rotate(0deg)' : 'rotate(180deg)';
        }
    </script>
<?php include 'admin/footer_modern.php'; ?>

