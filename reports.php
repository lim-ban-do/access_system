<?php
/**
 * Smart Attendance System (SAS)
 * Reporting Engine (Daily, Weekly, Monthly, Student, Class Reports)
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';

// Safe session start
start_session_safe();

// Fetch filter options
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Default: start of current month
$end_date   = $_GET['end_date'] ?? date('Y-m-d');     // Default: today
$class_id   = intval($_GET['class_id'] ?? 0);
$student_id = intval($_GET['student_id'] ?? 0);

// Build dynamic reporting query
$query_params = [];
$sql = "SELECT a.`attendance_date`, a.`time_in`, a.`status`, s.`fullname`, s.`student_id`, c.`class_name` 
        FROM `attendance` a
        INNER JOIN `students` s ON a.student_id = s.student_id
        INNER JOIN `classes` c ON a.class_id = c.class_id
        WHERE a.attendance_date BETWEEN ? AND ? ";

$query_params[] = $start_date;
$query_params[] = $end_date;

if ($class_id > 0) {
    $sql .= " AND a.class_id = ? ";
    $query_params[] = $class_id;
}

if ($student_id > 0) {
    $sql .= " AND a.student_id = ? ";
    $query_params[] = $student_id;
}

$sql .= " ORDER BY a.attendance_date DESC, c.class_name ASC, s.fullname ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($query_params);
    $report_data = $stmt->fetchAll();
    
    // --- EXCEL (CSV) EXPORT HANDLER ---
    if (isset($_GET['export']) && $_GET['export'] === 'excel') {
        // Configure headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="Attendance_Report_' . date('Ymd_His') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Write CSV headers
        fputcsv($output, ['Date', 'Student ID', 'Student Name', 'Class Level', 'Time In', 'Attendance Status']);
        
        // Write CSV data rows
        foreach ($report_data as $row) {
            fputcsv($output, [
                $row['attendance_date'],
                '#STU-' . str_pad($row['student_id'], 5, '0', STR_PAD_LEFT),
                $row['fullname'],
                $row['class_name'],
                $row['time_in'] ? date("h:i A", strtotime($row['time_in'])) : 'N/A',
                $row['status']
            ]);
        }
        
        fclose($output);
        exit();
    }
    
    // Fetch filter helpers (lists of classes and students)
    $classes_list = $pdo->query("SELECT * FROM `classes` ORDER BY `class_name` ASC")->fetchAll();
    $students_list = $pdo->query("SELECT `student_id`, `fullname` FROM `students` ORDER BY `fullname` ASC")->fetchAll();
    
    // Calculate report statistics summary
    $stat_total   = count($report_data);
    $stat_present = 0;
    $stat_late    = 0;
    $stat_absent  = 0;
    
    foreach ($report_data as $row) {
        if ($row['status'] === 'Present') $stat_present++;
        elseif ($row['status'] === 'Late') $stat_late++;
        elseif ($row['status'] === 'Absent') $stat_absent++;
    }
    
    $stat_rate = 0;
    if ($stat_total > 0) {
        $stat_rate = round((($stat_present + $stat_late) / $stat_total) * 100);
    }
    
} catch (PDOException $e) {
    die("Report Generation Failure: " . $e->getMessage());
}

// Include layout headers
$page_title = "Reports & Exports";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-0">
    <!-- Title Card -->
    <div class="col-12 mb-4">
        <div class="card border-0 shadow-sm p-4" style="border-radius: 16px;">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                    <i class="fa-solid fa-file-invoice fs-3"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-0 text-primary">Reports Engine</h3>
                    <p class="text-muted mb-0">Generate attendance reports and export to PDF, Excel (CSV) or Print</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Configuration Filter Bar -->
    <div class="col-12 mb-4 no-print">
        <div class="card border-0 shadow-sm p-4" style="border-radius: 16px;">
            <h5 class="fw-bold mb-3 text-primary"><i class="fa-solid fa-sliders me-2"></i>Filter Options</h5>
            
            <form action="reports.php" method="GET" class="row g-3">
                <!-- Class Filter -->
                <div class="col-md-3">
                    <label for="filter_class" class="form-label fw-semibold text-secondary">Class Level</label>
                    <select class="form-select" id="filter_class" name="class_id">
                        <option value="0" <?php echo ($class_id === 0) ? 'selected' : ''; ?>>All Classes</option>
                        <?php foreach ($classes_list as $cls): ?>
                            <option value="<?php echo $cls['class_id']; ?>" <?php echo ($class_id === intval($cls['class_id'])) ? 'selected' : ''; ?>>
                                <?php echo e($cls['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Student Filter -->
                <div class="col-md-3">
                    <label for="filter_student" class="form-label fw-semibold text-secondary">Student</label>
                    <select class="form-select" id="filter_student" name="student_id">
                        <option value="0" <?php echo ($student_id === 0) ? 'selected' : ''; ?>>All Students</option>
                        <?php foreach ($students_list as $stu): ?>
                            <option value="<?php echo $stu['student_id']; ?>" <?php echo ($student_id === intval($stu['student_id'])) ? 'selected' : ''; ?>>
                                <?php echo e($stu['fullname']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Start Date -->
                <div class="col-md-3 col-sm-6">
                    <label for="filter_start" class="form-label fw-semibold text-secondary">Start Date</label>
                    <input type="date" class="form-control" id="filter_start" name="start_date" value="<?php echo e($start_date); ?>">
                </div>

                <!-- End Date -->
                <div class="col-md-3 col-sm-6">
                    <label for="filter_end" class="form-label fw-semibold text-secondary">End Date</label>
                    <input type="date" class="form-control" id="filter_end" name="end_date" value="<?php echo e($end_date); ?>">
                </div>

                <!-- Submit / Clear Buttons -->
                <div class="col-12 d-flex justify-content-end gap-2 mt-4">
                    <a href="reports.php" class="btn btn-outline-secondary px-4" style="border-radius: 8px;">
                        <i class="fa-solid fa-arrows-rotate me-2"></i>Reset
                    </a>
                    <button type="submit" class="btn btn-primary px-4" style="border-radius: 8px;">
                        <i class="fa-solid fa-filter me-2"></i>Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Attendance Summary / Statistics cards -->
    <div class="row g-3 mb-4">
        <!-- Total Logs -->
        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div>
                    <h6 class="text-muted fw-semibold mb-1">Total Logs</h6>
                    <h3 class="fw-bold mb-0"><?php echo $stat_total; ?></h3>
                </div>
                <div class="card-icon icon-blue"><i class="fa-solid fa-list"></i></div>
            </div>
        </div>
        <!-- Present -->
        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div>
                    <h6 class="text-muted fw-semibold mb-1">Present</h6>
                    <h3 class="fw-bold mb-0 text-success"><?php echo $stat_present; ?></h3>
                </div>
                <div class="card-icon icon-green"><i class="fa-solid fa-circle-check"></i></div>
            </div>
        </div>
        <!-- Late -->
        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div>
                    <h6 class="text-muted fw-semibold mb-1">Late</h6>
                    <h3 class="fw-bold mb-0 text-warning"><?php echo $stat_late; ?></h3>
                </div>
                <div class="card-icon icon-orange"><i class="fa-regular fa-clock"></i></div>
            </div>
        </div>
        <!-- Attendance Rate -->
        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div>
                    <h6 class="text-muted fw-semibold mb-1">Attendance Rate</h6>
                    <h3 class="fw-bold mb-0 text-primary"><?php echo $stat_rate; ?>%</h3>
                </div>
                <div class="card-icon icon-blue"><i class="fa-solid fa-percent"></i></div>
            </div>
        </div>
    </div>

    <!-- Report Area (PDF / Print scope wrapper) -->
    <div class="col-12">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <!-- Actions Header -->
            <div class="card-header bg-white border-bottom p-4 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 no-print">
                <h5 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-square-poll-vertical me-2"></i>Report Datatable</h5>
                
                <div class="d-flex gap-2">
                    <!-- Print Button -->
                    <button onclick="window.print()" class="btn btn-outline-secondary px-3 d-flex align-items-center gap-2" style="border-radius: 8px;">
                        <i class="fa-solid fa-print"></i>Print
                    </button>
                    <!-- PDF Button -->
                    <button id="btn-export-pdf" class="btn btn-outline-danger px-3 d-flex align-items-center gap-2" style="border-radius: 8px;">
                        <i class="fa-solid fa-file-pdf"></i>Export PDF
                    </button>
                    <!-- Excel (CSV) Button -->
                    <a href="reports.php?<?php echo http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>" class="btn btn-outline-success px-3 d-flex align-items-center gap-2" style="border-radius: 8px;">
                        <i class="fa-solid fa-file-excel"></i>Export Excel
                    </a>
                </div>
            </div>

            <!-- Printable Roster Table -->
            <div class="card-body p-4" id="report-print-area">
                <!-- Header visible only during printing/PDF export -->
                <div class="d-none d-print-block mb-4 text-center border-bottom pb-3">
                    <h2 class="fw-bold text-primary mb-1">Attendance Registry Report</h2>
                    <h5 class="text-secondary mb-2">Smart Attendance System (SAS)</h5>
                    <p class="text-muted small mb-0">
                        Date Range: <?php echo date("M j, Y", strtotime($start_date)); ?> - <?php echo date("M j, Y", strtotime($end_date)); ?> 
                        <?php if ($class_id > 0) echo " | Class: " . e($report_data[0]['class_name'] ?? ''); ?>
                    </p>
                </div>

                <div class="table-responsive border-0 p-0 shadow-none">
                    <table class="table table-striped table-hover align-middle mb-0" style="font-size: 0.95rem;">
                        <thead class="table-light">
                            <tr>
                                <th class="py-3">Date</th>
                                <th class="py-3">Student Name</th>
                                <th class="py-3">Class</th>
                                <th class="py-3">Time In</th>
                                <th class="py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($report_data)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-magnifying-glass fs-2 mb-3 d-block text-secondary opacity-50"></i>
                                        No attendance entries found matching the specified filters.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($report_data as $row): ?>
                                    <tr>
                                        <td><?php echo date("M j, Y", strtotime($row['attendance_date'])); ?></td>
                                        <td class="fw-bold"><?php echo e($row['fullname']); ?></td>
                                        <td>
                                            <span class="badge bg-primary-subtle text-primary"><?php echo e($row['class_name']); ?></span>
                                        </td>
                                        <td><?php echo $row['time_in'] ? date("h:i A", strtotime($row['time_in'])) : '<span class="text-muted">N/A</span>'; ?></td>
                                        <td>
                                            <?php if ($row['status'] === 'Present'): ?>
                                                <span class="status-badge status-present">Present</span>
                                            <?php elseif ($row['status'] === 'Absent'): ?>
                                                <span class="status-badge status-absent">Absent</span>
                                            <?php else: ?>
                                                <span class="status-badge status-late">Late</span>
                                            <?php endif; ?>
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

<!-- html2pdf Library CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const btnPdf = document.getElementById("btn-export-pdf");
    if (btnPdf) {
        btnPdf.addEventListener("click", function() {
            // Get printable report div element
            const element = document.getElementById("report-print-area");
            
            // Temporary CSS override to ensure full scale during PDF convert
            element.classList.add("p-5");
            
            const options = {
                margin:       0.3,
                filename:     `SAS_Attendance_Report_${new Date().toISOString().slice(0,10)}.pdf`,
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
            };
            
            // Execute HTML to PDF export
            html2pdf().set(options).from(element).save().then(() => {
                element.classList.remove("p-5");
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
