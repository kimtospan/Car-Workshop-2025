<?php
/**
 * Users API Endpoint
 * Handles user CRUD operations via AJAX
 */

require_once '../../includes/auth.php';
require_once __DIR__ . '/common.php';
require_once '../../src/config/database.php';
require_once '../../src/models/User.php';
require_once '../../src/services/ValidationService.php';
require_once '../../src/services/FormProcessor.php';

// Ensure user is authenticated
$auth->requireLogin();

// Only allow specific HTTP methods
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST', 'PUT', 'DELETE'])) {
    sendError('Method not allowed', 405);
}

// Initialize dependencies
$db = Database::getInstance()->getConnection();
$userModel = new User();
$formProcessor = new FormProcessor($db, $auth);
$validator = new ValidationService();

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
 *  GET  - profile, user lookup, and listing.
 *
 * Returns the current user's profile when requested, or (for secretaries)
 * paginated user listings and individual user records. 
 */
function handleGet() {
    global $auth, $userModel;
    
    $currentUser = $auth->getCurrentUser();
    
    // Handle profile request (accessible to all authenticated users)
    if (isset($_GET['action']) && $_GET['action'] === 'get_profile') {
        try {
            $profile = $userModel->getUserWithRole($currentUser['id']);
            
            if (!$profile) {
                sendError('Profile not found', 404);
            }
            
            sendSuccess('Profile retrieved successfully', $profile);
        } catch (Exception $e) {
            sendError('Error retrieving profile: ' . $e->getMessage(), 500);
        }
        return;
    }
    
    // Only secretaries can list all users
    if ($currentUser['role'] !== 'secretary') {
        sendError('Access denied', 403);
    }
    
    try {
        if (isset($_GET['id'])) {
            // Get specific user with role information
            $userId = (int)$_GET['id'];
            $user = $userModel->getUserWithRole($userId);
            
            if (!$user) {
                sendError('User not found', 404);
            }
            
            sendSuccess('User retrieved successfully', $user);
        } else {
            // Handle pagination parameters
            $page = max(1, intval($_GET['page'] ?? 1));
            $perPage = min(5, max(1, intval($_GET['per_page'] ?? 10))); // Max 10 items per page
            $offset = ($page - 1) * $perPage;
            
            // Optional search query (real-time filtering)
            $q = isset($_GET['q']) ? trim($_GET['q']) : '';
            // Optional role/status filters and sort token
            $roleFilter = isset($_GET['role']) ? trim($_GET['role']) : '';
            $statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
            $sortToken = isset($_GET['sort']) ? trim($_GET['sort']) : '';

            if ($q !== '') {
                $users = $userModel->searchUsersWithRoles($q, $perPage, $offset);
                // Apply optional role/status client-provided filters on the server-side result set
                if ($roleFilter) {
                    $users = array_values(array_filter($users, function($u) use ($roleFilter) {
                        return (isset($u['actual_role']) && $u['actual_role'] === $roleFilter) || (isset($u['role']) && $u['role'] === $roleFilter);
                    }));
                }
                if ($statusFilter) {
                    $users = array_values(array_filter($users, function($u) use ($statusFilter) {
                        $active = $statusFilter === 'active';
                        return (isset($u['is_active']) && (bool)$u['is_active'] === $active) || (isset($u['status']) && (($u['status'] === 'active') === $active));
                    }));
                }

                // Apply simple sorting in PHP for search results using supported tokens
                if ($sortToken) {
                    switch ($sortToken) {
                        case 'name_asc':
                            usort($users, function($a, $b) {
                                $an = strtolower(trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')));
                                $bn = strtolower(trim(($b['first_name'] ?? '') . ' ' . ($b['last_name'] ?? '')));
                                return $an <=> $bn;
                            });
                            break;
                        case 'name_desc':
                            usort($users, function($a, $b) {
                                $an = strtolower(trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')));
                                $bn = strtolower(trim(($b['first_name'] ?? '') . ' ' . ($b['last_name'] ?? '')));
                                return $bn <=> $an;
                            });
                            break;
                        case 'username_asc':
                            usort($users, function($a, $b) { return strtolower($a['username'] ?? '') <=> strtolower($b['username'] ?? ''); });
                            break;
                        case 'username_desc':
                            usort($users, function($a, $b) { return strtolower($b['username'] ?? '') <=> strtolower($a['username'] ?? ''); });
                            break;
                        case 'email_asc':
                            usort($users, function($a, $b) { return strtolower($a['email'] ?? '') <=> strtolower($b['email'] ?? ''); });
                            break;
                        case 'email_desc':
                            usort($users, function($a, $b) { return strtolower($b['email'] ?? '') <=> strtolower($a['email'] ?? ''); });
                            break;
                        
                    }
                }

                // Get total count for search (note: filters applied above change the effective total)
                $totalUsers = count($users);
                // Apply pagination slice after filtering/sorting
                $users = array_slice($users, $offset, $perPage);
            } else {
                // No search query: request paginated data from model with optional sort token
                $users = $userModel->getAllWithRoles($perPage, $offset, $sortToken);
                // Apply role/status filters if present
                if ($roleFilter) {
                    $users = array_values(array_filter($users, function($u) use ($roleFilter) {
                        return (isset($u['actual_role']) && $u['actual_role'] === $roleFilter) || (isset($u['role']) && $u['role'] === $roleFilter);
                    }));
                }
                if ($statusFilter) {
                    $users = array_values(array_filter($users, function($u) use ($statusFilter) {
                        $active = $statusFilter === 'active';
                        return (isset($u['is_active']) && (bool)$u['is_active'] === $active) || (isset($u['status']) && (($u['status'] === 'active') === $active));
                    }));
                }

                // Get total count
                $totalUsers = $userModel->getTotalUsersCount();
            }
            
            // Build pagination metadata
            $totalPages = ceil($totalUsers / $perPage);
            $hasMore = $page < $totalPages;
            
            $meta = [
                'total' => $totalUsers,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPages,
                'has_more' => $hasMore
            ];

            sendSuccess('Users retrieved successfully', $users, $meta);
        }
        
    } catch (Exception $e) {
        sendError('Error retrieving users: ' . $e->getMessage(), 500);
    }
}

/**
 * POST  - create user or update profile.
 *
 * If `action=update_profile` this updates the logged-in user's profile.
 * Otherwise, secretaries may create new users. Validation and persistence
 * are delegated to FormProcessor.
 */
function handlePost() {
    global $auth, $formProcessor, $validator;
    
    $currentUser = $auth->getCurrentUser();
    
    // Handle profile update (accessible to all authenticated users)
    if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
        try {
            // Use FormProcessor for profile update
            $result = $formProcessor->processProfileUpdate($_POST);
            
            if ($result['success']) {
                sendSuccess($result['message'], [
                    'updated_fields' => array_keys($_POST)
                ]);
            } else {
                sendError($result['message'], 400, $result['errors'] ?? null);
            }
        } catch (Exception $e) {
            sendError('Error updating profile: ' . $e->getMessage(), 500);
        }
        return;
    }
    
    // User creation requires secretary role
    if ($currentUser['role'] !== 'secretary') {
        sendError('Access denied', 403);
    }
    
    try {
        // Validate input - build field-specific errors for missing fields
        $requiredFields = ['username', 'email', 'password', 'first_name', 'last_name', 'id_number', 'role'];
        $fieldErrors = [];
        foreach ($requiredFields as $field) {
            if (!isset($_POST[$field]) || trim((string)$_POST[$field]) === '') {
                $fieldErrors[$field] = "Το πεδίο {$field} είναι υποχρεωτικό";
            }
        }
        if (!empty($fieldErrors)) {
            sendError('Validation errors', 400, $fieldErrors);
        }
        
        // Use FormProcessor for user creation
        $result = $formProcessor->processUserCreation($_POST);
        
        if ($result['success']) {
            sendSuccess($result['message'], [
                'user_id' => $result['user_id'] ?? null
            ]);
        } else {
            sendError($result['message'], 400, $result['errors'] ?? null);
        }
        
    } catch (Exception $e) {
        sendError('Error creating user: ' . $e->getMessage(), 500);
    }
}

/**
 *  PUT  - update a user's details or status.
 *
 * Allows secretaries (or the user themselves) to update user fields or
 * toggle status. The PUT payload is validated and processed by
 * FormProcessor and results are returned in the standard JSON format.
 */
function handlePut() {
    global $auth, $formProcessor;
    
    $currentUser = $auth->getCurrentUser();
    
    // Parse PUT data (support both form-encoded and JSON bodies)
    $raw = file_get_contents("php://input");
    $putData = [];
    // Try form-encoded parsing first
    parse_str($raw, $putData);
    // If user_id is not present, attempt to decode JSON body
    if ((empty($putData) || !isset($putData['user_id'])) && $raw) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $putData = $decoded;
        }
    }

    if (!isset($putData['user_id'])) {
        sendError('User ID is required', 400);
    }
    
    $userId = (int)$putData['user_id'];
    
    // Check permissions
    if ($currentUser['role'] !== 'secretary' && $currentUser['id'] != $userId) {
        sendError('Access denied', 403);
    }
    
    try {
        // Toggle status if requested (secretary only)
        if ($currentUser['role'] === 'secretary' && (isset($putData['new_status']) || isset($putData['status']))) {
            $status = isset($putData['status']) ? $putData['status'] : ((int)$putData['new_status'] === 1 ? 'active' : 'inactive');
            $result = $formProcessor->processUserManagement('toggle_user_status', [
                'user_id' => $userId,
                'status' => $status
            ]);
            if ($result['success']) {
                sendSuccess($result['message'] ?? 'Status updated');
            } else {
                sendError($result['message'] ?? 'Failed to update status', 400);
            }
        }
        
        // Use FormProcessor for user update
        $result = $formProcessor->processUserUpdate($putData);
        
        if ($result['success']) {
            sendSuccess($result['message']);
        } else {
            sendError($result['message'], 400, $result['errors'] ?? null);
        }
        
    } catch (Exception $e) {
        sendError('Error updating user: ' . $e->getMessage(), 500);
    }
}

