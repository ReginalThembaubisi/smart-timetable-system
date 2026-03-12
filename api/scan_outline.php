<?php
/**
 * POST /api/scan_outline.php
 *
 * Accepts either:
 *   A) multipart/form-data  with fields: module_code + file (PDF/DOCX/TXT)
 *   B) application/json     with fields: module_code + text
 *
 * Response:
 *   { "success": true, "data": { "events": [...] } }
 */

require_once __DIR__ . '/../includes/api_helpers.php';
require_once __DIR__ . '/../includes/env.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(false, null, 'Method not allowed', 405);
}

// ── Read input (file upload OR JSON text) ─────────────────────────────────────
$moduleCode    = '';
$syllabusText  = '';
$isUploadedDocument = false;
$uploadedFileTmp = '';
$uploadedFileName = '';

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (str_contains($contentType, 'multipart/form-data')) {
    // ── FILE UPLOAD path ──────────────────────────────────────────────────────
    $moduleCode = trim($_POST['module_code'] ?? '');
    if (empty($moduleCode)) {
        sendJSONResponse(false, null, 'module_code is required.', 400);
    }

    if (empty($_FILES['file']['tmp_name'])) {
        sendJSONResponse(false, null, 'No file uploaded.', 400);
    }

    $fileTmp  = $_FILES['file']['tmp_name'];
    $fileName = $_FILES['file']['name'] ?? '';
    $fileSize = $_FILES['file']['size'] ?? 0;
    $fileType = $_FILES['file']['type'] ?? '';

    if ($fileSize > 10 * 1024 * 1024) { // 10 MB limit
        sendJSONResponse(false, null, 'File is too large (max 10 MB).', 400);
    }

    // Extract text from supported document formats.
    $syllabusText = extractTextFromUploadedFile($fileTmp, $fileName, $fileType);
    $isUploadedDocument = true;
    $uploadedFileTmp = $fileTmp;
    $uploadedFileName = $fileName;

} else {
    // ── JSON TEXT path ────────────────────────────────────────────────────────
    $data = getJSONInput();
    validateRequired($data, ['text', 'module_code']);
    $moduleCode   = trim($data['module_code']);
    $syllabusText = trim($data['text']);
}

if (empty($moduleCode)) {
    sendJSONResponse(false, null, 'module_code is required.', 400);
}
if (strlen($syllabusText) < 10) {
    $pdfAvailable = function_exists('exec') ? shell_exec('which pdftotext 2>/dev/null') : 'exec disabled';
    $ocrAvailable = function_exists('exec') ? shell_exec('which tesseract 2>/dev/null') : 'exec disabled';
    sendJSONResponse(
        false,
        null,
        'Could not extract readable text from the uploaded document. Supported formats: PDF, DOCX, TXT. ' .
        'pdftotext: ' . trim($pdfAvailable ?: 'not found') . '; OCR(tesseract): ' . trim($ocrAvailable ?: 'not found') .
        '. You can still paste the text directly.',
        400
    );
}

// Truncate to avoid token limits (~60 000 chars ≈ 15 000 tokens)
$syllabusText = mb_substr($syllabusText, 0, 60000);

// Prefer deterministic parsers for uploaded files (PDF/DOCX/TXT).
// Upload flow should not depend on noisy AI JSON output.
if ($isUploadedDocument) {
    $pythonEvents = extractEventsWithPython($uploadedFileTmp, $uploadedFileName, $moduleCode) ?? [];
    $deterministic = $pythonEvents;
    if (empty($deterministic)) {
        $deterministic = extractEventsFromTextHeuristic($syllabusText, $moduleCode);
    }
    if (!empty($deterministic)) {
        sendJSONResponse(true, ['events' => $deterministic], 'Events extracted successfully');
    }
    sendJSONResponse(true, ['events' => []], 'No assessment dates found in uploaded document.');
}

// ── API key: prefer Groq (generous free tier), fall back to Gemini ────────────
$groqKey   = getenv('GROQ_API_KEY');
$geminiKey = getenv('GEMINI_API_KEY');

if (empty($groqKey) && empty($geminiKey)) {
    sendJSONResponse(false, null, 'No AI API key configured on the server.', 500);
}

$useGroq = !empty($groqKey);

// ── Prompt (shared) ───────────────────────────────────────────────────────────
$prompt = <<<PROMPT
You are an academic assistant for a university student. Your goal is to find all important assessment dates from the syllabus/outline text below.

