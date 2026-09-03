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

<div class="container-fluid px-3 py-3">

    <!-- STAT CARDS -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3 mb-4">

        <div class="col">
            <div class="stat-card">
                <div>
                    <h6>Total Students</h6>
                    <h3><?php echo $total_students; ?></h3>
                </div>
                <div class="card-icon icon-blue">
                    <i class="fa-solid fa-user-graduate"></i>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="stat-card">
                <div>
                    <h6>Total Classes</h6>
                    <h3><?php echo $total_classes; ?></h3>
                </div>
                <div class="card-icon icon-blue">
                    <i class="fa-solid fa-chalkboard-user"></i>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="stat-card">
                <div>
                    <h6>Present Today</h6>
                    <h3 id="presentBox"><?php echo $present_today; ?></h3>
                </div>
                <div class="card-icon icon-green">
                    <i class="fa-solid fa-user-check"></i>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="stat-card">
                <div>
                    <h6>Absent Today</h6>
                    <h3 id="absentBox"><?php echo $absent_today; ?></h3>
                </div>
                <div class="card-icon icon-red">
                    <i class="fa-solid fa-user-xmark"></i>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="stat-card">
                <div>
                    <h6>Late Today</h6>
                    <h3 id="lateBox"><?php echo $late_today; ?></h3>
                </div>
                <div class="card-icon icon-orange">
                    <i class="fa-solid fa-clock"></i>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="stat-card">
                <div>
                    <h6>Attendance Rate</h6>
                    <h3><?php echo $attendance_rate; ?>%</h3>
                </div>
                <div class="card-icon icon-blue">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
            </div>
        </div>

    </div>

    <!-- ANALYTICS / CHARTS SECTION -->
    <div class="row g-3 mb-4">
        <!-- Today's Attendance Breakdown (Pie/Doughnut) -->
        <div class="col-lg-4">
            <div class="chart-card">
                <h5>Today's Breakdown</h5>
                <div class="chart-container flex-center">
                    <canvas id="todayPieChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Monthly Attendance Trend (Line Chart) -->
        <div class="col-lg-8">
            <div class="chart-card">
                <h5>Monthly Attendance Trend (%)</h5>
                <div class="chart-container">
                    <canvas id="monthlyTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <!-- Weekly Performance (Bar Chart) -->
        <div class="col-lg-6">
            <div class="chart-card">
                <h5>Weekly Attendance Rates (%)</h5>
                <div class="chart-container">
                    <canvas id="weeklyBarChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Yearly Comparison (Bar Chart) -->
        <div class="col-lg-6">
            <div class="chart-card">
                <h5>Yearly Attendance Rates (%)</h5>
                <div class="chart-container">
                    <canvas id="yearlyBarChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- RECENT ATTENDANCE TABLE -->
    <div class="row g-3">
        <div class="col-12">
            <div class="table-responsive">
                <h5 class="mb-3 font-weight-bold" style="font-weight:600;">Recent Attendance Logs</h5>
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Date</th>
                            <th>Time In</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_attendance)): ?>
                            <tr><td colspan="5" class="text-center text-muted">No attendance logs found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recent_attendance as $row): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['fullname']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['class_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['attendance_date']); ?></td>
                                    <td><?php echo htmlspecialchars($row['time_in']); ?></td>
                                    <td>
                                        <?php 
                                            $status_class = strtolower($row['status']);
                                            echo "<span class='status-badge status-{$status_class}'>{$row['status']}</span>";
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- CHART DATA SCRIPT -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
    // PHP variables mapped to JS
    const presentToday = <?php echo (int)$present_today; ?>;
    const absentToday  = <?php echo (int)$absent_today; ?>;
    const lateToday    = <?php echo (int)$late_today; ?>;

    const trendMonths = <?php echo json_encode($trend_months); ?>;
    const trendRates  = <?php echo json_encode($trend_rates); ?>;

    const weeklyLabels = <?php echo json_encode(array_reverse($weekly_labels)); ?>;
    const weeklyRates  = <?php echo json_encode(array_reverse($weekly_rates)); ?>;

    const yearlyLabels = <?php echo json_encode(array_reverse($yearly_labels)); ?>;
    const yearlyRates  = <?php echo json_encode(array_reverse($yearly_rates)); ?>;

    // --- 1. TODAY'S PIE/DOUGHNUT CHART ---
    const ctxPie = document.getElementById('todayPieChart').getContext('2d');
    const todayPieChart = new Chart(ctxPie, {
        type: 'doughnut',
        data: {
            labels: ['Present', 'Absent', 'Late'],
            datasets: [{
                data: [presentToday, absentToday, lateToday],
                backgroundColor: ['#22c55e', '#ef4444', '#f97316'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    // --- 2. MONTHLY TREND LINE CHART ---
    const ctxLine = document.getElementById('monthlyTrendChart').getContext('2d');
    new Chart(ctxLine, {
        type: 'line',
        data: {
            labels: trendMonths,
            datasets: [{
                label: 'Attendance Rate (%)',
                data: trendRates,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                fill: true,
                tension: 0.3,
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, max: 100 }
            }
        }
    });

    // --- 3. WEEKLY BAR CHART ---
    const ctxWeekly = document.getElementById('weeklyBarChart').getContext('2d');
    new Chart(ctxWeekly, {
        type: 'bar',
        data: {
            labels: weeklyLabels,
            datasets: [{
                label: 'Rate (%)',
                data: weeklyRates,
                backgroundColor: '#0ea5e9',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, max: 100 }
            }
        }
    });

    // --- 4. YEARLY BAR CHART ---
    const ctxYearly = document.getElementById('yearlyBarChart').getContext('2d');
    new Chart(ctxYearly, {
        type: 'bar',
        data: {
            labels: yearlyLabels,
            datasets: [{
                label: 'Rate (%)',
                data: yearlyRates,
                backgroundColor: '#1e40af',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, max: 100 }
            }
        }
    });
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

                // Dynamically update the Doughnut Chart
                todayPieChart.data.datasets[0].data = [data.present, data.absent, data.late];
                todayPieChart.update();
            });
    }

    setInterval(loadStats, 10000);
    </script>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>