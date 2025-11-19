<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HealthController
{
	public function index(Request $request, Response $response): Response
	{
		$payload = [
			'status' => 'ok',
			'app' => 'Smart Timetable API',
			'time' => gmdate('c'),
			'php' => PHP_VERSION
		];

		$response->getBody()->write(json_encode($payload));
		return $response
			->withHeader('Content-Type', 'application/json')
			->withStatus(200);
	}
}


