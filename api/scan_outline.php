<?php
/**
 * POST /api/scan_outline.php
 *
 * Accepts either:
 *   A) multipart/form-data  with fields: module_code + file (PDF)
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

    if ($fileSize > 10 * 1024 * 1024) { // 10 MB limit
        sendJSONResponse(false, null, 'File is too large (max 10 MB).', 400);
    }

    // Extract text from PDF using pdftotext (available on most Linux servers)
    // Fall back to reading raw bytes and letting Gemini handle it via base64
    $syllabusText = extractTextFromPdf($fileTmp, $fileName);

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
    sendJSONResponse(false, null, 'Could not extract enough text from the document.', 400);
}

// Truncate to avoid token limits (~60 000 chars ≈ 15 000 tokens)
$syllabusText = mb_substr($syllabusText, 0, 60000);

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
    sendJSONResponse(false, null, 'AI returned an empty response.', 502);
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
    sendJSONResponse(false, null, 'Could not parse AI response as JSON.', 502);
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
    $dateStr = trim($ev['date'] ?? '');
    $dt = DateTime::createFromFormat('Y-m-d', $dateStr);
    if (!$dt || $dt->format('Y-m-d') !== $dateStr) continue;

    $cleaned[] = [
        'title'         => trim($ev['title'] ?? 'Untitled event') ?: 'Untitled event',
        'date'          => $dateStr,
        'type'          => normaliseType($ev['type'] ?? ''),
        'moduleCode'    => $moduleCode,
        'time'          => !empty($ev['time'])  ? trim($ev['time'])  : null,
        'venue'         => !empty($ev['venue']) ? trim($ev['venue']) : null,
        'isReminderSet' => false,
    ];
}

sendJSONResponse(true, ['events' => $cleaned], 'Events extracted successfully');

// ── PDF text extraction helper ────────────────────────────────────────────────
function extractTextFromPdf(string $tmpPath, string $fileName): string
{
    // Try pdftotext (poppler-utils) — fast and accurate
    if (function_exists('exec')) {
        $escaped = escapeshellarg($tmpPath);
        $output  = [];
        exec("pdftotext -layout $escaped - 2>/dev/null", $output, $code);
        if ($code === 0 && !empty($output)) {
            return implode("\n", $output);
        }
    }

    // Fallback: read raw PDF bytes and extract readable ASCII text
    // This works for simple text-based PDFs without a parser
    $raw  = file_get_contents($tmpPath);
    if ($raw === false) return '';

    // Pull out text between BT/ET blocks (basic PDF text extraction)
    $text = '';
    if (preg_match_all('/BT[\s\S]*?ET/', $raw, $blocks)) {
        foreach ($blocks[0] as $block) {
            if (preg_match_all('/\(([^)]+)\)/', $block, $strings)) {
                $text .= implode(' ', $strings[1]) . "\n";
            }
        }
    }

    // If that yields nothing, return whatever printable chars exist
    if (strlen(trim($text)) < 20) {
        $text = preg_replace('/[^\x20-\x7E\n\r\t]/', ' ', $raw);
        $text = preg_replace('/\s{3,}/', "\n", $text);
    }

    return trim($text);
}
