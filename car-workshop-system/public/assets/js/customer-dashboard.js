/**
 * Client-side behaviour for the customer dashboard.
 *
 * Search/refresh pattern:
 * - Input elements are debounced on 'input'
 * - Debounced handlers call shared refresh functions (e.g. refreshAppointmentsTable)
 * - Shared refresh functions assemble query params and call the API, then update the DOM
 */

// Initialize customer dashboard functionality
document.addEventListener('DOMContentLoaded', function() {
    try {
        initCustomerDashboard();
    } catch (e) {
        if (window.__APP_DEBUG__) console.error('Error initializing customer dashboard:', e);
    }
});

function initCustomerDashboard() {
    if (window.__APP_DEBUG__) console.groupCollapsed('[Customer] init');
    
    // Initialize form handling 
    initFormHandling();
    
    // Initialize search and filtering
    initSearchAndFiltering();
    
    // Initial table loads 
    window.refreshAppointmentsTable();
    window.refreshVehiclesTable();
    window.initProfileForm();
    if (window.__APP_DEBUG__) console.groupEnd();
}

function initFormHandling() {
    // Initialize appointment form using forms.js 
    window.initAppointmentForm('customerAppointmentForm', {
        endpoint: 'appointments.php',
        onSuccess: (response) => {
            window.refreshAppointmentsTable();
        },
        onError: (error) => {
            showError('Σφάλμα κατά το κλείσιμο του ραντεβού: ' + error.message);
        }
    });

    // Initialize vehicle form using forms.js
    if (typeof window.initVehicleForm === 'function') {
        window.initVehicleForm('customerVehicleForm', {
            endpoint: 'vehicles.php',
            onSuccess: (response) => {
                if (typeof window.refreshVehiclesTable === 'function') window.refreshVehiclesTable();
            },
            onError: (error) => {
                showError('Σφάλμα κατά την προσθήκη του οχήματος: ' + error.message);
            }
        });
    } else {
        if (window.__APP_DEBUG__) console.error('forms.js initVehicleForm not available');
    }
}

function initSearchAndFiltering() {
    // Appointment search functionality
    const appointmentSearch = document.getElementById('appointment_search');
    const appointmentStatusFilter = document.getElementById('appointment_status_filter');
    
    if (appointmentSearch) {
        let searchTimeout;
        appointmentSearch.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                refreshAppointmentsTable();
                // If client-side rows are present, apply current status/date filters after refresh
                setTimeout(() => {
                    const status = appointmentStatusFilter ? appointmentStatusFilter.value : '';
                    if (status) filterAppointmentsByStatus(status);
                    applyDateFilters();
                }, 250);
            }, 300);
        });
    }
    
    if (appointmentStatusFilter) {
        appointmentStatusFilter.addEventListener('change', function() {
            // Prefer server-side filtered refresh, then try client-side row filtering if rows exist
            refreshAppointmentsTable();
            setTimeout(() => {
                filterAppointmentsByStatus(this.value);
            }, 200);
        });
    }
    
    // Vehicle search functionality
    const vehicleSearch = document.getElementById('vehicle_search');
    if (vehicleSearch) {
        let searchTimeout;
        vehicleSearch.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                refreshVehiclesTable();
            }, 300);
        });
    }

    // Vehicle filter (sort) - trigger vehicle table refresh when changed
    const vehicleFilter = document.getElementById('vehicle_filter');
    if (vehicleFilter) {
        vehicleFilter.addEventListener('change', function() {
            // Server-side refresh will apply the selected filter/sort
            if (typeof refreshVehiclesTable === 'function') {
                refreshVehiclesTable();
            }
        });
    }

    // Wire date range filters so customers trigger server-side filtering immediately
    const dateFrom = document.getElementById('date_from_filter');
    const dateTo = document.getElementById('date_to_filter');

    if (dateFrom) {
        dateFrom.addEventListener('change', function() {
            // Server-side refresh, then apply any client-side date filtering as a fallback
            refreshAppointmentsTable();
            setTimeout(() => {
                applyDateFilters();
            }, 200);
        });
    }

    if (dateTo) {
        dateTo.addEventListener('change', function() {
            refreshAppointmentsTable();
            setTimeout(() => {
                applyDateFilters();
            }, 200);
        });
    }
}


/**
 * Narrow the currently rendered appointment rows to a specific status
 */
