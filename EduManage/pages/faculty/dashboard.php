<?php
$page_title = 'Faculty Dashboard';
require_once('../../config/config.php');
require_once('../../includes/db.php');
require_once('../../includes/functions.php');
require_once('../../includes/header.php');

if (!hasRole('Faculty')) {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

global $conn;

// Get faculty info
$faculty = getFacultyInfo($_SESSION['user_id']);
$faculty_id = $faculty['faculty_id'];

// Get assigned courses
$courses_query = "SELECT * FROM courses WHERE faculty_id = ?";
$stmt = $conn->prepare($courses_query);
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get total students taught
$students_query = "
    SELECT COUNT(DISTINCT e.student_id) as count 
    FROM enrollments e 
    JOIN courses c ON e.course_id = c.course_id 
    WHERE c.faculty_id = ?
";
$stmt = $conn->prepare($students_query);
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$total_students = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Get attendance summary
$attendance_query = "
    SELECT 
        SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as total_present,
        SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as total_absent,
        COUNT(*) as total_records
    FROM attendance a
    JOIN enrollments e ON a.enrollment_id = e.enrollment_id
    JOIN courses c ON e.course_id = c.course_id
    WHERE c.faculty_id = ?
";
$stmt = $conn->prepare($attendance_query);
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$attendance_summary = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get marks statistics
$marks_query = "
    SELECT 
        c.course_name,
        COUNT(m.mark_id) as total_marks,
        AVG(m.marks_obtained) as avg_marks,
        MAX(m.marks_obtained) as max_marks,
        MIN(m.marks_obtained) as min_marks
    FROM marks m
    JOIN enrollments e ON m.enrollment_id = e.enrollment_id
    JOIN courses c ON e.course_id = c.course_id
    WHERE c.faculty_id = ?
    GROUP BY c.course_id, c.course_name
";
$stmt = $conn->prepare($marks_query);
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$marks_stats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Attendance by date (last 7 days)
$attendance_date_query = "
    SELECT DATE(a.attendance_date) as date,
           SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present,
           SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent
    FROM attendance a
    JOIN enrollments e ON a.enrollment_id = e.enrollment_id
    JOIN courses c ON e.course_id = c.course_id
    WHERE c.faculty_id = ? AND a.attendance_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(a.attendance_date)
    ORDER BY date DESC
    LIMIT 7
";
$stmt = $conn->prepare($attendance_date_query);
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$attendance_by_date = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$attendance_by_date = array_reverse($attendance_by_date);

// Prepare chart data
$course_names = json_encode(array_map(function($c) { return $c['course_name']; }, $marks_stats));
$avg_marks_data = json_encode(array_map(function($c) { return (float)($c['avg_marks'] ?? 0); }, $marks_stats));
$max_marks_data = json_encode(array_map(function($c) { return (float)($c['max_marks'] ?? 0); }, $marks_stats));

$dates = json_encode(array_map(function($a) { return $a['date']; }, $attendance_by_date));
$present_data = json_encode(array_map(function($a) { return (int)($a['present'] ?? 0); }, $attendance_by_date));
$absent_data = json_encode(array_map(function($a) { return (int)($a['absent'] ?? 0); }, $attendance_by_date));
?>

<div class="d-flex">
    <?php require_once('../../includes/sidebar.php'); ?>
    
    <main class="flex-grow-1 p-4">
        <div class="container-fluid">
            <h1 class="mb-4"><i class="fas fa-tachometer-alt"></i> Faculty Dashboard</h1>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <h6 class="card-title"><i class="fas fa-book"></i> Courses</h6>
                            <p class="card-text display-4"><?php echo count($courses); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h6 class="card-title"><i class="fas fa-users"></i> Students</h6>
                            <p class="card-text display-4"><?php echo $total_students; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <h6 class="card-title"><i class="fas fa-percent"></i> Attendance Rate</h6>
                            <p class="card-text display-4">
                                <?php 
                                $attendance_rate = ($attendance_summary['total_records'] ?? 0) > 0 
                                    ? round((($attendance_summary['total_present'] ?? 0) / $attendance_summary['total_records']) * 100, 1) 
                                    : 0;
                                echo $attendance_rate . '%';
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <h6 class="card-title"><i class="fas fa-pen-alt"></i> Avg Class Marks</h6>
                            <p class="card-text display-4">
                                <?php 
                                $overall_avg = 0;
                                if (count($marks_stats) > 0) {
                                    $total = 0;
                                    foreach ($marks_stats as $m) {
                                        $total += (float)($m['avg_marks'] ?? 0);
                                    }
                                    $overall_avg = round($total / count($marks_stats), 1);
                                }
                                echo $overall_avg;
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
                            <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Average Marks by Course</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($marks_stats) > 0): ?>
                                <div class="chart-container" style="position: relative; height: 300px;">
                                    <canvas id="marksChart"></canvas>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info mb-0">
                                    <i class="fas fa-info-circle"></i> No marks data available yet
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-chart-line"></i> Attendance (Last 7 Days)</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($attendance_by_date) > 0): ?>
                                <div class="chart-container" style="position: relative; height: 300px;">
                                    <canvas id="attendanceChart"></canvas>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info mb-0">
                                    <i class="fas fa-info-circle"></i> No attendance data available
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <a href="manage-attendance.php" class="btn btn-outline-primary btn-block mb-2">
                                <i class="fas fa-clipboard-list"></i> Mark Attendance
                            </a>
                            <a href="manage-marks.php" class="btn btn-outline-success btn-block mb-2">
                                <i class="fas fa-pen-alt"></i> Manage Marks
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
                            <h5 class="mb-0"><i class="fas fa-info-circle"></i> Assigned Courses (<?php echo count($courses); ?>)</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group">
                                <?php foreach ($courses as $course): ?>
                                    <div class="list-group-item">
                                        <h6><?php echo $course['course_code']; ?> - <?php echo $course['course_name']; ?></h6>
                                        <small class="text-muted">Semester <?php echo $course['semester']; ?></small>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (count($courses) == 0): ?>
                                    <div class="list-group-item text-muted">
                                        <i class="fas fa-inbox"></i> No courses assigned
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Attendance Summary -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-warning text-white">
                            <h5 class="mb-0"><i class="fas fa-clipboard-list"></i> Overall Attendance Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-4">
                                    <h6>Total Records</h6>
                                    <h3 class="text-primary"><?php echo $attendance_summary['total_records'] ?? 0; ?></h3>
                                </div>
                                <div class="col-md-4">
                                    <h6>Present</h6>
                                    <h3 class="text-success"><?php echo $attendance_summary['total_present'] ?? 0; ?></h3>
                                </div>
                                <div class="col-md-4">
                                    <h6>Absent</h6>
                                    <h3 class="text-danger"><?php echo $attendance_summary['total_absent'] ?? 0; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
    <?php if (count($marks_stats) > 0): ?>
    // Average Marks by Course
    setTimeout(function() {
        const ctx1 = document.getElementById('marksChart');
        if (ctx1) {
            new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: <?php echo $course_names; ?>,
                    datasets: [
                        {
                            label: 'Average Marks',
                            data: <?php echo $avg_marks_data; ?>,
                            backgroundColor: 'rgba(75, 192, 192, 0.7)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 2
                        },
                        {
                            label: 'Maximum Marks',
                            data: <?php echo $max_marks_data; ?>,
                            backgroundColor: 'rgba(54, 162, 235, 0.7)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }
    }, 100);
    <?php endif; ?>

    <?php if (count($attendance_by_date) > 0): ?>
    // Attendance Chart
    setTimeout(function() {
        const ctx2 = document.getElementById('attendanceChart');
        if (ctx2) {
            new Chart(ctx2, {
                type: 'line',
                data: {
                    labels: <?php echo $dates; ?>,
                    datasets: [
                        {
                            label: 'Present',
                            data: <?php echo $present_data; ?>,
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
                            data: <?php echo $absent_data; ?>,
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
                        y: { beginAtZero: true }
                    }
                }
            });
        }
    }, 100);
    <?php endif; ?>
</script>

<?php require_once('../../includes/footer.php'); ?>