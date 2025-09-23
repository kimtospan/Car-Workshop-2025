

// Search pattern used
// - Inputs are wired with a debounced 'input' handler
// - Handlers call the shared refresh functions (refreshUsersTable, refreshVehiclesTable, refreshAppointmentsTable)
// - Refresh functions build server queries and update DOM via centralized render helpers

// Initialize secretary dashboard functionality
document.addEventListener('DOMContentLoaded', function() {
    try {
    if (window.__APP_DEBUG__) console.log('[Secretary] DOMContentLoaded');
        initSecretaryDashboard();
    } catch (e) {
        if (window.__APP_DEBUG__) console.error('[Secretary] init error:', e);
    }
});

function initSecretaryDashboard() {
    if (window.__APP_DEBUG__) console.groupCollapsed('[Secretary] init');
    // Initialize user management features
    initUserManagement();
    
    // Initialize appointment management features
    initAppointmentManagement();
    
 
    
    // Initialize search and filtering
    initSearchAndFiltering();

    // Initial table loads without page reloads
    refreshUsersTable();
    refreshVehiclesTable();
    refreshAppointmentsTable();
   // Initialize profile form features
    window.initProfileForm();
    // Initialize appointment creation form to use AJAX
    try {
        if (typeof window.initAppointmentForm === 'function') {
            window.initAppointmentForm('secretaryAppointmentForm', {
                endpoint: 'appointments.php',
                onSuccess: (response) => {
                    if (typeof refreshAppointmentsTable === 'function') refreshAppointmentsTable();
                },
                onError: (error) => {
                    showError('Σφάλμα κατά το κλείσιμο του ραντεβού: ' + (error && error.message ? error.message : error));
                }
            });
        } else {
            if (window.__APP_DEBUG__) console.error('forms.js initAppointmentForm not available');
        }
    } catch (e) {
        if (window.__APP_DEBUG__) console.error('[Secretary] Error initializing appointment form:', e);
    }
   
    if (window.__APP_DEBUG__) console.groupEnd();
}

function initUserManagement() {
    // Add User: role-based field visibility only (submission handled via initUserForm in forms.js)
    // Role-based field visibility
    const roleSelect = document.getElementById('role');
    if (roleSelect) {
        roleSelect.addEventListener('change', function() {
            toggleRoleSpecificFields(this.value);
        });
    }
}