Extract events such as:
- Tests (Test 1, Semester Test, Class Test, etc.)
- Assignments (Submission dates, Projects, Lab reports)
- Exams (Final Exams, Assessments)
- Practicals or Lab sessions with specific dates

For each event, find:
1. title: A descriptive name (e.g., "Assignment 1: Data Structures")
2. date: The date in YYYY-MM-DD format. If only day/month is given, assume the year is 2026.
3. type: One of these exact strings: "Test", "Assignment", "Exam", "Practical".
4. time: The start time (e.g., "09:30"), or null if not found.
5. venue: The location (e.g., "Building 5, Room 202"), or null if not found.

Output ONLY a valid JSON array. No markdown, no explanation.
Example: [{"title":"Test 1","date":"2026-03-20","type":"Test","time":"14:00","venue":"Lab"}]
If no events are found, return: []

Module code: {$moduleCode}

Syllabus text:
---
{$syllabusText}
---
PROMPT;

// ── Call AI API ───────────────────────────────────────────────────────────────
if ($useGroq) {
    // Groq — OpenAI-compatible, very generous free tier
    $url     = 'https://api.groq.com/openai/v1/chat/completions';
    $payload = json_encode([
        'model'       => 'llama-3.1-8b-instant',
        'messages'    => [['role' => 'user', 'content' => $prompt]],
        'temperature' => 0.1,
        'max_tokens'  => 2048,
    ]);
    $headers = [
        'Content-Type: application/json',
        "Authorization: Bearer {$groqKey}",
    ];
} else {
    // Gemini fallback
    $url     = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$geminiKey}";
    $payload = json_encode([
        'contents'         => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['temperature' => 0.1, 'maxOutputTokens' => 2048],
    ]);
    $headers = ['Content-Type: application/json'];
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_TIMEOUT        => 25,
]);

$raw      = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    sendJSONResponse(false, null, 'Network error contacting AI: ' . $curlErr, 502);
}

if ($httpCode === 429) {
    sendJSONResponse(false, null, 'AI is busy — please wait 30 seconds and try again.', 429);
}

if ($httpCode !== 200) {
    $body = json_decode($raw, true);
    $msg  = $body['error']['message'] ?? "AI API error (HTTP {$httpCode})";
    sendJSONResponse(false, null, $msg, 502);
}

// ── Extract text from response ────────────────────────────────────────────────
$respData  = json_decode($raw, true);
$rawResult = $useGroq
    ? ($respData['choices'][0]['message']['content'] ?? null)
    : ($respData['candidates'][0]['content']['parts'][0]['text'] ?? null);

if (empty($rawResult)) {
    // Return raw API response for debugging
    sendJSONResponse(false, null, 'AI returned empty. Raw: ' . substr($raw, 0, 500), 502);
}

// ── Parse and normalise events ────────────────────────────────────────────────
$jsonStr = trim($rawResult);
$jsonStr = preg_replace('/^```json\s*|^```\s*|```$/m', '', $jsonStr);
$jsonStr = trim($jsonStr);

$start = strpos($jsonStr, '[');
$end   = strrpos($jsonStr, ']');
if ($start !== false && $end !== false && $end > $start) {
    $jsonStr = substr($jsonStr, $start, $end - $start + 1);
}

$events = json_decode($jsonStr, true);
if (!is_array($events)) {
    // Try one more pass by stripping non-printable chars that sometimes appear
    // in OCR-derived model output and can break JSON decoding.
    $sanitised = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', ' ', $jsonStr);
    $sanitised = preg_replace('/\s{2,}/', ' ', $sanitised);
    $events = json_decode(trim((string)$sanitised), true);
}
if (!is_array($events)) {
    // If AI output isn't parseable JSON, fall back to heuristic extraction.
    $heuristic = extractEventsFromTextHeuristic($syllabusText, $moduleCode);
    if (!empty($heuristic)) {
        sendJSONResponse(true, ['events' => $heuristic], 'Events extracted with fallback parser');
    }
    sendJSONResponse(true, ['events' => []], 'No parseable events found in the uploaded document.');
}

function normaliseType(string $raw): string {
    $v = strtolower(trim($raw));
    if (str_contains($v, 'test'))                               return 'Test';
    if (str_contains($v, 'exam'))                               return 'Exam';
    if (str_contains($v, 'practical') || str_contains($v, 'lab')) return 'Practical';
    return 'Assignment';
}

