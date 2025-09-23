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

require_once '../src/config/AppConfig.php';
require_once '../src/config/debug.php';

// Handle logout first
if (isset($_GET['logout'])) {
    $auth->logout();
    header("Location: index.php");
    exit;
}

// Ensure user is mechanic
$auth->requireLogin();
$currentUser = $auth->getCurrentUser();
$currentRole = $currentUser['role'];

if ($currentRole !== 'mechanic') {
    header("Location: dashboard.php");
    exit;
}

// Get database connection for mechanic dashboard
$db = Database::getInstance()->getConnection();

// Get mechanic ID from user ID (required for task and appointment management)
$mechanicId = null;
$upcomingAppointment = null;
$todayAppointmentsCount = 0;

try {
    // Get mechanic ID from current user
    $stmt = $db->prepare("SELECT id, specialty FROM mechanics WHERE user_id = ?");
    $stmt->execute([$currentUser['id']]);
    $mechanicData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$mechanicData) {
        $error_message = "<?= t('mechanic_profile_not_found') ?>";
    } else {
        $mechanicId = $mechanicData['id'];
        $currentUser['specialty'] = $mechanicData['specialty'];
        
        // Get upcoming appointment for mechanic
                $stmt = $db->prepare(
                        "SELECT a.*, 
                                        cu.first_name, cu.last_name, cu.email,
                                        vehicles.brand as vehicle_brand, vehicles.model as vehicle_model, vehicles.serial_number,
                                        a.reason
                         FROM appointments a
                         JOIN customers c ON a.customer_id = c.id
                            LEFT JOIN users cu ON c.user_id = cu.id
                            LEFT JOIN vehicles ON a.vehicle_id = vehicles.id
                         WHERE a.mechanic_id = ? 
                             AND a.status NOT IN ('ΠΕΡΑΙΩΜΕΝΟ', 'ΑΚΥΡΩΜΕΝΟ')
                             AND a.appointment_date >= CURDATE()
                         ORDER BY a.appointment_date ASC, a.appointment_time ASC
                         LIMIT 1"
                );
        $stmt->execute([$mechanicId]);
        $upcomingAppointment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get today's appointments count
        $stmt = $db->prepare(
            "SELECT COUNT(*) as count 
             FROM appointments 
             WHERE mechanic_id = ? 
               AND DATE(appointment_date) = CURDATE()
               AND status NOT IN ('ΑΚΥΡΩΜΕΝΟ')"
        );
        $stmt->execute([$mechanicId]);
        $todayAppointmentsCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
} catch (Exception $e) {
    $error_message = "Σφάλμα φόρτωσης δεδομένων: " . $e->getMessage();
}

// Initialize form processor
$formProcessor = new FormProcessor($db, $auth);

// Initialize message variables
$success_message = '';
$error_message = $error_message ?? '';
$validation_errors = [];