function validateUserForm(form) {
    const firstName = form.querySelector('[name="first_name"]');
    const lastName = form.querySelector('[name="last_name"]');
    const username = form.querySelector('[name="username"]');
    const email = form.querySelector('[name="email"]');
    const password = form.querySelector('[name="password"]');
    const role = form.querySelector('[name="role"]');
    const idNumber = form.querySelector('[name="id_number"]');
    // Clear previous field errors
    try { if (typeof clearFieldErrors === 'function') clearFieldErrors(form); } catch (_) {}
    let valid = true;
    
    // Basic validation
    if (!firstName.value.trim()) {
        try { if (typeof setFieldError === 'function') setFieldError(firstName, 'Παρακαλώ εισάγετε όνομα'); } catch (_) {}
        firstName.focus();
        valid = false;
    }
    
    if (!lastName.value.trim()) {
        try { if (typeof setFieldError === 'function') setFieldError(lastName, 'Παρακαλώ εισάγετε επώνυμο'); } catch (_) {}
        lastName.focus();
        valid = false;
    }
    
    if (!username.value.trim() || username.value.length < 3) {
        try { if (typeof setFieldError === 'function') setFieldError(username, 'Το username πρέπει να έχει τουλάχιστον 3 χαρακτήρες'); } catch (_) {}
        username.focus();
        valid = false;
    }
    
    if (!isValidEmail(email.value)) {
        try { if (typeof setFieldError === 'function') setFieldError(email, 'Παρακαλώ εισάγετε έγκυρη διεύθυνση email'); } catch (_) {}
        email.focus();
        valid = false;
    }
    
    if (!password.value || password.value.length < 6) {
        try { if (typeof setFieldError === 'function') setFieldError(password, 'Ο κωδικός πρέπει να έχει τουλάχιστον 6 χαρακτήρες'); } catch (_) {}
        password.focus();
        valid = false;
    }
    
    if (!role.value) {
        try { if (typeof setFieldError === 'function') setFieldError(role, 'Παρακαλώ επιλέξτε ρόλο χρήστη'); } catch (_) {}
        role.focus();
        valid = false;
    }

    // ID number required and must be 8 chars ending with 6 digits
    if (idNumber) {
        const v = (idNumber.value || '').trim();
        if (!v) {
            try { if (typeof setFieldError === 'function') setFieldError(idNumber, 'Ο αριθμός ταυτότητας (ΑΤ) είναι υποχρεωτικός'); } catch (_) {}
            idNumber.focus();
            valid = false;
        } else if (!isValidIdNumber(v)) {
            try { if (typeof setFieldError === 'function') setFieldError(idNumber, 'Ο αριθμός ταυτότητας πρέπει να είναι μορφής ΑΤ123456'); } catch (_) {}
            idNumber.focus();
            valid = false;
        }
    }
    
    // Role-specific required fields
    if (role.value === 'mechanic') {
        const specialty = form.querySelector('[name="specialty"]');
        if (specialty && !specialty.value.trim()) {
            try { if (typeof setFieldError === 'function') setFieldError(specialty, 'Η ειδικότητα είναι υποχρεωτική για μηχανικούς'); } catch (_) {}
            specialty.focus();
            valid = false;
        }
    } else if (role.value === 'customer') {
        const afm = form.querySelector('[name="afm"]');
        const address = form.querySelector('[name="address"]');
        if (afm && !isValidAFM(afm.value)) {
            try { if (typeof setFieldError === 'function') setFieldError(afm, 'Το ΑΦΜ πρέπει να αποτελείται από 9 ψηφία'); } catch (_) {}
            afm.focus();
            valid = false;
        }
        if (address && !address.value.trim()) {
            try { if (typeof setFieldError === 'function') setFieldError(address, 'Η διεύθυνση είναι υποχρεωτική για πελάτες'); } catch (_) {}
            address.focus();
            valid = false;
        }
    }

    // Focus first invalid field if any
    if (!valid) {
        const firstInvalid = form.querySelector('.is-invalid');
        if (firstInvalid && typeof firstInvalid.focus === 'function') firstInvalid.focus();
    }
    return valid;
}

function toggleRoleSpecificFields(role) {
    // Show/hide role-specific fields (if any) in add user form
    const specialtyField = document.getElementById('specialty_field');
    const afmField = document.getElementById('afm_field');
    const addressField = document.getElementById('address_field');
    
    // Hide all role-specific fields first
    [specialtyField, afmField, addressField].forEach(field => {
        if (field) field.style.display = 'none';
    });
    
    // Show relevant fields based on role
    switch (role) {
        case 'mechanic':
            if (specialtyField) specialtyField.style.display = 'block';
            break;
        case 'customer':
            if (afmField) afmField.style.display = 'block';
            if (addressField) addressField.style.display = 'block';
            break;
    }
}

function initAppointmentManagement() {
    // Add search input handler
    const appointmentSearchInput = document.getElementById('appointment_search');
    if (appointmentSearchInput) {
        appointmentSearchInput.addEventListener('input', debounce(function() {
            // Reset to first page when performing a new search
            window.currentAppointmentsPage = 1;
            refreshAppointmentsTable();
        }, 300));
    }
    
    // Status filter with API call
    const statusFilter = document.getElementById('appointment_status_filter');
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            // When changing status filter, go back to first page
            window.currentAppointmentsPage = 1;
            refreshAppointmentsTable();
        });
    }
    
    // Date range filtering with API calls
    const dateFromFilter = document.getElementById('date_from_filter');
    const dateToFilter = document.getElementById('date_to_filter');
    
    if (dateFromFilter && dateToFilter) {
        dateFromFilter.addEventListener('change', function() {
            window.currentAppointmentsPage = 1;
            refreshAppointmentsTable();
        });
        dateToFilter.addEventListener('change', function() {
            window.currentAppointmentsPage = 1;
            refreshAppointmentsTable();
        });
    }
}



