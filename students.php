<?php
/**
 * Smart Attendance System (SAS)
 * Student Management CRUD Controller
 */

$page_title = "Manage Students";
require_once __DIR__ . '/includes/header.php';

// Handle post requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action'] ?? '';
    $post_token = $_POST['csrf_token'] ?? '';
    
    // 1. Verify CSRF Token
    if (!verify_csrf_token($post_token)) {
        set_alert('error', 'Security check failed', 'Invalid security token.');
    } else {
        // --- ADD STUDENT ACTION ---
        if ($action === 'add') {
            $fullname = trim($_POST['fullname'] ?? '');
            $gender   = $_POST['gender'] ?? '';
            $phone    = trim($_POST['phone'] ?? '');
            $class_id = intval($_POST['class_id'] ?? 0);
            
            if (empty($fullname) || empty($gender) || !$class_id) {
                set_alert('error', 'Validation Error', 'Full Name, Gender, and Class are required.');
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    // First insert student details (qr_code is temporarily NULL)
                    $stmt = $pdo->prepare("INSERT INTO `students` (`fullname`, `gender`, `phone`, `class_id`, `qr_code`) VALUES (?, ?, ?, ?, NULL)");
                    $stmt->execute([$fullname, $gender, $phone, $class_id]);
                    $student_id = $pdo->lastInsertId();
                    
                    // Generate offline QR Code SVG
                    $qr_path = generate_student_qr($student_id, $fullname);
                    
                    if ($qr_path) {
                        // Update student row with the generated QR path
                        $update = $pdo->prepare("UPDATE `students` SET `qr_code` = ? WHERE `student_id` = ?");
                        $update->execute([$qr_path, $student_id]);
                        
                        $pdo->commit();
                        set_alert('success', 'Student Created', "Student '{$fullname}' registered and QR Code generated.");
                    } else {
                        throw new Exception("Failed to generate student QR Code SVG.");
                    }
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    set_alert('error', 'Database Error', $e->getMessage());
                }
            }
        }
        
        // --- EDIT STUDENT ACTION ---
        elseif ($action === 'edit') {
            $student_id = intval($_POST['student_id'] ?? 0);
            $fullname   = trim($_POST['fullname'] ?? '');
            $gender     = $_POST['gender'] ?? '';
            $phone      = trim($_POST['phone'] ?? '');
            $class_id   = intval($_POST['class_id'] ?? 0);
            
            if (empty($fullname) || empty($gender) || !$class_id || !$student_id) {
                set_alert('error', 'Validation Error', 'Full Name, Gender, and Class are required.');
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    // Fetch existing details to check if name changed (to regenerate QR if needed)
                    $old_stmt = $pdo->prepare("SELECT `fullname`, `qr_code` FROM `students` WHERE `student_id` = ? LIMIT 1");
                    $old_stmt->execute([$student_id]);
                    $old_student = $old_stmt->fetch();
                    
                    $qr_path = $old_student['qr_code'];
                    
                    // If name changed, we delete old QR file and generate a new one
                    if ($old_student && $old_student['fullname'] !== $fullname) {
                        if (!empty($old_student['qr_code']) && file_exists(__DIR__ . '/../' . $old_student['qr_code'])) {
                            unlink(__DIR__ . '/../' . $old_student['qr_code']);
                        }
                        $qr_path = generate_student_qr($student_id, $fullname);
                    }
                    
                    $stmt = $pdo->prepare("UPDATE `students` SET `fullname` = ?, `gender` = ?, `phone` = ?, `class_id` = ?, `qr_code` = ? WHERE `student_id` = ?");
                    $stmt->execute([$fullname, $gender, $phone, $class_id, $qr_path, $student_id]);
                    
                    $pdo->commit();
                    set_alert('success', 'Student Updated', "Changes saved successfully.");
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    set_alert('error', 'Database Error', $e->getMessage());
                }
            }
        }
        
        // --- DELETE STUDENT ACTION ---
        elseif ($action === 'delete') {
            $student_id = intval($_POST['student_id'] ?? 0);
            if ($student_id) {
                try {
                    // Fetch QR path to delete local file
                    $stmt = $pdo->prepare("SELECT `qr_code` FROM `students` WHERE `student_id` = ? LIMIT 1");
                    $stmt->execute([$student_id]);
                    $student = $stmt->fetch();
                    
                    if ($student && !empty($student['qr_code']) && file_exists(__DIR__ . '/../' . $student['qr_code'])) {
                        unlink(__DIR__ . '/../' . $student['qr_code']);
                    }
                    
                    // Delete student row (attendance logs will cascade delete)
                    $del_stmt = $pdo->prepare("DELETE FROM `students` WHERE `student_id` = ?");
                    $del_stmt->execute([$student_id]);
                    
                    set_alert('success', 'Student Deleted', 'Student record and QR Code removed.');
                } catch (PDOException $e) {
                    set_alert('error', 'Database Error', $e->getMessage());
                }
            }
        }
    }
    
    // Redirect to clear form resubmissions
    echo "<script>window.location.href='students.php';</script>";
    exit();
}

