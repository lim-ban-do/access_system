<?php
/**
 * Smart Attendance System (SAS)
 * Class Management CRUD Controller
 */

$page_title = "Manage Classes";
require_once __DIR__ . '/includes/header.php';

// Check post request operations
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action'] ?? '';
    $post_token = $_POST['csrf_token'] ?? '';
    
    // 1. Verify CSRF Token
    if (!verify_csrf_token($post_token)) {
        set_alert('error', 'Security check failed', 'Invalid security token.');
    } else {
        // --- ADD ACTION ---
        if ($action === 'add') {
            $class_name    = trim($_POST['class_name'] ?? '');
            $academic_year = trim($_POST['academic_year'] ?? '');
            
            if (empty($class_name) || empty($academic_year)) {
                set_alert('error', 'Validation Error', 'All fields are required.');
            } else {
                try {
                    // Check if class already exists
                    $check = $pdo->prepare("SELECT `class_id` FROM `classes` WHERE `class_name` = ? LIMIT 1");
                    $check->execute([$class_name]);
                    if ($check->fetch()) {
                        set_alert('error', 'Duplicate Entry', "Class '{$class_name}' already exists.");
                    } else {
                        // Insert class record
                        $stmt = $pdo->prepare("INSERT INTO `classes` (`class_name`, `academic_year`) VALUES (?, ?)");
                        $stmt->execute([$class_name, $academic_year]);
                        set_alert('success', 'Class Created', "Class '{$class_name}' has been created.");
                    }
                } catch (PDOException $e) {
                    set_alert('error', 'Database Error', $e->getMessage());
                }
            }
        }
        
        // --- EDIT ACTION ---
        elseif ($action === 'edit') {
            $class_id      = intval($_POST['class_id'] ?? 0);
            $class_name    = trim($_POST['class_name'] ?? '');
            $academic_year = trim($_POST['academic_year'] ?? '');
            
            if (empty($class_name) || empty($academic_year) || !$class_id) {
                set_alert('error', 'Validation Error', 'All fields are required.');
            } else {
                try {
                    // Check if class already exists under a different ID
                    $check = $pdo->prepare("SELECT `class_id` FROM `classes` WHERE `class_name` = ? AND `class_id` != ? LIMIT 1");
                    $check->execute([$class_name, $class_id]);
                    if ($check->fetch()) {
                        set_alert('error', 'Duplicate Entry', "Another class named '{$class_name}' already exists.");
                    } else {
                        // Update class record
                        $stmt = $pdo->prepare("UPDATE `classes` SET `class_name` = ?, `academic_year` = ? WHERE `class_id` = ?");
                        $stmt->execute([$class_name, $academic_year, $class_id]);
                        set_alert('success', 'Class Updated', "Class details saved successfully.");
                    }
                } catch (PDOException $e) {
                    set_alert('error', 'Database Error', $e->getMessage());
                }
            }
        }
        
        // --- DELETE ACTION ---
        elseif ($action === 'delete') {
            $class_id = intval($_POST['class_id'] ?? 0);
            if ($class_id) {
                try {
                    // Delete class record (cascade will remove linked students and attendance)
                    $stmt = $pdo->prepare("DELETE FROM `classes` WHERE `class_id` = ?");
                    $stmt->execute([$class_id]);
                    set_alert('success', 'Class Deleted', 'Class removed successfully.');
                } catch (PDOException $e) {
                    set_alert('error', 'Database Error', $e->getMessage());
                }
            }
        }
    }
    
    // Redirect to clear form resubmissions
    echo "<script>window.location.href='classes.php';</script>";
    exit();
}

// Fetch query filters (Search and Pagination)
$search = trim($_GET['search'] ?? '');
$query_params = [];

$sql = "SELECT c.*, COUNT(s.student_id) as total_students 
        FROM `classes` c 
        LEFT JOIN `students` s ON c.class_id = s.class_id ";

if (!empty($search)) {
    $sql .= " WHERE c.class_name LIKE ? OR c.academic_year LIKE ? ";
    $query_params[] = "%$search%";
    $query_params[] = "%$search%";
}

