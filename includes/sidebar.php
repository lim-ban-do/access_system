<?php
/**
 * Smart Attendance System (SAS)
 * Navigation Sidebar Template
 */

$current_file = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? 'Teacher';
?>
<aside id="sidebar" class="no-print">
    <!-- Sidebar Header -->
    <div class="sidebar-header">
        <div class="d-flex align-items-center gap-2">
            <i class="fa-solid fa-graduation-cap text-white fs-3"></i>
            <span class="fw-bold fs-5">SAS Admin</span>
        </div>
    </div>

    <!-- Sidebar Menu Items -->
    <div class="sidebar-menu">
        <ul class="nav flex-column">
            <!-- Dashboard Link -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_file === 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="fa-solid fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <!-- Attendance Tracking Link -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_file === 'attendance.php') ? 'active' : ''; ?>" href="attendance.php">
                    <i class="fa-solid fa-clipboard-user"></i>
                    <span>Mark Attendance</span>
                </a>
            </li>

            <!-- Classes Management Link -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_file === 'classes.php') ? 'active' : ''; ?>" href="classes.php">
                    <i class="fa-solid fa-chalkboard-user"></i>
                    <span>Manage Classes</span>
                </a>
            </li>

            <!-- Students Management Link -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_file === 'students.php') ? 'active' : ''; ?>" href="students.php">
                    <i class="fa-solid fa-users"></i>
                    <span>Manage Students</span>
                </a>
            </li>

            <!-- Reports Link -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_file === 'reports.php') ? 'active' : ''; ?>" href="reports.php">
                    <i class="fa-solid fa-file-invoice"></i>
                    <span>Reports & Exports</span>
                </a>
            </li>

            <!-- Users Management Link (Admin Only) -->
            <?php if ($role === 'Admin'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_file === 'users.php') ? 'active' : ''; ?>" href="users.php">
                    <i class="fa-solid fa-user-gear"></i>
                    <span>Manage Users</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Profile Settings Link -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_file === 'profile.php' || $current_file === 'change_password.php') ? 'active' : ''; ?>" href="profile.php">
                    <i class="fa-solid fa-user-circle"></i>
                    <span>My Profile</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <a class="nav-link text-danger-hover py-1" href="logout.php">
            <i class="fa-solid fa-right-from-bracket text-danger me-2"></i>
            <span>Log Out</span>
        </a>
    </div>
</aside>
