<?php
/**
 * Vehicles API Endpoint
 * Handles CRUD operations for vehicles
 */

require_once '../../includes/auth.php';
require_once '../../src/config/database.php';
require_once '../../src/models/Car.php';
require_once __DIR__ . '/common.php';


// Handle preflight requests for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Require authentication
$auth->requireLogin();
$currentUser = $auth->getCurrentUser();

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $carModel = new Car();
    
    switch ($method) {
        case 'GET':
            handleGetVehicles($carModel, $currentUser);
            break;
        case 'POST':
            handleCreateVehicle($carModel, $currentUser);
            break;
        case 'PUT':
            handleUpdateVehicle($carModel, $currentUser);
            break;
        case 'DELETE':
            handleDeleteVehicle($carModel, $currentUser);
            break;
        default:
            sendError('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    error_log("Vehicles API Error: " . $e->getMessage());
    sendError('Internal server error: ' . $e->getMessage(), 500);
}

/**
 * Handle GET requests for vehicles.
 *
 * Returns vehicle lists or a single vehicle record depending on query
 * parameters. It supports `customer_vehicles`, `id`, `customer_id` and
 * paginated listings, enforces role-based access (customers only see
 * their own vehicles) and relies on the Car model for data access.
 */
function handleGetVehicles($carModel, $currentUser) {
    $customerId = $_GET['customer_id'] ?? null;
    $vehicleId = $_GET['id'] ?? null;
    $search = $_GET['search'] ?? $_GET['q'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = min(100, max(10, intval($_GET['per_page'] ?? 25)));
    
    try {
        // Handle customer vehicles request
        if (isset($_GET['customer_vehicles'])) {
            $customerIdFromUser = getCustomerIdFromUserId((int)$currentUser['id']);
            if (!$customerIdFromUser) {
                sendError('Customer not found', 404);
            }
            
            $carModel = new Car();
            $vehicles = $carModel->getCustomerVehicles($customerIdFromUser);
            
            // Apply search filter if provided
            if (!empty($search)) {
                $vehicles = array_filter($vehicles, function($vehicle) use ($search) {
                    $searchLower = strtolower($search);
                    return strpos(strtolower($vehicle['brand']), $searchLower) !== false ||
                           strpos(strtolower($vehicle['model']), $searchLower) !== false ||
                           strpos(strtolower($vehicle['serial_number']), $searchLower) !== false ||
                           strpos(strtolower($vehicle['vehicle_type']), $searchLower) !== false;
                });
            }
            
            sendSuccess('Customer vehicles retrieved successfully', array_values($vehicles));
            return;
        }
        
        if ($vehicleId) {
            // Get specific vehicle
            $vehicle = getVehicleById($vehicleId, $currentUser);
            if (!$vehicle) {
                sendError('Vehicle not found', 404);
                return;
            }
            sendSuccess('Vehicle retrieved successfully', $vehicle);
            
        } elseif ($customerId) {
            // Get vehicles for specific customer
            $vehicles = getVehiclesByCustomer($customerId, $currentUser);
            sendSuccess('Vehicles retrieved successfully', $vehicles, ['vehicles' => $vehicles]);
            
        } else {
            // Get all vehicles with pagination
            $result = getAllVehiclesPaginated($carModel, $currentUser, $search, $page, $perPage);
            sendSuccess('Vehicles retrieved successfully', $result['data'], $result['meta']);
        }
        
    } catch (Exception $e) {
        error_log("Get vehicles error: " . $e->getMessage());
        sendError('Failed to retrieve vehicles: ' . $e->getMessage());
    }
}

/**
 * Get customer ID from a user record.
 *
 * Many handlers need to map the authenticated user to their customers row;
 * this helper runs that lookup and returns the customer id or null.
 */
function getCustomerIdFromUser($currentUser) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id FROM customers WHERE user_id = ?");
    $stmt->execute([$currentUser['id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['id'] : null;
}

/**
 * Get vehicles by customer ID.
 *
 * Returns a customer's vehicles after verifying access and adds a
 * user-friendly `vehicle_display` field for UI consumption.
 */
function getVehiclesByCustomer($customerId, $currentUser) {
    $db = Database::getInstance()->getConnection();
    
    // Check access permissions
    if ($currentUser['role'] === 'customer') {
        // Customers can only see their own vehicles
        $stmt = $db->prepare("
            SELECT c.id FROM customers c 
            WHERE c.id = ? AND c.user_id = ?
        ");
        $stmt->execute([$customerId, $currentUser['id']]);
        if (!$stmt->fetch()) {
            throw new Exception('Access denied');
        }
    }
    
        // Delegate data retrieval to the Car model to keep data access centralized
        $carModel = new Car();
        $vehicles = $carModel->getCustomerVehicles((int)$customerId);

    // Build a friendly display string. Use serial_number where available.
        foreach ($vehicles as &$v) {
            $brand = $v['brand'] ?? '';
            $model = $v['model'] ?? '';
            $serial = $v['serial_number'] ?? '';
            $display = trim($brand . ' ' . $model);
            if ($serial !== '') {
                $display .= ' (' . $serial . ')';
            }
            $v['vehicle_display'] = $display;
        }
        unset($v);

        return $vehicles;
}

/**
 * Get a vehicle record by its ID.
 *
 * Returns the vehicle along with owner information and enforces that a
 * customer can only access their own vehicle. Used by get/update/delete
 * flows.
 */
function getVehicleById($vehicleId, $currentUser) {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("
        SELECT v.*, 
               CONCAT(u.first_name, ' ', u.last_name) as customer_name,
               u.email as customer_email
        FROM vehicles v
        JOIN customers c ON v.customer_id = c.id
        JOIN users u ON c.user_id = u.id
        WHERE v.id = ?
    ");
    $stmt->execute([$vehicleId]);
    $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vehicle) {
        return null;
    }
    
    // Check access permissions
    if ($currentUser['role'] === 'customer') {
        $stmt = $db->prepare("
            SELECT c.id FROM customers c 
            WHERE c.id = ? AND c.user_id = ?
        ");
        $stmt->execute([$vehicle['customer_id'], $currentUser['id']]);
        if (!$stmt->fetch()) {
            throw new Exception('Access denied');
        }
    }
    
    return $vehicle;
}

/**
 * Get vehicles with pagination and optional search.
 *
 * Returns a single page of vehicles plus pagination metadata. The query
 * is parameterized and scoped by role (customers only see their vehicles)
 * to keep results efficient for the frontend.
 */
function getAllVehiclesPaginated($carModel, $currentUser, $search, $page, $perPage) {
    $db = Database::getInstance()->getConnection();
    
    // Build base query
    $whereClause = '';
    $params = [];
    
    if ($search) {
        // Search against serial_number (schema) along with brand/model/customer
        $whereClause = " WHERE (v.brand LIKE ? OR v.model LIKE ? OR v.serial_number LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
        $searchTerm = "%$search%";
        $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
    }
    
    // If customer, only show their vehicles
    if ($currentUser['role'] === 'customer') {
        $customerWhere = $whereClause ? " AND c.user_id = ?" : " WHERE c.user_id = ?";
        $whereClause .= $customerWhere;
        $params[] = $currentUser['id'];
    }
    
    // Get total count
    $countSql = "
        SELECT COUNT(*) as total
        FROM vehicles v
        JOIN customers c ON v.customer_id = c.id
        JOIN users u ON c.user_id = u.id
        $whereClause
    ";
    
    $stmt = $db->prepare($countSql);
    $stmt->execute($params);
    $totalItems = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get paginated data
    $offset = ($page - 1) * $perPage;
    
    $dataSql = "
        SELECT v.*, 
               CONCAT(u.first_name, ' ', u.last_name) as customer_name,
               u.email as customer_email
        FROM vehicles v
        JOIN customers c ON v.customer_id = c.id
        JOIN users u ON c.user_id = u.id
        $whereClause
        ORDER BY v.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $perPage;
    $params[] = $offset;
    
    $stmt = $db->prepare($dataSql);
    $stmt->execute($params);
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate pagination metadata
    $totalPages = ceil($totalItems / $perPage);
    $hasMore = $page < $totalPages;
    
    return [
        'data' => $vehicles,
        'meta' => [
            'page' => $page,
            'per_page' => $perPage,
            'total_items' => $totalItems,
            'total_pages' => $totalPages,
            'has_more' => $hasMore
        ]
    ];
}

/**
 * Handle POST requests to create a vehicle.
 *
 * Accepts JSON or form data, validates input, ensures the caller has
 * permission (customers can only add their own vehicles) and uses the
 * Car model to persist the new record. Called by customer and secretary
 * UIs.
 */
function handleCreateVehicle($carModel, $currentUser) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // If JSON decode fails, try to get from POST (for form submissions)
    if (!$input) {
        $input = $_POST;
    }
    
    // Validate required fields - using schema field names
    $required = ['customer_id', 'brand', 'model', 'serial_number', 'vehicle_type', 'engine_type', 
                'door_count', 'wheel_count', 'production_date', 'acquisition_year'];
    
    foreach ($required as $field) {
        if (empty($input[$field])) {
            sendError("Field '$field' is required");
            return;
        }
    }
    
    // Check permissions - customers can only add vehicles to themselves
    if ($currentUser['role'] === 'customer') {
        // Get customer ID from user ID using the utility function
        require_once '../../src/utils/Utils.php';
        $customerIdFromUser = getCustomerIdFromUserId((int)$currentUser['id']);
        
        if (!$customerIdFromUser || $customerIdFromUser != intval($input['customer_id'])) {
            sendError('Access denied: You can only add vehicles to your own account', 403);
            return;
        }
    }

    try {
        // Use ValidationService for proper validation
        require_once '../../src/services/ValidationService.php';
        
        // Prepare data for validation (matching schema fields exactly)
        $vehicleData = [
            'customer_id' => intval($input['customer_id']),
            'serial_number' => trim(strtoupper($input['serial_number'])),
            'brand' => trim($input['brand']),
            'model' => trim($input['model']),
            'vehicle_type' => trim($input['vehicle_type']),
            'engine_type' => trim($input['engine_type']),
            'door_count' => intval($input['door_count']),
            'wheel_count' => intval($input['wheel_count']),
            'production_date' => trim($input['production_date']),
            'acquisition_year' => intval($input['acquisition_year'])
        ];
        
        // Validate the data using ValidationService
        $errors = ValidationService::validateVehicleData($vehicleData);
        if (!empty($errors)) {
            sendError('Validation failed', 400, ['errors' => $errors]);
            return;
        }
        
        // Check if serial number is already taken
        if ($carModel->serialNumberExists($vehicleData['serial_number'])) {
            sendError('Validation failed', 400, ['errors' => ['serial_number' => 'Ο αριθμός κυκλοφορίας υπάρχει ήδη στο σύστημα']]);
            return;
        }
        
        // Create the vehicle using the Car model
        $result = $carModel->createVehicle($vehicleData);
        
        if ($result['success']) {
            sendSuccess($result['message'], ['vehicle_id' => $result['vehicle_id'] ?? null]);
        } else {
            sendError($result['message'], 400);
        }
        
    } catch (Exception $e) {
        error_log("Create vehicle error: " . $e->getMessage());
        sendError('Failed to create vehicle: ' . $e->getMessage());
    }
}

/**
 * Handle PUT requests to update a vehicle.
 *
 * Validates payload, checks permissions and updates the vehicle using the
 * Car model. Customers can update their own vehicles and secretaries can
 * update any vehicle.
 */
function handleUpdateVehicle($carModel, $currentUser) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['vehicle_id'])) {
        sendError('Vehicle ID is required');
        return;
    }
    
    // Check permissions
    $vehicle = getVehicleById($input['vehicle_id'], $currentUser);
    if (!$vehicle) {
        sendError('Vehicle not found', 404);
        return;
    }
    
    // Only secretary can update vehicles, or customer can update their own
    if ($currentUser['role'] !== 'secretary' && 
        ($currentUser['role'] !== 'customer' || $vehicle['customer_id'] != getCustomerIdFromUser($currentUser))) {
        sendError('Access denied', 403);
        return;
    }
    
    try {
        // Use ValidationService for proper validation
        require_once '../../src/services/ValidationService.php';
        
        // Prepare data for validation (matching schema fields)
        $vehicleData = [
            'serial_number' => trim($input['serial_number'] ?? ''),
            'brand' => trim($input['brand'] ?? ''),
            'model' => trim($input['model'] ?? ''),
            'vehicle_type' => trim($input['vehicle_type'] ?? ''),
            'engine_type' => trim($input['engine_type'] ?? ''),
            'door_count' => intval($input['door_count'] ?? 0),
            'wheel_count' => intval($input['wheel_count'] ?? 0),
            'production_date' => trim($input['production_date'] ?? ''),
            'acquisition_year' => intval($input['acquisition_year'] ?? 0)
        ];
        
        // Validate the data
        $errors = ValidationService::validateVehicleData($vehicleData);
        if (!empty($errors)) {
            sendError('Validation failed', 400, ['errors' => $errors]);
            return;
        }
        
        // Check if serial number is already taken by another vehicle
        if ($carModel->isSerialNumberTaken($vehicleData['serial_number'], $input['vehicle_id'])) {
            sendError('Validation failed', 400, ['errors' => ['serial_number' => 'The serial number is already in use by another vehicle.']]);
            return;
        }
        
        // Add the vehicle ID to the data
        $vehicleData['id'] = intval($input['vehicle_id']);
        
        $result = $carModel->updateVehicle($vehicleData);
        
        if ($result['success']) {
            sendSuccess($result['message'], $result['vehicle']);
        } else {
            sendError($result['message']);
        }
        
    } catch (Exception $e) {
        error_log("Update vehicle error: " . $e->getMessage());
        sendError('Failed to update vehicle: ' . $e->getMessage());
    }
}

/**
 * Handle DELETE requests to remove a vehicle.
 *
 * Verifies the vehicle exists and that the caller is authorized (secretary
 * or owning customer), then delegates deletion.
 */
function handleDeleteVehicle($carModel, $currentUser) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['id'])) {
        sendError('Vehicle ID is required');
        return;
    }
    
    // Check permissions
    $vehicle = getVehicleById($input['id'], $currentUser);
    if (!$vehicle) {
        sendError('Vehicle not found', 404);
        return;
    }
    
    // Only secretary can delete vehicles, or customer can delete their own
    if ($currentUser['role'] !== 'secretary' && $currentUser['role'] !== 'customer') {
        sendError('Access denied', 403);
        return;
    }
    
    try {
        $result = $carModel->deleteVehicleById($input['id']);
        
        if ($result['success']) {
            sendSuccess(null, $result['message']);
        } else {
            sendError($result['message']);
        }
        
    } catch (Exception $e) {
        error_log("Delete vehicle error: " . $e->getMessage());
        sendError('Failed to delete vehicle: ' . $e->getMessage());
    }
}

