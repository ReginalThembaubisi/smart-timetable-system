<?php

declare(strict_types=1);

// Front controller for Slim API under /api

// Ensure project base path
$projectRoot = dirname(__DIR__);

// Composer autoload
$autoload = $projectRoot . '/vendor/autoload.php';
if (!file_exists($autoload)) {
	http_response_code(500);
	header('Content-Type: application/json');
	echo json_encode([
		'error' => 'Composer dependencies not installed',
		'message' => 'Run: composer install (from ' . $projectRoot . ')'
	]);
	exit;
}

/** @var \Slim\App $app */
$app = require $projectRoot . '/bootstrap/app.php';
$app->run();


