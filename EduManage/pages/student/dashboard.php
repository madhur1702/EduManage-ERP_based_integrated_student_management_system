<?php
$page_title = 'Student Dashboard';
require_once('../../config/config.php');
require_once('../../includes/db.php');
require_once('../../includes/functions.php');
require_once('../../includes/header.php');

if (!hasRole('Student')) {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

global $conn;

// Get student info
$student = getStudentInfo($_SESSION['user_id']);
$student_id = $student['student_id'];

// Get enrolled courses
$courses_query = "
    SELECT c.*, d.dept_name, u.full_name as faculty_name 
    FROM enrollments e 
    JOIN courses c ON e.course_id = c.course_id 
    LEFT JOIN departments d ON c.dept_id = d.dept_id 
    LEFT JOIN faculty f ON c.faculty_id = f.faculty_id 
    LEFT JOIN users u ON f.user_id = u.user_id 
    WHERE e.student_id = ? 
    ORDER BY c.course_name
";
$stmt = $conn->prepare($courses_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get marks data
$marks_query = "
    SELECT m.*, c.course_name, e.semester
    FROM marks m
    JOIN enrollments e ON m.enrollment_id = e.enrollment_id
    JOIN courses c ON e.course_id = c.course_id
    WHERE e.student_id = ?
    ORDER BY m.exam_date DESC
    LIMIT 10
";
$stmt = $conn->prepare($marks_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$marks_result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get attendance data
$attendance_query = "
    SELECT c.course_name, 
           COUNT(*) as total_classes,
           SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_classes
    FROM attendance a
    JOIN enrollments e ON a.enrollment_id = e.enrollment_id
    JOIN courses c ON e.course_id = c.course_id
    WHERE e.student_id = ?
    GROUP BY c.course_id, c.course_name
";
$stmt = $conn->prepare($attendance_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$attendance_result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get fee info
$fee_info = getStudentFeeInfo($student_id);

// Get issued books
$issued_books = getIssuedBooksByStudent($student_id);

// Calculate GPA (average percentage)
$gpa_query = "
    SELECT AVG((marks_obtained/max_marks)*100) as avg_percentage
    FROM marks
    WHERE enrollment_id IN (SELECT enrollment_id FROM enrollments WHERE student_id = ?)
";
$stmt = $conn->prepare($gpa_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$gpa_data = $stmt->get_result()->fetch_assoc();
$gpa = (float)($gpa_data['avg_percentage'] ?? 0);
$stmt->close();

// Prepare chart data
$course_names = json_encode(array_map(function($a) { return $a['course_name']; }, $attendance_result));
$attendance_percent = json_encode(array_map(function($row) {
    return $row['total_classes'] > 0 ? round(($row['present_classes'] / $row['total_classes']) * 100, 2) : 0;
}, $attendance_result));

// Marks by exam type
$marks_by_type_query = "
    SELECT exam_type, AVG(marks_obtained) as avg_marks, COUNT(*) as count
    FROM marks
    WHERE enrollment_id IN (SELECT enrollment_id FROM enrollments WHERE student_id = ?)
    GROUP BY exam_type
";
$stmt = $conn->prepare($marks_by_type_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$marks_by_type = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$exam_types = json_encode(array_map(function($m) { return $m['exam_type']; }, $marks_by_type));
$avg_marks = json_encode(array_map(function($m) { return round((float)($m['avg_marks'] ?? 0), 2); }, $marks_by_type));
?>

<div class="d-flex">
    <?php require_once('../../includes/sidebar.php'); ?>
    
    <main class="flex-grow-1 p-4">
        <div class="container-fluid">
            <h1 class="mb-4"><i class="fas fa-tachometer-alt"></i> Welcome, <?php echo $student['full_name']; ?></h1>

            <!-- Student Info Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <h6 class="card-title"><i class="fas fa-id-card"></i> Roll No</h6>
                            <p class="card-text display-6"><?php echo $student['roll_no']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h6 class="card-title"><i class="fas fa-book"></i> Courses</h6>
                            <p class="card-text display-6"><?php echo count($courses); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <h6 class="card-title"><i class="fas fa-chart-line"></i> Average GPA</h6>
                            <p class="card-text display-6"><?php echo number_format($gpa, 2); ?>%</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <h6 class="card-title"><i class="fas fa-book-open"></i> Books Issued</h6>
                            <p class="card-text display-6"><?php echo count($issued_books); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Attendance by Course</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($attendance_result) > 0): ?>
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
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-chart-line"></i> Average Marks by Exam Type</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($marks_by_type) > 0): ?>
                                <div class="chart-container" style="position: relative; height: 300px;">
                                    <canvas id="marksChart"></canvas>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info mb-0">
                                    <i class="fas fa-info-circle"></i> No marks data available
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fee Status -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0"><i class="fas fa-dollar-sign"></i> Fee Status</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <h6>Total Fee</h6>
                                    <h3 class="text-primary">₹<?php echo number_format($fee_info['total_fee'] ?? 0, 2); ?></h3>
                                </div>
                                <div class="col-md-3">
                                    <h6>Amount Paid</h6>
                                    <h3 class="text-success">₹<?php echo number_format($fee_info['amount_paid'] ?? 0, 2); ?></h3>
                                </div>
                                <div class="col-md-3">
                                    <h6>Remaining</h6>
                                    <h3 class="text-danger">₹<?php echo number_format($fee_info['due_amount'] ?? 0, 2); ?></h3>
                                </div>
                                <div class="col-md-3">
                                    <h6>Payment Status</h6>
                                    <?php 
                                    $status = ($fee_info['due_amount'] ?? 0) == 0 ? 'Paid' : 'Pending';
                                    $badge_class = $status == 'Paid' ? 'bg-success' : 'bg-danger';
                                    ?>
                                    <h3><span class="badge <?php echo $badge_class; ?>"><?php echo $status; ?></span></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enrolled Courses -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-book"></i> Enrolled Courses (<?php echo count($courses); ?>)</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Course Code</th>
                                        <th>Course Name</th>
                                        <th>Faculty</th>
                                        <th>Department</th>
                                        <th>Semester</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($courses) > 0): ?>
                                        <?php foreach ($courses as $course): ?>
                                            <tr>
                                                <td><strong><?php echo $course['course_code']; ?></strong></td>
                                                <td><?php echo $course['course_name']; ?></td>
                                                <td><?php echo $course['faculty_name'] ?? 'N/A'; ?></td>
                                                <td><?php echo $course['dept_name']; ?></td>
                                                <td><span class="badge bg-primary"><?php echo $course['semester']; ?></span></td>
                                                <td>
                                                    <a href="view-marks.php?course=<?php echo $course['course_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-chart-line"></i> Marks
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">No courses enrolled yet</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Links & Books -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Links</h5>
                        </div>
                        <div class="card-body">
                            <a href="view-marks.php" class="btn btn-outline-primary btn-block mb-2">
                                <i class="fas fa-chart-line"></i> View Marks
                            </a>
                            <a href="view-attendance.php" class="btn btn-outline-info btn-block mb-2">
                                <i class="fas fa-clipboard-list"></i> View Attendance
                            </a>
                            <a href="library.php" class="btn btn-outline-warning btn-block mb-2">
                                <i class="fas fa-book"></i> Library
                            </a>
                            <a href="profile.php" class="btn btn-outline-success btn-block">
                                <i class="fas fa-user"></i> Profile
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-book-open"></i> Issued Books (<?php echo count($issued_books); ?>)</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($issued_books) > 0): ?>
                                <div class="list-group">
                                    <?php foreach (array_slice($issued_books, 0, 5) as $book): ?>
                                        <div class="list-group-item">
                                            <h6><?php echo $book['title']; ?></h6>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar"></i> Due: <?php echo date('d-M-Y', strtotime($book['due_date'])); ?>
                                                <?php 
                                                $days_left = (strtotime($book['due_date']) - time()) / 86400;
                                                if ($days_left < 0) {
                                                    echo ' <span class="badge bg-danger">Overdue</span>';
                                                } elseif ($days_left < 3) {
                                                    echo ' <span class="badge bg-warning">Due Soon</span>';
                                                }
                                                ?>
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted"><i class="fas fa-inbox"></i> No books issued</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
    <?php if (count($attendance_result) > 0): ?>
    // Attendance by Course - Bar Chart
    setTimeout(function() {
        const ctx1 = document.getElementById('attendanceChart');
        if (ctx1) {
            new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: <?php echo $course_names; ?>,
                    datasets: [{
                        label: 'Attendance %',
                        data: <?php echo $attendance_percent; ?>,
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
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        }
    }, 100);
    <?php endif; ?>

    <?php if (count($marks_by_type) > 0): ?>
    // Average Marks by Exam Type - Line Chart
    setTimeout(function() {
        const ctx2 = document.getElementById('marksChart');
        if (ctx2) {
            new Chart(ctx2, {
                type: 'line',
                data: {
                    labels: <?php echo $exam_types; ?>,
                    datasets: [{
                        label: 'Average Marks',
                        data: <?php echo $avg_marks; ?>,
                        borderColor: 'rgba(54, 162, 235, 1)',
                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: 'rgba(54, 162, 235, 1)',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5
                    }]
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
    <?php endif; ?>
</script>

<?php require_once('../../includes/footer.php'); ?>