<?php

declare(strict_types=1);

use App\Controllers\HealthController;

/** @var \Slim\App $app */

// Health check
$app->get('/health', [HealthController::class, 'index']);

// Example: legacy passthrough documentation (to be mapped gradually)
// $app->get('/timetable', [TimetableController::class, 'show']);
// $app->post('/student/login', [AuthController::class, 'login']);


