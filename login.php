<?php
/**
 * Smart Attendance System (SAS)
 * User Sign In / Authentication Page
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';

// Safe session start
start_session_safe();

// Redirect user to dashboard if already authenticated
if (is_logged_in()) {
    header("Location: dashboard.php");
    exit();
}

$csrf_token = generate_csrf_token();
$error = '';

// Handle Sign In submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $post_token = $_POST['csrf_token'] ?? '';
    
    // 1. Verify CSRF Token
    if (!verify_csrf_token($post_token)) {
        $error = 'Security check failed. Please refresh and try again.';
    }
    // 2. Validate Inputs
    elseif (empty($username) || empty($password)) {
        $error = 'Please fill in all credentials.';
    } else {
        // Fetch user from DB using prepared statement
        try {
            $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `username` = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            // Verify password using native bcrypt verification
            if ($user && password_verify($password, $user['password'])) {
                // Populate session details
                $_SESSION['user_id']       = $user['id'];
                $_SESSION['username']     = $user['username'];
                $_SESSION['fullname']     = $user['fullname'];
                $_SESSION['role']         = $user['role'];
                $_SESSION['last_activity'] = time();
                
                set_alert('success', 'Welcome Back', 'Logged in successfully as ' . $user['fullname']);
                header("Location: dashboard.php");
                exit();
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (PDOException $e) {
            $error = 'Database Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | Smart Attendance System</title>
    
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        
        .login-card {
            background-color: #ffffff;
            border-radius: 20px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            width: 100%;
            max-width: 450px;
        }
        
        .login-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            color: #ffffff;
            padding: 2.5rem 2rem;
            text-align: center;
        }
        
        .login-body {
            padding: 2.5rem 2rem;
        }
        
        .form-control {
            padding: 0.75rem 1rem;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
        }
        
        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }
        
        .btn-primary {
            background-color: #1e40af;
            border: none;
            padding: 0.75rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        
        .btn-primary:hover {
            background-color: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.2);
        }
    </style>
</head>
<body>

<!-- Render dynamic alerts if present -->
<?php echo display_alert(); ?>

<div class="login-card">
    <!-- Header -->
    <div class="login-header">
        <i class="fa-solid fa-graduation-cap fs-1 mb-2"></i>
        <h3 class="fw-bold mb-0">SAS Portal</h3>
        <p class="text-white-50 mb-0 mt-1">Smart Attendance System Login</p>
    </div>
    
    <!-- Form Body -->
    <div class="login-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" style="border-radius: 10px;">
                <i class="fa-solid fa-circle-exclamation"></i>
                <div style="font-size: 0.9rem;"><?php echo e($error); ?></div>
            </div>
        <?php endif; ?>
        
        <form action="login.php" method="POST">
            <!-- CSRF Token Input -->
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <!-- Username -->
            <div class="mb-3">
                <label for="username" class="form-label text-secondary fw-semibold">Username</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-secondary border-end-0" style="border-radius: 10px 0 0 10px;">
                        <i class="fa-regular fa-user"></i>
                    </span>
                    <input type="text" class="form-control border-start-0" id="username" name="username" placeholder="Enter username" style="border-radius: 0 10px 10px 0;" required autocomplete="username">
                </div>
            </div>
            
            <!-- Password -->
            <div class="mb-4">
                <label for="password" class="form-label text-secondary fw-semibold">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-secondary border-end-0" style="border-radius: 10px 0 0 10px;">
                        <i class="fa-solid fa-lock"></i>
                    </span>
                    <input type="password" class="form-control border-start-0" id="password" name="password" placeholder="Enter password" style="border-radius: 0 10px 10px 0;" required autocomplete="current-password">
                </div>
            </div>
            
            <!-- Submit button -->
            <button type="submit" class="btn btn-primary w-100 mb-2">
                <i class="fa-solid fa-right-to-bracket me-2"></i>Sign In
            </button>
        </form>
    </div>
</div>

<!-- Bootstrap 5 JS Bundle CDN -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
