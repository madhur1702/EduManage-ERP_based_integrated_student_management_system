<?php
$page_title = 'Reports';
require_once('../../config/config.php');
require_once('../../includes/db.php');
require_once('../../includes/functions.php');
require_once('../../includes/header.php');

if (!hasRole('SubAdmin')) {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

global $conn;

$report_type = isset($_GET['type']) ? sanitize($_GET['type']) : 'overview';

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

// Get enrollment by semester
$enrollment_by_semester = $conn->query("
    SELECT c.semester, COUNT(e.enrollment_id) as count 
    FROM courses c 
    LEFT JOIN enrollments e ON c.course_id = e.course_id 
    GROUP BY c.semester 
    ORDER BY c.semester
")->fetch_all(MYSQLI_ASSOC);

// Get top performing students
$top_students = $conn->query("
    SELECT u.full_name, s.roll_no, AVG((m.marks_obtained/m.max_marks)*100) as percentage
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    JOIN enrollments e ON s.student_id = e.student_id
    JOIN marks m ON e.enrollment_id = m.enrollment_id
    GROUP BY s.student_id, u.full_name, s.roll_no
    ORDER BY percentage DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Get course-wise enrollment
$course_enrollment = $conn->query("
    SELECT c.course_name, c.course_code, COUNT(e.enrollment_id) as total_students
    FROM courses c
    LEFT JOIN enrollments e ON c.course_id = e.course_id
    GROUP BY c.course_id, c.course_name, c.course_code
    ORDER BY total_students DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Get faculty course assignment
$faculty_courses = $conn->query("
    SELECT u.full_name, COUNT(c.course_id) as total_courses
    FROM faculty f
    JOIN users u ON f.user_id = u.user_id
    LEFT JOIN courses c ON f.faculty_id = c.faculty_id
    GROUP BY f.faculty_id, u.full_name
    ORDER BY total_courses DESC
")->fetch_all(MYSQLI_ASSOC);

// Get attendance statistics by department
$dept_attendance = $conn->query("
    SELECT d.dept_name,
           COUNT(*) as total_records,
           SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present,
           SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent
    FROM departments d
    LEFT JOIN users u ON d.dept_id = u.dept_id AND u.role_id = 4
    LEFT JOIN students s ON s.user_id = u.user_id
    LEFT JOIN enrollments e ON s.student_id = e.student_id
    LEFT JOIN attendance a ON e.enrollment_id = a.enrollment_id
    GROUP BY d.dept_id, d.dept_name
")->fetch_all(MYSQLI_ASSOC);

// Get marks distribution
$marks_distribution = $conn->query("
    SELECT 
        CASE 
            WHEN (marks_obtained/max_marks)*100 >= 90 THEN 'A (90-100%)'
            WHEN (marks_obtained/max_marks)*100 >= 80 THEN 'B (80-89%)'
            WHEN (marks_obtained/max_marks)*100 >= 70 THEN 'C (70-79%)'
            WHEN (marks_obtained/max_marks)*100 >= 60 THEN 'D (60-69%)'
            ELSE 'F (Below 60%)'
        END as grade,
        COUNT(*) as count
    FROM marks
    GROUP BY grade
    ORDER BY grade DESC
")->fetch_all(MYSQLI_ASSOC);
?>

<div class="d-flex">
    <?php require_once('../../includes/sidebar.php'); ?>
    
    <main class="flex-grow-1 p-4">
        <div class="container-fluid">
            <h1 class="mb-4"><i class="fas fa-chart-bar"></i> Reports</h1>

            <!-- Report Navigation -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Select Report Type</h5>
                </div>
                <div class="card-body">
                    <div class="btn-group flex-wrap" role="group">
                        <a href="?type=overview" class="btn <?php echo $report_type == 'overview' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="fas fa-chart-pie"></i> Overview
                        </a>
                        <a href="?type=students" class="btn <?php echo $report_type == 'students' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="fas fa-user-graduate"></i> Students
                        </a>
                        <a href="?type=faculty" class="btn <?php echo $report_type == 'faculty' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="fas fa-chalkboard-user"></i> Faculty
                        </a>
                        <a href="?type=courses" class="btn <?php echo $report_type == 'courses' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="fas fa-book"></i> Courses
                        </a>
                        <a href="?type=attendance" class="btn <?php echo $report_type == 'attendance' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="fas fa-clipboard-list"></i> Attendance
                        </a>
                        <a href="?type=marks" class="btn <?php echo $report_type == 'marks' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="fas fa-chart-line"></i> Marks
                        </a>
                    </div>
                </div>
            </div>

            <?php if ($report_type == 'overview'): ?>
                <!-- OVERVIEW REPORT -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <h6>Total Students</h6>
                                <h2><?php echo $total_students; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <h6>Total Faculty</h6>
                                <h2><?php echo $total_faculty; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <h6>Total Courses</h6>
                                <h2><?php echo $total_courses; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Students by Department</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container" style="position: relative; height: 300px;">
                                    <canvas id="overviewStudentChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Enrollment by Semester</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container" style="position: relative; height: 300px;">
                                    <canvas id="overviewEnrollmentChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($report_type == 'students'): ?>
                <!-- STUDENT REPORT -->
                <h3 class="mb-3"><i class="fas fa-user-graduate"></i> Student Report</h3>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">Students by Department</h5>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Department</th>
                                            <th>Count</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($dept_students as $dept): ?>
                                            <tr>
                                                <td><?php echo $dept['dept_name']; ?></td>
                                                <td><span class="badge bg-primary"><?php echo $dept['count']; ?></span></td>
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
                                <h5 class="mb-0">Top 10 Performing Students</h5>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 table-sm">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Roll No</th>
                                            <th>Name</th>
                                            <th>Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($top_students as $student): ?>
                                            <tr>
                                                <td><?php echo $student['roll_no']; ?></td>
                                                <td><?php echo substr($student['full_name'], 0, 20); ?></td>
                                                <td><span class="badge bg-success"><?php echo round($student['percentage'], 2); ?>%</span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($report_type == 'faculty'): ?>
                <!-- FACULTY REPORT -->
                <h3 class="mb-3"><i class="fas fa-chalkboard-user"></i> Faculty Report</h3>
                
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Faculty Course Assignments</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Faculty Name</th>
                                    <th>Total Courses Assigned</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($faculty_courses as $faculty): ?>
                                    <tr>
                                        <td><?php echo $faculty['full_name']; ?></td>
                                        <td><span class="badge bg-primary"><?php echo $faculty['total_courses']; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($report_type == 'courses'): ?>
                <!-- COURSES REPORT -->
                <h3 class="mb-3"><i class="fas fa-book"></i> Courses Report</h3>
                
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Top 10 Courses by Enrollment</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Course Code</th>
                                    <th>Course Name</th>
                                    <th>Total Students</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($course_enrollment as $course): ?>
                                    <tr>
                                        <td><strong><?php echo $course['course_code']; ?></strong></td>
                                        <td><?php echo $course['course_name']; ?></td>
                                        <td><span class="badge bg-primary"><?php echo $course['total_students']; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($report_type == 'attendance'): ?>
                <!-- ATTENDANCE REPORT -->
                <h3 class="mb-3"><i class="fas fa-clipboard-list"></i> Attendance Report by Department</h3>
                
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Attendance Statistics</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Department</th>
                                    <th>Total Records</th>
                                    <th>Present</th>
                                    <th>Absent</th>
                                    <th>Attendance %</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dept_attendance as $dept): ?>
                                    <tr>
                                        <td><?php echo $dept['dept_name']; ?></td>
                                        <td><?php echo $dept['total_records']; ?></td>
                                        <td><?php echo $dept['present']; ?></td>
                                        <td><?php echo $dept['absent']; ?></td>
                                        <td>
                                            <?php 
                                            $pct = $dept['total_records'] > 0 ? ($dept['present'] / $dept['total_records'] * 100) : 0;
                                            echo round($pct, 2) . '%';
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($report_type == 'marks'): ?>
                <!-- MARKS REPORT -->
                <h3 class="mb-3"><i class="fas fa-chart-line"></i> Marks Report</h3>
                
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Grade Distribution</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Grade</th>
                                    <th>Count</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_marks = array_sum(array_column($marks_distribution, 'count'));
                                foreach ($marks_distribution as $grade): 
                                ?>
                                    <tr>
                                        <td><?php echo $grade['grade']; ?></td>
                                        <td><?php echo $grade['count']; ?></td>
                                        <td>
                                            <?php 
                                            $pct = $total_marks > 0 ? ($grade['count'] / $total_marks * 100) : 0;
                                            echo round($pct, 2) . '%';
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Print Button -->
            <div class="mt-4">
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
    <?php if ($report_type == 'overview'): ?>
    setTimeout(function() {
        const ctx1 = document.getElementById('overviewStudentChart');
        if (ctx1) {
            new Chart(ctx1, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_column($dept_students, 'dept_name')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($dept_students, 'count')); ?>,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(54, 162, 235, 0.7)',
                            'rgba(75, 192, 192, 0.7)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(75, 192, 192, 1)'
                        ],
                        borderWidth: 2
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
        }

        const ctx2 = document.getElementById('overviewEnrollmentChart');
        if (ctx2) {
            new Chart(ctx2, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_map(function($s) { return 'Sem ' . $s['semester']; }, $enrollment_by_semester)); ?>,
                    datasets: [{
                        label: 'Enrollments',
                        data: <?php echo json_encode(array_column($enrollment_by_semester, 'count')); ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.7)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true } }
                }
            });
        }
    }, 100);
    <?php endif; ?>
</script>

<?php require_once('../../includes/footer.php'); ?>