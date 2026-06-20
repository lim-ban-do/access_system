<?php
/**
 * Smart Attendance System (SAS)
 * User Profile Dashboard & Management
 */

$page_title = "My Profile";
require_once __DIR__ . '/includes/header.php';

$error = '';
$success = '';

// Load user details directly from database to ensure fresh values
try {
    $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `id` = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        set_alert('error', 'Error', 'User record not found.');
        header("Location: logout.php");
        exit();
    }
} catch (PDOException $e) {
    set_alert('error', 'Database Error', $e->getMessage());
    header("Location: dashboard.php");
    exit();
}

// Handle Profile Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $post_token = $_POST['csrf_token'] ?? '';
    
    // 1. Verify CSRF Token
    if (!verify_csrf_token($post_token)) {
        $error = 'Security check failed. Please refresh the page.';
    }
    // 2. Validate Inputs
    elseif (empty($fullname) || empty($username)) {
        $error = 'All fields are required.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $error = 'Username must be 3-20 characters and contain only letters, numbers, and underscores.';
    } else {
        try {
            // Check if username is already taken by another user
            $check_stmt = $pdo->prepare("SELECT `id` FROM `users` WHERE `username` = ? AND `id` != ? LIMIT 1");
            $check_stmt->execute([$username, $_SESSION['user_id']]);
            
            if ($check_stmt->fetch()) {
                $error = 'The username "@' . e($username) . '" is already taken.';
            } else {
                // Update profile in database
                $update_stmt = $pdo->prepare("UPDATE `users` SET `fullname` = ?, `username` = ? WHERE `id` = ?");
                $update_stmt->execute([$fullname, $username, $_SESSION['user_id']]);
                
                // Update active session variables
                $_SESSION['fullname'] = $fullname;
                $_SESSION['username'] = $username;
                
                // Refresh $user data
                $user['fullname'] = $fullname;
                $user['username'] = $username;
                
                set_alert('success', 'Profile Updated', 'Your profile details have been saved successfully.');
                // Reload page to display changes and fire SweetAlert
                echo "<script>window.location.href='profile.php';</script>";
                exit();
            }
        } catch (PDOException $e) {
            $error = 'Database Error: ' . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid px-0">
    <div class="row">
        <!-- Profile Header / Title Card -->
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm p-4" style="border-radius: 16px;">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                        <i class="fa-solid fa-id-card fs-3"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold mb-0 text-primary">My Profile</h3>
                        <p class="text-muted mb-0">View and manage your account details</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Details Grid -->
        <div class="col-lg-4 mb-4">
            <!-- Summary Card -->
            <div class="card border-0 shadow-sm text-center p-4" style="border-radius: 16px;">
                <div class="profile-avatar mb-3">
                    <?php echo strtoupper(substr($user['fullname'], 0, 1)); ?>
                </div>
                <h4 class="fw-bold mb-1"><?php echo e($user['fullname']); ?></h4>
                <p class="text-muted mb-3">@<?php echo e($user['username']); ?></p>
                
                <span class="badge rounded-pill bg-primary px-3 py-2 fs-6 mb-4">
                    <i class="fa-solid fa-shield-halved me-2"></i><?php echo e($user['role']); ?>
                </span>

                <div class="border-top pt-3 text-start">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Account Type</span>
                        <strong class="text-secondary"><?php echo e($user['role']); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Member Since</span>
                        <strong class="text-secondary"><?php echo date("F j, Y", strtotime($user['created_at'])); ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8 mb-4">
            <!-- Edit Profile Card -->
            <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                <div class="card-header bg-white border-bottom p-4">
                    <h5 class="fw-bold mb-0 text-primary">
                        <i class="fa-solid fa-user-pen me-2"></i>Edit Account Information
                    </h5>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" style="border-radius: 10px;">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            <div><?php echo e($error); ?></div>
                        </div>
                    <?php endif; ?>

                    <form action="profile.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <div class="row">
                            <!-- Full Name -->
                            <div class="col-md-6 mb-3">
                                <label for="fullname" class="form-label fw-semibold text-secondary">Full Name</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-user"></i></span>
                                    <input type="text" class="form-control border-start-0" id="fullname" name="fullname" value="<?php echo e($user['fullname']); ?>" required>
                                </div>
                            </div>
                            
                            <!-- Username -->
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label fw-semibold text-secondary">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted">@</span>
                                    <input type="text" class="form-control border-start-0" id="username" name="username" value="<?php echo e($user['username']); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="dashboard.php" class="btn btn-outline-secondary px-4" style="border-radius: 10px;">
                                <i class="fa-solid fa-xmark me-2"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary px-4" style="border-radius: 10px;">
                                <i class="fa-solid fa-floppy-disk me-2"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
