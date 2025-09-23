<?php
/**
 * Tasks API Endpoint
 * Handles task CRUD operations via AJAX
 */

require_once '../../includes/auth.php';
require_once '../../src/config/database.php';
require_once __DIR__ . '/common.php';
require_once '../../src/models/Task.php';
require_once '../../src/services/FormProcessor.php';

// Ensure user is authenticated
$auth->requireLogin();

// Only allow specific HTTP methods
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST', 'PUT', 'DELETE'])) {
    sendError('Method not allowed', 405);
}

// Initialize dependencies
$db = Database::getInstance()->getConnection();
$taskModel = new Task();
$formProcessor = new FormProcessor($db, $auth);
require_once __DIR__ . '/../../src/config/AppConfig.php';

// Route to appropriate handler
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
 * Handle GET requests - List tasks or get a specific task.
 *
 * This handler lets the app read task data so mechanics can see their
 * assigned work and secretaries can review or search tasks. It looks at
 * query parameters like `task_id` or `appointment_id`, has role-based
 * access, and asks the Task model for the actual data. The response is a
 * consistent JSON payload 
 */
function handleGet() {
    global $auth, $taskModel;
    
    $currentUser = $auth->getCurrentUser();
    
    try {
        if (isset($_GET['task_id'])) {
            // Get specific task
            $taskId = (int)$_GET['task_id'];
            $task = $taskModel->findById($taskId);
            
            if (!$task) {
                sendError('Task not found', 404);
            }
            
            // Check permissions - only assigned mechanic or secretary can view
            if ($currentUser['role'] === 'mechanic') {
                $mechanicId = $taskModel->getMechanicIdFromUser($currentUser['id']);
                if (!$taskModel->canAccessTask($taskId, $mechanicId)) {
                    sendError('Access denied', 403);
                }
            } elseif ($currentUser['role'] !== 'secretary') {
                sendError('Access denied', 403);
            }
            
            sendSuccess('Task retrieved successfully', $task);
            
        } elseif (isset($_GET['appointment_id'])) {
            // Get tasks for specific appointment
            $appointmentId = (int)$_GET['appointment_id'];
            $tasks = $taskModel->getTasksByAppointment($appointmentId);
            
            sendSuccess('Tasks retrieved successfully', [
                'tasks' => $tasks,
                'total' => count($tasks)
            ]);
            
        } else {
            // Get tasks based on user role
            if ($currentUser['role'] === 'mechanic') {
                $mechanicId = $taskModel->getMechanicIdFromUser($currentUser['id']);
                
                if (!$mechanicId) {
                    // Add debug info
                    error_log("Tasks API: No mechanic record found for user ID: " . $currentUser['id'] . ", role: " . $currentUser['role']);
                    // Instead of error, return empty array
                    sendSuccess('No mechanic record found - returning empty tasks list', []);
                    return;
                }
                
                $tasks = $taskModel->getMechanicTasks($mechanicId);
            } elseif ($currentUser['role'] === 'secretary') {
                $tasks = $taskModel->getAllTasks();
            } else {
                sendError('Access denied. Current role: ' . $currentUser['role'], 403);
            }
            
            sendSuccess('Tasks retrieved successfully', $tasks);
        }
        
    } catch (Exception $e) {
        sendError('Error retrieving tasks: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle POST requests - create a new task.
 *
 * This endpoint creates a task linked to an appointment so work items,
 * materials and expected completion time are recorded. It validates the
 * incoming fields, enforces that only mechanics or secretaries can create
 * tasks, and uses the FormProcessor to perform validation and save the
 * record. It's called by the mechanic and secretary interfaces when they
 * add tasks 
 */
function handlePost() {
    global $auth, $formProcessor;
    
    $currentUser = $auth->getCurrentUser();
    
    // Only mechanics and secretaries can create tasks
    if (!in_array($currentUser['role'], ['mechanic', 'secretary'])) {
        sendError('Access denied', 403);
    }
    
    try {
        // Optional CSRF validation for state-changing requests
        if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'DELETE') {
            $raw = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : [];
            if (in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'DELETE'])) {
                parse_str(file_get_contents('php://input'), $raw);
            }
            if (!empty($raw['csrf_token']) && !\SecurityConfig::validateCSRFToken($raw['csrf_token'])) {
                sendError('Invalid CSRF token', 403);
            }
        }
        // Validate required fields (cost is now calculated automatically)
        $requiredFields = ['appointment_id', 'description', 'materials', 'completion_time'];
        $missingFields = [];
        
        foreach ($requiredFields as $field) {
            // More precise validation: check if field exists and is not just whitespace
            if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
                $missingFields[] = $field;
            }
        }
        
        if (!empty($missingFields)) {
            sendError('Missing required fields', 400, [
                'missing_fields' => $missingFields
            ]);
        }
        
        // Get mechanic ID for current user (for validation purposes)
        $mechanicId = null;
        if ($currentUser['role'] === 'mechanic') {
            $stmt = Database::getInstance()->getConnection()->prepare("SELECT id FROM mechanics WHERE user_id = ?");
            $stmt->execute([$currentUser['id']]);
            $mechanicData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$mechanicData) {
                sendError('Mechanic profile not found', 404);
            }
            
            $mechanicId = $mechanicData['id'];
        }
        // Note: For secretaries, mechanic_id will be determined from the appointment
        
        // Use FormProcessor for task creation
        $result = $formProcessor->processTaskCreation($_POST, $mechanicId);
        
        if ($result['success']) {
            sendSuccess($result['message'], [
                'task_id' => $result['task_id'] ?? null
            ]);
        } else {
            sendError($result['message'], 400, $result['errors'] ?? null);
        }
        
    } catch (Exception $e) {
        sendError('Error creating task: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle PUT requests - update an existing task.
 *
 * Changes task details or status as work progresses. The
 * handler reads PUT data, verifies the caller's permissions (mechanics may
 * update their own tasks; secretaries can update any), and hands the
 * request to FormProcessor for validation and persistence. Frontend
 * clients (mechanic and secretary UIs) call this via AJAX when editing
 * tasks.
 */
function handlePut() {
    global $auth, $formProcessor;
    
    $currentUser = $auth->getCurrentUser();
    
    // Parse PUT data (support both form-encoded and JSON bodies)
    $rawPut = file_get_contents("php://input");
    $putData = [];
    parse_str($rawPut, $putData);
    if ((empty($putData) || !isset($putData['task_id'])) && $rawPut) {
        $decoded = json_decode($rawPut, true);
        if (is_array($decoded)) {
            $putData = $decoded;
        }
    }

    if (!isset($putData['task_id'])) {
        sendError('Task ID is required', 400);
    }
    
    try {
        // Get mechanic ID for permission checking
        if ($currentUser['role'] === 'mechanic') {
            $stmt = Database::getInstance()->getConnection()->prepare("SELECT id FROM mechanics WHERE user_id = ?");
            $stmt->execute([$currentUser['id']]);
            $mechanicData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$mechanicData) {
                sendError('Mechanic profile not found', 404);
            }
            
            $mechanicId = $mechanicData['id'];
        } else {
            $mechanicId = null; // Secretary can update any task
        }
        
        // Use FormProcessor for task update
        $result = $formProcessor->processTaskUpdate($putData, $mechanicId);
        
        if ($result['success']) {
            sendSuccess($result['message']);
        } else {
            sendError($result['message'], 400, $result['errors'] ?? null);
        }
        
    } catch (Exception $e) {
        sendError('Error updating task: ' . $e->getMessage(), 500);
    }
}

