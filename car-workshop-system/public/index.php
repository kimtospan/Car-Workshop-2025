<?php
require_once '../includes/language.php';
require_once '../includes/auth.php';
require_once '../src/views/layouts/header.php';
require_once '../src/views/layouts/footer.php';

// Debug logging - only when debug mode enabled
if (function_exists('debug_log')) {
    debug_log('INDEX: Accessing URL', ['uri' => $_SERVER['REQUEST_URI'], 'get' => $_GET]);
}

// Handle logout first (before checking login status)
if (isset($_GET['logout'])) {
    if (function_exists('debug_log')) debug_log('INDEX: Logout triggered', ['get' => $_GET, 'referer' => $_SERVER['HTTP_REFERER'] ?? 'none']);
    $auth->logout();
    header("Location: index.php");
    exit;
}

// Check if user is logged in
$isLoggedIn = $auth->isLoggedIn();

?>
<!DOCTYPE html>
<html lang="<?= $lang->getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('site_title') ?> - <?= t('home') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/custom.css" rel="stylesheet">
</head>
<body>
    <div class="position-relative min-vh-100">
   
    <img src="assets/img/image.png" alt="Workshop background" class="position-fixed top-0 start-0 w-100 h-100 object-fit-cover" style="z-index:-1;" />
    
    <div class="position-fixed top-0 start-0 w-100 h-100" style="z-index:0; background-color: rgba(0,0,0,0.35);"></div>

        <div class="position-relative" style="z-index:1;">
            
            <?php renderHeader($lang, 'index'); ?>

            <!-- Main Content -->
            <main>
                
                <section class="d-flex justify-content-center align-items-center text-center text-white" style="min-height: 60vh;">
                    <div class="container">
                        <div class="row justify-content-center">
                            <div class="col-lg-8 col-xl-7">
                                <div class="py-5">
                                    
                                    <h2 class="display-5 fw-bold mb-3"><?= t('workshop_title') ?></h2>
                                    <p class="lead mb-4 text-white-50"><?= t('workshop_description') ?></p>
                                    
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

        <section class="py-5">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-8 text-center text-white">
                       
                       
                        
                        <!-- Lies -->
                        <div class="row mb-5">
                            <div class="col-6 col-md-3 mb-3">
                                <div class="text-center">
                                    <div class="h4 text-primary mb-1">250+</div>
                                    <small class="text-white"><?= t('cars_serviced') ?></small>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 mb-3">
                                <div class="text-center">
                                    <div class="h4 text-primary mb-1">150+</div>
                                    <small class="text-white"><?= t('happy_customers') ?></small>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 mb-3">
                                <div class="text-center">
                                    <div class="h4 text-primary mb-1">15+</div>
                                    <small class="text-white"><?= t('years_experience') ?></small>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 mb-3">
                                <div class="text-center">
                                    <div class="h4 text-primary mb-1">3</div>
                                    <small class="text-white"><?= t('expert_mechanics') ?></small>
                                </div>
                            </div>
                        </div>
                        
                       
                        <div class="mb-4">
                            <h3 class="h5 mb-3 text-white"><?= t('our_services') ?></h3>

                            <ul class="list-unstyled d-flex flex-wrap justify-content-center gap-4 p-0 m-0">
                                <li class="d-flex flex-column align-items-center p-3">
                                    <i class="bi bi-wrench fs-1 text-primary" aria-hidden="true"></i>
                                    <div class="mt-2"><?= t('service_cap_repair') ?></div>
                                </li>
                                <li class="d-flex flex-column align-items-center p-3">
                                    <i class="bi bi-tools fs-1 text-primary" aria-hidden="true"></i>
                                    <div class="mt-2"><?= t('service_general') ?></div>
                                </li>
                                
                                <li class="d-flex flex-column align-items-center p-3">
                                    <i class="bi bi-activity fs-1 text-primary" aria-hidden="true"></i>
                                    <div class="mt-2"><?= t('service_diagnostics_full') ?></div>
                                </li>

                                <li class="d-flex flex-column align-items-center p-3">
                                    <i class="bi bi-shield-check fs-1 text-primary" aria-hidden="true"></i>
                                    <div class="mt-2"><?= t('service_brakes_full') ?></div>
                                </li>

                                <li class="d-flex flex-column align-items-center p-3">
                                    <i class="bi bi-chevron-expand fs-1 text-primary" aria-hidden="true"></i>
                                    <div class="mt-2"><?= t('service_suspension') ?></div>
                                </li>

                               
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
    </section>
    </main>
        <!-- Footer with Contact Information -->
            <?php renderFooter($lang); ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
