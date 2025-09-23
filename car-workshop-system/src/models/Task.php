
<?php



/**
 * Task Model
 * Handles task data operations for the car workshop system
 */

require_once __DIR__ . '/BaseModel.php';

class Task extends BaseModel {
    protected $table = 'tasks';
    
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * Get all tasks for a specific appointment
     */
    public function getAppointmentTasks($appointmentId) {
        $sql = "SELECT t.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as mechanic_name
                FROM tasks t 
                LEFT JOIN mechanics m ON t.mechanic_id = m.id
                LEFT JOIN users u ON m.user_id = u.id
                WHERE t.appointment_id = ? 
                ORDER BY t.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$appointmentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Alias for getAppointmentTasks
     */
    public function getTasksByAppointment($appointmentId) {
        return $this->getAppointmentTasks($appointmentId);
    }
    
    /**
     * Get all tasks for a specific mechanic
     */
    public function getMechanicTasks($mechanicId) {
        $sql = "SELECT t.*, 
                       a.appointment_date,
                       a.appointment_time,
                       COALESCE(v.model, '[Όχημα διαγράφηκε]') as vehicle_model,
                       COALESCE(v.brand, '[Όχημα διαγράφηκε]') as vehicle_brand,
                       CONCAT(cu.first_name, ' ', cu.last_name) as customer_name
                FROM tasks t 
                LEFT JOIN appointments a ON t.appointment_id = a.id
                LEFT JOIN vehicles v ON a.vehicle_id = v.id
                LEFT JOIN customers c ON a.customer_id = c.id
                LEFT JOIN users cu ON c.user_id = cu.id
                WHERE t.mechanic_id = ? 
                ORDER BY a.appointment_date DESC, t.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$mechanicId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all tasks (for secretary)
     */
    public function getAllTasks() {
        $sql = "SELECT t.*, 
                       a.appointment_date,
                       a.appointment_time,
                       COALESCE(v.model, '[Όχημα διαγράφηκε]') as vehicle_model,
                       COALESCE(v.brand, '[Όχημα διαγράφηκε]') as vehicle_brand,
                       CONCAT(cu.first_name, ' ', cu.last_name) as customer_name,
                       CONCAT(mu.first_name, ' ', mu.last_name) as mechanic_name
                FROM tasks t 
                LEFT JOIN appointments a ON t.appointment_id = a.id
                LEFT JOIN vehicles v ON a.vehicle_id = v.id
                LEFT JOIN customers c ON a.customer_id = c.id
                LEFT JOIN users cu ON c.user_id = cu.id
                LEFT JOIN mechanics m ON t.mechanic_id = m.id
                LEFT JOIN users mu ON m.user_id = mu.id
                ORDER BY a.appointment_date DESC, t.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get mechanic ID from user ID
     */
    public function getMechanicIdFromUser($userId) {
        $sql = "SELECT id FROM mechanics WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['id'] : null;
    }
    
    /**
     * Check if mechanic can access task
     */
    public function canAccessTask($taskId, $mechanicId) {
        $sql = "SELECT COUNT(*) FROM tasks WHERE id = ? AND mechanic_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$taskId, $mechanicId]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Create a new task
     */
    public function createTask($data) {
        // Guard: task changes allowed only when appointment is IN PROGRESS and by the assigned mechanic
        $this->assertAppointmentAllowsTaskChange($data['appointment_id']);
        $this->assertMechanicIsAssignedToAppointment($data['appointment_id'], $data['mechanic_id']);

        // Calculate cost automatically
        $calculatedCost = $this->calculateTaskCost($data['completion_time'] ?? null);

        $sql = "INSERT INTO tasks (appointment_id, mechanic_id, description, materials, completion_time, cost) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            $data['appointment_id'],
            $data['mechanic_id'],
            $data['description'],
            $data['materials'],
            intval($data['completion_time']),
            $calculatedCost
        ]);
        
        if ($result) {
            $taskId = $this->db->lastInsertId();
            $this->recalculateAppointmentTotalCost($data['appointment_id']);
            return $taskId;
        }
        return false;
    }
    
    
    /**
     * Update an existing task (preserves existing cost unless explicitly provided)
     */
    public function updateTask($taskId, $mechanicId, $data) {
        // Security check - only allow mechanic to update their own tasks
        $checkSql = "SELECT cost FROM tasks WHERE id = ? AND mechanic_id = ?";
        $checkStmt = $this->db->prepare($checkSql);
        $checkStmt->execute([$taskId, $mechanicId]);
        $currentTask = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$currentTask) {
            throw new Exception('Δεν έχετε δικαίωμα επεξεργασίας αυτής της εργασίας');
        }
        
        // Guard: only when parent appointment is IN PROGRESS
        $appointmentId = $this->getAppointmentIdByTask($taskId);
        $this->assertAppointmentAllowsTaskChange($appointmentId);
        
        // Preserve existing cost unless explicitly provided
        $costToUse = isset($data['cost']) ? $data['cost'] : $currentTask['cost'];
        
        $sql = "UPDATE tasks 
                SET description = ?, materials = ?, completion_time = ?, cost = ?, updated_at = NOW()
                WHERE id = ? AND mechanic_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            $data['description'],
            $data['materials'],
            intval($data['completion_time']), // Ensure it's stored as integer (minutes)
            $costToUse,
            $taskId,
            $mechanicId
        ]);
        if ($result) {
            $this->recalculateAppointmentTotalCost($appointmentId);
        }
        return $result;
    }
    
    /**
     * Calculate task cost based on time and materials
     * Materials cost is randomly generated between 3-20 euros because, eh.
     * Labor cost is 50 euros flat rate per task
     */
    public function calculateTaskCost($timeMinutes = null, $materialsCost = null) {
        $laborRate = 50; // €50 flat rate per task (regardless of time)
        
        // Generate random materials cost if not provided
        if ($materialsCost === null) {
            $materialsCost = $this->generateRandomMaterialsCost();
        }
        
        return $laborRate + $materialsCost;
    }
    
    /**
     * Generate random materials cost between 3-20 euros
     */
    private function generateRandomMaterialsCost() {
        // Generate random cost between 3.00 and 20.00 euros
        // Using mt_rand for better randomness
        $minCents = 300; // 3.00 euros in cents
        $maxCents = 2000; // 20.00 euros in cents
        
        $randomCents = mt_rand($minCents, $maxCents);
        return $randomCents / 100; // Convert back to euros
    }
    
    /**
     * Delete a task
     */
    public function deleteTask($taskId, $mechanicId) {
        // Get task info for permission checking
        $taskInfoSql = "SELECT t.*, a.status as appointment_status FROM tasks t JOIN appointments a ON t.appointment_id = a.id WHERE t.id = ?";
        $taskInfoStmt = $this->db->prepare($taskInfoSql);
        $taskInfoStmt->execute([$taskId]);
        $taskInfo = $taskInfoStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$taskInfo) {
            throw new Exception('Εργασία δεν βρέθηκε');
        }
        
        // Security check - mechanics can only delete their own tasks, secretaries can delete any
        if ($mechanicId !== null) {
            // Mechanic role - can only delete their own tasks
            if ((int)$taskInfo['mechanic_id'] !== (int)$mechanicId) {
                throw new Exception('Δεν έχετε δικαίωμα διαγραφής αυτής της εργασίας');
            }
        }
        // If mechanicId is null, it means secretary role and they can delete any task
        
        // Only when parent appointment is IN PROGRESS
        $appointmentId = $this->getAppointmentIdByTask($taskId);
        $this->assertAppointmentAllowsTaskChange($appointmentId);
        
        // Delete the task
        if ($mechanicId !== null) {
            // For mechanics, use the double-check with mechanic_id
            $sql = "DELETE FROM tasks WHERE id = ? AND mechanic_id = ?";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$taskId, $mechanicId]);
        } else {
            // For secretaries, delete by task ID only
            $sql = "DELETE FROM tasks WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$taskId]);
        }
        
        if ($result) {
            $this->recalculateAppointmentTotalCost($appointmentId);
        }
        
        return $result;
    }
    
    /**
     * Get a specific task by ID for a mechanic (security check)
     */
    public function getMechanicTaskById($taskId, $mechanicId) {
        $sql = "SELECT t.* FROM tasks t WHERE t.id = ? AND t.mechanic_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$taskId, $mechanicId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Helpers: appointment/task constraints & cost recomputation
     */
    private function assertAppointmentAllowsTaskChange($appointmentId) {
        $sql = "SELECT status FROM appointments WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$appointmentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new Exception('Το ραντεβού δεν βρέθηκε');
        }
        if ($row['status'] !== 'ΣΕ ΕΞΕΛΙΞΗ') {
            throw new Exception('Εργασίες επιτρέπονται μόνο όταν το ραντεβού είναι ΣΕ ΕΞΕΛΙΞΗ');
        }
    }

    private function assertMechanicIsAssignedToAppointment($appointmentId, $mechanicId) {
        $sql = "SELECT mechanic_id FROM appointments WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$appointmentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new Exception('Το ραντεβού δεν βρέθηκε');
        }
        if ((int)$row['mechanic_id'] !== (int)$mechanicId) {
            throw new Exception('Μόνο ο ανατεθειμένος μηχανικός μπορεί να καταχωρεί εργασίες');
        }
    }

    private function getAppointmentIdByTask($taskId) {
        $sql = "SELECT appointment_id FROM tasks WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$taskId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['appointment_id'] : null;
    }

    private function recalculateAppointmentTotalCost($appointmentId) {
        
        $sql = "UPDATE appointments 
                SET total_cost = (
                    SELECT COALESCE(SUM(cost), 0)
                    FROM tasks
                    WHERE appointment_id = ?
                ), updated_at = NOW()
                WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$appointmentId, $appointmentId]);
    }
}
?>