$cleaned = [];
foreach ($events as $ev) {
    if (!is_array($ev)) continue;
    $dateStr = normaliseDateString($ev['date'] ?? '');
    if ($dateStr === null) continue;

    $cleaned[] = [
        'title'         => cleanEventTitle($ev['title'] ?? 'Untitled event'),
        'date'          => $dateStr,
        'type'          => normaliseType($ev['type'] ?? ''),
        'moduleCode'    => $moduleCode,
        'time'          => !empty($ev['time'])  ? trim($ev['time'])  : null,
        'venue'         => !empty($ev['venue']) ? trim($ev['venue']) : null,
        'isReminderSet' => false,
    ];
}

// If AI output is mostly gibberish/noise, rely on deterministic fallback extraction.
if (!empty($cleaned)) {
    $cleaned = array_values(array_filter($cleaned, static function ($item) use ($syllabusText) {
        return isCredibleEvent($item, $syllabusText);
    }));
}

if (empty($cleaned)) {
    $cleaned = extractEventsFromTextHeuristic($syllabusText, $moduleCode);
} else {
    // Always merge deterministic extraction so table dates are not missed when
    // AI under-extracts (common with PDF/OCR and week-range rows).
    $cleaned = mergeEventLists($cleaned, extractEventsFromTextHeuristic($syllabusText, $moduleCode));
}

sendJSONResponse(true, ['events' => $cleaned], 'Events extracted successfully');

// ── Uploaded file text extraction helpers ────────────────────────────────────
function extractTextFromUploadedFile(string $tmpPath, string $fileName, string $fileType): string
{
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    // Plain text files are straightforward.
    if ($ext === 'txt' || str_contains(strtolower($fileType), 'text/plain')) {
        $raw = file_get_contents($tmpPath);
        return $raw === false ? '' : trim($raw);
    }

    // DOCX is a ZIP archive with XML under word/document.xml.
    if ($ext === 'docx') {
        return extractTextFromDocx($tmpPath);
    }

    // Legacy .doc can sometimes be extracted by antiword if available.
    if ($ext === 'doc' && function_exists('exec')) {
        $escaped = escapeshellarg($tmpPath);
        $output = [];
        exec("antiword $escaped 2>/dev/null", $output, $code);
        if ($code === 0 && !empty($output)) {
            return trim(implode("\n", $output));
        }
    }

    // Default to PDF-style extraction (also safe fallback for unknown types).
    return extractTextFromPdf($tmpPath);
}

function extractEventsWithPython(string $tmpPath, string $fileName, string $moduleCode): ?array
{
    if (!function_exists('exec')) {
        return null;
    }

    $scriptPath = realpath(__DIR__ . '/../extractor/extract_events.py');
    if ($scriptPath === false || !file_exists($scriptPath)) {
        return null;
    }

    $output = [];
    $code = 1;
    foreach (['python3', 'python'] as $pythonBin) {
        $cmd = escapeshellcmd($pythonBin) . ' ' . escapeshellarg($scriptPath)
            . ' --file ' . escapeshellarg($tmpPath)
            . ' --filename ' . escapeshellarg($fileName)
            . ' --module ' . escapeshellarg($moduleCode);

        $output = [];
        exec($cmd . ' 2>/dev/null', $output, $code);
        if ($code === 0 && !empty($output)) {
            break;
        }
        // Windows-compatible stderr suppression retry.
        exec($cmd . ' 2>NUL', $output, $code);
        if ($code === 0 && !empty($output)) {
            break;
        }
    }

    if ($code !== 0 || empty($output)) {
        return null;
    }

    $decoded = json_decode(implode("\n", $output), true);
    if (!is_array($decoded) || !isset($decoded['events']) || !is_array($decoded['events'])) {
        return null;
    }

    $events = [];
    foreach ($decoded['events'] as $ev) {
        if (!is_array($ev)) continue;
        $date = normaliseDateString((string)($ev['date'] ?? ''));
        if ($date === null) continue;

        $events[] = [
            'title'         => cleanEventTitle((string)($ev['title'] ?? 'Untitled event')),
            'date'          => $date,
            'type'          => normaliseType((string)($ev['type'] ?? '')),
            'moduleCode'    => $moduleCode,
            'time'          => !empty($ev['time']) ? trim((string)$ev['time']) : null,
            'venue'         => !empty($ev['venue']) ? trim((string)$ev['venue']) : null,
            'isReminderSet' => false,
        ];
    }

    return $events;
}

