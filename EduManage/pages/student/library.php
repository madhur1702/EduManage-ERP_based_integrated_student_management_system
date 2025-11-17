<?php
$page_title = 'Library';
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

// Get issued books
$issued_books = getIssuedBooksByStudent($student_id);

// Get available books
$available_query = "
    SELECT * FROM library_books 
    WHERE available_copies > 0
    ORDER BY title ASC
";
$available_result = $conn->query($available_query);

if (!$available_result) {
    $available_books = array();
    $error = "Database Error: " . $conn->error;
} else {
    $available_books = $available_result->fetch_all(MYSQLI_ASSOC);
}

// Get library statistics
$stats_query = "
    SELECT 
        COUNT(DISTINCT lb.book_id) as total_books,
        COALESCE(SUM(lb.available_copies), 0) as available,
        COUNT(DISTINCT CASE WHEN li.return_date IS NULL THEN li.issue_id END) as issued_count
    FROM library_books lb
    LEFT JOIN library_issues li ON lb.book_id = li.book_id
";

$stats_result = $conn->query($stats_query);

if (!$stats_result) {
    $stats = array(
        'total_books' => 0,
        'available' => 0,
        'issued_count' => 0
    );
} else {
    $stats = $stats_result->fetch_assoc();
}
?>

<div class="d-flex">
    <?php require_once('../../includes/sidebar.php'); ?>
    
    <main class="flex-grow-1 p-4">
        <div class="container-fluid">
            <h1 class="mb-4"><i class="fas fa-book"></i> Library</h1>

            <?php if (isset($error)): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Library Stats -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <h6><i class="fas fa-book"></i> Total Books</h6>
                            <h3><?php echo $stats['total_books'] ?? 0; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h6><i class="fas fa-check-circle"></i> Available</h6>
                            <h3><?php echo $stats['available'] ?? 0; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <h6><i class="fas fa-book-open"></i> My Issues</h6>
                            <h3><?php echo count($issued_books); ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- My Issued Books -->
            <div class="card mb-4">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0"><i class="fas fa-book-open"></i> My Issued Books (<?php echo count($issued_books); ?>)</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Book Title</th>
                                <th>Author</th>
                                <th>Issue Date</th>
                                <th>Due Date</th>
                                <th>Days Left</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($issued_books) > 0): ?>
                                <?php foreach ($issued_books as $book): ?>
                                    <tr>
                                        <td><strong><?php echo $book['title']; ?></strong></td>
                                        <td><?php echo $book['author']; ?></td>
                                        <td><?php echo date('d-M-Y', strtotime($book['issue_date'])); ?></td>
                                        <td><?php echo date('d-M-Y', strtotime($book['due_date'])); ?></td>
                                        <td>
                                            <?php 
                                            $days_left = (strtotime($book['due_date']) - time()) / 86400;
                                            echo ceil($days_left);
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $days_left = (strtotime($book['due_date']) - time()) / 86400;
                                            if ($days_left < 0) {
                                                echo '<span class="badge bg-danger"><i class="fas fa-exclamation"></i> Overdue</span>';
                                            } else if ($days_left < 3) {
                                                echo '<span class="badge bg-warning"><i class="fas fa-clock"></i> Due Soon</span>';
                                            } else {
                                                echo '<span class="badge bg-success"><i class="fas fa-check"></i> Active</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox"></i> No books issued
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Available Books -->
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-book"></i> Available Books (<?php echo count($available_books); ?>)</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Title</th>
                                <th>Author</th>
                                <th>ISBN</th>
                                <th>Department</th>
                                <th>Available</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($available_books) > 0): ?>
                                <?php foreach (array_slice($available_books, 0, 25) as $book): ?>
                                    <tr>
                                        <td><strong><?php echo substr($book['title'], 0, 40); ?></strong></td>
                                        <td><?php echo substr($book['author'], 0, 30); ?></td>
                                        <td><?php echo $book['isbn'] ?? 'N/A'; ?></td>
                                        <td>
                                            <?php 
                                            // Get department name if department ID exists
                                            if ($book['department']) {
                                                $dept_query = "SELECT dept_name FROM departments WHERE dept_id = ?";
                                                $stmt = $conn->prepare($dept_query);
                                                $stmt->bind_param("i", $book['department']);
                                                $stmt->execute();
                                                $dept_result = $stmt->get_result()->fetch_assoc();
                                                $stmt->close();
                                                echo $dept_result['dept_name'] ?? 'General';
                                            } else {
                                                echo 'General';
                                            }
                                            ?>
                                        </td>
                                        <td><span class="badge bg-success"><?php echo $book['available_copies']; ?></span></td>
                                        <td><?php echo $book['total_copies']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox"></i> No books available currently
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (count($available_books) > 25): ?>
                    <div class="card-footer text-muted text-center">
                        <small>Showing 25 of <?php echo count($available_books); ?> available books</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<?php require_once('../../includes/footer.php'); ?>