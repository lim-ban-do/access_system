<?php
/**
 * Smart Attendance System (SAS)
 * Change Account Password View
 */

$page_title = "Change Password";
require_once __DIR__ . '/includes/header.php';

$error = '';

// Handle Password Update Post request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $post_token       = $_POST['csrf_token'] ?? '';
    
    // 1. Verify CSRF Token
    if (!verify_csrf_token($post_token)) {
        $error = 'Security check failed. Please refresh the page.';
    }
    // 2. Validate Fields
    elseif (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New password and confirmation password do not match.';
    } elseif (strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters long.';
    } else {
        try {
            // Retrieve current password hash
            $stmt = $pdo->prepare("SELECT `password` FROM `users` WHERE `id` = ? LIMIT 1");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($current_password, $user['password'])) {
                // Hash new password using secure bcrypt algorithm
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password in database
                $update_stmt = $pdo->prepare("UPDATE `users` SET `password` = ? WHERE `id` = ?");
                $update_stmt->execute([$new_hash, $_SESSION['user_id']]);
                
                set_alert('success', 'Password Updated', 'Your account password has been changed successfully.');
                echo "<script>window.location.href='profile.php';</script>";
                exit();
            } else {
                $error = 'Your current password is incorrect.';
            }
        } catch (PDOException $e) {
            $error = 'Database Error: ' . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid px-0">
    <div class="row justify-content-center">
        <!-- Title Card -->
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm p-4" style="border-radius: 16px;">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                        <i class="fa-solid fa-key fs-3"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold mb-0 text-primary">Change Password</h3>
                        <p class="text-muted mb-0">Update your account authentication credentials</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <!-- Form Card -->
            <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                <div class="card-header bg-white border-bottom p-4">
                    <h5 class="fw-bold mb-0 text-primary">
                        <i class="fa-solid fa-lock-open me-2"></i>Update Password
                    </h5>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" style="border-radius: 10px;">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            <div><?php echo e($error); ?></div>
                        </div>
                    <?php endif; ?>

                    <form action="change_password.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                        <!-- Current Password -->
                        <div class="mb-3">
                            <label for="current_password" class="form-label fw-semibold text-secondary">Current Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-lock text-secondary"></i></span>
                                <input type="password" class="form-control border-start-0" id="current_password" name="current_password" placeholder="Enter current password" required>
                            </div>
                        </div>

                        <hr class="my-4 text-muted opacity-25">

                        <!-- New Password -->
                        <div class="mb-3">
                            <label for="new_password" class="form-label fw-semibold text-secondary">New Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-key text-secondary"></i></span>
                                <input type="password" class="form-control border-start-0" id="new_password" name="new_password" placeholder="Enter new password (min. 6 characters)" required>
                            </div>
                        </div>

                        <!-- Confirm Password -->
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label fw-semibold text-secondary">Confirm New Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-check-double text-secondary"></i></span>
                                <input type="password" class="form-control border-start-0" id="confirm_password" name="confirm_password" placeholder="Re-enter new password" required>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="profile.php" class="btn btn-outline-secondary px-4" style="border-radius: 10px;">
                                <i class="fa-solid fa-xmark me-2"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary px-4" style="border-radius: 10px;">
                                <i class="fa-solid fa-shield-halved me-2"></i>Update Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
