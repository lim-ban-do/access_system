<?php
/**
 * Smart Attendance System (SAS)
 * Database Connection Configuration
 */

// Database configuration settings (Default settings for standard XAMPP/WAMP environment)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'smart_attendance_db');

try {
    // Create PDO connection with options for strict error reporting and UTF-8 encoding
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", 
        DB_USER, 
        DB_PASS, 
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // Use native prepared statements
        ]
    );
} catch (PDOException $e) {
    // Graceful error termination in case database is down or not created yet
    die("Database Connection Failed: " . $e->getMessage());
}
