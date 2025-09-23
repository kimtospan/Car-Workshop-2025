<?php
/**
 * Registration API Endpoint
 * AJAX-first registration processing following DRY principles
 */

// Debug configuration is handled centrally. Include AppConfig and debug helpers.
require_once __DIR__ . '/../../src/config/AppConfig.php';
require_once __DIR__ . '/../../src/config/debug.php';

// Start output buffering to catch any unexpected output
ob_start();

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../../includes/language.php';
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/models/User.php';
require_once __DIR__ . '/../../src/services/ValidationService.php';
require_once __DIR__ . '/../../src/services/FormProcessor.php';

// Start session for CSRF and rate limiting
if (session_status() === PHP_SESSION_NONE) {
    session_name('CAR_WORKSHOP_SESSION');
    session_set_cookie_params([
        'lifetime' => 3600,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

// CSRF validation
if (empty($_POST['csrf_token']) || !SecurityConfig::validateCSRFToken($_POST['csrf_token'])) {
    sendError('Security token validation failed. Please refresh the page and try again.', 400);
}

// Rate limiting for registration attempts
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
checkRateLimit('register_' . $clientIP, 3, 600); // 3 attempts per 10 minutes

try {
    // Get and validate input data
    $data = $_POST; // Support form data
    logApiRequest('/api/register.php', 'POST', array_keys($data));
    
    // Debug: Log received data (remove sensitive info for logging)
    $debugData = $data;
    unset($debugData['password']);
    if (function_exists('debug_log')) {
        debug_log("REGISTRATION DATA", $debugData);
    }
    
    // Use FormProcessor for consistent processing (DRY principle)
    $db = Database::getInstance()->getConnection();
    if (function_exists('debug_log')) {
        debug_log("Database connection successful");
    }
    
    $formProcessor = new FormProcessor($db, null); // No auth required for registration
    if (function_exists('debug_log')) {
        debug_log("FormProcessor created");
    }
    
    $result = $formProcessor->processUserRegistration($data);
    if (function_exists('debug_log')) {
        debug_log("ProcessUserRegistration result", $result);
    }
    
    if ($result['success']) {
        // Clear rate limiting on successful registration
        clearRateLimit('register_' . $clientIP);
        
        // Log successful registration
        logApiActivity('user_registered', [
            'user_id' => $result['data']['user_id'] ?? null,
            'username' => $data['username'] ?? 'unknown',
            'email' => $data['email'] ?? 'unknown',
            'role' => $data['role'] ?? 'customer'
        ]);
        
        // Clear any buffered output before sending JSON
        if (ob_get_level()) {
            ob_clean();
        }
        
        sendSuccess($result['message'], $result['data'] ?? null);
    } else {
        if (function_exists('debug_log')) {
            debug_log("Registration failed", $result);
        }
        
        // Clear any buffered output before sending JSON
        if (ob_get_level()) {
            ob_clean();
        }
        
        $httpCode = isset($result['errors']) ? 422 : 400;
        sendError($result['message'], $httpCode, $result['errors'] ?? null);
    }
    
} catch (Exception $e) {
    // Always log exceptions server-side (non-sensitive) so ops can triage. Keep full trace in server log.
    error_log("REGISTRATION ERROR: " . $e->getMessage());
    error_log("REGISTRATION ERROR TRACE: " . $e->getTraceAsString());
    
    // Clear any buffered output before sending JSON
    if (ob_get_level()) {
        ob_clean();
    }
    
    // Enhanced error message for debugging
    $errorMessage = 'Σφάλμα εγγραφής: ' . $e->getMessage();
    
    // Additional debug info in development
    $debugInfo = [
        'error_type' => get_class($e),
        'error_file' => $e->getFile(),
        'error_line' => $e->getLine(),
        'error_code' => $e->getCode()
    ];
    
    sendError($errorMessage, 500, null, $debugInfo);
}

/**
 * Simple rate-limit check for registration attempts.
 *
 * This protects the registration flow from rapid repeated attempts by
 * storing a small counter in the user's session. If the threshold is
 * exceeded the function sends a 429 error. For distributed setups you'd
 * want a central store (Redis) instead, but this is sufficient for local
 * deployments.
 */
function checkRateLimit($key, $maxAttempts, $timeWindow) {
    if (!isset($_SESSION['rate_limits'])) {
        $_SESSION['rate_limits'] = [];
    }
    
    $now = time();
    $rateLimitData = $_SESSION['rate_limits'][$key] ?? [
        'attempts' => 0,
        'first_attempt' => $now,
        'last_attempt' => $now
    ];
    
    // Reset if time window has passed
    if ($now - $rateLimitData['first_attempt'] > $timeWindow) {
        $rateLimitData = [
            'attempts' => 1,
            'first_attempt' => $now,
            'last_attempt' => $now
        ];
        $_SESSION['rate_limits'][$key] = $rateLimitData;
        return;
    }
    
    // Check if too many attempts
    if ($rateLimitData['attempts'] >= $maxAttempts) {
        $timeRemaining = $timeWindow - ($now - $rateLimitData['first_attempt']);
        sendError("Too many registration attempts. Please try again in " . ceil($timeRemaining / 60) . " minutes.", 429);
    }
    
    // Increment attempts
    $rateLimitData['attempts']++;
    $rateLimitData['last_attempt'] = $now;
    $_SESSION['rate_limits'][$key] = $rateLimitData;
}

/**
 * Clear the registration rate-limit entry for the current session.
 *
 * Called after a successful registration so the client isn't blocked by
 * earlier failures.
 */
function clearRateLimit($key) {
    if (isset($_SESSION['rate_limits'][$key])) {
        unset($_SESSION['rate_limits'][$key]);
    }
}

/**
 * Log API activity
 */
function logApiActivity($action, $details = []) {
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => $action,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'details' => $details
    ];
    
    if (function_exists('debug_log')) {
        debug_log('API Activity', $logData);
    } elseif (class_exists('AppConfig') && AppConfig::isDebugMode()) {
        // If the central AppConfig exists and debug mode is enabled, allow a fallback to error_log
        
        error_log("API Activity: " . json_encode($logData));
    }
}
?>
