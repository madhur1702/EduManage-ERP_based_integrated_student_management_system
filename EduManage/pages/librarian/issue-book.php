<?php
$page_title = 'Issue Book';
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
    if (isset($_POST['action']) && $_POST['action'] == 'issue') {
        $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
        $book_id = isset($_POST['book_id']) ? (int)$_POST['book_id'] : 0;
        $issue_date = isset($_POST['issue_date']) ? sanitize($_POST['issue_date']) : '';
        $due_date = isset($_POST['due_date']) ? sanitize($_POST['due_date']) : '';

        // Validation
        if (!$student_id) {
            $error = "Please select a student";
        } elseif (!$book_id) {
            $error = "Please select a book";
        } elseif (!$issue_date) {
            $error = "Please select issue date";
        } elseif (!$due_date) {
            $error = "Please select due date";
        } elseif (strtotime($due_date) <= strtotime($issue_date)) {
            $error = "Due date must be after issue date";
        } else {
            // Check if book exists and is available
            $book = $conn->query("SELECT * FROM library_books WHERE book_id = $book_id")->fetch_assoc();
            
            if (!$book) {
                $error = "Book not found";
            } elseif ($book['available_copies'] <= 0) {
                $error = "This book is not available for issue";
            } else {
                // Check if student already has this book issued
                $existing = $conn->query("SELECT * FROM library_issues WHERE student_id = $student_id AND book_id = $book_id AND return_date IS NULL")->fetch_assoc();
                
                if ($existing) {
                    $error = "Student already has this book issued";
                } else {
                    // Insert issue record
                    $query = "INSERT INTO library_issues (book_id, student_id, issue_date, due_date) VALUES (?, ?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    
                    if ($stmt) {
                        $stmt->bind_param("iiss", $book_id, $student_id, $issue_date, $due_date);

                        if ($stmt->execute()) {
                            // Update available copies
                            $new_available = $book['available_copies'] - 1;
                            $conn->query("UPDATE library_books SET available_copies = $new_available WHERE book_id = $book_id");

                            $success = "Book issued successfully to student!";
                            logActivity($_SESSION['user_id'], "Issued book: " . $book['title'] . " to student ID: $student_id");
                            
                            // Reset form
                            $_POST = array();
                        } else {
                            $error = "Failed to issue book: " . $stmt->error;
                        }
                        $stmt->close();
                    } else {
                        $error = "Database error: " . $conn->error;
                    }
                }
            }
        }
    }
}

