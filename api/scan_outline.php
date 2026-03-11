<?php
/**
 * POST /api/scan_outline.php
 * Accepts syllabus text, calls Gemini API server-side, returns extracted events.
 *
 * Request body (JSON):
 *   { "text": "...", "module_code": "ADB401_S1" }
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

$data = getJSONInput();
validateRequired($data, ['text', 'module_code']);

$syllabusText = trim($data['text']);
$moduleCode   = trim($data['module_code']);

if (strlen($syllabusText) < 10) {
    sendJSONResponse(false, null, 'Text is too short to analyse.', 400);
}

// Truncate to avoid token limits (≈60 000 chars ≈ 15 000 tokens)
$syllabusText = mb_substr($syllabusText, 0, 60000);

// ── Gemini API key ────────────────────────────────────────────────────────────
$geminiKey = getenv('GEMINI_API_KEY');
if (empty($geminiKey)) {
    sendJSONResponse(false, null, 'Gemini API key is not configured on the server.', 500);
}

// ── Prompt ────────────────────────────────────────────────────────────────────
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

// ── Call Gemini REST API ──────────────────────────────────────────────────────
$url     = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$geminiKey}";
$payload = json_encode([
    'contents' => [
        ['parts' => [['text' => $prompt]]]
    ],
    'generationConfig' => [
        'temperature'     => 0.1,
        'maxOutputTokens' => 2048,
    ],
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 30,
]);

$raw      = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    sendJSONResponse(false, null, 'Network error contacting AI: ' . $curlErr, 502);
}
if ($httpCode !== 200) {
    $body = json_decode($raw, true);
    $msg  = $body['error']['message'] ?? "Gemini API error (HTTP {$httpCode})";
    sendJSONResponse(false, null, $msg, 502);
}

$geminiData = json_decode($raw, true);
$rawResult  = $geminiData['candidates'][0]['content']['parts'][0]['text'] ?? null;

if (empty($rawResult)) {
    sendJSONResponse(false, null, 'AI returned an empty response.', 502);
}

// ── Parse JSON from AI response ───────────────────────────────────────────────
$jsonStr = trim($rawResult);

// Strip markdown fences if present
$jsonStr = preg_replace('/^```json\s*|^```\s*|```$/m', '', $jsonStr);
$jsonStr = trim($jsonStr);

// Extract first [...] block as a safety net
$start = strpos($jsonStr, '[');
$end   = strrpos($jsonStr, ']');
if ($start !== false && $end !== false && $end > $start) {
    $jsonStr = substr($jsonStr, $start, $end - $start + 1);
}

$events = json_decode($jsonStr, true);
if (!is_array($events)) {
    sendJSONResponse(false, null, 'Could not parse AI response as JSON.', 502);
}

// ── Normalise and validate each event ────────────────────────────────────────
$validTypes = ['Test', 'Assignment', 'Exam', 'Practical'];

function normaliseType(string $raw): string {
    $v = strtolower(trim($raw));
    if (str_contains($v, 'test'))                        return 'Test';
    if (str_contains($v, 'exam'))                        return 'Exam';
    if (str_contains($v, 'practical') || str_contains($v, 'lab')) return 'Practical';
    return 'Assignment';
}

$cleaned = [];
foreach ($events as $ev) {
    if (!is_array($ev)) continue;

    $dateStr = trim($ev['date'] ?? '');
    // Validate date
    $dt = DateTime::createFromFormat('Y-m-d', $dateStr);
    if (!$dt || $dt->format('Y-m-d') !== $dateStr) continue;

    $cleaned[] = [
        'title'       => trim($ev['title'] ?? 'Untitled event') ?: 'Untitled event',
        'date'        => $dateStr,
        'type'        => normaliseType($ev['type'] ?? ''),
        'moduleCode'  => $moduleCode,
        'time'        => !empty($ev['time'])  ? trim($ev['time'])  : null,
        'venue'       => !empty($ev['venue']) ? trim($ev['venue']) : null,
        'isReminderSet' => false,
    ];
}

sendJSONResponse(true, ['events' => $cleaned], 'Events extracted successfully');
