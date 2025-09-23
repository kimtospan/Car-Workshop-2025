<?php
/**
 * Appointment Model
 * Handles appointment data operations for the car workshop system
 */

require_once __DIR__ . '/BaseModel.php';

class Appointment extends BaseModel {
    protected $table = 'appointments';
    
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * Get all appointments for a specific customer
     */
    public function getCustomerAppointments($customerId) {
        $sql = "SELECT a.*, 
                       COALESCE(v.model, '[Όχημα διαγράφηκε]') as model, 
                       COALESCE(v.brand, '[Όχημα διαγράφηκε]') as brand, 
                       COALESCE(v.serial_number, '[Όχημα διαγράφηκε]') as serial_number,
                       CONCAT(u.first_name, ' ', u.last_name) as mechanic_name
                FROM appointments a 
                LEFT JOIN vehicles v ON a.vehicle_id = v.id
                LEFT JOIN mechanics m ON a.mechanic_id = m.id
                LEFT JOIN users u ON m.user_id = u.id
                WHERE a.customer_id = ? 
                ORDER BY a.appointment_date DESC, a.appointment_time DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$customerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all appointments for a specific mechanic
     */
    public function getMechanicAppointments($mechanicId) {
        $sql = "SELECT a.*, 
                       COALESCE(v.model, '[Όχημα διαγράφηκε]') as model, 
                       COALESCE(v.brand, '[Όχημα διαγράφηκε]') as brand, 
                       COALESCE(v.serial_number, '[Όχημα διαγράφηκε]') as serial_number,
                       CONCAT(cu.first_name, ' ', cu.last_name) as customer_name,
                       c.address as customer_address,
                       c.afm as customer_afm
                FROM appointments a 
                LEFT JOIN vehicles v ON a.vehicle_id = v.id
                LEFT JOIN customers c ON a.customer_id = c.id
                LEFT JOIN users cu ON c.user_id = cu.id
                WHERE a.mechanic_id = ? 
                ORDER BY a.appointment_date DESC, a.appointment_time DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$mechanicId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get a specific appointment by ID for a mechanic (security check)
     */
    public function getMechanicAppointmentById($appointmentId, $mechanicId) {
        $sql = "SELECT a.*, 
                       COALESCE(v.model, '[Όχημα διαγράφηκε]') as model, 
                       COALESCE(v.brand, '[Όχημα διαγράφηκε]') as brand, 
                       COALESCE(v.serial_number, '[Όχημα διαγράφηκε]') as serial_number,
                       CONCAT(cu.first_name, ' ', cu.last_name) as customer_name
                FROM appointments a 
                LEFT JOIN vehicles v ON a.vehicle_id = v.id
                LEFT JOIN customers c ON a.customer_id = c.id
                LEFT JOIN users cu ON c.user_id = cu.id
                WHERE a.id = ? AND a.mechanic_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$appointmentId, $mechanicId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get appointment statuses for display
     */
    public function getStatuses() {
        return [
            'ΔΗΜΙΟΥΡΓΗΜΕΝΟ' => 'Created',
            'ΣΕ ΕΞΕΛΙΞΗ' => 'In Progress',
            'ΠΕΡΑΙΩΜΕΝΟ' => 'Completed',
            'ΑΚΥΡΩΜΕΝΟ' => 'Cancelled'
        ];
    }
    
    /**
     * Get appointment reasons for display
     */
    public function getReasons() {
        return [
            'σέρβις' => 'Service',
            'επιδιόρθωση' => 'Repair'
        ];
    }
    
    /**
     * Update an appointment (for secretary use)
     */
    public function updateAppointment($appointmentId, $updateData) {
        try {
            // Build the SET clause dynamically based on provided data
            $setParts = [];
            $params = [];
            
            foreach ($updateData as $field => $value) {
                $setParts[] = "{$field} = ?";
                $params[] = $value;
            }
            
            if (empty($setParts)) {
                return false;
            }
            
            // Add the appointment ID to parameters
            $params[] = $appointmentId;
            
            $sql = "UPDATE appointments SET " . implode(', ', $setParts) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            
            return $stmt->execute($params);
        } catch (Exception $e) {
            throw new Exception('Σφάλμα κατά την ενημέρωση του ραντεβού: ' . $e->getMessage());
        }
    }
    
    /**
     * Get all appointments with full details 
     */
    public function getAllAppointments($conditions = [], $pagination = null) {
        $sql = "SELECT a.*, 
                       COALESCE(v.model, '[Όχημα διαγράφηκε]') as model, 
                       COALESCE(v.brand, '[Όχημα διαγράφηκε]') as brand, 
                       COALESCE(v.serial_number, '[Όχημα διαγράφηκε]') as serial_number,
                       CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                       u.email as customer_email,
                       CONCAT(mu.first_name, ' ', mu.last_name) as mechanic_name,
                       c.afm as customer_afm
                FROM appointments a 
                LEFT JOIN vehicles v ON a.vehicle_id = v.id
                LEFT JOIN customers c ON a.customer_id = c.id
                LEFT JOIN users u ON c.user_id = u.id
                LEFT JOIN mechanics m ON a.mechanic_id = m.id
                LEFT JOIN users mu ON m.user_id = mu.id";
        
        $params = [];
        $whereConditions = [];
        
        // Add conditions
        if (!empty($conditions['status'])) {
            $whereConditions[] = "a.status = ?";
            $params[] = $conditions['status'];
        }
        
        if (!empty($conditions['date_from'])) {
            $whereConditions[] = "a.appointment_date >= ?";
            $params[] = $conditions['date_from'];
        }
        
        if (!empty($conditions['date_to'])) {
            $whereConditions[] = "a.appointment_date <= ?";
            $params[] = $conditions['date_to'];
        }
        
        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(' AND ', $whereConditions);
        }
        
        $sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";
        
        if ($pagination) {
            $sql .= " LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Cancel an appointment (for secretary use)
     */
    public function cancelAppointment($appointmentId) {
        try {
            $sql = "UPDATE appointments 
                    SET status = 'ΑΚΥΡΩΜΕΝΟ', cancelled_at = NOW(), updated_at = NOW()
                    WHERE id = ? AND status NOT IN ('ΣΕ ΕΞΕΛΙΞΗ', 'ΠΕΡΑΙΩΜΕΝΟ', 'ΑΚΥΡΩΜΕΝΟ')";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$appointmentId]);
            
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            throw new Exception('Σφάλμα κατά την ακύρωση του ραντεβού: ' . $e->getMessage());
        }
    }
    
    /**
     * Get all mechanics for secretary appointment management
     */
    public function getAllMechanics() {
        $sql = "SELECT m.id, CONCAT(u.first_name, ' ', u.last_name) as full_name, u.email
                FROM mechanics m
                JOIN users u ON m.user_id = u.id
                WHERE u.status = 'active'
                ORDER BY u.first_name, u.last_name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Check mechanic availability for a specific date and time slot
     * Each appointment blocks a mechanic for 2 hours 
     */
    public function checkMechanicAvailability($appointmentDate, $appointmentTime) {
        // Convert time to DateTime for calculations
        $appointmentDateTime = new DateTime($appointmentDate . ' ' . $appointmentTime);
        $appointmentEndTime = clone $appointmentDateTime;
        $appointmentEndTime->add(new DateInterval('PT2H')); // Add 2 hours
        
        // Workshop hours: 8 to 4
        $workshopStart = new DateTime($appointmentDate . ' 08:00:00');
        $workshopEnd = new DateTime($appointmentDate . ' 16:00:00');
        
        // Check if appointment is within workshop hours
        if ($appointmentDateTime < $workshopStart || $appointmentEndTime > $workshopEnd) {
            throw new Exception('Το ραντεβού πρέπει να είναι εντός ωραρίου λειτουργίας (08:00 - 16:00)');
        }
        
        // Get all active mechanics
        $activeMechanics = $this->getAllMechanics();
        
        if (empty($activeMechanics)) {
            throw new Exception('Δεν υπάρχουν διαθέσιμοι μηχανικοί');
        }
        
        $availableMechanics = [];
        
        foreach ($activeMechanics as $mechanic) {
            if ($this->isMechanicAvailable($mechanic['id'], $appointmentDate, $appointmentTime)) {
                $availableMechanics[] = $mechanic;
            }
        }
        
        return $availableMechanics;
    }
    
    /**
     * Check if a specific mechanic is available at a given date/time
     */
    private function isMechanicAvailable($mechanicId, $appointmentDate, $appointmentTime) {
        $appointmentDateTime = new DateTime($appointmentDate . ' ' . $appointmentTime);
        
        // Check existing appointments for this mechanic
        $sql = "SELECT appointment_date, appointment_time 
                FROM appointments 
                WHERE mechanic_id = ? 
                AND appointment_date = ? 
                AND status NOT IN ('ΑΚΥΡΩΜΕΝΟ')";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$mechanicId, $appointmentDate]);
        $existingAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($existingAppointments as $existing) {
            $existingStart = new DateTime($existing['appointment_date'] . ' ' . $existing['appointment_time']);
            $existingEnd = clone $existingStart;
            $existingEnd->add(new DateInterval('PT2H')); // Each appointment blocks 2 hours
            
            $newEnd = clone $appointmentDateTime;
            $newEnd->add(new DateInterval('PT2H'));
            
            // Check for time overlap
            if (($appointmentDateTime >= $existingStart && $appointmentDateTime < $existingEnd) ||
                ($newEnd > $existingStart && $newEnd <= $existingEnd) ||
                ($appointmentDateTime <= $existingStart && $newEnd >= $existingEnd)) {
                return false; // Mechanic is busy
            }
        }
        
        return true; // Mechanic is available
    }
    
    
    public function createAppointment($appointmentData) {
        try {
            $this->beginTransaction();
            
            // Validate required fields
            $required = ['customer_id', 'vehicle_id', 'appointment_date', 'appointment_time', 'reason'];
            $this->validateRequired($appointmentData, $required);
            
            // Validate business rule: problem_description required for repairs
            if ($appointmentData['reason'] === 'επιδιόρθωση' && empty($appointmentData['problem_description'])) {
                throw new Exception('Η περιγραφή προβλήματος είναι υποχρεωτική για επιδιορθώσεις');
            }
            
            // Check mechanic availability
            $availableMechanics = $this->checkMechanicAvailability(
                $appointmentData['appointment_date'], 
                $appointmentData['appointment_time']
            );
            
            if (empty($availableMechanics)) {
                throw new Exception('Δεν υπάρχει διαθέσιμος μηχανικός για την επιλεγμένη ημερομηνία και ώρα. Παρακαλώ επιλέξτε άλλη ώρα.');
            }
            
            // Randomly select an available mechanic
            $selectedMechanic = $availableMechanics[array_rand($availableMechanics)];
            
            // Create the appointment using BaseModel's create method
            $appointmentDataToInsert = [
                'customer_id' => $appointmentData['customer_id'],
                'vehicle_id' => $appointmentData['vehicle_id'],
                'mechanic_id' => $selectedMechanic['id'],
                'appointment_date' => $appointmentData['appointment_date'],
                'appointment_time' => $appointmentData['appointment_time'],
                'reason' => $appointmentData['reason'],
                'problem_description' => $appointmentData['problem_description'] ?? null,
                'status' => 'ΔΗΜΙΟΥΡΓΗΜΕΝΟ'
            ];
            
            $appointmentId = $this->create($appointmentDataToInsert);
            
            if (!$appointmentId) {
                throw new Exception('Σφάλμα κατά τη δημιουργία του ραντεβού');
            }
            
            $this->commit();
            
            return [
                'success' => true,
                'appointment_id' => $appointmentId,
                'assigned_mechanic' => $selectedMechanic['full_name'],
                'message' => 'Το ραντεβού δημιουργήθηκε επιτυχώς και ανατέθηκε στον μηχανικό: ' . $selectedMechanic['full_name']
            ];
            
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * Get available time slots for a specific date
     */
    public function getAvailableTimeSlots($date) {
        $timeSlots = [];
        $startHour = 8;
        $endHour = 16; 
        
        // Generate 2-hour time slots
        for ($hour = $startHour; $hour < $endHour; $hour += 2) {
            $timeSlot = sprintf('%02d:00', $hour);
            
            // Check if any mechanic is available for this slot
            try {
                $availableMechanics = $this->checkMechanicAvailability($date, $timeSlot);
                if (!empty($availableMechanics)) {
                    $timeSlots[] = [
                        'time' => $timeSlot,
                        'display' => sprintf('%02d:00 - %02d:00', $hour, $hour + 2),
                        'available_mechanics' => count($availableMechanics)
                    ];
                }
            } catch (Exception $e) {
                // Skip this time slot if there's an error
                continue;
            }
        }
        
        return $timeSlots;
    }
    
    /**
     * Get appointment reasons for display
     */
    public function getAppointmentReasons() {
        return [
            'σέρβις' => 'Σέρβις',
            'επιδιόρθωση' => 'Επιδιόρθωση'
        ];
    }
    
    /**
     * Get customer vehicles for appointment booking
     */
    public function getCustomerVehiclesForAppointment($customerId) {
        $sql = "SELECT id, CONCAT(brand, ' ', model, ' (', serial_number, ')') as vehicle_display,
                       brand, model, serial_number
                FROM vehicles 
                WHERE customer_id = ? 
                ORDER BY brand, model";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$customerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get appointments count with conditions
     */
    public function getAppointmentsCount($conditions = []) {
        $sql = "SELECT COUNT(*) FROM appointments a";
        $params = [];
        $whereConditions = [];
        
        // Add conditions
        if (!empty($conditions['status'])) {
            $whereConditions[] = "a.status = ?";
            $params[] = $conditions['status'];
        }
        
        if (!empty($conditions['date_from'])) {
            $whereConditions[] = "a.appointment_date >= ?";
            $params[] = $conditions['date_from'];
        }
        
        if (!empty($conditions['date_to'])) {
            $whereConditions[] = "a.appointment_date <= ?";
            $params[] = $conditions['date_to'];
        }
        
        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(' AND ', $whereConditions);
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
    
    /**
     * Search appointments
     */
    public function searchAppointments($searchTerm, $conditions = [], $pagination = null) {
        $sql = "SELECT a.*, 
                       COALESCE(v.model, '[Όχημα διαγράφηκε]') as model, 
                       COALESCE(v.brand, '[Όχημα διαγράφηκε]') as brand, 
                       COALESCE(v.serial_number, '[Όχημα διαγράφηκε]') as serial_number,
                       CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                       CONCAT(mu.first_name, ' ', mu.last_name) as mechanic_name,
                       c.afm as customer_afm
                FROM appointments a 
                LEFT JOIN vehicles v ON a.vehicle_id = v.id
                LEFT JOIN customers c ON a.customer_id = c.id
                LEFT JOIN users u ON c.user_id = u.id
                LEFT JOIN mechanics m ON a.mechanic_id = m.id
                LEFT JOIN users mu ON m.user_id = mu.id";
        
        $params = [];
        $whereConditions = [];
        
        // Add search conditions
        if (!empty($searchTerm)) {
            $searchConditions = [
                "CONCAT(u.first_name, ' ', u.last_name) LIKE ?",
                "c.afm LIKE ?",
                "v.serial_number LIKE ?",
                "v.brand LIKE ?",
                "v.model LIKE ?",
                "a.problem_description LIKE ?"
            ];
            $whereConditions[] = "(" . implode(' OR ', $searchConditions) . ")";
            
            for ($i = 0; $i < count($searchConditions); $i++) {
                $params[] = "%$searchTerm%";
            }
        }
        
        // Apply customer / mechanic scoping when provided
        if (!empty($conditions['customer_id'])) {
            $whereConditions[] = "a.customer_id = ?";
            $params[] = $conditions['customer_id'];
        }
        if (!empty($conditions['mechanic_id'])) {
            $whereConditions[] = "a.mechanic_id = ?";
            $params[] = $conditions['mechanic_id'];
        }
        
        // Add additional conditions
        if (!empty($conditions['status'])) {
            $whereConditions[] = "a.status = ?";
            $params[] = $conditions['status'];
        }
        
        if (!empty($conditions['date_from'])) {
            $whereConditions[] = "a.appointment_date >= ?";
            $params[] = $conditions['date_from'];
        }
        
        if (!empty($conditions['date_to'])) {
            $whereConditions[] = "a.appointment_date <= ?";
            $params[] = $conditions['date_to'];
        }
        
        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(' AND ', $whereConditions);
        }
        
        $sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";
        
        if ($pagination) {
            $sql .= " LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get search count for pagination
     *
     * @param string $searchTerm
     * @param array $conditions
     * @return int
     */
    public function getSearchCount($searchTerm, $conditions = []): int {
        $sql = "SELECT COUNT(*) FROM appointments a 
                LEFT JOIN vehicles v ON a.vehicle_id = v.id
                LEFT JOIN customers c ON a.customer_id = c.id
                LEFT JOIN users u ON c.user_id = u.id
                LEFT JOIN mechanics m ON a.mechanic_id = m.id
                LEFT JOIN users mu ON m.user_id = mu.id";
        
        $params = [];
        $whereConditions = [];
        
        // Add search conditions
        if (!empty($searchTerm)) {
            $searchConditions = [
                "CONCAT(u.first_name, ' ', u.last_name) LIKE ?",
                "c.afm LIKE ?",
                "v.serial_number LIKE ?",
                "v.brand LIKE ?",
                "v.model LIKE ?",
                "a.problem_description LIKE ?"
            ];
            $whereConditions[] = "(" . implode(' OR ', $searchConditions) . ")";
            
            for ($i = 0; $i < count($searchConditions); $i++) {
                $params[] = "%$searchTerm%";
            }
        }
        
        // Add additional conditions
        if (!empty($conditions['status'])) {
            $whereConditions[] = "a.status = ?";
            $params[] = $conditions['status'];
        }
        
        if (!empty($conditions['date_from'])) {
            $whereConditions[] = "a.appointment_date >= ?";
            $params[] = $conditions['date_from'];
        }
        
        if (!empty($conditions['date_to'])) {
            $whereConditions[] = "a.appointment_date <= ?";
            $params[] = $conditions['date_to'];
        }
        
        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(' AND ', $whereConditions);
        }
        
    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
 
    return (int) $stmt->fetchColumn();
    }
    
    /**
     * Get appointment by ID with full details for view modal
     */
    public function getAppointmentById($appointmentId) {
        $sql = "SELECT a.*, 
                       COALESCE(v.model, '[Όχημα διαγράφηκε]') as model, 
                       COALESCE(v.brand, '[Όχημα διαγράφηκε]') as brand, 
                       COALESCE(v.serial_number, '[Όχημα διαγράφηκε]') as serial_number,
                       COALESCE(v.production_date, '') as production_date,
                       COALESCE(v.vehicle_type, '') as vehicle_type,
                       COALESCE(v.engine_type, '') as engine_type,
                       COALESCE(v.door_count, 0) as door_count,
                       COALESCE(v.wheel_count, 0) as wheel_count,
                       COALESCE(v.acquisition_year, '') as acquisition_year,
                       CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                       u.email as customer_email,
                       c.afm as customer_afm,
                       c.address as customer_address,
                       CONCAT(mu.first_name, ' ', mu.last_name) as mechanic_name,
                       mu.email as mechanic_email,
                       m.specialty as mechanic_specialty
                FROM appointments a 
                LEFT JOIN vehicles v ON a.vehicle_id = v.id
                LEFT JOIN customers c ON a.customer_id = c.id
                LEFT JOIN users u ON c.user_id = u.id
                LEFT JOIN mechanics m ON a.mechanic_id = m.id
                LEFT JOIN users mu ON m.user_id = mu.id
                WHERE a.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$appointmentId]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($appointment) {
            // Get associated tasks for this appointment
            $tasksSql = "SELECT t.id, t.description, t.materials, t.completion_time, t.cost, t.created_at, t.updated_at
                         FROM tasks t 
                         WHERE t.appointment_id = ? 
                         ORDER BY t.created_at DESC";
            
            $tasksStmt = $this->db->prepare($tasksSql);
            $tasksStmt->execute([$appointmentId]);
            $appointment['tasks'] = $tasksStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        return $appointment;
    }
    
    /**
     * Update appointment status
     */
    public function updateAppointmentStatus($appointmentId, $status, $mechanicId = null) {
        try {
            $this->beginTransaction();
            
            // Validate status against schema enum
            $allowed = ['ΔΗΜΙΟΥΡΓΗΜΕΝΟ', 'ΣΕ ΕΞΕΛΙΞΗ', 'ΠΕΡΑΙΩΜΕΝΟ', 'ΑΚΥΡΩΜΕΝΟ'];
            if (!in_array($status, $allowed, true)) {
                throw new Exception('Μη αποδεκτή κατάσταση ραντεβού');
            }

            $updateData = ['status' => $status];
            
            if ($mechanicId !== null) {
                $updateData['mechanic_id'] = $mechanicId;
            }

            // Maintain timestamps according to status transitions
            if ($status === 'ΠΕΡΑΙΩΜΕΝΟ') {
                $updateData['completed_at'] = date('Y-m-d H:i:s');
                $updateData['cancelled_at'] = null;
            } elseif ($status === 'ΑΚΥΡΩΜΕΝΟ') {
                $updateData['cancelled_at'] = date('Y-m-d H:i:s');
                $updateData['completed_at'] = null;
            }
            
            $result = $this->update($appointmentId, $updateData);
            
            $this->commit();
            return $result;
            
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * Update appointment date and time
     */
    public function updateAppointmentDateTime($appointmentId, $appointmentDate, $appointmentTime) {
        // Check mechanic availability for new time
        $appointment = $this->getAppointmentById($appointmentId);
        if (!$appointment) {
            throw new Exception('Appointment not found with ID: ' . $appointmentId);
        }
        
        // Allow date/time change only when status = ΔΗΜΙΟΥΡΓΗΜΕΝΟ
        if ($appointment['status'] !== 'ΔΗΜΙΟΥΡΓΗΜΕΝΟ') {
            throw new Exception('Cannot change date/time for appointment with status "' . $appointment['status'] . '". Only appointments with status "ΔΗΜΙΟΥΡΓΗΜΕΝΟ" can be modified.');
        }
        
        // Validate input parameters
        if (empty($appointmentDate) || empty($appointmentTime)) {
            throw new Exception('Both appointment date and time are required');
        }
        
        // Additional date validation
        $dateObj = DateTime::createFromFormat('Y-m-d', $appointmentDate);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $appointmentDate) {
            throw new Exception('Invalid date format: ' . $appointmentDate . '. Expected format: YYYY-MM-DD');
        }
        
        // Check if date is not in the past
        $today = new DateTime('today');
        if ($dateObj < $today) {
            throw new Exception('Cannot schedule appointment for past date: ' . $appointmentDate);
        }
        
        // Get all mechanics to check availability
        $sql = "SELECT m.id, CONCAT(u.first_name, ' ', u.last_name) as full_name 
                FROM mechanics m 
                JOIN users u ON m.user_id = u.id 
                WHERE u.status = 'active'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $allMechanics = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($allMechanics)) {
            throw new Exception('No active mechanics found in the system');
        }
        
        $availableMechanics = [];
        foreach ($allMechanics as $mechanic) {
            if ($this->isMechanicAvailable($mechanic['id'], $appointmentDate, $appointmentTime)) {
                $availableMechanics[] = $mechanic;
            }
        }
        
        // Check if current mechanic is available or find a new one
        $mechanicId = $appointment['mechanic_id'];
        $mechanicAvailable = false;
        $currentMechanicName = '';
        
        // Get current mechanic name for better error messaging
        if ($mechanicId) {
            $mechanicSql = "SELECT CONCAT(u.first_name, ' ', u.last_name) as full_name 
                           FROM mechanics m 
                           JOIN users u ON m.user_id = u.id 
                           WHERE m.id = ?";
            $mechanicStmt = $this->db->prepare($mechanicSql);
            $mechanicStmt->execute([$mechanicId]);
            $currentMechanic = $mechanicStmt->fetch(PDO::FETCH_ASSOC);
            $currentMechanicName = $currentMechanic ? $currentMechanic['full_name'] : 'Unknown';
        }
        
        foreach ($availableMechanics as $mechanic) {
            if ($mechanic['id'] == $mechanicId) {
                $mechanicAvailable = true;
                break;
            }
        }
        
        if (!$mechanicAvailable) {
            if (empty($availableMechanics)) {
                throw new Exception('No mechanics are available for the selected date and time (' . $appointmentDate . ' at ' . $appointmentTime . '). Please choose a different time slot.');
            }
            // Assign a random available mechanic
            $newMechanic = $availableMechanics[array_rand($availableMechanics)];
            $mechanicId = $newMechanic['id'];
            error_log("Appointment {$appointmentId}: Current mechanic ({$currentMechanicName}) not available. Reassigned to: " . $newMechanic['full_name']);
        }
        
        try {
            $this->beginTransaction();
            
            $updateData = [
                'appointment_date' => $appointmentDate,
                'appointment_time' => $appointmentTime,
                'mechanic_id' => $mechanicId
            ];
            
            $result = $this->update($appointmentId, $updateData);
            
            if (!$result) {
                throw new Exception('Database update failed for appointment ID: ' . $appointmentId);
            }
            
            $this->commit();
            
            // Log successful update
            error_log("Appointment {$appointmentId} successfully updated to {$appointmentDate} at {$appointmentTime} with mechanic ID {$mechanicId}");
            
            return $result;
            
        } catch (Exception $e) {
            $this->rollback();
            error_log("Database error updating appointment {$appointmentId}: " . $e->getMessage());
            throw new Exception('Failed to update appointment in database: ' . $e->getMessage());
        }
    }
}