?>
<!DOCTYPE html>
<html lang="<?= $lang->getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('site_title') ?> - <?= t('mechanic') ?> <?= t('dashboard') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/custom.css" rel="stylesheet">
</head>
<body>
    <?php renderHeader($lang, 'dashboard'); ?>

    <main class="py-4">
        <div class="container">
            <div id="alertContainer"></div>
            
            
            <?php DashboardComponents::renderProfileHeader($currentUser, $mechanicId ? t('specialty_label') . " " . htmlspecialchars($currentUser['specialty'] ?? t('general_specialty')) : ''); ?>

            <?php if ($mechanicId): ?>
                
                <?php if ($upcomingAppointment): ?>
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="alert alert-info border-0 shadow-sm d-flex align-items-center">
                                <i class="fa-solid fa-calendar-clock fa-2x me-3 text-info"></i>
                                <div class="flex-grow-1">
                                    <h5 class="alert-heading mb-1"><?= t('upcoming_appointment_alert') ?></h5>
                                    <p class="mb-1">
                                        <strong><?= Utils::escape($upcomingAppointment['first_name'] . ' ' . $upcomingAppointment['last_name']) ?></strong> - 
                                        <?= Utils::escape($upcomingAppointment['vehicle_brand'] . ' ' . $upcomingAppointment['vehicle_model']) ?>
                                    </p>
                                    <small class="text-muted">
                                        <i class="fa-solid fa-clock me-1"></i>
                                        <?= Utils::formatDate($upcomingAppointment['appointment_date']) ?> στις <?= Utils::escape($upcomingAppointment['appointment_time']) ?>
                                        - <span class="badge <?= $upcomingAppointment['reason'] === 'σέρβις' ? 'bg-primary' : 'bg-warning' ?>">
                                            <?= $upcomingAppointment['reason'] === 'σέρβις' ? 'Σέρβις' : 'Επισκευή' ?>
                                        </span>
                                    </small>
                                </div>
                                <button class="btn btn-outline-info btn-sm" onclick="viewAppointment(<?= $upcomingAppointment['id'] ?>)">
                                    <i class="fa-solid fa-eye me-1"></i>Προβολή
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="alert alert-light border shadow-sm d-flex align-items-center">
                            <i class="fa-solid fa-calendar-day fa-2x me-3 text-primary"></i>
                            <div>
                                <h6 class="mb-0"><?= t('today_appointments_count') ?> <strong class="text-primary"><?= $todayAppointmentsCount ?></strong> <?= t('appointments_today') ?></h6>
                                <small class="text-muted"><?= t('check_table_details') ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                
                <div class="row">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm rounded-3">
                            <div class="card-header bg-transparent border-0 py-3">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h5 class="mb-0">
                                            <i class="fa-solid fa-calendar-alt me-2 text-primary"></i>
                                            <?= t('my_appointments') ?>
                                        </h5>
                                    </div>
                                    <div class="col-md-6">
                                        <!-- Filter Controls -->
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <select class="form-select form-select-sm" id="appointment_status_filter">
                                                    <option value=""><?= t('all_statuses') ?></option>
                                                    <option value="ΔΗΜΙΟΥΡΓΗΜΕΝΟ"><?= t('appointment_status_created') ?></option>
                                                    <option value="ΣΕ ΕΞΕΛΙΞΗ"><?= t('appointment_status_in_progress') ?></option>
                                                    <option value="ΠΕΡΑΙΩΜΕΝΟ"><?= t('appointment_status_completed') ?></option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <input type="date" class="form-control form-control-sm" id="date_from_filter" 
                                                       value="<?= date('Y-m-d') ?>" title="<?= t('from_date') ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <input type="date" class="form-control form-control-sm" id="date_to_filter" 
                                                       title="<?= t('to_date') ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <!-- Loading Spinner -->
                                <div id="appointmentsLoading" class="text-center py-4 d-none">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Φόρτωση...</span>
                                    </div>
                                    <div class="mt-2 text-muted"><?= t('loading') ?> <?= t('appointments') ?>...</div>
                                </div>

                                <!-- Appointments Table -->
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
                                                <td colspan="8" class="text-center text-muted py-4"><?= t('loading') ?>...</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div id="appointmentsPagination" class="d-flex justify-content-center py-3">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- No Mechanic Profile Found -->
                <div class="row">
                    <div class="col-12">
                        <div class="alert alert-warning border-0 shadow-sm">
                            <div class="d-flex align-items-center">
                                <i class="fa-solid fa-triangle-exclamation fa-2x me-3"></i>
                                <div>
                                    <h5 class="mb-1"><?= t('mechanic_profile_unavailable') ?></h5>
                                    <p class="mb-0">
                                        <?= t('mechanic_profile_not_found') ?>
                                    </p>
                                    <?php if (!empty($error_message)): ?>
                                        <small class="text-muted mt-2 d-block"><?= t('details') ?>: <?= Utils::escape($error_message) ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

   
    <?php DashboardComponents::renderViewAppointmentModal($currentRole); ?>


    <?php DashboardComponents::renderViewVehicleModal(); ?>


    <?php DashboardComponents::renderEditProfileModal($currentUser); ?>

    <?php renderFooter($lang); ?>
    
    <!-- JavaScript Dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/dashboard-common.js"></script>
    <script src="assets/js/forms.js"></script>
    
    <!-- Mechanic Dashboard Configuration -->
    <script>
        // Expose configuration for mechanic-dashboard.js
        window.MechanicDashboardConfig = {
            mechanicId: <?= json_encode($mechanicId) ?>,
            userRole: 'mechanic',
            apiEndpoints: {
                appointments: 'api/appointments.php',
                tasks: 'api/tasks.php',
                users: 'api/users.php'
            }
        };
        
        // Expose CSRF token for AJAX requests
        window.csrfToken = '<?= htmlspecialchars(SecurityConfig::generateCSRFToken()) ?>';
    </script>
    <script src="assets/js/mechanic-dashboard.js"></script>
</body>
</html>
