<?php
require_once '../../includes/auth.php';
require_once '../../src/config/database.php';
require_once '../../src/services/ValidationService.php';
require_once '../../src/services/FormProcessor.php';
require_once '../../src/models/Car.php';
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
$carModel = new Car();

$summary = [
    'processed' => 0,
    'created' => 0,
    'skipped' => 0,
    'errors' => []
];

// Expect header row
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

    // Prepare vehicle data
    $vehicle = [
        'serial_number' => $data['serial_number'] ?? '',
        'brand' => $data['brand'] ?? '',
        'model' => $data['model'] ?? '',
        'vehicle_type' => $data['vehicle_type'] ?? '',
        'engine_type' => $data['engine_type'] ?? '',
        'door_count' => $data['door_count'] ?? 4,
        'wheel_count' => $data['wheel_count'] ?? 4,
        'production_date' => $data['production_date'] ?? null,
        'acquisition_year' => $data['acquisition_year'] ?? null,
        // customer association: secretary provides customer's AFM in the CSV (column: afm)
            'customer_afm' => $data['afm'] ?? ($data['customer_afm'] ?? null)
    ];

    $errors = ValidationService::validateVehicleData($vehicle);
    if (ValidationService::hasErrors($errors)) {
        $summary['skipped']++;
        $summary['errors'][] = ['row' => $summary['processed'], 'errors' => $errors];
        continue;
    }

    // Check duplicates by serial
    if ($carModel->serialNumberExists($vehicle['serial_number'])) {
        $summary['skipped']++;
        $summary['errors'][] = ['row' => $summary['processed'], 'errors' => 'Duplicate serial number'];
        continue;
    }

    // If no owner AFM provided, skip (we require owner AFM)
    if (empty($vehicle['customer_afm'])) {
        $summary['skipped']++;
        $summary['errors'][] = ['row' => $summary['processed'], 'errors' => 'Missing owner AFM (afm)'];
        continue;
    }

    // Resolve AFM -> customers.id (pre-check owner existence)
    try {
        $stmt = $db->prepare('SELECT id FROM customers WHERE afm = ? LIMIT 1');
        $stmt->execute([trim($vehicle['customer_afm'])]);
        $owner = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $summary['skipped']++;
        $summary['errors'][] = ['row' => $summary['processed'], 'errors' => 'DB error while resolving owner AFM'];
        continue;
    }

    if (!$owner) {
        $summary['skipped']++;
        $summary['errors'][] = ['row' => $summary['processed'], 'errors' => 'Owner not found for AFM: ' . $vehicle['customer_afm']];
        continue;
    }

    $customerId = intval($owner['id']);
    // attach resolved id to the vehicle array for processors that expect it
    $vehicle['customer_id'] = $customerId;

    // Create vehicle via processVehicleCreation
    $res = $processor->processVehicleCreation($vehicle, $customerId);
    if ($res['success']) {
        $summary['created']++;
    } else {
        $summary['skipped']++;
        // Prefer non-empty errors array; if empty, fall back to message string
        $errorDetail = 'Unknown';
        if (isset($res['errors']) && is_array($res['errors']) && count($res['errors']) > 0) {
            $errorDetail = $res['errors'];
        } elseif (!empty($res['message'])) {
            $errorDetail = $res['message'];
        }
        $summary['errors'][] = ['row' => $summary['processed'], 'errors' => $errorDetail];
    }
}

fclose($handle);
sendSuccess('Import finished', $summary);

?>
