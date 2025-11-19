<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
	header('Location: admin/login.php');
	exit;
}

require_once 'admin/config.php';
require_once __DIR__ . '/includes/database.php';

// Composer autoload for PDF parsing
$vendorAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
	require_once $vendorAutoload;
}

$pdo = Database::getInstance()->getConnection();

$message = '';
$messageType = '';
$previewData = null;
$importResults = null;

// Confirm and import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_exam_import'])) {
	$previewDataJson = $_POST['preview_data'] ?? '';
	$preview = json_decode($previewDataJson, true);
	if ($preview && isset($preview['rows'])) {
		$importResults = saveExamRows($preview['rows'], $pdo);
		$message = "Imported {$importResults['created']} of {$importResults['total']} exam entries.";
		$messageType = $importResults['created'] > 0 ? 'success' : 'info';
	}
}

// Handle upload + parse
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['exam_file']) && !isset($_POST['confirm_exam_import'])) {
	$file = $_FILES['exam_file'];
	if ($file['error'] === UPLOAD_ERR_OK) {
		$fileName = $file['name'];
		$fileTmp = $file['tmp_name'];
		$fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

		if (in_array($fileExt, ['txt', 'pdf'])) {
			$content = '';
			if ($fileExt === 'txt') {
				$content = file_get_contents($fileTmp);
			} else {
				// Try to parse PDF text via smalot/pdfparser if available
				if (class_exists(\Smalot\PdfParser\Parser::class)) {
					try {
						$parser = new \Smalot\PdfParser\Parser();
						$pdf = $parser->parseFile($fileTmp);
						$content = $pdf->getText();
					} catch (Exception $e) {
						$message = 'Could not parse PDF. ' . $e->getMessage();
						$messageType = 'error';
					}
				} else {
					$message = 'PDF parsing library not installed. Run: composer install';
					$messageType = 'error';
				}
			}

			if (!empty($content)) {
				// Save raw content for debugging
				$debugDir = __DIR__ . '/logs';
				if (!is_dir($debugDir)) {
					@mkdir($debugDir, 0755, true);
				}
				@file_put_contents($debugDir . '/last_exam_upload.txt', $content);
				
				$previewData = parseExamContentPreview($content);
				if ($previewData['total'] > 0) {
					$message = "Found {$previewData['total']} exams. Review and confirm to import.";
					$messageType = 'info';
				} else {
					$message = 'No exam entries detected. Check the file format. The raw content has been saved to logs/last_exam_upload.txt for debugging.';
					$messageType = 'error';
				}
			} else {
				$message = 'File appears to be empty or could not be read.';
				$messageType = 'error';
			}
		} else {
			$message = 'Invalid file type. Please upload a TXT or PDF.';
			$messageType = 'error';
		}
	} else {
		$message = 'Error uploading file.';
		$messageType = 'error';
	}
}

