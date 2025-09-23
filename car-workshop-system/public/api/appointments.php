<?php
/**
 * Appointments API Endpoint
 * Handles AJAX requests for appointment management
 */

require_once '../../includes/auth.php';
require_once '../../includes/language.php';
require_once __DIR__ . '/common.php';
require_once '../../src/models/Appointment.php';

require_once __DIR__ . '/../../src/utils/Utils.php';

require_once '../../src/services/ValidationService.php';
require_once '../../src/config/debug.php';

// Log API request
logApiRequest('appointments', $_SERVER['REQUEST_METHOD'], $_POST);

// Ensure user is authenticated
$auth->requireLogin();
$currentUser = $auth->getCurrentUser();

// Debug user information
debug_log('Appointments API - User Info', [
    'user_id' => $currentUser['id'],
    'role' => $currentUser['role'],
    'method' => $_SERVER['REQUEST_METHOD']
]);

// Only allow specific HTTP methods
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST', 'PUT', 'DELETE'])) {
    sendError('Method not allowed', 405);
}

// CSRF protection for state-changing requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    require_once '../../src/config/AppConfig.php';
    
    // Accept CSRF token from header first, then fallback to body
    $headers = getallheaders() ?: [];
    $csrfHeader = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    $data = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : getJsonInput();
    $csrfToken = $csrfHeader ?: ($data['csrf_token'] ?? null);
    
    if (empty($csrfToken) || !\SecurityConfig::validateCSRFToken($csrfToken)) {
        sendError('Invalid or missing CSRF token', 403);
    }
}

// Route to appropriate handler based on HTTP method
switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        handleGet();
        break;
    case 'POST':
        handlePost();
        break;
    case 'PUT':
        handlePut();
        break;
    case 'DELETE':
        handleDelete();
        break;
}

/**
 * 
 *
 * Returns appointment data for customers, mechanics and
 * secretaries (or available time slots when requested). It reads query
 * parameters like filters, pagination and special flags, enforces access
 * control, and calls the Appointment model for the actual data. The
 * response is a JSON envelope consumed by dashboard pages
 */
