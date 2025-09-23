<?php




require_once '../includes/auth.php';
require_once '../includes/language.php';
require_once '../src/config/database.php';
require_once '../src/views/layouts/header.php';
require_once '../src/views/layouts/footer.php';
require_once '../src/views/components/DashboardComponents.php';
require_once '../src/models/Car.php';
require_once '../src/models/Appointment.php';
require_once '../src/models/Task.php';
require_once '../src/services/FormProcessor.php';
require_once __DIR__ . '/../src/models/User.php';
require_once __DIR__ . '/../src/models/Car.php';
require_once __DIR__ . '/../src/models/Appointment.php';
// Handle logout first
if (isset($_GET['logout'])) {
    $auth->logout();
    header("Location: index.php");
    exit;
}

// Ensure user is secretary
$auth->requireLogin();
$currentUser = $auth->getCurrentUser();
$currentRole = $currentUser['role'];

if ($currentRole !== 'secretary') {
    header("Location: dashboard.php");
    exit;
}

// Get database connection for secretary management
$db = Database::getInstance()->getConnection();

// Initialize form processor
$formProcessor = new FormProcessor($db, $auth);

// Initialize message variables
$success_message = '';
$error_message = '';
$validation_errors = [];

// Initialize secretary data arrays
$allUsers = [];
$allAppointments = [];
$allVehicles = [];
$systemStats = [];
$allCustomers = [];
$allMechanics = [];