function parseExamContentPreview($content) {
	// Normalize line endings and split
	$content = str_replace(["\r\n", "\r"], "\n", $content);
	
	// First, try to detect and handle the specific "Final_November" format
	// This format has data spread across multiple lines with a pattern like:
	// 2025/11/03ACC321_P_1_1AUDITING 321
	// SCHOOL DEVELOPMENT 
	// STUDIES	09:00 180 5 51403_0_006 LECTRUE SEMINAR ROOM
	// Mbombela 
	// Campus	32
	
	if (strpos($content, 'Final_') !== false || strpos($content, 'Exam Timetable') !== false) {
		return parseFinalExamFormat($content);
	}
	
	$lines = explode("\n", $content);
	$rows = [];
	$currentRow = null; // For multi-line entries

	// Enhanced heuristics for common formats
	// Supports lines like:
	// CS101 2025-11-21 09:00-11:00 120min Main Hall
	// CS101 | 21/11/2025 | 09:00 | 120 | Main Hall
	// Module: CS101, Date: 2025-11-21, Time: 09:00-11:00, Venue: Main Hall, Duration: 120
	// Also handles multi-line entries and table formats
	
	foreach ($lines as $lineNum => $line) {
		$originalLine = $line;
		$line = trim(preg_replace('/\s+/', ' ', $line));
		if ($line === '' || strlen($line) < 3) {
			// Empty line might end a multi-line entry
			if ($currentRow && $currentRow['module_code'] && $currentRow['exam_date'] && $currentRow['exam_time']) {
				$rows[] = $currentRow;
				$currentRow = null;
			}
			continue;
		}

		// Try to extract data from this line
		$moduleCode = null;
		$examDate = null;
		$startTime = null;
		$endTime = null;
		$durationMin = null;
		$venue = null;

		// Extract module code - more flexible patterns
		// Pattern 1: Standard format (e.g., CS101, MATH201, BIT301, ITEC301)
		if (preg_match('/\b([A-Z]{2,}[0-9]{2,}[A-Z]?)\b/', $line, $m)) {
			$moduleCode = $m[1];
		}
		// Pattern 2: "Module:" prefix
		if (!$moduleCode && preg_match('/Module[:\s]+([A-Z0-9]+)/i', $line, $m)) {
			$moduleCode = trim($m[1]);
		}
		// Pattern 3: "Code:" prefix
		if (!$moduleCode && preg_match('/Code[:\s]+([A-Z0-9]+)/i', $line, $m)) {
			$moduleCode = trim($m[1]);
		}

		// Extract date - more formats
		$dateStr = tryParseDateString($line);
		if ($dateStr) {
			$examDate = $dateStr;
		}
		// Also try "Date:" prefix
		if (!$examDate && preg_match('/Date[:\s]+([0-9\/\-]+)/i', $line, $m)) {
			$dateStr = tryParseDateString($m[1]);
			if ($dateStr) {
				$examDate = $dateStr;
			}
		}

		// Extract time - more flexible
		// Pattern 1: Time range (09:00-11:00 or 09:00–11:00)
		if (preg_match('/\b(\d{1,2}):(\d{2})\s*[-–—]\s*(\d{1,2}):(\d{2})\b/', $line, $m)) {
			$startTime = sprintf('%02d:%02d:00', (int)$m[1], (int)$m[2]);
			$endTime = sprintf('%02d:%02d:00', (int)$m[3], (int)$m[4]);
			$durationMin = computeDurationMinutes(sprintf('%02d:%02d', (int)$m[1], (int)$m[2]), sprintf('%02d:%02d', (int)$m[3], (int)$m[4]));
		}
		// Pattern 2: Single time (09:00)
		if (!$startTime && preg_match('/\b(\d{1,2}):(\d{2})\b/', $line, $m)) {
			$startTime = sprintf('%02d:%02d:00', (int)$m[1], (int)$m[2]);
		}
		// Pattern 3: "Time:" prefix
		if (!$startTime && preg_match('/Time[:\s]+(\d{1,2}:\d{2})/i', $line, $m)) {
			$timeParts = explode(':', $m[1]);
			$startTime = sprintf('%02d:%02d:00', (int)$timeParts[0], isset($timeParts[1]) ? (int)$timeParts[1] : 0);
		}

		// Extract duration - more patterns
		if (preg_match('/\b(\d{1,3})\s*(min|mins|minutes?)\b/i', $line, $m)) {
			$durationMin = (int)$m[1];
		} elseif (preg_match('/\b(\d(?:\.\d)?)\s*(h|hr|hrs|hour|hours?)\b/i', $line, $m)) {
			$durationMin = (int)round(((float)$m[1]) * 60);
		} elseif (preg_match('/Duration[:\s]+(\d+)/i', $line, $m)) {
			$durationMin = (int)$m[1];
		}

		// Extract venue - more patterns
		if (preg_match('/Venue[:\s]+([^|,\n]+)/i', $line, $m)) {
			$venue = trim($m[1]);
		} elseif (preg_match('/Room[:\s]+([^|,\n]+)/i', $line, $m)) {
			$venue = trim($m[1]);
		} elseif (preg_match('/Location[:\s]+([^|,\n]+)/i', $line, $m)) {
			$venue = trim($m[1]);
		} else {
			// Heuristic: last token(s) after duration/time look like venue words
			if (preg_match('/(?:minutes?|hrs?|h|duration)\s+(.+?)(?:\s+\d|$)/i', $line, $m)) {
				$venue = trim($m[1]);
			} elseif ($endTime && preg_match('/\d{1,2}:\d{2}\s+(.+?)(?:\s+\d|$)/', $line, $m)) {
				$venue = trim($m[1]);
			}
			// Strip separators and clean up
			if ($venue) {
				$venue = trim(preg_replace('/^[\-\|,:\s]+|[\-\|,:\s]+$/', '', $venue));
				// Remove common trailing words that aren't venue names
				$venue = preg_replace('/\s+(min|mins|minutes?|hrs?|h|duration)$/i', '', $venue);
			}
		}

		// Merge with current row if we're building a multi-line entry
		if ($currentRow) {
			if ($moduleCode && !$currentRow['module_code']) $currentRow['module_code'] = $moduleCode;
			if ($examDate && !$currentRow['exam_date']) $currentRow['exam_date'] = $examDate;
			if ($startTime && !$currentRow['exam_time']) {
				$currentRow['exam_time'] = $startTime;
				$currentRow['end_time'] = $endTime;
			}
			if ($durationMin && !$currentRow['duration']) $currentRow['duration'] = $durationMin;
			if ($venue && !$currentRow['venue']) $currentRow['venue'] = $venue;
			$currentRow['raw'] .= ' | ' . $originalLine;
		} else {
			// Start new row
			$currentRow = [
				'module_code' => $moduleCode,
				'exam_date' => $examDate,
				'exam_time' => $startTime,
				'end_time' => $endTime,
				'duration' => $durationMin,
				'venue' => $venue,
				'raw' => $originalLine
			];
		}

		// If we have all required fields, save the row
		if ($currentRow && $currentRow['module_code'] && $currentRow['exam_date'] && $currentRow['exam_time']) {
			$rows[] = $currentRow;
			$currentRow = null; // Reset for next entry
		}
	}

	// Don't forget the last row if it's incomplete but has some data
	if ($currentRow && $currentRow['module_code'] && $currentRow['exam_date'] && $currentRow['exam_time']) {
		$rows[] = $currentRow;
	}

	return [
		'rows' => $rows,
		'total' => count($rows)
	];
}

