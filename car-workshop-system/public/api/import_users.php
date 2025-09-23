<?php
require_once '../../includes/auth.php';
require_once '../../src/config/database.php';
require_once '../../src/services/ValidationService.php';
require_once '../../src/services/FormProcessor.php';
require_once '../../src/models/User.php';
require_once __DIR__ . '/common.php';

$auth->requireLogin();
$currentUser = $auth->getCurrentUser();

if ($currentUser['role'] !== 'secretary') {
    sendError('Access denied', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

if (empty($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    sendError('File upload failed', 400);
}

$tmp = $_FILES['csv_file']['tmp_name'];

$handle = fopen($tmp, 'r');
if (!$handle) {
    sendError('Unable to open uploaded file', 500);
}

$db = Database::getInstance()->getConnection();
$processor = new FormProcessor($db, $auth);
$userModel = new User();

$summary = [
    'processed' => 0,
    'created' => 0,
    'skipped' => 0,
    'errors' => []
];

// Read header row to map columns (expect common headers)
$header = fgetcsv($handle);
if (!$header || count($header) < 3) {
    fclose($handle);
    sendError('CSV header invalid or missing', 400);
}

$columns = array_map('trim', $header);

while (($row = fgetcsv($handle)) !== false) {
    $summary['processed']++;
    $data = [];
    foreach ($columns as $i => $col) {
        $data[$col] = isset($row[$i]) ? trim($row[$i]) : '';
    }

    // Basic fields required for user creation: username,email,password,first_name,last_name,id_number,role
    $prepared = [
        'username' => $data['username'] ?? '',
        'email' => $data['email'] ?? '',
        'password' => $data['password'] ?? bin2hex(random_bytes(4)),
        'first_name' => $data['first_name'] ?? '',
        'last_name' => $data['last_name'] ?? '',
        'id_number' => $data['id_number'] ?? '',
        'role' => $data['role'] ?? 'customer',
        'afm' => $data['afm'] ?? null,
        'address' => $data['address'] ?? null,
        'specialty' => $data['specialty'] ?? null
    ];

    // Validate
    $errors = ValidationService::validateUserData($prepared, false);
    if (ValidationService::hasErrors($errors)) {
        $summary['skipped']++;
        $summary['errors'][] = ['row' => $summary['processed'], 'errors' => $errors];
        continue;
    }

    // Check duplicates
    if ($userModel->usernameExists($prepared['username']) || $userModel->emailExists($prepared['email']) || $userModel->idNumberExists($prepared['id_number'] ?? '')) {
        $summary['skipped']++;
        $summary['errors'][] = ['row' => $summary['processed'], 'errors' => 'Duplicate user'];
        continue;
    }

    // Create user via FormProcessor to reuse logic
    $result = $processor->processUserCreation($prepared);
    if ($result['success']) {
        $summary['created']++;
    } else {
        $summary['skipped']++;
        $errorDetail = 'Unknown';
        if (isset($result['errors']) && is_array($result['errors']) && count($result['errors']) > 0) {
            $errorDetail = $result['errors'];
        } elseif (!empty($result['message'])) {
            $errorDetail = $result['message'];
        }
        $summary['errors'][] = ['row' => $summary['processed'], 'errors' => $errorDetail];
    }
}

fclose($handle);
sendSuccess('Import finished', $summary);

?>