// Fetch query filters (Search, Class Filter)
$search = trim($_GET['search'] ?? '');
$class_filter = intval($_GET['class_id'] ?? 0);
$query_params = [];

$sql = "SELECT s.*, c.class_name 
        FROM `students` s 
        INNER JOIN `classes` c ON s.class_id = c.class_id WHERE 1=1 ";

if (!empty($search)) {
    $sql .= " AND (s.fullname LIKE ? OR s.phone LIKE ? OR s.student_id = ?) ";
    $query_params[] = "%$search%";
    $query_params[] = "%$search%";
    $query_params[] = intval($search);
}

if ($class_filter > 0) {
    $sql .= " AND s.class_id = ? ";
    $query_params[] = $class_filter;
}

$sql .= " ORDER BY s.fullname ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($query_params);
    $students = $stmt->fetchAll();
    
    // Fetch all classes for selects and filters
    $class_stmt = $pdo->query("SELECT * FROM `classes` ORDER BY `class_name` ASC");
    $classes = $class_stmt->fetchAll();
} catch (PDOException $e) {
    die("Query Error: " . $e->getMessage());
}
?>

<div class="container-fluid px-0">
    <div class="row">
        <!-- Title and Quick Action -->
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm p-4" style="border-radius: 16px;">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                            <i class="fa-solid fa-users fs-3"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-0 text-primary">Manage Students</h3>
                            <p class="text-muted mb-0">Add, edit, view, and print student QR profiles</p>
                        </div>
                    </div>
                    
                    <button class="btn btn-primary px-4 d-flex align-items-center gap-2" style="border-radius: 10px;" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                        <i class="fa-solid fa-user-plus"></i>Add New Student
                    </button>
                </div>
            </div>
        </div>

        <!-- Filter and Search controls -->
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm p-3" style="border-radius: 12px;">
                <form action="students.php" method="GET" class="row g-2 align-items-center justify-content-between">
                    <div class="col-md-3 col-sm-6">
                        <select class="form-select" name="class_id" onchange="this.form.submit()">
                            <option value="0">All Classes</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['class_id']; ?>" <?php echo ($class_filter === intval($class['class_id'])) ? 'selected' : ''; ?>>
                                    <?php echo e($class['class_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 col-sm-6 ms-auto">
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" value="<?php echo e($search); ?>" placeholder="Search student name, phone or ID...">
                            <button class="btn btn-primary" type="submit">
                                <i class="fa-solid fa-magnifying-glass"></i>
                            </button>
                            <?php if (!empty($search) || $class_filter > 0): ?>
                                <a href="students.php" class="btn btn-outline-secondary">Clear</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Student Datatable Card -->
        <div class="col-12">
            <div class="card border-0 shadow-sm" style="border-radius: 16px; overflow: hidden;">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.95rem;">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4 py-3">Student ID</th>
                                    <th class="py-3">Full Name</th>
                                    <th class="py-3">Class</th>
                                    <th class="py-3">Gender</th>
                                    <th class="py-3">Contact Phone</th>
                                    <th class="py-3 text-center">QR Code</th>
                                    <th class="pe-4 py-3 text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($students)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            <i class="fa-solid fa-user-slash fs-2 mb-3 d-block text-secondary"></i>
                                            No students found matching current criteria.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td class="ps-4 fw-semibold text-secondary">#<?php echo str_pad($student['student_id'], 5, '0', STR_PAD_LEFT); ?></td>
                                            <td class="fw-bold text-primary"><?php echo e($student['fullname']); ?></td>
                                            <td>
                                                <span class="badge bg-primary-subtle text-primary py-1 px-2">
                                                    <?php echo e($student['class_name']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo e($student['gender']); ?></td>
                                            <td><?php echo !empty($student['phone']) ? e($student['phone']) : '<span class="text-muted">N/A</span>'; ?></td>
                                            <td class="text-center">
                                                <?php if (!empty($student['qr_code']) && file_exists(__DIR__ . '/../' . $student['qr_code'])): ?>
                                                    <!-- Embedded SVG preview inside a button to view/download -->
                                                    <a href="javascript:void(0)" class="qr-preview-trigger" 
                                                       data-fullname="<?php echo e($student['fullname']); ?>"
                                                       data-qr-src="<?php echo e($student['qr_code']); ?>"
                                                       data-id="<?php echo $student['student_id']; ?>">
                                                        <div style="width: 42px; height: 42px; margin: 0 auto;" class="border rounded p-1 bg-white">
                                                            <!-- Load the SVG inline/object -->
                                                            <img src="<?php echo e($student['qr_code']); ?>" alt="QR" style="width: 100%; height: 100%; object-fit: contain;">
                                                        </div>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-danger"><i class="fa-solid fa-circle-xmark me-1"></i>Missing QR</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="pe-4 text-end">
                                                <div class="d-flex justify-content-end gap-2">
                                                    <!-- Edit Button -->
                                                    <button class="btn btn-sm btn-outline-primary" style="border-radius: 6px;" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#editStudentModal"
                                                            data-id="<?php echo $student['student_id']; ?>"
                                                            data-fullname="<?php echo e($student['fullname']); ?>"
                                                            data-gender="<?php echo e($student['gender']); ?>"
                                                            data-phone="<?php echo e($student['phone']); ?>"
                                                            data-class="<?php echo $student['class_id']; ?>">
                                                        <i class="fa-solid fa-user-gear"></i>
                                                    </button>
                                                    
                                                    <!-- Delete Button -->
                                                    <button class="btn btn-sm btn-outline-danger" style="border-radius: 6px;"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#deleteStudentModal"
                                                            data-id="<?php echo $student['student_id']; ?>"
                                                            data-fullname="<?php echo e($student['fullname']); ?>">
                                                        <i class="fa-solid fa-user-minus"></i>
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

<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-bottom p-4">
                <h5 class="modal-title fw-bold text-primary" id="addStudentModalLabel">
                    <i class="fa-solid fa-user-plus me-2"></i>Add Student Record
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="students.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="mb-3">
                        <label for="add_fullname" class="form-label fw-semibold text-secondary">Full Name</label>
                        <input type="text" class="form-control" id="add_fullname" name="fullname" placeholder="e.g. Michael Jordan" required>
                    </div>
                    <div class="mb-3">
                        <label for="add_class_id" class="form-label fw-semibold text-secondary">Assign Class Level</label>
                        <select class="form-select" id="add_class_id" name="class_id" required>
                            <option value="" disabled selected>Choose class...</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['class_id']; ?>"><?php echo e($class['class_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary d-block">Gender</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="gender" id="add_gender_m" value="Male" checked>
                            <label class="form-check-label" for="add_gender_m">Male</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="gender" id="add_gender_f" value="Female">
                            <label class="form-check-label" for="add_gender_f">Female</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="gender" id="add_gender_o" value="Other">
                            <label class="form-check-label" for="add_gender_o">Other</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="add_phone" class="form-label fw-semibold text-secondary">Parent Contact Phone</label>
                        <input type="text" class="form-control" id="add_phone" name="phone" placeholder="e.g. +1 555-1234">
                    </div>
                </div>
                <div class="modal-footer border-top p-3 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary px-4"><i class="fa-solid fa-floppy-disk me-2"></i>Register Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Student Modal -->
<div class="modal fade" id="editStudentModal" tabindex="-1" aria-labelledby="editStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-bottom p-4">
                <h5 class="modal-title fw-bold text-primary" id="editStudentModalLabel">
                    <i class="fa-solid fa-user-gear me-2"></i>Edit Student Profile
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="students.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" id="edit_student_id" name="student_id">
                    
                    <div class="mb-3">
                        <label for="edit_fullname" class="form-label fw-semibold text-secondary">Full Name</label>
                        <input type="text" class="form-control" id="edit_fullname" name="fullname" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_class_id" class="form-label fw-semibold text-secondary">Class Level</label>
                        <select class="form-select" id="edit_class_id" name="class_id" required>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['class_id']; ?>"><?php echo e($class['class_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary d-block">Gender</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="gender" id="edit_gender_m" value="Male">
                            <label class="form-check-label" for="edit_gender_m">Male</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="gender" id="edit_gender_f" value="Female">
                            <label class="form-check-label" for="edit_gender_f">Female</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="gender" id="edit_gender_o" value="Other">
                            <label class="form-check-label" for="edit_gender_o">Other</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_phone" class="form-label fw-semibold text-secondary">Parent Contact Phone</label>
                        <input type="text" class="form-control" id="edit_phone" name="phone">
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

<!-- Delete Student Modal -->
<div class="modal fade" id="deleteStudentModal" tabindex="-1" aria-labelledby="deleteStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-bottom p-4">
                <h5 class="modal-title fw-bold text-danger" id="deleteStudentModalLabel">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>Delete Student Record
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="students.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" id="delete_student_id" name="student_id">
                    
                    <p class="mb-0 text-secondary">
                        Are you sure you want to delete <strong id="delete_student_name_span" class="text-danger"></strong>?
                    </p>
                    <p class="text-muted mt-2 mb-0" style="font-size: 0.85rem;">
                        <i class="fa-solid fa-circle-exclamation me-1 text-warning"></i>
                        This deletes the student's entire attendance registry and removes their local QR code file. This cannot be undone.
                    </p>
                </div>
                <div class="modal-footer border-top p-3 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger px-4"><i class="fa-solid fa-trash me-2"></i>Delete Permanently</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- QR Details Preview Modal -->
<div class="modal fade" id="qrPreviewModal" tabindex="-1" aria-labelledby="qrPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header border-bottom p-3">
                <h6 class="modal-title fw-bold text-primary" id="qrPreviewModalLabel">
                    <i class="fa-solid fa-qrcode me-2"></i>Student QR Code
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-4">
                <h5 class="fw-bold mb-3 text-secondary" id="qr_student_name">Student Name</h5>
                <div class="border rounded p-3 bg-light mx-auto mb-4" style="width: 200px; height: 200px;">
                    <img id="qr_large_image" src="" alt="QR" style="width: 100%; height: 100%; object-fit: contain;">
                </div>
                
                <div class="d-grid gap-2">
                    <!-- Download Link -->
                    <a id="qr_download_link" href="" download="" class="btn btn-success d-flex align-items-center justify-content-center gap-2">
                        <i class="fa-solid fa-download"></i>Download QR Code (SVG)
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Populate Edit Modal fields
    const editModal = document.getElementById('editStudentModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            const btn = event.relatedTarget;
            document.getElementById('edit_student_id').value = btn.getAttribute('data-id');
            document.getElementById('edit_fullname').value = btn.getAttribute('data-fullname');
            document.getElementById('edit_phone').value = btn.getAttribute('data-phone');
            document.getElementById('edit_class_id').value = btn.getAttribute('data-class');
            
            const gender = btn.getAttribute('data-gender');
            if (gender === 'Male') document.getElementById('edit_gender_m').checked = true;
            else if (gender === 'Female') document.getElementById('edit_gender_f').checked = true;
            else document.getElementById('edit_gender_o').checked = true;
        });
    }

    // Populate Delete Modal fields
    const deleteModal = document.getElementById('deleteStudentModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function(event) {
            const btn = event.relatedTarget;
            document.getElementById('delete_student_id').value = btn.getAttribute('data-id');
            document.getElementById('delete_student_name_span').textContent = btn.getAttribute('data-fullname');
        });
    }

    // Handle QR Preview click
    const qrTriggers = document.querySelectorAll('.qr-preview-trigger');
    qrTriggers.forEach(function(trigger) {
        trigger.addEventListener('click', function() {
            const fullname = trigger.getAttribute('data-fullname');
            const qrSrc = trigger.getAttribute('data-qr-src');
            const stuId = trigger.getAttribute('data-id');
            
            document.getElementById('qr_student_name').textContent = fullname;
            document.getElementById('qr_large_image').src = qrSrc;
            
            // Configure download link
            const downloadLink = document.getElementById('qr_download_link');
            downloadLink.href = qrSrc;
            downloadLink.download = "QR_STU_" + stuId + "_" + fullname.replace(/\s+/g, '_') + ".svg";
            
            // Show Modal
            const qrModal = new bootstrap.Modal(document.getElementById('qrPreviewModal'));
            qrModal.show();
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
