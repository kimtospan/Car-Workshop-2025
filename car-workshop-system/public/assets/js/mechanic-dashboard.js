/**
 * Mechanic dashboard behaviour and utilities. 
 *
 * Search/refresh pattern:
 * - Filters and search inputs are debounced and call centralized refresh functions
 * - Those refresh functions call the API and update page tables via shared renderers
 */


// Initialize mechanic dashboard functionality
document.addEventListener('DOMContentLoaded', function() {
    try {
        if (window.MechanicDashboardConfig && window.MechanicDashboardConfig.mechanicId) {
            // Prevent multiple initialization
            if (window.mechanicDashboardInitialized) {
                if (window.__APP_DEBUG__) console.warn('[Mechanic] Dashboard already initialized, skipping...');
                return;
            }
            window.mechanicDashboardInitialized = true;
            initMechanicDashboard();
        } else {
            if (window.__APP_DEBUG__) console.warn('[Mechanic] No mechanic profile found - dashboard disabled');
        }
    } catch (e) {
        if (window.__APP_DEBUG__) console.error('[Mechanic] Dashboard initialization failed:', e);
    }
});

function initMechanicDashboard() {
    if (window.__APP_DEBUG__) console.groupCollapsed('[Mechanic] init');
    
    // Initialize search and filtering
    initSearchAndFiltering();
    
    // Initial table loads 
    if (window.__APP_DEBUG__) console.log('[Mechanic] Loading initial appointments table...');
    window.refreshAppointmentsTable();
    window.initProfileForm();
    if (window.__APP_DEBUG__) console.groupEnd();
}

function initSearchAndFiltering() {
    // Appointment status filter functionality
    const appointmentStatusFilter = document.getElementById('appointment_status_filter');
    
    if (appointmentStatusFilter) {
        // Remove any existing event listeners to prevent duplicates
        appointmentStatusFilter.removeEventListener('change', handleStatusFilterChange);
        appointmentStatusFilter.addEventListener('change', handleStatusFilterChange);
    }
    
    // Wire date range filters so mechanics trigger server-side filtering immediately
    const dateFrom = document.getElementById('date_from_filter');
    const dateTo = document.getElementById('date_to_filter');

    if (dateFrom) {
        dateFrom.removeEventListener('change', handleDateFromChange);
        dateFrom.addEventListener('change', handleDateFromChange);
    }

    if (dateTo) {
        dateTo.removeEventListener('change', handleDateToChange);
        dateTo.addEventListener('change', handleDateToChange);
    }
}

// Separate event handler functions to enable proper removal
function handleStatusFilterChange() {
    if (window.__APP_DEBUG__) console.log('[Mechanic] Status filter changed:', this.value);
    if (window.__APP_DEBUG__) console.log('[Mechanic] Calling refreshAppointmentsTable...');
    // Reset to first page when applying a filter
    window.currentAppointmentsPage = 1;
    if (typeof window.refreshAppointmentsTable === 'function') {
        window.refreshAppointmentsTable();
    } else {
                if (window.__APP_DEBUG__) console.error('[Mechanic] refreshAppointmentsTable function not found in window object');
    }
}

function handleDateFromChange() {
    if (window.__APP_DEBUG__) console.log('[Mechanic] Date from filter changed:', this.value);
    if (window.__APP_DEBUG__) console.log('[Mechanic] Calling refreshAppointmentsTable...');
    // Reset to first page when applying a filter
    window.currentAppointmentsPage = 1;
    if (typeof window.refreshAppointmentsTable === 'function') {
        window.refreshAppointmentsTable();
    } else {
    if (window.__APP_DEBUG__) console.error('[Mechanic] refreshAppointmentsTable function not found in window object');
    }
}

function handleDateToChange() {
    if (window.__APP_DEBUG__) console.log('[Mechanic] Date to filter changed:', this.value);
    if (window.__APP_DEBUG__) console.log('[Mechanic] Calling refreshAppointmentsTable...');
    // Reset to first page when applying a filter
    window.currentAppointmentsPage = 1;
        if (typeof window.refreshAppointmentsTable === 'function') {
        window.refreshAppointmentsTable();
    } else {
        if (window.__APP_DEBUG__) console.error('[Mechanic] refreshAppointmentsTable function not found in window object');
    }
}

/**
 * Start appointment - mechanic-specific function
 * Changes appointment status to ΣΕ ΕΞΕΛΙΞΗ

 * Mark an appointment as started on behalf of a mechanic.
 * called when a mechanic begins working on an appointment. It
 * updates the appointment status via the API and refreshes visible tables.
 */
