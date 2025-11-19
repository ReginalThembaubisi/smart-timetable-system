<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
	header('Location: admin/login.php');
	exit;
}

require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/includes/database.php';

// Composer autoload (for PDF parser)
$vendorAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
	require_once $vendorAutoload;
}

$pdo = Database::getInstance()->getConnection();

function computeDurationMinutes($start, $end) {
	$startDt = DateTime::createFromFormat('H:i', $start);
	$endDt = DateTime::createFromFormat('H:i', $end);
	if (!$startDt || !$endDt) return null;
	$diff = $endDt->getTimestamp() - $startDt->getTimestamp();
	if ($diff < 0) $diff += 24 * 3600;
	return (int)round($diff / 60);
}

// Ensure exam_status column exists
try {
	$col = $pdo->query("SHOW COLUMNS FROM exams LIKE 'exam_status'")->fetch(PDO::FETCH_ASSOC);
	if (!$col) {
		$pdo->exec("ALTER TABLE exams ADD COLUMN exam_status VARCHAR(20) NOT NULL DEFAULT 'final' AFTER duration, ADD INDEX idx_exam_status (exam_status)");
	}
} catch (Throwable $t) {
	// ignore if cannot alter, inserts will fallback to default/NULL
}

function normalizeTimeFragments($s) {
	// Normalize common time formats: 09H00 -> 09:00, 09h00 -> 09:00
	$s = preg_replace('/\b(\d{1,2})\s*[hH]\s*([0-5]\d)\b/', '$1:$2', $s);
	$s = preg_replace('/\b(\d{1,2})\s*[hH]\s*00\b/', '$1:00', $s);
	// Normalize am/pm if present → 24h not handled here; keep as HH:MM
	return $s;
}

function normalizeDashes($s) {
	// Replace en/em dashes and long hyphens with simple hyphen
	return str_replace(["–", "—", "−"], "-", $s);
}

function monthNameToNum($mon) {
	$map = [
		'JAN'=>1,'FEB'=>2,'MAR'=>3,'APR'=>4,'MAY'=>5,'JUN'=>6,
		'JUL'=>7,'AUG'=>8,'SEP'=>9,'SEPT'=>9,'OCT'=>10,'NOV'=>11,'DEC'=>12
	];
	$u = strtoupper($mon);
	return $map[$u] ?? null;
}

