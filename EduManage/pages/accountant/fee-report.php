<?php
$page_title = 'Fee Report';
require_once('../../config/config.php');
require_once('../../includes/db.php');
require_once('../../includes/functions.php');
require_once('../../includes/header.php');

if (!hasRole('Accountant')) {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

global $conn;

$report_type = isset($_GET['type']) ? sanitize($_GET['type']) : 'overview';

// Overall statistics
$fee_stats = $conn->query("
    SELECT 
        COALESCE(SUM(total_fee), 0) as total_fees,
        COALESCE(SUM(amount_paid), 0) as amount_collected,
        COALESCE(SUM(due_amount), 0) as amount_due,
        COUNT(DISTINCT student_id) as total_students,
        SUM(CASE WHEN due_amount = 0 THEN 1 ELSE 0 END) as paid_students,
        SUM(CASE WHEN due_amount > 0 THEN 1 ELSE 0 END) as pending_students
    FROM fees
")->fetch_assoc();

// Fee collection by department
$fee_by_dept_query = "
    SELECT d.dept_name, COUNT(f.fee_id) as students, COALESCE(SUM(f.total_fee), 0) as total, 
           COALESCE(SUM(f.amount_paid), 0) as collected, COALESCE(SUM(f.due_amount), 0) as pending
    FROM fees f
    INNER JOIN students s ON f.student_id = s.student_id
    INNER JOIN users u ON s.user_id = u.user_id
    INNER JOIN departments d ON u.dept_id = d.dept_id
    GROUP BY d.dept_id, d.dept_name
    ORDER BY collected DESC
";

$fee_by_dept_result = $conn->query($fee_by_dept_query);
if (!$fee_by_dept_result) {
    $fee_by_dept = array();
    $error_msg = "Database Error: " . $conn->error;
} else {
    $fee_by_dept = $fee_by_dept_result->fetch_all(MYSQLI_ASSOC);
}

// Payment trend (last 12 months)
$payment_trend_query = "
    SELECT DATE_FORMAT(last_payment_date, '%Y-%m') as month, 
           COALESCE(SUM(amount_paid), 0) as total, 
           COUNT(*) as transactions
    FROM fees
    WHERE last_payment_date IS NOT NULL
    GROUP BY DATE_FORMAT(last_payment_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
";

$payment_trend_result = $conn->query($payment_trend_query);
if (!$payment_trend_result) {
    $payment_trend = array();
} else {
    $payment_trend = $payment_trend_result->fetch_all(MYSQLI_ASSOC);
    $payment_trend = array_reverse($payment_trend);
}

// Students with highest dues
$highest_dues_query = "
    SELECT s.roll_no, u.full_name, d.dept_name, f.total_fee, f.amount_paid, f.due_amount
    FROM fees f
    INNER JOIN students s ON f.student_id = s.student_id
    INNER JOIN users u ON s.user_id = u.user_id
    LEFT JOIN departments d ON u.dept_id = d.dept_id
    WHERE f.due_amount > 0
    ORDER BY f.due_amount DESC
    LIMIT 15
";

$highest_dues_result = $conn->query($highest_dues_query);
if (!$highest_dues_result) {
    $highest_dues = array();
} else {
    $highest_dues = $highest_dues_result->fetch_all(MYSQLI_ASSOC);
}

// Fee range distribution
$fee_range_query = "
    SELECT 
        CASE 
            WHEN due_amount = 0 THEN 'Fully Paid'
            WHEN due_amount > 0 AND due_amount <= 25000 THEN '₹0 - ₹25,000'
            WHEN due_amount > 25000 AND due_amount <= 50000 THEN '₹25,000 - ₹50,000'
            WHEN due_amount > 50000 THEN '₹50,000+'
        END as range,
        COUNT(*) as count
    FROM fees
    GROUP BY 
        CASE 
            WHEN due_amount = 0 THEN 'Fully Paid'
            WHEN due_amount > 0 AND due_amount <= 25000 THEN '₹0 - ₹25,000'
            WHEN due_amount > 25000 AND due_amount <= 50000 THEN '₹25,000 - ₹50,000'
            WHEN due_amount > 50000 THEN '₹50,000+'
        END
";

$fee_range_result = $conn->query($fee_range_query);
if (!$fee_range_result) {
    $fee_range = array();
} else {
    $fee_range = $fee_range_result->fetch_all(MYSQLI_ASSOC);
}

// Prepare chart data
$months = json_encode(array_map(function($item) { return $item['month']; }, $payment_trend));
$trends = json_encode(array_map(function($item) { return (float)$item['total']; }, $payment_trend));

$dept_names = json_encode(array_map(function($item) { return $item['dept_name']; }, $fee_by_dept));
$collected = json_encode(array_map(function($item) { return (float)$item['collected']; }, $fee_by_dept));
?>

<div class="d-flex">
    <?php require_once('../../includes/sidebar.php'); ?>
    
    <main class="flex-grow-1 p-4">
        <div class="container-fluid">
            <h1 class="mb-4"><i class="fas fa-chart-bar"></i> Fee Report</h1>

            <?php if (isset($error_msg)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Report Navigation -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Select Report Type</h5>
                </div>
                <div class="card-body">
                    <div class="btn-group flex-wrap" role="group">
                        <a href="?type=overview" class="btn <?php echo ($report_type == 'overview' || $report_type == '') ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="fas fa-chart-pie"></i> Overview
                        </a>
                        <a href="?type=department" class="btn <?php echo $report_type == 'department' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="fas fa-building"></i> By Department
                        </a>
                        <a href="?type=trend" class="btn <?php echo $report_type == 'trend' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="fas fa-chart-line"></i> Trend Analysis
                        </a>
                        <a href="?type=pending" class="btn <?php echo $report_type == 'pending' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="fas fa-exclamation"></i> Highest Dues
                        </a>
                    </div>
                </div>
            </div>

            <?php if ($report_type == 'overview' || $report_type == ''): ?>
                <!-- OVERVIEW REPORT -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <h6>Total Fees</h6>
                                <h3>₹<?php echo number_format($fee_stats['total_fees'] ?? 0, 0); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <h6>Collected</h6>
                                <h3>₹<?php echo number_format($fee_stats['amount_collected'] ?? 0, 0); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-white bg-danger">
                            <div class="card-body">
                                <h6>Pending</h6>
                                <h3>₹<?php echo number_format($fee_stats['amount_due'] ?? 0, 0); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <h6>Collection Rate</h6>
                                <h3>
                                    <?php 
                                    $rate = ($fee_stats['total_fees'] ?? 0) > 0 ? (($fee_stats['amount_collected'] ?? 0) / $fee_stats['total_fees'] * 100) : 0;
                                    echo number_format($rate, 1) . '%';
                                    ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-users"></i> Student Payment Status</h5>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <tr>
                                        <td><strong>Paid Students</strong></td>
                                        <td class="text-end"><span class="badge bg-success" style="font-size: 1rem;"><?php echo $fee_stats['paid_students'] ?? 0; ?></span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Pending Students</strong></td>
                                        <td class="text-end"><span class="badge bg-danger" style="font-size: 1rem;"><?php echo $fee_stats['pending_students'] ?? 0; ?></span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total Students</strong></td>
                                        <td class="text-end"><span class="badge bg-info" style="font-size: 1rem;"><?php echo $fee_stats['total_students'] ?? 0; ?></span></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Dues Distribution</h5>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Range</th>
                                            <th>Count</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($fee_range) > 0): ?>
                                            <?php foreach ($fee_range as $range): ?>
                                                <tr>
                                                    <td><?php echo $range['range']; ?></td>
                                                    <td><span class="badge bg-info"><?php echo $range['count']; ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="2" class="text-center text-muted">No data available</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($report_type == 'department'): ?>
                <!-- DEPARTMENT REPORT -->
                <h3 class="mb-3"><i class="fas fa-building"></i> Fee Collection by Department</h3>
                
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-table"></i> Departmentwise Statistics</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Department</th>
                                    <th>Students</th>
                                    <th>Total Fee</th>
                                    <th>Collected</th>
                                    <th>Pending</th>
                                    <th>Collection %</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($fee_by_dept) > 0): ?>
                                    <?php foreach ($fee_by_dept as $dept): ?>
                                        <tr>
                                            <td><strong><?php echo $dept['dept_name']; ?></strong></td>
                                            <td><?php echo $dept['students']; ?></td>
                                            <td>₹<?php echo number_format($dept['total'] ?? 0, 0); ?></td>
                                            <td><span class="badge bg-success">₹<?php echo number_format($dept['collected'] ?? 0, 0); ?></span></td>
                                            <td><span class="badge bg-danger">₹<?php echo number_format($dept['pending'] ?? 0, 0); ?></span></td>
                                            <td>
                                                <?php 
                                                $pct = ($dept['total'] ?? 0) > 0 ? (($dept['collected'] ?? 0) / $dept['total'] * 100) : 0;
                                                echo number_format($pct, 1) . '%';
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No department data available</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($report_type == 'trend'): ?>
                <!-- TREND REPORT -->
                <h3 class="mb-3"><i class="fas fa-chart-line"></i> Payment Trend Analysis</h3>
                
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-line"></i> Payment Collection Trend</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($payment_trend) > 0): ?>
                            <div class="chart-container" style="position: relative; height: 400px;">
                                <canvas id="trendChart"></canvas>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No payment data available for chart
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-table"></i> Monthly Details</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Month</th>
                                    <th>Total Collected</th>
                                    <th>Transactions</th>
                                    <th>Avg per Transaction</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($payment_trend) > 0): ?>
                                    <?php foreach ($payment_trend as $trend): ?>
                                        <tr>
                                            <td><?php echo date('F Y', strtotime($trend['month'] . '-01')); ?></td>
                                            <td><strong>₹<?php echo number_format($trend['total'] ?? 0, 0); ?></strong></td>
                                            <td><?php echo $trend['transactions']; ?></td>
                                            <td>₹<?php echo number_format(($trend['total'] ?? 0) / ($trend['transactions'] ?: 1), 0); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">No trend data available</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($report_type == 'pending'): ?>
                <!-- PENDING REPORT -->
                <h3 class="mb-3"><i class="fas fa-exclamation"></i> Students with Highest Dues</h3>
                
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-list"></i> Top Outstanding Dues (<?php echo count($highest_dues); ?>)</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Roll No</th>
                                    <th>Student Name</th>
                                    <th>Department</th>
                                    <th>Total Fee</th>
                                    <th>Paid</th>
                                    <th>Due</th>
                                    <th>% Paid</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($highest_dues) > 0): ?>
                                    <?php foreach ($highest_dues as $student): ?>
                                        <tr class="<?php echo ($student['due_amount'] ?? 0) > 50000 ? 'table-danger' : (($student['due_amount'] ?? 0) > 25000 ? 'table-warning' : ''); ?>">
                                            <td><?php echo $student['roll_no']; ?></td>
                                            <td><?php echo $student['full_name']; ?></td>
                                            <td><?php echo $student['dept_name'] ?? 'N/A'; ?></td>
                                            <td>₹<?php echo number_format($student['total_fee'] ?? 0, 0); ?></td>
                                            <td><span class="badge bg-success">₹<?php echo number_format($student['amount_paid'] ?? 0, 0); ?></span></td>
                                            <td><span class="badge bg-danger">₹<?php echo number_format($student['due_amount'] ?? 0, 0); ?></span></td>
                                            <td>
                                                <?php 
                                                $pct = (($student['amount_paid'] ?? 0) / ($student['total_fee'] ?: 1)) * 100;
                                                echo number_format($pct, 0) . '%';
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">
                                            <i class="fas fa-check-circle"></i> All fees are paid!
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php endif; ?>

            <!-- Print/Export Buttons -->
            <div class="mt-4 mb-4">
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
    <?php if ($report_type == 'trend' && count($payment_trend) > 0): ?>
    setTimeout(function() {
        const ctx = document.getElementById('trendChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo $months; ?>,
                    datasets: [{
                        label: 'Amount Collected (₹)',
                        data: <?php echo $trends; ?>,
                        borderColor: 'rgba(54, 162, 235, 1)',
                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 6,
                        pointBackgroundColor: 'rgba(54, 162, 235, 1)',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { 
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₹' + value.toLocaleString();
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Amount: ₹' + context.parsed.y.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }
    }, 100);
    <?php endif; ?>
</script>

<?php require_once('../../includes/footer.php'); ?>