<?php
/**
 * Smart Attendance System (SAS)
 * Unified Attendance Registry (Manual Checklist & Webcam QR Scanner)
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';

// Safe session start
start_session_safe();

// Handle AJAX QR Scan POST requests (before headers are sent)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'qr_scan') {
    header('Content-Type: application/json');
    
    $qr_data    = trim($_POST['qr_data'] ?? '');
    $status     = trim($_POST['status'] ?? 'Present');
    $post_token = $_POST['csrf_token'] ?? '';
    
    // 1. Validate CSRF Token
    if (!verify_csrf_token($post_token)) {
        echo json_encode(['status' => 'error', 'message' => 'Security check failed. Please refresh.']);
        exit();
    }
    
    // 2. Validate QR Data Prefix (Expecting STU-ID)
    if (!preg_match('/^STU-(\d+)$/', $qr_data, $matches)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid QR Code structure.']);
        exit();
    }
    
    $student_id = intval($matches[1]);
    $today = date('Y-m-d');
    $time_now = date('H:i:s');
    
    try {
        // Fetch student details
        $stmt = $pdo->prepare("SELECT s.*, c.class_name FROM `students` s INNER JOIN `classes` c ON s.class_id = c.class_id WHERE s.student_id = ? LIMIT 1");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch();
        
        if (!$student) {
            echo json_encode(['status' => 'error', 'message' => 'Student record not found in system.']);
            exit();
        }
        
        $fullname = $student['fullname'];
        $class_id = $student['class_id'];
        $class_name = $student['class_name'];
        
        // Check for duplicate attendance on the same date
        $dup_stmt = $pdo->prepare("SELECT `status`, `time_in` FROM `attendance` WHERE `student_id` = ? AND `class_id` = ? AND `attendance_date` = ? LIMIT 1");
        $dup_stmt->execute([$student_id, $class_id, $today]);
        $existing = $dup_stmt->fetch();
        
        if ($existing) {
            $formatted_time = date('h:i A', strtotime($existing['time_in']));
            echo json_encode([
                'status'  => 'warning',
                'message' => "{$fullname} is already marked as '{$existing['status']}' today at {$formatted_time}."
            ]);
            exit();
        }
        
        // Log attendance record
        $ins_stmt = $pdo->prepare("INSERT INTO `attendance` (`student_id`, `class_id`, `attendance_date`, `time_in`, `status`) VALUES (?, ?, ?, ?, ?)");
        $ins_stmt->execute([$student_id, $class_id, $today, $time_now, $status]);
        
        echo json_encode([
            'status'        => 'success',
            'message'       => "{$fullname} has been marked as '{$status}' successfully.",
            'student_name'  => $fullname,
            'class_name'    => $class_name,
            'time_in'       => date('h:i A', strtotime($time_now)),
            'status_marked' => $status
        ]);
        exit();
        
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database failure: ' . $e->getMessage()]);
        exit();
    }
}

// Handle Manual Attendance Checklist Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'manual_submit') {
    $class_id        = intval($_POST['class_id'] ?? 0);
    $attendance_date = $_POST['attendance_date'] ?? date('Y-m-d');
    $status_array    = $_POST['status'] ?? []; // Array keyed by student_id
    $post_token      = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($post_token)) {
        set_alert('error', 'Security check failed', 'Invalid security token.');
        header("Location: attendance.php");
        exit();
    }
    
    if ($class_id > 0 && !empty($status_array)) {
        try {
            $pdo->beginTransaction();
            $time_now = date('H:i:s');
            
            // Loop through student checklist
            foreach ($status_array as $student_id => $status) {
                // Upsert logic (Insert or Update if already exists)
                $stmt = $pdo->prepare("
                    INSERT INTO `attendance` (`student_id`, `class_id`, `attendance_date`, `time_in`, `status`) 
                    VALUES (?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE `status` = VALUES(`status`), `time_in` = IF(VALUES(`status`) != 'Absent', IFNULL(`time_in`, ?), NULL)
                ");
                $stmt->execute([$student_id, $class_id, $attendance_date, ($status === 'Absent' ? null : $time_now), $status, $time_now]);
            }
            
            $pdo->commit();
            set_alert('success', 'Attendance Logged', 'Manual attendance records saved successfully.');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_alert('error', 'Database Error', $e->getMessage());
        }
    }
    
    // Redirect preserving filter options
    header("Location: attendance.php?class_id={$class_id}&date={$attendance_date}");
    exit();
}

// Fetch basic parameters for manual panel filtering
$class_filter = intval($_GET['class_id'] ?? 0);
$date_filter  = $_GET['date'] ?? date('Y-m-d');

// Include global templates
$page_title = "Record Attendance";
require_once __DIR__ . '/includes/header.php';

// Fetch classes list for the selector
try {
    $class_stmt = $pdo->query("SELECT * FROM `classes` ORDER BY `class_name` ASC");
    $classes = $class_stmt->fetchAll();
    
    $students_list = [];
    if ($class_filter > 0) {
        // Fetch all students in selected class, left-joining existing attendance records for the filtered date
        $student_stmt = $pdo->prepare("
            SELECT s.`student_id`, s.`fullname`, a.`status` as attendance_status, a.`time_in`
            FROM `students` s 
            LEFT JOIN `attendance` a ON s.student_id = a.student_id AND a.attendance_date = ?
            WHERE s.class_id = ?
            ORDER BY s.fullname ASC
        ");
        $student_stmt->execute([$date_filter, $class_filter]);
        $students_list = $student_stmt->fetchAll();
    }
} catch (PDOException $e) {
    die("Database Query Failure: " . $e->getMessage());
}
?>

<div class="container-fluid px-0">
    <!-- Title Card -->
    <div class="col-12 mb-4">
        <div class="card border-0 shadow-sm p-4" style="border-radius: 16px;">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                    <i class="fa-solid fa-clipboard-user fs-3"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-0 text-primary">Attendance Tracker</h3>
                    <p class="text-muted mb-0">Scan QR codes with camera or checklist students manually</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Interface Toggle Tabs -->
    <div class="col-12 mb-4">
        <ul class="nav nav-pills nav-fill bg-white p-2 border shadow-sm" style="border-radius: 14px;">
            <li class="nav-item">
                <button class="nav-link py-3 fw-bold active d-flex align-items-center justify-content-center gap-2" id="nav-manual-tab" data-bs-toggle="pill" data-bs-target="#tab-manual" type="button" style="border-radius: 10px;">
                    <i class="fa-solid fa-list-check fs-5"></i>Manual Registry
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link py-3 fw-bold d-flex align-items-center justify-content-center gap-2" id="nav-qr-tab" data-bs-toggle="pill" data-bs-target="#tab-qr" type="button" style="border-radius: 10px;">
                    <i class="fa-solid fa-camera-retro fs-5"></i>QR Scanner Mode
                </button>
            </li>
        </ul>
    </div>

    <!-- Tab Contents -->
    <div class="tab-content" id="attendanceTabContent">
        
        <!-- Tab 1: MANUAL CHECKLIST -->
        <div class="tab-pane fade show active" id="tab-manual" role="tabpanel" aria-labelledby="nav-manual-tab">
            <div class="row g-4">
                <!-- Class and Date Selectors -->
                <div class="col-12">
                    <div class="card border-0 shadow-sm p-4" style="border-radius: 16px;">
                        <h5 class="fw-bold mb-3 text-primary"><i class="fa-solid fa-filter me-2"></i>Filter Class Level</h5>
                        
                        <form action="attendance.php" method="GET" class="row g-3 align-items-end">
                            <div class="col-md-5">
                                <label for="class_id_select" class="form-label fw-semibold text-secondary">Select Class</label>
                                <select class="form-select" id="class_id_select" name="class_id" required>
                                    <option value="" disabled <?php echo ($class_filter === 0) ? 'selected' : ''; ?>>Choose class...</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['class_id']; ?>" <?php echo ($class_filter === intval($class['class_id'])) ? 'selected' : ''; ?>>
                                            <?php echo e($class['class_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-5">
                                <label for="attendance_date_select" class="form-label fw-semibold text-secondary">Attendance Date</label>
                                <input type="date" class="form-control" id="attendance_date_select" name="date" value="<?php echo e($date_filter); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100 py-2" style="border-radius: 8px;">
                                    <i class="fa-solid fa-arrows-rotate me-2"></i>Load Roster
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Student Checklist Table -->
                <div class="col-12">
                    <?php if ($class_filter === 0): ?>
                        <div class="card border-0 shadow-sm text-center py-5" style="border-radius: 16px;">
                            <div class="py-4">
                                <i class="fa-solid fa-arrow-pointer fs-1 text-primary opacity-50 mb-3"></i>
                                <h5 class="fw-bold text-secondary">Please select a class and date to load the roster.</h5>
                            </div>
                        </div>
                    <?php elseif (empty($students_list)): ?>
                        <div class="card border-0 shadow-sm text-center py-5" style="border-radius: 16px;">
                            <div class="py-4">
                                <i class="fa-solid fa-users-slash fs-1 text-secondary opacity-50 mb-3"></i>
                                <h5 class="fw-bold text-secondary">No students enrolled in this class.</h5>
                                <p class="text-muted mt-2 mb-0">Go to "Manage Students" to enroll members.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <form action="attendance.php" method="POST">
                            <input type="hidden" name="action" value="manual_submit">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="class_id" value="<?php echo $class_filter; ?>">
                            <input type="hidden" name="attendance_date" value="<?php echo e($date_filter); ?>">
                            
                            <div class="card border-0 shadow-sm" style="border-radius: 16px; overflow: hidden;">
                                <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center">
                                    <h5 class="fw-bold mb-0 text-primary">
                                        <i class="fa-solid fa-users-viewfinder me-2"></i>Class Roster: <?php 
                                            $class_key = array_search($class_filter, array_column($classes, 'class_id'));
                                            echo e($classes[$class_key]['class_name']);
                                        ?>
                                    </h5>
                                    <span class="text-muted fw-semibold">Date: <?php echo date("l, M j, Y", strtotime($date_filter)); ?></span>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive border-0 shadow-none p-0">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="ps-4 py-3">Student Name</th>
                                                    <th class="py-3">Log Time</th>
                                                    <th class="py-3 text-center" style="width: 400px;">Status Checklist</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($students_list as $student): ?>
                                                    <tr>
                                                        <td class="ps-4 fw-bold text-dark"><?php echo e($student['fullname']); ?></td>
                                                        <td>
                                                            <?php echo ($student['time_in']) ? date("h:i A", strtotime($student['time_in'])) : '<span class="text-muted">--:--</span>'; ?>
                                                        </td>
                                                        <td class="text-center py-3">
                                                            <div class="d-flex justify-content-center gap-3">
                                                                <!-- Present Radio -->
                                                                <input type="radio" class="btn-check" name="status[<?php echo $student['student_id']; ?>]" id="pres_<?php echo $student['student_id']; ?>" value="Present" <?php echo ($student['attendance_status'] === 'Present' || empty($student['attendance_status'])) ? 'checked' : ''; ?>>
                                                                <label class="btn btn-outline-success px-3 py-1 fw-semibold" for="pres_<?php echo $student['student_id']; ?>" style="border-radius: 20px; font-size: 0.85rem;">Present</label>

                                                                <!-- Late Radio -->
                                                                <input type="radio" class="btn-check" name="status[<?php echo $student['student_id']; ?>]" id="late_<?php echo $student['student_id']; ?>" value="Late" <?php echo ($student['attendance_status'] === 'Late') ? 'checked' : ''; ?>>
                                                                <label class="btn btn-outline-warning px-3 py-1 fw-semibold" for="late_<?php echo $student['student_id']; ?>" style="border-radius: 20px; font-size: 0.85rem;">Late</label>

                                                                <!-- Absent Radio -->
                                                                <input type="radio" class="btn-check" name="status[<?php echo $student['student_id']; ?>]" id="abs_<?php echo $student['student_id']; ?>" value="Absent" <?php echo ($student['attendance_status'] === 'Absent') ? 'checked' : ''; ?>>
                                                                <label class="btn btn-outline-danger px-3 py-1 fw-semibold" for="abs_<?php echo $student['student_id']; ?>" style="border-radius: 20px; font-size: 0.85rem;">Absent</label>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="card-footer bg-white border-top p-4 d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary px-5 py-2 fw-semibold" style="border-radius: 10px;">
                                        <i class="fa-solid fa-square-check me-2 fs-5"></i>Save Attendance Checklist
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tab 2: WEBCAM QR SCANNER -->
        <div class="tab-pane fade" id="tab-qr" role="tabpanel" aria-labelledby="nav-qr-tab">
            <div class="row g-4">
                <!-- Camera view column -->
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm p-4 text-center" style="border-radius: 16px; height: 100%;">
                        <h5 class="fw-bold mb-3 text-primary"><i class="fa-solid fa-camera-retro me-2"></i>Webcam QR Scanner</h5>
                        
                        <!-- Configuration Panel -->
                        <div class="row g-2 mb-3">
                            <div class="col-8 text-start">
                                <label for="qr_status_select" class="form-label small fw-semibold text-muted mb-1">Incoming Status</label>
                                <select class="form-select" id="qr_status_select">
                                    <option value="Present" selected>Present</option>
                                    <option value="Late">Late</option>
                                </select>
                            </div>
                            <div class="col-4 d-flex align-items-end justify-content-center">
                                <input type="hidden" id="csrf_token_val" value="<?php echo $csrf_token; ?>">
                            </div>
                        </div>

                        <!-- Scanner Viewfinder Box -->
                        <div class="bg-black rounded-3 overflow-hidden d-flex align-items-center justify-content-center mb-4" style="aspect-ratio: 4/3; min-height: 250px;">
                            <div id="reader" style="width: 100%; border: none !important;"></div>
                        </div>

                        <div class="d-grid gap-2">
                            <button id="btn-toggle-scan" class="btn btn-primary py-2 fw-bold" style="border-radius: 10px;">
                                <i class="fa-solid fa-play me-2"></i>Start Scanner
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Real-time scan logs table -->
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm" style="border-radius: 16px; height: 100%; overflow: hidden;">
                        <div class="card-header bg-white border-bottom p-4">
                            <h5 class="fw-bold mb-0 text-primary">
                                <i class="fa-solid fa-bolt me-2"></i>Real-time Scan History (Today)
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive border-0 shadow-none p-0" style="max-height: 420px; overflow-y: auto;">
                                <table class="table table-hover align-middle mb-0" id="scan-history-table">
                                    <thead class="table-light position-sticky top-0" style="z-index: 1;">
                                        <tr>
                                            <th class="ps-4 py-3">Student Name</th>
                                            <th class="py-3">Class</th>
                                            <th class="py-3">Scan Time</th>
                                            <th class="pe-4 py-3">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="scan-history-tbody">
                                        <tr>
                                            <td colspan="4" class="text-center py-5 text-muted" id="no-scans-placeholder">
                                                <i class="fa-solid fa-barcode fs-2 mb-2 d-block text-secondary opacity-50"></i>
                                                Waiting for camera scan logs...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- html5-qrcode library via CDN -->
<script src="https://unpkg.com/html5-qrcode"></script>

<script>

document.addEventListener("DOMContentLoaded", function() {
    let html5QrcodeScanner = null;
    const btnToggleScan = document.getElementById("btn-toggle-scan");
    let isScanning = false;

    // Define what happens when a QR code is read successfully
    const qrCodeSuccessCallback = (decodedText, decodedResult) => {
        // 1. Immediately turn off camera tracking before fetching to prevent multi-scan spamming
        if (html5QrcodeScanner && isScanning) {
            html5QrcodeScanner.stop().then(() => {
                isScanning = false;
                resetToggleButton();
                
                // 2. Process payload data safely now that the stream has stopped
                const status = document.getElementById('qr_status_select').value;
                const csrf_token = document.getElementById('csrf_token_val').value;
                
                fetch('attendance.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=qr_scan&qr_data=${encodeURIComponent(decodedText)}&status=${encodeURIComponent(status)}&csrf_token=${encodeURIComponent(csrf_token)}`
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Present Checked',
                            text: data.message,
                            timer: 2000
                        }).then(() => {
                            startScanning(); // Automatically wake camera back up
                        });

                        addScanLogToTable(data.student_name, data.class_name, data.time_in, data.status_marked);
                    } else if (data.status === 'warning') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Duplicate Code',
                            text: data.message,
                            timer: 2500
                        }).then(() => {
                            startScanning();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Verification Failed',
                            text: data.message
                        }).then(() => {
                            startScanning();
                        });
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire({
                        icon: 'error',
                        title: 'Server Error',
                        text: 'Failed to communicate with attendance server.'
                    }).then(() => {
                        startScanning();
                    });
                });
            }).catch(err => console.error("Error stopping scanner:", err));
        }
    };

    if (btnToggleScan) {
        btnToggleScan.addEventListener("click", function() {
            if (!isScanning) {
                // Instantiating clean instance handler
                if (!html5QrcodeScanner) {
                    html5QrcodeScanner = new Html5Qrcode("reader");
                }
                startScanning();
            } else {
                stopScanning();
            }
        });
    }

    function startScanning() {
        const config = { fps: 10, qrbox: { width: 250, height: 250 } };
        
        // Pass our populated qrCodeSuccessCallback block directly below
        html5QrcodeScanner.start(
            { facingMode: "environment" }, 
            config, 
            qrCodeSuccessCallback, 
            (errorMessage) => {
                // Cleanly and silently ignore ongoing scan match failures
            }
        ).then(() => {
            isScanning = true;
            btnToggleScan.innerHTML = '<i class="fa-solid fa-stop me-2"></i>Stop Scanner';
            btnToggleScan.classList.remove('btn-primary');
            btnToggleScan.classList.add('btn-danger');
        }).catch(err => {
            console.error(err);
            Swal.fire({
                icon: 'error',
                title: 'Camera Connection Failed',
                text: 'Unable to access web camera stream. Please allow site permissions.'
            });
        });
    }

    function stopScanning() {
        if (html5QrcodeScanner && isScanning) {
            html5QrcodeScanner.stop().then(() => {
                isScanning = false;
                resetToggleButton();
            }).catch(err => console.error("Failed to stop scanner smoothly:", err));
        }
    }

    function resetToggleButton() {
        btnToggleScan.innerHTML = '<i class="fa-solid fa-play me-2"></i>Start Scanner';
        btnToggleScan.classList.remove('btn-danger');
        btnToggleScan.classList.add('btn-primary');
    }

    function addScanLogToTable(name, className, time, status) {
        const tbody = document.getElementById("scan-history-tbody");
        const placeholder = document.getElementById("no-scans-placeholder");
        if (placeholder) {
            placeholder.remove();
        }

        const tr = document.createElement("tr");
        let badgeClass = 'status-present';
        if (status === 'Late') badgeClass = 'status-late';
        
        tr.innerHTML = `
            <td class="ps-4 fw-bold text-dark">${name}</td>
            <td><span class="badge bg-primary-subtle text-primary">${className}</span></td>
            <td>${time}</td>
            <td class="pe-4"><span class="status-badge ${badgeClass}">${status}</span></td>
        `;
        tbody.insertBefore(tr, tbody.firstChild);
    }
});

</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>















