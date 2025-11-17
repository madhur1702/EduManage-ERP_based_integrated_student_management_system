<?php
$page_title = 'Accountant Dashboard';
require_once('../../config/config.php');
require_once('../../includes/db.php');
require_once('../../includes/functions.php');
require_once('../../includes/header.php');

if (!hasRole('Accountant')) {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

global $conn;

// Get fee statistics
$fee_stats = $conn->query("
    SELECT 
        SUM(total_fee) as total_fees,
        SUM(amount_paid) as amount_collected,
        SUM(due_amount) as amount_due,
        COUNT(DISTINCT student_id) as total_students,
        COUNT(CASE WHEN due_amount > 0 THEN 1 END) as pending_students
    FROM fees
")->fetch_assoc();

// Get payment by month (last 6 months)
$payment_by_month = $conn->query("
    SELECT DATE_FORMAT(last_payment_date, '%Y-%m') as month, SUM(amount_paid) as total
    FROM fees
    WHERE last_payment_date IS NOT NULL AND last_payment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(last_payment_date, '%Y-%m')
    ORDER BY month
")->fetch_all(MYSQLI_ASSOC);

// Get students with pending fees
$pending_fees = $conn->query("
    SELECT s.roll_no, u.full_name, f.total_fee, f.amount_paid, f.due_amount, d.dept_name
    FROM fees f
    JOIN students s ON f.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN departments d ON u.dept_id = d.dept_id
    WHERE f.due_amount > 0
    ORDER BY f.due_amount DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Fee collection by department
$fee_by_dept = $conn->query("
    SELECT d.dept_name, COUNT(f.fee_id) as total_students, SUM(f.total_fee) as total_fees, SUM(f.amount_paid) as collected
    FROM fees f
    JOIN students s ON f.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    JOIN departments d ON u.dept_id = d.dept_id
    GROUP BY d.dept_id, d.dept_name
")->fetch_all(MYSQLI_ASSOC);

// Recent payments
$recent_payments = $conn->query("
    SELECT s.roll_no, u.full_name, f.amount_paid, f.last_payment_date, d.dept_name
    FROM fees f
    JOIN students s ON f.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN departments d ON u.dept_id = d.dept_id
    WHERE f.last_payment_date IS NOT NULL
    ORDER BY f.last_payment_date DESC
    LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

// Prepare chart data
$months = json_encode(array_column($payment_by_month, 'month'));
$monthly_totals = json_encode(array_column($payment_by_month, 'total'));

$dept_names = json_encode(array_column($fee_by_dept, 'dept_name'));
$collected_amounts = json_encode(array_column($fee_by_dept, 'collected'));
?>

<div class="d-flex">
    <?php require_once('../../includes/sidebar.php'); ?>
    
    <main class="flex-grow-1 p-4">
        <div class="container-fluid">
            <h1 class="mb-4"><i class="fas fa-tachometer-alt"></i> Accountant Dashboard</h1>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <h6 class="card-title"><i class="fas fa-money-bill-wave"></i> Total Fees</h6>
                            <p class="card-text" style="font-size: 1.8rem; font-weight: 700;">₹<?php echo number_format($fee_stats['total_fees'] ?? 0, 0); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h6 class="card-title"><i class="fas fa-check-circle"></i> Collected</h6>
                            <p class="card-text" style="font-size: 1.8rem; font-weight: 700;">₹<?php echo number_format($fee_stats['amount_collected'] ?? 0, 0); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-danger">
                        <div class="card-body">
                            <h6 class="card-title"><i class="fas fa-exclamation-circle"></i> Pending</h6>
                            <p class="card-text" style="font-size: 1.8rem; font-weight: 700;">₹<?php echo number_format($fee_stats['amount_due'] ?? 0, 0); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <h6 class="card-title"><i class="fas fa-percent"></i> Collection Rate</h6>
                            <p class="card-text" style="font-size: 1.8rem; font-weight: 700;">
                                <?php 
                                $rate = $fee_stats['total_fees'] > 0 ? ($fee_stats['amount_collected'] / $fee_stats['total_fees'] * 100) : 0;
                                echo number_format($rate, 1) . '%';
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-chart-line"></i> Monthly Payment Collection</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="position: relative; height: 300px;">
                                <canvas id="monthlyChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Fee Collection by Department</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="position: relative; height: 300px;">
                                <canvas id="deptChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Fees Alert -->
            <?php if ($fee_stats['pending_students'] > 0): ?>
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="alert alert-warning alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong><?php echo $fee_stats['pending_students']; ?> students have pending fees!</strong> 
                            Total pending: <strong>₹<?php echo number_format($fee_stats['amount_due'] ?? 0, 2); ?></strong>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Recent Payments & Pending Fees -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-history"></i> Recent Payments (<?php echo count($recent_payments); ?>)</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Roll No</th>
                                        <th>Student</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_payments as $payment): ?>
                                        <tr>
                                            <td><?php echo $payment['roll_no']; ?></td>
                                            <td><?php echo substr($payment['full_name'], 0, 20); ?></td>
                                            <td><span class="badge bg-success">₹<?php echo number_format($payment['amount_paid'], 0); ?></span></td>
                                            <td><?php echo date('d-M-Y', strtotime($payment['last_payment_date'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0"><i class="fas fa-exclamation"></i> Pending Fees (Top 10)</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Roll No</th>
                                        <th>Student</th>
                                        <th>Pending</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_fees as $pending): ?>
                                        <tr>
                                            <td><?php echo $pending['roll_no']; ?></td>
                                            <td><?php echo substr($pending['full_name'], 0, 20); ?></td>
                                            <td><span class="badge bg-danger">₹<?php echo number_format($pending['due_amount'], 0); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <a href="manage-fees.php" class="btn btn-outline-primary btn-block mb-2">
                                <i class="fas fa-money-bill-wave"></i> Manage Student Fees
                            </a>
                            <a href="fee-report.php" class="btn btn-outline-success btn-block mb-2">
                                <i class="fas fa-chart-bar"></i> View Reports
                            </a>
                            <a href="profile.php" class="btn btn-outline-info btn-block">
                                <i class="fas fa-user"></i> My Profile
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-info-circle"></i> Finance Summary</h5>
                        </div>
                        <div class="card-body">
                            <p><i class="fas fa-circle text-primary"></i> Total Students: <strong><?php echo $fee_stats['total_students']; ?></strong></p>
                            <p><i class="fas fa-circle text-success"></i> Paid Students: <strong><?php echo $fee_stats['total_students'] - $fee_stats['pending_students']; ?></strong></p>
                            <p><i class="fas fa-circle text-danger"></i> Pending Students: <strong><?php echo $fee_stats['pending_students']; ?></strong></p>
                            <p><i class="fas fa-circle text-warning"></i> Departments: <strong><?php echo count($fee_by_dept); ?></strong></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
    // Monthly Payment Collection Chart
    setTimeout(function() {
        const ctx1 = document.getElementById('monthlyChart');
        if (ctx1) {
            new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: <?php echo $months; ?>,
                    datasets: [{
                        label: 'Amount Collected (₹)',
                        data: <?php echo $monthly_totals; ?>,
                        borderColor: 'rgba(54, 162, 235, 1)',
                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 5,
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
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    }
                }
            });
        }
    }, 100);

    // Fee Collection by Department Chart
    setTimeout(function() {
        const ctx2 = document.getElementById('deptChart');
        if (ctx2) {
            new Chart(ctx2, {
                type: 'bar',
                data: {
                    labels: <?php echo $dept_names; ?>,
                    datasets: [{
                        label: 'Amount Collected (₹)',
                        data: <?php echo $collected_amounts; ?>,
                        backgroundColor: 'rgba(75, 192, 192, 0.7)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    }
                }
            });
        }
    }, 100);
</script>

<?php require_once('../../includes/footer.php'); ?>