<?php
/**
 * Smart Attendance System (SAS)
 * User Management Dashboard (Admin Only)
 */

$page_title = "Manage Users";
require_once __DIR__ . '/includes/header.php';

// Enforce role permission check (Admin Only)
require_role('Admin');

// Handle post requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action'] ?? '';
    $post_token = $_POST['csrf_token'] ?? '';
    
    // 1. Verify CSRF Token
    if (!verify_csrf_token($post_token)) {
        set_alert('error', 'Security check failed', 'Invalid security token.');
    } else {
        // --- ADD USER ACTION ---
        if ($action === 'add') {
            $fullname = trim($_POST['fullname'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $role_input = $_POST['role'] ?? 'Teacher';
            
            if (empty($fullname) || empty($username) || empty($password)) {
                set_alert('error', 'Validation Error', 'All fields are required.');
            } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
                set_alert('error', 'Validation Error', 'Username must be 3-20 characters, containing only letters, numbers, and underscores.');
            } else {
                try {
                    // Check if username is already taken
                    $check = $pdo->prepare("SELECT `id` FROM `users` WHERE `username` = ? LIMIT 1");
                    $check->execute([$username]);
                    if ($check->fetch()) {
                        set_alert('error', 'Duplicate Username', "The username '@{$username}' is already taken.");
                    } else {
                        // Hash password
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        
                        $stmt = $pdo->prepare("INSERT INTO `users` (`fullname`, `username`, `password`, `role`) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$fullname, $username, $hash, $role_input]);
                        set_alert('success', 'User Created', "User '{$fullname}' created successfully.");
                    }
                } catch (PDOException $e) {
                    set_alert('error', 'Database Error', $e->getMessage());
                }
            }
        }
        
        // --- EDIT USER ACTION ---
        elseif ($action === 'edit') {
            $user_id    = intval($_POST['user_id'] ?? 0);
            $fullname   = trim($_POST['fullname'] ?? '');
            $username   = trim($_POST['username'] ?? '');
            $password   = $_POST['password'] ?? '';
            $role_input = $_POST['role'] ?? 'Teacher';
            
            if (empty($fullname) || empty($username) || !$user_id) {
                set_alert('error', 'Validation Error', 'Full Name and Username are required.');
            } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
                set_alert('error', 'Validation Error', 'Username must be 3-20 characters long.');
            } else {
                try {
                    // Check if username is taken by another user
                    $check = $pdo->prepare("SELECT `id` FROM `users` WHERE `username` = ? AND `id` != ? LIMIT 1");
                    $check->execute([$username, $user_id]);
                    if ($check->fetch()) {
                        set_alert('error', 'Duplicate Username', "The username '@{$username}' is already taken.");
                    } else {
                        // Check if password is being updated
                        if (!empty($password)) {
                            $hash = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("UPDATE `users` SET `fullname` = ?, `username` = ?, `password` = ?, `role` = ? WHERE `id` = ?");
                            $stmt->execute([$fullname, $username, $hash, $role_input, $user_id]);
                        } else {
                            $stmt = $pdo->prepare("UPDATE `users` SET `fullname` = ?, `username` = ?, `role` = ? WHERE `id` = ?");
                            $stmt->execute([$fullname, $username, $role_input, $user_id]);
                        }
                        
                        // If updating own account, refresh current session data
                        if ($user_id === $_SESSION['user_id']) {
                            $_SESSION['fullname'] = $fullname;
                            $_SESSION['username'] = $username;
                            $_SESSION['role']     = $role_input;
                        }
                        
                        set_alert('success', 'User Updated', "Changes saved successfully.");
                    }
                } catch (PDOException $e) {
                    set_alert('error', 'Database Error', $e->getMessage());
                }
            }
        }
        
        // --- DELETE USER ACTION ---
        elseif ($action === 'delete') {
            $user_id = intval($_POST['user_id'] ?? 0);
            
            // Prevent deleting active session user
            if ($user_id === $_SESSION['user_id']) {
                set_alert('error', 'Action Restricted', 'You cannot delete your own logged-in account.');
            } elseif ($user_id) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM `users` WHERE `id` = ?");
                    $stmt->execute([$user_id]);
                    set_alert('success', 'User Deleted', 'User account deleted successfully.');
                } catch (PDOException $e) {
                    set_alert('error', 'Database Error', $e->getMessage());
                }
            }
        }
    }
    
    // Redirect to clear form resubmissions
    echo "<script>window.location.href='users.php';</script>";
    exit();
}

