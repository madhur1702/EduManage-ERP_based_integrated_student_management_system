<?php
$page_title = 'Librarian Dashboard';
require_once('../../config/config.php');
require_once('../../includes/db.php');
require_once('../../includes/functions.php');
require_once('../../includes/header.php');

if (!hasRole('Librarian')) {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

global $conn;

// Get library statistics
$total_books = countStats('books');
$available_books = countStats('available_books');
$issued_books = countStats('issued_books');

// Get books by department
$books_by_dept = $conn->query("
    SELECT d.dept_name, COUNT(lb.book_id) as count, SUM(lb.available_copies) as available
    FROM library_books lb
    LEFT JOIN departments d ON lb.department = d.dept_id
    GROUP BY d.dept_id, d.dept_name
")->fetch_all(MYSQLI_ASSOC);

// Get recently issued books
$recent_issues = $conn->query("
    SELECT li.*, lb.title, u.full_name, s.roll_no
    FROM library_issues li
    JOIN library_books lb ON li.book_id = lb.book_id
    JOIN students s ON li.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    WHERE li.return_date IS NULL
    ORDER BY li.issue_date DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Get book issue statistics (last 7 days)
$issue_stats = $conn->query("
    SELECT DATE(issue_date) as date, COUNT(*) as count
    FROM library_issues
    WHERE issue_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(issue_date)
    ORDER BY date DESC
    LIMIT 7
")->fetch_all(MYSQLI_ASSOC);
$issue_stats = array_reverse($issue_stats);

// Get overdue books
$overdue_books = $conn->query("
    SELECT li.*, lb.title, u.full_name, s.roll_no, DATEDIFF(NOW(), li.due_date) as days_overdue
    FROM library_issues li
    JOIN library_books lb ON li.book_id = lb.book_id
    JOIN students s ON li.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    WHERE li.return_date IS NULL AND li.due_date < NOW()
    ORDER BY li.due_date ASC
")->fetch_all(MYSQLI_ASSOC);

// Prepare chart data
$dept_labels = json_encode(array_column($books_by_dept, 'dept_name'));
$dept_data = json_encode(array_column($books_by_dept, 'count'));

$dates = json_encode(array_column($issue_stats, 'date'));
$issue_counts = json_encode(array_column($issue_stats, 'count'));
?>

<div class="d-flex">
    <?php require_once('../../includes/sidebar.php'); ?>
    
    <main class="flex-grow-1 p-4">
        <div class="container-fluid">
            <h1 class="mb-4"><i class="fas fa-tachometer-alt"></i> Librarian Dashboard</h1>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-book"></i> Total Books</h5>
                            <p class="card-text display-4"><?php echo $total_books; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-check-circle"></i> Available</h5>
                            <p class="card-text display-4"><?php echo $available_books; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-danger">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-arrow-right"></i> Issued</h5>
                            <p class="card-text display-4"><?php echo $issued_books; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-exclamation"></i> Overdue</h5>
                            <p class="card-text display-4"><?php echo count($overdue_books); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Books by Department</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="position: relative; height: 300px;">
                                <canvas id="deptChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-chart-line"></i> Issues (Last 7 Days)</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="position: relative; height: 300px;">
                                <canvas id="issueChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Overdue Books Alert -->
            <?php if (count($overdue_books) > 0): ?>
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong><?php echo count($overdue_books); ?> overdue books!</strong> Please follow up with students.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Currently Issued Books -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-arrow-right"></i> Currently Issued Books (<?php echo count($recent_issues); ?>)</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Book Title</th>
                                        <th>Roll No</th>
                                        <th>Student Name</th>
                                        <th>Issue Date</th>
                                        <th>Due Date</th>
                                        <th>Days Left</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_issues as $issue): ?>
                                        <tr>
                                            <td><?php echo substr($issue['title'], 0, 30); ?></td>
                                            <td><?php echo $issue['roll_no']; ?></td>
                                            <td><?php echo $issue['full_name']; ?></td>
                                            <td><?php echo date('d-M-Y', strtotime($issue['issue_date'])); ?></td>
                                            <td><?php echo date('d-M-Y', strtotime($issue['due_date'])); ?></td>
                                            <td>
                                                <?php 
                                                $days_left = (strtotime($issue['due_date']) - time()) / 86400;
                                                echo ceil($days_left);
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $days_left = (strtotime($issue['due_date']) - time()) / 86400;
                                                if ($days_left < 0) {
                                                    echo '<span class="badge bg-danger">Overdue</span>';
                                                } else if ($days_left < 3) {
                                                    echo '<span class="badge bg-warning">Due Soon</span>';
                                                } else {
                                                    echo '<span class="badge bg-success">Active</span>';
                                                }
                                                ?>
                                            </td>
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
                            <a href="manage-books.php" class="btn btn-outline-primary btn-block mb-2">
                                <i class="fas fa-book"></i> Manage Books
                            </a>
                            <a href="issue-book.php" class="btn btn-outline-success btn-block mb-2">
                                <i class="fas fa-arrow-right"></i> Issue Book
                            </a>
                            <a href="return-book.php" class="btn btn-outline-info btn-block">
                                <i class="fas fa-arrow-left"></i> Return Book
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-info-circle"></i> Library Summary</h5>
                        </div>
                        <div class="card-body">
                            <p><i class="fas fa-circle text-primary"></i> Total Books: <strong><?php echo $total_books; ?></strong></p>
                            <p><i class="fas fa-circle text-success"></i> Available: <strong><?php echo $available_books; ?></strong></p>
                            <p><i class="fas fa-circle text-danger"></i> Issued: <strong><?php echo $issued_books; ?></strong></p>
                            <p><i class="fas fa-circle text-warning"></i> Overdue: <strong><?php echo count($overdue_books); ?></strong></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
    // Books by Department
    setTimeout(function() {
        const ctx1 = document.getElementById('deptChart');
        if (ctx1) {
            new Chart(ctx1, {
                type: 'doughnut',
                data: {
                    labels: <?php echo $dept_labels; ?>,
                    datasets: [{
                        data: <?php echo $dept_data; ?>,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(54, 162, 235, 0.7)',
                            'rgba(75, 192, 192, 0.7)',
                            'rgba(255, 206, 86, 0.7)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(255, 206, 86, 1)'
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
    }, 100);

    // Issues Last 7 Days
    setTimeout(function() {
        const ctx2 = document.getElementById('issueChart');
        if (ctx2) {
            new Chart(ctx2, {
                type: 'bar',
                data: {
                    labels: <?php echo $dates; ?>,
                    datasets: [{
                        label: 'Books Issued',
                        data: <?php echo $issue_counts; ?>,
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
                            ticks: { stepSize: 1 }
                        }
                    }
                }
            });
        }
    }, 100);
</script>

<?php require_once('../../includes/footer.php'); ?>