/**
 * Handle form-based vehicle updates (POST with FormData)
 */
function handleUpdateVehicleForm($carModel, $currentUser) {
    // Validate required fields
    if (empty($_POST['vehicle_id'])) {
        sendError('Vehicle ID is required');
        return;
    }
    
    $vehicleId = intval($_POST['vehicle_id']);
    
    // Check permissions
    $vehicle = getVehicleById($vehicleId, $currentUser);
    if (!$vehicle) {
        sendError('Vehicle not found', 404);
        return;
    }
    
    try {
        $vehicleData = [
            'id' => $vehicleId,
            'brand' => trim($_POST['brand'] ?? $vehicle['brand']),
            'model' => trim($_POST['model'] ?? $vehicle['model']),
            'serial_number' => trim($_POST['serial_number'] ?? $vehicle['serial_number']),
            'vehicle_type' => trim($_POST['vehicle_type'] ?? $vehicle['vehicle_type']),
            'engine_type' => trim($_POST['engine_type'] ?? $vehicle['engine_type']),
            'acquisition_year' => intval($_POST['acquisition_year'] ?? $vehicle['acquisition_year'])
        ];
        
        $result = $carModel->updateVehicle($vehicleData);
        
        if ($result['success']) {
            sendSuccess($result['vehicle'], $result['message']);
        } else {
            sendError($result['message']);
        }
        
    } catch (Exception $e) {
        error_log("Update vehicle form error: " . $e->getMessage());
        sendError('Failed to update vehicle: ' . $e->getMessage());
    }
}

/**
 * Handle form-based vehicle deletion (POST with FormData)
 */
function handleDeleteVehicleForm($carModel, $currentUser) {
    // Validate required fields
    if (empty($_POST['vehicle_id'])) {
        sendError('Vehicle ID is required');
        return;
    }
    
    $vehicleId = intval($_POST['vehicle_id']);
    
    // Check permissions
    $vehicle = getVehicleById($vehicleId, $currentUser);
    if (!$vehicle) {
        sendError('Vehicle not found', 404);
        return;
    }
    
    // Only secretary can delete vehicles, or customer can delete their own
    if ($currentUser['role'] !== 'secretary' && $currentUser['role'] !== 'customer') {
        sendError('Access denied', 403);
        return;
    }
    
    try {
        $result = $carModel->deleteVehicleById($vehicleId);
        
        if ($result['success']) {
            sendSuccess(null, $result['message']);
        } else {
            sendError($result['message']);
        }
        
    } catch (Exception $e) {
        error_log("Delete vehicle form error: " . $e->getMessage());
        sendError('Failed to delete vehicle: ' . $e->getMessage());
    }
}
?>
