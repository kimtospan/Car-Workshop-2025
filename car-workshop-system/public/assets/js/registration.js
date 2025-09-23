
class RegistrationForm {
    constructor(formSelector) {
        this.form = document.querySelector(formSelector);
        this.submitButton = this.form.querySelector('button[type="submit"]');
        this.originalButtonText = this.submitButton.textContent;
        this.roleSpecificFields = document.getElementById('roleSpecificFields');
        
        this.init();
    }
    
    init() {
        if (!this.form) {
            if (window.__APP_DEBUG__) console.error('Registration form not found');
            return;
        }
        
        // Bind event listeners
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));
        
        // Role selection handling
        this.setupRoleSelection();
        
        // Real-time validation
        this.setupRealTimeValidation();
        
        // Password strength indicator
        this.setupPasswordStrength();
        
        // Check for existing username/email
        this.setupAvailabilityChecks();
    }
    
    /**
     * Wire the role selection UI to show the right extra fields.
     *
     * This method connects simple card-click and radio behaviours to the
     * form so the user sees customer- or mechanic-specific inputs
     * immediately after choosing a role.
     */
    setupRoleSelection() {
        const roleCards = document.querySelectorAll('[data-role]');
        const roleInputs = document.querySelectorAll('input[name="role"]');
        
        // Handle role card clicks
        roleCards.forEach(card => {
            card.addEventListener('click', () => {
                if (window.__APP_DEBUG__) console.log('Registration role card clicked');
                const radioInput = card.querySelector('input[type="radio"]');
                
                // Update radio selection
                radioInput.checked = true;
                
                // Update visual state
                roleCards.forEach(c => {
                    c.classList.remove('border-primary', 'bg-light');
                    c.classList.add('border-2');
                });
                card.classList.remove('border-2');
                card.classList.add('border-primary', 'bg-light');
                
                // Show role-specific fields
                this.showRoleSpecificFields(radioInput.value);
            });
        });
        
        // Handle direct radio clicks
        roleInputs.forEach(input => {
            input.addEventListener('change', () => {
                if (input.checked) {
                    const role = input.value;
                    
                    // Update card selection
                    roleCards.forEach(c => {
                        c.classList.remove('border-primary', 'bg-light');
                        c.classList.add('border-2');
                    });
                    const selectedCard = document.querySelector(`[data-role="${role}"]`);
                    if (selectedCard) {
                        selectedCard.classList.remove('border-2');
                        selectedCard.classList.add('border-primary', 'bg-light');
                    }
                    
                    this.showRoleSpecificFields(role);
                }
            });
        });
    }
    
    showRoleSpecificFields(role) {
        if (!this.roleSpecificFields) return;
        
        let fieldsHtml = '';
        
        if (role === 'customer') {
            fieldsHtml = this.getCustomerFields();
        } else if (role === 'mechanic') {
            fieldsHtml = this.getMechanicFields();
        }
        
        this.roleSpecificFields.innerHTML = fieldsHtml;
        this.roleSpecificFields.className = 'role-specific-fields';
        
        // Re-setup validation for new fields
        this.setupRoleFieldValidation();
    }
    
    getCustomerFields() {
        return `
            <div class="card bg-light border-0 mb-3">
                <div class="card-body">
                    <h6 class="card-title text-primary mb-3">
                        <i class="bi bi-person-badge me-2"></i>Στοιχεία Πελάτη
                    </h6>
                    
                    <!-- AFM Field -->
                    <div class="mb-3">
                        <label for="afm" class="form-label fw-semibold">
                            ΑΦΜ *
                        </label>
                        <input 
                            type="text" 
                            class="form-control form-control-lg border-2" 
                            id="afm" 
                            name="afm" 
                            pattern="[0-9]{9}"
                            placeholder="9 ψηφία"
                            maxlength="9"
                            required
                        >
                        <div class="form-text">Απαιτείται για έκδοση παραστατικών</div>
                    </div>

                    <!-- Address Field -->
                    <div class="mb-0">
                        <label for="address" class="form-label fw-semibold">
                            Διεύθυνση *
                        </label>
                        <textarea 
                            class="form-control border-2" 
                            id="address" 
                            name="address" 
                            rows="3"
                            placeholder="Εισάγετε πλήρη διεύθυνση κατοικίας"
                            required
                        ></textarea>
                    </div>
                </div>
            </div>
        `;
    }
    
    getMechanicFields() {
        const specialties = [
            'Αρχιμηχανικός',
            'Ηλεκτρολόγος',
            'Ηλεκτρονικός',
            'Μηχανικός Κινητήρων',
            'Ειδικός Φρένων',
            'Ειδικός Ανάρτησης',
            'Βαφέας',
            'Λαμαρινάς',
            'Άλλο'
        ];
        
        return `
            <div class="card bg-light border-0 mb-3">
                <div class="card-body">
                    <h6 class="card-title text-warning mb-3">
                        <i class="bi bi-tools me-2"></i>Στοιχεία Μηχανικού
                    </h6>
                    
                    <!-- Specialty Field -->
                    <div class="mb-0">
                        <label class="form-label fw-semibold">
                            Ειδικότητα *
                        </label>
                        <div class="row g-2">
                            ${specialties.map(specialty => `
                                <div class="col-md-6 col-lg-4">
                                    <div class="border rounded p-2" data-specialty="${specialty}" style="cursor: pointer;">
                                        <div class="form-check">
                                            <input 
                                                class="form-check-input" 
                                                type="radio" 
                                                name="specialty" 
                                                id="specialty_${specialty.toLowerCase().replace(/\s+/g, '_')}" 
                                                value="${specialty}"
                                                required
                                            >
                                            <label class="form-check-label fw-semibold" for="specialty_${specialty.toLowerCase().replace(/\s+/g, '_')}">
                                                ${specialty}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                        
                        <!-- Custom specialty input for "Άλλο" -->
                        <div id="customSpecialtyField" class="mt-3 d-none">
                            <input 
                                type="text" 
                                class="form-control form-control-lg border-2" 
                                id="custom_specialty" 
                                name="custom_specialty"
                                placeholder="Εισάγετε την ειδικότητά σας"
                            >
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    setupRoleFieldValidation() {
        // Specialty selection for mechanics
        const specialtyOptions = document.querySelectorAll('[data-specialty]');
        const customSpecialtyField = document.getElementById('customSpecialtyField');
        const customSpecialtyInput = document.getElementById('custom_specialty');
        
        specialtyOptions.forEach(option => {
            option.addEventListener('click', () => {
                const specialty = option.dataset.specialty;
                const radioInput = option.querySelector('input[type="radio"]');
                
                // Update selection
                specialtyOptions.forEach(opt => {
                    opt.classList.remove('border-primary', 'bg-primary', 'text-white');
                    opt.classList.add('border');
                });
                option.classList.remove('border');
                option.classList.add('border-primary', 'bg-primary', 'text-white');
                radioInput.checked = true;
                
                // Show/hide custom field
                if (specialty === 'Άλλο') {
                    customSpecialtyField.classList.remove('d-none');
                    customSpecialtyInput.required = true;
                } else {
                    customSpecialtyField.classList.add('d-none');
                    customSpecialtyInput.required = false;
                    customSpecialtyInput.value = '';
                }
            });
        });
        
        // AFM input formatting for customers
        const afmInput = document.getElementById('afm');
        if (afmInput) {
            afmInput.addEventListener('input', (e) => {
                // Only allow numbers
                e.target.value = e.target.value.replace(/\D/g, '');
                
                // Limit to 9 digits
                if (e.target.value.length > 9) {
                    e.target.value = e.target.value.slice(0, 9);
                }
                
                this.validateField(e.target);
            });
        }
    }
    
    async handleSubmit(e) {
        e.preventDefault();
        
        // Show loading state
        this.setLoadingState(true);
        
        try {
            if (!this.validateForm()) {
                return;
            }
            
            const formData = new FormData(this.form);
            
            // Handle custom specialty for mechanics
            const role = formData.get('role');
            if (role === 'mechanic') {
                const specialty = formData.get('specialty');
                const customSpecialty = formData.get('custom_specialty');
                
                if (specialty === 'Άλλο' && customSpecialty) {
                    formData.set('specialty', customSpecialty);
                }
                
                // Remove custom_specialty from the form data
                formData.delete('custom_specialty');
            }
            
            const response = await this.submitRegistration(formData);
            
            if (response.success) {
                this.showMessage(response.message, 'success');
                // Redirect after successful registration
                setTimeout(() => {
                    window.location.href = '/Apallaktiki/car-workshop-system/public/login.php?registered=1';
                }, 2000);
            } else {
                if (window.__APP_DEBUG__) console.log('Registration failed response placeholder');
                let errorMessage = response.message || 'Προέκυψε σφάλμα κατά την εγγραφή';
                
                // Add debug information to error message if available
                if (response.debug_info) {
                    if (window.__APP_DEBUG__) console.log('Debug info:', response.debug_info);
                    errorMessage += '\n\nΤεχνικά στοιχεία: ' + JSON.stringify(response.debug_info, null, 2);
                }
                
                if (response.data && response.data.error_type) {
                    errorMessage += '\n\nΤύπος σφάλματος: ' + response.data.error_type;
                }
                
                if (response.errors) {
                    this.handleErrors(response.errors);
                } else {
                    this.showMessage(errorMessage, 'error');
                }
            }
        } catch (error) {
            if (window.__APP_DEBUG__) console.error('Registration error:', error);
            let errorMessage = 'Προέκυψε σφάλμα κατά την εγγραφή. Παρακαλώ δοκιμάστε ξανά.';
            
            // Add more specific error information
            if (error.message) {
                errorMessage += '\n\nΤεχνικά στοιχεία: ' + error.message;
            }
            
            this.showMessage(errorMessage, 'error');
        } finally {
            this.setLoadingState(false);
        }
    }
    
    validateForm() {
        const errors = {};
        const formData = new FormData(this.form);
        
        // Required field validation
        const requiredFields = ['username', 'email', 'password', 'first_name', 'last_name', 'id_number', 'role'];
        requiredFields.forEach(field => {
            const value = formData.get(field)?.trim();
            if (!value) {
                errors[field] = 'Αυτό το πεδίο είναι υποχρεωτικό';
            }
        });
        
        // Username validation
        const username = formData.get('username')?.trim();
        if (username && !/^[a-zA-Z0-9_]{3,20}$/.test(username)) {
            errors.username = 'Το όνομα χρήστη πρέπει να είναι 3-20 χαρακτήρες (γράμματα, αριθμοί, _)';
        }
        
        // Email validation
        const email = formData.get('email')?.trim();
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            errors.email = 'Μη έγκυρη διεύθυνση email';
        }
        
        // Password validation
        const password = formData.get('password');
        if (password && password.length < 6) {
            errors.password = 'Ο κωδικός πρόσβασης πρέπει να έχει τουλάχιστον 6 χαρακτήρες';
        }
        
        // Name validation
        const firstName = formData.get('first_name')?.trim();
        if (firstName && firstName.length < 2) {
            errors.first_name = 'Το όνομα πρέπει να έχει τουλάχιστον 2 χαρακτήρες';
        }
        
        const lastName = formData.get('last_name')?.trim();
        if (lastName && lastName.length < 2) {
            errors.last_name = 'Το επώνυμο πρέπει να έχει τουλάχιστον 2 χαρακτήρες';
        }
        
        // ID Number validation
        const idNumber = formData.get('id_number')?.trim();
        if (idNumber && !/^[A-ZΑ-Ωa-zα-ω]{2}[0-9]{6}$/.test(idNumber)) {
            errors.id_number = 'Ο αριθμός ταυτότητας πρέπει να είναι 2 γράμματα + 6 αριθμοί (π.χ. ΑΤ123456)';
        }
        
        // Role-specific validation
        const role = formData.get('role');
        if (role === 'customer') {
            // AFM validation for customers
            const afm = formData.get('afm')?.trim();
            if (!afm) {
                errors.afm = 'Το ΑΦΜ είναι υποχρεωτικό για πελάτες';
            } else if (!/^\d{9}$/.test(afm)) {
                errors.afm = 'Το ΑΦΜ πρέπει να αποτελείται από 9 ψηφία';
            }
            
            // Address validation for customers
            const address = formData.get('address')?.trim();
            if (!address) {
                errors.address = 'Η διεύθυνση είναι υποχρεωτική για πελάτες';
            }
        } else if (role === 'mechanic') {
            // Specialty validation for mechanics
            let specialty = formData.get('specialty');
            const customSpecialty = formData.get('custom_specialty')?.trim();
            
            if (!specialty) {
                errors.specialty = 'Η ειδικότητα είναι υποχρεωτική για μηχανικούς';
            } else if (specialty === 'Άλλο' && !customSpecialty) {
                errors.custom_specialty = 'Παρακαλώ εισάγετε την ειδικότητά σας';
            }
        }
        
        // Terms validation
        const terms = formData.get('terms');
        if (!terms) {
            errors.terms = 'Πρέπει να αποδεχτείτε τους όρους και προϋποθέσεις';
        }
        
        // Display errors
        if (Object.keys(errors).length > 0) {
            this.handleErrors(errors);
            return false;
        }
        
        this.clearErrors();
        return true;
    }
    
    setupRealTimeValidation() {
        const fields = this.form.querySelectorAll('input[required]');
        
        fields.forEach(field => {
            field.addEventListener('blur', () => {
                this.validateField(field);
            });
            
            field.addEventListener('input', () => {
                if (field.classList.contains('is-invalid')) {
                    this.validateField(field);
                }
            });
        });
    }
    
    validateField(field) {
        const fieldName = field.name;
        const value = field.value.trim();
        let error = null;
        
        // Required validation
        if (field.hasAttribute('required') && !value) {
            error = 'Αυτό το πεδίο είναι υποχρεωτικό';
        }
        
        // Field-specific validation
        switch (fieldName) {
            case 'username':
                if (value && !/^[a-zA-Z0-9_]{3,20}$/.test(value)) {
                    error = 'Το όνομα χρήστη πρέπει να είναι 3-20 χαρακτήρες (γράμματα, αριθμοί, _)';
                }
                break;
            case 'email':
                if (value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                    error = 'Μη έγκυρη διεύθυνση email';
                }
                break;
            case 'password':
                if (value && value.length < 6) {
                    error = 'Ο κωδικός πρόσβασης πρέπει να έχει τουλάχιστον 6 χαρακτήρες';
                }
                break;
            case 'first_name':
            case 'last_name':
                if (value && value.length < 2) {
                    error = 'Πρέπει να έχει τουλάχιστον 2 χαρακτήρες';
                }
                break;
            case 'afm':
                if (value && !/^\d{9}$/.test(value)) {
                    error = 'Το ΑΦΜ πρέπει να αποτελείται από 9 ψηφία';
                }
                break;
            case 'id_number':
                if (value && !/^[A-ZΑ-Ωa-zα-ω]{2}[0-9]{6}$/.test(value)) {
                    error = 'Ο αριθμός ταυτότητας πρέπει να είναι 2 γράμματα + 6 αριθμοί (π.χ. ΑΤ123456)';
                }
                break;
        }
        
        // If this field has a persistent availability error (set by availability check)
        // and current validation produced no other error, do not clear the availability error here.
        if (field.dataset && field.dataset.availabilityError === '1' && !error) {
            // leave existing availability error in place until user edits the field
            return;
        }

        this.displayFieldError(field, error);
    }
    
    setupPasswordStrength() {
        const passwordField = this.form.querySelector('input[name="password"]');
        if (!passwordField) return;
        
        // Create password strength indicator
        const strengthIndicator = document.createElement('div');
        strengthIndicator.className = 'password-strength mt-1';
        strengthIndicator.innerHTML = `
            <div class="strength-bar">
                <div class="strength-fill"></div>
            </div>
            <small class="strength-text text-muted"></small>
        `;
        
        passwordField.parentNode.appendChild(strengthIndicator);
        
        passwordField.addEventListener('input', () => {
            this.updatePasswordStrength(passwordField.value, strengthIndicator);
        });
    }
    
    updatePasswordStrength(password, indicator) {
        const strengthFill = indicator.querySelector('.strength-fill');
        const strengthText = indicator.querySelector('.strength-text');
        
        if (!password) {
            strengthFill.style.width = '0%';
            strengthText.textContent = '';
            return;
        }
        
        let strength = 0;
        let text = '';
        
        // Calculate strength
        if (password.length >= 6) strength++;
        if (password.length >= 8) strength++;
        if (/[a-z]/.test(password)) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/\d/.test(password)) strength++;
        if (/[!@#$%^&*]/.test(password)) strength++;
        
        // Update visual indicator
        const percentage = (strength / 6) * 100;
        strengthFill.style.width = percentage + '%';
        
        if (strength <= 2) {
            strengthFill.className = 'strength-fill bg-danger';
            text = 'Αδύναμος κωδικός';
        } else if (strength <= 4) {
            strengthFill.className = 'strength-fill bg-warning';
            text = 'Μέτριος κωδικός';
        } else {
            strengthFill.className = 'strength-fill bg-success';
            text = 'Ισχυρός κωδικός';
        }
        
        strengthText.textContent = text;
    }
    
    setupAvailabilityChecks() {
        const usernameField = this.form.querySelector('input[name="username"]');
        const emailField = this.form.querySelector('input[name="email"]');
        
        if (usernameField) {
            let usernameTimeout;
            usernameField.addEventListener('input', () => {
                // User edited the field: clear previous availability state and any availability error shown
                delete usernameField.dataset.availabilityError;
                this.displayFieldError(usernameField, null);

                clearTimeout(usernameTimeout);
                usernameTimeout = setTimeout(() => {
                    this.checkAvailability('username', usernameField.value);
                }, 500);
            });
        }
        
        if (emailField) {
            let emailTimeout;
            emailField.addEventListener('input', () => {
                // Clear previous availability state when user edits
                delete emailField.dataset.availabilityError;
                this.displayFieldError(emailField, null);

                clearTimeout(emailTimeout);
                emailTimeout = setTimeout(() => {
                    this.checkAvailability('email', emailField.value);
                }, 500);
            });
        }
    }
    
    async checkAvailability(field, value) {
        if (!value || value.length < 3) return;
        try {
            // Use centralized fetchAPI and relative API path
            const result = await fetchAPI(`check-availability.php?${field}=${encodeURIComponent(value)}`);
            const fieldElement = this.form.querySelector(`input[name="${field}"]`);
            // API returns availability under result.data.available (envelope: { success, message, data })
            const available = result && result.data && result.data.available === true;

            if (!fieldElement) return;

            if (available) {
                // Field is available: clear any previous error
                delete fieldElement.dataset.availabilityError;
                this.displayFieldError(fieldElement, null);
            } else {
                // Field unavailable or invalid: show message (if present) or a sensible default
                const msg = result && result.message ? result.message : `Το ${field} χρησιμοποιείται ήδη`;
                // Mark that this error came from availability check so it persists until edited
                fieldElement.dataset.availabilityError = '1';
                this.displayFieldError(fieldElement, msg);
            }
        } catch (error) {
            if (window.__APP_DEBUG__) console.error('Availability check error:', error);
        }
    }
    
    handleErrors(errors, generalMessage = null) {
        // Clear previous errors
        this.clearErrors();
        
        // Show general error message
        if (generalMessage) {
            this.showError(generalMessage);
        }
        
        // Show field-specific errors
        Object.entries(errors).forEach(([field, message]) => {
            const fieldElement = this.form.querySelector(`input[name="${field}"]`);
            if (fieldElement && message) {
                this.displayFieldError(fieldElement, message);
            } else if (fieldElement) {
                // Clear error if no message
                this.displayFieldError(fieldElement, null);
            }
        });
    }
    
    displayFieldError(field, error) {
        // Remove existing error state
        field.classList.remove('is-invalid');
        const existingFeedback = field.parentNode.querySelector('.invalid-feedback');
        if (existingFeedback) {
            existingFeedback.remove();
        }
        
        if (error) {
            // Add error state
            field.classList.add('is-invalid');
            // Create error message
            const feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            feedback.textContent = error;
            field.parentNode.appendChild(feedback);
        }
    }
    
    clearErrors() {
        // Remove all error states
        this.form.querySelectorAll('.is-invalid').forEach(field => {
            field.classList.remove('is-invalid');
        });
        
        // Remove error messages
        this.form.querySelectorAll('.invalid-feedback').forEach(feedback => {
            feedback.remove();
        });
        
        // Hide general error alert
        const errorAlert = document.querySelector('.alert-danger');
        if (errorAlert) {
            errorAlert.remove();
        }
    }
    
    async submitRegistration(formData) {
        try {
            // Use centralized fetchAPI to POST registration (FormData supported via data)
            const result = await fetchAPI('register.php', {
                method: 'POST',
                data: formData
            });

            return result;
            
        } catch (error) {
            if (window.__APP_DEBUG__) console.error('Registration request error:', error);
            throw error;
        }
    }
    
    setLoadingState(loading) {
        if (loading) {
            this.submitButton.disabled = true;
            this.submitButton.innerHTML = `
                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                Εγγραφή...
            `;
        } else {
            this.submitButton.disabled = false;
            this.submitButton.textContent = this.originalButtonText;
        }
    }
    
    showMessage(message, type) {
        // Remove existing alerts
        document.querySelectorAll('.alert').forEach(alert => alert.remove());
        
        // Create new alert
        const alertType = type === 'error' ? 'danger' : type;
        const alert = document.createElement('div');
        alert.className = `alert alert-${alertType} alert-dismissible fade show`;
        
        // Handle multi-line messages
        const formattedMessage = message.replace(/\n/g, '<br>');
        
        alert.innerHTML = `
            ${formattedMessage}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        // Insert before form
        this.form.parentNode.insertBefore(alert, this.form);
        
        // Auto-dismiss success alerts
        if (type === 'success') {
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 5000);
        }
    }
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    const registrationForm = document.querySelector('form[action*="register"]');
    if (registrationForm) {
        new RegistrationForm('form[action*="register"]');
    }
});

// Export for manual initialization
window.RegistrationForm = RegistrationForm;