function extractTextFromDocx(string $tmpPath): string
{
    $entries = ['word/document.xml', 'word/header1.xml', 'word/header2.xml', 'word/footer1.xml', 'word/footer2.xml'];
    $parts = [];

    // Primary path: PHP zip extension.
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($tmpPath) === true) {
            foreach ($entries as $entry) {
                $xml = $zip->getFromName($entry);
                if ($xml !== false) {
                    $parts[] = $xml;
                }
            }
            $zip->close();
        }
    }

    // Fallback path: shell unzip (helps when ZipArchive extension is unavailable).
    if (empty($parts) && function_exists('exec')) {
        $escapedFile = escapeshellarg($tmpPath);
        foreach ($entries as $entry) {
            $escapedEntry = escapeshellarg($entry);
            $output = [];
            exec("unzip -p $escapedFile $escapedEntry 2>/dev/null", $output, $code);
            if ($code === 0 && !empty($output)) {
                $parts[] = implode("\n", $output);
            }
        }
    }

    if (empty($parts)) {
        return '';
    }

    $combinedXml = implode("\n", $parts);
    $combinedXml = preg_replace('/<\/w:p>/', "\n", $combinedXml);
    $combinedXml = preg_replace('/<[^>]+>/', '', $combinedXml);
    $text = html_entity_decode($combinedXml, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $text = trim($text);
    return preg_replace('/\n{3,}/', "\n\n", $text) ?? '';
}

function extractTextFromPdf(string $tmpPath): string
{
    $text = '';

    // Try pdftotext (poppler-utils) first — fast and accurate for text PDFs.
    if (function_exists('exec')) {
        $escaped = escapeshellarg($tmpPath);
        $output  = [];
        exec("pdftotext -layout $escaped - 2>/dev/null", $output, $code);
        if ($code === 0 && !empty($output)) {
            $text = trim(implode("\n", $output));
        }
    }

    // OCR fallback for scanned/image PDFs when extracted text is too weak.
    if (strlen($text) < 80) {
        $ocrText = extractTextFromPdfUsingOcr($tmpPath, 8);
        if (strlen(trim($ocrText)) > strlen(trim($text))) {
            $text = trim($ocrText);
        }
    }

    if (strlen($text) >= 20) {
        return $text;
    }

    // Last fallback: read raw PDF bytes and extract readable ASCII text.
    // This works for simple text-based PDFs without a full parser.
    $raw  = file_get_contents($tmpPath);
    if ($raw === false) return $text;

    // Pull out text between BT/ET blocks (basic PDF text extraction)
    $basicText = '';
    if (preg_match_all('/BT[\s\S]*?ET/', $raw, $blocks)) {
        foreach ($blocks[0] as $block) {
            if (preg_match_all('/\(([^)]+)\)/', $block, $strings)) {
                $basicText .= implode(' ', $strings[1]) . "\n";
            }
        }
    }

    // If that yields nothing, return whatever printable chars exist
    if (strlen(trim($basicText)) < 20) {
        $basicText = preg_replace('/[^\x20-\x7E\n\r\t]/', ' ', $raw);
        $basicText = preg_replace('/\s{3,}/', "\n", $basicText);
    }

    if (strlen(trim($basicText)) > strlen(trim($text))) {
        $text = $basicText;
    }

    return trim($text);
}

function extractTextFromPdfUsingOcr(string $tmpPath, int $maxPages = 8): string
{
    if (!function_exists('exec')) {
        return '';
    }

    $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pdfocr_' . uniqid('', true);
    if (!@mkdir($tmpDir, 0700, true) && !is_dir($tmpDir)) {
        return '';
    }

    $escapedPdf = escapeshellarg($tmpPath);
    $outputPrefix = $tmpDir . DIRECTORY_SEPARATOR . 'page';
    $escapedPrefix = escapeshellarg($outputPrefix);

    // Render first N pages as PNG files for OCR.
    $renderOut = [];
    exec("pdftoppm -png -gray -r 300 -f 1 -l $maxPages $escapedPdf $escapedPrefix 2>/dev/null", $renderOut, $renderCode);
    if ($renderCode !== 0) {
        cleanupDirectory($tmpDir);
        return '';
    }

    $images = glob($tmpDir . DIRECTORY_SEPARATOR . 'page-*.png');
    if (!$images) {
        cleanupDirectory($tmpDir);
        return '';
    }

    natsort($images);
    $textParts = [];

    foreach ($images as $imagePath) {
        $escapedImage = escapeshellarg($imagePath);
        $ocrTextA = runTesseractToText($escapedImage, '--oem 1 --psm 6');
        $ocrTextB = runTesseractToText($escapedImage, '--oem 1 --psm 11');
        $scoreA = textQualityScore($ocrTextA);
        $scoreB = textQualityScore($ocrTextB);
        $best = $scoreA >= $scoreB ? $ocrTextA : $ocrTextB;
        if (trim($best) !== '') $textParts[] = $best;
    }

    cleanupDirectory($tmpDir);
    return trim(implode("\n\n", $textParts));
}

