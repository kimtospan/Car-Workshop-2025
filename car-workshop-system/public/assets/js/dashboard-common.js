/**
 * Dashboard shared utilities used by all role-specific pages.
 */

// Initialize common dashboard utilities
document.addEventListener('DOMContentLoaded', function() {
    if (window.__APP_DEBUG__) console.log('[Dashboard Common] Utilities loaded');
});


/**
 * Cancel an appointment via the API and refresh relevant UI. It confirms the
 * user's intent, calls the appointments API to delete the appointment and
 * refreshes appointment lists where appropriate. 
 */
async function cancelAppointment(appointmentId, userRole) {
    const ok = await confirmAction({ title: 'Επιβεβαίωση', message: 'Είστε σίγουροι ότι θέλετε να ακυρώσετε αυτό το ραντεβού;', confirmText: 'Ναι, ακύρωσε', cancelText: 'Ακύρωση' });
    if (!ok) return;
    
    
    if (!userRole) {
        userRole = getCurrentUserRole();
    }
    
    // DELETE supports cancellation in appointments API
    fetchAPI(`appointments.php?id=${encodeURIComponent(appointmentId)}`, {
        method: 'DELETE'
    }).then(resp => {
        if (resp.success) {
            showSuccess('Το ραντεβού ακυρώθηκε');
            // Refresh tables if functions exist
            if (typeof refreshAppointmentsTable === 'function') {
                refreshAppointmentsTable();
            }
            
        } else {
            showError(resp.message || 'Αποτυχία ακύρωσης ραντεβού');
        }
    }).catch(() => showError('Σφάλμα δικτύου'));
}

/*
 * Delete vehicle - shared function for all roles
 */
async function deleteVehicle(vehicleId, userRole) {
    const ok = await confirmAction({ title: 'Επιβεβαίωση', message: 'Είστε σίγουροι ότι θέλετε να διαγράψετε αυτό το όχημα;', confirmText: 'Διαγραφή', cancelText: 'Ακύρωση' });
    if (!ok) return;
    
    // Auto-detect user role if not provided
    if (!userRole) {
        userRole = getCurrentUserRole();
    }
    
    fetchAPI('vehicles.php', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        data: { id: vehicleId }
    }).then(resp => {
        if (resp.success) {
            showSuccess('Το όχημα διαγράφηκε');
            // Refresh tables if functions exist
            if (typeof refreshVehiclesTable === 'function') {
                refreshVehiclesTable();
            }
            
        } else {
            showError(resp.message || 'Αποτυχία διαγραφής οχήματος');
        }
    }).catch(() => showError('Σφάλμα δικτύου'));
}

/**
 * Attempt to infer the current user's role from the page context.
 */
function getCurrentUserRole() {
    // Try to detect role from page context
    if (typeof SecretaryDashboard !== 'undefined') {
        return 'secretary';
    } else if (typeof CustomerDashboard !== 'undefined') {
        return 'customer';
    } else if (typeof MechanicDashboard !== 'undefined') {
        return 'mechanic';
    }
    
    // Fallback: check URL or other indicators
    const path = window.location.pathname;
    if (path.includes('secretary')) {
        return 'secretary';
    } else if (path.includes('mechanic')) {
        return 'mechanic';
    } else if (path.includes('user_dashboard') || path.includes('customer')) {
        return 'customer';
    }
    
    // Default fallback
    return 'customer';
}






function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

 // True if valid AFM format
function isValidAFM(afm) {
    return /^\d{9}$/.test((afm || '').trim());
}

function isValidIdNumber(idNumber) {
    const v = (idNumber || '').trim();
    const okLen = v.length === 8;
    const lastSix = v.slice(-6);
    const okDigits = /^\d{6}$/.test(lastSix);
    return okLen && okDigits;
}

/**
 * Initialize profile form validation and submission behavior.
 * Shared across all dashboards.
 * Enhanced to handle role-specific fields dynamically.
 */
