<?php

declare(strict_types=1);

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\Factory\AppFactory;

require_once __DIR__ . '/../vendor/autoload.php';

// Create App with Nyholm PSR-7 factories
$psr17Factory = new Psr17Factory();
AppFactory::setResponseFactory($psr17Factory);
AppFactory::setContainer(new \DI\Container());
$app = AppFactory::create();

// Middleware (add as needed)
$app->add(function ($request, $handler) {
	$origins = defined('API_ALLOWED_ORIGINS') ? API_ALLOWED_ORIGINS : (getenv('API_ALLOWED_ORIGINS') !== false ? getenv('API_ALLOWED_ORIGINS') : '*');
	$allowedOrigin = '*';
	if ($origins !== '*') {
		$originList = array_map('trim', explode(',', (string)$origins));
		$requestOrigin = $request->getHeaderLine('Origin');
		if ($requestOrigin && in_array($requestOrigin, $originList, true)) {
			$allowedOrigin = $requestOrigin;
		} else {
			$allowedOrigin = reset($originList) ?: '*';
		}
	}
	$response = $handler->handle($request);
	return $response
		->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
		->withHeader('Access-Control-Allow-Credentials', 'true')
		->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
		->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
});

$app->options('/{routes:.+}', function ($request, $response) {
	return $response;
});
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// Error middleware (dev-friendly defaults)
$displayErrorDetails = true;
$logErrors = true;
$logErrorDetails = true;
$app->addErrorMiddleware($displayErrorDetails, $logErrors, $logErrorDetails);

// Load routes
require __DIR__ . '/../routes/api.php';

return $app;


