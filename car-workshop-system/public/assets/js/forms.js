/**
 * Centralised form handling utilities and table renderers. This file is the
 * single source of truth for form interactions, table refresh logic and
 * modal behaviours
 */



/**
 * Wire an appointment creation/edit form to the shared AJAX flow. It
 * prevents native submission, serialises the form and calls
 * `handleFormSubmit`. On success triggers a table refresh. 
 */
function initAppointmentForm(formId, options = {}) {
    const form = document.getElementById(formId);
    if (!form) { if (window.__APP_DEBUG__) console.warn('[AppointmentForm] Form not found:', formId); return; }
    if (window.__APP_DEBUG__) console.log('[AppointmentForm] Initializing for:', formId, form);
    
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        // Basic HTML5 validation
        if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
            // Show built-in validation messages
            try { form.reportValidity(); } catch (_) {}
            return;
        }

        try {
            if (window.__APP_DEBUG__) {
                console.groupCollapsed('[AppointmentForm] Submit');
                if (window.__APP_DEBUG__) console.log('Form data snapshot:', Object.fromEntries(new FormData(form).entries()));
            }

            const response = await handleFormSubmit(form, {
                endpoint: 'appointments.php',
                method: 'POST',
                ...options
            });

            if (response && response.success) {
                // Notify caller; caller always handles table refresh
                if (options.onSuccess) {
                    options.onSuccess(response);
                }
            }

            if (window.__APP_DEBUG__) console.groupEnd();
        } catch (error) {
            if (window.__APP_DEBUG__) console.error('Appointment creation failed:', error);
            try { showError('Appointment creation failed'); } catch (_) {}
            if (window.__APP_DEBUG__) console.groupEnd();
        }
    });
    
    // Handle reason change to show/hide problem description
    const reasonSelect = form.querySelector('[name="reason"]');
    const problemDescGroup = form.querySelector('.problem-description-group') || 
                           document.getElementById('secretaryProblemDescriptionGroup') ||
                           document.getElementById('customerProblemDescriptionGroup');
    
    if (reasonSelect && problemDescGroup) {
        reasonSelect.addEventListener('change', () => {
            if (reasonSelect.value === 'επιδιόρθωση') {
                problemDescGroup.style.display = 'block';
                const textarea = problemDescGroup.querySelector('textarea');
                if (textarea) textarea.required = true;
            } else {
                problemDescGroup.style.display = 'none';
                const textarea = problemDescGroup.querySelector('textarea');
                if (textarea) {
                    textarea.required = false;
                    textarea.value = '';
                }
            }
        });
    } else {
        if (window.__APP_DEBUG__) console.warn('[AppointmentForm] Reason/problem elements missing:', { reasonSelect: !!reasonSelect, problemDescGroup: !!problemDescGroup });
    }
}

/**
 * Wire a vehicle form (create/update) to the central submit handler. The
 * function prevents default submission, uses `handleFormSubmit` and
 * refreshes the vehicles table on success
 */
function initVehicleForm(formId, options = {}) {
    const form = document.getElementById(formId);
    if (!form) return;
    
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        try {
            const response = await handleFormSubmit(form, {
                endpoint: 'vehicles.php',
                ...options
            });

            if (response && response.success) {
                if (options.onSuccess) options.onSuccess(response);
                refreshVehiclesTable();
            }
        } catch (error) {
            if (window.__APP_DEBUG__) console.error('Vehicle form submission failed:', error);
        }
    });
}

/**
 * Wire a generic user form to the AJAX submission flow used on secretary
 * pages for add/edit user forms. It runs any page-provided
 * `validateUserForm` before submitting
 */
function initUserForm(formId, options = {}) {
    const form = document.getElementById(formId);
    if (!form) return;
    
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
            try {
                // Run page-specific validation if available
                if (typeof validateUserForm === 'function') {
                    const ok = validateUserForm(form);
                    if (!ok) return; // Block submission on invalid
                }

                const response = await handleFormSubmit(form, {
                    endpoint: 'users.php',
                    ...options
                });

                if (response && response.success) {
                    if (options.onSuccess) options.onSuccess(response);
                    refreshUsersTable();
                }
            } catch (error) {
                if (window.__APP_DEBUG__) console.error('User form submission failed:', error);
            }
    });
}

// CSV import handlers for secretary
document.addEventListener('DOMContentLoaded', function() {
    const importUsersForm = document.getElementById('importUsersForm');
    const importVehiclesForm = document.getElementById('importVehiclesForm');

    if (importUsersForm) {
        importUsersForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleCsvImport(importUsersForm, 'users');
        });
    }

    if (importVehiclesForm) {
        importVehiclesForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleCsvImport(importVehiclesForm, 'vehicles');
        });
    }
});

async function handleCsvImport(form, type) {
    const fileInput = form.querySelector('input[type="file"]');
    const resultEl = form.querySelector('[id$="Result"]');
    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        showFormMessage(resultEl, 'No file selected', 'danger');
        return;
    }

    const file = fileInput.files[0];
    if (file.size === 0) {
        showFormMessage(resultEl, 'Selected file is empty', 'danger');
        return;
    }

    // Basic safety: restrict file type and size 
    const maxSize = 2 * 1024 * 1024;
    if (file.size > maxSize) {
        showFormMessage(resultEl, 'File is too large (max 2MB)', 'danger');
        return;
    }

    const formData = new FormData();
    formData.append('csv_file', file);
    // include CSRF token if available
    if (window.csrfToken) formData.append('csrf_token', window.csrfToken);

    // Show loading
    showFormMessage(resultEl, 'Processing import...', 'info');

    try {
        const endpoint = `import_${type}.php`;
        // Use centralized fetchAPI which handles FormData (pass via `data`)
        const data = await fetchAPI(endpoint, {
            method: 'POST',
            data: formData
        });
        if (data.success) {
            // Build a detailed summary if provided
            const summary = data.data || data.summary || null;
            let messageHtml = '';
            if (data.message) {
                messageHtml += '<div>' + escapeHtml(String(data.message)) + '</div>';
            }
            if (summary) {
                messageHtml += '<div class="small mt-2">';
                messageHtml += `<strong>Processed:</strong> ${summary.processed ?? summary.processed_rows ?? 0} &nbsp; `;
                messageHtml += `<strong>Created:</strong> ${summary.created ?? summary.added ?? 0} &nbsp; `;
                messageHtml += `<strong>Skipped:</strong> ${summary.skipped ?? summary.ignored ?? 0}`;
                messageHtml += '</div>';

                if (summary.errors && summary.errors.length > 0) {
                    messageHtml += '<div class="mt-2"><small>Errors:</small><pre class="small">' + escapeHtml(JSON.stringify(summary.errors, null, 2)) + '</pre></div>';
                }
            } else if (data.errors) {
                // If server returned errors but still success, show them
                messageHtml += '<div class="mt-2"><small>Details:</small><pre class="small">' + escapeHtml(JSON.stringify(data.errors, null, 2)) + '</pre></div>';
            } else {
                messageHtml += '<div class="small mt-2">Import finished.</div>';
            }

            showFormMessage(resultEl, messageHtml, 'success');

            // refresh lists
            if (type === 'users' && typeof refreshUsersTable === 'function') refreshUsersTable();
            if (type === 'vehicles' && typeof refreshVehiclesTable === 'function') refreshVehiclesTable();
        } else {
            const msg = data.message || 'Import failed';
            const details = data.errors ? '<pre class="small mt-2">' + escapeHtml(JSON.stringify(data.errors, null, 2)) + '</pre>' : '';
            showFormMessage(resultEl, escapeHtml(msg) + details, 'danger');
        }
    } catch (err) {
        console.error('CSV import error', err);
    showFormMessage(resultEl, 'Operation failed', 'danger');
    }
}

function showFormMessage(container, message, type) {
    if (!container) return;
    container.innerHTML = `<div class="alert alert-${type} small mb-0">${message}</div>`;
}



/**
 * Initialize modal forms and hook into Bootstrap modal lifecycle to clear
 * alerts and reset create/edit states when modals are shown/hidden.
 */
function initModalForms() {
    document.addEventListener('show.bs.modal', (e) => {
        const modal = e.target;
        const form = modal.querySelector('form');
        
        if (form) {
            // Clear any previous alerts
            clearAlerts(modal);
            
            // Reset form if it's a create modal
            if (modal.classList.contains('create-modal')) {
                form.reset();
            }
        }
    });
    
    document.addEventListener('hidden.bs.modal', (e) => {
        const modal = e.target;
        const form = modal.querySelector('form');
        
        if (form) {
            // Clear alerts and reset submission state
            clearAlerts(modal);
            form.dataset.submitting = 'false';
        }

        // If edit car modal if open then close it as well
        if (modal.id === 'viewVehicleModal') {
            const editVehicleModal = bootstrap.Modal.getInstance(document.getElementById('editVehicleModal'));
            if (editVehicleModal) {
                editVehicleModal.hide();
            }
        }
    });
}

/**
 * Refresh appointments table with role-aware parameters.
 */
