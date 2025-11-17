<?php
$page_title = 'View Marks';
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

// Get marks data
$marks_query = "
    SELECT m.*, c.course_name, c.course_code, e.semester
    FROM marks m
    JOIN enrollments e ON m.enrollment_id = e.enrollment_id
    JOIN courses c ON e.course_id = c.course_id
    WHERE e.student_id = ?
    ORDER BY m.exam_date DESC, c.course_name
";
$stmt = $conn->prepare($marks_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$all_marks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get course-wise statistics
$stats_query = "
    SELECT c.course_name, c.course_code, 
           COUNT(m.mark_id) as total_marks,
           AVG(m.marks_obtained) as avg_marks,
           MAX(m.marks_obtained) as max_marks,
           MIN(m.marks_obtained) as min_marks
    FROM marks m
    JOIN enrollments e ON m.enrollment_id = e.enrollment_id
    JOIN courses c ON e.course_id = c.course_id
    WHERE e.student_id = ?
    GROUP BY c.course_id, c.course_name, c.course_code
    ORDER BY c.course_name
";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$course_stats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get overall statistics
$overall_query = "
    SELECT 
        COUNT(m.mark_id) as total_marks,
        AVG(m.marks_obtained) as avg_marks,
        MAX(m.marks_obtained) as max_marks,
        MIN(m.marks_obtained) as min_marks,
        AVG((m.marks_obtained/m.max_marks)*100) as avg_percentage
    FROM marks m
    JOIN enrollments e ON m.enrollment_id = e.enrollment_id
    WHERE e.student_id = ?
";
$stmt = $conn->prepare($overall_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$overall_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>

<div class="d-flex">
    <?php require_once('../../includes/sidebar.php'); ?>
    
    <main class="flex-grow-1 p-4">
        <div class="container-fluid">
            <h1 class="mb-4"><i class="fas fa-chart-line"></i> My Marks</h1>

            <!-- Overall Statistics -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <h6>Total Marks</h6>
                            <h3><?php echo $overall_stats['total_marks'] ?? 0; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h6>Average Marks</h6>
                            <h3><?php echo number_format($overall_stats['avg_marks'] ?? 0, 2); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <h6>Best Marks</h6>
                            <h3><?php echo number_format($overall_stats['max_marks'] ?? 0, 2); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <h6>Average %</h6>
                            <h3><?php echo number_format($overall_stats['avg_percentage'] ?? 0, 2); ?>%</h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Course-wise Statistics -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Course-wise Statistics</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Course Code</th>
                                <th>Course Name</th>
                                <th>Total Marks</th>
                                <th>Average</th>
                                <th>Best</th>
                                <th>Lowest</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($course_stats) > 0): ?>
                                <?php foreach ($course_stats as $stat): ?>
                                    <tr>
                                        <td><?php echo $stat['course_code']; ?></td>
                                        <td><?php echo $stat['course_name']; ?></td>
                                        <td><?php echo $stat['total_marks']; ?></td>
                                        <td><?php echo number_format($stat['avg_marks'], 2); ?></td>
                                        <td><?php echo number_format($stat['max_marks'], 2); ?></td>
                                        <td><?php echo number_format($stat['min_marks'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No marks available yet</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- All Marks -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-list"></i> All Marks (<?php echo count($all_marks); ?>)</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Course Code</th>
                                <th>Course Name</th>
                                <th>Exam Type</th>
                                <th>Marks Obtained</th>
                                <th>Max Marks</th>
                                <th>Percentage</th>
                                <th>Grade</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($all_marks) > 0): ?>
                                <?php foreach ($all_marks as $mark): ?>
                                    <tr>
                                        <td><?php echo $mark['course_code']; ?></td>
                                        <td><?php echo $mark['course_name']; ?></td>
                                        <td><?php echo $mark['exam_type']; ?></td>
                                        <td><?php echo number_format($mark['marks_obtained'], 2); ?></td>
                                        <td><?php echo number_format($mark['max_marks'], 2); ?></td>
                                        <td>
                                            <?php 
                                            $percentage = ($mark['marks_obtained'] / $mark['max_marks']) * 100;
                                            $badge_class = $percentage >= 80 ? 'bg-success' : ($percentage >= 60 ? 'bg-warning' : 'bg-danger');
                                            echo '<span class="badge ' . $badge_class . '">' . number_format($percentage, 1) . '%</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($percentage >= 90) $grade = 'A+';
                                            elseif ($percentage >= 80) $grade = 'A';
                                            elseif ($percentage >= 70) $grade = 'B';
                                            elseif ($percentage >= 60) $grade = 'C';
                                            elseif ($percentage >= 50) $grade = 'D';
                                            else $grade = 'F';
                                            echo '<strong>' . $grade . '</strong>';
                                            ?>
                                        </td>
                                        <td><?php echo date('d-M-Y', strtotime($mark['exam_date'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                        No marks available yet
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once('../../includes/footer.php'); ?>