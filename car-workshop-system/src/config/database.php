<?php
/**
 * Database Configuration and Connection Manager
 * Implements Singleton pattern for single database connection
 * Uses PDO with prepared statements to prevent SQL injection
 */
class Database {
    private static $instance = null;
    private $connection;
    
    // Database configuration - Update these for your environment
    private const DB_HOST = 'localhost';
    private const DB_NAME = 'car_workshop_db';
    private const DB_USER = 'root';  // Change for production
    private const DB_PASS = '';     // Change for production
    private const DB_CHARSET = 'utf8mb4';
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        $this->connect();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Establish database connection with PDO
     */
    private function connect(): void {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            self::DB_HOST,
            self::DB_NAME,
            self::DB_CHARSET
        );
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,  // Use real prepared statements
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . self::DB_CHARSET,
            PDO::ATTR_PERSISTENT => false  // Disable persistent connections
        ];
        
        try {
            $this->connection = new PDO($dsn, self::DB_USER, self::DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed. Please check configuration.");
        }
    }
    
    /**
     * Get PDO connection instance
     */
    public function getConnection(): PDO {
        // Check if connection is still alive
        if ($this->connection === null) {
            $this->connect();
        }
        
        return $this->connection;
    }
 
}