/**
 *  DELETE  - a user.
 *
 * Removes a user account after checking
 * permissions. Deletion logic is handled by FormProcessor
 */
function handleDelete() {
    global $auth, $formProcessor;
    
    $currentUser = $auth->getCurrentUser();
    
    // Parse DELETE data (support both form-encoded and JSON bodies)
    $rawDelete = file_get_contents("php://input");
    $deleteData = [];
    parse_str($rawDelete, $deleteData);
    if ((empty($deleteData) || !isset($deleteData['user_id'])) && $rawDelete) {
        $decoded = json_decode($rawDelete, true);
        if (is_array($decoded)) {
            $deleteData = $decoded;
        }
    }

    if (!isset($deleteData['user_id'])) {
        sendError('User ID is required', 400);
    }

    $userId = (int)$deleteData['user_id'];
    
    // Check permissions
    if ($currentUser['role'] !== 'secretary' && $currentUser['id'] != $userId) {
        sendError('Access denied', 403);
    }
    
    try {
        // Use FormProcessor for user deletion
        $result = $formProcessor->processUserDeletion($deleteData);
        
        if ($result['success']) {
            sendSuccess($result['message']);
        } else {
            sendError($result['message'], 400);
        }
        
    } catch (Exception $e) {
        sendError('Error deleting user: ' . $e->getMessage(), 500);
    }
}
?>
