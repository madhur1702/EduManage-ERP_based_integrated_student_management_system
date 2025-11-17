<?php
$page_title = 'View Attendance';
require_once('../../config/config.php');
require_once('../../includes/db.php');
require_once('../../includes/functions.php');
require_once('../../includes/header.php');

if (!hasRole('Student')) {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

global $conn;

$student = getStudentInfo($_SESSION['user_id']);
$student_id = $student['student_id'];

// Get attendance summary by course
$summary_query = "
    SELECT c.course_code, c.course_name,
           COUNT(*) as total_classes,
           SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present,
           SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent
    FROM attendance a
    JOIN enrollments e ON a.enrollment_id = e.enrollment_id
    JOIN courses c ON e.course_id = c.course_id
    WHERE e.student_id = ?
    GROUP BY c.course_id, c.course_code, c.course_name
    ORDER BY c.course_name
";
$stmt = $conn->prepare($summary_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$attendance_summary = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get overall attendance
$overall_query = "
    SELECT 
        COUNT(*) as total_classes,
        SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent
    FROM attendance a
    JOIN enrollments e ON a.enrollment_id = e.enrollment_id
    WHERE e.student_id = ?
";
$stmt = $conn->prepare($overall_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$overall_attendance = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get detailed attendance records
$detail_query = "
    SELECT a.attendance_date, a.status, c.course_code, c.course_name
    FROM attendance a
    JOIN enrollments e ON a.enrollment_id = e.enrollment_id
    JOIN courses c ON e.course_id = c.course_id
    WHERE e.student_id = ?
    ORDER BY a.attendance_date DESC
    LIMIT 50
";
$stmt = $conn->prepare($detail_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$detailed_attendance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Prepare chart data
$course_names = json_encode(array_map(function($a) { return $a['course_code'] . ' - ' . substr($a['course_name'], 0, 15); }, $attendance_summary));
$present_data = json_encode(array_map(function($a) { return (int)$a['present']; }, $attendance_summary));
$absent_data = json_encode(array_map(function($a) { return (int)$a['absent']; }, $attendance_summary));
?>

<div class="d-flex">
    <?php require_once('../../includes/sidebar.php'); ?>
    
    <main class="flex-grow-1 p-4">
        <div class="container-fluid">
            <h1 class="mb-4"><i class="fas fa-clipboard-list"></i> My Attendance</h1>

            <!-- Overall Attendance -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <h6>Total Classes</h6>
                            <h3><?php echo $overall_attendance['total_classes'] ?? 0; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h6>Present</h6>
                            <h3><?php echo $overall_attendance['present'] ?? 0; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-danger">
                        <div class="card-body">
                            <h6>Absent</h6>
                            <h3><?php echo $overall_attendance['absent'] ?? 0; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <h6>Attendance %</h6>
                            <h3>
                                <?php 
                                $total = $overall_attendance['total_classes'] ?? 0;
                                $present = $overall_attendance['present'] ?? 0;
                                $percentage = $total > 0 ? round(($present / $total) * 100, 1) : 0;
                                echo $percentage . '%';
                                ?>
                            </h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chart -->
            <?php if (count($attendance_summary) > 0): ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Attendance by Course</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height: 350px;">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Course-wise Summary -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Course-wise Attendance</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Course Code</th>
                                <th>Course Name</th>
                                <th>Total Classes</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($attendance_summary) > 0): ?>
                                <?php foreach ($attendance_summary as $summary): ?>
                                    <tr>
                                        <td><?php echo $summary['course_code']; ?></td>
                                        <td><?php echo $summary['course_name']; ?></td>
                                        <td><?php echo $summary['total_classes']; ?></td>
                                        <td><span class="badge bg-success"><?php echo $summary['present']; ?></span></td>
                                        <td><span class="badge bg-danger"><?php echo $summary['absent']; ?></span></td>
                                        <td>
                                            <?php 
                                            $pct = ($summary['present'] / $summary['total_classes']) * 100;
                                            $badge_class = $pct >= 75 ? 'bg-success' : ($pct >= 60 ? 'bg-warning' : 'bg-danger');
                                            echo '<span class="badge ' . $badge_class . '">' . number_format($pct, 1) . '%</span>';
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No attendance records available</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Detailed Attendance Records -->
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Recent Attendance Records (Last 50)</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Course</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($detailed_attendance) > 0): ?>
                                <?php foreach ($detailed_attendance as $record): ?>
                                    <tr>
                                        <td><?php echo date('d-M-Y', strtotime($record['attendance_date'])); ?></td>
                                        <td><?php echo $record['course_code']; ?> - <?php echo $record['course_name']; ?></td>
                                        <td>
                                            <?php 
                                            if ($record['status'] == 'Present') {
                                                echo '<span class="badge bg-success"><i class="fas fa-check"></i> Present</span>';
                                            } else {
                                                echo '<span class="badge bg-danger"><i class="fas fa-times"></i> Absent</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted">No attendance records available</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
    <?php if (count($attendance_summary) > 0): ?>
    setTimeout(function() {
        const ctx = document.getElementById('attendanceChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo $course_names; ?>,
                    datasets: [
                        {
                            label: 'Present',
                            data: <?php echo $present_data; ?>,
                            backgroundColor: 'rgba(75, 192, 192, 0.7)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 2
                        },
                        {
                            label: 'Absent',
                            data: <?php echo $absent_data; ?>,
                            backgroundColor: 'rgba(255, 99, 132, 0.7)',
                            borderColor: 'rgba(255, 99, 132, 1)',
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
</script>

<?php require_once('../../includes/footer.php'); ?>