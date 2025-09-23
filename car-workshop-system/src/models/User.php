<?php
/**
 * User Model
 * Handles user data operations with role-specific functionality
 */

require_once __DIR__ . '/BaseModel.php';

class User extends BaseModel {
    protected $table = 'users';
    
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * Find user by username/email and password for login
     */
    public function findByCredentials($usernameOrEmail, $password) {
        $sql = "SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 'active'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        
        return false;
    }
    
    /**
     * Get all users with their role information (with pagination)
     */
    public function getAllWithRoles($limit = null, $offset = null, $sort = null) {
        // Map allowed sort tokens to safe ORDER BY clauses
        $orderSql = 'u.created_at DESC';
        if ($sort) {
            switch ($sort) {
                case 'name_asc':
                    $orderSql = "u.first_name ASC, u.last_name ASC";
                    break;
                case 'name_desc':
                    $orderSql = "u.first_name DESC, u.last_name DESC";
                    break;
                case 'username_asc':
                    $orderSql = "u.username ASC";
                    break;
                case 'username_desc':
                    $orderSql = "u.username DESC";
                    break;
                case 'email_asc':
                    $orderSql = "u.email ASC";
                    break;
                case 'email_desc':
                    $orderSql = "u.email DESC";
                    break;
                case 'created_at_asc':
                    $orderSql = "u.created_at ASC";
                    break;
                case 'created_at_desc':
                default:
                    $orderSql = "u.created_at DESC";
                    break;
            }
        }

        $sql = "SELECT u.*, 
                       CASE 
                           WHEN c.id IS NOT NULL THEN 'customer'
                           WHEN m.id IS NOT NULL THEN 'mechanic' 
                           WHEN u.role = 'secretary' THEN 'secretary'
                           ELSE u.role
                       END as actual_role,
                       CASE 
                           WHEN c.id IS NOT NULL THEN c.id
                           WHEN m.id IS NOT NULL THEN m.id 
                           ELSE NULL
                       END as role_id,
                       CASE WHEN u.status = 'active' THEN 1 ELSE 0 END as is_active,
                       c.afm,
                       c.address,
                       m.specialty
                FROM users u
                LEFT JOIN customers c ON u.id = c.user_id
                LEFT JOIN mechanics m ON u.id = m.user_id
                ORDER BY " . $orderSql;
        
        if ($limit !== null) {
                $sql .= " LIMIT " . (int)$limit;
            if ($offset !== null) {
                $sql .= " OFFSET " . (int)$offset;
            }
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    
    public function getTotalUsersCount() {
        $sql = "SELECT COUNT(*) FROM users";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }
    
    /**
     * Search users by username, last name, or AFM including role information (with pagination)
     */
    public function searchUsersWithRoles(string $query, int $limit = 50, int $offset = 0) {
    // Default ordering
    $orderSql = 'u.created_at DESC';
    
    $sql = "SELECT u.*, 
                       CASE 
                           WHEN c.id IS NOT NULL THEN 'customer'
                           WHEN m.id IS NOT NULL THEN 'mechanic' 
                           WHEN u.role = 'secretary' THEN 'secretary'
                           ELSE u.role
                       END as actual_role,
                       CASE 
                           WHEN c.id IS NOT NULL THEN c.id
                           WHEN m.id IS NOT NULL THEN m.id 
                           ELSE NULL
                       END as role_id,
                       CASE WHEN u.status = 'active' THEN 1 ELSE 0 END as is_active,
                       c.afm,
                       c.address,
                       m.specialty
                FROM users u
                LEFT JOIN customers c ON u.id = c.user_id
                LEFT JOIN mechanics m ON u.id = m.user_id
                WHERE (u.username LIKE ? OR u.last_name LIKE ? OR c.afm LIKE ?)
                ORDER BY " . $orderSql . "
                LIMIT ? OFFSET ?";
        
        $search = '%' . $query . '%';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $search, PDO::PARAM_STR);
        $stmt->bindValue(2, $search, PDO::PARAM_STR);
        $stmt->bindValue(3, $search, PDO::PARAM_STR);
        $stmt->bindValue(4, (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(5, (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get count of search results
     */
    public function getSearchUsersCount(string $query) {
        $sql = "SELECT COUNT(*) 
                FROM users u
                LEFT JOIN customers c ON u.id = c.user_id
                LEFT JOIN mechanics m ON u.id = m.user_id
                WHERE (u.username LIKE ? OR u.last_name LIKE ? OR c.afm LIKE ?)";
        
        $search = '%' . $query . '%';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $search, PDO::PARAM_STR);
        $stmt->bindValue(2, $search, PDO::PARAM_STR);
        $stmt->bindValue(3, $search, PDO::PARAM_STR);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }
    
    /**
     * Check if username exists
     */
    public function usernameExists($username, $excludeId = null) {
        $sql = "SELECT COUNT(*) FROM users WHERE username = ?";
        $params = [$username];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Check if email exists
     */
    public function emailExists($email, $excludeId = null) {
        $sql = "SELECT COUNT(*) FROM users WHERE email = ?";
        $params = [$email];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Check if id_number (ΑΤ) exists
     */
    public function idNumberExists($idNumber, $excludeId = null) {
        $sql = "SELECT COUNT(*) FROM users WHERE id_number = ?";
        $params = [$idNumber];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Create user with role-specific data
     */
    public function createUserWithRole($userData) {
        try {
            $this->db->beginTransaction();
            
            // Uniqueness validations
            if ($this->usernameExists($userData['username'])) {
                throw new Exception('Το username υπάρχει ήδη');
            }
            if ($this->emailExists($userData['email'])) {
                throw new Exception('Το email υπάρχει ήδη');
            }
            if ($this->idNumberExists($userData['id_number'])) {
                throw new Exception('Ο αριθμός ταυτότητας (ΑΤ) υπάρχει ήδη');
            }

            // Create base user
            $stmt = $this->db->prepare("
                INSERT INTO users (username, email, password, first_name, last_name, id_number, role, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'inactive')
            ");
            
            $result = $stmt->execute([
                $userData['username'],
                $userData['email'],
                password_hash($userData['password'], PASSWORD_DEFAULT),
                $userData['first_name'],
                $userData['last_name'],
                $userData['id_number'],
                $userData['role']
            ]);
            
            if (!$result) {
                throw new Exception('Failed to create user');
            }
            
            $userId = $this->db->lastInsertId();
            
            // Create role-specific record
            if ($userData['role'] === 'customer') {
                $stmt = $this->db->prepare("INSERT INTO customers (user_id, afm, address) VALUES (?, ?, ?)");
                $stmt->execute([$userId, $userData['afm'] ?? '', $userData['address'] ?? '']);
            } elseif ($userData['role'] === 'mechanic') {
                $stmt = $this->db->prepare("INSERT INTO mechanics (user_id, specialty) VALUES (?, ?)");
                $stmt->execute([$userId, $userData['specialty'] ?? '']);
            }
            
            $this->db->commit();
            return ['success' => true, 'user_id' => $userId];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Update user data
     */
    public function updateUser($userId, $userData) {
        try {
            $this->db->beginTransaction();
            
            // Update base user data
            $stmt = $this->db->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, email = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $result = $stmt->execute([
                $userData['first_name'],
                $userData['last_name'],
                $userData['email'],
                $userId
            ]);
            
            // Update password if provided
            if (!empty($userData['password'])) {
                $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([password_hash($userData['password'], PASSWORD_DEFAULT), $userId]);
            }
            
            // Update role-specific data
            if (isset($userData['role'])) {
                if ($userData['role'] === 'customer' && isset($userData['afm'])) {
                    $stmt = $this->db->prepare("
                        UPDATE customers 
                        SET afm = ?, address = ? 
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$userData['afm'], $userData['address'] ?? '', $userId]);
                } elseif ($userData['role'] === 'mechanic' && isset($userData['specialty'])) {
                    $stmt = $this->db->prepare("
                        UPDATE mechanics 
                        SET specialty = ? 
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$userData['specialty'], $userId]);
                }
            }
            
            $this->db->commit();
            return ['success' => true];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Update user status
     */
    public function updateStatus($userId, $status) {
        $stmt = $this->db->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$status, $userId]);
    }
    
    /**
     * Delete user 
     */
    public function deleteUser($userId) {
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
            $result = $stmt->execute([$userId]);
            
            if (!$result) {
                throw new Exception('Αποτυχία διαγραφής χρήστη');
            }
            
            $this->db->commit();
            return ['success' => true];
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get user with role information
     */
    public function getUserWithRole($userId) {
        $sql = "SELECT u.*, 
                       CASE 
                           WHEN c.id IS NOT NULL THEN 'customer'
                           WHEN m.id IS NOT NULL THEN 'mechanic' 
                           ELSE u.role
                       END as actual_role,
                       c.afm, c.address,
                       m.specialty
                FROM users u
                LEFT JOIN customers c ON u.id = c.user_id
                LEFT JOIN mechanics m ON u.id = m.user_id
                WHERE u.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all active customers for appointment booking
     */
    public function getAllCustomers() {
        $sql = "SELECT c.id, CONCAT(u.first_name, ' ', u.last_name) as full_name, u.email
                FROM customers c
                JOIN users u ON c.user_id = u.id
                WHERE u.status = 'active'
                ORDER BY u.first_name, u.last_name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>