
// Global configuration
const Config = {
    apiBase: 'api/',
    defaultTimeout: 30000,
    defaultPerPage: 20
};

// Global state
const AppState = {
    loading: new Set(),
    cache: new Map(),
    currentUser: null
};

// Global debug switch (default false). Templates may set window.__APP_DEBUG__ = true when in dev.
if (typeof window !== 'undefined' && typeof window.__APP_DEBUG__ === 'undefined') {
    window.__APP_DEBUG__ = false;
}

// When not in debug mode, mute console methods to avoid accidental debug output in production.
if (typeof window !== 'undefined') {
    (function(){
        try {
            if (!window.__APP_DEBUG__ && typeof console !== 'undefined') {
                const _methods = ['log','info','warn','error','groupCollapsed','groupEnd','group','debug','table'];
                _methods.forEach(m => {
                    if (typeof console[m] === 'function') {
                        try { console['_orig_' + m] = console[m]; } catch (e) {}
                        try { console[m] = function() {}; } catch (e) {}
                    }
                });
            }
        } catch (e) {
            // Swallow any errors while muting console 
        }
    })();
}

/**
 * fetch() Wrapper
 * browser fetch API, enforces JSON handling, attaches CSRF tokens for
 * mutating requests, redirects on 401 responses, normalises headers.
 * Every AJAX module
 * uses `fetchAPI` so token handling, error redirects and logging are
 * consistent.
 */
async function fetchAPI(endpoint, options = {}) {

    //fetch() has url and finalOptions
    // At first we resolve the URL
    
    const url = (
        endpoint.startsWith('http') ||
        endpoint.startsWith(Config.apiBase) ||
        endpoint.startsWith('/') ||
        endpoint.startsWith('./') ||
        endpoint.startsWith('../')
    ) ? endpoint : Config.apiBase + endpoint;

    const defaultOptions = {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
    };

    // Merge headers
    const mergedHeaders = { ...defaultOptions.headers, ...(options.headers || {}) };

    const method = ((options.method || defaultOptions.method) || 'GET').toUpperCase();

    // Attach CSRF header for mutating requests if available
    if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(method) && window.csrfToken && !mergedHeaders['X-CSRF-Token']) {
        mergedHeaders['X-CSRF-Token'] = window.csrfToken;
    }

    // Handle body: FormData vs JSON
    if (options.data instanceof FormData) {
        // Let browser set Content-Type boundary
        delete mergedHeaders['Content-Type'];
        options.body = options.data;

        // Ensure CSRF token present in form data for server-side frameworks
        if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(method) && window.csrfToken) {
            options.body.append('csrf_token', window.csrfToken);
        }
    } else if (options.data && method !== 'GET') {
        // Send JSON body for non-FormData
        options.body = typeof options.data === 'string' ? options.data : JSON.stringify(options.data);
    }

    // Finalize options
    const finalOptions = { ...defaultOptions, ...options };
    finalOptions.headers = mergedHeaders;
    delete finalOptions.data;

    // If sending JSON for mutating requests, ensure a csrf_token key exists inside the payload
    if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(method) && finalOptions.headers['Content-Type'] && finalOptions.headers['Content-Type'].toLowerCase().includes('application/json') && window.csrfToken) {
        if (typeof finalOptions.body === 'string') {
            try {
                const parsed = JSON.parse(finalOptions.body || '{}');
                if (!parsed.csrf_token && !parsed.csrfToken) parsed.csrf_token = window.csrfToken;
                finalOptions.body = JSON.stringify(parsed);
            } catch (e) {
                // leave body as-is if it isn't valid JSON
            }
        } else if (!finalOptions.body) {
            finalOptions.body = JSON.stringify({ csrf_token: window.csrfToken });
            finalOptions.headers['Content-Type'] = 'application/json';
        }
    }

    // Perform the network call
    const response = await fetch(url, finalOptions);

    // Authentication handling
    if (response.status === 401) {
        window.location.href = 'login.php';
        return null;
    }

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    const contentType = (response.headers.get('content-type') || '').toLowerCase();

    // If API returned HTML (likely login page), redirect to login
    const isApiCall = url.includes(Config.apiBase) || url.includes('/api/');
    if (isApiCall && contentType.includes('text/html')) {
        window.location.href = 'login.php';
        return null;
    }

    if (!contentType.includes('application/json')) {
        throw new Error('Response is not JSON (content-type: ' + contentType + ')');
    }

    // Parse JSON response and return
    try {
        return await response.json();
    } catch (parseError) {
        // Attempt to provide the raw body for debugging
        let text = '';
        try { text = await response.clone().text(); } catch (_) {}
        const snippet = (text || '').replace(/\s+/g, ' ').slice(0, 300);
        throw new Error('Invalid JSON response from server' + (snippet ? `: ${snippet}` : ''));
    }
}

/**
 * Show a contextual loading state.
 * Callers should always call the matching `hideLoading` when the work is finished so the UI is restored.
 */
