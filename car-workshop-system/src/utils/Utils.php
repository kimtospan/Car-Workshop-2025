<?php
/**
 * Utility Functions
 */

// Include necessary dependencies
require_once __DIR__ . '/../config/AppConfig.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../includes/language.php';

class Utils {
    // This is quite useless but eh
    public static function escape($value) {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Format date for display
     */
    public static function formatDate($date, $format = 'd/m/Y') {
        if (!$date) return '';
        
        if (is_string($date)) {
            $date = new DateTime($date);
        }
        
        return $date->format($format);
    }
}


/**
 * Get customer id from a user id
 */
function getCustomerIdFromUserId(int $userId): ?int {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare('SELECT id FROM customers WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int)$row['id'] : null;
}

/**
 * Get mechanic id from a user id
 */
function getMechanicIdFromUserId(int $userId): ?int {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare('SELECT id FROM mechanics WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int)$row['id'] : null;
}

?>
