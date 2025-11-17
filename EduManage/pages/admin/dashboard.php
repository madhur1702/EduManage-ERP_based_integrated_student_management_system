<?php
$page_title = 'Admin Dashboard';
require_once('../../config/config.php');
require_once('../../includes/db.php');
require_once('../../includes/functions.php');
require_once('../../includes/header.php');

if (!hasRole('Admin')) {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

global $conn;

// Fetch statistics
$total_students = countStats('students');
$total_faculty = countStats('faculty');
$total_courses = countStats('courses');
$total_books = countStats('books');
$available_books = countStats('available_books');
$issued_books = countStats('issued_books');

// Get students by department
$dept_students = $conn->query("
    SELECT d.dept_name, COUNT(s.student_id) as count 
    FROM departments d 
    LEFT JOIN users u ON d.dept_id = u.dept_id AND u.role_id = 4
    LEFT JOIN students s ON s.user_id = u.user_id 
    GROUP BY d.dept_id, d.dept_name
")->fetch_all(MYSQLI_ASSOC);

// Get courses by semester
$semester_courses = $conn->query("
    SELECT semester, COUNT(course_id) as count 
    FROM courses 
    GROUP BY semester 
    ORDER BY semester
")->fetch_all(MYSQLI_ASSOC);

// Get attendance data for last 7 days
$attendance_data = $conn->query("
    SELECT DATE(attendance_date) as date, 
           SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
           SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent
    FROM attendance 
    WHERE attendance_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(attendance_date)
    ORDER BY date DESC
    LIMIT 7
")->fetch_all(MYSQLI_ASSOC);
$attendance_data = array_reverse($attendance_data);

// Get fee collection data
$fee_data = $conn->query("
    SELECT 
        SUM(total_fee) as total_fees,
        SUM(amount_paid) as amount_collected,
        SUM(due_amount) as amount_due,
        COUNT(DISTINCT student_id) as total_students
    FROM fees
")->fetch_assoc();
?>

<div class="d-flex">
    <?php require_once('../../includes/sidebar.php'); ?>
    
    <main class="flex-grow-1 p-4">
        <div class="container-fluid">
            <h1 class="mb-4"><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <h5 class="card-title">Total Students</h5>
                            <p class="card-text display-4"><?php echo $total_students; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h5 class="card-title">Total Faculty</h5>
                            <p class="card-text display-4"><?php echo $total_faculty; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <h5 class="card-title">Total Courses</h5>
                            <p class="card-text display-4"><?php echo $total_courses; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <h5 class="card-title">Library Books</h5>
                            <p class="card-text display-4"><?php echo $total_books; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 1 -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Students by Department</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="position: relative; height: 300px; width: 100%;">
                                <canvas id="deptChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Courses by Semester</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="position: relative; height: 300px; width: 100%;">
                                <canvas id="semesterChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2 -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-chart-line"></i> Attendance (Last 7 Days)</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="position: relative; height: 300px; width: 100%;">
                                <canvas id="attendanceChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-warning text-white">
                            <h5 class="mb-0"><i class="fas fa-book"></i> Library Status</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="position: relative; height: 300px; width: 100%;">
                                <canvas id="libraryChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fee Statistics -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0"><i class="fas fa-dollar-sign"></i> Fee Collection Statistics</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-3">
                                    <h6>Total Fees</h6>
                                    <h3 class="text-primary">₹<?php echo number_format($fee_data['total_fees'] ?? 0, 2); ?></h3>
                                </div>
                                <div class="col-md-3">
                                    <h6>Collected</h6>
                                    <h3 class="text-success">₹<?php echo number_format($fee_data['amount_collected'] ?? 0, 2); ?></h3>
                                </div>
                                <div class="col-md-3">
                                    <h6>Pending</h6>
                                    <h3 class="text-danger">₹<?php echo number_format($fee_data['amount_due'] ?? 0, 2); ?></h3>
                                </div>
                                <div class="col-md-3">
                                    <h6>Collection Rate</h6>
                                    <h3 class="text-info">
                                        <?php 
                                        $rate = $fee_data['total_fees'] > 0 ? ($fee_data['amount_collected'] / $fee_data['total_fees'] * 100) : 0;
                                        echo number_format($rate, 2) . '%';
                                        ?>
                                    </h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <a href="manage-users.php" class="btn btn-outline-primary btn-block mb-2"><i class="fas fa-users"></i> Manage Users</a>
                            <a href="manage-courses.php" class="btn btn-outline-info btn-block mb-2"><i class="fas fa-book"></i> Manage Courses</a>
                            <a href="manage-library.php" class="btn btn-outline-warning btn-block mb-2"><i class="fas fa-library"></i> Manage Library</a>
                            <a href="manage-fees.php" class="btn btn-outline-success btn-block"><i class="fas fa-dollar-sign"></i> Manage Fees</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">System Summary</h5>
                        </div>
                        <div class="card-body">
                            <p><i class="fas fa-circle text-success"></i> Available Books: <strong><?php echo $available_books; ?></strong></p>
                            <p><i class="fas fa-circle text-danger"></i> Issued Books: <strong><?php echo $issued_books; ?></strong></p>
                            <p><i class="fas fa-circle text-warning"></i> Total Departments: <strong><?php echo count($dept_students); ?></strong></p>
                            <p><i class="fas fa-circle text-primary"></i> Active Semesters: <strong><?php echo count($semester_courses); ?></strong></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
    // Prepare data from PHP
    const deptLabels = <?php echo json_encode(array_map(function($d) { return $d['dept_name']; }, $dept_students)); ?>;
    const deptData = <?php echo json_encode(array_map(function($d) { return (int)$d['count']; }, $dept_students)); ?>;
    
    const semesterLabels = <?php echo json_encode(array_map(function($s) { return 'Semester ' . $s['semester']; }, $semester_courses)); ?>;
    const semesterData = <?php echo json_encode(array_map(function($s) { return (int)$s['count']; }, $semester_courses)); ?>;
    
    const attendanceDates = <?php echo json_encode(array_map(function($a) { return $a['date']; }, $attendance_data)); ?>;
    const attendancePresent = <?php echo json_encode(array_map(function($a) { return (int)$a['present']; }, $attendance_data)); ?>;
    const attendanceAbsent = <?php echo json_encode(array_map(function($a) { return (int)$a['absent']; }, $attendance_data)); ?>;

    // Define colors
    const colors = [
        'rgba(255, 99, 132, 0.7)',
        'rgba(54, 162, 235, 0.7)',
        'rgba(75, 192, 192, 0.7)',
        'rgba(255, 206, 86, 0.7)',
        'rgba(153, 102, 255, 0.7)'
    ];
    
    const borderColors = [
        'rgba(255, 99, 132, 1)',
        'rgba(54, 162, 235, 1)',
        'rgba(75, 192, 192, 1)',
        'rgba(255, 206, 86, 1)',
        'rgba(153, 102, 255, 1)'
    ];

    // Students by Department - Pie Chart
    setTimeout(function() {
        const ctx1 = document.getElementById('deptChart');
        if (ctx1) {
            new Chart(ctx1, {
                type: 'doughnut',
                data: {
                    labels: deptLabels,
                    datasets: [{
                        data: deptData,
                        backgroundColor: colors,
                        borderColor: borderColors,
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
    }, 100);

    // Courses by Semester - Bar Chart
    setTimeout(function() {
        const ctx2 = document.getElementById('semesterChart');
        if (ctx2) {
            new Chart(ctx2, {
                type: 'bar',
                data: {
                    labels: semesterLabels,
                    datasets: [{
                        label: 'Number of Courses',
                        data: semesterData,
                        backgroundColor: 'rgba(54, 162, 235, 0.7)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }
    }, 100);

    // Attendance Last 7 Days - Line Chart
    setTimeout(function() {
        const ctx3 = document.getElementById('attendanceChart');
        if (ctx3) {
            new Chart(ctx3, {
                type: 'line',
                data: {
                    labels: attendanceDates,
                    datasets: [
                        {
                            label: 'Present',
                            data: attendancePresent,
                            borderColor: 'rgba(75, 192, 75, 1)',
                            backgroundColor: 'rgba(75, 192, 75, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4,
                            pointRadius: 4,
                            pointBackgroundColor: 'rgba(75, 192, 75, 1)'
                        },
                        {
                            label: 'Absent',
                            data: attendanceAbsent,
                            borderColor: 'rgba(255, 99, 132, 1)',
                            backgroundColor: 'rgba(255, 99, 132, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4,
                            pointRadius: 4,
                            pointBackgroundColor: 'rgba(255, 99, 132, 1)'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
    }, 100);

    // Library Status - Doughnut Chart
    setTimeout(function() {
        const ctx4 = document.getElementById('libraryChart');
        if (ctx4) {
            new Chart(ctx4, {
                type: 'doughnut',
                data: {
                    labels: ['Available', 'Issued'],
                    datasets: [{
                        data: [<?php echo $available_books; ?>, <?php echo $issued_books; ?>],
                        backgroundColor: [
                            'rgba(75, 192, 192, 0.7)',
                            'rgba(255, 99, 132, 0.7)'
                        ],
                        borderColor: [
                            'rgba(75, 192, 192, 1)',
                            'rgba(255, 99, 132, 1)'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
    }, 100);
</script>

<?php require_once('../../includes/footer.php'); ?>