function cleanupDirectory(string $dir): void
{
    if (!is_dir($dir)) return;
    $files = glob($dir . DIRECTORY_SEPARATOR . '*');
    if ($files) {
        foreach ($files as $f) {
            @unlink($f);
        }
    }
    @rmdir($dir);
}

function runTesseractToText(string $escapedImagePath, string $args): string
{
    $ocrOut = [];
    exec("tesseract $escapedImagePath stdout -l eng $args 2>/dev/null", $ocrOut, $ocrCode);
    if ($ocrCode !== 0 || empty($ocrOut)) return '';
    return trim(implode("\n", $ocrOut));
}

function textQualityScore(string $text): float
{
    $s = trim($text);
    if ($s === '') return -1000.0;

    $len = strlen($s);
    $alpha = preg_match_all('/[A-Za-z]/', $s);
    $digits = preg_match_all('/\d/', $s);
    $symbols = preg_match_all('/[^A-Za-z0-9\s]/', $s);
    $words = preg_match_all('/[A-Za-z]{3,}/', $s);
    $dateLike = preg_match_all('/\b(\d{1,2}[\/\-.]\d{1,2}(?:[\/\-.]\d{2,4})?|\d{4}-\d{2}-\d{2})\b/', $s);
    $months = preg_match_all('/\b(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec|January|February|March|April|June|July|August|September|October|November|December)\b/i', $s);
    $keywords = preg_match_all('/\b(test|exam|assignment|practical|quiz|project|week|assessment|submission|due)\b/i', $s);

    $alnumRatio = ($alpha + $digits) / max(1, $len);
    $symbolRatio = $symbols / max(1, $len);

    $score = 0.0;
    $score += $words * 0.8;
    $score += $dateLike * 2.5;
    $score += $months * 2.0;
    $score += $keywords * 2.0;
    $score += $alnumRatio * 25.0;
    $score -= $symbolRatio * 40.0;

    return $score;
}

function normaliseDateString(string $raw): ?string
{
    $value = trim($raw);
    if ($value === '') return null;

    // Normalise dash variants from PDFs/OCR: –, —, etc.
    $value = preg_replace('/[\x{2012}-\x{2015}]/u', '-', $value) ?? $value;

    // Remove ordinal suffixes: 1st, 2nd, 3rd, 4th...
    $value = preg_replace('/\b(\d{1,2})(st|nd|rd|th)\b/i', '$1', $value) ?? $value;
    $value = trim($value);

    // Handle week ranges like:
    // "Week of 16-20 March 2026" or "16-20 March 2026"
    $weekValue = preg_replace('/^\s*week\s+of\s+/i', '', $value) ?? $value;
    if (preg_match('/^(\d{1,2})\s*-\s*(\d{1,2})\s+([A-Za-z]{3,9})\s+(\d{4})$/i', $weekValue, $m)) {
        $firstDay = (int)$m[1];
        $monthName = $m[3];
        $year = (int)$m[4];
        $dt = DateTime::createFromFormat('!j M Y', "$firstDay $monthName $year")
            ?: DateTime::createFromFormat('!j F Y', "$firstDay $monthName $year");
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d');
        }
    }

    // Already normalised
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }

    $formats = [
        'd/m/Y', 'd-m-Y', 'd.m.Y',
        'j/n/Y', 'j-n-Y',
        'd/m/y', 'd-m-y',
        'j M Y', 'j F Y', 'd M Y', 'd F Y',
        'M j Y', 'F j Y',
        'j M, Y', 'j F, Y', 'M j, Y', 'F j, Y',
    ];

    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat('!' . $fmt, $value);
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d');
        }
    }

    // Day/month without year -> assume 2026 for current academic cycle.
    if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})$/', $value, $m)) {
        $day = (int)$m[1];
        $month = (int)$m[2];
        if (checkdate($month, $day, 2026)) {
            return sprintf('2026-%02d-%02d', $month, $day);
        }
    }
    if (preg_match('/^(\d{1,2})\s+([A-Za-z]{3,9})$/', $value, $m)) {
        $dt = DateTime::createFromFormat('!j M Y', "{$m[1]} {$m[2]} 2026")
            ?: DateTime::createFromFormat('!j F Y', "{$m[1]} {$m[2]} 2026");
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d');
        }
    }

    return null;
}