function handleGet() {
    global $currentUser;
    
    try {
        // Handle customer appointments request
        if (isset($_GET['customer_appointments'])) {
            $customerId = getCustomerIdFromUserId((int)$currentUser['id']);
            if (!$customerId) {
                sendError('Customer not found', 404);
            }

            $appointmentModel = new Appointment();

            // Handle search / filter parameters
            $searchTerm = trim($_GET['search'] ?? '');
            $statusFilter = trim($_GET['status'] ?? '');
            $dateFrom = trim($_GET['date_from'] ?? '');
            $dateTo = trim($_GET['date_to'] ?? '');
            
            // Pagination parameters
            $page = max(1, intval($_GET['page'] ?? 1));
            $perPage = min(10, max(1, intval($_GET['per_page'] ?? 10))); // Max 10 items per page
            $pagination = [
                'page' => $page,
                'per_page' => $perPage,
                'offset' => ($page - 1) * $perPage
            ];

            // Build conditions and always scope to the customer
            $conditions = ['customer_id' => $customerId];
            if ($statusFilter !== '') $conditions['status'] = $statusFilter;
            if ($dateFrom !== '') $conditions['date_from'] = $dateFrom;
            if ($dateTo !== '') $conditions['date_to'] = $dateTo;

            // If no explicit filters provided, default to today's pending appointments
            if ($searchTerm === '' && $statusFilter === '' && $dateFrom === '' && $dateTo === '') {
                $today = date('Y-m-d');
                $conditions['date_from'] = $today;
                $conditions['date_to'] = $today;
                $conditions['status'] = 'ΔΗΜΙΟΥΡΓΗΜΕΝΟ';
            }

            // Use the DB search so filtering/pagination happen in SQL
            $appointments = $appointmentModel->searchAppointments($searchTerm, $conditions, $pagination);
            $totalCount = $appointmentModel->getSearchCount($searchTerm, $conditions);
            
            $meta = [
                'total' => $totalCount,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($totalCount / $perPage),
                'has_more' => $page < ceil($totalCount / $perPage)
            ];

            sendSuccess('Appointments retrieved successfully', $appointments, $meta);
        }
        
        // Handle mechanic appointments request
        if (isset($_GET['mechanic_appointments'])) {
            $mechanicId = getMechanicIdFromUserId((int)$currentUser['id']);
            if (!$mechanicId) {
                error_log("Appointments API: No mechanic record found for user ID: " . $currentUser['id'] . ", role: " . $currentUser['role']);
                // Instead of error, return empty array
                sendSuccess('No mechanic record found - returning empty appointments list', []);
                return;
            }
            
            $appointmentModel = new Appointment();
            
            // Handle search / filter parameters for mechanic appointments
            $searchTerm = trim($_GET['search'] ?? '');
            $statusFilter = trim($_GET['status'] ?? '');
            $dateFrom = trim($_GET['date_from'] ?? '');
            $dateTo = trim($_GET['date_to'] ?? '');
            
            // Pagination parameters
            $page = max(1, intval($_GET['page'] ?? 1));
            $perPage = min(10, max(1, intval($_GET['per_page'] ?? 10))); // Max 10 items per page
            $pagination = [
                'page' => $page,
                'per_page' => $perPage,
                'offset' => ($page - 1) * $perPage
            ];
            
            // Build conditions with mechanic restriction
            $conditions = ['mechanic_id' => $mechanicId];
            if ($statusFilter !== '') $conditions['status'] = $statusFilter;
            if ($dateFrom !== '') $conditions['date_from'] = $dateFrom;
            if ($dateTo !== '') $conditions['date_to'] = $dateTo;
            
            // If no explicit filters provided, default to today's appointments
            if ($statusFilter === '' && $dateFrom === '' && $dateTo === '') {
                $today = date('Y-m-d');
                $conditions['date_from'] = $today;
                $conditions['date_to'] = $today;
            }
            
            // Use the DB search for consistent filtering
            $appointments = $appointmentModel->searchAppointments($searchTerm, $conditions, $pagination);
            $totalCount = $appointmentModel->getSearchCount($searchTerm, $conditions);
            
            $meta = [
                'total' => $totalCount,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($totalCount / $perPage),
                'has_more' => $page < ceil($totalCount / $perPage)
            ];
            
            sendSuccess('Mechanic appointments retrieved successfully', $appointments, $meta);
        }
        
        // Handle available slots request
        if (isset($_GET['action']) && $_GET['action'] === 'available_slots') {
            $appointmentModel = new Appointment();
            handleAvailableSlots($appointmentModel);
        }
        
        // Handle secretary appointments request with search/filter support
        if (isset($_GET['secretary_appointments']) || ($currentUser['role'] === 'secretary' && !isset($_GET['id']))) {
            $appointmentModel = new Appointment();
            
            // Handle search / filter parameters (same as customer)
            $searchTerm = trim($_GET['search'] ?? '');
            $statusFilter = trim($_GET['status'] ?? '');
            $dateFrom = trim($_GET['date_from'] ?? '');
            $dateTo = trim($_GET['date_to'] ?? '');
            
            // Pagination parameters
            $page = max(1, intval($_GET['page'] ?? 1));
            $perPage = min(10, max(1, intval($_GET['per_page'] ?? 10))); // Max 10 items per page
            $pagination = [
                'page' => $page,
                'per_page' => $perPage,
                'offset' => ($page - 1) * $perPage
            ];
            
            // Build conditions (no customer_id restriction for secretary)
            $conditions = [];
            if ($statusFilter !== '') $conditions['status'] = $statusFilter;
            if ($dateFrom !== '') $conditions['date_from'] = $dateFrom;
            if ($dateTo !== '') $conditions['date_to'] = $dateTo;
            
            // If no explicit filters provided, default to today's pending appointments
            if ($searchTerm === '' && $statusFilter === '' && $dateFrom === '' && $dateTo === '') {
                $today = date('Y-m-d');
                $conditions['date_from'] = $today;
                $conditions['date_to'] = $today;
                $conditions['status'] = 'ΔΗΜΙΟΥΡΓΗΜΕΝΟ';
            }
            
            // Use the DB search for consistent filtering
            $appointments = $appointmentModel->searchAppointments($searchTerm, $conditions, $pagination);
            $totalCount = $appointmentModel->getSearchCount($searchTerm, $conditions);
            
            $meta = [
                'total' => $totalCount,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($totalCount / $perPage),
                'has_more' => $page < ceil($totalCount / $perPage)
            ];
            
            sendSuccess('Secretary appointments retrieved successfully', $appointments, $meta);
            return;
        }

        // Handle single appointment request
        if (isset($_GET['id'])) {
            $appointmentId = intval($_GET['id']);
            $appointmentModel = new Appointment();
            $appointment = $appointmentModel->getAppointmentById($appointmentId);
            
            if (!$appointment) {
                sendError('Appointment not found', 404);
            }
            
            // Check permissions
            if ($currentUser['role'] === 'customer') {
                $customerId = getCustomerIdFromUserId((int)$currentUser['id']);
                if ($appointment['customer_id'] != $customerId) {
                    sendError('Access denied', 403);
                }
            } elseif ($currentUser['role'] === 'mechanic') {
                $mechanicId = getMechanicIdFromUserId((int)$currentUser['id']);
                if ($appointment['mechanic_id'] != $mechanicId) {
                    sendError('Access denied', 403);
                }
            }
            
            sendSuccess('Appointment retrieved successfully', $appointment);
            return;
        }
        
        // Fallback for any unhandled requests
        sendError('Invalid request parameters', 400);
        
    } catch (Exception $e) {
        sendError('Error retrieving appointments: ' . $e->getMessage(), 500);
    }
}