function initSearchAndFiltering() {
    // Global search functionality
    const searchInput = document.getElementById('global_search');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(function() {
            performGlobalSearch(this.value);
        }, 300));
    }
    
    // Table search inputs
    const userSearchInput = document.getElementById('user_search');
    const userFilterSelect = document.getElementById('user_filter');
    const appointmentSearchInput = document.getElementById('appointment_search');
    
    // Vehicle search inputs
    const vehicleSearchInput = document.getElementById('vehicle_search');
    const vehicleFilterSelect = document.getElementById('vehicle_filter');
    
    if (userSearchInput) {
        const debouncedAjaxUserSearch = debounce(function() {
            // Reset to first page when starting a new search so results appear on page 1
            window.currentUsersPage = 1;
            refreshUsersTable();
        }, 300);
        userSearchInput.addEventListener('input', debouncedAjaxUserSearch);
    }

    if (userFilterSelect) {
        userFilterSelect.addEventListener('change', function() {
            // When changing filters, reset to first page so results appear on page 1
            window.currentUsersPage = 1;
            refreshUsersTable();
        });
    }
    
    if (vehicleSearchInput) {
        const debouncedVehicleSearch = debounce(function() {
            const q = vehicleSearchInput.value.trim();
            const endpoint = q ? `vehicles.php?q=${encodeURIComponent(q)}` : 'vehicles.php';
            refreshTable('vehiclesTable', endpoint, {
                onSuccess: (response) => {
                    updateVehiclesTable(response.data, response.meta);
                }
            });
        }, 300);
        vehicleSearchInput.addEventListener('input', debouncedVehicleSearch);
    }

    if (vehicleFilterSelect) {
        vehicleFilterSelect.addEventListener('change', function() {
            refreshVehiclesTable();
        });
    }
    
    if (appointmentSearchInput) {
        appointmentSearchInput.addEventListener('input', debounce(function() {
            refreshAppointmentsTable();
        }, 300));
    }
}

function performGlobalSearch(query) {
    // Search across all visible tables
    searchTable('users', query);
    searchTable('appointments', query);
    searchTable('vehicles', query);
}

function searchTable(tableType, query) {
    let tableSelector = '';
    
    switch (tableType) {
        case 'users':
            tableSelector = '.card:has(h5:contains("user")) .table tbody tr';
            break;
        case 'appointments':
            tableSelector = '.card:has(h5:contains("appointment")) .table tbody tr';
            break;
        case 'vehicles':
            tableSelector = '#vehiclesTable .table tbody tr';
            break;
        default:
            return;
    }
    
    const rows = document.querySelectorAll(tableSelector);
    const searchTerm = query.toLowerCase();
    
    rows.forEach(row => {
        const rowText = row.textContent.toLowerCase();
        row.style.display = rowText.includes(searchTerm) ? '' : 'none';
    });
}




/**
 * Load a user into the shared edit modal, allow editing and submit changes.
 *
 * this function fetches the user record from the API,
 * populates a single reusable modal with the returned data, shows the
 * appropriate role-specific fields and wires an AJAX submit that performs
 * the update. The modal is reset when hidden so the same instance can be
 * reused for multiple edits.
 */
