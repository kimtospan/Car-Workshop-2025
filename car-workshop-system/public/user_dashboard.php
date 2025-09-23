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
require_once '../src/models/User.php';
require_once '../src/utils/Utils.php';
// Handle logout first
if (isset($_GET['logout'])) {
    $auth->logout();
    header("Location: index.php");
    exit;
}

// Ensure user is customer
$auth->requireLogin();
$currentUser = $auth->getCurrentUser();
$currentRole = $currentUser['role'];

if ($currentRole !== 'customer') {
    header("Location: dashboard.php");
    exit;
}

// Get database connection for customer dashboard
$db = Database::getInstance()->getConnection();

// Get customer ID from user ID (required for vehicle creation)
require_once '../src/utils/Utils.php';
$customerId = getCustomerIdFromUserId((int)$currentUser['id']);
if (!$customerId) {
    $error_message = "<?= t('customer_not_found') ?>";
}

// Initialize form processor
$formProcessor = new FormProcessor($db, $auth);

// Initialize message variables
$success_message = '';
$error_message = '';
$validation_errors = [];

// Initialize customer data arrays
$customerVehicles = [];
$customerStats = [];
$upcomingAppointment = null;
try {
    // Load basic customer data using model methods
    $carModel = new Car();
    $appointmentModel = new Appointment();
    $userModel = new User();

    // Get customer's vehicles for form dropdowns (basic data only)
    try {
        $customerVehicles = $carModel->getCustomerVehicles($customerId);
    } catch (Exception $e) {
        die('Error in getCustomerVehicles: ' . $e->getMessage());
    }

    // Get upcoming appointment for customer.
    // Fetch upcoming appointment for alert
    
    try {
        // Get upcoming appointment for alert
    // Join customers and filter by customers.user_id = current user id.
    $stmt = $db->prepare(
        "SELECT a.*, v.brand, v.model,
               CONCAT(u.first_name, ' ', u.last_name) as mechanic_name,
               DATEDIFF(a.appointment_date, CURDATE()) as days_until
        FROM appointments a
        LEFT JOIN customers c ON a.customer_id = c.id
        LEFT JOIN vehicles v ON a.vehicle_id = v.id
        LEFT JOIN mechanics m ON a.mechanic_id = m.id
        LEFT JOIN users u ON m.user_id = u.id
        WHERE c.user_id = ?
        AND a.appointment_date >= CURDATE()
        AND a.status IN ('ΔΗΜΙΟΥΡΓΗΜΕΝΟ', 'ΣΕ ΕΞΕΛΙΞΗ')
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 1"
    );
    $stmt->execute([$currentUser['id']]);
    $upcomingAppointment = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log('Error fetching upcoming appointment: ' . $e->getMessage());
    }

    if (!$upcomingAppointment) {

        $error_message = "Δεν βρέθηκε προγραμματισμένο ραντεβού για τον χρήστη";
    }

} catch (Exception $e) {
    $error_message = "Σφάλμα φόρτωσης δεδομένων: " . $e->getMessage();
}



