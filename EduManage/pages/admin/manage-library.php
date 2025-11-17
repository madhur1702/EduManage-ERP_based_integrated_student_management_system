<?php
$page_title = 'Manage Library';
require_once('../../config/config.php');
require_once('../../includes/db.php');
require_once('../../includes/functions.php');
require_once('../../includes/header.php');

if (!hasRole('Admin')) {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

$books = getAllLibraryBooks();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = sanitize($_POST['action']);
    global $conn;

    if ($action == 'add') {
        $title = sanitize($_POST['title']);
        $author = sanitize($_POST['author']);
        $isbn = sanitize($_POST['isbn']);
        $department = isset($_POST['department']) ? (int)$_POST['department'] : null;
        $total_copies = (int)$_POST['total_copies'];
        $available_copies = $total_copies;

        $query = "INSERT INTO library_books (title, author, isbn, department, total_copies, available_copies) 
                  VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssii", $title, $author, $isbn, $department, $total_copies, $available_copies);

        if ($stmt->execute()) {
            $success = 'Book added successfully';
            logActivity($_SESSION['user_id'], "Added new book: $title");
            $books = getAllLibraryBooks();
        } else {
            $error = 'Failed to add book';
        }
    }
}

$depts = $conn->query("SELECT * FROM departments ORDER BY dept_name")->fetch_all(MYSQLI_ASSOC);
?>

<div class="d-flex">
    <?php require_once('../../includes/sidebar.php'); ?>
    
    <main class="flex-grow-1 p-4">
        <div class="container-fluid">
            <h1 class="mb-4"><i class="fas fa-library"></i> Manage Library</h1>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Add New Book</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="title" class="form-label">Book Title</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="author" class="form-label">Author</label>
                                <input type="text" class="form-control" id="author" name="author" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="isbn" class="form-label">ISBN</label>
                                <input type="text" class="form-control" id="isbn" name="isbn">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="department" class="form-label">Department</label>
                                <select class="form-control" id="department" name="department">
                                    <option value="">Select Department</option>
                                    <?php foreach ($depts as $dept): ?>
                                        <option value="<?php echo $dept['dept_id']; ?>"><?php echo $dept['dept_name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="total_copies" class="form-label">Total Copies</label>
                                <input type="number" class="form-control" id="total_copies" name="total_copies" min="1" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Book</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Library Books</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Title</th>
                                <th>Author</th>
                                <th>ISBN</th>
                                <th>Department</th>
                                <th>Total Copies</th>
                                <th>Available</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($books as $book): ?>
                                <tr>
                                    <td><strong><?php echo $book['title']; ?></strong></td>
                                    <td><?php echo $book['author']; ?></td>
                                    <td><?php echo $book['isbn']; ?></td>
                                    <td><?php echo $book['dept_name'] ?? 'N/A'; ?></td>
                                    <td><?php echo $book['total_copies']; ?></td>
                                    <td><span class="badge bg-success"><?php echo $book['available_copies']; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once('../../includes/footer.php'); ?>