<?php
require_once __DIR__ . '/../includes/api_helpers.php';
require_once __DIR__ . '/../includes/env.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSONResponse(false, null, 'Method not allowed', 405);
}

$result = [
    'exec_enabled' => function_exists('exec'),
    'python_bin_env' => getenv('PYTHON_BIN') ?: null,
    'script_exists' => file_exists(__DIR__ . '/../extractor/extract_events.py'),
    'script_path' => realpath(__DIR__ . '/../extractor/extract_events.py') ?: null,
];

$checks = [];
foreach (['python3', 'python'] as $bin) {
    $out = [];
    exec($bin . ' --version 2>&1', $out, $code);
    $checks[$bin] = [
        'exit_code' => $code,
        'output' => implode("\n", $out),
    ];
}

$envBin = trim((string)getenv('PYTHON_BIN'));
if ($envBin !== '') {
    $out = [];
    exec(escapeshellcmd($envBin) . ' --version 2>&1', $out, $code);
    $checks['env_python_bin'] = [
        'bin' => $envBin,
        'exit_code' => $code,
        'output' => implode("\n", $out),
    ];
}

$result['checks'] = $checks;
sendJSONResponse(true, $result, 'parser health');