function computeDurationMinutes($start, $end) {
	$startDt = DateTime::createFromFormat('H:i', $start);
	$endDt = DateTime::createFromFormat('H:i', $end);
	if (!$startDt || !$endDt) return null;
	$diff = $endDt->getTimestamp() - $startDt->getTimestamp();
	if ($diff < 0) $diff += 24 * 3600; // cross midnight
	return (int)round($diff / 60);
}

function normalizeTimeFragments($s) {
	$s = preg_replace('/\b(\d{1,2})\s*[hH]\s*([0-5]\d)\b/', '$1:$2', $s);
	$s = preg_replace('/\b(\d{1,2})\s*[hH]\s*00\b/', '$1:00', $s);
	return $s;
}

function normalizeDashes($s) {
	return str_replace(["–", "—", "−"], "-", $s);
}

function monthNameToNum($mon) {
	$map = [
		'JAN'=>1,'JANUARY'=>1,
		'FEB'=>2,'FEBRUARY'=>2,
		'MAR'=>3,'MARCH'=>3,
		'APR'=>4,'APRIL'=>4,
		'MAY'=>5,
		'JUN'=>6,'JUNE'=>6,
		'JUL'=>7,'JULY'=>7,
		'AUG'=>8,'AUGUST'=>8,
		'SEP'=>9,'SEPT'=>9,'SEPTEMBER'=>9,
		'OCT'=>10,'OCTOBER'=>10,
		'NOV'=>11,'NOVEMBER'=>11,
		'DEC'=>12,'DECEMBER'=>12
	];
	$u = strtoupper(trim($mon));
	// Try exact match first
	if (isset($map[$u])) {
		return $map[$u];
	}
	// Try partial match (first 3 chars)
	$u3 = substr($u, 0, 3);
	return $map[$u3] ?? null;
}

function tryParseDateString($s) {
	// Normalize dashes
	$s = normalizeDashes($s);
	
	// Pattern 1: YYYY-MM-DD
	if (preg_match('/\b(\d{4})-(\d{1,2})-(\d{1,2})\b/', $s, $m)) {
		return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
	}
	// Pattern 2: YYYY/MM/DD
	if (preg_match('/\b(\d{4})\/(\d{1,2})\/(\d{1,2})\b/', $s, $m)) {
		return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
	}
	// Pattern 3: DD/MM/YYYY
	if (preg_match('/\b(\d{1,2})\/(\d{1,2})\/(\d{4})\b/', $s, $m)) {
		return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
	}
	// Pattern 4: DD-MM-YYYY
	if (preg_match('/\b(\d{1,2})-(\d{1,2})-(\d{4})\b/', $s, $m)) {
		return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
	}
	// Pattern 5: DD/MM/YY (assume 20XX)
	if (preg_match('/\b(\d{1,2})\/(\d{1,2})\/(\d{2})\b/', $s, $m)) {
		$year = (int)$m[3];
		if ($year < 50) $year += 2000; else $year += 1900;
		return sprintf('%04d-%02d-%02d', $year, (int)$m[2], (int)$m[1]);
	}
	// Pattern 6: DD Month YYYY or DD-Month-YYYY
	if (preg_match('/\b(\d{1,2})[\-\/\s]+([A-Za-z]{3,9})[\-\/\s]+(\d{4})\b/', $s, $m)) {
		$mon = monthNameToNum($m[2]);
		if ($mon) {
			return sprintf('%04d-%02d-%02d', (int)$m[3], $mon, (int)$m[1]);
		}
	}
	// Pattern 7: Month DD, YYYY
	if (preg_match('/\b([A-Za-z]{3,9})\s+(\d{1,2}),?\s+(\d{4})\b/', $s, $m)) {
		$mon = monthNameToNum($m[1]);
		if ($mon) {
			return sprintf('%04d-%02d-%02d', (int)$m[3], $mon, (int)$m[2]);
		}
	}
	return null;
}