function extractEventsFromTextHeuristic(string $text, string $moduleCode): array
{
    $lines = preg_split('/\R+/', $text) ?: [];
    $results = [];
    $seen = [];

    $datePatterns = [
        '/\b(?:week\s+of\s+)?\d{1,2}\s*[–-]\s*\d{1,2}\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec|January|February|March|April|June|July|August|September|October|November|December)\s+\d{4}\b/i',
        '/\b\d{4}-\d{2}-\d{2}\b/',
        '/\b\d{1,2}[\/\-.]\d{1,2}(?:[\/\-.]\d{2,4})?\b/',
        '/\b\d{1,2}(?:st|nd|rd|th)?\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec|January|February|March|April|June|July|August|September|October|November|December)\b(?:,?\s*\d{4})?/i',
        '/\b(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec|January|February|March|April|June|July|August|September|October|November|December)\s+\d{1,2}(?:st|nd|rd|th)?\b(?:,?\s*\d{4})?/i',
    ];

    foreach ($lines as $index => $line) {
        $line = trim($line);
        $line = cleanEventTitle($line);
        if ($line === '' || strlen($line) < 6) continue;

        $looksLikeAssessment = hasAssessmentKeyword($line);
        if (!$looksLikeAssessment) continue;

        $matchedDate = null;
        foreach ($datePatterns as $pattern) {
            if (preg_match($pattern, $line, $m)) {
                $matchedDate = $m[0];
                break;
            }
        }
        if ($matchedDate === null) continue;

        $dateStr = normaliseDateString($matchedDate);
        if ($dateStr === null) continue;

        $type = normaliseType($line);
        $title = trim(str_replace($matchedDate, '', $line));
        $title = trim(preg_replace('/\s+/', ' ', $title) ?? $title);
        if (strlen($title) > 120) {
            $title = substr($title, 0, 117) . '...';
        }
        if ($title === '' || isLikelyGibberish($title)) {
            $title = $type . ' event';
        }

        $key = strtolower($title . '|' . $dateStr);
        if (isset($seen[$key])) continue;
        $seen[$key] = true;

        $time = extractTimeFromLine($line);

        $results[] = [
            'title' => $title,
            'date' => $dateStr,
            'type' => $type,
            'moduleCode' => $moduleCode,
            'time' => $time,
            'venue' => null,
            'isReminderSet' => false,
        ];
    }

    // Second pass: if nothing found, extract any recognisable date lines.
    // This helps for table-style handouts where labels and dates are split.
    if (empty($results)) {
        $lineCount = count($lines);
        foreach ($lines as $index => $line) {
            $line = trim($line);
            if ($line === '' || strlen($line) < 3) continue;

            $matchedDate = null;
            foreach ($datePatterns as $pattern) {
                if (preg_match($pattern, $line, $m)) {
                    $matchedDate = $m[0];
                    break;
                }
            }
            if ($matchedDate === null) continue;

            $dateStr = normaliseDateString($matchedDate);
            if ($dateStr === null) continue;

            $context = $line;
            if ($index > 0) $context .= ' ' . trim($lines[$index - 1] ?? '');
            if ($index + 1 < $lineCount) $context .= ' ' . trim($lines[$index + 1] ?? '');
            if (!hasAssessmentKeyword($context)) continue;

            $title = trim(str_replace($matchedDate, '', $line));
            if ($title === '' && $index > 0) {
                $title = cleanEventTitle(trim($lines[$index - 1] ?? ''));
            }
            if ($title === '' && $index + 1 < $lineCount) {
                $title = cleanEventTitle(trim($lines[$index + 1] ?? ''));
            }
            if ($title === '' || isLikelyGibberish($title)) {
                $title = 'Important date';
            }
            if (strlen($title) > 120) {
                $title = substr($title, 0, 117) . '...';
            }

            $type = normaliseType($title . ' ' . $line);
            $key = strtolower($title . '|' . $dateStr);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            $results[] = [
                'title' => $title,
                'date' => $dateStr,
                'type' => $type,
                'moduleCode' => $moduleCode,
                'time' => extractTimeFromLine($line),
                'venue' => null,
                'isReminderSet' => false,
            ];
        }
    }

    return $results;
}

