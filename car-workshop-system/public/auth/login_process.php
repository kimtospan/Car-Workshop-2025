<?php
// Set JSON headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Function to return JSON and exit
function jsonResponse($success, $message, $redirect = '', $debug = []) {
    $response = [
        'success' => $success,
        'message' => $message,
        'redirect' => $redirect
    ];
    
    // Add debug info if in development mode
    if (!empty($debug)) {
        $response['debug'] = $debug;
    }
    
    echo json_encode($response);
    exit;
}

require_once '../../includes/auth.php';
require_once '../../includes/language.php';
require_once '../../src/config/AppConfig.php';

// Ensure session is started the same way as login.php
if (session_status() === PHP_SESSION_NONE) {
    // Use the same session configuration as login.php 
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

// Debug array to collect information
$debug_info = [];
$debug_info[] = "Files loaded successfully";
$debug_info[] = "Session status before AuthMiddleware: " . session_status();
$debug_info[] = "Session ID before AuthMiddleware: " . session_id();
$debug_info[] = "Session name before AuthMiddleware: " . session_name();
$debug_info[] = "CSRF token in session before AuthMiddleware: " . (isset($_SESSION['csrf_token']) ? 'YES' : 'NO');

// Use the global auth instance
$auth = new AuthMiddleware();

$debug_info[] = "AuthMiddleware created";
$debug_info[] = "Session status after AuthMiddleware: " . session_status();
$debug_info[] = "Session ID after AuthMiddleware: " . session_id();
$debug_info[] = "Session name after AuthMiddleware: " . session_name();
$debug_info[] = "CSRF token in session after AuthMiddleware: " . (isset($_SESSION['csrf_token']) ? 'YES' : 'NO');

// CSRF validation
if (empty($_POST['csrf_token']) || !SecurityConfig::validateCSRFToken($_POST['csrf_token'])) {
    http_response_code(400);
    jsonResponse(false, 'Security token validation failed. Please refresh the page and try again.');
}

// Check if already logged in
if ($auth->isLoggedIn()) {
    $debug_info[] = "User already logged in";
    jsonResponse(true, 'Already logged in', 'dashboard.php', $debug_info);
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $debug_info[] = "Invalid request method: " . $_SERVER['REQUEST_METHOD'];
    http_response_code(405);
    jsonResponse(false, 'Method not allowed', '', $debug_info);
}

$debug_info[] = "POST request received";



// Rate limiting (basic)
if (!isset($_SESSION['last_login_attempt'])) {
    $_SESSION['last_login_attempt'] = 0;
    $_SESSION['login_attempt_count'] = 0;
}

if (time() - $_SESSION['last_login_attempt'] < 2) { // 2 second delay between attempts
    http_response_code(429);
    jsonResponse(false, 'Too many requests. Please wait 2 seconds.');
}

$_SESSION['last_login_attempt'] = time();
$_SESSION['login_attempt_count']++;

// Validate input
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$password = $_POST['password'] ?? '';

if (!$email || empty($password)) {
    jsonResponse(false, 'Please enter a valid email and password.');
}



try {
    
    $result = $auth->loginUser($email, $password);
    
    // Clear rate limiting on successful login
    if ($result['success']) {
        unset($_SESSION['login_attempt_count']);
        unset($_SESSION['last_login_attempt']);
    }
    
    // Return the result (already in correct format)
    jsonResponse($result['success'], $result['message'], $result['redirect']);
    
} catch (Exception $e) {
    error_log("Login process error: " . $e->getMessage());
    jsonResponse(false, 'An error occurred. Please try again later.');
}