function parseFinalExamFormat($content) {
	// Handle the specific "Final_November 2025 Exam Timetable" format
	// Pattern: YYYY/MM/DDMODULECODE_P_1_1EXAMNAME
	// Followed by department, time, duration, candidates, room info across multiple lines
	// Example:
	// 2025/11/03ACC321_P_1_1AUDITING 321
	// SCHOOL DEVELOPMENT 
	// STUDIES	09:00 180 5 51403_0_006 LECTRUE SEMINAR ROOM
	// Mbombela 
	// Campus	32
	
	$rows = [];
	$lines = explode("\n", $content);
	$currentEntry = null;
	$entryLines = []; // Store all lines for current entry
	$skipNextEmpty = false;
	
	foreach ($lines as $lineNum => $line) {
		$originalLine = $line;
		$line = trim($line);
		
		// Skip header lines
		if (preg_match('/^(Final_|Exam date|Exam unique|Exam name|Exam department|Exam slot|Exam duration|Room unique|Room name|Exam total|Exam candidate)/i', $line)) {
			continue;
		}
		
		// Skip standalone numbers (page numbers)
		if (preg_match('/^\d+$/', $line)) {
			$skipNextEmpty = true;
			continue;
		}
		
		// Empty line might end an entry
		if (empty($line)) {
			if ($skipNextEmpty) {
				$skipNextEmpty = false;
				continue;
			}
			// Try to finalize current entry
			if ($currentEntry && $currentEntry['module_code'] && $currentEntry['exam_date'] && $currentEntry['exam_time']) {
				// If duration is missing, default to 180 minutes (common exam duration)
				if (!$currentEntry['duration']) {
					$currentEntry['duration'] = 180;
				}
				// Calculate end time if we have duration
				if ($currentEntry['duration'] && $currentEntry['exam_time']) {
					$startTime = substr($currentEntry['exam_time'], 0, 5);
					$startDt = DateTime::createFromFormat('H:i', $startTime);
					if ($startDt) {
						$startDt->modify('+' . $currentEntry['duration'] . ' minutes');
						$currentEntry['end_time'] = $startDt->format('H:i:s');
					}
				}
				$rows[] = $currentEntry;
			}
			$currentEntry = null;
			$entryLines = [];
			continue;
		}
		
		// Look for the pattern: YYYY/MM/DD followed immediately by module code
		// Example: 2025/11/03ACC321_P_1_1AUDITING 321
		// Also handles: 2025/11/28LSC202_P_1_1CONTRACT LAW SPECIFIC CONTRACTS
		if (preg_match('/^(\d{4}\/\d{2}\/\d{2})([A-Z0-9]+_P_\d+_\d+)([A-Z][A-Z0-9\s]*)/', $line, $m)) {
			// Finalize previous entry if exists
			if ($currentEntry && $currentEntry['module_code'] && $currentEntry['exam_date'] && $currentEntry['exam_time']) {
				// If duration is missing, default to 180 minutes (common exam duration)
				if (!$currentEntry['duration']) {
					$currentEntry['duration'] = 180;
				}
				// Calculate end time if we have duration
				if ($currentEntry['duration'] && $currentEntry['exam_time']) {
					$startTime = substr($currentEntry['exam_time'], 0, 5);
					$startDt = DateTime::createFromFormat('H:i', $startTime);
					if ($startDt) {
						$startDt->modify('+' . $currentEntry['duration'] . ' minutes');
						$currentEntry['end_time'] = $startDt->format('H:i:s');
					}
				}
				$rows[] = $currentEntry;
			}
			
			$dateStr = $m[1];
			$moduleCodeFull = $m[2]; // e.g., ACC321_P_1_1
			
			// Extract module code (before _P_)
			$moduleCode = preg_replace('/_P_\d+_\d+$/', '', $moduleCodeFull);
			
			// Convert date from YYYY/MM/DD to YYYY-MM-DD
			$examDate = str_replace('/', '-', $dateStr);
			
			// Start new entry
			$currentEntry = [
				'module_code' => $moduleCode,
				'exam_date' => $examDate,
				'exam_time' => null,
				'end_time' => null,
				'duration' => null,
				'venue' => null,
				'raw' => $originalLine
			];
			$entryLines = [$originalLine];
			continue;
		}
		
		// If we have a current entry, try to extract remaining fields from subsequent lines
		if ($currentEntry) {
			$entryLines[] = $originalLine;
			$currentEntry['raw'] = implode(' | ', $entryLines);
			
			// The key data line pattern: "STUDIES	09:00 180 5 51403_0_006 LECTRUE SEMINAR ROOM"
			// Or: "STUDIES	09:00 180 3011 1351116_0_001 GREAT HALL & EXAM VENUE"
			// Contains: [optional text + tab] time, duration, total_candidates, candidates_roomed+room_code, room_name
			// Note: Sometimes candidates_roomed and room_code are concatenated (e.g., "1351116_0_001" = "1351" + "116_0_001")
			
			// Pattern 1: Standard format with space between candidates_roomed and room_code
			// e.g., "09:00 180 5 51403_0_006 LECTRUE SEMINAR ROOM"
			if (preg_match('/\b(\d{1,2}):(\d{2})\s+(\d{1,3})\s+\d+\s+\d+\s+(\d+_[-\d]+_[A-Z0-9]+)\s+(.+)$/', $line, $m)) {
				$currentEntry['exam_time'] = sprintf('%02d:%02d:00', (int)$m[1], (int)$m[2]);
				$duration = (int)$m[3];
				if ($duration >= 30 && $duration <= 300) {
					$currentEntry['duration'] = $duration;
				}
				$roomName = trim($m[5]);
				$roomName = preg_replace('/\s+\d+(_\d+)?$/', '', $roomName);
				$roomName = preg_replace('/\s+[A-Z]+\d+$/', '', $roomName);
				$roomName = preg_replace('/\s+\d+$/', '', $roomName);
				if (preg_match('/[A-Z]/', $roomName) && strlen($roomName) > 3) {
					$currentEntry['venue'] = trim($roomName);
				}
			}
			// Pattern 2: Handle concatenated candidates_roomed + room_code
			// e.g., "09:00 180 3011 1351116_0_001" where "1351" + "116_0_001"
			// Or: "09:00 180 3011 351117_0_001" where "35" + "1117_0_001"
			// Strategy: Find room code pattern and extract everything after it as room name
			elseif (preg_match('/\b(\d{1,2}):(\d{2})\s+(\d{1,3})\s+\d+\s+\d+(\d+_[-\d]+_[A-Z0-9]+)\s+(.+)$/', $line, $m)) {
				$currentEntry['exam_time'] = sprintf('%02d:%02d:00', (int)$m[1], (int)$m[2]);
				$duration = (int)$m[3];
				if ($duration >= 30 && $duration <= 300) {
					$currentEntry['duration'] = $duration;
				}
				// $m[4] is the room code (with leading digits from candidates_roomed)
				// $m[5] is the room name
				$roomName = trim($m[5]);
				$roomName = preg_replace('/\s+\d+(_\d+)?$/', '', $roomName);
				$roomName = preg_replace('/\s+[A-Z]+\d+$/', '', $roomName);
				$roomName = preg_replace('/\s+\d+$/', '', $roomName);
				if (preg_match('/[A-Z]/', $roomName) && strlen($roomName) > 3) {
					$currentEntry['venue'] = trim($roomName);
				}
			}
			// Pattern 3: Fallback - extract time and duration if not found yet
			if (!$currentEntry['exam_time'] && preg_match('/\b(\d{1,2}):(\d{2})\s+(\d{1,3})\b/', $line, $m)) {
				$currentEntry['exam_time'] = sprintf('%02d:%02d:00', (int)$m[1], (int)$m[2]);
				$duration = (int)$m[3];
				if ($duration >= 30 && $duration <= 300) {
					$currentEntry['duration'] = $duration;
				}
				
				// Look for room code pattern anywhere after the time
				if (preg_match('/(\d+_[-\d]+_[A-Z0-9]+)\s+(.+)$/', $line, $roomMatch)) {
					$roomName = trim($roomMatch[2]);
					$roomName = preg_replace('/\s+\d+(_\d+)?$/', '', $roomName);
					$roomName = preg_replace('/\s+[A-Z]+\d+$/', '', $roomName);
					$roomName = preg_replace('/\s+\d+$/', '', $roomName);
					if (preg_match('/[A-Z]/', $roomName) && strlen($roomName) > 3) {
						$currentEntry['venue'] = trim($roomName);
					}
				}
			}
			// Extract venue/room - look for room names on lines with room codes
			if (!$currentEntry['venue']) {
				// Pattern 1: Room code followed by room name (e.g., 1126_1_130 MULTI-PURPOSE AUDITORIUM18_130)
				// Also handles: 1153_0_LG001 MULTI_HALL & EXAM VENUE LG001
				if (preg_match('/\d+_[-\d]+_[A-Z0-9]+\s+([A-Z][A-Z0-9\s&\-]+(?:ROOM|VENUE|HALL|LAB|AUDITORIUM|LIBRARY|LABORATORY|PURPOSE|HALL|LABORATORY))/i', $line, $m)) {
					$venue = trim($m[1]);
					// Clean up venue name - remove trailing numbers/codes
					$venue = preg_replace('/\s+\d+(_\d+)?$/', '', $venue); // Remove "18_130", "001"
					$venue = preg_replace('/\s+[A-Z]+\d+$/', '', $venue); // Remove "LG001", "E_101"
					$venue = preg_replace('/\s+\d+$/', '', $venue); // Remove trailing standalone numbers
					if (strlen($venue) > 3) {
						$currentEntry['venue'] = trim($venue);
					}
				}
				// Pattern 2: Just room name (e.g., LECTRUE SEMINAR ROOM, MULTI-PURPOSE AUDITORIUM)
				elseif (preg_match('/([A-Z][A-Z0-9\s&\-]+(?:ROOM|VENUE|HALL|SEMINAR|LECTURE|LAB|AUDITORIUM|LIBRARY|LABORATORY|PURPOSE|HALL))/i', $line, $m)) {
					$venue = trim($m[1]);
					// Make sure it's not just "CAMPUS" and has meaningful content
					if (stripos($venue, 'CAMPUS') === false && strlen($venue) > 5) {
						// Clean up
						$venue = preg_replace('/\s+\d+(_\d+)?$/', '', $venue);
						$venue = preg_replace('/\s+[A-Z]+\d+$/', '', $venue);
						$venue = preg_replace('/\s+\d+$/', '', $venue);
						if (strlen($venue) > 3) {
							$currentEntry['venue'] = trim($venue);
						}
					}
				}
				// Pattern 3: Multi-word venue names (e.g., GREAT HALL & EXAM VENUE, MULTI_HALL & EXAM VENUE)
				elseif (preg_match('/([A-Z][A-Z0-9\s&_\-]+(?:&|AND)\s+[A-Z\s]+(?:VENUE|HALL|ROOM))/i', $line, $m)) {
					$currentEntry['venue'] = trim($m[1]);
				}
			}
			
			// If we have all required fields, we can save (but wait for next entry to finalize)
			// This allows us to capture all venue variations for the same exam
		}
	}
	
	// Don't forget the last entry
	if ($currentEntry && $currentEntry['module_code'] && $currentEntry['exam_date'] && $currentEntry['exam_time']) {
		// If duration is missing, default to 180 minutes (common exam duration)
		if (!$currentEntry['duration']) {
			$currentEntry['duration'] = 180;
		}
		// Calculate end time if we have duration
		if ($currentEntry['duration'] && $currentEntry['exam_time']) {
			$startTime = substr($currentEntry['exam_time'], 0, 5);
			$startDt = DateTime::createFromFormat('H:i', $startTime);
			if ($startDt) {
				$startDt->modify('+' . $currentEntry['duration'] . ' minutes');
				$currentEntry['end_time'] = $startDt->format('H:i:s');
			}
		}
		$rows[] = $currentEntry;
	}
	
	return [
		'rows' => $rows,
		'total' => count($rows)
	];
}