function editUser(userId) {
    const modal = document.getElementById('editUserModal');
    const loadingDiv = document.getElementById('editUserLoading');
    const contentDiv = document.getElementById('editUserContent');
    const submitBtn = document.getElementById('editUserSubmitBtn');
    const form = document.getElementById('editUserForm');
    
    if (!modal) {
        showError('Edit modal not found');
        return;
    }
    
    // Reset modal state
    loadingDiv.style.display = 'block';
    contentDiv.style.display = 'none';
    submitBtn.style.display = 'none';
    
    // Show modal immediately with loading state using shared safe modal function
    safeShowModal(modal);
    
    // Get modal instance for later operations
    let bootstrapModal = bootstrap.Modal.getInstance(modal);
    if (!bootstrapModal) {
        bootstrapModal = new bootstrap.Modal(modal);
    }
    
    // Fetch user data from API
    fetchAPI(`users.php?id=${encodeURIComponent(userId)}`)
        .then(response => {
            if (!response.success) {
                throw new Error(response.message || 'Failed to load user data');
            }
            
            const user = response.data;
            
            // Populate form fields with user data
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_user_role').value = user.actual_role || user.role;
            document.getElementById('edit_first_name').value = user.first_name || '';
            document.getElementById('edit_last_name').value = user.last_name || '';
            document.getElementById('edit_username').value = user.username || '';
            document.getElementById('edit_email').value = user.email || '';
            document.getElementById('edit_role_display').value = (user.actual_role || user.role || '').toUpperCase();
            
            // Update modal title with user name
            document.getElementById('editUserModalLabel').textContent = 
                `Επεξεργασία Χρήστη: ${user.first_name} ${user.last_name}`;
            
            // Hide all role-specific fields initially
            document.getElementById('edit_customer_fields').style.display = 'none';
            document.getElementById('edit_mechanic_fields').style.display = 'none';
            
            // Show role-specific fields and populate data
            const userRole = user.actual_role || user.role;
            if (userRole === 'customer') {
                document.getElementById('edit_customer_fields').style.display = 'block';
                document.getElementById('edit_afm').value = user.afm || '';
                document.getElementById('edit_address').value = user.address || '';
                // Make customer fields required
                document.getElementById('edit_afm').required = true;
                document.getElementById('edit_address').required = true;
            } else if (userRole === 'mechanic') {
                document.getElementById('edit_mechanic_fields').style.display = 'block';
                document.getElementById('edit_specialty').value = user.specialty || '';
                // Make mechanic fields required
                document.getElementById('edit_specialty').required = true;
            }
            
            // Show form content and submit button
            loadingDiv.style.display = 'none';
            contentDiv.style.display = 'block';
            submitBtn.style.display = 'inline-block';
            
        })
        .catch(error => {
            if (window.__APP_DEBUG__) console.error('Error loading user data:', error);
            loadingDiv.innerHTML = `
                <div class="text-center py-4">
                    <i class="fa-solid fa-exclamation-triangle text-danger fs-1 mb-3"></i>
                    <p class="text-danger">Σφάλμα φόρτωσης δεδομένων χρήστη</p>
                    <p class="text-muted small">${error.message}</p>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Κλείσιμο</button>
                </div>
            `;
        });
    
    // Set up form submission handler 
    if (!form.dataset.boundAjax) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Extract form data and convert to a plain object for JSON PUT request
            const formData = new FormData(form);
            const payload = {};
            for (const [key, value] of formData.entries()) {
                payload[key] = value;
            }
            
            // Disable submit button during request
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Αποθήκευση...';
            
            // Send AJAX PUT request to users.php API endpoint (JSON)
            fetchAPI('users.php', {
                method: 'PUT',
                data: payload
            })
            .then(response => {
                if (response.success) {
                    // Success: Update UI and close modal
                    showSuccess('Τα στοιχεία χρήστη ενημερώθηκαν επιτυχώς');
                    refreshUsersTable(); // Refresh table to show updated data
                    bootstrapModal.hide();
                } else {
                    // Error: Display field-specific errors or generic message
                    if (response.errors) {
                        try {
                            displayFieldErrors(form, response.errors);
                        } catch (_) {
                            showError('Error: ' + Object.values(response.errors).join(', '));
                        }
                    } else {
                        showError(response.message || 'Αποτυχία ενημέρωσης χρήστη');
                    }
                }
            })
            .catch(error => {
                if (window.__APP_DEBUG__) console.error('Error updating user:', error);
                showError('Error: Network issue');
            })
            .finally(() => {
                // Re-enable submit button
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-save me-2"></i>Αποθήκευση Αλλαγών';
            });
        });
        
        // Mark form as having bound AJAX handler
        form.dataset.boundAjax = '1';
    }
    
    // Add modal cleanup when hidden
    modal.addEventListener('hidden.bs.modal', function() {
        // Reset form
        form.reset();
        // Hide role-specific fields
        document.getElementById('edit_customer_fields').style.display = 'none';
        document.getElementById('edit_mechanic_fields').style.display = 'none';
        // Remove required attributes
        document.getElementById('edit_afm').required = false;
        document.getElementById('edit_address').required = false;
        document.getElementById('edit_specialty').required = false;
        // Clear any field errors
        try {
            if (typeof clearFieldErrors === 'function') {
                clearFieldErrors(form);
            }
        } catch (_) {}
    });
}