/**
 * DELETE task
 * 
 * It reads the incoming payload (or query param), checks that the caller
 * has permission (mechanics for their own tasks, secretaries for any),
 * and delegates the deletion to FormProcessor. The mechanic and secretary
 * UIs invoke this when they remove tasks.
 */
function handleDelete() {
    global $auth, $formProcessor;
    
    $currentUser = $auth->getCurrentUser();
    
    // Parse DELETE data - try multiple methods to get task_id (support JSON)
    $rawDelete = file_get_contents("php://input");
    $deleteData = [];
    parse_str($rawDelete, $deleteData);
    
    // Also check query parameters for task_id
    if (!isset($deleteData['task_id']) && isset($_GET['task_id'])) {
        $deleteData['task_id'] = $_GET['task_id'];
    }
    
    if (!isset($deleteData['task_id'])) {
        sendError('Task ID is required', 400);
    }
    
    try {
        // Get mechanic ID for permission checking
        if ($currentUser['role'] === 'mechanic') {
            $stmt = Database::getInstance()->getConnection()->prepare("SELECT id FROM mechanics WHERE user_id = ?");
            $stmt->execute([$currentUser['id']]);
            $mechanicData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$mechanicData) {
                sendError('Mechanic profile not found', 404);
            }
            
            $mechanicId = $mechanicData['id'];
        } elseif ($currentUser['role'] === 'secretary') {
            $mechanicId = null; // Secretary can delete any task
        } else {
            sendError('Access denied - insufficient permissions', 403);
        }
        
        // Use FormProcessor for task deletion
        $result = $formProcessor->processTaskDeletion($deleteData, $mechanicId);
        
        if ($result['success']) {
            sendSuccess($result['message']);
        } else {
            sendError($result['message'], 400);
        }
        
    } catch (Exception $e) {
        sendError('Error deleting task: ' . $e->getMessage(), 500);
    }
}
?>