$sql .= " GROUP BY c.class_id ORDER BY c.class_name ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($query_params);
    $classes = $stmt->fetchAll();
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
                            <i class="fa-solid fa-chalkboard-user fs-3"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-0 text-primary">Manage Classes</h3>
                            <p class="text-muted mb-0">Add, edit, and view class levels</p>
                        </div>
                    </div>
                    
                    <button class="btn btn-primary px-4 d-flex align-items-center gap-2" style="border-radius: 10px;" data-bs-toggle="modal" data-bs-target="#addClassModal">
                        <i class="fa-solid fa-plus"></i>Add New Class
                    </button>
                </div>
            </div>
        </div>

        <!-- Filter bar -->
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm p-3" style="border-radius: 12px;">
                <form action="classes.php" method="GET" class="row g-2 justify-content-end">
                    <div class="col-md-4 col-sm-6">
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" value="<?php echo e($search); ?>" placeholder="Search class name or year...">
                            <button class="btn btn-primary" type="submit">
                                <i class="fa-solid fa-magnifying-glass"></i>
                            </button>
                            <?php if (!empty($search)): ?>
                                <a href="classes.php" class="btn btn-outline-secondary">Clear</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Class Table Card -->
        <div class="col-12">
            <div class="card border-0 shadow-sm" style="border-radius: 16px; overflow: hidden;">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.95rem;">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4 py-3">#</th>
                                    <th class="py-3">Class Name</th>
                                    <th class="py-3">Academic Year</th>
                                    <th class="py-3">Total Enrolled</th>
                                    <th class="pe-4 py-3 text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($classes)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="fa-solid fa-folder-open fs-2 mb-3 d-block text-secondary"></i>
                                            No classes registered yet.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($classes as $idx => $class): ?>
                                        <tr>
                                            <td class="ps-4"><?php echo $idx + 1; ?></td>
                                            <td class="fw-bold text-primary"><?php echo e($class['class_name']); ?></td>
                                            <td><?php echo e($class['academic_year']); ?></td>
                                            <td>
                                                <span class="badge bg-info-subtle text-info px-2 py-1 fs-6">
                                                    <?php echo e($class['total_students']); ?> Student(s)
                                                </span>
                                            </td>
                                            <td class="pe-4 text-end">
                                                <div class="d-flex justify-content-end gap-2">
                                                    <!-- Edit Button -->
                                                    <button class="btn btn-sm btn-outline-primary" style="border-radius: 6px;" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#editClassModal"
                                                            data-id="<?php echo $class['class_id']; ?>"
                                                            data-name="<?php echo e($class['class_name']); ?>"
                                                            data-year="<?php echo e($class['academic_year']); ?>">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </button>
                                                    
                                                    <!-- Delete Button -->
                                                    <button class="btn btn-sm btn-outline-danger" style="border-radius: 6px;"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#deleteClassModal"
                                                            data-id="<?php echo $class['class_id']; ?>"
                                                            data-name="<?php echo e($class['class_name']); ?>">
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </button>
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

<!-- Add Class Modal -->
<div class="modal fade" id="addClassModal" tabindex="-1" aria-labelledby="addClassModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-bottom p-4">
                <h5 class="modal-title fw-bold text-primary" id="addClassModalLabel">
                    <i class="fa-solid fa-plus-circle me-2"></i>Add New Class
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="classes.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="mb-3">
                        <label for="add_class_name" class="form-label fw-semibold text-secondary">Class Name</label>
                        <input type="text" class="form-control" id="add_class_name" name="class_name" placeholder="e.g. Class 10-A" required>
                    </div>
                    <div class="mb-3">
                        <label for="add_academic_year" class="form-label fw-semibold text-secondary">Academic Year</label>
                        <input type="text" class="form-control" id="add_academic_year" name="academic_year" placeholder="e.g. 2026-2027" required>
                    </div>
                </div>
                <div class="modal-footer border-top p-3 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary px-4"><i class="fa-solid fa-floppy-disk me-2"></i>Save Class</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Class Modal -->
<div class="modal fade" id="editClassModal" tabindex="-1" aria-labelledby="editClassModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-bottom p-4">
                <h5 class="modal-title fw-bold text-primary" id="editClassModalLabel">
                    <i class="fa-solid fa-edit me-2"></i>Edit Class Level
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="classes.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" id="edit_class_id" name="class_id">
                    
                    <div class="mb-3">
                        <label for="edit_class_name" class="form-label fw-semibold text-secondary">Class Name</label>
                        <input type="text" class="form-control" id="edit_class_name" name="class_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_academic_year" class="form-label fw-semibold text-secondary">Academic Year</label>
                        <input type="text" class="form-control" id="edit_academic_year" name="academic_year" required>
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

<!-- Delete Class Modal -->
<div class="modal fade" id="deleteClassModal" tabindex="-1" aria-labelledby="deleteClassModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-bottom p-4">
                <h5 class="modal-title fw-bold text-danger" id="deleteClassModalLabel">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>Delete Class
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="classes.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" id="delete_class_id" name="class_id">
                    
                    <p class="mb-0 text-secondary">
                        Are you sure you want to delete class <strong id="delete_class_name_span" class="text-danger"></strong>?
                    </p>
                    <p class="text-muted mt-2 mb-0" style="font-size: 0.85rem;">
                        <i class="fa-solid fa-triangle-exclamation me-1 text-warning"></i>
                        <strong>Warning:</strong> This will delete all enrolled students and all their attendance history. This action cannot be undone.
                    </p>
                </div>
                <div class="modal-footer border-top p-3 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger px-4"><i class="fa-solid fa-trash-can me-2"></i>Delete Permanently</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Populate Edit Modal fields
    const editModal = document.getElementById('editClassModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            const btn = event.relatedTarget;
            document.getElementById('edit_class_id').value = btn.getAttribute('data-id');
            document.getElementById('edit_class_name').value = btn.getAttribute('data-name');
            document.getElementById('edit_academic_year').value = btn.getAttribute('data-year');
        });
    }

    // Populate Delete Modal fields
    const deleteModal = document.getElementById('deleteClassModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function(event) {
            const btn = event.relatedTarget;
            document.getElementById('delete_class_id').value = btn.getAttribute('data-id');
            document.getElementById('delete_class_name_span').textContent = btn.getAttribute('data-name');
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
