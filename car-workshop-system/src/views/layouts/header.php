<?php

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/language.php';

// Initialize language manager
$lang = new LanguageManager();

// Force client-side debug mode when defined true. Set to true to enable verbose client debug.
if (!defined('FORCE_CLIENT_DEBUG')) {
    define('FORCE_CLIENT_DEBUG', true);
}

// Use the same authentication check as pages
global $auth;
$isLoggedIn = $auth->isLoggedIn();
$user = null;

if ($isLoggedIn) {
    // Get user data from auth system (consistent with pages)
    $headerCurrentUser = $auth->getCurrentUser();
    
    if ($headerCurrentUser) {
        $user = [
            'first_name' => $headerCurrentUser['first_name'] ?? 'User',
            'last_name' => $headerCurrentUser['last_name'] ?? '',
            'full_name' => trim(($headerCurrentUser['first_name'] ?? '') . ' ' . ($headerCurrentUser['last_name'] ?? '')) ?: ($headerCurrentUser['username'] ?? 'User'),
            'email' => $headerCurrentUser['email'] ?? '',
            'role' => $headerCurrentUser['role'] ?? 'visitor'
        ];
    }
}


/**
 * Render the header navigation section
 */
function renderHeaderNavigation($lang, $currentPage = '', $additionalNavItems = []) {
    global $isLoggedIn, $user;
    ?>
    <div class="col-md-8">
        <nav>
            <ul class="list-unstyled d-flex justify-content-end align-items-center mb-0 flex-wrap">
                
                <?php if (!empty($additionalNavItems)): ?>
                    <?php foreach ($additionalNavItems as $item): ?>
                        <li class="nav-item me-1 mb-1">
                            <a href="<?= htmlspecialchars($item['url']) ?>" class="btn btn-sm d-flex align-items-center">
                                <?php if (!empty($item['icon'])): ?>
                                    <i class="<?= htmlspecialchars($item['icon']) ?> me-1"></i>
                                <?php endif; ?>
                                <span class="d-none d-sm-inline"><?= htmlspecialchars($item['text']) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if ($currentPage !== 'index'): ?>
                    <li class="nav-item me-1 mb-1">
                        <a href="index.php" class="btn btn-outline-primary btn-sm d-flex align-items-center">
                            <i class="bi bi-house me-1"></i>
                            <span class="d-none d-sm-inline"><?= t('home') ?></span>
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php if ($isLoggedIn && $user): ?>
                    <!-- LOGGED IN USER - More options available -->
                    
                   
                    
                    <!-- Dashboard Link - Additional option for logged-in users -->
                    <?php if ($currentPage !== 'dashboard'): ?>
                        <li class="nav-item me-1 mb-1">
                            <a href="dashboard.php" class="btn btn-primary btn-sm d-flex align-items-center">
                                <i class="bi bi-speedometer2 me-1"></i>
                                <span class="d-none d-sm-inline"><?= t('dashboard') ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                     <!-- User Info Display -->
                    <li class="nav-item me-2">
                        <div class="d-flex align-items-center bg-light rounded px-2 py-1 border flex-nowrap">
                            <i class="bi bi-person-fill me-1 text-success d-none d-sm-inline"></i>
                            <div class="flex-grow-1 text-truncate">
                                <span class="fw-bold text-dark small text-truncate d-inline-block"><?= htmlspecialchars($user['full_name'] ?? '') ?></span>
                            </div>
                            <a href="?logout=1" class="text-danger ms-1 flex-shrink-0" 
                               onclick="return confirm('<?= t('confirm_logout') ?>')" 
                               title="<?= t('logout') ?>"
                               style="text-decoration: none;">
                                <i class="bi bi-box-arrow-right"></i>
                            </a>
                        </div>
                    </li>
                
                <?php else: ?>
                    <!-- VISITORS - Basic login/register options -->
                    
                    <!-- Login Button -->
                    <li class="nav-item me-1 mb-1">
                        <a href="login.php" class="btn btn-primary btn-sm d-flex align-items-center">
                            <i class="bi bi-person-circle me-1"></i>
                            <span class="d-none d-sm-inline"><?= t('login') ?></span>
                        </a>
                    </li>
                    
                    <!-- Register Button -->
                    <li class="nav-item me-1 mb-1">
                        <a href="register.php" class="btn btn-outline-primary btn-sm d-flex align-items-center">
                            <i class="bi bi-person-plus me-1"></i>
                            <span class="d-none d-sm-inline"><?= t('register') ?></span>
                           
                        </a>
                    </li>
                <?php endif; ?>
                
                <!-- Language Switcher -->
                <li class="nav-item me-1 mb-1">
                    <?= $lang->getLanguageSwitcher($currentPage) ?>
                </li>
            </ul>
        </nav>
    </div>
    <?php
}

/**
 * Render complete header
 */
function renderHeader($lang, $currentPage = '', $additionalNavItems = []) {
    global $isLoggedIn, $user;
    ?>
    <header class="bg-white border-bottom shadow-sm py-3">
        
        <?php if (defined('FORCE_CLIENT_DEBUG') && FORCE_CLIENT_DEBUG): ?>
            <script>window.__APP_DEBUG__ = true;</script>
        <?php endif; ?>
        
        <div class="container">
            <div class="row align-items-center">
                <!-- Brand Section -->
                <div class="col-md-4">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-car-front-fill text-primary fs-2 me-3"></i>
                        <div>
                            <h1 class="h4 mb-0 fw-bold text-primary"><?= t('site_title') ?></h1>
                            
                        </div>
                    </div>
                </div>
                
                <!-- Navigation Section -->
                <?php renderHeaderNavigation($lang, $currentPage, $additionalNavItems); ?>
            </div>
        </div>
    </header>
    <?php
}
?>