function initProfileForm() {
    const profileForm = document.getElementById('editProfileForm');
    if (!profileForm) return;

    const userRole = profileForm.getAttribute('data-user-role');
    
    // Show role-specific fields and load data when modal is shown
    const modal = document.getElementById('editProfileModal');
    if (modal) {
        modal.addEventListener('show.bs.modal', function() {
            loadRoleSpecificFields(userRole);
        });
    }

    // Add form validation
    profileForm.addEventListener('submit', function(e) {
        e.preventDefault(); // Prevent default form submission
        
        // Scope the email input to the profile form to avoid id collisions with other modals
        const email = profileForm.querySelector('#email') || profileForm.querySelector('[name="email"]');

        // Validate email format (only validate the profile's email field)
        if (email && !isValidEmail(email.value)) {
            // Prefer per-field feedback if helpers exist
            try { if (typeof setFieldError === 'function') setFieldError(email, 'Παρακαλώ εισάγετε έγκυρη διεύθυνση email'); } catch (_) {}
            // Fallback to global alert
            showError('Παρακαλώ εισάγετε έγκυρη διεύθυνση email');
            if (typeof email.focus === 'function') email.focus();
            return false;
        }

        // Role-specific validation
        if (userRole === 'customer') {
            const afm = profileForm.querySelector('#afm');
            const address = profileForm.querySelector('#address');
            
            if (!afm || !afm.value.trim()) {
                showError('Το ΑΦΜ είναι υποχρεωτικό για πελάτες');
                if (afm && typeof afm.focus === 'function') afm.focus();
                return false;
            }
            
            if (!isValidAFM(afm.value)) {
                showError('Το ΑΦΜ πρέπει να αποτελείται από 9 ψηφία');
                if (afm && typeof afm.focus === 'function') afm.focus();
                return false;
            }
            
            if (!address || !address.value.trim()) {
                showError('Η διεύθυνση κατοικίας είναι υποχρεωτική για πελάτες');
                if (address && typeof address.focus === 'function') address.focus();
                return false;
            }
        } else if (userRole === 'mechanic') {
            const specialty = profileForm.querySelector('#specialty');
            const otherSpecialty = profileForm.querySelector('#otherSpecialty');
            
            if (!specialty || !specialty.value.trim()) {
                showError('Η ειδικότητα είναι υποχρεωτική για μηχανικούς');
                if (specialty && typeof specialty.focus === 'function') specialty.focus();
                return false;
            }
            
            if (specialty.value === 'Άλλη' && (!otherSpecialty || !otherSpecialty.value.trim())) {
                showError('Παρακαλώ αναφέρετε την ειδικότητά σας');
                if (otherSpecialty && typeof otherSpecialty.focus === 'function') otherSpecialty.focus();
                return false;
            }
        }

        // Submit form via AJAX
        submitProfileForm(profileForm, userRole);
    });
}

/**
 * Show role-specific fields and load existing data
 */
function loadRoleSpecificFields(userRole) {
    const roleSpecificSection = document.getElementById('roleSpecificFields');
    const customerFields = document.getElementById('customerFields');
    const mechanicFields = document.getElementById('mechanicFields');
    
    // Hide all role-specific sections first
    if (roleSpecificSection) roleSpecificSection.style.display = 'none';
    if (customerFields) customerFields.style.display = 'none';
    if (mechanicFields) mechanicFields.style.display = 'none';
    
    if (userRole === 'customer') {
        if (roleSpecificSection) roleSpecificSection.style.display = 'block';
        if (customerFields) customerFields.style.display = 'block';
        loadCustomerData();
    } else if (userRole === 'mechanic') {
        if (roleSpecificSection) roleSpecificSection.style.display = 'block';
        if (mechanicFields) mechanicFields.style.display = 'block';
        loadMechanicData();
    }
}

/**
 * Load customer-specific data from API
 */