function tryParseDateString($s) {
	// Try YYYY-MM-DD first
	if (preg_match('/\b(\d{4})-(\d{1,2})-(\d{1,2})\b/', $s, $m)) {
		return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
	}
	// Try YYYY/MM/DD
	if (preg_match('/\b(\d{4})\/(\d{1,2})\/(\d{1,2})\b/', $s, $m)) {
		return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
	}
	// Try DD/MM/YYYY
	if (preg_match('/\b(\d{1,2})\/(\d{1,2})\/(\d{4})\b/', $s, $m)) {
		return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
	}
	// Try DD-MMM-YYYY or DD MMM YYYY (month as name)
	if (preg_match('/\b(\d{1,2})[\-\/\s]([A-Za-z]{3,4})[\-\/\s](\d{4})\b/', $s, $m)) {
		$mon = monthNameToNum($m[2]);
		if ($mon) {
			return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$mon, (int)$m[1]);
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
				// Only remove truly unnecessary suffixes, but preserve room numbers that distinguish venues
				// Keep patterns like "001", "002", "101", "102" as they distinguish different rooms
				if (preg_match('/^(.+?)\s+(\d{3,})$/', $roomName, $roomMatch)) {
					// If ends with 3+ digits, keep them (e.g., "001", "002", "101")
					$currentEntry['venue'] = trim($roomName);
				} else {
					// Only clean up complex patterns
					$roomName = preg_replace('/\s+\d+(_\d+)+$/', '', $roomName); // Remove "18_130" type patterns
					$roomName = preg_replace('/\s+[A-Z]+_\d+$/', '', $roomName); // Remove "E_101" type patterns
					if (preg_match('/[A-Z]/', $roomName) && strlen($roomName) > 3) {
						$currentEntry['venue'] = trim($roomName);
					}
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
				// Only remove truly unnecessary suffixes, but preserve room numbers that distinguish venues
				// Keep patterns like "001", "002", "101", "102" as they distinguish different rooms
				if (preg_match('/^(.+?)\s+(\d{3,})$/', $roomName, $roomMatch)) {
					// If ends with 3+ digits, keep them (e.g., "001", "002", "101")
					$currentEntry['venue'] = trim($roomName);
				} else {
					// Only clean up complex patterns
					$roomName = preg_replace('/\s+\d+(_\d+)+$/', '', $roomName); // Remove "18_130" type patterns
					$roomName = preg_replace('/\s+[A-Z]+_\d+$/', '', $roomName); // Remove "E_101" type patterns
					if (preg_match('/[A-Z]/', $roomName) && strlen($roomName) > 3) {
						$currentEntry['venue'] = trim($roomName);
					}
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
					// Only remove truly unnecessary suffixes, but preserve room numbers that distinguish venues
					if (preg_match('/^(.+?)\s+(\d{3,})$/', $roomName, $roomNumMatch)) {
						// If ends with 3+ digits, keep them (e.g., "001", "002", "101")
						$currentEntry['venue'] = trim($roomName);
					} else {
						// Only clean up complex patterns
						$roomName = preg_replace('/\s+\d+(_\d+)+$/', '', $roomName); // Remove "18_130" type patterns
						$roomName = preg_replace('/\s+[A-Z]+_\d+$/', '', $roomName); // Remove "E_101" type patterns
						if (preg_match('/[A-Z]/', $roomName) && strlen($roomName) > 3) {
							$currentEntry['venue'] = trim($roomName);
						}
					}
				}
			}
			// Extract venue/room - look for room names on lines with room codes
			if (!$currentEntry['venue']) {
				// Pattern 1: Room code followed by room name (e.g., 1126_1_130 MULTI-PURPOSE AUDITORIUM18_130)
				// Also handles: 1153_0_LG001 MULTI_HALL & EXAM VENUE LG001
				if (preg_match('/\d+_[-\d]+_[A-Z0-9]+\s+([A-Z][A-Z0-9\s&\-]+(?:ROOM|VENUE|HALL|LAB|AUDITORIUM|LIBRARY|LABORATORY|PURPOSE|HALL|LABORATORY))/i', $line, $m)) {
					$venue = trim($m[1]);
					// Only remove truly unnecessary suffixes, but preserve room numbers that distinguish venues
					// Keep patterns like "001", "002", "101", "102" as they distinguish different rooms
					if (preg_match('/^(.+?)\s+(\d{3,})$/', $venue, $venueMatch)) {
						// If ends with 3+ digits, keep them (e.g., "001", "002", "101")
						$currentEntry['venue'] = trim($venue);
					} else {
						// Only clean up complex patterns
						$venue = preg_replace('/\s+\d+(_\d+)+$/', '', $venue); // Remove "18_130" type patterns
						$venue = preg_replace('/\s+[A-Z]+_\d+$/', '', $venue); // Remove "E_101" type patterns
						if (strlen($venue) > 3) {
							$currentEntry['venue'] = trim($venue);
						}
					}
				}
				// Pattern 2: Just room name (e.g., LECTRUE SEMINAR ROOM, MULTI-PURPOSE AUDITORIUM)
				elseif (preg_match('/([A-Z][A-Z0-9\s&\-]+(?:ROOM|VENUE|HALL|SEMINAR|LECTURE|LAB|AUDITORIUM|LIBRARY|LABORATORY|PURPOSE|HALL))/i', $line, $m)) {
					$venue = trim($m[1]);
					// Make sure it's not just "CAMPUS" and has meaningful content
					if (stripos($venue, 'CAMPUS') === false && strlen($venue) > 5) {
						// Only remove truly unnecessary suffixes, but preserve room numbers that distinguish venues
						if (preg_match('/^(.+?)\s+(\d{3,})$/', $venue, $venueMatch)) {
							// If ends with 3+ digits, keep them (e.g., "001", "002", "101")
							$currentEntry['venue'] = trim($venue);
						} else {
							// Only clean up complex patterns
							$venue = preg_replace('/\s+\d+(_\d+)+$/', '', $venue); // Remove "18_130" type patterns
							$venue = preg_replace('/\s+[A-Z]+_\d+$/', '', $venue); // Remove "E_101" type patterns
							if (strlen($venue) > 3) {
								$currentEntry['venue'] = trim($venue);
							}
						}
					}
				}
				// Pattern 3: Multi-word venue names (e.g., GREAT HALL & EXAM VENUE, MULTI_HALL & EXAM VENUE)
				elseif (preg_match('/([A-Z][A-Z0-9\s&_\-]+(?:&|AND)\s+[A-Z\s]+(?:VENUE|HALL|ROOM))/i', $line, $m)) {
					$currentEntry['venue'] = trim($m[1]);
				}
			}
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

function parseExamContentPreview($content) {
	// Normalize line endings
	$content = str_replace(["\r\n", "\r"], "\n", $content);
	
	// First, try to detect and handle the specific "Final_November" format
	// This format has data spread across multiple lines
	if (strpos($content, 'Final_') !== false || strpos($content, 'Exam Timetable') !== false) {
		return parseFinalExamFormat($content);
	}
	
	$lines = preg_split('/\n/', $content);
	$rows = [];

	foreach ($lines as $line) {
		$line = normalizeDashes($line);
		$line = normalizeTimeFragments($line);
		$line = trim(preg_replace('/\s+/', ' ', $line));
		if ($line === '' || strlen($line) < 6) continue;

		$moduleCode = null;
		$examDate = null;
		$startTime = null;
		$endTime = null;
		$durationMin = null;
		$venue = null;

		// Special handling for lines that begin with YYYY/MM/DD immediately followed by Exam unique name
		// Example:
		// 2025/11/03FEP302_P_1_3 EDUCATIONAL PSYCHOLOGY 302 SCHOOL EARLY CHILDHOOD EDU 09:00 180 ... 3011_0_004 MAIN HALL A EXAM Siyabuswa Campus 250
		if (preg_match('/^(\d{4}\/\d{2}\/\d{2})(.+)$/', $line, $mDateRow)) {
			$examDate = tryParseDateString($mDateRow[1]);
			$rowRemainder = ltrim($mDateRow[2]);
			if (preg_match('/^([A-Z0-9_]+)/', $rowRemainder, $mUniq)) {
				$uniq = $mUniq[1];
				if (preg_match('/^([A-Z]{2,}\d{2,})/', $uniq, $mMod)) {
					$moduleCode = strtoupper($mMod[1]);
				}
			}
			if (preg_match('/\b(\d{1,2}[:\.][0-5]\d)\b/', $rowRemainder, $mTime)) {
				$t1 = str_replace('.', ':', $mTime[1]);
				$startTime = sprintf('%s:00', $t1);
				// Duration appears after time as 2-3 digits
				if (preg_match('/\b' . preg_quote($mTime[1], '/') . '\b\s+(\d{2,3})\b/', $rowRemainder, $mDur)) {
					$durationMin = (int)$mDur[1];
				}
			}
			// Venue comes after room unique name like 3011_0_004
			if (preg_match('/\b\d{3,}_[0-9]_\d{3,}\s+(.+?)(?:\s+[A-Za-z].*?Campus\b|\s+\d{2,3}\b|$)/i', $rowRemainder, $mVenue)) {
				$venue = trim($mVenue[1]);
			}
		}

		// Module codes like CS101, CS-101, CS 101, ACCT201/1, or from Exam unique name like HSM502_P_1_3
		if (!$moduleCode && preg_match('/\b([A-Z]{2,}\s*[-\/]?\s*[A-Z0-9]{1,}\s*\d{2,}(?:\/\d+)?)\b/', $line, $m)) {
			$moduleCode = preg_replace('/\s+/', '', str_replace(' ', '', strtoupper($m[1])));
			$moduleCode = preg_replace('/\s+/', '', $moduleCode);
			$moduleCode = str_replace(' ', '', $moduleCode);
			$moduleCode = preg_replace('/\s+/', '', $moduleCode);
			$moduleCode = strtoupper(str_replace(' ', '', $m[1]));
			$moduleCode = preg_replace('/\s+/', '', $moduleCode);
			$moduleCode = preg_replace('/\s*-\s*/', '-', $moduleCode);
			$moduleCode = preg_replace('/\s*\/\s*/', '/', $moduleCode);
		} elseif (!$moduleCode && preg_match('/\b([A-Z]{2,}\d{2,})(?=_[A-Z0-9_]+)/', $line, $m)) {
			$moduleCode = strtoupper($m[1]);
		} elseif (!$moduleCode && preg_match('/\b([A-Z]{2,}\s*\d{2,})\b/', $line, $m)) {
			$moduleCode = $m[1];
		}

		if (!$examDate) {
			$examDate = tryParseDateString($line);
		}

		// Time ranges like 09:00 - 12:00 or 09.00 - 12.00
		if (!$startTime && preg_match('/\b(\d{1,2}[:\.][0-5]\d)\s*-\s*(\d{1,2}[:\.][0-5]\d)\b/', $line, $m)) {
			$t1 = str_replace('.', ':', $m[1]);
			$t2 = str_replace('.', ':', $m[2]);
			$startTime = sprintf('%s:00', $t1);
			$endTime = sprintf('%s:00', $t2);
			$durationMin = $durationMin ?? computeDurationMinutes(substr($t1,0,5), substr($t2,0,5));
		} elseif (!$startTime && preg_match('/\b(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2})\b/', $line, $m)) {
			$startTime = $m[1] . ':00';
			$endTime = $m[2] . ':00';
			$durationMin = $durationMin ?? computeDurationMinutes($m[1], $m[2]);
		} elseif (!$startTime && preg_match('/\b(\d{2}:\d{2})\b/', $line, $m)) {
			$startTime = $m[1] . ':00';
		} elseif (!$startTime && preg_match('/\b(\d{1,2})\s*[:hH]\s*([0-5]\d)\b/', $line, $m)) {
			$hh = sprintf('%02d', (int)$m[1]);
			$mm = sprintf('%02d', (int)$m[2]);
			$startTime = $hh . ':' . $mm . ':00';
		}

		if (preg_match('/\b(\d{1,3})\s*(min|mins|minutes)\b/i', $line, $m)) {
			$durationMin = (int)$m[1];
		} elseif (preg_match('/\b(\d(?:\.\d)?)\s*(h|hr|hrs|hour|hours)\b/i', $line, $m)) {
			$durationMin = (int)round(((float)$m[1]) * 60);
		} elseif (preg_match('/\b(\d)\s*(hour|hours|hr|hrs)\b/i', $line, $m)) {
			$durationMin = ((int)$m[1]) * 60;
		} elseif (!$durationMin && $startTime && preg_match('/\b\d{1,2}[:\.hH][0-5]\d\b\s+(\d{2,3})\b/', $line, $m)) {
			$durationMin = (int)$m[1];
		}

		if (preg_match('/Venue:\s*([^|,]+)$/i', $line, $m)) {
			$venue = trim($m[1]);
		} else {
			if ($endTime && preg_match('/\d{2}:\d{2}\s+(.+)$/', $line, $m)) {
				$venue = trim($m[1]);
			}
			if ($venue) {
				$venue = trim(preg_replace('/^[\-\|,]\s*/', '', $venue));
			}
			// Columnar PDF: split by 2+ spaces and map columns heuristically
			if (!$venue) {
				$cols = preg_split('/\s{2,}/', $line);
				if (count($cols) >= 3) {
					if (!$moduleCode && preg_match('/^[A-Z].*\\d/', $cols[0])) {
						$moduleCode = strtoupper(preg_replace('/\s+/', '', $cols[0]));
					}
					if (!$examDate) {
						$examDate = tryParseDateString($line) ?? tryParseDateString($cols[1]);
					}
					// Time in its own column
					if (!$startTime && preg_match('/(\d{1,2}[:\.][0-5]\d)\b/', $line, $tm)) {
						$t1 = str_replace('.', ':', $tm[1]);
						$startTime = sprintf('%s:00', $t1);
					}
					// Duration likely next numeric column (e.g., 180)
					if ($startTime && !$durationMin) {
						for ($i = 0; $i < count($cols); $i++) {
							if (preg_match('/\b\d{1,2}[:\.][0-5]\d\b/', $cols[$i])) {
								$next = $cols[$i+1] ?? '';
								if (preg_match('/^\d{2,3}$/', trim($next))) {
									$durationMin = (int)trim($next);
								}
								break;
							}
						}
					}
					// Venue likely last column
					$venue = $venue ?? trim(end($cols));
				}
				// Try room unique name pattern to venue (e.g., 3011_0_004 MAIN HALL A EXAM ...)
				if (!$venue && preg_match('/\b\d{3,}_[0-9]_\d{3,}\s+(.+?)(?:\s+[A-Za-z].*?Campus\b|\s+\d{2,3}\b|$)/i', $line, $mRoom)) {
					$venue = trim($mRoom[1]);
				}
			}
		}

		if (!$moduleCode || !$examDate || !$startTime) {
			continue;
		}

		$rows[] = [
			'module_code' => $moduleCode,
			'exam_date' => $examDate,
			'exam_time' => $startTime,
			'end_time' => $endTime,
			'duration' => $durationMin,
			'venue' => $venue,
			'raw' => $line
		];
	}

	return [
		'rows' => $rows,
		'total' => count($rows)
	];
}

function saveExamRows($rows, $pdo, $status = 'final') {
	$total = count($rows);
	$created = 0;
	$skipped = 0;
	$modulesCache = [];
	$venuesCache = [];
	$skipReasons = []; // Track why entries are skipped

	foreach ($rows as $row) {
		$moduleCode = trim($row['module_code']);
		if ($moduleCode === '') { 
			$skipped++; 
			$skipReasons[] = [
				'reason' => 'Empty module code',
				'data' => $row['raw'] ?? 'N/A'
			];
			continue; 
		}

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

		// Check for duplicates: same module, date, time, AND venue
		// Different venues for the same exam are allowed (e.g., large class split across multiple rooms)
		if ($venueId) {
			$check = $pdo->prepare("SELECT e.exam_id, m.module_code, v.venue_name, e.exam_date, e.exam_time FROM exams e LEFT JOIN modules m ON e.module_id = m.module_id LEFT JOIN venues v ON e.venue_id = v.venue_id WHERE e.module_id = ? AND e.exam_date = ? AND e.exam_time = ? AND e.venue_id = ?");
			$check->execute([$moduleId, $examDate, $examTime, $venueId]);
		} else {
			// If no venue specified, check for exact match without venue
			$check = $pdo->prepare("SELECT e.exam_id, m.module_code, v.venue_name, e.exam_date, e.exam_time FROM exams e LEFT JOIN modules m ON e.module_id = m.module_id LEFT JOIN venues v ON e.venue_id = v.venue_id WHERE e.module_id = ? AND e.exam_date = ? AND e.exam_time = ? AND (e.venue_id IS NULL OR e.venue_id = 0)");
			$check->execute([$moduleId, $examDate, $examTime]);
		}
		$exists = $check->fetch(PDO::FETCH_ASSOC);
		if ($exists) { 
			$skipped++; 
			$skipReasons[] = [
				'reason' => 'Duplicate exam (same module, date, time, and venue already exists)',
				'module' => $moduleCode,
				'date' => $examDate,
				'time' => $examTime,
				'venue' => $venueName ?: 'N/A',
				'existing_exam_id' => $exists['exam_id'],
				'existing_module' => $exists['module_code'] ?? 'N/A',
				'existing_venue' => $exists['venue_name'] ?? 'N/A',
				'raw' => $row['raw'] ?? 'N/A'
			];
			continue; 
		}

		if ($pdo->query("SHOW COLUMNS FROM exams LIKE 'exam_status'")->fetch(PDO::FETCH_ASSOC)) {
			$ins = $pdo->prepare("INSERT INTO exams (module_id, venue_id, exam_date, exam_time, duration, exam_status) VALUES (?, ?, ?, ?, ?, ?)");
			$ins->execute([$moduleId, $venueId, $examDate, $examTime, (int)$duration, $status ?: 'final']);
		} else {
			$ins = $pdo->prepare("INSERT INTO exams (module_id, venue_id, exam_date, exam_time, duration) VALUES (?, ?, ?, ?, ?)");
			$ins->execute([$moduleId, $venueId, $examDate, $examTime, (int)$duration]);
		}
		$examId = (int)$pdo->lastInsertId();
		$created++;
		
		// Create notifications for all students enrolled in this module
		try {
			// Ensure exam_notifications table exists
			$pdo->exec("CREATE TABLE IF NOT EXISTS exam_notifications (
				notification_id INT AUTO_INCREMENT PRIMARY KEY,
				student_id INT NOT NULL,
				exam_id INT NULL,
				title VARCHAR(255) NULL,
				message TEXT NULL,
				is_read TINYINT(1) NOT NULL DEFAULT 0,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				INDEX idx_student_id (student_id),
				INDEX idx_exam_id (exam_id),
				FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
				FOREIGN KEY (exam_id) REFERENCES exams(exam_id) ON DELETE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
			
			// Get all students enrolled in this module
			$stmt = $pdo->prepare("SELECT DISTINCT student_id FROM student_modules WHERE module_id = ? AND status = 'active'");
			$stmt->execute([$moduleId]);
			$enrolledStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
			// Get module and venue info for notification message
			$moduleStmt = $pdo->prepare("SELECT module_code, module_name FROM modules WHERE module_id = ?");
			$moduleStmt->execute([$moduleId]);
			$moduleInfo = $moduleStmt->fetch(PDO::FETCH_ASSOC);
			
			$venueNameForMsg = $venueName ?: 'TBA';
			$examDateFormatted = date('F j, Y', strtotime($examDate));
			$examTimeFormatted = date('g:i A', strtotime($examTime));
			
			$title = "New Exam Scheduled: {$moduleInfo['module_code']}";
			$message = "Exam scheduled for {$moduleInfo['module_code']} ({$moduleInfo['module_name']}) on {$examDateFormatted} at {$examTimeFormatted} in {$venueNameForMsg}.";
			
			// Create notification for each enrolled student
			$notifStmt = $pdo->prepare("INSERT INTO exam_notifications (student_id, exam_id, title, message, is_read) VALUES (?, ?, ?, ?, 0)");
			foreach ($enrolledStudents as $student) {
				try {
					$notifStmt->execute([$student['student_id'], $examId, $title, $message]);
				} catch (Exception $e) {
					// Skip if notification creation fails (might be duplicate or constraint issue)
					error_log("Failed to create notification for student {$student['student_id']}, exam {$examId}: " . $e->getMessage());
				}
			}
		} catch (Exception $e) {
			// Don't fail the import if notification creation fails
			error_log("Failed to create exam notifications: " . $e->getMessage());
		}
	}

	// Save skip reasons to session for display
	$_SESSION['exam_import_skip_reasons'] = $skipReasons;
	$_SESSION['exam_import_skip_count'] = $skipped;
	
	return [
		'total' => $total,
		'created' => $created,
		'skipped' => $skipped,
		'skip_reasons' => $skipReasons
	];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
	$file = $_FILES['file'];
	if ($file['error'] === UPLOAD_ERR_OK) {
		$fileTmp = $file['tmp_name'];
		$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
		$content = '';

		if ($ext === 'txt') {
			$content = file_get_contents($fileTmp);
		} elseif ($ext === 'pdf') {
			if (class_exists(\Smalot\PdfParser\Parser::class)) {
				try {
					$parser = new \Smalot\PdfParser\Parser();
					$pdf = $parser->parseFile($fileTmp);
					$content = $pdf->getText();
				} catch (Exception $e) {
					$_SESSION['error_message'] = 'Could not parse PDF: ' . $e->getMessage();
					header('Location: admin/exams.php');
					exit;
				}
			} else {
				$_SESSION['error_message'] = 'PDF parser missing. Run composer install.';
				header('Location: admin/exams.php');
				exit;
			}
		} else {
			$_SESSION['error_message'] = 'Unsupported file type. Upload TXT or PDF.';
			header('Location: admin/exams.php');
			exit;
		}

		// Write raw extracted text to logs for debugging
		try {
			@file_put_contents(__DIR__ . '/logs/last_exam_text.txt', $content ?: '(empty)');
		} catch (Throwable $t) {
			// ignore
		}

		$preview = parseExamContentPreview($content);
		if ($preview['total'] <= 0) {
			$_SESSION['error_message'] = 'No exam entries detected in file.';
			header('Location: admin/exams.php');
			exit;
		}

		$status = isset($_POST['exam_status']) && in_array($_POST['exam_status'], ['draft','final']) ? $_POST['exam_status'] : 'final';
		$res = saveExamRows($preview['rows'], $pdo, $status);
		$_SESSION['success_message'] = "Imported {$res['created']} of {$res['total']} exams. Skipped {$res['skipped']}.";
		header('Location: admin/exams.php');
		exit;
	} else {
		$_SESSION['error_message'] = 'Upload error.';
		header('Location: admin/exams.php');
		exit;
	}
}

header('Location: admin/exams.php');
exit;


