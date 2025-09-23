<?php

// Only enable in development
if (AppConfig::isDebugMode()) {
    // Enable all error reporting
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    
    // Enable detailed error logging
    ini_set('log_errors', 1);
    ini_set('error_log', '/Applications/XAMPP/xamppfiles/logs/php_debug.log');
    
    // MySQL error reporting
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}

function debug_log($message, $data = null) {
    if (!AppConfig::isDebugMode()) return;
    
    $timestamp = date('Y-m-d H:i:s');
    $output = "[$timestamp] DEBUG: " . $message;
    
    if ($data !== null) {
        $output .= " | Data: " . print_r($data, true);
    }
    
    error_log($output);
    
    
}




function debug_request() {
    if (!AppConfig::isDebugMode()) return;

    debug_log("HTTP Request", [
        'method' => $_SERVER['REQUEST_METHOD'],
        'uri' => $_SERVER['REQUEST_URI'],
        'get' => $_GET,
        'post' => $_POST,
        'headers' => function_exists('getallheaders') ? getallheaders() : [],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
}

// Auto-debug requests if included
if (AppConfig::isDebugMode() && !defined('DEBUG_INITIALIZED')) {
    define('DEBUG_INITIALIZED', true);
    debug_request();
}
?>
