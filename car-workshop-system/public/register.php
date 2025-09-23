<?php
require_once '../includes/language.php';
require_once '../includes/auth.php';
require_once '../src/views/layouts/header.php';
?>
<!DOCTYPE html>
<html lang="<?= $lang->getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('register_title') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/custom.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Header with User Info -->
    <?php renderHeader($lang, 'register'); ?>

    <!-- Main Content -->
    <main class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <!-- Register Card -->
                    <div class="card shadow border-0">
                        <div class="card-body p-5">
                            <!-- Header -->
                            <div class="text-center mb-4">
                                <h2 class="h4 fw-bold text-dark mb-2"><?= t('new_user_registration') ?></h2>
                            </div>

                            <!-- Register Form -->
                            <form id="registrationForm" method="POST" action="api/register.php">
                                <input type="hidden" name="csrf_token" value="<?= SecurityConfig::generateCSRFToken(); ?>">
                                <!-- Username Field -->
                                <div class="mb-3">
                                    <label for="username" class="form-label fw-semibold text-dark">
                                        <?= t('username') ?> *
                                    </label>
                                    <input 
                                        type="text" 
                                        class="form-control form-control-lg border-2" 
                                        id="username" 
                                        name="username" 
                                        required
                                        autocomplete="username"
                                        placeholder="<?= t('enter_username') ?>"
                                    >
                                    <div class="form-text"><?= t('username_requirements') ?></div>
                                </div>

                                <!-- Email Field -->
                                <div class="mb-3">
                                    <label for="email" class="form-label fw-semibold text-dark">
                                        <?= t('email') ?> *
                                    </label>
                                    <input 
                                        type="email" 
                                        class="form-control form-control-lg border-2" 
                                        id="email" 
                                        name="email" 
                                        required
                                        autocomplete="email"
                                        placeholder="<?= t('enter_email') ?>"
                                    >
                                </div>

                                <!-- Password Field -->
                                <div class="mb-3">
                                    <label for="password" class="form-label fw-semibold text-dark">
                                        <?= t('password') ?> *
                                    </label>
                                    <input 
                                        type="password" 
                                        class="form-control form-control-lg border-2" 
                                        id="password" 
                                        name="password" 
                                        required
                                        autocomplete="new-password"
                                        placeholder="<?= t('enter_password') ?>"
                                    >
                                    <div class="form-text"><?= t('minimum_6_characters') ?></div>
                                </div>

                                <!-- First Name Field -->
                                <div class="mb-3">
                                    <label for="first_name" class="form-label fw-semibold text-dark">
                                        <?= t('first_name') ?> *
                                    </label>
                                    <input 
                                        type="text" 
                                        class="form-control form-control-lg border-2" 
                                        id="first_name" 
                                        name="first_name" 
                                        required
                                        autocomplete="given-name"
                                        placeholder="<?= t('enter_first_name') ?>"
                                    >
                                </div>

                                <!-- Last Name Field -->
                                <div class="mb-3">
                                    <label for="last_name" class="form-label fw-semibold text-dark">
                                        <?= t('last_name') ?> *
                                    </label>
                                    <input 
                                        type="text" 
                                        class="form-control form-control-lg border-2" 
                                        id="last_name" 
                                        name="last_name" 
                                        required
                                        autocomplete="family-name"
                                        placeholder="<?= t('enter_last_name') ?>"
                                    >
                                </div>

                                <!-- ID Number Field -->
                                <div class="mb-3">
                                    <label for="id_number" class="form-label fw-semibold text-dark">
                                        <?= t('id_number') ?> *
                                    </label>
                                    <input 
                                        type="text" 
                                        class="form-control form-control-lg border-2" 
                                        id="id_number" 
                                        name="id_number" 
                                        required
                                        placeholder="<?= t('id_number_example') ?>"
                                        pattern="[A-ZΑ-Ωa-zα-ω]{2}[0-9]{6}"
                                        maxlength="8"
                                    >
                                    <div class="form-text"><?= t('id_number_format') ?></div>
                                </div>

                                <!-- Role Selection -->
                                <div class="mb-4">
                                    <label class="form-label fw-semibold text-dark">
                                        <?= t('account_type') ?> *
                                    </label>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="card border-2 h-100" data-role="customer" style="cursor: pointer;">
                                                <div class="card-body text-center">
                                                    <input 
                                                        class="form-check-input visually-hidden" style="position:absolute;left:-9999px;" 
                                                        type="radio" 
                                                        name="role" 
                                                        id="role_customer" 
                                                        value="customer" 
                                                        required
                                                    >
                                                    <label class="form-check-label w-100" for="role_customer" style="cursor: pointer;">
                                                        <i class="bi bi-person-fill fs-1 text-primary d-block mb-2"></i>
                                                        <h6 class="fw-bold"><?= t('customer') ?></h6>
                                                        
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card border-2 h-100" data-role="mechanic" style="cursor: pointer;">
                                                <div class="card-body text-center">
                                                    <input 
                                                        class="form-check-input visually-hidden" style="position:absolute;left:-9999px;" 
                                                        type="radio" 
                                                        name="role" 
                                                        id="role_mechanic" 
                                                        value="mechanic" 
                                                        required
                                                    >
                                                    <label class="form-check-label w-100" for="role_mechanic" style="cursor: pointer;">
                                                        <i class="bi bi-tools fs-1 text-warning d-block mb-2"></i>
                                                        <h6 class="fw-bold"><?= t('mechanic') ?></h6>
                                                        
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Dynamic Role-Specific Fields -->
                                <div id="roleSpecificFields"></div>

                                <!-- Terms and Conditions -->
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                                    <label class="form-check-label" for="terms">
                                        <?= t('terms_agreement') ?> <a href="#" class="link-primary"><?= t('terms_of_service') ?></a> <?= t('and') ?> 
                                        <a href="#" class="link-primary"><?= t('privacy_policy') ?></a> *
                                    </label>
                                </div>

                                <!-- Register Button -->
                                <div class="d-grid mb-4">
                                    <button type="submit" class="btn btn-primary btn-lg fw-semibold py-3">
                                        <i class="bi bi-person-plus me-2"></i><?= t('register') ?>
                                    </button>
                                </div>
                            </form>

                            <!-- Sign In Section -->
                            <div class="text-center">
                                <p class="mb-2 text-muted"><?= t('already_have_account') ?></p>
                                <a href="login.php" class="btn btn-outline-primary btn-lg fw-semibold px-4">
                                    <?= t('sign_in') ?>
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
    <script src="assets/js/registration.js"></script>
</body>
</html>
