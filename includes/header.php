<?php
/**
 * Smart Attendance System (SAS)
 * Global Header Template
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

// Generate CSRF token for the pageforms
$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? e($page_title) . " | SAS" : "Smart Attendance System"; ?></title>
    
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 Icons CDN -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    
    <!-- SweetAlert2 CSS & JS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    
    <!-- Custom Theme Stylesheet -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

<!-- SweetAlert Session Alert Handler -->
<?php echo display_alert(); ?>

<div id="wrapper">
    <!-- Include Sidebar Navigation -->
    <?php include_once __DIR__ . '/sidebar.php'; ?>

    <div id="content-wrapper">
        <!-- Top Navbar -->
        <nav id="navbar" class="no-print">
            <div class="d-flex align-items-center gap-3">
                <!-- Sidebar Toggler Buttons -->
                <button id="sidebar-toggle-mob" class="btn btn-light border" aria-label="Toggle Navigation">
                    <i class="fa-solid fa-bars text-primary"></i>
                </button>
                <button id="sidebar-toggle-desk" class="btn btn-light border" aria-label="Collapse Navigation">
                    <i class="fa-solid fa-bars text-primary"></i>
                </button>
                
                <h4 class="mb-0 fw-bold d-none d-sm-block text-primary">
                    <i class="fa-solid fa-graduation-cap me-2"></i>Smart Attendance System
                </h4>
            </div>

            <!-- Profile Dropdown -->
            <div class="dropdown">
                <button class="btn btn-light border dropdown-toggle d-flex align-items-center gap-2" type="button" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-weight: 600;">
                        <?php echo strtoupper(substr($_SESSION['fullname'], 0, 1)); ?>
                    </div>
                    <div class="text-start d-none d-md-block" style="font-size: 0.85rem; line-height: 1.2;">
                        <span class="d-block fw-bold"><?php echo e($_SESSION['fullname']); ?></span>
                        <span class="text-muted d-block" style="font-size: 0.75rem;"><?php echo e($_SESSION['role']); ?></span>
                    </div>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2" aria-labelledby="profileDropdown" style="border-radius: 10px;">
                    <li>
                        <a class="dropdown-item py-2" href="profile.php">
                            <i class="fa-regular fa-user text-muted me-2"></i>My Profile
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item py-2" href="change_password.php">
                            <i class="fa-solid fa-key text-muted me-2"></i>Change Password
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item py-2 text-danger" href="logout.php">
                            <i class="fa-solid fa-right-from-bracket me-2"></i>Log Out
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main Page Content Section -->
        <main class="main-content">
