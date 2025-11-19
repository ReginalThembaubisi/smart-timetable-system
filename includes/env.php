<?php
/**
 * Lightweight .env loader.
 * Loads key=value pairs from project-root/.env into getenv()/$_ENV.
 */

function loadEnv(string $envPath): void
{
	if (!file_exists($envPath)) {
		return;
	}
	$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	if ($lines === false) {
		return;
	}
	foreach ($lines as $line) {
		$line = trim($line);
		if ($line === '' || str_starts_with($line, '#')) {
			continue;
		}
		// Support KEY="VALUE" and KEY='VALUE' and KEY=VALUE
		[$key, $value] = array_pad(explode('=', $line, 2), 2, '');
		$key = trim($key);
		$value = trim($value);
		if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
			$quote = $value[0];
			if (str_ends_with($value, $quote)) {
				$value = substr($value, 1, -1);
			}
		}
		if ($key === '') {
			continue;
		}
		// Do not override existing env vars
		if (getenv($key) === false) {
			putenv("$key=$value");
			$_ENV[$key] = $value;
		}
	}
}

// Auto-load from project root .env
$projectRoot = dirname(__DIR__);
loadEnv($projectRoot . DIRECTORY_SEPARATOR . '.env');