?>
<!DOCTYPE html>
<html lang="<?= $lang->getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('site_title') ?> - <?= t('customer') ?> <?= t('dashboard') ?></title>
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

            
                <!-- Main Dashboard Content -->
                <?php if ($upcomingAppointment): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="alert alert-info alert-dismissible fade show border-0 shadow-sm" role="alert">
                            <div class="d-flex align-items-center">
                                <i class="fa-solid fa-calendar-check fa-2x me-3 text-info"></i>
                                <div>
                                    <h5 class="mb-1"><?= t('upcoming_appointment') ?></h5>
                                    <p class="mb-0">
                                        <?= t('appointment_scheduled_for') ?> <strong><?= Utils::formatDate($upcomingAppointment['appointment_date']) ?></strong>
                                        <?php if ($upcomingAppointment['days_until'] == 0): ?>
                                            <span class="badge bg-warning text-dark"><?= t('today') ?></span>
                                        <?php elseif ($upcomingAppointment['days_until'] == 1): ?>
                                            <span class="badge bg-info"><?= t('tomorrow') ?></span>
                                        <?php else: ?>
                                            σε <strong><?= $upcomingAppointment['days_until'] ?> <?= t('days') ?></strong>
                                        <?php endif; ?>
                                        στις <strong><?= $upcomingAppointment['appointment_time'] ?></strong>
                                        <?= t('for_vehicle') ?> <strong><?= Utils::escape($upcomingAppointment['brand'] . ' ' . $upcomingAppointment['model']) ?></strong>
                                        <?php if ($upcomingAppointment['mechanic_name']): ?>
                                            <?= t('with_mechanic') ?> <strong><?= Utils::escape($upcomingAppointment['mechanic_name']) ?></strong>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="alert alert-secondary alert-dismissible fade show border-0 shadow-sm" role="alert">
                            <div class="d-flex align-items-center">
                                <i class="fa-solid fa-calendar-plus fa-2x me-3 text-secondary"></i>
                                <div>
                                    <h5 class="mb-1"><?= t('no_upcoming_appointment') ?></h5>
                                    <p class="mb-0">
                                        <?= t('can_book_new_appointment') ?>
                                    </p>
                                </div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>


                
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm rounded-3">
                            <div class="card-header bg-transparent border-0 py-3">
                                <ul class="nav nav-tabs card-header-tabs" id="customerTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="appointments-tab" data-bs-toggle="tab" data-bs-target="#appointmentsTab" type="button" role="tab">
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
                                <div class="tab-content" id="customerTabContent">


                                    <!-- Appointments Tab -->
                                    <div class="tab-pane fade show active" id="appointmentsTab" role="tabpanel">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div class="d-flex align-items-center gap-2 flex-nowrap">
                                                <div class="input-group" style="max-width: 320px;">
                                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                                    <input type="search" id="appointment_search" class="form-control" placeholder="<?= t('search') ?> <?= t('appointments') ?>...">
                                                </div>
                                                
                                                
                                                <!-- Customer-specific quick filters: status + date range -->
                                                <select id="appointment_status_filter" class="form-select ms-2" style="max-width: 220px;">
                                                    <option value=""><?= t('all_statuses') ?></option>
                                                    <option value="ΔΗΜΙΟΥΡΓΗΜΕΝΟ"><?= t('appointment_status_created') ?></option>
                                                    <option value="ΣΕ ΕΞΕΛΙΞΗ"><?= t('appointment_status_in_progress') ?></option>
                                                    <option value="ΠΕΡΑΙΩΜΕΝΟ"><?= t('appointment_status_completed') ?></option>
                                                    <option value="ΑΚΥΡΩΜΕΝΟ"><?= t('appointment_status_cancelled') ?></option>
                                                </select>
                                                <div class="d-flex align-items-center ms-2" style="gap:.5rem">
                                                    <input type="date" id="date_from_filter" class="form-control form-control-sm" style="max-width:140px" placeholder="<?= t('from') ?>">
                                                    <input type="date" id="date_to_filter" class="form-control form-control-sm" style="max-width:140px" placeholder="<?= t('to') ?>">
                                                </div>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <button class="btn btn-primary" onclick="showNewAppointmentModal()">
                                                    <i class="bi bi-plus-lg me-1"></i> <?= t('new_appointment') ?>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="table-responsive" id="appointmentsTable">
                                            <table class="table table-hover mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>#</th>
                                                        <th><?= t('customer') ?></th>
                                                        <th><?= t('vehicle') ?></th>
                                                        <th><?= t('date') ?></th>
                                                        <th><?= t('time') ?></th>
                                                        <th><?= t('status') ?></th>
                                                        <th><?= t('appointment_reason') ?></th>
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
                                    </div>
                                    
                                    <!-- Vehicles Tab -->
                                    <div class="tab-pane fade" id="vehiclesTab" role="tabpanel">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div class="d-flex align-items-center gap-2 flex-nowrap">
                                                <div class="input-group" style="max-width: 320px;">
                                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                                    <input type="search" id="vehicle_search" class="form-control" 
                                                           placeholder="<?= t('search') ?> <?= t('vehicles') ?>...">
                                                </div>
                                                <select id="vehicle_filter" class="form-select" style="max-width: 260px;">
                                                    <option value="all"><?= t('all_vehicles') ?></option>
                                                    <option value="brand:asc"><?= t('brand_a_z') ?></option>
                                                    <option value="brand:desc"><?= t('brand_z_a') ?></option>
                                                    
                                                </select>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <button class="btn btn-success" onclick="showNewVehicleModal()">
                                                    <i class="bi bi-plus-lg me-1"></i> <?= t('new_vehicle') ?>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="table-responsive" id="vehiclesTable">
                                            <table class="table table-hover mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>#</th>
                                                            <th><?= t('vehicle') ?></th>
                                                            <th><?= t('license_plate') ?></th>
                                                            <th><?= t('owner') ?></th>
                                                            <th><?= t('manufacturing_year') ?></th>
                                                        <th class="text-center"><?= t('actions') ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td colspan="5" class="text-center text-muted py-4"><?= t('loading') ?>...</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <div class="col-12">

        </div>
    </main>

                
    
    
    <div class="modal fade" id="newAppointmentModal" tabindex="-1" aria-labelledby="newAppointmentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="" id="customerAppointmentForm">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="newAppointmentModalLabel">
                            <i class="fa-solid fa-calendar-plus me-2"></i><?= t('book_appointment') ?>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fa-solid fa-info-circle me-2"></i>
                            <?= t('working_hours_info') ?>
                        </div>
                        
                        <!-- Hidden customer ID field since this is for the current customer -->
                        <input type="hidden" name="customer_id" value="<?= $customerId ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="customer_vehicle_id" class="form-label"><?= t('vehicle') ?> *</label>
                                <select class="form-select" id="customer_vehicle_id" name="vehicle_id" required>
                                    <option value=""><?= t('select_vehicle') ?></option>
                                    <?php foreach ($customerVehicles as $vehicle): ?>
                                        <option value="<?= $vehicle['id'] ?>">
                                            <?= htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model'] . ' (' . $vehicle['serial_number'] . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="customer_appointment_reason" class="form-label"><?= t('appointment_reason') ?> *</label>
                                <select class="form-select" id="customer_appointment_reason" name="reason" required>
                                    <option value=""><?= t('select_reason') ?></option>
                                    <option value="σέρβις"><?= t('service') ?></option>
                                    <option value="επιδιόρθωση"><?= t('repair') ?></option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="customer_appointment_date" class="form-label"><?= t('appointment_date') ?> *</label>
                                <input type="date" class="form-control" id="customer_appointment_date" name="appointment_date" 
                                       min="<?= date('Y-m-d') ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="customer_appointment_time" class="form-label"><?= t('appointment_time') ?> *</label>
                                <select class="form-select" id="customer_appointment_time" name="appointment_time" required>
                                    <option value=""><?= t('select_date_first') ?></option>
                                </select>
                            </div>
                        </div>
                        
                        <div id="customerProblemDescriptionGroup" class="mb-3" style="display: none;">
                            <label for="customer_problem_description" class="form-label"><?= t('problem_description') ?> *</label>
                            <textarea class="form-control" id="customer_problem_description" name="problem_description" rows="3" 
                                      placeholder="<?= t('problem_description_required') ?>"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                        <button type="submit" class="btn btn-success">
                            <i class="fa-solid fa-calendar-check me-2"></i><?= t('book_appointment') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- New Vehicle Modal (Customer) -->
    <div class="modal fade" id="newVehicleModal" tabindex="-1" aria-labelledby="newVehicleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <!-- AJAX-only vehicle form: handled by forms.js -->
                <form id="customerVehicleForm" method="POST" data-ajax="1">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="newVehicleModalLabel">
                            <i class="fa-solid fa-car me-2"></i><?= t('add_vehicle') ?>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="customer_id" value="<?= $customerId ?>">
                        
                        <!-- Basic Information -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="vehicle_brand" class="form-label"><?= t('vehicle_brand') ?> *</label>
                                <input type="text" class="form-control" id="vehicle_brand" name="brand" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="vehicle_model" class="form-label"><?= t('vehicle_model') ?> *</label>
                                <input type="text" class="form-control" id="vehicle_model" name="model" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="vehicle_serial_number" class="form-label"><?= t('license_plate') ?> *</label>
                                <input type="text" class="form-control" id="vehicle_serial_number" name="serial_number" required>
                                       
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="vehicle_type" class="form-label"><?= t('vehicle_type') ?> *</label>
                                <select class="form-select" id="vehicle_type" name="vehicle_type" required>
                                    <option value=""><?= t('select_type') ?></option>
                                    <?php foreach (array_keys(AppConfig::VEHICLE_TYPES) as $type): ?>
                                        <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="engine_type" class="form-label"><?= t('engine_type') ?> *</label>
                                <select class="form-select" id="engine_type" name="engine_type" required>
                                    <option value=""><?= t('select_type') ?></option>
                                    <?php foreach (array_keys(AppConfig::ENGINE_TYPES) as $type): ?>
                                        <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="acquisition_year" class="form-label"><?= t('acquisition_year') ?> *</label>
                                <input type="number" class="form-control" id="acquisition_year" name="acquisition_year" 
                                       min="1900" max="<?= date('Y') ?>" value="<?= date('Y') ?>" required>
                            </div>
                        </div>

                        <!-- Technical Details -->
                        <hr>
                        <h6 class="text-muted mb-3"><?= t('technical_specifications') ?></h6>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="door_count" class="form-label"><?= t('door_count') ?> *</label>
                                <select class="form-select" id="door_count" name="door_count" required>
                                    <option value=""><?= t('select_option') ?></option>
                                    <option value="1">1 <?= t('doors') ?></option>
                                    <option value="2">2 <?= t('doors') ?></option>
                                    <option value="3">3 <?= t('doors') ?></option>
                                    <option value="4">4 <?= t('doors') ?></option>
                                    <option value="5">5 <?= t('doors') ?></option>
                                    <option value="6">6 <?= t('doors') ?></option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="wheel_count" class="form-label"><?= t('wheel_count') ?> *</label>
                                <select class="form-select" id="wheel_count" name="wheel_count" required>
                                    <option value=""><?= t('select_option') ?></option>
                                    
                                    <option value="3">3 <?= t('wheels') ?></option>
                                    <option value="4">4 <?= t('wheels') ?></option>
                                    <option value="5">5 <?= t('wheels') ?></option>
                                    <option value="6">6 <?= t('wheels') ?></option>
                                    <option value="8">8 <?= t('wheels') ?></option>
                                    <option value="10">10 <?= t('wheels') ?></option>
                                    <option value="12">12 <?= t('wheels') ?></option>
                                    <option value="18">18 <?= t('wheels') ?></option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="production_date" class="form-label"><?= t('production_date') ?> *</label>
                                <input type="date" class="form-control" id="production_date" name="production_date" required 
                                       max="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                        <button type="submit" class="btn btn-success">
                            <i class="fa-solid fa-save me-2"></i><?= t('add_vehicle') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <?php DashboardComponents::renderEditProfileModal($currentUser); ?>

    <!-- View Appointment Modal -->
    <?php DashboardComponents::renderViewAppointmentModal($currentRole); ?>

    <!-- View Vehicle Modal -->
    <?php DashboardComponents::renderViewVehicleModal(); ?>

    <?php renderFooter($lang); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/dashboard-common.js"></script>
    <script src="assets/js/forms.js"></script>
    <script src="assets/js/customer-dashboard.js"></script>
    <script>
        // Expose CSRF token to JavaScript
        window.csrfToken = '<?php echo \SecurityConfig::generateCSRFToken(); ?>';
    </script>
    <script>
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
        } catch (error) {
            showAlert('Σφάλμα κατά την εμφάνιση του modal. Παρακαλώ ανανεώστε τη σελίδα.', 'danger');
        }
    }

    function showNewVehicleModal() {
        try {
            const modalEl = document.getElementById('newVehicleModal');
            if (!modalEl) {
                showAlert('Δεν βρέθηκε το modal οχήματος', 'warning');
                return;
            }
            
            // Show the modal
            const modal = new bootstrap.Modal(modalEl, {
                backdrop: 'static',
                keyboard: false
            });
            modal.show();
        } catch (error) {
            showAlert('Σφάλμα κατά την εμφάνιση του modal. Παρακαλώ ανανεώστε τη σελίδα.', 'danger');
        }
    }
    
    
    function toggleCustomerProblemDescription() {
        
        const reasonSelect = document.getElementById('customer_appointment_reason');
        const problemGroup = document.getElementById('customerProblemDescriptionGroup');
        const problemTextarea = document.getElementById('customer_problem_description');
        
        if (reasonSelect && reasonSelect.value === 'επιδιόρθωση') {
            if (problemGroup) problemGroup.style.display = 'block';
            if (problemTextarea) problemTextarea.required = true;
        } else {
            if (problemGroup) problemGroup.style.display = 'none';
            if (problemTextarea) {
                problemTextarea.required = false;
                problemTextarea.value = '';
            }
        }
    }
    
    function updateCustomerTimeSlots() {
        // Updated to work with the new customer form field IDs
        const dateInput = document.getElementById('customer_appointment_date');
        const timeSelect = document.getElementById('customer_appointment_time');
        
        if (!dateInput || !timeSelect) return;
        
        if (!dateInput.value) {
            timeSelect.innerHTML = '<option value="">Επιλέξτε ημερομηνία πρώτα</option>';
            return;
        }
        
        // Show loading
        timeSelect.innerHTML = '<option value="">Φόρτωση διαθέσιμων ωρών...</option>';
        timeSelect.disabled = true;
        
        // Make AJAX request to get available time slots
        fetchAPI(`appointments.php?action=available_slots&date=${encodeURIComponent(dateInput.value)}`)
        .then(data => {
            if (window.__APP_DEBUG__) console.log('[Slots] Response for date', dateInput.value, data);
            timeSelect.innerHTML = '<option value="">Επιλέξτε Ώρα</option>';
            
            if (data.success && data.data && data.data.length > 0) {
                data.data.forEach(slot => {
                    const option = document.createElement('option');
                    // Handle both string and object formats
                    if (typeof slot === 'string') {
                        option.value = slot;
                        option.textContent = slot;
                    } else if (typeof slot === 'object' && slot.time) {
                        option.value = slot.time;
                        option.textContent = slot.display || slot.time;
                        if (slot.available_mechanics) {
                            option.textContent += ` (Διαθέσιμοι: ${slot.available_mechanics})`;
                        }
                    } else {
                        // Fallback for unexpected format
                        if (window.__APP_DEBUG__) console.warn('Unexpected slot format:', slot);
                        return;
                    }
                    timeSelect.appendChild(option);
                });
            } else {
                timeSelect.innerHTML = '<option value="">Δεν υπάρχουν διαθέσιμες ώρες</option>';
            }
            
            timeSelect.disabled = false;
        })
            .catch(error => {
            if (window.__APP_DEBUG__) console.error('Error fetching time slots:', error);
            timeSelect.innerHTML = '<option value="">Σφάλμα φόρτωσης ωρών</option>';
            timeSelect.disabled = false;
        });
    }

    
    document.addEventListener('DOMContentLoaded', function() {
    if (window.__APP_DEBUG__) console.log('Customer Dashboard: DOM Content Loaded - Using forms.js for form handling');
        
        
        // Set up the appointment reason change handler for the customer appointment modal
        const reasonSelect = document.getElementById('customer_appointment_reason');
        if (reasonSelect) {
            reasonSelect.addEventListener('change', toggleCustomerProblemDescription);
        }
        
        // Set up the appointment date change handler for the customer appointment modal  
        const dateInput = document.getElementById('customer_appointment_date');
        if (dateInput) {
            dateInput.addEventListener('change', updateCustomerTimeSlots);
        }
    });
    </script>
</body>
</html>
