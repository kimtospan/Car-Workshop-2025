<?php
require_once '../includes/language.php';
require_once '../includes/auth.php';
require_once '../src/config/AppConfig.php';
require_once '../src/views/layouts/header.php';

// Handle logout
if (isset($_GET['logout'])) {
    if (isset($_SESSION['user_id'])) {
        // You could log the logout here
        session_destroy();
        
        // Start a new session to generate a fresh CSRF token
        session_start();
    }
    header("Location: login.php");
    exit;
}

?>


<!DOCTYPE html>
<html lang="<?= $lang->getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('login_title') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/custom.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Header with User Info -->
    <?php 
    renderHeader($lang, 'login');
    ?>

    <!-- Main Content -->
    <main class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <!-- Login Card -->
                    <div class="card shadow border-0">
                        <div class="card-body p-5">
                            <!-- Header -->
                            <div class="text-center mb-4">
                                <h2 class="h4 fw-bold text-dark mb-2"><?= t('sign_in_existing') ?></h2>
                              
                            </div>

                            <!-- Registration Success Message -->
                            <?php if (isset($_GET['registered']) && $_GET['registered'] == '1'): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-check-circle-fill me-3 fs-4"></i>
                                    <div>
                                        <h6 class="alert-heading mb-1"><?= t('registration_successful') ?></h6>
                                        <p class="mb-0"><?= t('account_created_message') ?></p>
                                    </div>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php endif; ?>

                            <!-- Alert Container for AJAX Messages -->
                            <div id="alertContainer"></div>

                            <!-- Login Form -->
                            <form id="loginForm">
                                <input type="hidden" name="csrf_token" value="<?= SecurityConfig::generateCSRFToken(); ?>">
                                
                                <!-- Username/Email Field -->
                                <div class="mb-3">
                                    <label for="signInName" class="form-label fw-semibold text-dark">
                                        <?= t('email_address') ?>
                                    </label>
                                    <input 
                                        type="email" 
                                        class="form-control form-control-lg border-2" 
                                        id="signInName" 
                                        name="email" 
                                        required
                                        placeholder="<?= t('enter_email') ?>"
                                    >
                                </div>

                                <!-- Password Field -->
                                <div class="mb-3">
                                    <label for="password" class="form-label fw-semibold text-dark">
                                        <?= t('password') ?>
                                    </label>
                                    <input 
                                        type="password" 
                                        class="form-control form-control-lg border-2" 
                                        id="password" 
                                        name="password" 
                                        required
                                        placeholder="<?= t('enter_password') ?>"
                                    >
                                    
                                </div>

                                <!-- Sign In Button -->
                                <div class="d-grid mb-4">
                                    <button type="submit" class="btn btn-primary btn-lg fw-semibold py-3" id="submitBtn">
                                        <span class="btn-text"><?= t('sign_in') ?></span>
                                        <span class="loading-spinner" style="display: none;">
                                            <i class="bi bi-arrow-clockwise spin"></i> <?= t('signing_in') ?>
                                        </span>
                                    </button>
                                </div>
                            </form>

                            <!-- Divider -->
                            <div class="text-center my-4">
                                <div class="border-top position-relative">
                                    <span class="bg-white px-3 text-muted position-absolute top-50 start-50 translate-middle">
                                        <?= t('or') ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Sign Up Section -->
                            <div class="text-center">
                                <p class="mb-2 text-muted"><?= t('dont_have_account') ?></p>
                                <a href="register.php" class="btn btn-outline-primary btn-lg fw-semibold px-4">
                                    <?= t('sign_up_now') ?>
                                </a>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-top mt-auto py-3">
        <div class="container">
            <div class="text-center text-muted">
                <small><?= t('copyright') ?></small>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
    
    <!-- AJAX Login Enhancement -->
    <script>
        // Add spinning animation CSS
        const style = document.createElement('style');
        style.textContent = `
            .spin {
                animation: spin 1s linear infinite;
            }
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            .form-control:focus {
                border-color: #0d6efd;
                box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
            }
        `;
        document.head.appendChild(style);

        // Get form elements
        const loginForm = document.getElementById('loginForm');
        const submitBtn = document.getElementById('submitBtn');
        const btnText = submitBtn.querySelector('.btn-text');
        const loadingSpinner = submitBtn.querySelector('.loading-spinner');
        const alertContainer = document.getElementById('alertContainer');

    

        // Function to show loading state
        function setLoading(loading) {
            if (loading) {
                submitBtn.disabled = true;
                btnText.style.display = 'none';
                loadingSpinner.style.display = 'inline';
            } else {
                submitBtn.disabled = false;
                btnText.style.display = 'inline';
                loadingSpinner.style.display = 'none';
            }
        }

        // Enhanced form submission with AJAX
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            clearAlerts();
            setLoading(true);

            try {
                const formData = new FormData(loginForm);
                
                // Use centralized fetchAPI which returns parsed JSON and handles CSRF
                const result = await fetchAPI('./auth/login_process.php', {
                    method: 'POST',
                    data: formData
                });

                if (result && result.success) {
                    showAlert(result.message || '<?= t('login_successful') ?>', 'success');
                    setTimeout(() => {
                        window.location.href = result.redirect || 'dashboard.php';
                    }, 1500);
                } else {
                    showAlert((result && result.message) || 'Login failed. Please check your credentials.');

                    // If CSRF token failed, refresh the page to get a new token
                    if (result && result.message && result.message.includes('Security token validation failed')) {
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        setLoading(false);
                    }
                }

            } catch (error) {
                let errorMessage = '<?= t('connection_error') ?> ';
                if (error.name === 'TypeError' && error.message.includes('fetch')) {
                    errorMessage += '<?= t('server_connection_failed') ?>';
                } else if (error.message.includes('Server error')) {
                    errorMessage += '<?= t('server_error_try_again') ?>';
                } else {
                    errorMessage += '<?= t('check_connection') ?>';
                }
                
                showAlert(errorMessage);
                setLoading(false);
            }
        });

        // UX Enhancements
        document.addEventListener('DOMContentLoaded', function() {
            // Check if we're returning from a logout
            if (window.location.search.includes('logout')) {
                // Remove logout parameter and refresh to get new CSRF token
                const url = new URL(window.location);
                url.searchParams.delete('logout');
                window.history.replaceState({}, '', url.pathname);
                
                // Show logout success message
                showAlert('<?= t('logout_successful') ?>', 'info');
            }
            
            // Auto-focus email field
            document.getElementById('signInName').focus();
            
            // Enter key support for password field
            document.getElementById('password').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    loginForm.dispatchEvent(new Event('submit'));
                }
            });
            
            // Real-time validation feedback
            const emailField = document.getElementById('signInName');
            const passwordField = document.getElementById('password');
            
            emailField.addEventListener('input', function() {
                clearAlerts();
            });
            
            passwordField.addEventListener('input', function() {
                clearAlerts();
            });
        });
    </script>
</body>
</html>