function extractTimeFromLine(string $line): ?string
{
    if (preg_match('/\b([01]?\d|2[0-3]):([0-5]\d)\b/', $line, $tm)) {
        return sprintf('%02d:%02d', (int)$tm[1], (int)$tm[2]);
    }
    if (preg_match('/\b(1[0-2]|0?[1-9])(?:[:.]([0-5]\d))?\s*(am|pm)\b/i', $line, $tm)) {
        $hour = (int)$tm[1];
        $min = isset($tm[2]) && $tm[2] !== '' ? (int)$tm[2] : 0;
        $ampm = strtolower($tm[3]);
        if ($ampm === 'pm' && $hour !== 12) $hour += 12;
        if ($ampm === 'am' && $hour === 12) $hour = 0;
        return sprintf('%02d:%02d', $hour, $min);
    }
    return null;
}

function hasAssessmentKeyword(string $text): bool
{
    return preg_match('/\b(test|exam|assignment|practical|quiz|project|submission|due|assessment|sick test)\b/i', $text) === 1;
}

function mergeEventLists(array $primary, array $secondary): array
{
    $merged = [];
    $seen = [];

    foreach ([$primary, $secondary] as $list) {
        foreach ($list as $event) {
            if (!is_array($event)) continue;
            $key = eventDedupKey($event);
            if ($key === '') continue;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $merged[] = $event;
        }
    }

    usort($merged, static function ($a, $b) {
        $ad = (string)($a['date'] ?? '');
        $bd = (string)($b['date'] ?? '');
        return strcmp($ad, $bd);
    });

    return $merged;
}

function eventDedupKey(array $event): string
{
    $date = strtolower(trim((string)($event['date'] ?? '')));
    $title = strtolower(trim((string)($event['title'] ?? '')));
    if ($date === '' || $title === '') return '';

    $title = preg_replace('/\s+/', ' ', $title) ?? $title;
    $title = preg_replace('/[^a-z0-9\s]/', '', $title) ?? $title;
    $title = trim($title);
    if ($title === '') return '';

    return $date . '|' . $title;
}

function cleanEventTitle(string $raw): string
{
    $title = trim($raw);
    if ($title === '') return 'Untitled event';

    // Remove replacement chars and non-printable noise from OCR/LLM output.
    $title = str_replace(["\xEF\xBF\xBD", '�'], ' ', $title);
    $title = preg_replace('/[^\x20-\x7E]/', ' ', $title) ?? $title;
    $title = preg_replace('/\s+/', ' ', $title) ?? $title;
    $title = trim($title, " \t\n\r\0\x0B-:|");

    // Condense noisy table/OCR lines into concise assessment labels.
    if (preg_match('/(sick\s*test|semester\s*test\s*\d*|class\s*test\s*\d*|test\s*[0-9il]+|assignment\s*\d*|quiz(?:zes)?|project(?:\s*submission)?)/i', $title, $m)) {
        $label = preg_replace('/\s+/', ' ', trim($m[1])) ?? trim($m[1]);
        $label = preg_replace('/\btest\s+l\b/i', 'Test 1', $label) ?? $label;
        $label = ucwords(strtolower($label));
        if ($label !== '') return $label;
    }

    if ($title === '' || isLikelyGibberish($title)) return 'Untitled event';
    if (str_word_count($title) > 10) return 'Important assessment';
    if (strlen($title) > 120) {
        $title = substr($title, 0, 117) . '...';
    }
    return $title;
}

function isLikelyGibberish(string $value): bool
{
    $s = trim($value);
    if ($s === '' || strlen($s) < 4) return true;

    // Too many symbols/punctuation vs letters/digits usually indicates OCR noise.
    $symbolCount = preg_match_all('/[^A-Za-z0-9\s]/', $s);
    $alnumCount = preg_match_all('/[A-Za-z0-9]/', $s);
    if ($alnumCount === 0) return true;
    if ($symbolCount > ($alnumCount * 0.35)) return true;

    // Long runs of punctuation are almost always corruption.
    if (preg_match('/[^\w\s]{4,}/', $s)) return true;
    if (preg_match('/[?]{2,}/', $s)) return true;

    // If it has no vowels and is long, it's likely garbage tokens.
    if (strlen($s) > 12 && !preg_match('/[AEIOUaeiou]/', $s)) return true;

    return false;
}