try {
    // Load all system data for secretary management
    $carModel = new Car();
    $appointmentModel = new Appointment();
    $userModel = new User();

    // Get all customers for appointment booking using existing model method
    try {
        $allCustomers = $userModel->getAllCustomers();
    } catch (Exception $e) {
        die('Error in getAllCustomers: ' . $e->getMessage());
    }

    // Get all mechanics for appointment assignment using existing model method  
    try {
        $allMechanics = $appointmentModel->getAllMechanics();
    } catch (Exception $e) {
        die('Error in getAllMechanics: ' . $e->getMessage());
    }

    // Get all users with their roles using existing model method
    try {
        $allUsers = $userModel->getAllWithRoles();
    } catch (Exception $e) {
        die('Error in getAllWithRoles: ' . $e->getMessage());
    }
    
    // Get all appointments with full details using existing model method
    try {
        $allAppointments = $appointmentModel->getAllAppointments();
    } catch (Exception $e) {
        die('Error in getAllAppointments: ' . $e->getMessage());
    }
    
    // Get all vehicles with customer info using existing model method
    try {
        $allVehicles = $carModel->getAllVehicles();
    } catch (Exception $e) {
        die('Error in getAllVehicles: ' . $e->getMessage());
    }
    
    // System statistics aggregation removed 
    $systemStats = null;
    
    // Prepare data for User Management modals 
    $userCars = [];
    $userAppointments = [];
    
    foreach ($allUsers as $systemUser) {
        // Get cars for customers
        try {
            if ($systemUser['actual_role'] === 'customer' && $systemUser['role_id']) {
                $userCars[$systemUser['id']] = $carModel->getCustomerVehicles($systemUser['role_id']);
            }
        } catch (Exception $e) {
            die('Error in getCustomerVehicles for user ' . $systemUser['id'] . ': ' . $e->getMessage());
        }
        
        // Get appointments for customers and mechanics 
        try {
            if (in_array($systemUser['actual_role'], ['customer', 'mechanic']) && $systemUser['role_id']) {
                if ($systemUser['actual_role'] === 'customer') {
                    $userAppointments[$systemUser['id']] = $appointmentModel->getCustomerAppointments($systemUser['role_id']);
                } else { // mechanic
                    $userAppointments[$systemUser['id']] = $appointmentModel->getMechanicAppointments($systemUser['role_id']);
                }
            }
        } catch (Exception $e) {
            die('Error in getAppointments for user ' . $systemUser['id'] . ': ' . $e->getMessage());
        }
    }
    
} catch (Exception $e) {
    $error_message = "Σφάλμα φόρτωσης δεδομένων: " . $e->getMessage();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle appointment status update
    if (isset($_POST['update_appointment_status'])) {
        $result = $formProcessor->processAppointmentStatusUpdate($_POST);
        
        if ($result['success']) {
            $success_message = $result['message'];
            // Refresh appointment data
            $allAppointments = $appointmentModel->getAllAppointments();
        } else {
            if (isset($result['errors'])) {
                $validation_errors = $result['errors'];
            } else {
                $error_message = $result['message'];
            }
        }
    }

    // Handle user management operations
    if (isset($_POST['user_management_action'])) {
        $action = $_POST['user_management_action'];
        $result = $formProcessor->processUserManagement($action, $_POST);
        
        if ($result['success']) {
            $success_message = $result['message'];
            // Refresh user data
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            if (isset($result['errors'])) {
                $validation_errors = $result['errors'];
            } else {
                $error_message = $result['message'];
            }
        }
    }

    // Handle user status toggle (activate/deactivate)
    if (isset($_POST['toggle_user_status'])) {
        $result = $formProcessor->processUserManagement('toggle_user_status', $_POST);
        
        if ($result['success']) {
            $success_message = $result['message'];
            // Refresh user data
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $error_message = $result['message'];
        }
    }

    // Handle user deletion
    if (isset($_POST['delete_user'])) {
        $result = $formProcessor->processUserManagement('delete_user', $_POST);
        
        if ($result['success']) {
            $success_message = $result['message'];
            // Refresh user data
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $error_message = $result['message'];
        }
    }

    // Handle user information update
    if (isset($_POST['update_user_details'])) {
        $result = $formProcessor->processUserManagement('update_user', $_POST);
        
        if ($result['success']) {
            $success_message = $result['message'];
            // Refresh user data
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            if (isset($result['errors'])) {
                $validation_errors = $result['errors'];
            } else {
                $error_message = $result['message'];
            }
        }
    }

    // Handle vehicle deletion
    if (isset($_POST['action']) && $_POST['action'] === 'delete_vehicle' && isset($_POST['vehicle_id'])) {
        $vehicleId = (int)$_POST['vehicle_id'];
        $result = $formProcessor->processVehicleDeletion($vehicleId, 'secretary');
        
        if ($result['success']) {
            $success_message = $result['message'];
            // Refresh vehicle data using existing model method
            $allVehicles = $carModel->getAllVehicles();
        } else {
            $error_message = $result['message'];
        }
    }

    // Handle appointment cancellation
    if (isset($_POST['action']) && $_POST['action'] === 'delete_appointment' && isset($_POST['appointment_id'])) {
        $appointmentId = (int)$_POST['appointment_id'];
        $result = $formProcessor->processAppointmentCancellation($appointmentId, 'secretary');
        
        if ($result['success']) {
            $success_message = $result['message'];
            // Refresh appointment data
            $allAppointments = $appointmentModel->getAllAppointments();
        } else {
            $error_message = $result['message'];
        }
    }

    // Handle profile update
    if (isset($_POST['update_profile'])) {
        $result = $formProcessor->processProfileUpdate($_POST);
        
        if ($result['success']) {
            $success_message = $result['message'];
            $currentUser = $auth->getCurrentUser();
        } else {
            if (isset($result['errors'])) {
                $validation_errors = $result['errors'];
            } else {
                $error_message = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang->getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('site_title') ?> - <?= t('secretary') ?> <?= t('dashboard') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/custom.css" rel="stylesheet">
    
    
</head>
<body>
    <?php renderHeader($lang, 'dashboard'); ?>

    <main class="py-4">
        <div class="container">
            
            
            <div class="col-12">
                <div id="alertContainer"></div>
                <?php DashboardComponents::renderProfileHeader($currentUser); ?>
            </div>

            <!-- Success/Error Messages -->
            <?php if (!empty($success_message)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fa-solid fa-check-circle me-2"></i>
                            <?= htmlspecialchars($success_message) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fa-solid fa-exclamation-triangle me-2"></i>
                            <?= htmlspecialchars($error_message) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($validation_errors)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <i class="fa-solid fa-exclamation-triangle me-2"></i>
                            <strong>Σφάλματα επικύρωσης:</strong>
                            <ul class="mb-0 mt-2">
                                <?php foreach ($validation_errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        
               
                
                <!-- Management Tabs (Users, Appointments, Vehicles) -->
                    <!-- Management Tabs (Users, Appointments, Vehicles) -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card border-0 shadow-sm rounded-3">
                                <div class="card-header bg-transparent border-0 py-3">
                                    <ul class="nav nav-tabs card-header-tabs" id="secretaryTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="users-tab" data-bs-toggle="tab" data-bs-target="#usersTab" type="button" role="tab">
                                                <i class="fa-solid fa-users me-2"></i><?= t('users') ?>
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="appointments-tab" data-bs-toggle="tab" data-bs-target="#appointmentsTab" type="button" role="tab">
                                                <i class="fa-solid fa-calendar-check me-2"></i><?= t('appointments') ?>
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="vehicles-tab" data-bs-toggle="tab" data-bs-target="#vehiclesTab" type="button" role="tab">
                                                <i class="fa-solid fa-car me-2"></i><?= t('vehicles') ?>
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                                <div class="card-body">
                                    <div class="tab-content" id="secretaryTabContent">
                                        <!-- Users Tab -->
                                        <div class="tab-pane fade show active" id="usersTab" role="tabpanel">
                                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                                <div class="d-flex align-items-center gap-2 flex-wrap flex-grow-1">
                                                    <div class="input-group" style="min-width: 200px; max-width: 320px;">
                                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                                        <input type="search" id="user_search" class="form-control" placeholder="<?= t('search_users') ?>">
                                                    </div>
                                                    <select id="user_filter" class="form-select" style="min-width: 150px; max-width: 260px;">
                                                        <option value="all"><?= t('all_users') ?></option>
                                                        <option value="role:customer"><?= t('customers_only') ?></option>
                                                        <option value="role:mechanic"><?= t('mechanics_only') ?></option>
                                                        <option value="role:secretary"><?= t('secretaries_only') ?></option>
                                                        <option value="status:active"><?= t('active_users') ?></option>
                                                        <option value="status:inactive"><?= t('inactive_users') ?></option>
                                                        <option value="sort:name_asc"><?= t('name_a_z') ?></option>
                                                        <option value="sort:name_desc"><?= t('name_z_a') ?></option>
                                                    </select>
                                                </div>
                                                <div class="d-flex gap-2">
                                        
                                        </a>
                                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                                        <i class="bi bi-plus-lg me-1"></i> <?= t('new_user') ?>
                                                    </button>
                                                    <!-- Import users CSV (secretary only) -->
                                                    <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importUsersModal" title="<?= t('import_users') ?>">
                                                        <i class="bi bi-upload me-1"></i> <?= t('import_csv') ?>
                                                    </button>
                                    </div>
                                </div>
                                            <div class="table-responsive" id="usersTable">
                                                <table class="table table-hover mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>#</th>
                                                            <th><?= t('full_name') ?></th>
                                                            <th><?= t('username') ?></th>
                                                            <th><?= t('email') ?></th>
                                                            <th><?= t('role') ?></th>
                                                            <th><?= t('status') ?></th>
                                                            <th class="text-center"><?= t('actions') ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td colspan="7" class="text-center text-muted py-4"><?= t('loading') ?>...</td>
                                                        </tr>
                                                    </tbody>
                        </table>
                        </div>
                        <div id="usersPagination" class="mt-3"></div>
                    </div>
                                        
                                        <!-- Appointments Tab -->
                                        <div class="tab-pane fade" id="appointmentsTab" role="tabpanel">
                                            <div class="row g-3 mb-3">
                                                <div class="col-md-3">
                                                    <label class="form-label mb-1">Αναζήτηση</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                                        <input type="search" id="appointment_search" class="form-control" 
                                                               placeholder="Αναζήτηση πελάτη, ΑΦΜ, οχήματος...">
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label mb-1">Κατάσταση</label>
                                                    <select id="appointment_status_filter" class="form-select">
                                                        <option value="">Όλες</option>
                                                        <option value="ΔΗΜΙΟΥΡΓΗΜΕΝΟ">ΔΗΜΙΟΥΡΓΗΜΕΝΟ</option>
                                                        <option value="ΣΕ ΕΞΕΛΙΞΗ">ΣΕ ΕΞΕΛΙΞΗ</option>
                                                        <option value="ΠΕΡΑΙΩΜΕΝΟ">ΠΕΡΑΙΩΜΕΝΟ</option>
                                                        <option value="ΑΚΥΡΩΜΕΝΟ">ΑΚΥΡΩΜΕΝΟ</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label mb-1">Από</label>
                                                    <input type="date" id="date_from_filter" class="form-control">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label mb-1">Έως</label>
                                                    <input type="date" id="date_to_filter" class="form-control">
                                                </div>
                                                <div class="col-md-2 d-flex align-items-end">
                                                    <button class="btn btn-success w-100" onclick="showNewAppointmentModal()" id="appointmentModalBtn">
                                                        <i class="fa-solid fa-plus me-1"></i>Νέο Ραντεβού
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="table-responsive" id="appointmentsTable">
                                                <table class="table table-hover mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>#</th>
                                                            <th>Πελάτης</th>
                                                            <th>Όχημα</th>
                                                            <th>Ημερομηνία</th>
                                                            <th>Ώρα</th>
                                                            <th>Κατάσταση</th>
                                                            <th>Λόγος</th>
                                                            <th class="text-center">Ενέργειες</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td colspan="8" class="text-center text-muted py-4">Φόρτωση...</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div id="appointmentsPagination" class="mt-3"></div>
                                        </div>
                                        
                                        <!-- Vehicles Tab -->
                                        <div class="tab-pane fade" id="vehiclesTab" role="tabpanel">
                                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                                <div class="d-flex align-items-center gap-2 flex-wrap flex-grow-1">
                                                    <div class="input-group" style="min-width: 200px; max-width: 320px;">
                                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                                        <input type="search" id="vehicle_search" class="form-control" 
                                                               placeholder="Αναζήτηση οχημάτων...">
                                                    </div>
                                                    <select id="vehicle_filter" class="form-select" style="min-width: 150px; max-width: 260px;">
                                                        <option value="all">Όλα τα οχήματα</option>
                                                        <option value="brand:asc">Μάρκα A-Z</option>
                                                        <option value="brand:desc">Μάρκα Z-A</option>
                                                        <option value="year:desc">Νεότερα πρώτα</option>
                                                        <option value="year:asc">Παλαιότερα πρώτα</option>
                                                        <option value="customer:asc">Ιδιοκτήτης A-Z</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="mt-2">
                                                <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importVehiclesModal" title="<?= t('import_vehicles') ?>">
                                                    <i class="bi bi-upload me-1"></i> <?= t('import_csv') ?>
                                                </button>
                                            </div>
                                            <div class="table-responsive" id="vehiclesTable">
                                                <table class="table table-hover mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>#</th>
                                                            <th>Όχημα</th>
                                                            <th>Αρ. Κυκλοφορίας</th>
                                                            <th>Ιδιοκτήτης</th>
                                                            <th>Έτος Κατασκευής</th>
                                                            <th class="text-center">Ενέργειες</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td colspan="6" class="text-center text-muted py-4">Φόρτωση...</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                                </div>
                                            <div id="vehiclesPagination" class="mt-3"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- System Status and Notifications -->
                    
                    
                   
                <!-- end management tabs -->
            </div>
        </div>
    </main>

    <!-- Secretary Management Modals -->
    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content create-modal">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addUserModalLabel"><i class="fa-solid fa-user-plus me-2"></i>Δημιουργία Χρήστη</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addUserForm" method="POST" action="javascript:void(0)" novalidate>
                    <!-- javascript:void(0) is used to prevent the form from submitting and refreshing the page, its a safety net -->
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Όνομα *</label>
                                <input type="text" name="first_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Επώνυμο *</label>
                                <input type="text" name="last_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Username *</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Κωδικός *</label>
                                <input type="password" name="password" class="form-control" required minlength="6">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Αρ. Ταυτότητας (ΑΤ) *</label>
                                <input type="text" name="id_number" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Ρόλος *</label>
                                <select name="role" id="role" class="form-select" required>
                                    <option value="">Επιλέξτε</option>
                                    <option value="customer">Πελάτης</option>
                                    <option value="mechanic">Μηχανικός</option>
                                </select>
                            </div>
                            <div class="col-md-6" id="specialty_field" style="display:none;">
                                <label class="form-label">Ειδικότητα (για Μηχανικό)</label>
                                <input type="text" name="specialty" class="form-control">
                            </div>
                            <div class="col-md-6" id="afm_field" style="display:none;">
                                <label class="form-label">ΑΦΜ (για Πελάτη)</label>
                                <input type="text" name="afm" class="form-control">
                            </div>
                            <div class="col-md-6" id="address_field" style="display:none;">
                                <label class="form-label">Διεύθυνση (για Πελάτη)</label>
                                <input type="text" name="address" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Ακύρωση</button>
                        <button type="submit" class="btn btn-primary">Δημιουργία</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Generic Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editUserModalLabel">
                        <i class="fa-solid fa-user-edit me-2"></i>Επεξεργασία Χρήστη
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editUserForm" method="POST" action="javascript:void(0)" novalidate>
                    <div class="modal-body">
                        <!-- Loading state -->
                        <div id="editUserLoading" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Φόρτωση...</span>
                            </div>
                            <p class="mt-2 text-muted">Φόρτωση στοιχείων χρήστη...</p>
                        </div>
                        
                        <!-- Form content (hidden initially) -->
                        <div id="editUserContent" style="display: none;">
                            <input type="hidden" name="user_id" id="edit_user_id">
                            <input type="hidden" name="user_role" id="edit_user_role">
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="edit_first_name" class="form-label">Όνομα *</label>
                                    <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="edit_last_name" class="form-label">Επώνυμο *</label>
                                    <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="edit_username" class="form-label">Όνομα Χρήστη *</label>
                                    <input type="text" class="form-control" id="edit_username" name="username" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="edit_email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="edit_email" name="email" required>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Ρόλος</label>
                                    <input type="text" class="form-control" id="edit_role_display" disabled>
                                    <small class="form-text text-muted">Ο ρόλος δεν μπορεί να αλλάξει</small>
                                </div>
                                
                                <!-- Customer-specific fields -->
                                <div id="edit_customer_fields" style="display: none;">
                                    <hr class="my-4">
                                    <h6 class="mb-3"><i class="fa-solid fa-user me-2"></i>Στοιχεία Πελάτη</h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="edit_afm" class="form-label">ΑΦΜ *</label>
                                            <input type="text" class="form-control" id="edit_afm" name="afm">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="edit_address" class="form-label">Διεύθυνση *</label>
                                            <input type="text" class="form-control" id="edit_address" name="address">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Mechanic-specific fields -->
                                <div id="edit_mechanic_fields" style="display: none;">
                                    <hr class="my-4">
                                    <h6 class="mb-3"><i class="fa-solid fa-wrench me-2"></i>Στοιχεία Μηχανικού</h6>
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label for="edit_specialty" class="form-label">Ειδικότητα *</label>
                                            <input type="text" class="form-control" id="edit_specialty" name="specialty">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Ακύρωση</button>
                        <button type="submit" class="btn btn-primary" id="editUserSubmitBtn" style="display: none;">
                            <i class="fa-solid fa-save me-2"></i>Αποθήκευση Αλλαγών
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- New Appointment Modal (Secretary) -->
    <div class="modal fade" id="newAppointmentModal" tabindex="-1" aria-labelledby="newAppointmentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="" id="secretaryAppointmentForm">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="newAppointmentModalLabel">
                            <i class="fa-solid fa-calendar-plus me-2"></i>Δημιουργία Νέου Ραντεβού
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fa-solid fa-info-circle me-2"></i>
                            Ωράριο λειτουργίας: 08:00 - 16:00 (κάθε ραντεβού διαρκεί 2 ώρες)
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="secretary_customer_id" class="form-label">Πελάτης *</label>
                                <select class="form-select" id="secretary_customer_id" name="customer_id" required onchange="loadCustomerVehicles()">
                                    <option value="">Επιλέξτε Πελάτη</option>
                                    <?php foreach ($allCustomers as $customer): ?>
                                        <option value="<?= $customer['id'] ?>">
                                            <?= htmlspecialchars($customer['full_name']) ?> (<?= htmlspecialchars($customer['email']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text" id="selectedCustomerName" style="display:none"></div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="secretary_vehicle_id" class="form-label">Όχημα *</label>
                                <select class="form-select" id="secretary_vehicle_id" name="vehicle_id" required>
                                    <option value="">Επιλέξτε πρώτα πελάτη</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="secretary_appointment_reason" class="form-label">Λόγος Ραντεβού *</label>
                                <select class="form-select" id="secretary_appointment_reason" name="reason" required onchange="toggleSecretaryProblemDescription()">
                                    <option value="">Επιλέξτε Λόγο</option>
                                    <option value="σέρβις">Σέρβις</option>
                                    <option value="επιδιόρθωση">Επιδιόρθωση</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="secretary_appointment_date" class="form-label">Ημερομηνία Ραντεβού *</label>
                                <input type="date" class="form-control" id="secretary_appointment_date" name="appointment_date" 
                                       min="<?= date('Y-m-d') ?>" required onchange="updateSecretaryTimeSlots()">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="secretary_appointment_time" class="form-label">Ώρα Ραντεβού *</label>
                                <select class="form-select" id="secretary_appointment_time" name="appointment_time" required>
                                    <option value="">Επιλέξτε ημερομηνία πρώτα</option>
                                </select>
                            </div>
                        </div>
                        
                        <div id="secretaryProblemDescriptionGroup" class="mb-3" style="display: none;">
                            <label for="secretary_problem_description" class="form-label">Περιγραφή Προβλήματος *</label>
                            <textarea class="form-control" id="secretary_problem_description" name="problem_description" rows="3" 
                                      placeholder="Η περιγραφή προβλήματος είναι υποχρεωτική για επιδιορθώσεις"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Ακύρωση</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fa-solid fa-calendar-check me-2"></i>Δημιουργία Ραντεβού
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Profile Modal (shared component) -->
    <?php DashboardComponents::renderEditProfileModal($currentUser); ?>

    <!-- View Appointment Modal -->
    <?php DashboardComponents::renderViewAppointmentModal($currentRole); ?>

    <!-- View Vehicle Modal -->
    <?php DashboardComponents::renderViewVehicleModal(); ?>

    <!-- Individual Edit User Modals (generated for each user) -->
    <?php if (!empty($allUsers)): ?>
        <?php foreach ($allUsers as $user): ?>
        <!-- Edit User Modal for User <?= $user['id'] ?> -->
        <div class="modal fade" id="editUserModal<?= $user['id'] ?>" tabindex="-1" aria-labelledby="editUserModalLabel<?= $user['id'] ?>" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="editUserModalLabel<?= $user['id'] ?>">
                            <i class="fa-solid fa-user-edit me-2"></i>Επεξεργασία Χρήστη: <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="javascript:void(0)" onsubmit="return false;" novalidate>
                        <div class="modal-body">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <input type="hidden" name="user_role" value="<?= $user['actual_role'] ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name_<?= $user['id'] ?>" class="form-label">Όνομα *</label>
                                    <input type="text" class="form-control" id="first_name_<?= $user['id'] ?>" name="first_name" 
                                           value="<?= htmlspecialchars($user['first_name']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name_<?= $user['id'] ?>" class="form-label">Επώνυμο *</label>
                                    <input type="text" class="form-control" id="last_name_<?= $user['id'] ?>" name="last_name" 
                                           value="<?= htmlspecialchars($user['last_name']) ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="username_<?= $user['id'] ?>" class="form-label">Όνομα Χρήστη *</label>
                                    <input type="text" class="form-control" id="username_<?= $user['id'] ?>" name="username" 
                                           value="<?= htmlspecialchars($user['username']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email_<?= $user['id'] ?>" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email_<?= $user['id'] ?>" name="email" 
                                           value="<?= htmlspecialchars($user['email']) ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Ρόλος</label>
                                <input type="text" class="form-control" value="<?= ucfirst($user['actual_role']) ?>" disabled>
                                <small class="form-text text-muted">Ο ρόλος δεν μπορεί να αλλάξει</small>
                            </div>

                            <!-- Role-specific fields -->
                            <?php if ($user['actual_role'] === 'customer'): ?>
                            <hr class="my-4">
                            <h6 class="mb-3"><i class="fa-solid fa-user me-2"></i>Στοιχεία Πελάτη</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="afm_<?= $user['id'] ?>" class="form-label">ΑΦΜ *</label>
                                    <input type="text" class="form-control" id="afm_<?= $user['id'] ?>" name="afm" 
                                           value="<?= htmlspecialchars($user['afm'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="address_<?= $user['id'] ?>" class="form-label">Διεύθυνση *</label>
                                    <input type="text" class="form-control" id="address_<?= $user['id'] ?>" name="address" 
                                           value="<?= htmlspecialchars($user['address'] ?? '') ?>" required>
                                </div>
                            </div>
                            <?php elseif ($user['actual_role'] === 'mechanic'): ?>
                            <hr class="my-4">
                            <h6 class="mb-3"><i class="fa-solid fa-wrench me-2"></i>Στοιχεία Μηχανικού</h6>
                            <div class="mb-3">
                                <label for="specialty_<?= $user['id'] ?>" class="form-label">Ειδικότητα *</label>
                                <input type="text" class="form-control" id="specialty_<?= $user['id'] ?>" name="specialty" 
                                       value="<?= htmlspecialchars($user['specialty'] ?? '') ?>" required>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Ακύρωση</button>
                            <button type="submit" name="update_user_details" class="btn btn-primary">
                                <i class="fa-solid fa-save me-2"></i>Αποθήκευση Αλλαγών
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- View User Details Modal for User <?= $user['id'] ?> -->
        <div class="modal fade" id="viewUserModal<?= $user['id'] ?>" tabindex="-1" aria-labelledby="viewUserModalLabel<?= $user['id'] ?>" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title" id="viewUserModalLabel<?= $user['id'] ?>">
                            <i class="fa-solid fa-user me-2"></i>Λεπτομέρειες Χρήστη: <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <strong>ID Χρήστη:</strong><br>
                                <span class="text-muted"><?= htmlspecialchars($user['id']) ?></span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Όνομα Χρήστη:</strong><br>
                                <span class="text-muted"><?= htmlspecialchars($user['username']) ?></span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Ονοματεπώνυμο:</strong><br>
                                <span class="text-muted"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Email:</strong><br>
                                <span class="text-muted"><?= htmlspecialchars($user['email']) ?></span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Ρόλος:</strong><br>
                                <span class="badge bg-<?= $user['actual_role'] === 'customer' ? 'primary' : ($user['actual_role'] === 'mechanic' ? 'warning' : 'success') ?>">
                                    <?= ucfirst($user['actual_role']) ?>
                                </span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Κατάσταση:</strong><br>
                                <span class="badge bg-<?= $user['is_active'] ? 'success' : 'danger' ?>">
                                    <?= $user['is_active'] ? 'Ενεργός' : 'Ανενεργός' ?>
                                </span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Ημερομηνία Εγγραφής:</strong><br>
                                <span class="text-muted"><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></span>
                            </div>
                            
                            <!-- Role-specific information -->
                            <?php if ($user['actual_role'] === 'customer'): ?>
                            <div class="col-12"><hr class="my-4"></div>
                            <div class="col-12 mb-3">
                                <h6><i class="fa-solid fa-user me-2"></i>Στοιχεία Πελάτη</h6>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>ΑΦΜ:</strong><br>
                                <span class="text-muted"><?= htmlspecialchars($user['afm'] ?? 'Δεν έχει οριστεί') ?></span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Διεύθυνση:</strong><br>
                                <span class="text-muted"><?= htmlspecialchars($user['address'] ?? 'Δεν έχει οριστεί') ?></span>
                            </div>
                            <?php elseif ($user['actual_role'] === 'mechanic'): ?>
                            <div class="col-12"><hr class="my-4"></div>
                            <div class="col-12 mb-3">
                                <h6><i class="fa-solid fa-wrench me-2"></i>Στοιχεία Μηχανικού</h6>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Ειδικότητα:</strong><br>
                                <span class="text-muted"><?= htmlspecialchars($user['specialty'] ?? 'Δεν έχει οριστεί') ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Show related data counts -->
                            <?php if ($user['actual_role'] === 'customer'): ?>
                            <div class="col-12"><hr class="my-4"></div>
                            <div class="col-12 mb-3">
                                <h6><i class="fa-solid fa-chart-bar me-2"></i>Στατιστικά</h6>
                            </div>
                            <?php
                            // Get customer's vehicle and appointment counts
                            $stmt = $db->prepare("
                                SELECT 
                                    (SELECT COUNT(*) FROM vehicles v JOIN customers c ON v.customer_id = c.id WHERE c.user_id = ?) as vehicle_count,
                                    (SELECT COUNT(*) FROM appointments a JOIN customers c ON a.customer_id = c.id WHERE c.user_id = ?) as appointment_count
                            ");
                            $stmt->execute([$user['id'], $user['id']]);
                            $userStats = $stmt->fetch(PDO::FETCH_ASSOC);
                            ?>
                            <div class="col-md-6 mb-3">
                                <strong>Οχήματα:</strong><br>
                                <span class="text-muted"><?= $userStats['vehicle_count'] ?? 0 ?> οχήματα</span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Ραντεβού:</strong><br>
                                <span class="text-muted"><?= $userStats['appointment_count'] ?? 0 ?> ραντεβού</span>
                            </div>
                            <?php elseif ($user['actual_role'] === 'mechanic'): ?>
                            <div class="col-12"><hr class="my-4"></div>
                            <div class="col-12 mb-3">
                                <h6><i class="fa-solid fa-chart-bar me-2"></i>Στατιστικά</h6>
                            </div>
                            <?php
                            // Get mechanic's appointment count
                            $stmt = $db->prepare("
                                SELECT COUNT(*) as appointment_count 
                                FROM appointments a 
                                JOIN mechanics m ON a.mechanic_id = m.id 
                                WHERE m.user_id = ?
                            ");
                            $stmt->execute([$user['id']]);
                            $mechanicStats = $stmt->fetch(PDO::FETCH_ASSOC);
                            ?>
                            <div class="col-md-6 mb-3">
                                <strong>Ραντεβού:</strong><br>
                                <span class="text-muted"><?= $mechanicStats['appointment_count'] ?? 0 ?> ραντεβού</span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Κλείσιμο</button>
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $user['id'] ?>">
                            <i class="fa-solid fa-edit me-2"></i>Επεξεργασία
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <?php renderFooter($lang); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/dashboard-common.js"></script>
    <script src="assets/js/forms.js"></script>
    <script src="assets/js/secretary-dashboard.js"></script>
    <script>
        // Import modals markup handlers will be initialized in forms.js
    </script>
    <script>
        // Expose CSRF token to JavaScript
        window.csrfToken = '<?php echo \SecurityConfig::generateCSRFToken(); ?>';
    </script>
    <script>
    // Bind Add User form to AJAX
    document.addEventListener('DOMContentLoaded', function() {
        const addUserForm = document.getElementById('addUserForm');
        if (addUserForm) {
            initUserForm('addUserForm', {
                onSuccess: function() {
                    refreshUsersTable();
                }
            });
        }
        
        // Initialize the generic edit user form
        const editUserForm = document.getElementById('editUserForm');
        if (editUserForm) {
            if (window.__APP_DEBUG__) console.log('Generic edit user modal initialized');
        }
    });
    </script>
    
    <script>
    // Secretary appointment booking JavaScript functionality
    function showNewAppointmentModal() {
        try {
            const modalEl = document.getElementById('newAppointmentModal');
            if (!modalEl) {
                showAlert('Δεν βρέθηκε το modal ραντεβού', 'warning');
                return;
            }
            
            // First try to hide any existing modals
            const existingModals = document.querySelectorAll('.modal.show');
            existingModals.forEach(modal => {
                const instance = bootstrap.Modal.getInstance(modal);
                if (instance) instance.hide();
            });
            
            // Show the modal
            const modal = new bootstrap.Modal(modalEl, {
                backdrop: 'static',
                keyboard: false
            });
            modal.show();
            // Force re-parent to body if necessary to avoid backdrop-only gray screen
            if (modalEl.parentNode !== document.body) {
                document.body.appendChild(modalEl);
                try { bootstrap.Modal.getOrCreateInstance(modalEl).show(); } catch (e) {}
            }
        } catch (error) {
            showAlert('Σφάλμα κατά την εμφάνιση του modal. Παρακαλώ ανανεώστε τη σελίδα.', 'danger');
        }
    }
    
    function setSecretaryCustomer(customerId, customerName) {
        try {
            const select = document.getElementById('secretary_customer_id');
            if (select) {
                select.value = String(customerId);
                loadCustomerVehicles();
            }
            const helper = document.getElementById('selectedCustomerName');
            if (helper) {
                helper.style.display = 'block';
                helper.textContent = 'Επιλεγμένος πελάτης: ' + (customerName || customerId);
            }
        } catch (e) {
            console.error('Failed to set secretary customer', e);
        }
    }
    function toggleSecretaryProblemDescription() {
        const reasonSelect = document.getElementById('secretary_appointment_reason');
        const problemGroup = document.getElementById('secretaryProblemDescriptionGroup');
        const problemTextarea = document.getElementById('secretary_problem_description');
        
        if (reasonSelect.value === 'επιδιόρθωση') {
            problemGroup.style.display = 'block';
            problemTextarea.required = true;
        } else {
            problemGroup.style.display = 'none';
            problemTextarea.required = false;
            problemTextarea.value = '';
        }
    }
    
    
    </script>

    
    <script>
    (function() {
        // Expose CSRF token safely
        try {
            window.csrfToken = '<?php echo addslashes(\SecurityConfig::generateCSRFToken()); ?>';
        } catch (e) {
            window.csrfToken = '';
        }

        
        <?php if (!\AppConfig::isDebugMode()): ?>
        (function() {
            var noop = function() {};
            ['log','info','warn','debug','error'].forEach(function(m){ if (!console[m]) console[m]=noop; else console[m]=noop; });
        })();
        <?php endif; ?>
    })();
    </script>
</body>
</html>

<!-- Import Users CSV Modal -->
<div class="modal fade" id="importUsersModal" tabindex="-1" aria-labelledby="importUsersModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title" id="importUsersModalLabel"><i class="bi bi-upload me-2"></i> <?= t('import_users') ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="importUsersForm" action="javascript:void(0)" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <p class="small text-muted"><?= t('import_csv_help') ?></p>
                    <div class="mb-3">
                        <input type="file" accept=".csv,text/csv" name="csv_file" id="importUsersFile" class="form-control" required>
                    </div>
                    <div id="importUsersResult" class="mt-2"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('close') ?></button>
                    <button type="submit" class="btn btn-primary"><?= t('import') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Import Vehicles CSV Modal -->
<div class="modal fade" id="importVehiclesModal" tabindex="-1" aria-labelledby="importVehiclesModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title" id="importVehiclesModalLabel"><i class="bi bi-upload me-2"></i> <?= t('import_vehicles') ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="importVehiclesForm" action="javascript:void(0)" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <p class="small text-muted"><?= t('import_csv_help') ?></p>
                    <div class="mb-3">
                        <input type="file" accept=".csv,text/csv" name="csv_file" id="importVehiclesFile" class="form-control" required>
                    </div>
                    <div id="importVehiclesResult" class="mt-2"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('close') ?></button>
                    <button type="submit" class="btn btn-primary"><?= t('import') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>