<?php
/**
 * Application Configuration and Constants
 * Centralizes all business rules and constants
 */

class AppConfig {
    
   
    
    // User roles with hierarchy
    const USER_ROLES = [
        'visitor' => 0,
        'customer' => 1,
        'mechanic' => 2,
        'secretary' => 3
    ];
    
   
    // Vehicle types (aligned with DB schema)
    const VEHICLE_TYPES = [
        'επιβατικό' => 'Passenger Car',
        'φορτηγό' => 'Truck',
        'λεωφορείο' => 'Bus'
    ];
    
    // Engine types (aligned with DB schema)
    const ENGINE_TYPES = [
        'βενζινοκίνητο' => 'Petrol',
        'ηλεκτροκίνητο' => 'Electric',
        'πετρελαιοκίνητο' => 'Diesel',
        'υγραέριο' => 'LPG',
        'μεικτό' => 'Mixed'
    ];
   
    
    
   
  
    /**
     * Check role hierarchy
     */
    public static function hasPermission($userRole, $requiredRole) {
        $userLevel = self::USER_ROLES[$userRole] ?? 0;
        $requiredLevel = self::USER_ROLES[$requiredRole] ?? 0;
        
        return $userLevel >= $requiredLevel;
    }
    
    /**
     * Get environment configuration
     */
    public static function getEnvironment() {
        return $_ENV['APP_ENV'] ?? 'development';
    }
    
    /**
     * Check if debug mode is enabled
     */
    public static function isDebugMode() {
        return self::getEnvironment() === 'development';
    }
}



/**
 * Security Configuration
 */
class SecurityConfig {
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
?>
