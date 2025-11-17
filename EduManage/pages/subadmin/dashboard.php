<?php
$page_title = 'SubAdmin Dashboard';
require_once('../../config/config.php');
require_once('../../includes/db.php');
require_once('../../includes/functions.php');
require_once('../../includes/header.php');

if (!hasRole('SubAdmin')) {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

global $conn;

// Get statistics
$total_students = countStats('students');
$total_faculty = countStats('faculty');
$total_courses = countStats('courses');

// Get students by department
$dept_students = $conn->query("
    SELECT d.dept_name, COUNT(s.student_id) as count 
    FROM departments d 
    LEFT JOIN users u ON d.dept_id = u.dept_id AND u.role_id = 4
    LEFT JOIN students s ON s.user_id = u.user_id 
    GROUP BY d.dept_id, d.dept_name
")->fetch_all(MYSQLI_ASSOC);

// Get faculty by department
$dept_faculty = $conn->query("
    SELECT d.dept_name, COUNT(f.faculty_id) as count 
    FROM departments d 
    LEFT JOIN users u ON d.dept_id = u.dept_id AND u.role_id = 3
    LEFT JOIN faculty f ON f.user_id = u.user_id 
    GROUP BY d.dept_id, d.dept_name
")->fetch_all(MYSQLI_ASSOC);

// Get enrollment by semester
$enrollment_by_semester = $conn->query("
    SELECT c.semester, COUNT(e.enrollment_id) as count 
    FROM courses c 
    LEFT JOIN enrollments e ON c.course_id = e.course_id 
    GROUP BY c.semester 
    ORDER BY c.semester
")->fetch_all(MYSQLI_ASSOC);

// Get attendance statistics
$attendance_stats = $conn->query("
    SELECT 
        SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as total_present,
        SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as total_absent,
        COUNT(*) as total_records,
        DATE(a.attendance_date) as date
    FROM attendance a
    WHERE a.attendance_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(a.attendance_date)
    ORDER BY date DESC
    LIMIT 7
")->fetch_all(MYSQLI_ASSOC);
$attendance_stats = array_reverse($attendance_stats);

// Get recently added students
$recent_students = $conn->query("
    SELECT s.*, u.full_name, u.email, d.dept_name
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN departments d ON u.dept_id = d.dept_id
    ORDER BY u.created_at DESC
    LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

// Get recently added faculty
$recent_faculty = $conn->query("
    SELECT f.*, u.full_name, u.email, d.dept_name
    FROM faculty f
    JOIN users u ON f.user_id = u.user_id
    LEFT JOIN departments d ON u.dept_id = d.dept_id
    ORDER BY u.created_at DESC
    LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

// Total enrollment
$total_enrollment = $conn->query("SELECT COUNT(*) as count FROM enrollments")->fetch_assoc()['count'];
?>

<div class="d-flex">
    <?php require_once('../../includes/sidebar.php'); ?>
    
    <main class="flex-grow-1 p-4">
        <div class="container-fluid">
            <h1 class="mb-4"><i class="fas fa-tachometer-alt"></i> SubAdmin Dashboard</h1>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-user-graduate"></i> Total Students</h5>
                            <p class="card-text display-4"><?php echo $total_students; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-chalkboard-user"></i> Total Faculty</h5>
                            <p class="card-text display-4"><?php echo $total_faculty; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-book"></i> Total Courses</h5>
                            <p class="card-text display-4"><?php echo $total_courses; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-users-class"></i> Total Enrollments</h5>
                            <p class="card-text display-4"><?php echo $total_enrollment; ?></p>
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
                                <canvas id="studentDeptChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Faculty by Department</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="position: relative; height: 300px; width: 100%;">
                                <canvas id="facultyDeptChart"></canvas>
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
                            <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Enrollments by Semester</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="position: relative; height: 300px; width: 100%;">
                                <canvas id="semesterChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-warning text-white">
                            <h5 class="mb-0"><i class="fas fa-chart-line"></i> Attendance (Last 7 Days)</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="position: relative; height: 300px; width: 100%;">
                                <canvas id="attendanceChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary Statistics -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="fas fa-chart-bar"></i> System Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-3">
                                    <h6>Total Attendance Records</h6>
                                    <h3 class="text-primary"><?php echo array_sum(array_column($attendance_stats, 'total_records')); ?></h3>
                                </div>
                                <div class="col-md-3">
                                    <h6>Average Attendance Rate</h6>
                                    <h3 class="text-success">
                                        <?php 
                                        $total_records = array_sum(array_column($attendance_stats, 'total_records'));
                                        $total_present = array_sum(array_column($attendance_stats, 'total_present'));
                                        $rate = $total_records > 0 ? round(($total_present / $total_records) * 100, 2) : 0;
                                        echo $rate . '%';
                                        ?>
                                    </h3>
                                </div>
                                <div class="col-md-3">
                                    <h6>Departments</h6>
                                    <h3 class="text-info"><?php echo count($dept_students); ?></h3>
                                </div>
                                <div class="col-md-3">
                                    <h6>Active Semesters</h6>
                                    <h3 class="text-warning"><?php echo count($enrollment_by_semester); ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Additions -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-user-graduate"></i> Recently Added Students (8)</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Roll No</th>
                                        <th>Name</th>
                                        <th>Department</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_students as $student): ?>
                                        <tr>
                                            <td><small><?php echo $student['roll_no']; ?></small></td>
                                            <td><small><?php echo $student['full_name']; ?></small></td>
                                            <td><small><?php echo $student['dept_name'] ?? 'N/A'; ?></small></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-chalkboard-user"></i> Recently Added Faculty (8)</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Designation</th>
                                        <th>Department</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_faculty as $faculty): ?>
                                        <tr>
                                            <td><small><?php echo $faculty['full_name']; ?></small></td>
                                            <td><small><?php echo $faculty['designation']; ?></small></td>
                                            <td><small><?php echo $faculty['dept_name'] ?? 'N/A'; ?></small></td>
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
                            <a href="manage-students.php" class="btn btn-outline-primary btn-block mb-2">
                                <i class="fas fa-user-graduate"></i> Manage Students
                            </a>
                            <a href="manage-faculty.php" class="btn btn-outline-success btn-block mb-2">
                                <i class="fas fa-chalkboard-user"></i> Manage Faculty
                            </a>
                            <a href="view-reports.php" class="btn btn-outline-info btn-block">
                                <i class="fas fa-chart-bar"></i> View Reports
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-info-circle"></i> Important Info</h5>
                        </div>
                        <div class="card-body">
                            <p><i class="fas fa-circle text-primary"></i> Total Students: <strong><?php echo $total_students; ?></strong></p>
                            <p><i class="fas fa-circle text-success"></i> Total Faculty: <strong><?php echo $total_faculty; ?></strong></p>
                            <p><i class="fas fa-circle text-warning"></i> Total Courses: <strong><?php echo $total_courses; ?></strong></p>
                            <p><i class="fas fa-circle text-danger"></i> Total Enrollments: <strong><?php echo $total_enrollment; ?></strong></p>
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
    const deptStudentLabels = <?php echo json_encode(array_map(function($d) { return $d['dept_name']; }, $dept_students)); ?>;
    const deptStudentData = <?php echo json_encode(array_map(function($d) { return (int)$d['count']; }, $dept_students)); ?>;
    
    const deptFacultyLabels = <?php echo json_encode(array_map(function($d) { return $d['dept_name']; }, $dept_faculty)); ?>;
    const deptFacultyData = <?php echo json_encode(array_map(function($d) { return (int)$d['count']; }, $dept_faculty)); ?>;
    
    const semesterLabels = <?php echo json_encode(array_map(function($s) { return 'Sem ' . $s['semester']; }, $enrollment_by_semester)); ?>;
    const semesterData = <?php echo json_encode(array_map(function($s) { return (int)$s['count']; }, $enrollment_by_semester)); ?>;
    
    const attendanceDates = <?php echo json_encode(array_map(function($a) { return $a['date']; }, $attendance_stats)); ?>;
    const attendancePresent = <?php echo json_encode(array_map(function($a) { return (int)$a['total_present']; }, $attendance_stats)); ?>;
    const attendanceAbsent = <?php echo json_encode(array_map(function($a) { return (int)$a['total_absent']; }, $attendance_stats)); ?>;

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

    // Students by Department Chart
    setTimeout(function() {
        const ctx1 = document.getElementById('studentDeptChart');
        if (ctx1) {
            new Chart(ctx1, {
                type: 'doughnut',
                data: {
                    labels: deptStudentLabels,
                    datasets: [{
                        data: deptStudentData,
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

    // Faculty by Department Chart
    setTimeout(function() {
        const ctx2 = document.getElementById('facultyDeptChart');
        if (ctx2) {
            new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: deptFacultyLabels,
                    datasets: [{
                        data: deptFacultyData,
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

    // Enrollments by Semester Chart
    setTimeout(function() {
        const ctx3 = document.getElementById('semesterChart');
        if (ctx3) {
            new Chart(ctx3, {
                type: 'bar',
                data: {
                    labels: semesterLabels,
                    datasets: [{
                        label: 'Enrollments',
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

    // Attendance Chart
    setTimeout(function() {
        const ctx4 = document.getElementById('attendanceChart');
        if (ctx4) {
            new Chart(ctx4, {
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
</script>

<?php require_once('../../includes/footer.php'); ?>