function filterAppointmentsByStatus(status) {
    if (!status) return;

    const selectors = [
        '#appointmentManagementModal .table tbody tr',
        '#appointmentsTable tbody tr',
        '.appointments-table tbody tr',
        '#appointments .table tbody tr'
    ];

    for (const sel of selectors) {
        const rows = document.querySelectorAll(sel);
        if (!rows || rows.length === 0) continue;

        rows.forEach(row => {
            try {
                const badge = row.querySelector('.badge');
                const text = badge ? badge.textContent.trim() : '';
                row.style.display = (!status || text === status) ? '' : 'none';
            } catch (e) {
                // ignore rows that don't match expected structure
            }
        });

        // If we found rows for a selector, stop — we've applied the filter
        return;
    }
}


/**
 * Client-side date-range filter for appointment rows.
 * Looks for date_from_filter / date_to_filter inputs and a table.
 */
function applyDateFilters() {
    const dateFrom = document.getElementById('date_from_filter');
    const dateTo = document.getElementById('date_to_filter');

    if (!dateFrom && !dateTo) return;

    const fromDate = dateFrom && dateFrom.value ? parseLocalDateString(dateFrom.value) : null;
    const toDate = dateTo && dateTo.value ? parseLocalDateString(dateTo.value) : null;

    const selectors = [
        '#appointmentManagementModal .table tbody tr',
        '#appointmentsTable tbody tr',
        '.appointments-table tbody tr',
        '#appointments .table tbody tr'
    ];

    for (const sel of selectors) {
        const rows = document.querySelectorAll(sel);
        if (!rows || rows.length === 0) continue;

        rows.forEach(row => {
            try {
                // Try to find a date cell — prefer data attributes, fallback to cell[2]
                let dateText = '';
                const dateCell = row.querySelector('[data-appointment-date]') || row.cells[2] || null;
                if (dateCell) dateText = dateCell.textContent.trim();

                const appointmentDate = dateText ? parseLocalDateString(dateText) : null;
                let showRow = true;
                if (fromDate && appointmentDate && appointmentDate < fromDate) showRow = false;
                if (toDate && appointmentDate && appointmentDate > toDate) showRow = false;
                row.style.display = showRow ? '' : 'none';
            } catch (e) {
                // ignore structure mismatches
            }
        });

        return;
    }
}


// Sanity check: log whether the canonical functions from forms.js are present.
document.addEventListener('DOMContentLoaded', () => {
    if (window.__APP_DEBUG__) console.info('customer-dashboard sanity check:', {
        initAppointmentForm: typeof window.initAppointmentForm === 'function',
        initVehicleForm: typeof window.initVehicleForm === 'function',
        refreshAppointmentsTable: typeof window.refreshAppointmentsTable === 'function',
        refreshVehiclesTable: typeof window.refreshVehiclesTable === 'function'
    });
});

function showBookAppointmentModal() {
    // Use the shared function to show the modal
    try {
        const modalEl = document.getElementById('newAppointmentModal');
        if (!modalEl) {
            showError('Δεν βρέθηκε το modal ραντεβού');
            return;
        }
        
        // Clean up any stray backdrops before showing modal
        if (typeof cleanupModalBackdrops === 'function') {
            cleanupModalBackdrops();
        }
        
        // Get or create modal instance (avoid creating multiple instances cough cough)
        let modal = bootstrap.Modal.getInstance(modalEl);
        if (!modal) {
            modal = new bootstrap.Modal(modalEl);
        }
        modal.show();
    } catch (error) {
        if (window.__APP_DEBUG__) console.error('Error showing appointment modal:', error);
        showError('Σφάλμα κατά το άνοιγμα του modal ραντεβού');
    }
}

function bookAppointmentForSelf() {
    showBookAppointmentModal();
}

// Export functions for global access
window.CustomerDashboard = {
    refreshAppointmentsTable: function() {
        if (typeof window.refreshAppointmentsTable === 'function') return window.refreshAppointmentsTable();
        if (window.__APP_DEBUG__) console.error('forms.js refreshAppointmentsTable not available');
    },
    refreshVehiclesTable: function() {
        if (typeof window.refreshVehiclesTable === 'function') return window.refreshVehiclesTable();
        if (window.__APP_DEBUG__) console.error('forms.js refreshVehiclesTable not available');
    },
    showBookAppointmentModal,
    bookAppointmentForSelf
};

// Make individual functions available globally for onclick handlers
window.showBookAppointmentModal = showBookAppointmentModal;
window.bookAppointmentForSelf = bookAppointmentForSelf;
