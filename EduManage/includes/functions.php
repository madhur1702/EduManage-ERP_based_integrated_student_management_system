<?php
// Helper Functions

// Sanitize Input
function sanitize($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check user role
function userRole() {
    return isset($_SESSION['role']) ? $_SESSION['role'] : null;
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/login.php');
        exit();
    }
}

// Check if user has specific role
function hasRole($role) {
    return userRole() == $role;
}

// Redirect based on role
function redirectByRole() {
    $role = userRole();
    switch ($role) {
        case 'Admin':
            header('Location: ' . APP_URL . '/pages/admin/dashboard.php');
            break;
        case 'SubAdmin':
            header('Location: ' . APP_URL . '/pages/subadmin/dashboard.php');
            break;
        case 'Faculty':
            header('Location: ' . APP_URL . '/pages/faculty/dashboard.php');
            break;
        case 'Student':
            header('Location: ' . APP_URL . '/pages/student/dashboard.php');
            break;
        case 'Librarian':
            header('Location: ' . APP_URL . '/pages/librarian/dashboard.php');
            break;
        case 'Accountant':
            header('Location: ' . APP_URL . '/pages/accountant/dashboard.php');
            break;
        default:
            header('Location: ' . APP_URL . '/login.php');
    }
    exit();
}

// Log Activity
function logActivity($user_id, $action) {
    global $conn;
    $action = sanitize($action);
    $query = "INSERT INTO activity_log (user_id, action) VALUES (?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $user_id, $action);
    return $stmt->execute();
}

// Get User Info
function getUserInfo($user_id) {
    global $conn;
    $query = "SELECT * FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Get Student Info
function getStudentInfo($user_id) {
    global $conn;
    $query = "SELECT s.*, u.full_name, u.email, u.phone, d.dept_name 
              FROM students s 
              JOIN users u ON s.user_id = u.user_id 
              LEFT JOIN departments d ON u.dept_id = d.dept_id 
              WHERE s.user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Get Faculty Info
function getFacultyInfo($user_id) {
    global $conn;
    $query = "SELECT f.*, u.full_name, u.email, u.phone, d.dept_name 
              FROM faculty f 
              JOIN users u ON f.user_id = u.user_id 
              LEFT JOIN departments d ON u.dept_id = d.dept_id 
              WHERE f.user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Get all courses
function getAllCourses() {
    global $conn;
    $query = "SELECT c.*, d.dept_name, u.full_name as faculty_name 
              FROM courses c 
              LEFT JOIN departments d ON c.dept_id = d.dept_id 
              LEFT JOIN faculty f ON c.faculty_id = f.faculty_id 
              LEFT JOIN users u ON f.user_id = u.user_id 
              ORDER BY c.course_name";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Get student courses
function getStudentCourses($student_id) {
    global $conn;
    $query = "SELECT c.*, d.dept_name, u.full_name as faculty_name 
              FROM enrollments e 
              JOIN courses c ON e.course_id = c.course_id 
              LEFT JOIN departments d ON c.dept_id = d.dept_id 
              LEFT JOIN faculty f ON c.faculty_id = f.faculty_id 
              LEFT JOIN users u ON f.user_id = u.user_id 
              WHERE e.student_id = ? 
              ORDER BY c.course_name";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get student attendance
function getStudentAttendance($student_id) {
    global $conn;
    $query = "SELECT a.*, c.course_name, e.semester 
              FROM attendance a 
              JOIN enrollments e ON a.enrollment_id = e.enrollment_id 
              JOIN courses c ON e.course_id = c.course_id 
              JOIN students s ON e.student_id = s.student_id 
              WHERE s.student_id = ? 
              ORDER BY a.attendance_date DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get student marks
function getStudentMarks($student_id) {
    global $conn;
    $query = "SELECT m.*, c.course_name, e.semester 
              FROM marks m 
              JOIN enrollments e ON m.enrollment_id = e.enrollment_id 
              JOIN courses c ON e.course_id = c.course_id 
              JOIN students s ON e.student_id = s.student_id 
              WHERE s.student_id = ? 
              ORDER BY m.exam_date DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get student fee info
function getStudentFeeInfo($student_id) {
    global $conn;
    $query = "SELECT * FROM fees WHERE student_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Get all students
function getAllStudents() {
    global $conn;
    $query = "SELECT s.*, u.full_name, u.email, u.phone, d.dept_name 
              FROM students s 
              JOIN users u ON s.user_id = u.user_id 
              LEFT JOIN departments d ON u.dept_id = d.dept_id 
              ORDER BY u.full_name";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Get all faculty
function getAllFaculty() {
    global $conn;
    $query = "SELECT f.*, u.full_name, u.email, u.phone, d.dept_name 
              FROM faculty f 
              JOIN users u ON f.user_id = u.user_id 
              LEFT JOIN departments d ON u.dept_id = d.dept_id 
              ORDER BY u.full_name";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Get all users
function getAllUsers() {
    global $conn;
    $query = "SELECT u.*, r.role_name, d.dept_name 
              FROM users u 
              LEFT JOIN roles r ON u.role_id = r.role_id 
              LEFT JOIN departments d ON u.dept_id = d.dept_id 
              ORDER BY u.full_name";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Get all library books
function getAllLibraryBooks() {
    global $conn;
    $query = "SELECT lb.*, d.dept_name 
              FROM library_books lb 
              LEFT JOIN departments d ON lb.department = d.dept_id 
              ORDER BY lb.title";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Get issued books by student
function getIssuedBooksByStudent($student_id) {
    global $conn;
    $query = "SELECT li.*, lb.title, lb.author, lb.isbn 
              FROM library_issues li 
              JOIN library_books lb ON li.book_id = lb.book_id 
              WHERE li.student_id = ? AND li.return_date IS NULL 
              ORDER BY li.issue_date DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Count dashboard statistics
function countStats($type, $param = null) {
    global $conn;
    
    switch ($type) {
        case 'students':
            $query = "SELECT COUNT(*) as count FROM students";
            break;
        case 'faculty':
            $query = "SELECT COUNT(*) as count FROM faculty";
            break;
        case 'courses':
            $query = "SELECT COUNT(*) as count FROM courses";
            break;
        case 'books':
            $query = "SELECT COUNT(*) as count FROM library_books";
            break;
        case 'available_books':
            $query = "SELECT SUM(available_copies) as count FROM library_books";
            break;
        case 'issued_books':
            $query = "SELECT COUNT(*) as count FROM library_issues WHERE return_date IS NULL";
            break;
        case 'attendance':
            $query = "SELECT COUNT(*) as count FROM attendance WHERE status = 'Present'";
            break;
        default:
            return 0;
    }
    
    $result = $conn->query($query);
    $data = $result->fetch_assoc();
    return $data['count'] ?? 0;
}
?>