<?php

require_once __DIR__ . '/../config/AppConfig.php';

class ValidationService {
    
    /**
     * Validate user profile data (common across all user types)
     */
    public static function validateProfileData($data, $userRole = null) {
        $errors = [];
        
        // Basic required fields
        $required = ['first_name', 'last_name', 'email'];
        foreach ($required as $field) {
            if (empty(trim($data[$field] ?? ''))) {
                $errors[$field] = "Το πεδίο {$field} είναι υποχρεωτικό";
            }
        }
        
        // Email validation
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Μη έγκυρη διεύθυνση email';
        }
        
        // Role-specific validation
        if ($userRole === 'customer') {
            if (empty(trim($data['afm'] ?? ''))) {
                $errors['afm'] = 'Το ΑΦΜ είναι υποχρεωτικό για πελάτες';
            }
            if (empty(trim($data['address'] ?? ''))) {
                $errors['address'] = 'Η διεύθυνση είναι υποχρεωτική για πελάτες';
            }
            
            // AFM validation (simple format check)
            if (!empty($data['afm']) && !preg_match('/^\d{9}$/', $data['afm'])) {
                $errors['afm'] = 'Το ΑΦΜ πρέπει να αποτελείται από 9 ψηφία';
            }
        }
        
        if ($userRole === 'mechanic') {
            if (empty(trim($data['specialty'] ?? ''))) {
                $errors['specialty'] = 'Η ειδικότητα είναι υποχρεωτική για μηχανικούς';
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate vehicle data
     */
    public static function validateVehicleData($data) {
        $errors = [];
        
        $required = ['serial_number', 'model', 'brand', 'vehicle_type', 'engine_type', 
                    'door_count', 'wheel_count', 'production_date', 'acquisition_year'];
        
        foreach ($required as $field) {
            if (empty(trim($data[$field] ?? ''))) {
                $errors[$field] = "Το πεδίο {$field} είναι υποχρεωτικό";
            }
        }
        
        // Serial number format validation
        if (!empty($data['serial_number']) && strlen($data['serial_number']) < 5) {
            $errors['serial_number'] = 'Ο αριθμός σάσι πρέπει να έχει τουλάχιστον 5 χαρακτήρες';
        }
        
        // Numeric validation
        if (!empty($data['door_count']) && (!is_numeric($data['door_count']) || $data['door_count'] < 1)) {
            $errors['door_count'] = 'Ο αριθμός πορτών πρέπει να είναι θετικός αριθμός';
        }
        
        if (!empty($data['wheel_count']) && (!is_numeric($data['wheel_count']) || $data['wheel_count'] < 3)) {
            $errors['wheel_count'] = 'Ο αριθμός τροχών πρέπει να είναι τουλάχιστον 3';
        }
        
        // Year validation
        $currentYear = date('Y');
        if (!empty($data['acquisition_year'])) {
            $year = (int)$data['acquisition_year'];
            if ($year < 1900 || $year > $currentYear) {
                $errors['acquisition_year'] = "Το έτος κτήσης πρέπει να είναι μεταξύ 1900 και {$currentYear}";
            }
        }
        
        // Production date validation
        if (!empty($data['production_date'])) {
            $productionDate = DateTime::createFromFormat('Y-m-d', $data['production_date']);
            if (!$productionDate || $productionDate > new DateTime()) {
                $errors['production_date'] = 'Μη έγκυρη ημερομηνία παραγωγής';
            }
        }

        // Enum validation for vehicle_type and engine_type using AppConfig lists
        try {
            $validVehicleTypes = array_keys(AppConfig::VEHICLE_TYPES);
            $validEngineTypes = array_keys(AppConfig::ENGINE_TYPES);
            if (!empty($data['vehicle_type']) && !in_array($data['vehicle_type'], $validVehicleTypes, true)) {
                $errors['vehicle_type'] = 'Μη έγκυρος τύπος οχήματος';
            }
            if (!empty($data['engine_type']) && !in_array($data['engine_type'], $validEngineTypes, true)) {
                $errors['engine_type'] = 'Μη έγκυρος τύπος κινητήρα';
            }
        } catch (Exception $e) {
            error_log('Validation error: ' . $e->getMessage());
        }
        
        return $errors;
    }
    
    /**
     * Validate appointment data
     */
    public static function validateAppointmentData($data) {
        $errors = [];
        
        $required = ['customer_id', 'vehicle_id', 'appointment_date', 'appointment_time', 'reason'];
        foreach ($required as $field) {
            if (empty(trim($data[$field] ?? ''))) {
                $errors[$field] = "Το πεδίο {$field} είναι υποχρεωτικό";
            }
        }
        
        // Date validation
        if (!empty($data['appointment_date'])) {
            $appointmentDate = DateTime::createFromFormat('Y-m-d', $data['appointment_date']);
            $today = new DateTime('today');
            
            if (!$appointmentDate) {
                $errors['appointment_date'] = 'Μη έγκυρη ημερομηνία';
            } elseif ($appointmentDate < $today) {
                $errors['appointment_date'] = 'Δεν μπορείτε να γυρίσετε στο παρελθόν';
            }
        }
        
        // Time validation (workshop hours: 08:00-16:00)
        if (!empty($data['appointment_time'])) {
            $time = DateTime::createFromFormat('H:i', $data['appointment_time']);
            if (!$time) {
                $errors['appointment_time'] = 'Μη έγκυρη ώρα';
            } else {
                $hour = (int)$time->format('H');
                if ($hour < 8 || $hour >= 16) {
                    $errors['appointment_time'] = 'Οι ώρες λειτουργίας είναι 08:00-16:00';
                }
            }
        }
        
        // Business rule: problem description required for repairs
        if (!empty($data['reason']) && $data['reason'] === 'επιδιόρθωση' && empty(trim($data['problem_description'] ?? ''))) {
            $errors['problem_description'] = 'Η περιγραφή προβλήματος είναι υποχρεωτική για επιδιορθώσεις';
        }
        
        return $errors;
    }
    /**
     * Validate task data
     */
    public static function validateTaskData($data, $isUpdate = false) {
        $errors = [];
        
       
        // For updates, mechanic_id is determined server-side for security, not required in form data
        $required = ['appointment_id', 'description', 'materials', 'completion_time'];
        if (!$isUpdate) {
            $required[] = 'mechanic_id'; // Only required for creation
        }
        
        foreach ($required as $field) {
            if (empty(trim($data[$field] ?? ''))) {
                $errors[$field] = "Το πεδίο {$field} είναι υποχρεωτικό";
            }
        }
        
        // Completion time validation (in minutes) - must be positive number
        if (!empty($data['completion_time']) && (!is_numeric($data['completion_time']) || $data['completion_time'] <= 0)) {
            $errors['completion_time'] = 'Ο χρόνος ολοκλήρωσης πρέπει να είναι θετικός αριθμός (λεπτά)';
        }
        
        // Materials validation - must be a non-empty string
        if (!empty($data['materials']) && !is_string($data['materials'])) {
            $errors['materials'] = 'Τα υλικά πρέπει να είναι κείμενο';
        }
        
        return $errors;
    }
    
    /**
     * Validate user creation/update data (for secretary use)
     */
    public static function validateUserData($data, $isUpdate = false) {
        $errors = [];
        
        $required = ['username', 'email', 'first_name', 'last_name'];
        if (!$isUpdate) {
            // On creation, role is required along with password and id_number
            $required[] = 'role';
            $required[] = 'password';
            $required[] = 'id_number';
        }
        
        foreach ($required as $field) {
            if (empty(trim($data[$field] ?? ''))) {
                $errors[$field] = "Το πεδίο {$field} είναι υποχρεωτικό";
            }
        }
        
        // Username validation
        if (!empty($data['username']) && !preg_match('/^[a-zA-Z0-9_]{3,20}$/', $data['username'])) {
            $errors['username'] = 'Το όνομα χρήστη πρέπει να είναι 3-20 χαρακτήρες (γράμματα, αριθμοί, _)';
        }
        
        // Email validation
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Μη έγκυρη διεύθυνση email';
        }
        
        // Password validation (only for new users or password changes)
        if (!empty($data['password']) && strlen($data['password']) < 6) {
            $errors['password'] = 'Ο κωδικός πρόσβασης πρέπει να έχει τουλάχιστον 6 χαρακτήρες';
        }

        // ID number validation for creations or when provided
        if ((!$isUpdate && !empty($data['id_number'])) || (!empty($data['id_number']))) {
            $idNumber = trim($data['id_number']);
            if (mb_strlen($idNumber, 'UTF-8') !== 8) {
                $errors['id_number'] = 'Ο αριθμός ταυτότητας πρέπει να είναι 8 χαρακτήρες (π.χ. ΑΤ123456)';
            } elseif (!ctype_digit(mb_substr($idNumber, -6, 6, 'UTF-8'))) {
                $errors['id_number'] = 'Ο αριθμός ταυτότητας πρέπει να τελειώνει με 6 αριθμούς (π.χ. ΑΤ123456)';
            }
        }
        
        // Role validation
        $validRoles = ['customer', 'mechanic', 'secretary'];
        if (!empty($data['role']) && !in_array($data['role'], $validRoles)) {
            $errors['role'] = 'Μη έγκυρος ρόλος χρήστη';
        }

        // Role-specific requirements (for creation or when role provided)
        if (!empty($data['role'])) {
            if ($data['role'] === 'customer') {
                if (empty(trim($data['afm'] ?? ''))) {
                    $errors['afm'] = 'Το ΑΦΜ είναι υποχρεωτικό για πελάτες';
                } elseif (!preg_match('/^\d{9}$/', $data['afm'])) {
                    $errors['afm'] = 'Το ΑΦΜ πρέπει να αποτελείται από 9 ψηφία';
                }
                if (empty(trim($data['address'] ?? ''))) {
                    $errors['address'] = 'Η διεύθυνση είναι υποχρεωτική για πελάτες';
                }
            } elseif ($data['role'] === 'mechanic') {
                if (empty(trim($data['specialty'] ?? ''))) {
                    $errors['specialty'] = 'Η ειδικότητα είναι υποχρεωτική για μηχανικούς';
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate registration data (public registration)
     */
    public static function validateRegistrationData($data) {
        $errors = [];
        
        // Required fields for registration
        $required = ['username', 'email', 'password', 'first_name', 'last_name', 'id_number', 'role'];
        foreach ($required as $field) {
            if (empty(trim($data[$field] ?? ''))) {
                $errors[$field] = "Το πεδίο {$field} είναι υποχρεωτικό";
            }
        }
        
        // Username validation
        if (!empty($data['username'])) {
            if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $data['username'])) {
                $errors['username'] = 'Το όνομα χρήστη πρέπει να είναι 3-20 χαρακτήρες (γράμματα, αριθμοί, _)';
            }
        }
        
        // Email validation
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Μη έγκυρη διεύθυνση email';
        }
        
        // Password validation
        if (!empty($data['password'])) {
            if (strlen($data['password']) < 6) {
                $errors['password'] = 'Ο κωδικός πρόσβασης πρέπει να έχει τουλάχιστον 6 χαρακτήρες';
            }
            
           
        }
        
        // Name validation
        if (!empty($data['first_name']) && strlen(trim($data['first_name'])) < 2) {
            $errors['first_name'] = 'Το όνομα πρέπει να έχει τουλάχιστον 2 χαρακτήρες';
        }
        
        if (!empty($data['last_name']) && strlen(trim($data['last_name'])) < 2) {
            $errors['last_name'] = 'Το επώνυμο πρέπει να έχει τουλάχιστον 2 χαρακτήρες';
        }
        
        // ID Number validation (simplified approach)
        if (!empty($data['id_number'])) {
            $idNumber = trim($data['id_number']);
            
            // Check length (should be 8 characters)
            if (mb_strlen($idNumber, 'UTF-8') !== 8) {
                $errors['id_number'] = 'Ο αριθμός ταυτότητας πρέπει να είναι 8 χαρακτήρες (π.χ. ΑΤ123456)';
            } 
            // Check if last 6 characters are digits
            else if (!ctype_digit(mb_substr($idNumber, -6, 6, 'UTF-8'))) {
                $errors['id_number'] = 'Ο αριθμός ταυτότητας πρέπει να τελειώνει με 6 αριθμούς (π.χ. ΑΤ123456)';
            }
        }
        
        // Role validation - only customer and mechanic allowed for public registration
        if (!empty($data['role'])) {
            $allowedRoles = ['customer', 'mechanic'];
            if (!in_array($data['role'], $allowedRoles)) {
                $errors['role'] = 'Μη έγκυρος ρόλος χρήστη. Επιτρέπονται μόνο: πελάτης, μηχανικός';
            }
            
            // Role-specific validation
            if ($data['role'] === 'customer') {
                // AFM is required for customers
                if (empty(trim($data['afm'] ?? ''))) {
                    $errors['afm'] = 'Το ΑΦΜ είναι υποχρεωτικό για πελάτες';
                } elseif (!preg_match('/^\d{9}$/', $data['afm'])) {
                    $errors['afm'] = 'Το ΑΦΜ πρέπει να αποτελείται από 9 ψηφία';
                }
                
                // Address is required for customers
                if (empty(trim($data['address'] ?? ''))) {
                    $errors['address'] = 'Η διεύθυνση είναι υποχρεωτική για πελάτες';
                }
            } elseif ($data['role'] === 'mechanic') {
                // Specialty is required for mechanics
                if (empty(trim($data['specialty'] ?? ''))) {
                    $errors['specialty'] = 'Η ειδικότητα είναι υποχρεωτική για μηχανικούς';
                }
            }
        }
        
        return $errors;
    }   
    
    
    public static function sanitizeInput($data) {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = trim($value);
            } elseif (is_array($value)) {
                $sanitized[$key] = self::sanitizeInput($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Check if validation has errors
     */
    public static function hasErrors($errors) {
        return !empty($errors);
    }
    
    
}
?>