function loadCustomerData() {
    fetchAPI('users.php?action=get_profile')
        .then(resp => {
            if (resp.success && resp.data) {
                const data = resp.data;
                
                // Populate customer-specific fields
                const afmField = document.getElementById('afm');
                const addressField = document.getElementById('address');
                
                if (afmField && data.afm) {
                    afmField.value = data.afm;
                }
                if (addressField && data.address) {
                    addressField.value = data.address;
                }
            }
        })
        .catch(error => {
            if (window.__APP_DEBUG__) console.error('Error loading customer data:', error);
        });
}

/**
 * Load mechanic-specific data from API
 */
function loadMechanicData() {
    fetchAPI('users.php?action=get_profile')
        .then(resp => {
            if (resp.success && resp.data) {
                const data = resp.data;
                
                // Populate mechanic-specific fields
                const specialtyField = document.getElementById('specialty');
                const otherSpecialtyField = document.getElementById('otherSpecialty');
                const otherSpecialtyContainer = document.getElementById('otherSpecialtyContainer');
                
                if (specialtyField && data.specialty) {
                    // Check if specialty matches a predefined option
                    const predefinedOptions = ['Αρχιμηχανικός', 'Ηλεκτρονικός', 'Μηχανικός κινητήρων', 
                                              'Μηχανικός ψυκτικών', 'Μηχανικός φρένων', 'Μηχανικός ανάρτησης', 
                                              'Μηχανικός υδραυλικών'];
                    
                    if (predefinedOptions.includes(data.specialty)) {
                        specialtyField.value = data.specialty;
                    } else {
                        // Custom specialty
                        specialtyField.value = 'Άλλη';
                        if (otherSpecialtyField) {
                            otherSpecialtyField.value = data.specialty;
                            otherSpecialtyField.required = true;
                        }
                        if (otherSpecialtyContainer) {
                            otherSpecialtyContainer.style.display = 'block';
                        }
                    }
                }
            }
        })
        .catch(error => {
            if (window.__APP_DEBUG__) console.error('Error loading mechanic data:', error);
        });
}

/**
 * Submit profile form via AJAX
 */
function submitProfileForm(form, userRole) {
    // Convert form data to URLSearchParams for reliable API communication
    const formData = new FormData(form);
    const urlParams = new URLSearchParams();
    
    // Copy all form data to URLSearchParams
    for (const [key, value] of formData.entries()) {
        urlParams.append(key, value);
    }
    
    // Handle specialty field for mechanics
    if (userRole === 'mechanic') {
        const specialty = form.querySelector('#specialty');
        const otherSpecialty = form.querySelector('#otherSpecialty');
        
        if (specialty && specialty.value === 'Άλλη' && otherSpecialty && otherSpecialty.value.trim()) {
            urlParams.set('specialty', otherSpecialty.value.trim());
        }
    }
    
    // Add action for API processing
    urlParams.set('action', 'update_profile');
    
    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Αποθήκευση...';
    }
    
    fetchAPI('users.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: urlParams.toString()
    })
    .then(resp => {
        if (resp.success) {
            showSuccess(resp.message || 'Το προφίλ ενημερώθηκε επιτυχώς');
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('editProfileModal'));
            if (modal) {
                modal.hide();
            }
            
            // Optionally refresh user display
            if (typeof updateUserDisplay === 'function') {
                updateUserDisplay();
            }
        } else {
            showError(resp.message || 'Αποτυχία ενημέρωσης προφίλ');
            
            // Handle validation errors
            if (resp.errors) {
                Object.entries(resp.errors).forEach(([field, message]) => {
                    const fieldElement = form.querySelector(`[name="${field}"]`);
                    if (fieldElement && typeof setFieldError === 'function') {
                        setFieldError(fieldElement, message);
                    }
                });
            }
        }
    })
    .catch(error => {
        if (window.__APP_DEBUG__) console.error('Profile update error:', error);
        showError('Σφάλμα δικτύου κατά την ενημέρωση του προφίλ');
    })
    .finally(() => {
        // Restore button state
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
}


 


function debounce(func, wait) {
    try {
        if (typeof window !== 'undefined' && typeof window.debounce === 'function') {
            return window.debounce(func, wait);
        }
    } catch (e) {
        if (window.__APP_DEBUG__) console.error('Debounce delegation error:', e);
    }

    // This keeps failing so just implement it again. Maybe app.js loads too late
    let timeout;
    return function executedFunction(...args) {
        const context = this;
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(context, args), wait || 200);
    };
}