function showLoading(element, text = 'Loading...') {
    if (typeof element === 'string') {
        element = document.getElementById(element);
    }
    
    if (!element) return;
    
    const loadingId = element.id || Math.random().toString(36);
    AppState.loading.add(loadingId);
    
    // Disable form elements
    if (element.tagName === 'FORM') {
        const inputs = element.querySelectorAll('input, button, select, textarea');
        inputs.forEach(input => input.disabled = true);
    }
    
    // Show spinner on buttons
    if (element.tagName === 'BUTTON') {
        const spinner = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>';
        element.innerHTML = spinner + text;
        element.disabled = true;
    }
    
    // Show loading overlay for containers
    if (element.classList.contains('table-container') || element.classList.contains('card-body')) {
        let overlay = element.querySelector('.loading-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'loading-overlay position-absolute top-0 start-0 w-100 h-100 d-flex justify-content-center align-items-center bg-white bg-opacity-75';
            overlay.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="mt-2">${text}</div>
                </div>
            `;
            element.style.position = 'relative';
            element.appendChild(overlay);
        }
        overlay.style.display = 'flex';
    }
}

/**
 * Restore the element's interactive state and hide any loading UI created by
 * `showLoading`.
 */
function hideLoading(element) {
    if (typeof element === 'string') {
        element = document.getElementById(element);
    }
    
    if (!element) return;
    
    const loadingId = element.id || Math.random().toString(36);
    AppState.loading.delete(loadingId);
    
    // Enable form elements
    if (element.tagName === 'FORM') {
        const inputs = element.querySelectorAll('input, button, select, textarea');
        inputs.forEach(input => input.disabled = false);
    }
    
    // Reset button state
    if (element.tagName === 'BUTTON') {
        element.disabled = false;
        // Restore original text (should be handled by calling code)
    }
    
    // Hide loading overlay
    const overlay = element.querySelector('.loading-overlay');
    if (overlay) {
        overlay.style.display = 'none';
    }
}

function showSuccess(message, container = null) {
    showAlert(message, 'success', container);
}

function showError(message, container = null) {
    showAlert(message, 'danger', container);
}

/**
 * Clear per-field validation errors inside a form
 */
function clearFieldErrors(form) {
    if (!form) return;
    try {
        const invalids = form.querySelectorAll('.is-invalid');
        invalids.forEach(el => el.classList.remove('is-invalid'));
        const feedbacks = form.querySelectorAll('.invalid-feedback.__dynamic');
        feedbacks.forEach(el => el.remove());
    } catch (_) {}
}

/**
 * Mark a specific input/select/textarea as invalid with a message
 */
function setFieldError(input, message) {
    if (!input) return;
    try {
        input.classList.add('is-invalid');
        // Avoid duplicating messages
        let fb = input.nextElementSibling;
        const needsNew = !fb || !fb.classList || !fb.classList.contains('invalid-feedback');
        if (needsNew) {
            fb = document.createElement('div');
            fb.className = 'invalid-feedback __dynamic d-block';
            input.parentNode.insertBefore(fb, input.nextSibling);
        } else {
            fb.classList.add('__dynamic');
            fb.classList.add('d-block');
        }
        fb.textContent = message || 'Μη έγκυρη τιμή';
    } catch (_) {}
}

/**
 * Display field errors map { fieldName: message } for a form
 */
function displayFieldErrors(form, errors) {
    if (!form || !errors) return;
    // Normalize possible structures: {field: 'msg'} or {field: ['msg']}
    Object.entries(errors).forEach(([field, msg]) => {
        const message = Array.isArray(msg) ? msg.join(' ') : msg;
        const input = form.querySelector(`[name="${CSS.escape(field)}"]`);
        if (input) setFieldError(input, message);
    });
    // Focus first invalid
    const firstInvalid = form.querySelector('.is-invalid');
    if (firstInvalid && typeof firstInvalid.focus === 'function') firstInvalid.focus();
}

function showErrors(errors, container = null) {
    if (typeof errors === 'object') {
        const errorMessages = Object.values(errors).flat();
        showAlert(errorMessages.join('<br>'), 'danger', container);
    } else {
        showError(errors, container);
    }
}

function showAlert(message, type = 'info', container = null) {
    const alertContainer = container || document.getElementById('alertContainer') || document.body;
    
    const alertElement = document.createElement('div');
    alertElement.className = `alert alert-${type} alert-dismissible fade show`;
    alertElement.innerHTML = `
        <i class="bi bi-${getAlertIcon(type)} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Insert at the beginning
    alertContainer.insertBefore(alertElement, alertContainer.firstChild);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        if (alertElement.parentNode) {
            alertElement.remove();
        }
    }, 5000);
}

function getAlertIcon(type) {
    const icons = {
        success: 'check-circle-fill',
        danger: 'exclamation-triangle-fill',
        warning: 'exclamation-triangle-fill',
        info: 'info-circle-fill'
    };
    return icons[type] || 'info-circle-fill';
}