// Get all students
$students_result = $conn->query("
    SELECT s.student_id, s.roll_no, u.full_name, u.email 
    FROM students s 
    JOIN users u ON s.user_id = u.user_id 
    ORDER BY u.full_name
");
$students = $students_result ? $students_result->fetch_all(MYSQLI_ASSOC) : array();

// Get available books
$books = getAllLibraryBooks();
$available_books = array_filter($books, function($b) { return $b['available_copies'] > 0; });
$available_books = array_values($available_books); // Re-index array

// Get total counts
$total_students = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
$issues_today = $conn->query("SELECT COUNT(*) as count FROM library_issues WHERE DATE(issue_date) = CURDATE()")->fetch_assoc()['count'];
?>

<div class="d-flex">
    <?php require_once('../../includes/sidebar.php'); ?>
    
    <main class="flex-grow-1 p-4">
        <div class="container-fluid">
            <h1 class="mb-4"><i class="fas fa-arrow-right"></i> Issue Book</h1>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-plus-circle"></i> Issue New Book</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" id="issueForm">
                                <input type="hidden" name="action" value="issue">

                                <!-- Student Search -->
                                <div class="mb-4">
                                    <label for="studentSearch" class="form-label">
                                        <i class="fas fa-user-graduate"></i> Search Student <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="studentSearch" placeholder="Search by Roll No, Name or Email..." autocomplete="off">
                                        <button type="button" class="btn btn-outline-secondary" onclick="clearStudent()" id="clearStudentBtn">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <div id="studentResults" class="list-group mt-2" style="max-height: 250px; overflow-y: auto; display: none; position: absolute; width: 100%; z-index: 1000;">
                                    </div>
                                    <input type="hidden" id="student_id" name="student_id">
                                    <div id="selectedStudent" class="alert alert-info mt-3" style="display: none;">
                                        <i class="fas fa-check-circle"></i> <strong>Selected:</strong> <span id="studentName"></span> (Roll: <span id="studentRoll"></span>)
                                    </div>
                                </div>

                                <!-- Book Search -->
                                <div class="mb-4">
                                    <label for="bookSearch" class="form-label">
                                        <i class="fas fa-book"></i> Search Book <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="bookSearch" placeholder="Search by Title, Author or ISBN..." autocomplete="off">
                                        <button type="button" class="btn btn-outline-secondary" onclick="clearBook()" id="clearBookBtn">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <div id="bookResults" class="list-group mt-2" style="max-height: 250px; overflow-y: auto; display: none; position: absolute; width: 100%; z-index: 1000;">
                                    </div>
                                    <input type="hidden" id="book_id" name="book_id">
                                    <div id="selectedBook" class="alert alert-info mt-3" style="display: none;">
                                        <i class="fas fa-check-circle"></i> <strong>Selected:</strong> <span id="bookTitle"></span><br>
                                        <small class="text-muted">Available: <span id="bookAvailable" class="badge bg-success"></span> copies</small>
                                    </div>
                                </div>

                                <!-- Dates Section -->
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="issue_date" class="form-label">
                                            <i class="fas fa-calendar-alt"></i> Issue Date <span class="text-danger">*</span>
                                        </label>
                                        <input type="date" class="form-control" id="issue_date" name="issue_date" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="due_date" class="form-label">
                                            <i class="fas fa-calendar-check"></i> Due Date <span class="text-danger">*</span>
                                        </label>
                                        <input type="date" class="form-control" id="due_date" name="due_date" value="<?php echo date('Y-m-d', strtotime('+15 days')); ?>" required>
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg" id="issueBtn">
                                        <i class="fas fa-check"></i> Issue Book
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-info-circle"></i> Quick Stats</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-4">
                                <p class="text-muted mb-1"><i class="fas fa-book"></i> Available Books</p>
                                <p class="display-6 text-primary"><?php echo count($available_books); ?></p>
                            </div>
                            
                            <hr>
                            
                            <div class="mb-4">
                                <p class="text-muted mb-1"><i class="fas fa-user-graduate"></i> Total Students</p>
                                <p class="display-6 text-success"><?php echo $total_students; ?></p>
                            </div>

                            <hr>

                            <div class="mb-4">
                                <p class="text-muted mb-1"><i class="fas fa-arrow-right"></i> Issues Today</p>
                                <p class="display-6 text-info"><?php echo $issues_today; ?></p>
                            </div>

                            <hr>

                            <div class="alert alert-warning">
                                <i class="fas fa-lightbulb"></i> <strong>Tips:</strong>
                                <ul class="small mb-0 mt-2">
                                    <li>Default due date is 15 days</li>
                                    <li>Type to search students/books</li>
                                    <li>Or click books list below</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Available Books List -->
            <div class="card shadow-sm mt-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-book"></i> Available Books (<?php echo count($available_books); ?>)</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Title</th>
                                <th>Author</th>
                                <th>ISBN</th>
                                <th>Available</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($available_books as $book): ?>
                                <tr onclick="quickSelectBook(<?php echo htmlspecialchars(json_encode($book), ENT_QUOTES, 'UTF-8'); ?>)" style="cursor: pointer; transition: background-color 0.2s;">
                                    <td><strong><?php echo substr($book['title'], 0, 35); ?></strong></td>
                                    <td><?php echo substr($book['author'], 0, 25); ?></td>
                                    <td><small><?php echo $book['isbn']; ?></small></td>
                                    <td><span class="badge bg-success"><?php echo $book['available_copies']; ?></span></td>
                                    <td><?php echo $book['total_copies']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    // Data
    const studentsData = <?php echo json_encode($students); ?>;
    const booksData = <?php echo json_encode($available_books); ?>;

    // Student Search Handler
    document.getElementById('studentSearch').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        const resultsDiv = document.getElementById('studentResults');
        
        if (searchTerm.length < 1) {
            resultsDiv.style.display = 'none';
            return;
        }

        const filtered = studentsData.filter(s => 
            s.full_name.toLowerCase().includes(searchTerm) ||
            s.roll_no.toLowerCase().includes(searchTerm) ||
            s.email.toLowerCase().includes(searchTerm)
        );

        if (filtered.length === 0) {
            resultsDiv.innerHTML = '<div class="list-group-item text-muted"><i class="fas fa-times"></i> No students found</div>';
            resultsDiv.style.display = 'block';
            return;
        }

        resultsDiv.innerHTML = filtered.map(s => `
            <button type="button" class="list-group-item list-group-item-action" onclick="selectStudent(${s.student_id}, '${escapeHtml(s.full_name)}', '${s.roll_no}'); return false;">
                <strong>${s.full_name}</strong><br>
                <small class="text-muted">Roll: ${s.roll_no} | ${s.email}</small>
            </button>
        `).join('');
        resultsDiv.style.display = 'block';
    });

    // Book Search Handler
    document.getElementById('bookSearch').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        const resultsDiv = document.getElementById('bookResults');
        
        if (searchTerm.length < 1) {
            resultsDiv.style.display = 'none';
            return;
        }

        const filtered = booksData.filter(b => 
            b.title.toLowerCase().includes(searchTerm) ||
            b.author.toLowerCase().includes(searchTerm) ||
            (b.isbn && b.isbn.toLowerCase().includes(searchTerm))
        );

        if (filtered.length === 0) {
            resultsDiv.innerHTML = '<div class="list-group-item text-muted"><i class="fas fa-times"></i> No books found</div>';
            resultsDiv.style.display = 'block';
            return;
        }

        resultsDiv.innerHTML = filtered.map(b => `
            <button type="button" class="list-group-item list-group-item-action" onclick="selectBook(${b.book_id}, '${escapeHtml(b.title)}', ${b.available_copies}); return false;">
                <strong>${b.title}</strong><br>
                <small class="text-muted">Author: ${b.author} | Available: <span class="badge bg-success">${b.available_copies}</span></small>
            </button>
        `).join('');
        resultsDiv.style.display = 'block';
    });

    // Select Student
    function selectStudent(id, name, roll) {
        document.getElementById('student_id').value = id;
        document.getElementById('studentSearch').value = name + ' (' + roll + ')';
        document.getElementById('studentResults').style.display = 'none';
        document.getElementById('selectedStudent').style.display = 'block';
        document.getElementById('studentName').textContent = name;
        document.getElementById('studentRoll').textContent = roll;
    }

    // Select Book
    function selectBook(id, title, available) {
        document.getElementById('book_id').value = id;
        document.getElementById('bookSearch').value = title;
        document.getElementById('bookResults').style.display = 'none';
        document.getElementById('selectedBook').style.display = 'block';
        document.getElementById('bookTitle').textContent = title;
        document.getElementById('bookAvailable').textContent = available;
    }

    // Quick select from table
    function quickSelectBook(book) {
        selectBook(book.book_id, book.title, book.available_copies);
    }

    // Clear Student
    function clearStudent() {
        document.getElementById('student_id').value = '';
        document.getElementById('studentSearch').value = '';
        document.getElementById('studentResults').style.display = 'none';
        document.getElementById('selectedStudent').style.display = 'none';
    }

    // Clear Book
    function clearBook() {
        document.getElementById('book_id').value = '';
        document.getElementById('bookSearch').value = '';
        document.getElementById('bookResults').style.display = 'none';
        document.getElementById('selectedBook').style.display = 'none';
    }

    // HTML Escape
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
        const studentSearch = document.getElementById('studentSearch');
        const studentResults = document.getElementById('studentResults');
        const bookSearch = document.getElementById('bookSearch');
        const bookResults = document.getElementById('bookResults');

        if (!event.target.closest('.input-group:first-of-type') && !event.target.matches('#studentSearch') && !event.target.closest('#studentResults')) {
            studentResults.style.display = 'none';
        }

        if (!event.target.closest('.input-group:last-of-type') && !event.target.matches('#bookSearch') && !event.target.closest('#bookResults')) {
            bookResults.style.display = 'none';
        }
    });
</script>

<?php require_once('../../includes/footer.php'); ?>