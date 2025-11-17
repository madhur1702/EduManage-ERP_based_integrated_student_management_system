<?php
$page_title = 'Manage Books';
require_once('../../config/config.php');
require_once('../../includes/db.php');
require_once('../../includes/functions.php');
require_once('../../includes/header.php');

if (!hasRole('Librarian')) {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

global $conn;

$books = getAllLibraryBooks();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = isset($_POST['action']) ? sanitize($_POST['action']) : '';

    if ($action == 'add') {
        $title = sanitize($_POST['title']);
        $author = sanitize($_POST['author']);
        $isbn = sanitize($_POST['isbn']);
        $department = isset($_POST['department']) && $_POST['department'] !== '' ? (int)$_POST['department'] : null;
        $total_copies = (int)$_POST['total_copies'];
        $available_copies = $total_copies;

        $query = "INSERT INTO library_books (title, author, isbn, department, total_copies, available_copies) 
                  VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssiii", $title, $author, $isbn, $department, $total_copies, $available_copies);

        if ($stmt->execute()) {
            $success = "Book added successfully!";
            logActivity($_SESSION['user_id'], "Added new book: $title");
            $books = getAllLibraryBooks();
        } else {
            $error = "Failed to add book: " . $conn->error;
        }
    } elseif ($action == 'update') {
        $book_id = (int)$_POST['book_id'];
        $title = sanitize($_POST['title']);
        $author = sanitize($_POST['author']);
        $isbn = sanitize($_POST['isbn']);
        $total_copies = (int)$_POST['total_copies'];

        $query = "UPDATE library_books SET title = ?, author = ?, isbn = ?, total_copies = ? WHERE book_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssii", $title, $author, $isbn, $total_copies, $book_id);

        if ($stmt->execute()) {
            $success = "Book updated successfully!";
            logActivity($_SESSION['user_id'], "Updated book: $title");
            $books = getAllLibraryBooks();
        } else {
            $error = "Failed to update book: " . $conn->error;
        }
    } elseif ($action == 'delete') {
        $book_id = (int)$_POST['book_id'];
        $book = $conn->query("SELECT title FROM library_books WHERE book_id = $book_id")->fetch_assoc();

        $query = "DELETE FROM library_books WHERE book_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $book_id);

        if ($stmt->execute()) {
            $success = "Book deleted successfully!";
            logActivity($_SESSION['user_id'], "Deleted book: " . $book['title']);
            $books = getAllLibraryBooks();
        } else {
            $error = "Failed to delete book: " . $conn->error;
        }
    }
}

$depts = $conn->query("SELECT * FROM departments ORDER BY dept_name")->fetch_all(MYSQLI_ASSOC);
?>