function refreshAppointmentsTable() {
    // Check if we're on secretary dashboard and use secretary-specific search
    const appointmentSearch = document.getElementById('appointment_search');
    const statusFilter = document.getElementById('appointment_status_filter');
    const dateFromFilter = document.getElementById('date_from_filter');
    const dateToFilter = document.getElementById('date_to_filter');
    
    // Determine which dashboard we're on
    const isCustomerPage = !!document.getElementById('customerAppointmentForm');
    const isMechanicPage = window.MechanicDashboardConfig && window.MechanicDashboardConfig.mechanicId;
    
    // If filters exist or we have a specific dashboard type, use appropriate API endpoint
    if (appointmentSearch || statusFilter || dateFromFilter || dateToFilter || isCustomerPage || isMechanicPage) {
        const searchTerm = appointmentSearch?.value?.trim() || '';
        const status = statusFilter?.value || '';
        const dateFrom = dateFromFilter?.value || '';
        const dateTo = dateToFilter?.value || '';
        
        // Build query parameters based on dashboard type
        const params = new URLSearchParams();
    if (isCustomerPage) {
            params.set('customer_appointments', '1');
        } else if (isMechanicPage) {
            params.set('mechanic_appointments', '1');
        } else {
            params.set('secretary_appointments', '1');
        }

    // Pagination: use currentAppointmentsPage if set, otherwise default to 1
    window.currentAppointmentsPage = window.currentAppointmentsPage || 1;
    const page = parseInt(window.currentAppointmentsPage, 10) || 1;
    const perPage = Math.min(10, parseInt(window.appPerPage || 10, 10));
    params.set('page', page);
    params.set('per_page', perPage);

        if (searchTerm) params.set('search', searchTerm);
        if (status) params.set('status', status);

        // Default to today's appointments (whole day) when no date filters provided.
        
    const today = formatLocalDate(new Date());
        if (isMechanicPage && !dateFrom && !dateTo) {
                params.set('date_from', today);
                params.set('date_to', today);
            } else if (isCustomerPage && !dateFrom && !dateTo) {
                
                params.set('date_from', today);
                params.set('date_to', today);
            } else if (!isCustomerPage && !isMechanicPage && !dateFrom && !dateTo) {
               
                params.set('date_from', today);
                params.set('date_to', today);
            } else {
            if (dateFrom) params.set('date_from', dateFrom);
            if (dateTo) params.set('date_to', dateTo);
        }
        
        // Show loading state
        const tbody = document.querySelector('#appointmentsTable tbody, .appointments-table tbody');
        
        if (tbody) {
            tbody.classList.add('loading');
            tbody.innerHTML = '<tr><td colspan="8" class="text-center"><div class="spinner-border spinner-border-sm me-2"></div>Φόρτωση...</td></tr>';
        }
        
        // Hide loading indicator if it exists
        document.getElementById('appointmentsLoading')?.classList.remove('d-none');
        
        // Make API call via centralized fetchAPI (ensures credentials, CSRF handling and JSON parsing)
        fetchAPI(`appointments.php?${params.toString()}`)
            .then(data => {
                if (data && data.success) {
                    updateAppointmentsTable(data.data, data.meta);
                } else {
                    if (window.__APP_DEBUG__) console.error('Failed to fetch appointments:', data && data.message);
                    if (typeof showError === 'function') {
                        showError('Σφάλμα κατά τη φόρτωση ραντεβού: ' + (data && data.message ? data.message : '')); 
                    }
                    
                    // Remove loading overlay and show error
                    if (overlay) {
                        overlay.classList.add('fade-out');
                        setTimeout(() => overlay.remove(), 300);
                    }
                    
                    const tbody = table?.querySelector('tbody');
                    if (tbody) {
                        tbody.classList.remove('loading');
                        fadeOutAndReplace(tbody, `
                            <tr class="fade-in">
                                <td colspan="8" class="text-center text-danger py-4">
                                    <i class="bi bi-exclamation-triangle fs-1 d-block mb-2"></i>
                                    Σφάλμα φόρτωσης δεδομένων
                                </td>
                            </tr>
                        `);
                    }
                }
            })
            .catch(error => {
                if (window.__APP_DEBUG__) console.error('Error fetching appointments:', error);
                if (typeof showError === 'function') {
                    showError('Σφάλμα δικτύου κατά τη φόρτωση ραντεβού');
                }
                
                // Remove loading overlay and show error
                if (overlay) {
                    overlay.classList.add('fade-out');
                    setTimeout(() => overlay.remove(), 300);
                }
                
                const tbody = table?.querySelector('tbody');
                if (tbody) {
                    tbody.classList.remove('loading');
                    fadeOutAndReplace(tbody, `
                        <tr class="fade-in">
                            <td colspan="8" class="text-center text-danger py-4">
                                <i class="bi bi-wifi-off fs-1 d-block mb-2"></i>
                                Σφάλμα δικτύου
                            </td>
                        </tr>
                    `);
                }
            })
            .finally(() => {
                // Hide loading state
                document.getElementById('appointmentsLoading')?.classList.add('d-none');
            });
    } else {
        // Fallback to generic table refresh for other cases
        refreshTable('appointmentsTable', 'appointments.php', {
            onSuccess: (response) => {
                updateAppointmentsTable(response.data, response.meta);
            }
        });
    }
}

/**
 * Refresh vehicles table
 * Secretary can search by vehicle brand/model, serial number, customer name, or production year, with filter dropdown for sorting by brand, year, or customer name
 */
function refreshVehiclesTable() {
    // Use currentVehiclesPage if set, otherwise default to 1
    window.currentVehiclesPage = window.currentVehiclesPage || 1;
    const page = parseInt(window.currentVehiclesPage, 10) || 1;

    const perPage = Math.min(5, parseInt(window.vehiclesPerPage || 5, 10));

    // Build query params from current UI state
    const params = new URLSearchParams();
    params.set('page', page);
    params.set('per_page', perPage);

    const searchQuery = document.getElementById('vehicle_search')?.value?.trim() || '';
    if (searchQuery) {
        params.set('q', searchQuery);
    }

    const filterValue = document.getElementById('vehicle_filter')?.value || 'all';
    if (filterValue && filterValue !== 'all') {
        if (filterValue.includes(':')) {
            const [filterType, filterDirection] = filterValue.split(':');
            params.set('sort', filterType);
            if (filterDirection) {
                params.set('order', filterDirection);
            }
        }
    }

    const endpoint = `vehicles.php?${params.toString()}`;
    
    refreshTable('vehiclesTable', endpoint, {
        onSuccess: (response) => {
            updateVehiclesTable(response.data || [], response.meta || {}, filterValue);
        },
        onError: (error) => {
            if (window.__APP_DEBUG__) console.error('Failed to refresh vehicles table:', error);
            if (typeof showError === 'function') {
                showError('Σφάλμα κατά τη φόρτωση των οχημάτων');
            }
        }
    });
}/**
 * Refresh users table
 * Secretary can search by user name, username, or email, and filter by role (customer/mechanic/secretary) or status (active/inactive) with sorting options
 */
function refreshUsersTable() {
    // Pagination: use currentUsersPage if set, otherwise default to 1
    window.currentUsersPage = window.currentUsersPage || 1;
    const page = parseInt(window.currentUsersPage, 10) || 1;
    // Align per_page with server expectations (server caps at 5)
    const perPage = Math.min(5, parseInt(window.usersPerPage || 5, 10));

    // Build query params from current UI state so every refresh requests fresh data
    const params = new URLSearchParams();
    params.set('page', page);
    params.set('per_page', perPage);

    // Include search query if present
    const q = document.getElementById('user_search')?.value?.trim() || '';
    if (q) params.set('q', q);

    // Include filter/sort (user_filter uses role:, status:, sort: prefixes)
    const filterVal = document.getElementById('user_filter')?.value || '';
    if (filterVal) {
        if (filterVal.startsWith('role:')) {
            params.set('role', filterVal.split(':')[1]);
        } else if (filterVal.startsWith('status:')) {
            params.set('status', filterVal.split(':')[1]);
        } else if (filterVal.startsWith('sort:')) {
            params.set('sort', filterVal.split(':')[1]);
        }
    }

    const endpoint = `users.php?${params.toString()}`;

    refreshTable('usersTable', endpoint, {
        onSuccess: (response) => {
            updateUsersTable(response.data, response.meta);
        }
    });
}

/**
 * Render appointment rows into the appointments table.
 */
