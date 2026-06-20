<?php
/**
 * Smart Attendance System (SAS)
 * Advanced Dashboard (REAL-TIME + ANALYTICS + OPTIMIZED)
 */

$page_title = "Dashboard Analytics";
require_once __DIR__ . '/includes/header.php';

$today = date('Y-m-d');

try {

    // =========================
    // BASIC COUNTS
    // =========================
    $total_students = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $total_classes  = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();

    // =========================
    // TODAY ATTENDANCE
    // =========================
    function getCount($pdo, $sql, $params = []) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    $present_today = getCount($pdo,
        "SELECT COUNT(*) FROM attendance WHERE attendance_date=? AND status='Present'",
        [$today]
    );

    $absent_today = getCount($pdo,
        "SELECT COUNT(*) FROM attendance WHERE attendance_date=? AND status='Absent'",
        [$today]
    );

    $late_today = getCount($pdo,
        "SELECT COUNT(*) FROM attendance WHERE attendance_date=? AND status='Late'",
        [$today]
    );

    $total_attendance_today = $present_today + $absent_today + $late_today;

    $attendance_rate = ($total_attendance_today > 0)
        ? round((($present_today + $late_today) / $total_attendance_today) * 100)
        : 0;

    // =========================
    // RECENT ATTENDANCE
    // =========================
    $recent_stmt = $pdo->query("
        SELECT 
            a.attendance_date,
            a.time_in,
            a.status,
            s.fullname,
            c.class_name
        FROM attendance a
        INNER JOIN students s ON a.student_id = s.student_id
        INNER JOIN classes c ON a.class_id = c.class_id
        ORDER BY a.attendance_date DESC, a.time_in DESC
        LIMIT 7
    ");

    $recent_attendance = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

    // =========================
    // MONTHLY ANALYTICS
    // =========================
    $trend_stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(attendance_date, '%b %Y') AS month_label,
            ROUND(
                SUM(CASE WHEN status IN ('Present','Late') THEN 1 ELSE 0 END) * 100.0 / COUNT(*),
            2) AS rate
        FROM attendance
        GROUP BY DATE_FORMAT(attendance_date, '%Y-%m')
        ORDER BY MIN(attendance_date)
        LIMIT 6
    ");

    $trends = $trend_stmt->fetchAll(PDO::FETCH_ASSOC);

    $trend_months = [];
    $trend_rates  = [];

    if (empty($trends)) {
        $trend_months = ['Jan','Feb','Mar','Apr','May','Jun'];
        $trend_rates  = [0,0,0,0,0,0];
    } else {
        foreach ($trends as $t) {
            $trend_months[] = $t['month_label'];
            $trend_rates[]  = (float)$t['rate'];
        }
    }

    // =========================
    // WEEKLY ANALYTICS
    // =========================
    $weekly_stmt = $pdo->query("
        SELECT 
            WEEK(attendance_date) AS week_no,
            ROUND(
                SUM(CASE WHEN status IN ('Present','Late') THEN 1 ELSE 0 END) * 100.0 / COUNT(*),
            2) AS rate
        FROM attendance
        GROUP BY WEEK(attendance_date)
        ORDER BY week_no DESC
        LIMIT 8
    ");

    $weekly_data = $weekly_stmt->fetchAll(PDO::FETCH_ASSOC);

    $weekly_labels = [];
    $weekly_rates  = [];

    foreach ($weekly_data as $w) {
        $weekly_labels[] = "Week " . $w['week_no'];
        $weekly_rates[]  = (float)$w['rate'];
    }

    // =========================
    // YEARLY ANALYTICS
    // =========================
    $yearly_stmt = $pdo->query("
        SELECT 
            YEAR(attendance_date) AS year,
            ROUND(
                SUM(CASE WHEN status IN ('Present','Late') THEN 1 ELSE 0 END) * 100.0 / COUNT(*),
            2) AS rate
        FROM attendance
        GROUP BY YEAR(attendance_date)
        ORDER BY year DESC
    ");

    $yearly_data = $yearly_stmt->fetchAll(PDO::FETCH_ASSOC);

    $yearly_labels = [];
    $yearly_rates  = [];

    foreach ($yearly_data as $y) {
        $yearly_labels[] = $y['year'];
        $yearly_rates[]  = (float)$y['rate'];
    }

} catch (PDOException $e) {
    die("Dashboard Error: " . $e->getMessage());
}
?>

<!-- ================= DASHBOARD UI START ================= -->

<div class="container-fluid px-3">

    <!-- STAT CARDS -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3 mb-4">

        <div class="col">
            <div class="stat-card">
                <h6>Total Students</h6>
                <h3><?php echo $total_students; ?></h3>
            </div>
        </div>

        <div class="col">
            <div class="stat-card">
                <h6>Total Classes</h6>
                <h3><?php echo $total_classes; ?></h3>
            </div>
        </div>

        <div class="col">
            <div class="stat-card">
                <h6>Present Today</h6>
                <h3 id="presentBox"><?php echo $present_today; ?></h3>
            </div>
        </div>

        <div class="col">
            <div class="stat-card">
                <h6>Absent Today</h6>
                <h3 id="absentBox"><?php echo $absent_today; ?></h3>
            </div>
        </div>

        <div class="col">
            <div class="stat-card">
                <h6>Late Today</h6>
                <h3 id="lateBox"><?php echo $late_today; ?></h3>
            </div>
        </div>

        <div class="col">
            <div class="stat-card">
                <h6>Attendance Rate</h6>
                <h3><?php echo $attendance_rate; ?>%</h3>
            </div>
        </div>

    </div>

    <!-- CHART DATA -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
    const trendMonths = <?php echo json_encode($trend_months); ?>;
    const trendRates  = <?php echo json_encode($trend_rates); ?>;

    const weeklyLabels = <?php echo json_encode($weekly_labels); ?>;
    const weeklyRates  = <?php echo json_encode($weekly_rates); ?>;

    const yearlyLabels = <?php echo json_encode($yearly_labels); ?>;
    const yearlyRates  = <?php echo json_encode($yearly_rates); ?>;
    </script>

    <!-- REAL-TIME AJAX -->
    <script>
    function loadStats() {
        fetch("api/dashboard_stats.php")
            .then(res => res.json())
            .then(data => {
                document.getElementById("presentBox").innerText = data.present;
                document.getElementById("absentBox").innerText = data.absent;
                document.getElementById("lateBox").innerText = data.late;
            });
    }

    setInterval(loadStats, 10000);
    </script>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>