<?php
/**
 * FormProcessor
 * Common form processing patterns to eliminate code duplication
 */

require_once __DIR__ . '/ValidationService.php';
require_once __DIR__ . '/../../includes/language.php';
require_once __DIR__ . '/../models/Car.php';
require_once __DIR__ . '/../models/Appointment.php';
require_once __DIR__ . '/../models/Task.php';
require_once __DIR__ . '/../models/User.php';

class FormProcessor {
    private $db;
    private $auth;
    
    public function __construct($db, $auth) {
        $this->db = $db;
        $this->auth = $auth;
    }
    
    /**
     * Process profile update (common to all user types)
     */
    public function processProfileUpdate($postData) {
        try {
            // Optional CSRF validation when token supplied
            if (!empty($postData['csrf_token'])) {
                require_once __DIR__ . '/../config/AppConfig.php';
                if (!\SecurityConfig::validateCSRFToken($postData['csrf_token'])) {
                    return [ 'success' => false, 'message' => 'Invalid CSRF token' ];
                }
            }
            $currentUser = $this->auth->getCurrentUser();
            
            // Sanitize input
            $data = ValidationService::sanitizeInput($postData);
            
            // Validate data
            $errors = ValidationService::validateProfileData($data, $currentUser['role']);
            if (ValidationService::hasErrors($errors)) {
                return [
                    'success' => false, 
                    'message' => 'Validation errors',
                    'errors' => $errors
                ];
            }
            
            // Check if email is already taken by another user
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$data['email'], $currentUser['id']]);
            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'Το email χρησιμοποιείται ήδη από άλλον χρήστη'
                ];
            }
            
            $this->db->beginTransaction();
            
            // Update users table
            $stmt = $this->db->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
            $userResult = $stmt->execute([
                $data['first_name'],
                $data['last_name'],
                $data['email'],
                $currentUser['id']
            ]);
            
            // Role-specific updates
            if ($currentUser['role'] === 'customer') {
                $stmt = $this->db->prepare("UPDATE customers SET afm = ?, address = ? WHERE user_id = ?");
                $stmt->execute([
                    $data['afm'],
                    $data['address'],
                    $currentUser['id']
                ]);
            } elseif ($currentUser['role'] === 'mechanic') {
                $stmt = $this->db->prepare("UPDATE mechanics SET specialty = ? WHERE user_id = ?");
                $stmt->execute([
                    $data['specialty'] ?? '',
                    $currentUser['id']
                ]);
            }
            
            if ($userResult) {
                $this->db->commit();
                return [
                    'success' => true,
                    'message' => t('profile_updated_successfully')
                ];
            } else {
                $this->db->rollback();
                throw new Exception('Αποτυχία ενημέρωσης προφίλ');
            }
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process vehicle creation
     */
    public function processVehicleCreation($postData, $customerId) {
        try {
            // Sanitize input
            $data = ValidationService::sanitizeInput($postData);
            $data['customer_id'] = $customerId;
            
            // Validate data
            $errors = ValidationService::validateVehicleData($data);
            if (ValidationService::hasErrors($errors)) {
                return [
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $errors
                ];
            }
            
            // Check for duplicate serial number
            $stmt = $this->db->prepare("SELECT id FROM vehicles WHERE serial_number = ?");
            $stmt->execute([$data['serial_number']]);
            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'Υπάρχει ήδη όχημα με αυτόν τον αριθμό σάσι'
                ];
            }
            
            // Create vehicle
            $carModel = new Car();
            $createResult = $carModel->createVehicle($data);
            if (is_array($createResult) && !empty($createResult['success'])) {
                return [
                    'success' => true,
                    'message' => t('vehicle_added_successfully')
                ];
            } else {
                // Propagate detailed error if available
                $msg = 'Αποτυχία δημιουργίας οχήματος';
                if (is_array($createResult) && !empty($createResult['message'])) {
                    $msg = $createResult['message'];
                }
                return [
                    'success' => false,
                    'message' => $msg,
                    'errors' => isset($createResult['errors']) ? $createResult['errors'] : []
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    
    
    /**
     * Process task creation (for mechanics)
     */
    public function processTaskCreation($postData, $mechanicId) {
        try {
            // Optional CSRF validation when token supplied
            if (!empty($postData['csrf_token'])) {
                require_once __DIR__ . '/../config/AppConfig.php';
                if (!\SecurityConfig::validateCSRFToken($postData['csrf_token'])) {
                    return [ 'success' => false, 'message' => 'Invalid CSRF token' ];
                }
            }
            // Sanitize input
            $data = ValidationService::sanitizeInput($postData);
            
            // Get appointment details first to validate and get correct mechanic
            $appointmentModel = new Appointment();
            $appointment = $appointmentModel->getAppointmentById($data['appointment_id']);
            
            if (!$appointment) {
                return [
                    'success' => false,
                    'message' => 'Το ραντεβού δεν βρέθηκε'
                ];
            }
            
            // Check if appointment is in progress
            if ($appointment['status'] !== 'ΣΕ ΕΞΕΛΙΞΗ') {
                return [
                    'success' => false,
                    'message' => 'Δεν μπορείτε να προσθέσετε εργασίες σε αυτό το ραντεβού'
                ];
            }
            
            // Use the mechanic assigned to the appointment
            $data['mechanic_id'] = $appointment['mechanic_id'];
            
            // Validate data (for creation, mechanic_id is required)
            $errors = ValidationService::validateTaskData($data, false);
            if (ValidationService::hasErrors($errors)) {
                return [
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $errors
                ];
            }
            
            // Create task
            $taskModel = new Task();
            if ($taskModel->createTask($data)) {
                return [
                    'success' => true,
                    'message' => t('task_added_successfully')
                ];
            } else {
                throw new Exception('Αποτυχία δημιουργίας εργασίας');
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process task update (for mechanics)
     */
    public function processTaskUpdate($postData, $mechanicId) {
        try {
            // Optional CSRF validation when token supplied
            if (!empty($postData['csrf_token'])) {
                require_once __DIR__ . '/../config/AppConfig.php';
                if (!\SecurityConfig::validateCSRFToken($postData['csrf_token'])) {
                    return [ 'success' => false, 'message' => 'Invalid CSRF token' ];
                }
            }
            $taskId = (int)$postData['task_id'];
            
            // Sanitize input
            $data = ValidationService::sanitizeInput($postData);
            
            // Validate data (for updates, mechanic_id is not required in form data)
            $errors = ValidationService::validateTaskData($data, true);
            if (ValidationService::hasErrors($errors)) {
                return [
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $errors
                ];
            }
            
            // Update task
            $taskModel = new Task();
            if ($taskModel->updateTask($taskId, $mechanicId, $data)) {
                return [
                    'success' => true,
                    'message' => t('task_updated_successfully')
                ];
            } else {
                throw new Exception('Αποτυχία ενημέρωσης εργασίας');
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process task deletion (for mechanics)
     */
    public function processTaskDeletion($postData, $mechanicId) {
        try {
            // Optional CSRF validation when token supplied
            if (!empty($postData['csrf_token'])) {
                require_once __DIR__ . '/../config/AppConfig.php';
                if (!\SecurityConfig::validateCSRFToken($postData['csrf_token'])) {
                    return [ 'success' => false, 'message' => 'Invalid CSRF token' ];
                }
            }
            $taskId = (int)$postData['task_id'];
            
            // Delete task
            $taskModel = new Task();
            if ($taskModel->deleteTask($taskId, $mechanicId)) {
                return [
                    'success' => true,
                    'message' => t('task_deleted_successfully')
                ];
            } else {
                throw new Exception('Αποτυχία διαγραφής εργασίας');
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process appointment status update (for secretary)
     */
    public function processAppointmentStatusUpdate($postData) {
        try {
            $appointmentId = (int)$postData['appointment_id'];
            $newStatus = $postData['appointment_status'];
            $mechanicId = !empty($postData['mechanic_id']) ? (int)$postData['mechanic_id'] : null;
            
            $appointmentModel = new Appointment();
            
            // Enforce status transitions and timestamps via model method
            if ($appointmentModel->updateAppointmentStatus($appointmentId, $newStatus, $mechanicId)) {
                return [
                    'success' => true,
                    'message' => t('appointment_updated_successfully')
                ];
            } else {
                throw new Exception('Αποτυχία ενημέρωσης ραντεβού');
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process appointment cancellation
     */
    public function processAppointmentCancellation($appointmentId, $userRole, $userId = null) {
        try {
            $appointmentModel = new Appointment();
            
            // Role-based cancellation logic
            switch ($userRole) {
                case 'customer':
                    // For customers, verify they own the appointment before cancelling
                    $stmt = $this->db->prepare("
                        SELECT a.id FROM appointments a 
                        JOIN customers c ON a.customer_id = c.id 
                        WHERE a.id = ? AND c.user_id = ? AND a.status NOT IN ('ΠΕΡΑΙΩΜΕΝΟ', 'ΑΚΥΡΩΜΕΝΟ')
                    ");
                    $stmt->execute([$appointmentId, $userId]);
                    if (!$stmt->fetch()) {
                        throw new Exception('Δεν μπορείτε να ακυρώσετε αυτό το ραντεβού');
                    }
                    
                    if ($appointmentModel->cancelAppointment($appointmentId)) {
                        return [
                            'success' => true,
                            'message' => t('appointment_cancelled_successfully')
                        ];
                    }
                    break;
                    
                case 'secretary':
                    if ($appointmentModel->cancelAppointment($appointmentId)) {
                        return [
                            'success' => true,
                            'message' => t('appointment_cancelled_successfully')
                        ];
                    }
                    break;
                    
                default:
                    throw new Exception('Δεν έχετε δικαίωμα ακύρωσης ραντεβού');
            }
            
            throw new Exception('Δεν είναι δυνατή η ακύρωση αυτού του ραντεβού');
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process vehicle deletion
     */
    public function processVehicleDeletion($vehicleId, $userRole, $customerId = null) {
        try {
            $carModel = new Car();
            
            if ($userRole === 'customer') {
                $result = $carModel->deleteVehicle($vehicleId, $customerId);
            } elseif ($userRole === 'secretary') {
                $result = $carModel->deleteVehicleAdmin($vehicleId);
            } else {
                throw new Exception('Δεν έχετε δικαίωμα διαγραφής οχημάτων');
            }
            
            if ($result['success']) {
                // Build detailed success message
                $messageParts = [t('vehicle_deleted_successfully')];
                
                if ($result['deleted_appointments'] > 0) {
                    $messageParts[] = sprintf(t('deleted_future_appointments'), $result['deleted_appointments']);
                }
                
                if (isset($result['preserved_appointments']) && $result['preserved_appointments'] > 0) {
                    $messageParts[] = sprintf(t('preserved_completed_appointments'), $result['preserved_appointments']);
                }
                
                if (isset($result['cancelled_in_progress']) && $result['cancelled_in_progress'] > 0) {
                    $messageParts[] = sprintf(t('cancelled_in_progress_appointments'), $result['cancelled_in_progress']);
                }
                
                return [
                    'success' => true,
                    'message' => implode(' ', $messageParts)
                ];
            } else {
                throw new Exception($result['message'] ?? 'Αποτυχία διαγραφής οχήματος');
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process user management operations (for secretary)
     */
    public function processUserManagement($action, $postData) {
        try {
            switch ($action) {
                case 'create_user':
                    return $this->createUser($postData);
                case 'update_user':
                    return $this->updateUser($postData);
                case 'delete_user':
                    return $this->deleteUser($postData);
                case 'toggle_user_status':
                    return $this->toggleUserStatus($postData);
                default:
                    throw new Exception('Μη έγκυρη ενέργεια');
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    
    public function processUserCreation($postData) {
        return $this->createUser($postData);
    }
    
    
    public function processUserUpdate($postData) {
        return $this->updateUser($postData);
    }
    
    
    public function processUserDeletion($postData) {
        return $this->deleteUser($postData);
    }
    
    
    public function processUserRegistration($postData) {
        try {
            // Sanitize input
            $data = ValidationService::sanitizeInput($postData);
            
            // Validate registration data
            $errors = ValidationService::validateRegistrationData($data);
            if (ValidationService::hasErrors($errors)) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $errors
                ];
            }
            
            // Check for existing username/email
            $userModel = new User();
            
            if ($userModel->usernameExists($data['username'])) {
                return [
                    'success' => false,
                    'message' => 'Username already exists',
                    'errors' => ['username' => 'Το όνομα χρήστη υπάρχει ήδη']
                ];
            }
            
            if ($userModel->emailExists($data['email'])) {
                return [
                    'success' => false,
                    'message' => 'Email already exists',
                    'errors' => ['email' => 'Το email υπάρχει ήδη']
                ];
            }
            
            // Prepare user data for creation based on role
            $userData = [
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => $data['password'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'id_number' => $data['id_number'], 
                'role' => $data['role'] ?? 'customer', 
            ];
            
            // Add role-specific data
            if ($userData['role'] === 'customer') {
                // Customer requires AFM and address
                if (!empty($data['afm'])) {
                    $userData['afm'] = $data['afm'];
                }
                if (!empty($data['address'])) {
                    $userData['address'] = $data['address'];
                }
            } elseif ($userData['role'] === 'mechanic') {
                // Mechanic requires specialty
                if (!empty($data['specialty'])) {
                    $userData['specialty'] = $data['specialty'];
                }
            }
            
            // Create user account
            $result = $userModel->createUserWithRole($userData);
            
            if ($result['success']) {
                // Customize message based on role
                $message = 'Η εγγραφή ολοκληρώθηκε επιτυχώς! ';
                if ($userData['role'] === 'customer') {
                    $message .= 'Ο λογαριασμός πελάτη σας θα ενεργοποιηθεί σύντομα από τη γραμματεία.';
                } elseif ($userData['role'] === 'mechanic') {
                    $message .= 'Ο λογαριασμός μηχανικού σας θα αξιολογηθεί και ενεργοποιηθεί από τη γραμματεία.';
                } else {
                    $message .= 'Παρακαλώ περιμένετε την ενεργοποίηση του λογαριασμού σας.';
                }
                
                return [
                    'success' => true,
                    'message' => $message,
                    'data' => [
                        'user_id' => $result['user_id'],
                        'role' => $userData['role'],
                        'redirect' => 'login.php?registered=1'
                    ]
                ];
            } else {
                $response = [
                    'success' => false,
                    'message' => 'Αποτυχία εγγραφής: ' . ($result['message'] ?? 'Άγνωστο σφάλμα')
                ];
                require_once __DIR__ . '/../config/AppConfig.php';
                if (\AppConfig::isDebugMode()) {
                    $response['debug_info'] = [
                        'result' => $result,
                        'user_data' => array_diff_key($userData, ['password' => '']) 
                    ];
                }
                return $response;
            }
            
        } catch (Exception $e) {
            error_log("Registration processing error: " . $e->getMessage());
            error_log("Registration processing stack trace: " . $e->getTraceAsString());
            $resp = [
                'success' => false,
                'message' => 'Σφάλμα κατά την επεξεργασία εγγραφής: ' . $e->getMessage()
            ];
            require_once __DIR__ . '/../config/AppConfig.php';
            if (\AppConfig::isDebugMode()) {
                $resp['debug_info'] = [
                    'exception_type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ];
            }
            return $resp;
        }
    }
    
    /**
     * Create new user (secretary function)
     */
    private function createUser($postData) {
        // Sanitize input
        $data = ValidationService::sanitizeInput($postData);
        
        // Validate data
        $errors = ValidationService::validateUserData($data, false);
        if (ValidationService::hasErrors($errors)) {
            return [
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $errors
            ];
        }
        
        // Check for duplicates and return field-specific errors
        $duplicateErrors = [];
        $stmt = $this->db->prepare("SELECT username, email, id_number FROM users WHERE username = ? OR email = ? OR id_number = ? LIMIT 1");
        $stmt->execute([$data['username'], $data['email'], $data['id_number']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            if (!empty($existing['username']) && $existing['username'] === $data['username']) {
                $duplicateErrors['username'] = 'Το όνομα χρήστη υπάρχει ήδη';
            }
            if (!empty($existing['email']) && $existing['email'] === $data['email']) {
                $duplicateErrors['email'] = 'Το email υπάρχει ήδη';
            }
            if (!empty($existing['id_number']) && $existing['id_number'] === $data['id_number']) {
                $duplicateErrors['id_number'] = 'Ο αριθμός ταυτότητας (ΑΤ) υπάρχει ήδη';
            }
        }
        if (!empty($duplicateErrors)) {
            return [
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $duplicateErrors
            ];
        }
        
        try {
            $this->db->beginTransaction();
            
            // Create user
                $stmt = $this->db->prepare("
                    INSERT INTO users (username, email, password, first_name, last_name, id_number, role, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'inactive')
                ");
            $result = $stmt->execute([
                $data['username'],
                $data['email'],
                password_hash($data['password'], PASSWORD_DEFAULT),
                $data['first_name'],
                $data['last_name'],
                    $data['id_number'], 
                $data['role']
            ]);
            
            if ($result) {
                $userId = $this->db->lastInsertId();
                
                // Create role-specific record
                if ($data['role'] === 'customer') {
                    $stmt = $this->db->prepare("INSERT INTO customers (user_id, afm, address) VALUES (?, ?, ?)");
                    $stmt->execute([$userId, $data['afm'] ?? '', $data['address'] ?? '']);
                } elseif ($data['role'] === 'mechanic') {
                    $stmt = $this->db->prepare("INSERT INTO mechanics (user_id, specialty) VALUES (?, ?)");
                    $stmt->execute([$userId, $data['specialty'] ?? '']);
                }
                
                $this->db->commit();
                return [
                    'success' => true,
                    'message' => 'Ο χρήστης δημιουργήθηκε επιτυχώς'
                ];
            } else {
                $this->db->rollback();
                throw new Exception('Αποτυχία δημιουργίας χρήστη');
            }
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update existing user (secretary function)
     */
    private function updateUser($postData) {
        $userId = (int)$postData['user_id'];
        
        // Sanitize input
        $data = ValidationService::sanitizeInput($postData);
        
        // Validate data
        $errors = ValidationService::validateUserData($data, true);
        if (ValidationService::hasErrors($errors)) {
            return [
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $errors
            ];
        }
        
        try {
            $this->db->beginTransaction();
            // Uniqueness checks for username/email if provided
            if (!empty($data['username'])) {
                $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1");
                $stmt->execute([$data['username'], $userId]);
                if ($stmt->fetch()) {
                    $this->db->rollBack();
                    return [
                        'success' => false,
                        'message' => 'Validation errors',
                        'errors' => ['username' => 'Το όνομα χρήστη υπάρχει ήδη']
                    ];
                }
            }
            if (!empty($data['email'])) {
                $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
                $stmt->execute([$data['email'], $userId]);
                if ($stmt->fetch()) {
                    $this->db->rollBack();
                    return [
                        'success' => false,
                        'message' => 'Validation errors',
                        'errors' => ['email' => 'Το email χρησιμοποιείται ήδη από άλλον χρήστη']
                    ];
                }
            }

            // Build dynamic update for base user fields
            $fields = ['email' => 'email', 'first_name' => 'first_name', 'last_name' => 'last_name'];
            $params = [];
            $sets = [];
            foreach ($fields as $key => $column) {
                if (isset($data[$key])) {
                    $sets[] = "$column = ?";
                    $params[] = $data[$key];
                }
            }
            if (!empty($data['username'])) {
                $sets[] = 'username = ?';
                $params[] = $data['username'];
            }
            if (empty($sets)) {
                $sets[] = 'updated_at = NOW()';
            } else {
                $sets[] = 'updated_at = NOW()';
            }
            $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?';
            $params[] = $userId;
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);

            // Update password if provided
            if (!empty($data['password'])) {
                $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([password_hash($data['password'], PASSWORD_DEFAULT), $userId]);
            }

            // Role-specific updates if role or role fields provided
            $role = $data['role'] ?? ($data['user_role'] ?? null);
            if ($role === 'customer') {
                if (isset($data['afm']) || isset($data['address'])) {
                    $stmt = $this->db->prepare("UPDATE customers SET afm = COALESCE(?, afm), address = COALESCE(?, address) WHERE user_id = ?");
                    $stmt->execute([
                        $data['afm'] ?? null,
                        $data['address'] ?? null,
                        $userId
                    ]);
                }
            } elseif ($role === 'mechanic') {
                if (isset($data['specialty'])) {
                    $stmt = $this->db->prepare("UPDATE mechanics SET specialty = COALESCE(?, specialty) WHERE user_id = ?");
                    $stmt->execute([
                        $data['specialty'] ?? null,
                        $userId
                    ]);
                }
            }

            if ($result) {
                $this->db->commit();
                return [
                    'success' => true,
                    'message' => 'Ο χρήστης ενημερώθηκε επιτυχώς'
                ];
            }

            $this->db->rollBack();
            throw new Exception('Αποτυχία ενημέρωσης χρήστη');
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete user (secretary function)
     */
    private function deleteUser($postData) {
        $userId = (int)$postData['user_id'];
        
        try {
            $userModel = new User();
            $result = $userModel->deleteUser($userId);
            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => 'Ο χρήστης διαγράφηκε επιτυχώς'
                ];
            }
            return [
                'success' => false,
                'message' => $result['message'] ?? 'Αποτυχία διαγραφής χρήστη'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Toggle user status (secretary function)
     */
    private function toggleUserStatus($postData) {
        $userId = (int)$postData['user_id'];
        $newStatus = $postData['status'];
        
        try {
            $stmt = $this->db->prepare("UPDATE users SET status = ? WHERE id = ?");
            $result = $stmt->execute([$newStatus, $userId]);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Η κατάσταση του χρήστη ενημερώθηκε επιτυχώς'
                ];
            } else {
                throw new Exception('Αποτυχία ενημέρωσης κατάστασης χρήστη');
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
?>
