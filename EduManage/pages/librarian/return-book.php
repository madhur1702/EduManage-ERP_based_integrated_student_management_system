<?php
$page_title = 'Return Book';
require_once('../../config/config.php');
require_once('../../includes/db.php');
require_once('../../includes/functions.php');
require_once('../../includes/header.php');

if (!hasRole('Librarian')) {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

global $conn;

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $issue_id = (int)$_POST['issue_id'];
    $return_date = sanitize($_POST['return_date']);

    // Get issue details
    $issue = $conn->query("SELECT * FROM library_issues WHERE issue_id = $issue_id")->fetch_assoc();

    if ($issue) {
        // Update issue record
        $query = "UPDATE library_issues SET return_date = ? WHERE issue_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $return_date, $issue_id);

        if ($stmt->execute()) {
            // Update available copies
            $book = $conn->query("SELECT available_copies FROM library_books WHERE book_id = " . $issue['book_id'])->fetch_assoc();
            $new_available = $book['available_copies'] + 1;
            $conn->query("UPDATE library_books SET available_copies = $new_available WHERE book_id = " . $issue['book_id']);

            $success = "Book returned successfully!";
            logActivity($_SESSION['user_id'], "Returned book from student ID: " . $issue['student_id']);
        } else {
            $error = "Failed to return book";
        }
    } else {
        $error = "Issue record not found";
    }
}

// Get currently issued books
$issued_books = $conn->query("
    SELECT li.*, lb.title, u.full_name, s.roll_no, DATEDIFF(NOW(), li.due_date) as days_overdue
    FROM library_issues li
    JOIN library_books lb ON li.book_id = lb.book_id
    JOIN students s ON li.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    WHERE li.return_date IS NULL
    ORDER BY li.issue_date DESC
")->fetch_all(MYSQLI_ASSOC);
?>

<div class="d-flex">
    <?php require_once('../../includes/sidebar.php'); ?>
    
    <main class="flex-grow-1 p-4">
        <div class="container-fluid">
            <h1 class="mb-4"><i class="fas fa-arrow-left"></i> Return Book</h1>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Currently Issued Books (<?php echo count($issued_books); ?>)</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Book Title</th>
                                <th>Roll No</th>
                                <th>Student Name</th>
                                <th>Issue Date</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($issued_books as $issued): ?>
                                <tr>
                                    <td><strong><?php echo substr($issued['title'], 0, 30); ?></strong></td>
                                    <td><?php echo $issued['roll_no']; ?></td>
                                    <td><?php echo $issued['full_name']; ?></td>
                                    <td><?php echo date('d-M-Y', strtotime($issued['issue_date'])); ?></td>
                                    <td><?php echo date('d-M-Y', strtotime($issued['due_date'])); ?></td>
                                    <td>
                                        <?php 
                                        $days_overdue = $issued['days_overdue'];
                                        if ($days_overdue < 0) {
                                            echo '<span class="badge bg-success">Active</span>';
                                        } else if ($days_overdue == 0) {
                                            echo '<span class="badge bg-warning">Due Today</span>';
                                        } else {
                                            echo '<span class="badge bg-danger">Overdue (' . $days_overdue . ' days)</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#returnModal<?php echo $issued['issue_id']; ?>">
                                            <i class="fas fa-check"></i> Return
                                        </button>
                                    </td>
                                </tr>

                                <!-- Return Modal -->
                                <div class="modal fade" id="returnModal<?php echo $issued['issue_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Return Book</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <div class="modal-body">
                                                    <input type="hidden" name="issue_id" value="<?php echo $issued['issue_id']; ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Book Title</label>
                                                        <input type="text" class="form-control" value="<?php echo $issued['title']; ?>" disabled>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Student Name</label>
                                                        <input type="text" class="form-control" value="<?php echo $issued['full_name']; ?>" disabled>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Return Date</label>
                                                        <input type="date" class="form-control" name="return_date" value="<?php echo date('Y-m-d'); ?>" required>
                                                    </div>
                                                    <?php if ($issued['days_overdue'] > 0): ?>
                                                        <div class="alert alert-danger">
                                                            <i class="fas fa-exclamation"></i> This book is overdue by <strong><?php echo $issued['days_overdue']; ?> days</strong>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-success">Confirm Return</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once('../../includes/footer.php'); ?>