<?php
/**
 * API Helper Functions
 * Standardized functions for API endpoints
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/crud_helpers.php';

// Start output buffering early to avoid stray output before JSON
if (!headers_sent()) {
	ob_start();
}

/**
 * Send standardized JSON response
 * @param bool $success Whether the operation was successful
 * @param mixed $data Optional data to include in response
 * @param string $message Optional message
 * @param int $statusCode HTTP status code (default: 200)
 */
function sendJSONResponse($success, $data = null, $message = '', $statusCode = 200) {
    // Clean any previous output
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    
    http_response_code($statusCode);
    
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

/**
 * Set CORS headers for API
 * In production, replace '*' with specific allowed origins
 */
function setCORSHeaders() {
    // Get the origin from the request
    $origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
    
    // Extract origin from referer if needed
    if (empty($origin) && !empty($_SERVER['HTTP_REFERER'])) {
        $parsed = parse_url($_SERVER['HTTP_REFERER']);
        $origin = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
    }
    
    // Always allow the Flutter app origin
    $allowedOrigins = [
        'https://web-production-ffbb.up.railway.app',
        'http://web-production-ffbb.up.railway.app',
        'https://web-production-f8792.up.railway.app',
        'http://web-production-f8792.up.railway.app',
    ];
    
    // Also allow localhost for development
    $isLocalhost = !empty($origin) && (
        strpos($origin, 'localhost') !== false || 
        strpos($origin, '127.0.0.1') !== false ||
        strpos($origin, '0.0.0.0') !== false
    );
    
    // Set CORS headers - MUST be set before any output
    if (!empty($origin)) {
        // Check if origin is in allowed list or is localhost
        $isAllowed = in_array($origin, $allowedOrigins) || $isLocalhost;
        if ($isAllowed) {
            header("Access-Control-Allow-Origin: $origin");
        } else {
            // Fallback: allow all for now (can restrict later)
            header('Access-Control-Allow-Origin: *');
        }
    } else {
        // No origin header, allow all
        header('Access-Control-Allow-Origin: *');
    }
    
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, Accept, X-Requested-With');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400'); // Cache preflight for 24 hours
    
    // Handle preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        // Clean any output buffers
        while (ob_get_level() > 0) { @ob_end_clean(); }
        header('Content-Length: 0');
        exit(0);
    }
    
    // Set content type for actual requests
    header('Content-Type: application/json');
}

/**
 * Get JSON input data
 */
function getJSONInput() {
    $data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendJSONResponse(false, null, 'Invalid JSON data', 400);
    }
    return $data ?? [];
}

/**
 * Validate required fields
 */
function validateRequired($data, $requiredFields) {
    $missing = [];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        sendJSONResponse(false, null, 'Missing required fields: ' . implode(', ', $missing), 400);
    }
}

/**
 * Get database connection for API
 */
function getDBConnection() {
    try {
        return Database::getInstance()->getConnection();
    } catch (Exception $e) {
        sendJSONResponse(false, null, 'Database connection failed', 500);
    }
}

/**
 * Handle API errors with improved logging and user-friendly messages
 */
function handleAPIError($e, $defaultMessage = 'An error occurred') {
    // Log error with context
    logError($e, 'API Error: ' . $defaultMessage, [
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ]);
    
    // Get user-friendly error message
    $errorMessage = getErrorMessage($e, $defaultMessage, false);
    
    // Determine appropriate status code
    $statusCode = 500;
    if ($e instanceof PDOException) {
        // Database errors
        $statusCode = 500;
    } elseif (strpos($e->getMessage(), 'not found') !== false || strpos($e->getMessage(), 'Invalid') !== false) {
        $statusCode = 404;
    } elseif (strpos($e->getMessage(), 'unauthorized') !== false || strpos($e->getMessage(), 'permission') !== false) {
        $statusCode = 403;
    }
    
    sendJSONResponse(false, null, $errorMessage, $statusCode);
}
?>