<div class="d-flex">
    <?php require_once('../../includes/sidebar.php'); ?>
    
    <main class="flex-grow-1 p-4">
        <div class="container-fluid">
            <h1 class="mb-4"><i class="fas fa-book"></i> Manage Books</h1>

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

            <!-- Add Book Form -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-plus"></i> Add New Book</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="title" class="form-label">Book Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" placeholder="e.g., Introduction to Algorithms" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="author" class="form-label">Author <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="author" name="author" placeholder="e.g., Thomas Cormen" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="isbn" class="form-label">ISBN</label>
                                <input type="text" class="form-control" id="isbn" name="isbn" placeholder="e.g., 978-0262033848">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="department" class="form-label">Department</label>
                                <select class="form-control" id="department" name="department">
                                    <option value="">General (All Departments)</option>
                                    <?php foreach ($depts as $dept): ?>
                                        <option value="<?php echo $dept['dept_id']; ?>"><?php echo $dept['dept_name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="total_copies" class="form-label">Total Copies <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="total_copies" name="total_copies" min="1" value="1" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Book</button>
                    </form>
                </div>
            </div>

            <!-- Books List -->
            <div class="card">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list"></i> All Books (<span id="booksCount"><?php echo count($books); ?></span>)</h5>
                    <div class="d-flex gap-2 align-items-center">
                        <input type="text" class="form-control form-control-sm" id="liveSearch" style="width: 250px;" placeholder="Search by title or author...">
                        <button type="button" class="btn btn-light btn-sm" id="clearSearch" title="Clear Search" style="display: none;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
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
                                <th>Issued</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="booksTableBody">
                            <?php if (count($books) > 0): ?>
                                <?php foreach ($books as $book): ?>
                                    <tr class="book-row" data-title="<?php echo strtolower(htmlspecialchars($book['title'])); ?>" data-author="<?php echo strtolower(htmlspecialchars($book['author'])); ?>">
                                        <td><strong><?php echo htmlspecialchars(substr($book['title'], 0, 40)); ?><?php echo strlen($book['title']) > 40 ? '...' : ''; ?></strong></td>
                                        <td><?php echo htmlspecialchars($book['author']); ?></td>
                                        <td><?php echo htmlspecialchars($book['isbn']); ?></td>
                                        <td><span class="badge bg-info"><?php echo htmlspecialchars($book['dept_name'] ?? 'General'); ?></span></td>
                                        <td><?php echo $book['total_copies']; ?></td>
                                        <td><span class="badge bg-success"><?php echo $book['available_copies']; ?></span></td>
                                        <td><span class="badge bg-danger"><?php echo $book['total_copies'] - $book['available_copies']; ?></span></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $book['book_id']; ?>" title="Edit Book">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $book['book_id']; ?>" title="Delete Book">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- Edit Modal -->
                                    <div class="modal fade" id="editModal<?php echo $book['book_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header bg-warning text-white">
                                                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Book</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="action" value="update">
                                                        <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Book Title</label>
                                                            <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($book['title']); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Author</label>
                                                            <input type="text" class="form-control" name="author" value="<?php echo htmlspecialchars($book['author']); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">ISBN</label>
                                                            <input type="text" class="form-control" name="isbn" value="<?php echo htmlspecialchars($book['isbn']); ?>">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Total Copies</label>
                                                            <input type="number" class="form-control" name="total_copies" value="<?php echo $book['total_copies']; ?>" min="<?php echo $book['total_copies'] - $book['available_copies']; ?>" required>
                                                            <small class="text-muted">Note: <?php echo $book['total_copies'] - $book['available_copies']; ?> copies are currently issued</small>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-warning">
                                                            <i class="fas fa-save"></i> Update Book
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Delete Modal -->
                                    <div class="modal fade" id="deleteModal<?php echo $book['book_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Are you sure you want to delete <strong><?php echo htmlspecialchars($book['title']); ?></strong>?</p>
                                                    <?php if ($book['total_copies'] - $book['available_copies'] > 0): ?>
                                                        <p class="text-warning"><i class="fas fa-warning"></i> Warning: <?php echo $book['total_copies'] - $book['available_copies']; ?> copies are currently issued!</p>
                                                    <?php endif; ?>
                                                    <p class="text-danger"><i class="fas fa-warning"></i> This action cannot be undone!</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-danger">
                                                            <i class="fas fa-trash"></i> Delete Permanently
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                        No books found
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('liveSearch');
    const clearButton = document.getElementById('clearSearch');
    const tableRows = document.querySelectorAll('.book-row');
    const booksCount = document.getElementById('booksCount');
    const tbody = document.getElementById('booksTableBody');

    // Live search functionality
    searchInput.addEventListener('keyup', function() {
        const searchValue = this.value.toLowerCase().trim();
        let visibleCount = 0;

        tableRows.forEach(row => {
            const title = row.getAttribute('data-title');
            const author = row.getAttribute('data-author');

            if (searchValue === '') {
                row.style.display = '';
                visibleCount++;
            } else if (title.includes(searchValue) || author.includes(searchValue)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Update count and show/hide clear button
        booksCount.textContent = visibleCount;
        clearButton.style.display = searchValue !== '' ? 'block' : 'none';

        // Show "no results" message
        if (visibleCount === 0 && searchValue !== '') {
            if (!document.getElementById('noResultsRow')) {
                const noResultsRow = document.createElement('tr');
                noResultsRow.id = 'noResultsRow';
                noResultsRow.innerHTML = '<td colspan="8" class="text-center text-muted py-4"><i class="fas fa-search fa-2x mb-2 d-block"></i>No books found matching "' + searchValue + '"</td>';
                tbody.appendChild(noResultsRow);
            } else {
                document.getElementById('noResultsRow').innerHTML = '<td colspan="8" class="text-center text-muted py-4"><i class="fas fa-search fa-2x mb-2 d-block"></i>No books found matching "' + searchValue + '"</td>';
            }
        } else {
            const noResultsRow = document.getElementById('noResultsRow');
            if (noResultsRow) {
                noResultsRow.remove();
            }
        }
    });

    // Clear button functionality
    clearButton.addEventListener('click', function() {
        searchInput.value = '';
        searchInput.focus();
        searchInput.dispatchEvent(new Event('keyup'));
    });
});
</script>

<?php require_once('../../includes/footer.php'); ?>