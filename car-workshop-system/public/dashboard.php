<?php
// Main dashboard entry point - redirects to role-specific dashboards. 
// Only exists because this used to be a 3 part monilith. 
require_once '../includes/auth.php';
require_once '../includes/language.php';

// Handle logout first (before checking login status)
if (isset($_GET['logout'])) {
    $auth->logout();
    header("Location: index.php");
    exit;
}

// Require login to access this page
$auth->requireLogin();

// Get current user information
$currentUser = $auth->getCurrentUser();

// Preserve language parameter in redirects
$langParam = isset($_GET['lang']) ? '?lang=' . urlencode($_GET['lang']) : '';

// Route to appropriate dashboard based on role
switch ($currentUser['role']) {
    case 'secretary':
        header("Location: secretary_dashboard.php" . $langParam);
        break;
    case 'mechanic':
        header("Location: mechanic_dashboard.php" . $langParam);
        break;
    case 'customer':
        header("Location: user_dashboard.php" . $langParam);
        break;
    default:
        // Unknown role - logout and redirect
        $auth->logout();
        header("Location: index.php?error=unknown_role");
        break;
}
exit;
?>
