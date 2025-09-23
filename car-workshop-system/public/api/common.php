<?php
/**
 * Common API Utilities
 */

// Ensure clean output for JSON responses
if (!headers_sent()) {
    // Set JSON response header
    header('Content-Type: application/json; charset=utf-8');

    // Prevent caching
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
}


function sendSuccess($message, $data = null, $meta = null) {
    // Clear any output buffer to ensure clean JSON
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set headers if not already sent
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(200);
    }
    
    $response = [
        'success' => true,
        'message' => $message,
        'data' => $data,
        'errors' => null
    ];
    
    if ($meta !== null) {
        $response['meta'] = $meta;
    }
    
    // Log the response being sent 
    if (function_exists('debug_log')) {
        debug_log('COMMON: Sending success response', $response);
    }
    
    $json = json_encode($response, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        error_log("COMMON ERROR: JSON encoding failed - " . json_last_error_msg());
        $fallback = [
            'success' => true,
            'message' => 'Response encoding error: ' . json_last_error_msg(),
            'data' => null,
            'errors' => null
        ];
        echo json_encode($fallback);
    } else {
        echo $json;
    }
    exit;
}

/**
 * Send a standardized error JSON response.
 */
function sendError($message, $httpCode = 400, $errors = null, $data = null) {
    // Clear any output buffer to ensure clean JSON
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set headers if not already sent
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($httpCode);
    }
    
    $response = [
        'success' => false,
        'message' => $message,
        'data' => $data,
        'errors' => $errors,
        'meta' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'http_code' => $httpCode,
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'endpoint' => basename($_SERVER['PHP_SELF'] ?? 'unknown')
        ]
    ];
    
    // Add debugging info in development
    if (defined('DEBUG') && constant('DEBUG') === true) {
        $response['debug'] = [
            'php_input' => file_get_contents('php://input'),
            'post_data' => $_POST,
            'get_data' => $_GET,
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
        ];
    }
    
    // Log the error response being sent
    if (function_exists('debug_log')) {
        debug_log("COMMON: Sending error response (HTTP $httpCode)", $response);
    }
    
    $json = json_encode($response, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        error_log("COMMON ERROR: JSON encoding failed for error response - " . json_last_error_msg());
        $fallback = [
            'success' => false,
            'message' => 'Error response encoding failed: ' . json_last_error_msg(),
            'data' => null,
            'errors' => null,
            'meta' => [
                'timestamp' => date('Y-m-d H:i:s'),
                'http_code' => 500,
                'encoding_error' => true
            ]
        ];
        echo json_encode($fallback);
    } else {
        echo $json;
    }
    exit;
}

/**
 * Read JSON from the request body and return it as an associative array.
 *
 * Endpoints that accept JSON (PUT/POST) call this to get a decoded payload.
 */
function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}

/**
 * Validate required fields
 */
function validateRequired($data, $requiredFields) {
    $missing = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        sendError('Required fields missing', 400, [
            'missing_fields' => $missing
        ]);
    }
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}



/**
 * Log API request for debugging
 */
function logApiRequest($endpoint, $method, $data = null) {
    $logData = [
        'endpoint' => $endpoint,
        'method' => $method,
        'timestamp' => date('Y-m-d H:i:s'),
        'user_id' => $_SESSION['user_id'] ?? 'anonymous',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    if ($data) {
        $logData['data'] = $data;
    }
    
    if (function_exists('debug_log')) {
        debug_log('API_REQUEST', $logData);
    }
}

//Cross-origin resource sharing
function handleCors() {
    // Allow from any origin during development
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
    }
    
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        }
        
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
        }
        
        exit(0);
    }
}

// Handle CORS
handleCors();
?>
