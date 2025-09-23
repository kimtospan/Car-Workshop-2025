<?php
/**
 * Reusable Dashboard Components
 */

// Include necessary dependencies
require_once __DIR__ . '/../../../includes/language.php';
require_once __DIR__ . '/../../config/AppConfig.php';

class DashboardComponents {
    
    /**
     * Render user profile header (used in all dashboards)
     */
    public static function renderProfileHeader($currentUser, $additionalInfo = '') {
        ?>
        <div class="portfolio-profile-container mb-4">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-md-8 col-12">
                        <div class="d-flex align-items-center">
                            <div>
                                <h4 class="mb-1 text-white"><?= htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></h4>
                                <div class="d-flex align-items-center gap-3 flex-wrap">
                                    <span class="text-white-50">
                                        <i class="fa-solid fa-envelope me-1"></i>
                                        <?= htmlspecialchars($currentUser['email']) ?>
                                    </span>
                                    <span class="badge bg-<?= self::getRoleBadgeColor($currentUser['role']) ?> px-3 py-2">
                                        <i class="fa-solid fa-<?= self::getRoleIcon($currentUser['role']) ?> me-1"></i>
                                        <?= htmlspecialchars(ucfirst($currentUser['role'])) ?>
                                    </span>
                                    <?= $additionalInfo ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-12 text-md-end text-center mt-md-0 mt-3">
                        <button class="btn btn-light btn-lg shadow-sm" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                            <i class="fa-solid fa-user-edit me-2"></i>
                            <?= t('edit_profile') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render generic view appointment modal, with 3 different versions
     */
    public static function renderViewAppointmentModal($userRole = 'customer') {
        ?>
        <div class="modal fade" id="viewAppointmentModal" tabindex="-1" aria-labelledby="viewAppointmentModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title" id="viewAppointmentModalLabel">
                            <i class="fa-solid fa-eye me-2"></i><?= t('appointment_details') ?>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?= t('close') ?>"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Loading state -->
                        <div id="viewAppointmentLoading" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden"><?= t('loading') ?></span>
                            </div>
                            <p class="mt-2 text-muted"><?= t('loading_appointment_data') ?></p>
                        </div>
                        
                        <!-- Appointment content (hidden initially) -->
                        <div id="viewAppointmentContent" style="display: none;">
                            <div class="row">
                                <!-- Basic Information -->
                                <div class="col-12 mb-4">
                                    <h6 class="border-bottom pb-2 mb-3">
                                        <i class="fa-solid fa-info-circle me-2"></i><?= t('general_information') ?>
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <strong><?= t('appointment_number') ?>:</strong><br>
                                            <span id="view_appointment_id" class="text-muted"></span>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong><?= t('status') ?>:</strong><br>
                                            <span id="view_appointment_status" class="badge"></span>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong><?= t('date') ?>:</strong><br>
                                            <span id="view_appointment_date" class="text-muted"></span>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong><?= t('time') ?>:</strong><br>
                                            <span id="view_appointment_time" class="text-muted"></span>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong><?= t('appointment_reason') ?>:</strong><br>
                                            <span id="view_appointment_reason" class="text-muted"></span>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong><?= t('creation_date') ?>:</strong><br>
                                            <span id="view_appointment_created" class="text-muted"></span>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong><?= t('total_cost') ?>:</strong><br>
                                            <span id="view_appointment_cost" class="text-muted"></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Customer Information -->
                                <div class="col-12 mb-4" id="view_customer_section">
                                    <h6 class="border-bottom pb-2 mb-3">
                                        <i class="fa-solid fa-user me-2"></i><?= t('customer_information') ?>
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <strong><?= t('full_name') ?>:</strong><br>
                                            <span id="view_customer_name" class="text-muted"></span>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong><?= t('email') ?>:</strong><br>
                                            <span id="view_customer_email" class="text-muted"></span>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong><?= t('vat_number') ?>:</strong><br>
                                            <span id="view_customer_afm" class="text-muted"></span>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong><?= t('address') ?>:</strong><br>
                                            <span id="view_customer_address" class="text-muted"></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Vehicle Information -->
                                <div class="col-12 mb-4">
                                    <h6 class="border-bottom pb-2 mb-3">
                                        <i class="fa-solid fa-car me-2"></i><?= t('vehicle_information') ?>
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <strong><?= t('brand_model') ?>:</strong><br>
                                            <span id="view_vehicle_brand_model" class="text-muted"></span>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong><?= t('serial_number') ?>:</strong><br>
                                            <span id="view_vehicle_serial" class="text-muted"></span>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong><?= t('manufacturing_date') ?>:</strong><br>
                                            <span id="view_vehicle_year" class="text-muted"></span>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong><?= t('acquisition_year_label') ?>:</strong><br>
                                            <span id="view_vehicle_acquisition" class="text-muted"></span>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong><?= t('vehicle_type_label') ?>:</strong><br>
                                            <span id="view_vehicle_type" class="text-muted"></span>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong><?= t('engine_type_label') ?>:</strong><br>
                                            <span id="view_vehicle_engine" class="text-muted"></span>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong><?= t('doors_count') ?>:</strong><br>
                                            <span id="view_vehicle_doors" class="text-muted"></span>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong><?= t('wheels_count') ?>:</strong><br>
                                            <span id="view_vehicle_wheels" class="text-muted"></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Mechanic Information -->
                                <div class="col-12 mb-4" id="view_mechanic_section">
                                    <h6 class="border-bottom pb-2 mb-3">
                                        <i class="fa-solid fa-wrench me-2"></i><?= t('mechanic_information') ?>
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <strong><?= t('full_name') ?>:</strong><br>
                                            <span id="view_mechanic_name" class="text-muted"></span>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong><?= t('email') ?>:</strong><br>
                                            <span id="view_mechanic_email" class="text-muted"></span>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong><?= t('specialty') ?>:</strong><br>
                                            <span id="view_mechanic_specialty" class="text-muted"></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Problem Description -->
                                <div class="col-12 mb-4" id="view_problem_section" style="display: none;">
                                    <h6 class="border-bottom pb-2 mb-3">
                                        <i class="fa-solid fa-tools me-2"></i><?= t('problem_description') ?>
                                    </h6>
                                    <div class="bg-light p-3 rounded">
                                        <span id="view_problem_description" class="text-muted"></span>
                                    </div>
                                </div>

                                <!-- Tasks (for mechanics) -->
                                <div class="col-12 mb-4" id="view_tasks_section" style="display: none;">
                                    <h6 class="border-bottom pb-2 mb-3">
                                        <i class="fa-solid fa-list-check me-2"></i><?= t('tasks') ?>
                                    </h6>
                                    <div id="view_tasks_list">
                                        <!-- Tasks will be populated dynamically -->
                                    </div>
                                </div>
                            </div>
                            
                          
                            <div id="appointmentActionSections" class="mt-4">
                               
                                <div id="dateTimeEditSection" class="card mb-3" style="display: none;" 
                                     data-roles="<?= $userRole === 'secretary' ? 'all' : ($userRole === 'customer' ? 'ΔΗΜΙΟΥΡΓΗΜΕΝΟ' : 'ΣΕ ΕΞΕΛΙΞΗ') ?>">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">
                                            <i class="bi bi-calendar-event me-2"></i><?= t('edit_date_time_section') ?>
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <form id="updateDateTimeForm" data-ajax="1">
                                            <input type="hidden" id="datetime_appointment_id" name="appointment_id">
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label for="edit_appointment_date" class="form-label"><?= t('date') ?> <?= t('required_indicator') ?></label>
                                                    <input type="date" class="form-control" id="edit_appointment_date" name="appointment_date" 
                                                           min="<?= date('Y-m-d') ?>" required>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label for="edit_appointment_time" class="form-label"><?= t('time') ?> <?= t('required_indicator') ?></label>
                                                    <select class="form-select" id="edit_appointment_time" name="appointment_time" required>
                                                        <option value=""><?= t('select_date_first') ?></option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="bi bi-check-lg me-2"></i><?= t('save_changes') ?>
                                                </button>
                                                <button type="button" class="btn btn-secondary" onclick="hideDateTimeSection()">
                                                    <i class="bi bi-x-lg me-2"></i><?= t('cancel') ?>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                
                                <!-- Status Change Section (Secretary only) -->
                                <?php if ($userRole === 'secretary'): ?>
                                <div id="statusChangeSection" class="card mb-3" style="display: none;">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">
                                            <i class="bi bi-arrow-repeat me-2"></i><?= t('change_status_section') ?>
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <form id="updateStatusForm" data-ajax="1">
                                            <input type="hidden" id="status_appointment_id" name="appointment_id">
                                            <div class="row">
                                                <div class="col-md-8 mb-3">
                                                    <label for="edit_appointment_status" class="form-label"><?= t('new_status') ?> <?= t('required_indicator') ?></label>
                                                    <select class="form-select" id="edit_appointment_status" name="status" required>
                                                        <option value="ΔΗΜΙΟΥΡΓΗΜΕΝΟ"><?= t('appointment_status_created') ?></option>
                                                        <option value="ΣΕ ΕΞΕΛΙΞΗ"><?= t('appointment_status_in_progress') ?></option>
                                                        <option value="ΠΕΡΑΙΩΜΕΝΟ"><?= t('appointment_status_completed') ?></option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <button type="submit" class="btn btn-success">
                                                    <i class="bi bi-check-lg me-2"></i><?= t('update_status') ?>
                                                </button>
                                                <button type="button" class="btn btn-secondary" onclick="hideStatusSection()">
                                                    <i class="bi bi-x-lg me-2"></i><?= t('cancel') ?>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Tasks Management Section  -->
                                <?php if ($userRole === 'secretary' || $userRole === 'mechanic'): ?>
                                <div id="tasksManagementSection" class="card mb-3" style="display: none;" 
                                     data-roles="<?= $userRole === 'secretary' ? 'all' : 'ΣΕ ΕΞΕΛΙΞΗ' ?>">
                                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0">
                                            <i class="bi bi-tools me-2"></i><?= t('task_management_section') ?>
                                        </h6>
                                        <button type="button" class="btn btn-sm btn-success" onclick="showAddTaskForm()">
                                            <i class="bi bi-plus"></i> Προσθήκη Εργασίας
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <div id="tasksListManagement">
                                            <!-- Editable tasks list will be populated here -->
                                        </div>
                                        <div id="addTaskForm" class="mt-3" style="display: none;">
                                            <form id="createTaskForm" data-ajax="1">
                                                <input type="hidden" id="task_appointment_id" name="appointment_id">
                                                <div class="row">
                                                    <div class="col-md-12 mb-3">
                                                        <label for="task_description" class="form-label"><?= t('task_description') ?> <?= t('required_indicator') ?></label>
                                                        <textarea class="form-control" id="task_description" name="description" rows="2" required></textarea>
                                                    </div>
                                                    <div class="col-md-8 mb-3">
                                                        <label for="task_materials" class="form-label"><?= t('materials_parts') ?> <?= t('required_indicator') ?></label>
                                                        <textarea class="form-control" id="task_materials" name="materials" rows="2" required></textarea>
                                                    </div>
                                                    <div class="col-md-4 mb-3">
                                                        <label for="task_completion_time" class="form-label"><?= t('completion_time_hours') ?> <?= t('required_indicator') ?></label>
                                                        <input type="number" class="form-control" id="task_completion_time" name="completion_time" 
                                                               step="0.5" min="0.5" max="24" required>
                                                    </div>
                                                    <div class="col-md-12 mb-3">
                                                        <div class="alert alert-info">
                                                            <i class="bi bi-info-circle me-2"></i>
                                                            <strong><?= t('cost') ?>:</strong> <?= t('cost_calculation_info') ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="d-flex gap-2">
                                                    <button type="submit" class="btn btn-success">
                                                        <i class="bi bi-plus-lg me-2"></i><?= t('add_task') ?>
                                                    </button>
                                                    <button type="button" class="btn btn-secondary" onclick="hideAddTaskForm()">
                                                        <i class="bi bi-x-lg me-2"></i><?= t('cancel') ?>
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fa-solid fa-times me-2"></i><?= t('close') ?>
                        </button>
                        
                        <div id="view_appointment_actions">
                        
                            
                            <?php if ($userRole === 'customer'): ?>
                                <!-- Customer cancel button - shown conditionally via JS based on status -->
                                <button type="button" id="customerCancelBtn" class="btn btn-danger" style="display: none;" 
                                        onclick="cancelAppointmentAsCustomer()">
                                    <i class="bi bi-x-circle me-2"></i>Ακύρωση Ραντεβού
                                </button>
                                <!-- Customer edit date/time button - shown when status is CREATED -->
                                <button type="button" id="customerEditDateTimeBtn" class="btn btn-primary" style="display: none;" 
                                        onclick="showDateTimeEditSection()">
                                    <i class="bi bi-calendar-event me-2"></i>Αλλαγή Ημερομηνίας/Ώρας
                                </button>
                            <?php elseif ($userRole === 'mechanic'): ?>
                                <!-- Mechanic buttons - shown when status is ΣΕ ΕΞΕΛΙΞΗ -->
                                <button type="button" id="mechanicEditDateTimeBtn" class="btn btn-primary" style="display: none;" 
                                        onclick="showDateTimeEditSection()">
                                    <i class="bi bi-calendar-event me-2"></i>Αλλαγή Ημερομηνίας/Ώρας
                                </button>
                                <button type="button" id="mechanicManageTasksBtn" class="btn btn-warning" style="display: none;" 
                                        onclick="showTasksManagementSection()">
                                    <i class="bi bi-tools me-2"></i>Διαχείριση Εργασιών
                                </button>
                            <?php elseif ($userRole === 'secretary'): ?>
                                <!-- Secretary has full access - all buttons shown conditionally -->
                                <button type="button" id="secretaryEditDateTimeBtn" class="btn btn-primary" style="display: none;" 
                                        onclick="showDateTimeEditSection()">
                                    <i class="bi bi-calendar-event me-2"></i>Αλλαγή Ημερομηνίας/Ώρας
                                </button>
                                <button type="button" id="secretaryChangeStatusBtn" class="btn btn-info" style="display: none;" 
                                        onclick="showStatusChangeSection()">
                                    <i class="bi bi-arrow-repeat me-2"></i>Αλλαγή Κατάστασης
                                </button>
                                <button type="button" id="secretaryManageTasksBtn" class="btn btn-warning" style="display: none;" 
                                        onclick="showTasksManagementSection()">
                                    <i class="bi bi-tools me-2"></i>Διαχείριση Εργασιών
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        // Role-based appointment modal management
        window.appointmentModalUserRole = '<?= $userRole ?>';
        
        // Function to show/hide action sections based on user role and appointment status
        function updateAppointmentModalActions(appointmentData) {
            const userRole = window.appointmentModalUserRole;
            const status = appointmentData.status;
            
            // Hide all action sections first (with null checks)
            const dateTimeSection = document.getElementById('dateTimeEditSection');
            if (dateTimeSection) dateTimeSection.style.display = 'none';
            
            const statusSection = document.getElementById('statusChangeSection');
            if (statusSection) statusSection.style.display = 'none';
            
            const tasksSection = document.getElementById('tasksManagementSection');
            if (tasksSection) tasksSection.style.display = 'none';
            
            // Hide all action buttons first (with null checks)
            const allActionButtons = document.querySelectorAll('#view_appointment_actions button[id*="Btn"]');
            allActionButtons.forEach(btn => {
                if (btn) btn.style.display = 'none';
            });
            
            // Role-based logic with defensive programming
            if (userRole === 'customer') {
                // Customer can edit date/time only when status is CREATED
                if (status === 'ΔΗΜΙΟΥΡΓΗΜΕΝΟ') {
                    const editBtn = document.getElementById('customerEditDateTimeBtn');
                    if (editBtn) editBtn.style.display = 'inline-block';
                }
                // Customer can cancel only when status is not IN_PROGRESS, COMPLETED, or CANCELED
                if (!['ΣΕ ΕΞΕΛΙΞΗ', 'ΠΕΡΑΙΩΜΕΝΟ', 'ΑΚΥΡΩΜΕΝΟ'].includes(status)) {
                    const cancelBtn = document.getElementById('customerCancelBtn');
                    if (cancelBtn) cancelBtn.style.display = 'inline-block';
                }
            } else if (userRole === 'mechanic') {
                // Mechanic can edit and manage tasks only when status is ΣΕ ΕΞΕΛΙΞΗ
                if (status === 'ΣΕ ΕΞΕΛΙΞΗ') {
                    const editBtn = document.getElementById('mechanicEditDateTimeBtn');
                    if (editBtn) editBtn.style.display = 'inline-block';
                    
                    const tasksBtn = document.getElementById('mechanicManageTasksBtn');
                    if (tasksBtn) tasksBtn.style.display = 'inline-block';
                }
            } else if (userRole === 'secretary') {
                // Secretary has full access
                const editBtn = document.getElementById('secretaryEditDateTimeBtn');
                if (editBtn) editBtn.style.display = 'inline-block';
                
                const statusBtn = document.getElementById('secretaryChangeStatusBtn');
                if (statusBtn) statusBtn.style.display = 'inline-block';
                
                const tasksBtn = document.getElementById('secretaryManageTasksBtn');
                if (tasksBtn) tasksBtn.style.display = 'inline-block';
            }
        }
        
        // Functions to show action sections
        function showDateTimeEditSection() {
            const section = document.getElementById('dateTimeEditSection');
            if (section) section.style.display = 'block';
        }
        
        function hideDateTimeSection() {
            const section = document.getElementById('dateTimeEditSection');
            if (section) section.style.display = 'none';
        }
        
        function showStatusChangeSection() {
            const section = document.getElementById('statusChangeSection');
            if (section) section.style.display = 'block';
        }
        
        function hideStatusSection() {
            const section = document.getElementById('statusChangeSection');
            if (section) section.style.display = 'none';
        }
        
        function showTasksManagementSection() {
            const section = document.getElementById('tasksManagementSection');
            if (section) section.style.display = 'block';
        }
        
        function hideTasksManagementSection() {
            const section = document.getElementById('tasksManagementSection');
            if (section) section.style.display = 'none';
        }
        
        // Customer-specific cancel function
        function cancelAppointmentAsCustomer() {
            const appointmentId = document.getElementById('view_appointment_id').textContent;
            if (!appointmentId) return;
            
            if (!confirm('<?= t('confirm_cancellation') ?>')) {
                return;
            }
            
            // Use the shared cancel function from dashboard-common.js
            if (typeof window.cancelAppointment === 'function') {
                window.cancelAppointment(appointmentId, 'customer');
            } else {
                if (typeof window !== 'undefined' && window.__APP_DEBUG__) console.error('cancelAppointment function not available from dashboard-common.js');
            }
        }
        </script>
        <?php
    }

    /**
     * Render generic view vehicle modal
     */
    public static function renderViewVehicleModal() {
        ?>
        <div class="modal fade" id="<?= htmlspecialchars('viewVehicleModal') ?>" tabindex="-1" aria-labelledby="<?= htmlspecialchars('viewVehicleModalLabel') ?>" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="<?= htmlspecialchars('viewVehicleModalLabel') ?>">
                            <i class="fa-solid fa-car me-2"></i><?= t('vehicle_details') ?>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?= t('close') ?>"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Loading state -->
                        <div id="<?= htmlspecialchars('viewVehicleLoading') ?>" class="text-center py-4">
                            <div class="spinner-border text-success" role="status">
                                <span class="visually-hidden"><?= t('loading') ?></span>
                            </div>
                            <p class="mt-2 text-muted"><?= t('loading_vehicle_data') ?></p>
                        </div>
                        
                        <!-- Vehicle content -->
                        <div id="<?= htmlspecialchars('viewVehicleContent') ?>" style="display: none;">
                            <!-- View Mode -->
                            <div id="<?= htmlspecialchars('vehicleViewMode') ?>">
                                <div class="row">
                                    <!-- Basic Vehicle Information -->
                                    <div class="col-12 mb-4">
                                        <h6 class="border-bottom pb-2 mb-3">
                                            <i class="fa-solid fa-info-circle me-2"></i><?= htmlspecialchars('Βασικές Πληροφορίες') ?>
                                        </h6>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <strong><?= htmlspecialchars('Serial Number:') ?></strong><br>
                                                <span id="<?= htmlspecialchars('view_vehicle_serial_number') ?>" class="badge bg-info"></span>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <strong><?= t('vehicle_brand') ?>:</strong><br>
                                                <span id="<?= htmlspecialchars('view_vehicle_brand') ?>" class="text-muted"></span>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <strong><?= t('vehicle_model') ?>:</strong><br>
                                                <span id="<?= htmlspecialchars('view_vehicle_model') ?>" class="text-muted"></span>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <strong><?= t('vehicle_type_label') ?>:</strong><br>
                                                <span id="<?= htmlspecialchars('view_vehicle_type_') ?>" class="text-muted"></span>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <strong><?= t('engine_type_label') ?>:</strong><br>
                                                <span id="<?= htmlspecialchars('view_vehicle_engine_type') ?>" class="text-muted"></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Technical Specifications -->
                                    <div class="col-12 mb-4">
                                        <h6 class="border-bottom pb-2 mb-3">
                                            <i class="fa-solid fa-cogs me-2"></i><?= t('technical_specifications') ?>
                                        </h6>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <strong><?= t('doors_count') ?>:</strong><br>
                                                <span id="<?= htmlspecialchars('view_vehicle_numberof_doors') ?>" class="text-muted"></span>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <strong><?= t('wheels_count') ?>:</strong><br>
                                                <span id="<?= htmlspecialchars('view_vehicle_numberof_wheels') ?>" class="text-muted"></span>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <strong><?= t('manufacturing_date') ?>:</strong><br>
                                                <span id="<?= htmlspecialchars('view_vehicle_production_date') ?>" class="text-muted"></span>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <strong><?= t('acquisition_year_label') ?>:</strong><br>
                                                <span id="<?= htmlspecialchars('view_vehicle_acquisition_year') ?>" class="text-muted"></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Customer Information -->
                                    <div class="col-12 mb-4" id="<?= htmlspecialchars('view_vehicle_customer_section') ?>">
                                        <h6 class="border-bottom pb-2 mb-3">
                                            <i class="fa-solid fa-user me-2"></i><?= t('owner_information') ?>
                                        </h6>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <strong><?= t('full_name') ?>:</strong><br>
                                                <span id="<?= htmlspecialchars('view_vehicle_customer_name') ?>" class="text-muted"></span>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <strong><?= t('email') ?>:</strong><br>
                                                <span id="<?= htmlspecialchars('view_vehicle_customer_email') ?>" class="text-muted"></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Registration Information -->
                                    <div class="col-12 mb-4">
                                        <h6 class="border-bottom pb-2 mb-3">
                                            <i class="fa-solid fa-calendar me-2"></i><?= t('registration_information') ?>
                                        </h6>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <strong><?= t('registration_date') ?>:</strong><br>
                                                <span id="view_vehicle_created_at" class="text-muted"></span>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <strong><?= t('last_updated') ?>:</strong><br>
                                                <span id="view_vehicle_updated_at" class="text-muted"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Edit Mode (hidden initially) -->
                            <div id="<?= htmlspecialchars('vehicleEditMode') ?>" style="display: none;">
                                <form id="<?= htmlspecialchars('editVehicleForm') ?>">
                                    <input type="hidden" id="<?= htmlspecialchars('edit_vehicle_id') ?>" name="<?= htmlspecialchars('vehicle_id') ?>">
                                    <input type="hidden" id="<?= htmlspecialchars('edit_vehicle_customer_id') ?>" name="<?= htmlspecialchars('customer_id') ?>">
                                    
                                    <div class="row">
                                        <!-- Basic Vehicle Information -->
                                        <div class="col-12 mb-4">
                                            <h6 class="border-bottom pb-2 mb-3">
                                                <i class="fa-solid fa-edit me-2"></i>Επεξεργασία Στοιχείων Οχήματος
                                            </h6>
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label for="edit_serial_number" class="form-label"><?= t('license_plate') ?> <?= t('required_indicator') ?></label>
                                                    <input type="text" class="form-control" id="edit_serial_number" name="serial_number" required>
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label for="edit_brand" class="form-label"><?= t('vehicle_brand') ?> <?= t('required_indicator') ?></label>
                                                    <input type="text" class="form-control" id="edit_brand" name="brand" required>
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label for="edit_model" class="form-label"><?= t('vehicle_model') ?> <?= t('required_indicator') ?></label>
                                                    <input type="text" class="form-control" id="edit_model" name="model" required>
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label for="edit_vehicle_type" class="form-label"><?= t('vehicle_type') ?> <?= t('required_indicator') ?></label>
                                                    <select class="form-select" id="edit_vehicle_type" name="vehicle_type" required>
                                                        <option value=""><?= t('select_vehicle_type') ?></option>
                                                        <option value="επιβατικό"><?= t('passenger_vehicle') ?></option>
                                                        <option value="φορτηγό">Φορτηγό</option>
                                                        <option value="λεωφορείο">Λεωφορείο</option>
                                                    </select>
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label for="edit_engine_type" class="form-label">Τύπος Κινητήρα *</label>
                                                    <select class="form-select" id="edit_engine_type" name="engine_type" required>
                                                        <option value="">Επιλέξτε τύπο κινητήρα</option>
                                                        <option value="βενζινοκίνητο">Βενζινοκίνητο</option>
                                                        <option value="ηλεκτροκίνητο">Ηλεκτροκίνητο</option>
                                                        <option value="πετρελαιοκίνητο">Πετρελαιοκίνητο</option>
                                                        <option value="υγραέριο">Υγραέριο</option>
                                                        <option value="μεικτό">Μεικτό</option>
                                                    </select>
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label for="edit_door_count" class="form-label">Αριθμός Θυρών *</label>
                                                    <input type="number" class="form-control" id="edit_door_count" name="door_count" min="1" max="6" required>
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label for="edit_wheel_count" class="form-label">Αριθμός Ροδών *</label>
                                                    <input type="number" class="form-control" id="edit_wheel_count" name="wheel_count" min="3" max="18" required>
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label for="edit_production_date" class="form-label">Ημερομηνία Παραγωγής *</label>
                                                    <input type="date" class="form-control" id="edit_production_date" name="production_date" max="<?= date('Y-m-d') ?>" required>
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label for="edit_acquisition_year" class="form-label">Έτος Κτήσης *</label>
                                                    <input type="number" class="form-control" id="edit_acquisition_year" name="acquisition_year" 
                                                           min="1900" max="<?= date('Y') ?>" required>
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fa-solid fa-save me-2"></i>Αποθήκευση Αλλαγών
                                        </button>
                                        <button type="button" class="btn btn-secondary" onclick="cancelVehicleEdit()">
                                            <i class="fa-solid fa-times me-2"></i>Ακύρωση
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fa-solid fa-times me-2"></i>Κλείσιμο
                        </button>
                        <!-- Role-specific action buttons will be added dynamically -->
                        <div id="view_vehicle_actions">
                            <!-- Edit button will be shown for secretaries -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render edit profile modal
     */
    public static function renderEditProfileModal($currentUser) {
        ?>
        <div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    
                    <form id="editProfileForm" method="POST" action="" data-user-role="<?= htmlspecialchars($currentUser['role']) ?>">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="editProfileModalLabel">
                                <i class="fa-solid fa-user-edit me-2"></i><?= t('edit_profile') ?>
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <!-- Basic Information Section -->
                            <div class="mb-4">
                                <h6 class="border-bottom pb-2 mb-3">
                                    <i class="fa-solid fa-user me-2"></i><?= t('basic_information') ?>
                                </h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="first_name" class="form-label"><?= t('first_name') ?> <?= t('required_indicator') ?></label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" 
                                               value="<?= htmlspecialchars($currentUser['first_name']) ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="last_name" class="form-label"><?= t('last_name') ?> <?= t('required_indicator') ?></label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" 
                                               value="<?= htmlspecialchars($currentUser['last_name']) ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label"><?= t('email') ?> <?= t('required_indicator') ?></label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?= htmlspecialchars($currentUser['email']) ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="username" class="form-label"><?= t('username') ?></label>
                                        <input type="text" class="form-control" id="username" name="username" 
                                               value="<?= htmlspecialchars($currentUser['username']) ?>" readonly>
                                        <small class="form-text text-muted"><?= t('username_cannot_change') ?></small>
                                    </div>
                                </div>
                            </div>

                            <!-- Role-specific Information Section -->
                            <div id="roleSpecificFields" class="mb-4" style="display: none;">
                                <!-- Customer Fields -->
                                <div id="customerFields" class="role-fields" data-role="customer" style="display: none;">
                                    <h6 class="border-bottom pb-2 mb-3">
                                        <i class="fa-solid fa-address-card me-2"></i><?= t('customer_details') ?>
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="afm" class="form-label"><?= t('vat_number') ?> <?= t('required_indicator') ?></label>
                                            <input type="text" class="form-control" id="afm" name="afm" 
                                                   placeholder="123456789" maxlength="9" pattern="[0-9]{9}"
                                                   title="<?= t('vat_number_validation') ?>">
                                            <small class="form-text text-muted"><?= t('nine_digits') ?></small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="address" class="form-label"><?= t('home_address') ?> <?= t('required_indicator') ?></label>
                                            <textarea class="form-control" id="address" name="address" 
                                                      rows="3" placeholder="<?= t('address_placeholder') ?>"></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Mechanic Fields -->
                                <div id="mechanicFields" class="role-fields" data-role="mechanic" style="display: none;">
                                    <h6 class="border-bottom pb-2 mb-3">
                                        <i class="fa-solid fa-tools me-2"></i><?= t('mechanic_details') ?>
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="specialty" class="form-label"><?= t('specialty') ?> <?= t('required_indicator') ?></label>
                                            <select class="form-select" id="specialty" name="specialty">
                                                <option value=""><?= t('select_specialty') ?></option>
                                                <option value="Αρχιμηχανικός"><?= t('chief_mechanic') ?></option>
                                                <option value="Ηλεκτρονικός"><?= t('electronic_specialist') ?></option>
                                                <option value="Μηχανικός κινητήρων"><?= t('engine_mechanic') ?></option>
                                                <option value="Μηχανικός ψυκτικών"><?= t('cooling_mechanic') ?></option>
                                                <option value="Μηχανικός φρένων"><?= t('brake_mechanic') ?></option>
                                                <option value="Μηχανικός ανάρτησης"><?= t('suspension_mechanic') ?></option>
                                                <option value="Μηχανικός υδραυλικών"><?= t('hydraulics_mechanic') ?></option>
                                                <option value="Άλλη"><?= t('other_specialty') ?></option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3" id="otherSpecialtyContainer" style="display: none;">
                                            <label for="otherSpecialty" class="form-label"><?= t('custom_specialty') ?> <?= t('required_indicator') ?></label>
                                            <input type="text" class="form-control" id="otherSpecialty" name="otherSpecialty" 
                                                   placeholder="<?= t('specify_specialty') ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fa-solid fa-save me-2"></i><?= t('save_changes') ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        // Role-specific field handling for edit profile modal
        document.addEventListener('DOMContentLoaded', function() {
            const specialtySelect = document.getElementById('specialty');
            const otherSpecialtyContainer = document.getElementById('otherSpecialtyContainer');
            const otherSpecialtyInput = document.getElementById('otherSpecialty');

            if (specialtySelect) {
                specialtySelect.addEventListener('change', function() {
                    if (this.value === 'Άλλη') {
                        otherSpecialtyContainer.style.display = 'block';
                        if (otherSpecialtyInput) {
                            otherSpecialtyInput.required = true;
                        }
                    } else {
                        otherSpecialtyContainer.style.display = 'none';
                        if (otherSpecialtyInput) {
                            otherSpecialtyInput.required = false;
                            otherSpecialtyInput.value = '';
                        }
                    }
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * Get role-specific badge color
     */
    private static function getRoleBadgeColor($role) {
        return match($role) {
            'secretary' => 'success',
            'mechanic' => 'warning',
            'customer' => 'primary',
            default => 'secondary'
        };
    }
    
    /**
     * Get role-specific icon
     */
    private static function getRoleIcon($role) {
        return match($role) {
            'secretary' => 'shield',
            'mechanic' => 'tools',
            'customer' => 'user',
            default => 'question'
        };
    }
}
?>