/**
 *  POST  — create an appointment.
 *
 * Creates a new appointment record. 
 *   Validate required fields are present.
 *  Sanitize input and run validation via ValidationService.
 *  Enforce role rules: customers may only create appointments for
 *    themselves; secretaries may create on behalf of customers.
 *  Delegate to Appointment::createAppointment and return
 *   a JSON success payload with the new appointment id and assigned
 *    mechanic, or return an error if creation fails.
 *
 *

 */
function handlePost() {
    global $currentUser;
    
    try {
        
        $rawPost = $_POST;
        if (empty($rawPost)) {
            $jsonInput = getJsonInput();
            if ($jsonInput && is_array($jsonInput)) {
                $rawPost = $jsonInput;
            }
        }

        // Validate required fields for appointment creation
        $requiredFields = ['customer_id', 'vehicle_id', 'appointment_date', 'appointment_time', 'reason'];
        validateRequired($rawPost, $requiredFields);
        
        // Sanitize input
        $data = sanitizeInput($rawPost);

        // Additional validation using ValidationService for consistency
        $validationErrors = ValidationService::validateAppointmentData($data);
        if (ValidationService::hasErrors($validationErrors)) {
            sendError('Validation errors', 400, $validationErrors);
        }
        
        // Role-based access control
        if ($currentUser['role'] === 'customer') {
            // Ensure customer can only create appointments for themselves
            $customerId = getCustomerIdFromUserId((int)$currentUser['id']);
            if ((int)$data['customer_id'] !== $customerId) {
                sendError('You can only create appointments for yourself', 403);
            }
        } elseif ($currentUser['role'] !== 'secretary') {
            sendError('Only customers and secretaries can create appointments', 403);
        }
        
        // Create appointment
        $appointment = new Appointment();
        $result = $appointment->createAppointment($data);
        
        if ($result['success']) {
            sendSuccess('Appointment created successfully', [
                'appointment_id' => $result['appointment_id'],
                'assigned_mechanic' => $result['assigned_mechanic']
            ]);
        } else {
            sendError($result['message'], 400);
        }
        
    } catch (Exception $e) {
        error_log('Appointment creation error: ' . $e->getMessage());
        sendError('Failed to create appointment', 500);
    }
}