function isCredibleEvent(array $event, string $sourceText): bool
{
    $title = (string)($event['title'] ?? '');
    $date = (string)($event['date'] ?? '');
    $type = strtolower((string)($event['type'] ?? ''));

    if ($title === '' || isLikelyGibberish($title)) return false;
    if ($date === '') return false;

    // Very common corruption markers from OCR/LLM noise.
    if (preg_match('/[?]{3,}|[@]{2,}|[|]{2,}/', $title)) return false;

    // Require the extracted date to have at least one textual footprint in source text.
    if (!dateAppearsInSource($date, $sourceText)) return false;

    // If title has no typical assessment keyword, still allow if type is explicit and date is present.
    $hasKeyword = preg_match('/\b(test|exam|assignment|practical|quiz|project|submission|due)\b/i', $title) === 1;
    $typeKnown = in_array($type, ['test', 'exam', 'assignment', 'practical'], true);
    if (!$hasKeyword && !$typeKnown) return false;

    // Hard guard against hallucinated/gibberish titles:
    // require title signal to appear in extracted source text.
    if (!titleHasSourceSignal($title, $sourceText)) return false;

    return true;
}

function dateAppearsInSource(string $yyyyMmDd, string $sourceText): bool
{
    $dt = DateTime::createFromFormat('!Y-m-d', $yyyyMmDd);
    if (!$dt instanceof DateTime) return false;

    $day = (int)$dt->format('j');
    $monthShort = $dt->format('M'); // e.g. Mar
    $monthLong = $dt->format('F');  // e.g. March
    $year = $dt->format('Y');
    $monthNum = $dt->format('m');
    $dayPadded = $dt->format('d');

    $patterns = [
        '/\b' . preg_quote($yyyyMmDd, '/') . '\b/i',
        '/\b' . $day . '[\/\-.]' . ltrim($monthNum, '0') . '(?:[\/\-.]' . $year . ')?\b/i',
        '/\b' . $dayPadded . '[\/\-.]' . $monthNum . '(?:[\/\-.]' . $year . ')?\b/i',
        '/\b' . $day . '\s+' . preg_quote($monthShort, '/') . '(?:\s+' . $year . ')?\b/i',
        '/\b' . $day . '\s+' . preg_quote($monthLong, '/') . '(?:\s+' . $year . ')?\b/i',
        '/\b' . preg_quote($monthShort, '/') . '\s+' . $day . '(?:,?\s*' . $year . ')?\b/i',
        '/\b' . preg_quote($monthLong, '/') . '\s+' . $day . '(?:,?\s*' . $year . ')?\b/i',
        // Week-range table variant: "16-20 March 2026" (match start day with same month/year).
        '/\b' . $day . '\s*[–-]\s*\d{1,2}\s+' . preg_quote($monthLong, '/') . '\s+' . $year . '\b/i',
        '/\b' . $day . '\s*[–-]\s*\d{1,2}\s+' . preg_quote($monthShort, '/') . '\s+' . $year . '\b/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $sourceText) === 1) {
            return true;
        }
    }
    return false;
}

function titleHasSourceSignal(string $title, string $sourceText): bool
{
    $titleLower = strtolower($title);
    $sourceLower = strtolower($sourceText);
    if ($sourceLower === '') return false;

    // Keep meaningful tokens only; ignore short/common glue words.
    preg_match_all('/[a-z0-9]{4,}/', $titleLower, $m);
    $tokens = array_values(array_unique($m[0] ?? []));
    if (empty($tokens)) return false;

    $stop = [
        'week', 'date', 'mark', 'final', 'online', 'physical',
        'assessment', 'module', 'semester', 'coverage'
    ];

    $hits = 0;
    foreach ($tokens as $t) {
        if (in_array($t, $stop, true)) continue;
        if (str_contains($sourceLower, $t)) {
            $hits++;
        }
    }

    // Require at least one strong token match; two for longer noisy titles.
    if (count($tokens) >= 5) {
        return $hits >= 2;
    }
    return $hits >= 1;
}