function updateAppointmentsTable(appointments, meta) {
    const tableBody = document.querySelector('#appointmentsTable tbody');
    if (!tableBody) return;
    
    // Remove loading state
    tableBody.classList.remove('loading');
    
    if (!appointments || appointments.length === 0) {
        // Smooth transition to empty state
        if (typeof fadeOutAndReplace === 'function') {
            fadeOutAndReplace(tableBody, `
                <tr class="fade-in">
                    <td colspan="8" class="text-center text-muted py-4">
                        <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                        Δεν βρέθηκαν ραντεβού
                    </td>
                </tr>
            `);
        } else {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                        Δεν βρέθηκαν ραντεβού
                    </td>
                </tr>
            `;
        }
        return;
    }
    
    // Check if we're in secretary dashboard to use different button handlers
    const isSecretaryDashboard = document.getElementById('appointment_search') !== null;
    const isMechanicPage = window.MechanicDashboardConfig && window.MechanicDashboardConfig.mechanicId;
    
    // Helper function to check if appointment can be cancelled
    const canCancelAppointment = (status) => {
        return status === 'ΔΗΜΙΟΥΡΓΗΜΕΝΟ'; // Only allow cancellation for created appointments
    };
    
    // Create new rows with fade-in animation
    const newRows = appointments.map(appointment => `
        <tr class="fade-in" data-appointment-date="${escapeHtml(appointment.appointment_date || '')}">
            <td>${escapeHtml(String(appointment.id || ''))}</td>
            <td>${escapeHtml(appointment.customer_name || 'Άγνωστος')}</td>
            <td>${escapeHtml((appointment.brand || '') + ' ' + (appointment.model || ''))}</td>
            <td>${escapeHtml(appointment.appointment_date || '')}</td>
            <td>${escapeHtml(appointment.appointment_time || '')}</td>
            <td><span class="badge bg-${getStatusColor(appointment.status)}">${escapeHtml(appointment.status || '')}</span></td>
            <td>${escapeHtml(appointment.reason || '')}</td>
            <td class="text-center">
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-info btn-sm" onclick="viewAppointment(${parseInt(appointment.id) || 0})" title="Προβολή">
                        <i class="bi bi-eye"></i>
                    </button>
                    ${!isMechanicPage && isSecretaryDashboard ? `
                        ${canCancelAppointment(appointment.status) ? `
                            <button class="btn btn-outline-danger btn-sm" onclick="window.cancelAppointment(${parseInt(appointment.id) || 0})" title="Ακύρωση">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        ` : ''}
                    ` : ``}
                </div>
            </td>
        </tr>
    `).join('');
    
   
        fadeOutAndReplace(tableBody, newRows);
    
    
    // Update pagination if meta is provided
    if (meta) {
        updatePagination('appointmentsPagination', meta);
    }
}

function updateVehiclesTable(vehicles, meta, filterValue = 'all') {
    const tableBody = document.querySelector('#vehiclesTable tbody');
    if (!tableBody) return;
    
    // Remove loading state
    tableBody.classList.remove('loading');
    
    if (!vehicles || vehicles.length === 0) {
       
            fadeOutAndReplace(tableBody, `
                <tr class="fade-in">
                    <td colspan="6" class="text-center text-muted py-4">
                        <i class="bi bi-car-front fs-1 d-block mb-2"></i>
                        Δεν βρέθηκαν οχήματα
                    </td>
                </tr>
            `);
       
        return;
    }
    
    // Apply client-side filtering/sorting based on filterValue
    let processedVehicles = applyVehicleFilterAndSort([...vehicles], filterValue);
    
    // Create new rows with fade-in animation
    const newRows = processedVehicles.map(vehicle => {
        const row = renderVehicleRow(vehicle);
        return row.replace('<tr>', '<tr class="fade-in">');
    }).join('');
    
    
        fadeOutAndReplace(tableBody, newRows);
    
    
    if (meta) {
        updatePagination('vehiclesPagination', meta);
    }
}

/**
 * Apply filtering and sorting to vehicles array
 */
function applyVehicleFilterAndSort(vehicles, filterValue) {
    try {
        let result = [...vehicles];
        
        // Apply sorting based on filter value
        switch (filterValue) {
            case 'brand:asc':
                result.sort((a, b) => (a.brand || '').localeCompare(b.brand || ''));
                break;
            case 'brand:desc':
                result.sort((a, b) => (b.brand || '').localeCompare(a.brand || ''));
                break;
            case 'year:desc':
                result.sort((a, b) => (parseInt(b.year) || 0) - (parseInt(a.year) || 0));
                break;
            case 'year:asc':
                result.sort((a, b) => (parseInt(a.year) || 0) - (parseInt(b.year) || 0));
                break;
            case 'customer:asc':
                result.sort((a, b) => (a.customer_name || '').localeCompare(b.customer_name || ''));
                break;
            case 'all':
            default:
                // No additional sorting, keep original order
                break;
        }
        
        return result;
    } catch (e) {
    if (window.__APP_DEBUG__) console.warn('applyVehicleFilterAndSort error', e);
        return vehicles || [];
    }
}

/**
 * Render a single vehicle table row
 */
function renderVehicleRow(vehicle) {
    return `
        <tr>
            <td>${vehicle.id}</td>
            <td>${vehicle.brand} ${vehicle.model}</td>
            <td>${vehicle.serial_number || ''}</td>
            <td>${vehicle.customer_name || ''}</td>
            <td>${vehicle.production_date || ''}</td>
            <td class="text-center">
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-info" onclick="viewVehicle(${vehicle.id})" title="Προβολή">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                    <button class="btn btn-outline-danger" onclick="window.deleteVehicle(${vehicle.id})" title="Διαγραφή">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `;
}

function updateUsersTable(payload, meta) {
    // The users endpoint returns either an array or an object with users list
    const users = Array.isArray(payload) ? payload : (payload && payload.users) ? payload.users : [];
    const tableBody = document.querySelector('#usersTable tbody');
    if (!tableBody) return;
    
    // Remove loading state
    tableBody.classList.remove('loading');
    
    if (!users || users.length === 0) {
        
            fadeOutAndReplace(tableBody, `
                <tr class="fade-in">
                    <td colspan="8" class="text-center text-muted py-4">
                        <i class="bi bi-people fs-1 d-block mb-2"></i>
                        No users found
                    </td>
                </tr>
            `);
        
        return;
    }

    // Create new rows with fade-in animation
    const newRows = users.map(user => `
        <tr class="fade-in">
            <td>${user.id}</td>
            <td>${user.first_name} ${user.last_name}</td>
            <td>${user.username}</td>
            <td>${user.email}</td>
            <td><span class="badge bg-${getRoleColor(user.actual_role)}">${(user.actual_role || user.role || '').toUpperCase()}</span></td>
            <td><span class="badge bg-${user.is_active ? 'success' : 'danger'}">${user.is_active ? 'Active' : 'Inactive'}</span></td>
            <td>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" onclick="SecretaryDashboard.editUser(${user.id})">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-outline-${user.is_active ? 'warning' : 'success'}" onclick="${user.is_active ? 'SecretaryDashboard.deactivateUser' : 'SecretaryDashboard.activateUser'}(${user.id})">
                        <i class="bi bi-${user.is_active ? 'person-dash' : 'person-check'}"></i>
                    </button>
                    ${user.actual_role === 'customer' ? `
                    <button type="button" class="btn btn-outline-success" title="Κλείσιμο Ραντεβού" onclick="SecretaryDashboard.openBookAppointment(${user.role_id})">
                        <i class="bi bi-calendar-plus"></i>
                    </button>` : ''}
                    ${user.actual_role !== 'secretary' ? `
                    <button class="btn btn-outline-danger" onclick="SecretaryDashboard.deleteUser(${user.id})">
                        <i class="bi bi-trash"></i>
                    </button>` : ''}
                </div>
            </td>
        </tr>
    `).join('');

    
        fadeOutAndReplace(tableBody, newRows);
    

    // Update pagination controls if meta provided
    if (meta) {
        updatePagination('usersPagination', meta);
    }
}

function applyUserFilterAndSort(users) {
    try {
        const select = document.getElementById('user_filter');
        const searchInput = document.getElementById('user_search');
        let result = Array.isArray(users) ? [...users] : [];
        const selected = select ? select.value : 'all';
        const search = (searchInput && searchInput.value || '').trim().toLowerCase();

        // Text search across key fields
        if (search) {
            result = result.filter(u => {
                const text = `${u.first_name || ''} ${u.last_name || ''} ${u.username || ''} ${u.email || ''}`.toLowerCase();
                return text.includes(search);
            });
        }

        // Filter by role/status
        if (selected.startsWith('role:')) {
            const role = selected.split(':')[1];
            result = result.filter(u => (u.actual_role || u.role) === role);
        } else if (selected.startsWith('status:')) {
            const status = selected.split(':')[1];
            const active = status === 'active';
            result = result.filter(u => !!u.is_active === active);
        }

        // Sorting
        if (selected === 'sort:name_asc' || selected === 'sort:name_desc') {
            const dir = selected.endsWith('_asc') ? 1 : -1;
            result.sort((a, b) => {
                const an = `${a.first_name || ''} ${a.last_name || ''}`.trim().toLowerCase();
                const bn = `${b.first_name || ''} ${b.last_name || ''}`.trim().toLowerCase();
                if (an < bn) return -1 * dir;
                if (an > bn) return 1 * dir;
                return 0;
            });
        }

        return result;
    } catch (e) {
    if (window.__APP_DEBUG__) console.warn('applyUserFilterAndSort error', e);
        return users || [];
    }
}

function getRoleColor(role) {
    const colors = {
        'customer': 'primary',
        'mechanic': 'warning',
        'secretary': 'success'
    };
    return colors[role] || 'secondary';
}


/**
 * View appointment details in modal
 * Generic function that works across all dashboards
 */
function viewAppointment(appointmentId) {
    const modal = document.getElementById('viewAppointmentModal');
    const loadingDiv = document.getElementById('viewAppointmentLoading');
    const contentDiv = document.getElementById('viewAppointmentContent');
    
    if (!modal) {
        showError('View appointment modal not found');
        return;
    }
    
    // Reset modal state
    loadingDiv.style.display = 'block';
    contentDiv.style.display = 'none';
    
    // Get or create modal instance (avoid creating multiple instances)
    let bootstrapModal = bootstrap.Modal.getInstance(modal);
    if (!bootstrapModal) {
        bootstrapModal = new bootstrap.Modal(modal);
    }
    
    // Show modal with loading state (only if not already shown)
    if (!modal.classList.contains('show')) {
        bootstrapModal.show();
    }
    
    // Fetch appointment data from API
    fetchAPI(`appointments.php?id=${encodeURIComponent(appointmentId)}`)
        .then(response => {
            if (!response.success) {
                throw new Error(response.message || 'Failed to load appointment data');
            }
            
            const appointment = response.data;
            
            // Populate basic appointment information
            document.getElementById('view_appointment_id').textContent = appointment.id || '';
            document.getElementById('view_appointment_status').textContent = appointment.status || '';
            document.getElementById('view_appointment_status').className = `badge bg-${getStatusColor(appointment.status)}`;
            document.getElementById('view_appointment_date').textContent = appointment.appointment_date || '';
            document.getElementById('view_appointment_time').textContent = appointment.appointment_time || '';
            document.getElementById('view_appointment_reason').textContent = appointment.reason || '';
            document.getElementById('view_appointment_created').textContent = appointment.created_at ? 
                new Date(appointment.created_at).toLocaleString('el-GR') : '';
            
            // Display cost information
            document.getElementById('view_appointment_cost').textContent = appointment.total_cost ? 
                `€${parseFloat(appointment.total_cost).toFixed(2)}` : 'Δεν έχει υπολογιστεί';
            
            // Populate customer information (if available)
            const customerSection = document.getElementById('view_customer_section');
            const currentUserRole = getCurrentUserRole(); // This function needs to be implemented
            
            if (appointment.customer_name && (currentUserRole === 'secretary' || currentUserRole === 'mechanic')) {
                customerSection.style.display = 'block';
                document.getElementById('view_customer_name').textContent = appointment.customer_name || '';
                document.getElementById('view_customer_email').textContent = appointment.customer_email || '';
                document.getElementById('view_customer_afm').textContent = appointment.customer_afm || 'Δεν έχει οριστεί';
                document.getElementById('view_customer_address').textContent = appointment.customer_address || 'Δεν έχει οριστεί';
            } else {
                customerSection.style.display = 'none';
            }
            
            // Populate vehicle information
            document.getElementById('view_vehicle_brand_model').textContent = 
                `${appointment.brand || ''} ${appointment.model || ''}`.trim() || 'Δεν έχει οριστεί';
            document.getElementById('view_vehicle_serial').textContent = appointment.serial_number || 'Δεν έχει οριστεί';
            document.getElementById('view_vehicle_year').textContent = appointment.production_date || 'Δεν έχει οριστεί';
            document.getElementById('view_vehicle_type').textContent = appointment.vehicle_type || 'Δεν έχει οριστεί';
            document.getElementById('view_vehicle_engine').textContent = appointment.engine_type || 'Δεν έχει οριστεί';
            document.getElementById('view_vehicle_doors').textContent = appointment.door_count || 'Δεν έχει οριστεί';
            document.getElementById('view_vehicle_wheels').textContent = appointment.wheel_count || 'Δεν έχει οριστεί';
            document.getElementById('view_vehicle_acquisition').textContent = appointment.acquisition_year || 'Δεν έχει οριστεί';
            
            // Populate mechanic information (if available)
            const mechanicSection = document.getElementById('view_mechanic_section');
            if (appointment.mechanic_name) {
                mechanicSection.style.display = 'block';
                document.getElementById('view_mechanic_name').textContent = appointment.mechanic_name || '';
                document.getElementById('view_mechanic_email').textContent = appointment.mechanic_email || '';
                document.getElementById('view_mechanic_specialty').textContent = appointment.mechanic_specialty || 'Δεν έχει οριστεί';
            } else {
                mechanicSection.style.display = 'none';
            }
            
            // Show problem description if exists
            const problemSection = document.getElementById('view_problem_section');
            if (appointment.problem_description && appointment.problem_description.trim()) {
                problemSection.style.display = 'block';
                document.getElementById('view_problem_description').textContent = appointment.problem_description;
            } else {
                problemSection.style.display = 'none';
            }
            
            // Show tasks section for mechanics and secretary
            const tasksSection = document.getElementById('view_tasks_section');
            if ((currentUserRole === 'mechanic' || currentUserRole === 'secretary') && appointment.tasks) {
                tasksSection.style.display = 'block';
                const tasksList = document.getElementById('view_tasks_list');
                if (Array.isArray(appointment.tasks) && appointment.tasks.length > 0) {
                    tasksList.innerHTML = appointment.tasks.map(task => `
                        <div class="border rounded p-3 mb-2">
                            <div class="d-flex justify-content-between align-items-start">
                                <h6 class="mb-1">Εργασία #${task.id}</h6>
                                <span class="badge bg-info">€${parseFloat(task.cost || 0).toFixed(2)}</span>
                            </div>
                            <p class="mb-2 text-muted"><strong>Περιγραφή:</strong> ${task.description || 'Χωρίς περιγραφή'}</p>
                            ${task.materials ? `<p class="mb-2 text-muted"><strong>Υλικά:</strong> ${task.materials}</p>` : ''}
                            <div class="row">
                                                <div class="col-md-6">
                                                    <small class="text-muted">
                                                        <strong>Χρόνος:</strong> ${typeof task.completion_time !== 'undefined' && task.completion_time !== null ? (Number(task.completion_time) / 60).toFixed(2) + ' ώρες' : '0 ώρες'}
                                                    </small>
                                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted">
                                        <strong>Δημιουργήθηκε:</strong> ${task.created_at ? new Date(task.created_at).toLocaleDateString('el-GR') : 'Άγνωστο'}
                                    </small>
                                </div>
                            </div>
                        </div>
                    `).join('');
                } else {
                    tasksList.innerHTML = '<p class="text-muted">Δεν υπάρχουν εργασίες για αυτό το ραντεβού.</p>';
                }
            } else {
                tasksSection.style.display = 'none';
            }
            
            // Add role-specific action buttons
            const actionsContainer = document.getElementById('view_appointment_actions');
            actionsContainer.innerHTML = generateAppointmentActionButtons(appointment, currentUserRole);
            
            // Update role-based modal actions
            if (typeof updateAppointmentModalActions === 'function') {
                updateAppointmentModalActions(appointment);
            }
            
            // Show content and hide loading
            loadingDiv.style.display = 'none';
            contentDiv.style.display = 'block';
            
        })
        .catch(error => {
            if (window.__APP_DEBUG__) console.error('Error loading appointment data:', error);
            loadingDiv.innerHTML = `
                <div class="text-center py-4">
                    <i class="fa-solid fa-exclamation-triangle text-danger fs-1 mb-3"></i>
                    <p class="text-danger">Σφάλμα φόρτωσης δεδομένων ραντεβού</p>
                    <p class="text-muted small">${error.message}</p>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Κλείσιμο</button>
                </div>
            `;
        });
}

/**
 * View vehicle details in modal
 * Generic function that works across all dashboards
 */
function viewVehicle(vehicleId) {
    if (!vehicleId) {
    if (window.__APP_DEBUG__) console.error('Vehicle ID is required');
        return;
    }

    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('viewVehicleModal'));
    
    
    // Show loading state
    const loadingDiv = document.getElementById('viewVehicleLoading');
    const contentDiv = document.getElementById('viewVehicleContent');
    const editMode = document.getElementById('vehicleEditMode');
    const viewMode = document.getElementById('vehicleViewMode');
    
    loadingDiv.style.display = 'block';
    contentDiv.style.display = 'none';
    if (editMode) editMode.style.display = 'none';
    if (viewMode) viewMode.style.display = 'block';
    
    // Clear any previous field errors
    const form = document.getElementById('editVehicleForm');
    if (form) {
        clearFieldErrors(form);
    }
    
    
    // Fetch vehicle data from API
    fetchAPI(`vehicles.php?id=${vehicleId}`)
        .then(response => {
            if (!response.success) {
                throw new Error(response.message || 'Σφάλμα φόρτωσης δεδομένων οχήματος');
            }
            
            const vehicle = response.data;
            if (window.__APP_DEBUG__) console.log('Vehicle data loaded:', vehicle);
            if (window.__APP_DEBUG__) console.log('Serial number:', vehicle.serial_number);
            const serialnumber = vehicle.serial_number || '';
            // Populate view mode fields
            document.getElementById('view_vehicle_serial_number').textContent = serialnumber;
            document.getElementById('view_vehicle_brand').textContent = vehicle.brand || 'Δεν έχει οριστεί';
            document.getElementById('view_vehicle_model').textContent = vehicle.model || 'Δεν έχει οριστεί';
            document.getElementById('view_vehicle_type_').textContent = vehicle.vehicle_type || 'Δεν έχει οριστεί';
            document.getElementById('view_vehicle_engine_type').textContent = vehicle.engine_type || 'Δεν έχει οριστεί';
            document.getElementById('view_vehicle_numberof_doors').textContent = vehicle.door_count || 'Δεν έχει οριστεί';
            document.getElementById('view_vehicle_numberof_wheels').textContent = vehicle.wheel_count || 'Δεν έχει οριστεί';
            document.getElementById('view_vehicle_production_date').textContent = vehicle.production_date || 'Δεν έχει οριστεί';
            document.getElementById('view_vehicle_acquisition_year').textContent = vehicle.acquisition_year || 'Δεν έχει οριστεί';
            
            // Format and display dates
            if (vehicle.created_at) {
                document.getElementById('view_vehicle_created_at').textContent = 
                    new Date(vehicle.created_at).toLocaleDateString('el-GR', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
            }
            
            if (vehicle.updated_at) {
                document.getElementById('view_vehicle_updated_at').textContent = 
                    new Date(vehicle.updated_at).toLocaleDateString('el-GR', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
            }
            
            // Populate customer information (if available)
            const customerSection = document.getElementById('view_vehicle_customer_section');
            const currentUserRole = getCurrentUserRole();
            
            if (vehicle.customer_name && (currentUserRole === 'secretary' || currentUserRole === 'mechanic')) {
                customerSection.style.display = 'block';
                document.getElementById('view_vehicle_customer_name').textContent = vehicle.customer_name || '';
                document.getElementById('view_vehicle_customer_email').textContent = vehicle.customer_email || '';
            } else {
                customerSection.style.display = 'none';
            }
            
            // Store vehicle data for edit mode
            window.currentVehicleData = vehicle;
            
            // Add role-specific action buttons (edit button for secretaries and vehicle owners)
            const actionsContainer = document.getElementById('view_vehicle_actions');
            
            // Secretaries can edit any vehicle, customers can edit their own vehicles
            const canEdit = currentUserRole === 'secretary' || 
                           (currentUserRole === 'customer' && vehicle.customer_name);
            
            if (canEdit) {
                actionsContainer.innerHTML = `
                    <button type="button" class="btn btn-warning" onclick="enableVehicleEdit()">
                        <i class="fa-solid fa-edit me-2"></i>Επεξεργασία
                    </button>
                `;
            } else {
                actionsContainer.innerHTML = '';
            }
            
            // Show content and hide loading
            loadingDiv.style.display = 'none';
            contentDiv.style.display = 'block';
            modal.show();
            
        })
        
        .catch(error => {
        if (window.__APP_DEBUG__) console.error('Error loading vehicle data:', error);
            loadingDiv.innerHTML = `
                <div class="text-center py-4">
                    <i class="fa-solid fa-exclamation-triangle text-danger fs-1 mb-3"></i>
                    <p class="text-danger">Σφάλμα φόρτωσης δεδομένων οχήματος</p>
                    <p class="text-muted small">${error.message}</p>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Κλείσιμο</button>
                </div>
            `;
        
    });
}

function enableVehicleEdit() {
    const vehicle = window.currentVehicleData;
    if (!vehicle) {
    if (window.__APP_DEBUG__) console.error('No vehicle data available for editing');
        return;
    }
    
    // Switch to edit mode
    document.getElementById('vehicleViewMode').style.display = 'none';
    document.getElementById('vehicleEditMode').style.display = 'block';
    
    // Populate edit form fields
    document.getElementById('edit_vehicle_id').value = vehicle.id;
    document.getElementById('edit_vehicle_customer_id').value = vehicle.customer_id;
    document.getElementById('edit_serial_number').value = vehicle.serial_number || '';
    document.getElementById('edit_brand').value = vehicle.brand || '';
    document.getElementById('edit_model').value = vehicle.model || '';
    document.getElementById('edit_vehicle_type').value = vehicle.vehicle_type || '';
    document.getElementById('edit_engine_type').value = vehicle.engine_type || '';
    document.getElementById('edit_door_count').value = vehicle.door_count || '';
    document.getElementById('edit_wheel_count').value = vehicle.wheel_count || '';
    document.getElementById('edit_production_date').value = vehicle.production_date || '';
    document.getElementById('edit_acquisition_year').value = vehicle.acquisition_year || '';
    
    // Hide action buttons in edit mode
    document.getElementById('view_vehicle_actions').style.display = 'none';
}

function cancelVehicleEdit() {
    // Switch back to view mode
    document.getElementById('vehicleEditMode').style.display = 'none';
    document.getElementById('vehicleViewMode').style.display = 'block';
    
    // Show action buttons again
    document.getElementById('view_vehicle_actions').style.display = 'block';
    
    // Clear any validation errors
    const form = document.getElementById('editVehicleForm');
    if (form) {
        clearFieldErrors(form);
    }
}

/**
 * Generate action buttons based on user role and appointment status
 */
function generateAppointmentActionButtons(appointment, userRole) {
    let buttons = '';
    const appointmentId = parseInt(appointment.id) || 0;
    
    if (appointmentId <= 0) {
        return ''; // Invalid appointment ID
    }
    
    if (userRole === 'secretary') {
        // Secretary can always change status (except to cancelled)
        buttons += `
            <button type="button" class="btn btn-warning me-2" onclick="toggleStatusSection(${appointmentId})">
                <i class="bi bi-arrow-repeat me-2"></i>Αλλαγή Κατάστασης
            </button>
        `;
        
        if (appointment.status === 'ΔΗΜΙΟΥΡΓΗΜΕΝΟ') {
            buttons += `
                <button type="button" class="btn btn-info me-2" onclick="toggleDateTimeSection(${appointmentId})">
                    <i class="bi bi-calendar-event me-2"></i>Επεξεργασία Ημ/νίας
                </button>
                <button type="button" class="btn btn-danger me-2" onclick="window.cancelAppointment(${appointmentId})">
                    <i class="bi bi-x-circle me-2"></i>Ακύρωση
                </button>
            `;
        } else if (appointment.status === 'ΣΕ ΕΞΕΛΙΞΗ') {
            buttons += `
                <button type="button" class="btn btn-primary me-2" onclick="toggleTasksSection(${appointmentId})">
                    <i class="bi bi-tools me-2"></i>Διαχείριση Εργασιών
                </button>
            `;
        }
    } else if (userRole === 'mechanic') {
        if (appointment.status === 'ΔΗΜΙΟΥΡΓΗΜΕΝΟ') {
            buttons += `
                <button type="button" class="btn btn-success me-2" onclick="startAppointment(${appointmentId})">
                    <i class="bi bi-play me-2"></i>Έναρξη
                </button>
            `;
        } else if (appointment.status === 'ΣΕ ΕΞΕΛΙΞΗ') {
            buttons += `
                <button type="button" class="btn btn-primary me-2" onclick="toggleTasksSection(${appointmentId})">
                    <i class="bi bi-tools me-2"></i>Διαχείριση Εργασιών
                </button>
                <button type="button" class="btn btn-success me-2" onclick="completeAppointment(${appointmentId})">
                    <i class="bi bi-check me-2"></i>Ολοκλήρωση
                </button>
            `;
        }
    } else if (userRole === 'customer') {
        if (appointment.status === 'ΔΗΜΙΟΥΡΓΗΜΕΝΟ') {
            buttons += `
                <button type="button" class="btn btn-info me-2" onclick="toggleDateTimeSection(${appointmentId})">
                    <i class="bi bi-calendar-event me-2"></i>Επεξεργασία Ημ/νίας
                </button>
                <button type="button" class="btn btn-danger me-2" onclick="window.cancelAppointment(${appointmentId})">
                    <i class="bi bi-x-circle me-2"></i>Ακύρωση
                </button>
            `;
        }
    }
    
    return buttons;
}

/**
 * Get task status color
 */
function getTaskStatusColor(status) {
    switch (status) {
        case 'completed':
        case 'ΟΛΟΚΛΗΡΩΜΕΝΗ':
            return 'success';
        case 'in_progress':
        case 'ΣΕ ΕΞΕΛΙΞΗ':
            return 'warning';
        case 'pending':
        case 'ΕΚΚΡΕΜΗΣ':
        default:
            return 'secondary';
    }
}

/**
 * Get current user role (this should be implemented based on how user data is available)
 */
function getCurrentUserRole() {
    // Try to get role from the global variable set by DashboardComponents first
    if (window.appointmentModalUserRole) {
        return window.appointmentModalUserRole;
    }
    
    // Fallback to detecting role from page elements
    // Check if we're on secretary dashboard
    if (document.getElementById('appointment_search') || document.querySelector('.nav-link[href*="secretary"]')) {
        return 'secretary';
    }
    
    // Check if we're on mechanic dashboard
    if (document.querySelector('.nav-link[href*="mechanic"]') || document.getElementById('mechanic_tasks')) {
        return 'mechanic';
    }
    
    // Default to customer
    return 'customer';
}

/**
 * Get status color class
 */
function getStatusColor(status) {
    const colors = {
        'ΔΗΜΙΟΥΡΓΗΜΕΝΟ': 'primary',
        'ΣΕ ΕΞΕΛΙΞΗ': 'warning',
        'ΠΕΡΑΙΩΜΕΝΟ': 'success',
        'ΑΚΥΡΩΜΕΝΟ': 'danger'
    };
    return colors[status] || 'secondary';
}

/**
 * Update pagination controls
 */
function updatePagination(containerId, meta) {
    const container = document.getElementById(containerId);
    if (!container || !meta) return;
    
    const { page, total_pages, has_more } = meta;
    const currentPage = parseInt(page) || 1;
    const totalPages = parseInt(total_pages) || 1;
    
    let paginationHTML = '<nav><ul class="pagination pagination-sm justify-content-center">';
    
    // Previous button
    paginationHTML += `
        <li class="page-item ${currentPage <= 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changePage(${currentPage - 1}, '${containerId}'); return false;">
                <i class="bi bi-chevron-left"></i>
            </a>
        </li>
    `;
    
    // Page numbers 
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);
    
    for (let i = startPage; i <= endPage; i++) {
        paginationHTML += `
            <li class="page-item ${i === currentPage ? 'active' : ''}">
                <a class="page-link" href="#" onclick="changePage(${i}, '${containerId}'); return false;">${i}</a>
            </li>
        `;
    }
    
    // Next button
    paginationHTML += `
        <li class="page-item ${!has_more ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changePage(${currentPage + 1}, '${containerId}'); return false;">
                <i class="bi bi-chevron-right"></i>
            </a>
        </li>
    `;
    
    paginationHTML += '</ul></nav>';
    
    container.innerHTML = paginationHTML;
}

/**
 * Change page function for pagination
 */
function changePage(page, containerId = 'appointmentsPagination') {
    if (window.__APP_DEBUG__) console.log('Change to page:', page, 'for', containerId);
    if (!page || page < 1) return;

    // Decide which table to refresh based on containerId
    if (containerId === 'usersPagination') {
        window.currentUsersPage = page;
        if (typeof refreshUsersTable === 'function') {
            refreshUsersTable();
            return;
        }
    }
    
    if (containerId === 'vehiclesPagination') {
        window.currentVehiclesPage = page;
        if (typeof refreshVehiclesTable === 'function') {
            refreshVehiclesTable();
            return;
        }
    }

    // Default: appointments pagination
    window.currentAppointmentsPage = page;
    if (typeof window.refreshAppointmentsTable === 'function') {
        window.refreshAppointmentsTable();
    } else {
        if (window.__APP_DEBUG__) console.warn('refreshAppointmentsTable not available to change page');
    }
}


/**
 * Toggle Date/Time Edit Section
 */
function toggleDateTimeSection(appointmentId) {
    const section = document.getElementById('dateTimeEditSection');
    if (!section) {
        if (window.__APP_DEBUG__) console.warn('dateTimeEditSection not found - user role may not have access');
        return;
    }
    
    const isVisible = section.style.display !== 'none';
    
    // Hide all other sections
    hideAllActionSections();
    
    if (!isVisible) {
        // Show and populate the date/time edit form
        populateDateTimeForm(appointmentId);
        section.style.display = 'block';
    }
}

/**
 * Toggle Status Change Section
 */
function toggleStatusSection(appointmentId) {
    const section = document.getElementById('statusChangeSection');
    if (!section) {
        if (window.__APP_DEBUG__) console.warn('statusChangeSection not found - user role may not have access');
        return;
    }
    
    const isVisible = section.style.display !== 'none';
    
    hideAllActionSections();
    
    if (!isVisible) {
        populateStatusForm(appointmentId);
        section.style.display = 'block';
    }
}

/**
 * Toggle Tasks Management Section
 */
function toggleTasksSection(appointmentId) {
    const section = document.getElementById('tasksManagementSection');
    if (!section) {
        if (window.__APP_DEBUG__) console.warn('tasksManagementSection not found - user role may not have access');
        return;
    }
    
    const isVisible = section.style.display !== 'none';
    
    hideAllActionSections();
    
    if (!isVisible) {
        loadTasksManagement(appointmentId);
        section.style.display = 'block';
    }
}

/**
 * Hide all action sections
 */
function hideAllActionSections() {
    ['dateTimeEditSection', 'statusChangeSection', 'tasksManagementSection'].forEach(id => {
        const section = document.getElementById(id);
        if (section) {
            section.style.display = 'none';
        }
    });
}

/**
 * Hide individual sections
 */
function hideDateTimeSection() {
    document.getElementById('dateTimeEditSection').style.display = 'none';
}

function hideStatusSection() {
    document.getElementById('statusChangeSection').style.display = 'none';
}

function hideTasksSection() {
    document.getElementById('tasksManagementSection').style.display = 'none';
}

/**
 * Populate Date/Time Edit Form
 */
function populateDateTimeForm(appointmentId) {
    // Get current appointment data from the view modal
    const currentDate = document.getElementById('view_appointment_date').textContent.trim();
    const currentTime = document.getElementById('view_appointment_time').textContent.trim();
    
    // Set form values
    document.getElementById('datetime_appointment_id').value = appointmentId;
    
    // Convert date format if needed (assuming it's in readable format like DD/MM/YYYY or YYYY-MM-DD)
    const dateInput = document.getElementById('edit_appointment_date');
    if (currentDate) {
        try {
            let dateValue = '';
            
            // Check if it's already in YYYY-MM-DD format
            if (currentDate.match(/^\d{4}-\d{2}-\d{2}$/)) {
                dateValue = currentDate;
            } else {
                // Try to parse various date formats
                const parsedDate = parseLocalDateString(currentDate);
                if (parsedDate) {
                    dateValue = formatLocalDate(parsedDate);
                }
            }
            
            if (dateValue) {
                dateInput.value = dateValue;
                if (window.__APP_DEBUG__) console.log('[Edit DateTime] Set date to:', dateValue);
            }
            } catch (e) {
            if (window.__APP_DEBUG__) console.warn('[Edit DateTime] Could not parse current date:', currentDate, e);
        }
    }
    
    // Remove any existing event listeners to avoid duplicates
    const newDateInput = dateInput.cloneNode(true);
    dateInput.parentNode.replaceChild(newDateInput, dateInput);
    
    // Add date change event listener (same logic as appointment creation)
    newDateInput.addEventListener('change', () => {
    if (window.__APP_DEBUG__) console.log('[Edit DateTime] Date changed to:', newDateInput.value);
        loadAvailableTimeSlots();
    });
    
    // Load available time slots immediately if we have a date
    if (newDateInput.value) {
    if (window.__APP_DEBUG__) console.log('[Edit DateTime] Loading slots for current appointment time:', currentTime);
        loadAvailableTimeSlots(currentTime);
    }
}

/**
 * Load available time slots for date/time editing
 */
function loadAvailableTimeSlots(selectedTime = null) {
    const dateInput = document.getElementById('edit_appointment_date');
    const timeSelect = document.getElementById('edit_appointment_time');
    
    if (!dateInput.value) {
        timeSelect.innerHTML = '<option value="">Επιλέξτε πρώτα ημερομηνία</option>';
        return;
    }
    
    // Show loading
    timeSelect.innerHTML = '<option value="">Φόρτωση διαθέσιμων ωρών...</option>';
    timeSelect.disabled = true;
    
    // Fetch available slots using the same API as appointment creation (use fetchAPI for consistent headers/credentials)
    fetchAPI(`appointments.php?action=available_slots&date=${encodeURIComponent(dateInput.value)}`)
    .then(data => {
        if (window.__APP_DEBUG__) console.log('[Edit Slots] Response for date', dateInput.value, data);
        timeSelect.innerHTML = '<option value="">Επιλέξτε Ώρα</option>';

        if (data && data.success && data.data && data.data.length > 0) {
            data.data.forEach(slot => {
                const option = document.createElement('option');
                option.value = slot.time;
                option.textContent = slot.display + ' (Διαθέσιμοι: ' + slot.available_mechanics + ')';

                // Select the current time if it matches
                if (selectedTime === slot.time) {
                    option.selected = true;
                }

                timeSelect.appendChild(option);
            });

            timeSelect.disabled = false;
        } else {
            timeSelect.innerHTML = '<option value="">Δεν υπάρχουν διαθέσιμες ώρες</option>';
            if (window.__APP_DEBUG__) console.warn('[Edit Slots] No available slots for date:', dateInput.value);
        }
    })
    .catch(error => {
        if (window.__APP_DEBUG__) console.error('[Edit Slots] Error loading time slots:', error);
        timeSelect.innerHTML = '<option value="">Σφάλμα φόρτωσης ωρών</option>';
        timeSelect.disabled = false;
    });
}

/**
 * Populate Status Change Form
 */
function populateStatusForm(appointmentId) {
    const currentStatus = document.getElementById('view_appointment_status').textContent.trim();
    
    document.getElementById('status_appointment_id').value = appointmentId;
    document.getElementById('edit_appointment_status').value = currentStatus;
    
    if (window.__APP_DEBUG__) console.log('[Status Form] Populated with appointment ID:', appointmentId, 'current status:', currentStatus);
}

/**
 * Load Tasks Management
 */
function loadTasksManagement(appointmentId) {
    const tasksContainer = document.getElementById('tasksListManagement');
    const taskAppointmentInput = document.getElementById('task_appointment_id');
    
    // Set appointment ID for new task form
    if (taskAppointmentInput) {
        taskAppointmentInput.value = appointmentId;
    }
    
    // Show loading
    tasksContainer.innerHTML = '<div class="text-center"><div class="spinner-border spinner-border-sm" role="status"></div> Φόρτωση εργασιών...</div>';
    
    // Fetch tasks for this appointment
    fetchAPI(`tasks.php?appointment_id=${appointmentId}`)
        .then(response => {
            if (response.success) {
                renderTasksManagement(response.data.tasks || []);
            } else {
                tasksContainer.innerHTML = '<div class="alert alert-warning">Δεν βρέθηκαν εργασίες</div>';
            }
        })
        .catch(error => {
            if (window.__APP_DEBUG__) console.error('Error loading tasks:', error);
            tasksContainer.innerHTML = '<div class="alert alert-danger">Σφάλμα φόρτωσης εργασιών</div>';
        });
}

/**
 * Render tasks management interface
 */
function renderTasksManagement(tasks) {
    const container = document.getElementById('tasksListManagement');
    
    if (!tasks || tasks.length === 0) {
        container.innerHTML = '<div class="alert alert-info">Δεν υπάρχουν εργασίες ακόμη. Προσθέστε την πρώτη εργασία.</div>';
        return;
    }
    
    let html = '<div class="table-responsive"><table class="table table-sm">';
    html += '<thead><tr><th>Περιγραφή</th><th>Υλικά</th><th>Χρόνος</th><th>Κόστος</th><th>Ενέργειες</th></tr></thead><tbody>';
    
    let totalCost = 0;
    
    tasks.forEach(task => {
        totalCost += parseFloat(task.cost || 0);
        const hoursDisplay = (typeof task.completion_time !== 'undefined' && task.completion_time !== null) ? (Number(task.completion_time) / 60).toFixed(2) + ' ώρ.' : '0 ώρ.';
        html += `
            <tr>
                <td>${escapeHtml(task.description)}</td>
                <td>${escapeHtml(task.materials)}</td>
                <td>${hoursDisplay}</td>
                <td>€${parseFloat(task.cost).toFixed(2)}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="editTask(${task.id})" title="Επεξεργασία">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteTask(${task.id})" title="Διαγραφή">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    
    html += '</tbody></table></div>';
    html += `<div class="alert alert-success mt-2"><strong>Συνολικό Κόστος: €${totalCost.toFixed(2)}</strong></div>`;
    
    container.innerHTML = html;
}

/**
 * Show/Hide Add Task Form
 */
function showAddTaskForm() {
    document.getElementById('addTaskForm').style.display = 'block';
}

function hideAddTaskForm() {
    document.getElementById('addTaskForm').style.display = 'none';
    document.getElementById('createTaskForm').reset();
}

/**
 * Edit Task Function
 */
function editTask(taskId) {
    // Fetch task data and show edit form
    fetchAPI(`tasks.php?task_id=${taskId}`)
        .then(response => {
            if (response.success) {
                const task = response.data;
                
                // Fill the add task form with existing data for editing
                document.getElementById('task_description').value = task.description;
                document.getElementById('task_materials').value = task.materials;
                // Stored value is minutes; show hours (decimal) in the input for clarity
                const completionInput = document.getElementById('task_completion_time');
                if (completionInput) {
                    completionInput.value = (typeof task.completion_time !== 'undefined' && task.completion_time !== null) ? (Number(task.completion_time) / 60).toFixed(2) : '';
                }
                
                // Change form to edit mode
                const form = document.getElementById('createTaskForm');
                form.dataset.editMode = 'true';
                form.dataset.taskId = taskId;
                
                // Change button text
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="bi bi-check-lg me-2"></i>Ενημέρωση Εργασίας';
                
                showAddTaskForm();
            }
        })
        .catch(error => {
            if (window.__APP_DEBUG__) console.error('Error loading task:', error);
            if (typeof showError === 'function') {
                showError('Σφάλμα φόρτωσης εργασίας');
            }
        });
}

/**
 * Delete Task Function
 */
async function deleteTask(taskId) {
    const ok = await confirmAction({ title: 'Επιβεβαίωση', message: 'Είστε σίγουροι ότι θέλετε να διαγράψετε αυτή την εργασία;', confirmText: 'Διαγραφή', cancelText: 'Ακύρωση' });
    if (!ok) return;

    fetchAPI('tasks.php', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `task_id=${taskId}`
    })
    .then(response => {
        if (response.success) {
            // Reload tasks list
            const appointmentId = document.getElementById('task_appointment_id').value;
            loadTasksManagement(appointmentId);
            
            if (typeof showSuccess === 'function') {
                showSuccess('Εργασία διαγράφηκε επιτυχώς');
            }
        } else {
            if (typeof showError === 'function') {
                showError(response.message || 'Σφάλμα διαγραφής εργασίας');
            }
        }
    })
    .catch(error => {
        if (window.__APP_DEBUG__) console.error('Error deleting task:', error);
        if (typeof showError === 'function') {
            showError('Σφάλμα δικτύου');
        }
    });
}

/**
 * Small, safe HTML escaper used for rendering table content.
 */
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text ? text.replace(/[&<>"']/g, m => map[m]) : '';
}



/**
 * I don't understand how this works. For some reason JS is one hour behind? I dont understand.
 * I was data testing on midnight and the date was parsed as the previous day.
 * 
 * Parse the localdatestring in this function to globalize time, maybe this fixes it?
 *
 */
function parseLocalDateString(s) {
    if (!s) return null;
    // ISO-like date: YYYY-MM-DD
    const iso = s.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (iso) {
        return new Date(Number(iso[1]), Number(iso[2]) - 1, Number(iso[3]));
    }

    // Common European format: DD/MM/YYYY
    const dmy = s.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    if (dmy) {
        return new Date(Number(dmy[3]), Number(dmy[2]) - 1, Number(dmy[1]));
    }

    // Fallback to Date constructor (best-effort)
    const d = new Date(s);
    return isNaN(d) ? null : d;
}

/**
 * Format a Date instance as YYYY-MM-DD using local date components.
 */
function formatLocalDate(date) {
    if (!date || !(date instanceof Date)) return '';
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}


// Initialize when DOM is ready. 
document.addEventListener('DOMContentLoaded', () => {
    initModalForms();
    initAppointmentModalForms();
    if (window.__APP_DEBUG__) console.log('[forms.js] Modal forms initialized; appointment creation forms should be initialized by page scripts');
});

/**
 * Initialize appointment modal forms
 */
function initAppointmentModalForms() {
    // Date/Time update form
    const dateTimeForm = document.getElementById('updateDateTimeForm');
    if (dateTimeForm) {
        dateTimeForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(dateTimeForm);
            const appointmentId = formData.get('appointment_id');
            
            // Convert FormData to JSON for PUT request
            const data = {
                action: 'update_datetime',
                appointment_id: appointmentId,
                appointment_date: formData.get('appointment_date'),
                appointment_time: formData.get('appointment_time')
            };
            
            try {
                const response = await fetchAPI('appointments.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                if (response.success) {
                    if (typeof showSuccess === 'function') {
                        showSuccess('Ημερομηνία/ώρα ενημερώθηκε επιτυχώς');
                    }
                    // Refresh the appointment view without creating new modal instance
                    setTimeout(() => {
                        viewAppointment(appointmentId);
                        hideDateTimeSection();
                        // Clean up any stray backdrops
                        cleanupModalBackdrops();
                    }, 100);
                } else {
                    if (typeof showError === 'function') {
                        showError(response.message || 'Σφάλμα ενημέρωσης');
                    }
                }
            } catch (error) {
                console.error('Error updating date/time:', error);
                if (typeof showError === 'function') {
                    showError('Σφάλμα δικτύου');
                }
            }
        });
    }
    
    // Status update form
    const statusForm = document.getElementById('updateStatusForm');
    if (statusForm) {
        statusForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(statusForm);
            const appointmentId = formData.get('appointment_id');
            
            // Convert FormData to JSON for PUT request
            const data = {
                action: 'update_status',
                appointment_id: appointmentId,
                status: formData.get('status')
            };
            
            try {
                const response = await fetchAPI('appointments.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                if (response.success) {
                    if (typeof showSuccess === 'function') {
                        showSuccess('Κατάσταση ενημερώθηκε επιτυχώς');
                    }
                    // Refresh the appointment view without creating new modal instance
                    setTimeout(() => {
                        viewAppointment(appointmentId);
                        hideStatusSection();
                        // Clean up any stray backdrops
                        cleanupModalBackdrops();
                    }, 100);
                } else {
                    if (typeof showError === 'function') {
                        showError(response.message || 'Σφάλμα ενημέρωσης κατάστασης');
                    }
                }
            } catch (error) {
                console.error('Error updating status:', error);
                if (typeof showError === 'function') {
                    showError('Σφάλμα δικτύου');
                }
            }
        });
    }
    
    // Task creation/update form
    const taskForm = document.getElementById('createTaskForm');
    if (taskForm) {
        taskForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
                const formData = new FormData(taskForm);
            const appointmentId = formData.get('appointment_id');
            const isEditMode = taskForm.dataset.editMode === 'true';
            const taskId = taskForm.dataset.taskId;
            
            try {
                let response;
                
               
                const rawHours = formData.get('completion_time');
                if (rawHours !== null) {
                    const hoursNum = parseFloat(rawHours);
                    if (!isNaN(hoursNum)) {
                        const minutes = Math.round(hoursNum * 60);
                        formData.set('completion_time', String(minutes));
                    }
                }

                if (isEditMode) {
                    // Update existing task - send JSON payload for PUT
                    formData.append('task_id', taskId);
                    const payload = {};
                    for (const [k, v] of formData.entries()) payload[k] = v;
                    response = await fetchAPI('tasks.php', {
                        method: 'PUT',
                        data: payload
                    });
                } else {
                    // Create new task 
                    response = await fetchAPI('tasks.php', {
                        method: 'POST',
                        data: formData  
                    });
                }
                
                if (response.success) {
                    if (typeof showSuccess === 'function') {
                        showSuccess(isEditMode ? 'Εργασία ενημερώθηκε επιτυχώς' : 'Εργασία προστέθηκε επιτυχώς');
                    }
                    
                    // Reset form and reload tasks
                    hideAddTaskForm();
                    loadTasksManagement(appointmentId);
                    
                    // Reset edit mode
                    taskForm.dataset.editMode = 'false';
                    taskForm.dataset.taskId = '';
                    const submitBtn = taskForm.querySelector('button[type="submit"]');
                    submitBtn.innerHTML = '<i class="bi bi-plus-lg me-2"></i>Προσθήκη Εργασίας';
                } else {
                    if (typeof showError === 'function') {
                        showError(response.message || 'Σφάλμα αποθήκευσης εργασίας');
                    }
                }
            } catch (error) {
                console.error('Error saving task:', error);
                if (typeof showError === 'function') {
                    showError('Σφάλμα δικτύου');
                }
            }
        });
    }
    
    // Edit Vehicle form
    const editVehicleForm = document.getElementById('editVehicleForm');
    if (editVehicleForm) {
        editVehicleForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            // Clear previous errors
            clearFieldErrors(editVehicleForm);
            
            const formData = new FormData(editVehicleForm);
            const vehicleId = formData.get('vehicle_id');
            
            // Validate form data
            const validation = validateVehicleFormData(formData);
            if (!validation.isValid) {
                displayFieldErrors(editVehicleForm, validation.errors);
                return;
            }
            
            // Convert FormData to JSON for PUT request
            const data = {};
            for (let [key, value] of formData.entries()) {
                data[key] = value;
            }
            data.action = 'update_vehicle';
            
            try {
                showLoading(editVehicleForm);
                
                const response = await fetchAPI('vehicles.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                if (response.success) {
                    if (typeof showSuccess === 'function') {
                        showSuccess('Όχημα ενημερώθηκε επιτυχώς');
                    }
                    
                    // Refresh the vehicle view
                    viewVehicle(vehicleId);
                    cancelVehicleEdit();
                    
                    // Refresh vehicles table if it exists
                    if (typeof refreshVehiclesTable === 'function') {
                        refreshVehiclesTable();
                    }
                } else {
                    if (response.errors && typeof response.errors === 'object') {
                        displayFieldErrors(editVehicleForm, response.errors);
                    } else {
                        if (typeof showError === 'function') {
                            showError(response.message || 'Σφάλμα ενημέρωσης οχήματος');
                        }
                    }
                }
            } catch (error) {
                console.error('Error updating vehicle:', error);
                if (typeof showError === 'function') {
                    showError('Σφάλμα δικτύου');
                }
            } finally {
                hideLoading(editVehicleForm);
            }
        });
    }
}

/**
 * Validate vehicle form data
 */
function validateVehicleFormData(formData) {
    const errors = {};
    let isValid = true;
    
    // Serial number validation
    const serialNumber = formData.get('serial_number');
    if (!serialNumber || serialNumber.trim().length < 5) {
        errors.serial_number = 'Ο αριθμός κυκλοφορίας πρέπει να έχει τουλάχιστον 5 χαρακτήρες';
        isValid = false;
    }
    
    // Brand validation
    const brand = formData.get('brand');
    if (!brand || brand.trim().length < 2) {
        errors.brand = 'Η μάρκα πρέπει να έχει τουλάχιστον 2 χαρακτήρες';
        isValid = false;
    }
    
    // Model validation
    const model = formData.get('model');
    if (!model || model.trim().length < 1) {
        errors.model = 'Το μοντέλο είναι υποχρεωτικό';
        isValid = false;
    }
    
    // Door count validation
    const doorCount = parseInt(formData.get('door_count'));
    if (!doorCount || doorCount < 1 || doorCount > 6) {
        errors.door_count = 'Ο αριθμός θυρών πρέπει να είναι μεταξύ 1 και 6';
        isValid = false;
    }
    
    // Wheel count validation
    const wheelCount = parseInt(formData.get('wheel_count'));
    if (!wheelCount || wheelCount < 3 || wheelCount > 18) {
        errors.wheel_count = 'Ο αριθμός ροδών πρέπει να είναι μεταξύ 3 και 18';
        isValid = false;
    }
    
    // Production date validation
    const productionDate = formData.get('production_date');
    if (!productionDate) {
        errors.production_date = 'Η ημερομηνία παραγωγής είναι υποχρεωτική';
        isValid = false;
    } else {
        const prodDate = new Date(productionDate);
        const today = new Date();
        if (prodDate > today) {
            errors.production_date = 'Η ημερομηνία παραγωγής δεν μπορεί να είναι μελλοντική';
            isValid = false;
        }
    }
    
    // Acquisition year validation
    const acquisitionYear = parseInt(formData.get('acquisition_year'));
    const currentYear = new Date().getFullYear();
    if (!acquisitionYear || acquisitionYear < 1900 || acquisitionYear > currentYear) {
        errors.acquisition_year = `Το έτος κτήσης πρέπει να είναι μεταξύ 1900 και ${currentYear}`;
        isValid = false;
    }
    
    return { isValid, errors };
}

/**
 * Clean up any stray modal backdrops
 
 */
function cleanupModalBackdrops() {
    // Remove any orphaned modal backdrops
    const backdrops = document.querySelectorAll('.modal-backdrop');
    backdrops.forEach(backdrop => {
        // Check if this backdrop belongs to an active modal
        const activeModals = document.querySelectorAll('.modal.show');
        if (activeModals.length === 0) {
            // No active modals, remove all backdrops
            backdrop.remove();
        } else if (activeModals.length === 1) {
            // Only one active modal, should only have one backdrop
            if (backdrop !== backdrops[0]) {
                backdrop.remove();
            }
        }
    });
    
    // Reset body classes if no modals are active
    const activeModals = document.querySelectorAll('.modal.show');
    if (activeModals.length === 0) {
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    }
}

function safeHideModal(modalElement) {
    if (!modalElement) return;
    
    const modalInstance = bootstrap.Modal.getInstance(modalElement);
    if (modalInstance) {
        modalInstance.hide();
        
        // Add a cleanup after modal is hidden
        modalElement.addEventListener('hidden.bs.modal', function onHidden() {
            modalElement.removeEventListener('hidden.bs.modal', onHidden);
            setTimeout(cleanupModalBackdrops, 100);
        });
    }
}

/**
 * Smooth transition helper for table content updates
 */
function fadeOutAndReplace(element, newContent) {
    // Add loading class for smooth transition
    element.style.opacity = '0.5';
    element.style.transition = 'opacity 0.2s ease';
    
    setTimeout(() => {
        element.innerHTML = newContent;
        element.style.opacity = '1';
        
        // Trigger fade-in animation for new rows
        const newRows = element.querySelectorAll('.fade-in');
        newRows.forEach((row, index) => {
            row.style.animationDelay = `${index * 0.05}s`;
        });
    }, 150);
}

/**
 * Create loading overlay for tables
 */
function createTableLoadingOverlay(table) {
    const container = table.parentElement;
    if (!container.classList.contains('table-container')) {
        container.classList.add('table-container');
    }
    
    const overlay = document.createElement('div');
    overlay.className = 'table-loading-overlay';
    overlay.innerHTML = `
        <div class="text-center">
            <div class="spinner-border spinner-border-sm me-2" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <span>Φόρτωση...</span>
        </div>
    `;
    
    container.appendChild(overlay);
    return overlay;
}

/**
 * Show smooth loading state for table
 */
function showTableLoading(tableSelector) {
    const table = document.querySelector(tableSelector);
    if (!table) return null;
    
    const tbody = table.querySelector('tbody');
    if (tbody) {
        tbody.classList.add('loading');
    }
    
    return createTableLoadingOverlay(table);
}

// Make viewVehicle function available globally
window.viewVehicle = viewVehicle;
// Make cleanup functions available globally
window.cleanupModalBackdrops = cleanupModalBackdrops;
window.safeHideModal = safeHideModal;
// Make table refresh functions available globally
window.refreshAppointmentsTable = refreshAppointmentsTable;
window.refreshVehiclesTable = refreshVehiclesTable;
window.refreshUsersTable = refreshUsersTable;
// Make table transition helpers available globally
window.fadeOutAndReplace = fadeOutAndReplace;
window.showTableLoading = showTableLoading;

// Add global event listener for modal cleanup
document.addEventListener('DOMContentLoaded', function() {
    // Listen for all modal hide events and clean up backdrops
    document.addEventListener('hidden.bs.modal', function(event) {
        setTimeout(cleanupModalBackdrops, 100);
    });
    
    // Also clean up on page load in case there are orphaned backdrops
    setTimeout(cleanupModalBackdrops, 500);
});