function clearAlerts(container = null) {
    // If a specific container is provided and it's NOT the global alert container,
    // only remove alert elements inside it. Do NOT wipe the entire container.
    if (container && container !== document.getElementById('alertContainer')) {
        try {
            const alerts = container.querySelectorAll('.alert');
            alerts.forEach(a => a.remove());
            return;
        } catch (_) {}
    }
    const alertContainer = container || document.getElementById('alertContainer');
    if (alertContainer) {
        alertContainer.innerHTML = '';
    }
}

/**
 * AJAX form submit handler that performs form submissions over XHR 
 */
async function handleFormSubmit(form, options = {}) {
    if (typeof form === 'string') {
        form = document.getElementById(form);
    }
    
    if (!form) {
        throw new Error('Form not found');
    }
    
    const formData = new FormData(form);
    const action = options.endpoint || form.action;
   
    const method = options.method || form.getAttribute('method') || 'POST';
    
    // Show loading state
    showLoading(form);
    clearAlerts(options.alertContainer);
    clearFieldErrors(form);
    
    try {
        const response = await fetchAPI(action, {
            method: method,
            data: formData
        });
        
        if (response.success) {
            showSuccess(response.message, options.alertContainer);
            
            // Call success callback
            if (options.onSuccess) {
                options.onSuccess(response);
            }
            
            // Reset form if requested
            if (options.resetForm !== false) {
                form.reset();
            }
            
            // Close modal if form is in modal
            const modal = form.closest('.modal');
            if (modal) {
                const modalInstance = bootstrap.Modal.getInstance(modal);
                if (modalInstance) {
                    modalInstance.hide();
                }
            }
        } else {
            if (response.errors && typeof response.errors === 'object') {
                displayFieldErrors(form, response.errors);
            }
            showErrors(response.errors || response.message, options.alertContainer);
            
            if (options.onError) {
                options.onError(response);
            }
        }
        
        return response;
        
    } catch (error) {
        if (window.__APP_DEBUG__) console.error('Form submission error:', error);
        showError('Network error occurred. Please try again.', options.alertContainer);
        
        if (options.onError) {
            options.onError({ success: false, message: error.message });
        }
        
        throw error;
        
    } finally {
        hideLoading(form);
    }
}


async function refreshTable(tableId, endpoint, options = {}) {
    const tableContainer = document.getElementById(tableId);
    if (!tableContainer) return;
    
    try {
        showLoading(tableContainer, 'Refreshing data...');
        
        const response = await fetchAPI(endpoint, {
            method: 'GET',
            data: options.params
        });
        
        if (response.success && options.onSuccess) {
            options.onSuccess(response);
        }
        
    } catch (error) {
        if (window.__APP_DEBUG__) console.error('Table refresh error:', error);
        showError('Failed to refresh data');
    } finally {
        hideLoading(tableContainer);
    }
}

/**
 * confirmAction - show a non-blocking Bootstrap modal confirmation.
 * Returns a Promise<boolean> which resolves true when confirmed.
 * Usage: const ok = await confirmAction({ title, message }); if (!ok) return;
 */
function confirmAction({ title = 'Confirm Action', message = '', confirmText = 'Confirm', cancelText = 'Cancel', size = 'md' } = {}) {
        return new Promise((resolve) => {
                // Create modal HTML
                const modalId = `confirmModal_${Math.random().toString(36).slice(2,9)}`;
                const wrapper = document.createElement('div');
                wrapper.innerHTML = `
                        <div class="modal fade" id="${modalId}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-${size}">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">${title}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">${message}</div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">${cancelText}</button>
                                        <button type="button" class="btn btn-danger btn-confirm">${confirmText}</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                `;

                document.body.appendChild(wrapper);
                const modalEl = document.getElementById(modalId);
                const bsModal = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: false });

                const cleanup = (result) => {
                        try { bsModal.hide(); } catch (_) {}
                        setTimeout(() => {
                                modalEl.remove();
                                if (wrapper && wrapper.parentNode) wrapper.parentNode.removeChild(wrapper);
                        }, 300);
                        resolve(result);
                };

                modalEl.addEventListener('hidden.bs.modal', () => cleanup(false), { once: true });
                modalEl.querySelector('.btn-confirm').addEventListener('click', () => cleanup(true), { once: true });

                bsModal.show();
        });
}

/**
 * 
 * AJAX calls are expensive, so create a fictional wait time to prevent double clicks and spam
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Export debounce explicitly so other scripts can rely on window.debounce
try {
    if (typeof window !== 'undefined') window.debounce = debounce;
} catch (_) {}

function initApp() {
    if (window.__APP_DEBUG__) console.log('Car Workshop App initialized');
    
    // Set up global error handling
    window.addEventListener('unhandledrejection', event => {
    if (window.__APP_DEBUG__) console.error('Unhandled promise rejection:', event.reason);
        showError('An unexpected error occurred');
    });
    
    // Set up global AJAX error handling
    // Auto-dismiss alerts on click
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('btn-close')) {
            const alert = e.target.closest('.alert');
            if (alert) {
                alert.remove();
            }
        }
    });
    
    // Initialize tooltips (tolerate early call)
    try {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    } catch (_) {}

    
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initApp);
} else {
    initApp();
}