async function startAppointment(appointmentId) {
    if (!appointmentId) {
        showError('Μη έγκυρο αναγνωριστικό ραντεβού');
        return;
    }

    const okStart = await confirmAction({ title: 'Επιβεβαίωση', message: 'Είστε σίγουροι ότι θέλετε να ξεκινήσετε αυτό το ραντεβού;', confirmText: 'Ναι, ξεκίνα', cancelText: 'Ακύρωση' });
    if (!okStart) return;

    try {
        const payload = {
            action: 'update_status',
            appointment_id: parseInt(appointmentId),
            status: 'ΣΕ ΕΞΕΛΙΞΗ'
        };

        const response = await fetchAPI('appointments.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        if (response.success) {
            showSuccess('Το ραντεβού ξεκίνησε επιτυχώς');
            window.refreshAppointmentsTable();
            
            // Close modal if open
            const modal = bootstrap.Modal.getInstance(document.getElementById('viewAppointmentModal'));
            if (modal) {
                modal.hide();
            }
        } else {
            showError(response.message || 'Σφάλμα κατά την έναρξη του ραντεβού');
        }
    } catch (error) {
        if (window.__APP_DEBUG__) console.error('[Mechanic] Error starting appointment:', error);
        showError('Σφάλμα δικτύου. Παρακαλώ δοκιμάστε ξανά.');
    }
}

/**
 * Complete appointment - mechanic-specific function
 * Changes appointment status to ΠΕΡΑΙΩΜΕΝΟ
 */
async function completeAppointment(appointmentId) {
    if (!appointmentId) {
        showError('Μη έγκυρο αναγνωριστικό ραντεβού');
        return;
    }

    const okComplete = await confirmAction({ title: 'Επιβεβαίωση', message: 'Είστε σίγουροι ότι θέλετε να ολοκληρώσετε αυτό το ραντεβού;', confirmText: 'Ναι, ολοκλήρωσε', cancelText: 'Ακύρωση' });
    if (!okComplete) return;

    try {
        const payload = {
            action: 'update_status',
            appointment_id: parseInt(appointmentId),
            status: 'ΠΕΡΑΙΩΜΕΝΟ'
        };

        const response = await fetchAPI('appointments.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        if (response.success) {
            showSuccess('Το ραντεβού ολοκληρώθηκε επιτυχώς');
            window.refreshAppointmentsTable();
            
            // Close modal if open
            const modal = bootstrap.Modal.getInstance(document.getElementById('viewAppointmentModal'));
            if (modal) {
                modal.hide();
            }
        } else {
            showError(response.message || 'Σφάλμα κατά την ολοκλήρωση του ραντεβού');
        }
    } catch (error) {
        if (window.__APP_DEBUG__) console.error('[Mechanic] Error completing appointment:', error);
        showError('Σφάλμα δικτύου. Παρακαλώ δοκιμάστε ξανά.');
    }
}

async function addTaskToAppointment(appointmentId) {
    if (!appointmentId) {
        showError('Μη έγκυρο αναγνωριστικό ραντεβού');
        return;
    }
    
    // Use the task creation flow from forms.js
    try {
        const taskForm = document.getElementById('createTaskForm');
        if (!taskForm) {
            showError('Η φόρμα εργασιών δεν είναι διαθέσιμη. Παρακαλώ ανανεώστε τη σελίδα.');
            return;
        }

        // Prepare form for creation mode
        taskForm.dataset.editMode = 'false';
        taskForm.dataset.taskId = '';
        const appointmentInput = document.getElementById('task_appointment_id');
        if (appointmentInput) appointmentInput.value = appointmentId;

        // Reset and set submit button text
        taskForm.reset();
        if (appointmentInput) appointmentInput.value = appointmentId; 
        
        const submitBtn = taskForm.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.innerHTML = '<i class="bi bi-plus-lg me-2"></i>Προσθήκη Εργασίας';

        // Show the add task form area and focus on description
        if (typeof showAddTaskForm === 'function') {
            showAddTaskForm();
        } else {
            const addTaskForm = document.getElementById('addTaskForm');
            if (addTaskForm) addTaskForm.style.display = 'block';
        }
        
        const desc = document.getElementById('task_description');
        if (desc) {
            setTimeout(() => desc.focus(), 100); // Small delay to ensure form is visible
        }

    } catch (error) {
        if (window.__APP_DEBUG__) console.error('[Mechanic] Error preparing add task form:', error);
        showError('Σφάλμα προετοιμασίας φόρμας εργασίας');
    }
}





// Export functions for global access
window.MechanicDashboard = {
    refreshAppointmentsTable: function() {
        if (typeof window.refreshAppointmentsTable === 'function') {
            window.refreshAppointmentsTable();
        }
    },
    startAppointment,
    completeAppointment,
    addTaskToAppointment
};

// Make individual functions available globally for onclick handlers
window.startAppointment = startAppointment;
window.completeAppointment = completeAppointment;
window.addTaskToAppointment = addTaskToAppointment;