// Fetch query filters (Search)
$search = trim($_GET['search'] ?? '');
$query_params = [];

$sql = "SELECT `id`, `fullname`, `username`, `role`, `created_at` FROM `users`";
if (!empty($search)) {
    $sql .= " WHERE `fullname` LIKE ? OR `username` LIKE ? ";
    $query_params[] = "%$search%";
    $query_params[] = "%$search%";
}
$sql .= " ORDER BY `fullname` ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($query_params);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Query Error: " . $e->getMessage());
}
?>

<div class="container-fluid px-0">
    <div class="row">
        <!-- Title and Search Section -->
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm p-4" style="border-radius: 16px;">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                            <i class="fa-solid fa-user-gear fs-3"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-0 text-primary">Manage Users</h3>
                            <p class="text-muted mb-0">Manage system administration roles and teachers</p>
                        </div>
                    </div>
                    
                    <button class="btn btn-primary px-4 d-flex align-items-center gap-2" style="border-radius: 10px;" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fa-solid fa-user-plus"></i>Add New User
                    </button>
                </div>
            </div>
        </div>

        <!-- Filter bar -->
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm p-3" style="border-radius: 12px;">
                <form action="users.php" method="GET" class="row g-2 justify-content-end">
                    <div class="col-md-4 col-sm-6">
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" value="<?php echo e($search); ?>" placeholder="Search user name or username...">
                            <button class="btn btn-primary" type="submit">
                                <i class="fa-solid fa-magnifying-glass"></i>
                            </button>
                            <?php if (!empty($search)): ?>
                                <a href="users.php" class="btn btn-outline-secondary">Clear</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Users Table Card -->
        <div class="col-12">
            <div class="card border-0 shadow-sm" style="border-radius: 16px; overflow: hidden;">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.95rem;">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4 py-3">#</th>
                                    <th class="py-3">Full Name</th>
                                    <th class="py-3">Username</th>
                                    <th class="py-3">System Role</th>
                                    <th class="py-3">Created At</th>
                                    <th class="pe-4 py-3 text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="fa-solid fa-folder-open fs-2 mb-3 d-block text-secondary"></i>
                                            No user records found.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $idx => $user): ?>
                                        <tr>
                                            <td class="ps-4"><?php echo $idx + 1; ?></td>
                                            <td class="fw-bold"><?php echo e($user['fullname']); ?></td>
                                            <td>@<?php echo e($user['username']); ?></td>
                                            <td>
                                                <?php if ($user['role'] === 'Admin'): ?>
                                                    <span class="badge bg-danger-subtle text-danger px-3 py-1 fs-6">
                                                        <i class="fa-solid fa-shield-halved me-1"></i>Admin
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary-subtle text-primary px-3 py-1 fs-6">
                                                        <i class="fa-solid fa-chalkboard me-1"></i>Teacher
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date("M j, Y", strtotime($user['created_at'])); ?></td>
                                            <td class="pe-4 text-end">
                                                <div class="d-flex justify-content-end gap-2">
                                                    <!-- Edit Button -->
                                                    <button class="btn btn-sm btn-outline-primary" style="border-radius: 6px;" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#editUserModal"
                                                            data-id="<?php echo $user['id']; ?>"
                                                            data-fullname="<?php echo e($user['fullname']); ?>"
                                                            data-username="<?php echo e($user['username']); ?>"
                                                            data-role="<?php echo e($user['role']); ?>">
                                                        <i class="fa-solid fa-user-pen"></i>
                                                    </button>
                                                    
                                                    <!-- Delete Button -->
                                                    <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                                        <button class="btn btn-sm btn-outline-danger" style="border-radius: 6px;"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#deleteUserModal"
                                                                data-id="<?php echo $user['id']; ?>"
                                                                data-fullname="<?php echo e($user['fullname']); ?>">
                                                            <i class="fa-solid fa-trash-can"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-light border text-muted" style="border-radius: 6px;" disabled title="Active account">
                                                            <i class="fa-solid fa-lock"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODALS ================= -->

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-bottom p-4">
                <h5 class="modal-title fw-bold text-primary" id="addUserModalLabel">
                    <i class="fa-solid fa-user-plus me-2"></i>Add New User
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="users.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="mb-3">
                        <label for="add_fullname" class="form-label fw-semibold text-secondary">Full Name</label>
                        <input type="text" class="form-control" id="add_fullname" name="fullname" placeholder="e.g. John Doe" required>
                    </div>
                    <div class="mb-3">
                        <label for="add_username" class="form-label fw-semibold text-secondary">Username</label>
                        <input type="text" class="form-control" id="add_username" name="username" placeholder="e.g. johndoe" required>
                    </div>
                    <div class="mb-3">
                        <label for="add_password" class="form-label fw-semibold text-secondary">Password</label>
                        <input type="password" class="form-control" id="add_password" name="password" placeholder="Create password" required>
                    </div>
                    <div class="mb-3">
                        <label for="add_role" class="form-label fw-semibold text-secondary">Role</label>
                        <select class="form-select" id="add_role" name="role" required>
                            <option value="Teacher" selected>Teacher</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top p-3 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary px-4"><i class="fa-solid fa-floppy-disk me-2"></i>Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-bottom p-4">
                <h5 class="modal-title fw-bold text-primary" id="editUserModalLabel">
                    <i class="fa-solid fa-user-pen me-2"></i>Edit User Account
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="users.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" id="edit_user_id" name="user_id">
                    
                    <div class="mb-3">
                        <label for="edit_fullname" class="form-label fw-semibold text-secondary">Full Name</label>
                        <input type="text" class="form-control" id="edit_fullname" name="fullname" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_username" class="form-label fw-semibold text-secondary">Username</label>
                        <input type="text" class="form-control" id="edit_username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_password" class="form-label fw-semibold text-secondary">Password (Leave blank to keep current)</label>
                        <input type="password" class="form-control" id="edit_password" name="password" placeholder="Enter new password to change">
                    </div>
                    <div class="mb-3">
                        <label for="edit_role" class="form-label fw-semibold text-secondary">Role</label>
                        <select class="form-select" id="edit_role" name="role" required>
                            <option value="Teacher">Teacher</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top p-3 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary px-4"><i class="fa-solid fa-floppy-disk me-2"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-bottom p-4">
                <h5 class="modal-title fw-bold text-danger" id="deleteUserModalLabel">
                    <i class="fa-solid fa-trash-can me-2"></i>Delete User Account
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="users.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" id="delete_user_id" name="user_id">
                    
                    <p class="mb-0 text-secondary">
                        Are you sure you want to delete account for user <strong id="delete_user_fullname" class="text-danger"></strong>?
                    </p>
                    <p class="text-muted mt-2 mb-0" style="font-size: 0.85rem;">
                        <i class="fa-solid fa-circle-info me-1 text-info"></i>
                        The deleted user will immediately lose access to this admin panel.
                    </p>
                </div>
                <div class="modal-footer border-top p-3 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger px-4"><i class="fa-solid fa-trash-can me-2"></i>Confirm Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Populate Edit Modal fields
    const editModal = document.getElementById('editUserModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            const btn = event.relatedTarget;
            document.getElementById('edit_user_id').value = btn.getAttribute('data-id');
            document.getElementById('edit_fullname').value = btn.getAttribute('data-fullname');
            document.getElementById('edit_username').value = btn.getAttribute('data-username');
            document.getElementById('edit_role').value = btn.getAttribute('data-role');
        });
    }

    // Populate Delete Modal fields
    const deleteModal = document.getElementById('deleteUserModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function(event) {
            const btn = event.relatedTarget;
            document.getElementById('delete_user_id').value = btn.getAttribute('data-id');
            document.getElementById('delete_user_fullname').textContent = btn.getAttribute('data-fullname');
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
