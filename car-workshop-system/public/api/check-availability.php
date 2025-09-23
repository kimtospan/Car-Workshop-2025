<?php


require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/models/User.php';
require_once __DIR__ . '/../../src/models/Appointment.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

try {
    $userModel = new User();
    
    // Check available time slots for appointments
    if (isset($_GET['date'])) {
        $date = $_GET['date'];
        
        // Validate date format and that it's today or future
        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        $today = new DateTime('today');
        
        if (!$dateObj || $dateObj < $today) {
            sendError('Invalid date or date is in the past', 400);
        }
        
        $appointmentModel = new Appointment();
        $availableSlots = $appointmentModel->getAvailableTimeSlots($date);
        
        sendSuccess('Available time slots retrieved successfully', $availableSlots);
    }
    
    // Check username availability
    if (isset($_GET['username'])) {
        $username = trim($_GET['username']);
        
        if (empty($username)) {
            sendError('Username cannot be empty', 400);
        }
        
        if (strlen($username) < 3) {
            sendSuccess('Username too short', ['available' => false]);
        }
        
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            sendSuccess('Invalid username format', ['available' => false]);
        }
        
        $exists = $userModel->usernameExists($username);
        
        if ($exists) {
            sendSuccess('Username is already taken', ['available' => false]);
        } else {
            sendSuccess('Username is available', ['available' => true]);
        }
    }
    
    // Check email availability
    if (isset($_GET['email'])) {
        $email = trim($_GET['email']);
        
        if (empty($email)) {
            sendError('Email cannot be empty', 400);
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendSuccess('Invalid email format', ['available' => false]);
        }
        
        $exists = $userModel->emailExists($email);
        
        if ($exists) {
            sendSuccess('Email is already registered', ['available' => false]);
        } else {
            sendSuccess('Email is available', ['available' => true]);
        }
    }
    
    // If no parameters provided
    sendError('No field specified for availability check', 400);
    
} catch (Exception $e) {
    error_log("Availability check error: " . $e->getMessage());
    sendError('An error occurred during availability check', 500);
}
?>