function saveExamRows($rows, $pdo) {
	$total = count($rows);
	$created = 0;
	$skipped = 0;

	$modulesCache = [];
	$venuesCache = [];

	foreach ($rows as $row) {
		$moduleCode = trim($row['module_code']);
		if ($moduleCode === '') { $skipped++; continue; }

		// Resolve module_id (create if missing)
		if (!isset($modulesCache[$moduleCode])) {
			$stmt = $pdo->prepare("SELECT module_id FROM modules WHERE module_code = ?");
			$stmt->execute([$moduleCode]);
			$existing = $stmt->fetch(PDO::FETCH_ASSOC);
			if ($existing) {
				$modulesCache[$moduleCode] = (int)$existing['module_id'];
			} else {
				$stmt = $pdo->prepare("INSERT INTO modules (module_code, module_name, credits) VALUES (?, ?, 0)");
				$stmt->execute([$moduleCode, $moduleCode]);
				$modulesCache[$moduleCode] = (int)$pdo->lastInsertId();
			}
		}
		$moduleId = $modulesCache[$moduleCode];

		// Resolve venue_id (optional)
		$venueId = null;
		$venueName = trim($row['venue'] ?? '');
		if ($venueName !== '') {
			if (!isset($venuesCache[$venueName])) {
				$stmt = $pdo->prepare("SELECT venue_id FROM venues WHERE venue_name = ?");
				$stmt->execute([$venueName]);
				$existing = $stmt->fetch(PDO::FETCH_ASSOC);
				if ($existing) {
					$venuesCache[$venueName] = (int)$existing['venue_id'];
				} else {
					$stmt = $pdo->prepare("INSERT INTO venues (venue_name, capacity) VALUES (?, 0)");
					$stmt->execute([$venueName]);
					$venuesCache[$venueName] = (int)$pdo->lastInsertId();
				}
			}
			$venueId = $venuesCache[$venueName];
		}

		$examDate = $row['exam_date'];
		$examTime = $row['exam_time'];
		$duration = $row['duration'] ?? null;
		if (!$duration && !empty($row['end_time'])) {
			$duration = computeDurationMinutes(substr($examTime, 0, 5), substr($row['end_time'], 0, 5));
		}
		// Default to 180 minutes if duration is still missing (common exam duration)
		if (!$duration) {
			$duration = 180;
		}

		// Avoid duplicates on module_id + date + time
		$check = $pdo->prepare("SELECT exam_id FROM exams WHERE module_id = ? AND exam_date = ? AND exam_time = ?");
		$check->execute([$moduleId, $examDate, $examTime]);
		$exists = $check->fetch(PDO::FETCH_ASSOC);
		if ($exists) { $skipped++; continue; }

		$ins = $pdo->prepare("INSERT INTO exams (module_id, venue_id, exam_date, exam_time, duration) VALUES (?, ?, ?, ?, ?)");
		$ins->execute([$moduleId, $venueId, $examDate, $examTime, (int)$duration]);
		$created++;
	}

	return [
		'total' => $total,
		'created' => $created,
		'skipped' => $skipped
	];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Exam Parser - Smart Timetable</title>
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
		.sidebar { width: 280px; background: linear-gradient(180deg, #0f1419 0%, #1a2332 100%); padding: 30px 20px; border-right: 1px solid rgba(255,255,255,0.1); position: fixed; height: 100vh; overflow-y: auto; }
		.sidebar-header { margin-bottom: 40px; }
		.sidebar-header h1 { font-size: 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 10px; }
		.sidebar-section { margin-bottom: 30px; }
		.sidebar-section-title { font-size: 11px; text-transform: uppercase; letter-spacing: 2px; color: #7f8c8d; margin-bottom: 15px; padding: 0 15px; }
		.sidebar-nav a { display: flex; align-items: center; padding: 12px 15px; color: #b0b0b0; text-decoration: none; border-radius: 8px; margin-bottom: 5px; transition: all 0.3s; }
		.sidebar-nav a:hover, .sidebar-nav a.active { background: rgba(102, 126, 234, 0.2); color: #667eea; }
		.sidebar-nav a i { margin-right: 12px; width: 20px; font-style: normal; font-size: 16px; }
		.main-content { margin-left: 280px; flex: 1; padding: 40px; }
		.upload-card { background: linear-gradient(135deg, #1e2746 0%, #2a3a5a 100%); border-radius: 16px; padding: 40px; margin-bottom: 30px; border: 1px solid rgba(255,255,255,0.1); }
		.upload-card h2 { font-size: 24px; margin-bottom: 12px; }
		.upload-card p { color: #a0a0a0; margin-bottom: 25px; line-height: 1.6; }
		.tags { display: flex; gap: 10px; margin-bottom: 25px; }
		.tag { background: rgba(102, 126, 234, 0.2); padding: 6px 12px; border-radius: 6px; font-size: 12px; color: #667eea; }
		.upload-area { border: 2px dashed rgba(102, 126, 234, 0.5); border-radius: 12px; padding: 40px; text-align: center; background: rgba(102, 126, 234, 0.05); transition: all 0.3s; }
		.upload-area:hover { border-color: #667eea; background: rgba(102, 126, 234, 0.1); }
		.upload-area input[type="file"] { display: none; }
		.upload-btn { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; margin-top: 20px; }
		.upload-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 16px rgba(102, 126, 234, 0.4); }
		.success-banner { background: #27ae60; color: white; padding: 20px; border-radius: 12px; margin-bottom: 30px; display: flex; align-items: center; gap: 12px; }
		.results-card { background: linear-gradient(135deg, #1e2746 0%, #2a3a5a 100%); border-radius: 16px; padding: 30px; border: 1px solid rgba(255,255,255,0.1); }
		.results-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
		.results-header h3 { font-size: 20px; }
		.results-header p { color: #a0a0a0; font-size: 14px; }
		.table { width: 100%; border-collapse: collapse; }
		.table th, .table td { padding: 10px 12px; border-bottom: 1px solid rgba(255,255,255,0.1); text-align: left; font-size: 14px; }
		.table th { color: #7f8c8d; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; font-size: 12px; }
		.action-buttons { display: flex; gap: 15px; margin-top: 25px; }
		.btn { padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-block; transition: all 0.3s; }
		.btn-primary { background: #27ae60; color: white; }
		.btn-secondary { background: #7f8c8d; color: white; }
		.btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.3); }
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
					<a href="admin/index.php"><i>📊</i> Dashboard</a>
				</nav>
			</div>

			<div class="sidebar-section">
				<div class="sidebar-section-title">Curriculum & Timetable</div>
				<nav class="sidebar-nav">
					<a href="admin/modules.php"><i>📚</i> Modules</a>
					<a href="admin/timetable.php"><i>➕</i> Add Session</a>
					<a href="timetable_editor.php"><i>✏️</i> Edit Sessions</a>
					<a href="view_timetable.php"><i>📋</i> View Timetable</a>
					<a href="timetable_pdf_parser.php"><i>📤</i> Upload Timetable</a>
					<a href="admin/exams.php"><i>📆</i> Exam Timetables</a>
					<a href="exam_pdf_parser.php" class="active"><i>📤</i> Upload Exam Timetable</a>
				</nav>
			</div>
		</div>

		<!-- Main Content -->
		<div class="main-content">
			<div style="margin-bottom: 24px;">
				<a href="admin/index.php" style="display: inline-flex; align-items: center; gap: 8px; color: rgba(255,255,255,0.7); text-decoration: none; font-size: 14px; padding: 8px 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; transition: all 0.2s;" onmouseover="this.style.background='rgba(102, 126, 234, 0.1)'; this.style.borderColor='rgba(102, 126, 234, 0.3)'; this.style.color='#667eea';" onmouseout="this.style.background='rgba(255,255,255,0.05)'; this.style.borderColor='rgba(255,255,255,0.1)'; this.style.color='rgba(255,255,255,0.7)';">
					<span>←</span>
					<span>Back to Dashboard</span>
				</a>
			</div>

			<div class="upload-card">
				<h2>Upload Exam Timetable (PDF or TXT)</h2>
				<p>Supports formats like "final_nov..." official timetables. We detect module, date, time, venue, and duration automatically.</p>
				<div class="tags">
					<span class="tag">📄 PDF & TXT supported</span>
					<span class="tag">✨ Auto-detect common formats</span>
					<span class="tag">🗓️ Computes duration from time range</span>
				</div>
				<form method="POST" enctype="multipart/form-data">
					<div class="upload-area">
						<div style="font-size: 48px; margin-bottom: 15px;">📄</div>
						<p style="margin-bottom: 15px;">Drag and drop your file here or click to browse</p>
						<input type="file" name="exam_file" id="fileInput" accept=".txt,.pdf" required>
						<label for="fileInput" class="upload-btn">Choose File</label>
						<div id="fileName" style="margin-top: 15px; color: #667eea;"></div>
					</div>
					<button type="submit" class="upload-btn" style="width: 100%; margin-top: 20px;">Parse Exams</button>
				</form>
			</div>

			<?php if ($message): ?>
			<div class="success-banner" style="background: <?= $messageType === 'error' ? '#e74c3c' : ($messageType === 'info' ? '#3498db' : '#27ae60') ?>;">
				<span><?= $messageType === 'error' ? '❌' : ($messageType === 'info' ? 'ℹ️' : '✅') ?></span>
				<span><?= htmlspecialchars($message) ?></span>
			</div>
			<?php endif; ?>

			<?php if ($previewData && !$importResults): ?>
			<div class="results-card">
				<div class="results-header">
					<div>
						<h3>Preview: Exam Entries</h3>
						<p>Review parsed exams. Click "Confirm & Import" to save to database.</p>
					</div>
					<span class="tag">🔎 <?= (int)$previewData['total'] ?> detected</span>
				</div>
				<div style="overflow:auto;">
					<table class="table">
						<thead>
							<tr>
								<th>Module</th>
								<th>Date</th>
								<th>Time</th>
								<th>Duration</th>
								<th>Venue</th>
								<th>Source</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($previewData['rows'] as $r): ?>
							<tr>
								<td><?= htmlspecialchars($r['module_code']) ?></td>
								<td><?= htmlspecialchars($r['exam_date']) ?></td>
								<td><?= htmlspecialchars(substr($r['exam_time'],0,5)) ?></td>
								<td><?= htmlspecialchars((string)($r['duration'] ?? '')) ?><?= $r['duration'] ? ' min' : '' ?></td>
								<td><?= htmlspecialchars($r['venue'] ?? '') ?></td>
								<td><small style="color: rgba(255,255,255,0.5)"><?= htmlspecialchars($r['raw']) ?></small></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<form method="POST" style="margin-top: 25px;">
					<input type="hidden" name="preview_data" value='<?= htmlspecialchars(json_encode($previewData), ENT_QUOTES) ?>'>
					<div class="action-buttons">
						<button type="submit" name="confirm_exam_import" class="btn btn-primary">✅ Confirm & Import</button>
						<a href="exam_pdf_parser.php" class="btn btn-secondary">❌ Cancel</a>
					</div>
				</form>
			</div>
			<?php endif; ?>

			<?php if ($importResults): ?>
			<div class="results-card">
				<div class="results-header">
					<div>
						<h3>Import Complete</h3>
						<p>Created: <?= (int)$importResults['created'] ?> • Skipped: <?= (int)$importResults['skipped'] ?></p>
					</div>
				</div>
				<div class="action-buttons">
					<a href="admin/exams.php" class="btn btn-primary">📆 View Exams</a>
					<a href="exam_pdf_parser.php" class="btn btn-secondary">📄 Parse another file</a>
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


