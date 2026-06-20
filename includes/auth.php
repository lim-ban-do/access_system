<?php
/**
 * Smart Attendance System (SAS)
 * Session-based Authentication Guard
 */

require_once __DIR__ . '/functions.php';

// Safe session start
start_session_safe();

// If user is not authenticated, redirect to login page
if (!is_logged_in()) {
    header("Location: login.php");
    exit();
}

// 30 Minutes Session Timeout Guard (1800 seconds)
$timeout_seconds = 1800;

if (isset($_SESSION['last_activity'])) {
    $duration = time() - $_SESSION['last_activity'];
    if ($duration > $timeout_seconds) {
        // Clear all session variables
        session_unset();
        session_destroy();
        
        // Start a new session to set the alert
        start_session_safe();
        set_alert('warning', 'Session Timeout', 'You have been logged out due to inactivity.');
        header("Location: login.php");
        exit();
    }
}

// Update last activity timestamp
$_SESSION['last_activity'] = time();
