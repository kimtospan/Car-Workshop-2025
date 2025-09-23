<?php


require_once __DIR__ . '/../../../includes/language.php';

if (!isset($lang)) {
    $lang = new LanguageManager();
}

function renderFooter($lang) {
?>
    <!-- Footer -->
    <footer class="bg-dark text-light py-5 mt-5">
        <div class="container">
            <div class="row">
                <!-- Workshop Contact Info with small map -->
                <div class="col-lg-6 mx-auto">
                    <div class="text-center">
                        <h3 class="h4 fw-bold mb-4 text-primary"><?= t('visit_workshop') ?></h3>
                        <div class="row">
                            <div class="col-lg-8 mx-auto">
                                <div class="text-center">
                                    <div class="mb-3">
                                        <i class="bi bi-geo-alt-fill text-primary me-2"></i>
                                        <span class="fw-semibold"><?= t('address') ?>:</span>
                                        <div class="small mt-1"><?= t('workshop_address') ?></div>
                                    </div>
                                    <div class="mb-3">
                                        <i class="bi bi-telephone-fill text-primary me-2"></i>
                                        <span class="fw-semibold"><?= t('phone') ?>:</span>
                                        <span class="ms-2"><?= t('workshop_phone') ?></span>
                                    </div>
                                    <div class="mb-3">
                                        <i class="bi bi-clock-fill text-primary me-2"></i>
                                        <span class="fw-semibold"><?= t('hours') ?>:</span>
                                        <span class="ms-2"><?= t('workshop_hours') ?></span>
                                    </div>
                                    <div class="mb-4">
                                        <i class="bi bi-envelope-fill text-primary me-2"></i>
                                        <span class="fw-semibold"><?= t('email') ?>:</span>
                                        <span class="ms-2"><?= t('workshop_email') ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer Bottom -->
            <hr class="my-4 border-secondary">
            <div class="row">
                <div class="col-12 text-center">
                    <p class="mb-0 ">
                        &copy; <?= date('Y') ?> <?= t('workshop_title') ?>. <?= t('all_rights_reserved') ?>
                    </p>
                </div>
            </div>
        </div>
    </footer>
<?php
}
?>