async function deleteUser(userId) {
    const okDelete = await confirmAction({ title: 'Επιβεβαίωση', message: 'Είστε σίγουροι ότι θέλετε να διαγράψετε αυτόν τον χρήστη;', confirmText: 'Διαγραφή', cancelText: 'Ακύρωση' });
    if (!okDelete) return;
    
    fetchAPI('users.php', {
        method: 'DELETE',
        data: { user_id: userId }
    }).then(resp => {
        if (resp && resp.success) {
            showSuccess('Ο χρήστης διαγράφηκε επιτυχώς');
            refreshUsersTable();
        } else if (resp) {
            showError(resp.message || 'Αποτυχία διαγραφής χρήστη');
        }
    }).catch(() => showError('Σφάλμα δικτύου κατά τη διαγραφή χρήστη'));
}

async function activateUser(userId) {
    const okActivate = await confirmAction({ title: 'Επιβεβαίωση', message: 'Είστε σίγουροι ότι θέλετε να ενεργοποιήσετε αυτόν τον χρήστη;', confirmText: 'Ναι, ενεργοποίησε', cancelText: 'Ακύρωση' });
    if (!okActivate) return;
    fetchAPI('users.php', {
        method: 'PUT',
        data: { user_id: userId, new_status: 1 }
    }).then(resp => {
        if (resp.success) {
            showSuccess('Ο χρήστης ενεργοποιήθηκε');
            refreshUsersTable();
        } else {
            showError(resp.message || 'Αποτυχία ενεργοποίησης χρήστη');
        }
    }).catch(() => showError('Σφάλμα δικτύου'));
}

async function deactivateUser(userId) {
    const okDeactivate = await confirmAction({ title: 'Επιβεβαίωση', message: 'Είστε σίγουροι ότι θέλετε να απενεργοποιήσετε αυτόν τον χρήστη;', confirmText: 'Ναι, απενεργοποίησε', cancelText: 'Ακύρωση' });
    if (!okDeactivate) return;
    fetchAPI('users.php', {
        method: 'PUT',
        data: { user_id: userId, new_status: 0 }
    }).then(resp => {
        if (resp.success) {
            showSuccess('Ο χρήστης απενεργοποιήθηκε');
            refreshUsersTable();
        } else {
            showError(resp.message || 'Αποτυχία απενεργοποίησης χρήστη');
        }
    }).catch(() => showError('Σφάλμα δικτύου'));
}




/**
 * Load vehicles for the selected customer into the secretary booking modal.
 */
function loadCustomerVehicles() {
    const customerSelect = document.getElementById('secretary_customer_id');
    const vehicleSelect = document.getElementById('secretary_vehicle_id');
    if (!vehicleSelect) return;

    if (!customerSelect || !customerSelect.value) {
        vehicleSelect.innerHTML = '<option value="">Επιλέξτε πρώτα πελάτη</option>';
        return;
    }

    vehicleSelect.innerHTML = '<option value="">Φόρτωση οχημάτων...</option>';
    vehicleSelect.disabled = true;

    fetchAPI(`vehicles.php?customer_id=${encodeURIComponent(customerSelect.value)}`)
    .then(data => {
        if (window.__APP_DEBUG__) console.log('[Vehicles] Response for customer', customerSelect.value, data);
        vehicleSelect.innerHTML = '<option value="">Επιλέξτε Όχημα</option>';

        if (data && data.success && Array.isArray(data.data) && data.data.length > 0) {
            data.data.forEach(vehicle => {
                const option = document.createElement('option');
                option.value = vehicle.id;
                option.textContent = (vehicle.brand || '') + ' ' + (vehicle.model || '') + ' (' + (vehicle.serial_number || '') + ')';
                vehicleSelect.appendChild(option);
            });
        } else {
            vehicleSelect.innerHTML = '<option value="">Δεν βρέθηκαν οχήματα</option>';
        }

        vehicleSelect.disabled = false;
    })
    .catch(error => {
        if (window.__APP_DEBUG__) console.error('Error fetching vehicles:', error);
        vehicleSelect.innerHTML = '<option value="">Σφάλμα φόρτωσης οχημάτων</option>';
        vehicleSelect.disabled = false;
    });
}

