<?php
/**
 * Smart Attendance System (SAS)
 * Core Utility and Security Functions
 */

// Initialize session safely if not started yet
function start_session_safe() {
    if (session_status() === PHP_SESSION_NONE) {
        // Enforce cookie security attributes
        session_set_cookie_params([
            'lifetime' => 0,          // Cookie expires when browser closes
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // HTTPS only if active
            'httponly' => true,        // Mitigate XSS stealing session cookies
            'samesite' => 'Strict'     // Mitigate CSRF
        ]);
        session_start();
    }
}

// XSS Prevention helper: Escape outputs before rendering in HTML
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// CSRF Token Generation: Creates token if not already in session
function generate_csrf_token() {
    start_session_safe();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF Token Verification: Checks token matches session token
function verify_csrf_token($token) {
    start_session_safe();
    if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        return true;
    }
    return false;
}

// Input Sanitization helper (trim, strip slashes, and escape)
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Check if user is logged in
function is_logged_in() {
    start_session_safe();
    return isset($_SESSION['user_id']);
}

// Check user role
function has_role($role) {
    start_session_safe();
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Require a specific role or array of roles to access a page
function require_role($allowed_roles) {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit();
    }
    
    $role = $_SESSION['role'];
    if (is_array($allowed_roles)) {
        if (!in_array($role, $allowed_roles)) {
            set_alert('error', 'Unauthorized Access', 'You do not have permission to access this page.');
            header("Location: dashboard.php");
            exit();
        }
    } else {
        if ($role !== $allowed_roles) {
            set_alert('error', 'Unauthorized Access', 'You do not have permission to access this page.');
            header("Location: dashboard.php");
            exit();
        }
    }
}

// Set a redirect alert in session (to show on the next loaded page)
function set_alert($type, $title, $message) {
    start_session_safe();
    $_SESSION['alert'] = [
        'type'    => $type, // success, error, warning, info
        'title'   => $title,
        'message' => $message
    ];
}

// Display SweetAlert2 notification if queued in session
function display_alert() {
    start_session_safe();
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        unset($_SESSION['alert']); // Consume alert so it doesn't repeat
        
        $type = e($alert['type']);
        $title = e($alert['title']);
        $message = e($alert['message']);
        
        return "
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: '{$type}',
                    title: '{$title}',
                    text: '{$message}',
                    confirmButtonColor: '#3085d6',
                    timer: 4000
                });
            });
        </script>
        ";
    }
    return '';
}

// Generate offline student QR Code as SVG vector image
function generate_student_qr($student_id, $fullname) {
    $qr_content = "STU-" . $student_id; // Unique identifier structure
    
    // Include splitbrain's QRCode library
    require_once __DIR__ . '/QRCode.php';
    
    try {
        // Generate SVG string
        $svg = \splitbrain\phpQRCode\QRCode::svg($qr_content);
        
        // Build filename and absolute path
        $filename = "qr_" . $student_id . "_" . md5($student_id . $fullname) . ".svg";
        $filepath = __DIR__ . "/../assets/qr/" . $filename;
        
        // Write SVG file to assets/qr/ folder
        if (file_put_contents($filepath, $svg) !== false) {
            return "assets/qr/" . $filename; // Return relative path for DB storage
        }
    } catch (Exception $e) {
        error_log("QR Code Generation Error: " . $e->getMessage());
    }
    return null;
}
