<?php
/**
 * Smart Attendance System (SAS)
 * User Sign Out / Session Termination
 */

require_once __DIR__ . '/includes/functions.php';

// Safe session start
start_session_safe();

// Clear and destroy the session
session_unset();
session_destroy();

// Start a fresh temporary session to pass the logged out success message
start_session_safe();
set_alert('success', 'Logged Out', 'You have successfully signed out.');

// Redirect back to login page
header("Location: login.php");
exit();