function safeShowModal(modalSelector) {
    const modal = typeof modalSelector === 'string' ? 
        document.querySelector(modalSelector) : modalSelector;
    
    if (!modal) {
        if (window.__APP_DEBUG__) console.error('Modal not found:', modalSelector);
        return;
    }
    
    // Clean up any stray backdrops before showing
    if (typeof cleanupModalBackdrops === 'function') {
        cleanupModalBackdrops();
    }
    
    // Get or create modal instance
    let modalInstance = bootstrap.Modal.getInstance(modal);
    if (!modalInstance) {
        modalInstance = new bootstrap.Modal(modal);
    }
    
    modalInstance.show();
}


/**
 * Refresh the visible user/profile information in the UI (header and
 * profile header). Calls the users API and updates name, email and role
 * where applicable. 
 */
function updateUserDisplay() {
    fetchAPI('users.php?action=get_profile')
        .then(resp => {
            if (!resp || !resp.success || !resp.data) return;
            const data = resp.data || {};
            const fullName = ((data.first_name || '') + ' ' + (data.last_name || '')).trim();

            // Update profile header (main dashboard area)
            const profileH4 = document.querySelector('.portfolio-profile-container h4');
            if (profileH4) profileH4.textContent = fullName || profileH4.textContent;

            // Update profile email line (keeps envelope icon)
            const profileEmail = document.querySelector('.portfolio-profile-container .text-white-50');
            if (profileEmail) {
                profileEmail.innerHTML = `<i class="fa-solid fa-envelope me-1"></i> ${data.email || ''}`;
            }

            // Update role badge text if present
            const profileBadge = document.querySelector('.portfolio-profile-container .badge');
            if (profileBadge) {
                const roleText = (data.role || '').toString();
                profileBadge.textContent = roleText ? roleText.charAt(0).toUpperCase() + roleText.slice(1) : profileBadge.textContent;
            }

            // Update header navigation user name
            const headerName = document.querySelector('header .fw-bold.text-dark.small.text-truncate');
            if (headerName) headerName.textContent = data.full_name || fullName || headerName.textContent;

            // If an avatar element exists update it
            const headerAvatarImg = document.getElementById('headerAvatar') || document.querySelector('.header-avatar img');
            if (headerAvatarImg && data.avatar_url) {
                try {
                    headerAvatarImg.src = data.avatar_url;
                } catch (err) {
                    if (window.__APP_DEBUG__) console.error('Failed to update header avatar src', err);
                }
            }
        })
        .catch(err => {
            if (window.__APP_DEBUG__) console.error('Failed to refresh user display:', err);
        });
}


// Export all shared functions to global scope
window.DashboardCommon = {
    // API Operations
    cancelAppointment,
    deleteVehicle,
    
    // UI Utilities (delegated to app.js)
    
    // Profile form initializer
    initProfileForm,
    
    // Validation
    isValidEmail,
    isValidAFM,
    isValidIdNumber,
    
    // Utilities (debounce delegated to app.js)
    safeShowModal,
    updateUserDisplay,
    getCurrentUserRole
};

// Also make individual functions available globally for backward compatibility
window.cancelAppointment = cancelAppointment;
window.deleteVehicle = deleteVehicle;
window.isValidEmail = isValidEmail;
window.isValidAFM = isValidAFM;
window.isValidIdNumber = isValidIdNumber;

window.safeShowModal = safeShowModal;
window.getCurrentUserRole = getCurrentUserRole;
// Backwards-compatible global reference
window.initProfileForm = initProfileForm;
// Expose updateUserDisplay globally so profile submitters can call it
window.updateUserDisplay = updateUserDisplay;

if (window.__APP_DEBUG__) console.log('[Dashboard Common] All shared utilities exported to global scope');