/**
 * PUT — update appointment.
 *
 * JSON payload has action parameter. "Enum" of
 *  - update_status: change the appointment status (secretaries can set
 *    any status; mechanics can advance status along allowed transitions)
 *  - update_datetime: reschedule the appointment's date and time
 *  - cancel: cancel the appointment
 */
function handlePut() {
    global $currentUser;
    
    debug_log('handlePut called', ['user' => $currentUser['role'], 'user_id' => $currentUser['id']]);
    
    try {
        // Get JSON input for PUT requests
        $input = getJsonInput();
        debug_log('PUT request input', $input);
        
        if (!$input) {
            debug_log('PUT request failed - no JSON input');
            sendError('Invalid JSON input', 400);
        }
        
        // Validate appointment ID
        if (!isset($input['appointment_id'])) {
            debug_log('PUT request failed - no appointment_id');
            sendError('Appointment ID is required', 400);
        }
        
        $appointmentId = (int)$input['appointment_id'];
        debug_log('Processing appointment update', ['appointment_id' => $appointmentId, 'action' => $input['action'] ?? 'unknown']);
        
        $appointment = new Appointment();
        
        // Check if appointment exists and user has permission
        $existingAppointment = $appointment->getAppointmentById($appointmentId);
        if (!$existingAppointment) {
            debug_log('PUT request failed - appointment not found', ['appointment_id' => $appointmentId]);
            sendError('Appointment not found', 404);
        }
        
        debug_log('Existing appointment details', $existingAppointment);
        
        // Role-based access control
        if ($currentUser['role'] === 'customer') {
            $customerId = getCustomerIdFromUserId((int)$currentUser['id']);
            debug_log('Customer access check', ['customer_id' => $customerId, 'appointment_customer_id' => $existingAppointment['customer_id']]);
            
            if ((int)$existingAppointment['customer_id'] !== $customerId) {
                debug_log('PUT request failed - customer access denied');
                sendError('You can only modify your own appointments', 403);
            }
            
            // Customers can only change date/time when status is ΔΗΜΙΟΥΡΓΗΜΕΝΟ
            if ($existingAppointment['status'] !== 'ΔΗΜΙΟΥΡΓΗΜΕΝΟ') {
                debug_log('PUT request failed - invalid status for customer', ['status' => $existingAppointment['status']]);
                sendError('You can only modify appointments with CREATED status', 403);
            }
        } elseif ($currentUser['role'] === 'mechanic') {
            $mechanicId = getMechanicIdFromUserId((int)$currentUser['id']);
            debug_log('Mechanic access check', ['mechanic_id' => $mechanicId, 'appointment_mechanic_id' => $existingAppointment['mechanic_id']]);
            
            if ((int)$existingAppointment['mechanic_id'] !== $mechanicId) {
                debug_log('PUT request failed - mechanic access denied');
                sendError('You can only modify your assigned appointments', 403);
            }
        } elseif ($currentUser['role'] !== 'secretary') {
            debug_log('PUT request failed - insufficient permissions', ['role' => $currentUser['role']]);
            sendError('Insufficient permissions', 403);
        }
        
        // Update appointment based on action
        $action = $input['action'] ?? 'update';
        debug_log('Processing action', ['action' => $action, 'input_keys' => array_keys($input)]);
        
        // Depending on action payload
        switch ($action) {
            case 'update_status':
                
                
                // Validate required fields for status update
                if (!isset($input['status']) || empty($input['status'])) {
                    sendError('Status is required for status update', 400);
                }
                
                $newStatus = $input['status'];
                
                // Role-based permission and transition validation
                if ($currentUser['role'] === 'secretary') {
                    // Secretaries can change to any valid status
                    if (!in_array($newStatus, ['ΔΗΜΙΟΥΡΓΗΜΕΝΟ', 'ΣΕ ΕΞΕΛΙΞΗ', 'ΠΕΡΑΙΩΜΕΝΟ', 'ΑΚΥΡΩΜΕΝΟ'])) {
                        sendError('Invalid status provided', 400);
                    }
                } elseif ($currentUser['role'] === 'mechanic') {
                    // Mechanics can only progress status forward, not cancel
                    $currentStatus = $existingAppointment['status'];
                    $validTransitions = [
                        'ΔΗΜΙΟΥΡΓΗΜΕΝΟ' => ['ΣΕ ΕΞΕΛΙΞΗ'],
                        'ΣΕ ΕΞΕΛΙΞΗ' => ['ΠΕΡΑΙΩΜΕΝΟ']
                    ];
                    
                    if (!isset($validTransitions[$currentStatus]) || !in_array($newStatus, $validTransitions[$currentStatus])) {
                        sendError("Invalid status transition from {$currentStatus} to {$newStatus} for mechanic", 400);
                    }
                } else {
                    sendError('Only secretaries and mechanics can update appointment status', 403);
                }
                
                try {
                    debug_log('Calling updateAppointmentStatus', ['appointment_id' => $appointmentId, 'status' => $newStatus]);
                    $result = $appointment->updateAppointmentStatus($appointmentId, $newStatus);
                    if ($result) {
                        debug_log('updateAppointmentStatus successful');
                        sendSuccess('Appointment status updated successfully');
                    } else {
                        debug_log('updateAppointmentStatus failed - returned false');
                        sendError('Failed to update appointment status - database operation failed', 500);
                    }
                } catch (Exception $e) {
                    debug_log('updateAppointmentStatus exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                    error_log('Update status error: ' . $e->getMessage() . ' for appointment ID: ' . $appointmentId);
                    sendError('Failed to update appointment status: ' . $e->getMessage(), 400);
                }
                break;
                
            case 'update_datetime':
                debug_log('Handling update_datetime action');
                // Validate required fields for datetime update
                $requiredFields = ['appointment_date', 'appointment_time'];
                foreach ($requiredFields as $field) {
                    if (!isset($input[$field]) || empty($input[$field])) {
                        debug_log('update_datetime validation failed', ['missing_field' => $field]);
                        sendError("Field '{$field}' is required for datetime update", 400);
                    }
                }
                
                debug_log('update_datetime input validation passed', [
                    'appointment_date' => $input['appointment_date'],
                    'appointment_time' => $input['appointment_time']
                ]);
                
                // Validate date format
                $dateObj = DateTime::createFromFormat('Y-m-d', $input['appointment_date']);
                if (!$dateObj || $dateObj->format('Y-m-d') !== $input['appointment_date']) {
                    debug_log('update_datetime date format invalid', ['date' => $input['appointment_date']]);
                    sendError('Invalid date format. Expected YYYY-MM-DD format', 400);
                }
                
                // Validate time format
                if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $input['appointment_time'])) {
                    debug_log('update_datetime time format invalid', ['time' => $input['appointment_time']]);
                    sendError('Invalid time format. Expected HH:MM format', 400);
                }
                
                // Check if date is not in the past
                $today = new DateTime('today');
                if ($dateObj < $today) {
                    debug_log('update_datetime date in past', ['date' => $input['appointment_date'], 'today' => $today->format('Y-m-d')]);
                    sendError('Cannot schedule appointment for a past date', 400);
                }
                
                try {
                    debug_log('Calling updateAppointmentDateTime', [
                        'appointment_id' => $appointmentId,
                        'appointment_date' => $input['appointment_date'],
                        'appointment_time' => $input['appointment_time']
                    ]);
                    $result = $appointment->updateAppointmentDateTime($appointmentId, $input['appointment_date'], $input['appointment_time']);
                    if ($result) {
                        debug_log('updateAppointmentDateTime successful');
                        sendSuccess('Appointment date and time updated successfully');
                    } else {
                        debug_log('updateAppointmentDateTime failed - returned false');
                        sendError('Failed to update appointment date/time - database operation failed', 500);
                    }
                } catch (Exception $e) {
                    debug_log('updateAppointmentDateTime exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                    error_log('Update datetime error: ' . $e->getMessage() . ' for appointment ID: ' . $appointmentId . ', date: ' . $input['appointment_date'] . ', time: ' . $input['appointment_time']);
                    sendError('Failed to update appointment date/time: ' . $e->getMessage(), 400);
                }
                break;
                
            case 'cancel':
                debug_log('Handling cancel action');
                try {
                    debug_log('Calling cancelAppointment', ['appointment_id' => $appointmentId]);
                    $result = $appointment->cancelAppointment($appointmentId);
                    if ($result) {
                        debug_log('cancelAppointment successful');
                        sendSuccess('Appointment cancelled successfully');
                    } else {
                        debug_log('cancelAppointment failed - returned false');
                        sendError('Failed to cancel appointment - database operation failed', 500);
                    }
                } catch (Exception $e) {
                    debug_log('cancelAppointment exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                    error_log('Cancel appointment error: ' . $e->getMessage() . ' for appointment ID: ' . $appointmentId);
                    sendError('Failed to cancel appointment: ' . $e->getMessage(), 400);
                }
                break;
                
            default:
                debug_log('Invalid action provided', ['action' => $action]);
                sendError('Invalid action. Supported actions: update_status, update_datetime, cancel', 400);
        }
        
    } catch (Exception $e) {
        debug_log('Unexpected exception in handlePut', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        error_log('Unexpected error in handlePut(): ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
        sendError('An unexpected error occurred while processing the request: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle DELETE requests - Cancel appointment
 */
function handleDelete() {
    global $currentUser;
    
    try {
        $appointmentId = (int)($_GET['id'] ?? 0);
        if (!$appointmentId) {
            sendError('Appointment ID is required', 400);
        }
        
        $appointment = new Appointment();
        $existingAppointment = $appointment->getAppointmentById($appointmentId);
        
        if (!$existingAppointment) {
            sendError('Appointment not found', 404);
        }
        
        // Check if appointment can be cancelled based on status
        if (!in_array($existingAppointment['status'], ['ΔΗΜΙΟΥΡΓΗΜΕΝΟ'])) {
            sendError('Cannot cancel appointment with status: ' . $existingAppointment['status'], 400);
        }
        
        // Role-based access control
        if ($currentUser['role'] === 'customer') {
            $customerId = getCustomerIdFromUserId((int)$currentUser['id']);
            if ((int)$existingAppointment['customer_id'] !== $customerId) {
                sendError('You can only cancel your own appointments', 403);
            }
        } elseif ($currentUser['role'] !== 'secretary') {
            sendError('Only customers and secretaries can cancel appointments', 403);
        }
        
        $result = $appointment->cancelAppointment($appointmentId);
        
        if ($result) {
            sendSuccess('Appointment cancelled successfully');
        } else {
            sendError('Failed to cancel appointment', 500);
        }
        
    } catch (Exception $e) {
        error_log('Appointment cancellation error: ' . $e->getMessage());
        sendError('Failed to cancel appointment', 500);
    }
}

/**
 * Handle fetching available time slots for a given date.
 * Call Appointment::getAvailableTimeSlots() to receive the slots
 */
function handleAvailableSlots($appointment) {
    global $currentUser;
    
    // Check if date is provided
    if (!isset($_GET['date']) || empty($_GET['date'])) {
        sendError('Date parameter is required', 400);
    }
    
    $date = $_GET['date'];
    
    // Validate date format and that it's today or future
    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
    $today = new DateTime('today');
    
    if (!$dateObj || $dateObj < $today) {
        sendError('Invalid date or date is in the past', 400);
    }
    
    try {
        $appointmentModel = new Appointment();
        $availableSlots = $appointmentModel->getAvailableTimeSlots($date);
        
        sendSuccess('Available time slots retrieved successfully', $availableSlots);
        
    } catch (Exception $e) {
        sendError('Error retrieving available slots: ' . $e->getMessage(), 500);
    }
}



?>