/**
 * Update available time slots for the selected date in the secretary booking modal.
 */
function updateSecretaryTimeSlots() {
    const dateInput = document.getElementById('secretary_appointment_date');
    const timeSelect = document.getElementById('secretary_appointment_time');
    if (!timeSelect) return;

    if (!dateInput || !dateInput.value) {
        timeSelect.innerHTML = '<option value="">Επιλέξτε ημερομηνία πρώτα</option>';
        return;
    }

    timeSelect.innerHTML = '<option value="">Φόρτωση διαθέσιμων ωρών...</option>';
    timeSelect.disabled = true;

    fetchAPI(`appointments.php?action=available_slots&date=${encodeURIComponent(dateInput.value)}`)
    .then(data => {
        if (window.__APP_DEBUG__) console.log('[Slots] Response for date', dateInput.value, data);
        timeSelect.innerHTML = '<option value="">Επιλέξτε Ώρα</option>';

        if (data && data.success && Array.isArray(data.data) && data.data.length > 0) {
            data.data.forEach(slot => {
                const option = document.createElement('option');
                option.value = slot.time;
                option.textContent = slot.display + ' (Διαθέσιμοι: ' + slot.available_mechanics + ')';
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



// Export and import functions



// Export functions for use in other scripts or inline handlers
window.SecretaryDashboard = {
    editUser,
    deleteUser,
    activateUser,
    deactivateUser,
    cancelAppointment: (appointmentId) => window.cancelAppointment(appointmentId, 'secretary'),
    deleteVehicle: (vehicleId) => window.deleteVehicle(vehicleId, 'secretary'),
    
    openBookAppointment: function(customerId) {
        try {
        if (window.__APP_DEBUG__) console.log('[Secretary] openBookAppointment called with:', customerId);
            // Try to find the customer's name from the table row for UX hint
            let customerName = '';
            const row = document.querySelector(`#usersTable tbody tr td:first-child`);
            // Fallback: not reliable to grab from DOM, keep blank
            if (typeof window.openSecretaryAppointmentForCustomer === 'function') {
                window.openSecretaryAppointmentForCustomer(customerId, customerName);
            } else {
                const modalEl = document.getElementById('newAppointmentModal');
                if (modalEl && typeof modalEl.querySelector === 'function') {
                    const select = document.getElementById('secretary_customer_id');
                    if (select) {
                        select.value = String(customerId);
                        if (typeof loadCustomerVehicles === 'function') loadCustomerVehicles();
                    }
                    // Ensure backdrop not blocking old instances
                    try {
                        if (window.__APP_DEBUG__) console.log('[Secretary] Fallback showing newAppointmentModal');
                        bootstrap.Modal.getOrCreateInstance(modalEl).show();
                                    } catch (e) { if (window.__APP_DEBUG__) console.error('[Secretary] Modal show error:', e); }
                }
            }
        } catch (e) {
            if (window.__APP_DEBUG__) console.error('openBookAppointment error', e);
        }
    }
};

// Expose new helpers for legacy inline handlers
window.SecretaryDashboard.loadCustomerVehicles = loadCustomerVehicles;
window.SecretaryDashboard.updateSecretaryTimeSlots = updateSecretaryTimeSlots;
