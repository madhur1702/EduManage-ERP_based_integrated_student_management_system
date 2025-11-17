<?php
$role = userRole();
?>
<div class="sidebar bg-light">
    <nav class="nav flex-column">
        <?php if ($role == 'Admin'): ?>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/pages/admin/dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'view_students.php' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/pages/admin/view_student.php">
                <i class="fas fa-search-plus"></i> View Students
            </a>
            <a class="nav-link" href="<?php echo APP_URL; ?>/pages/admin/manage-users.php">
                <i class="fas fa-users"></i> Manage Users
            </a>
            <a class="nav-link" href="<?php echo APP_URL; ?>/pages/admin/manage-courses.php">
                <i class="fas fa-book"></i> Manage Courses
            </a>
            <a class="nav-link" href="<?php echo APP_URL; ?>/pages/admin/manage-library.php">
                <i class="fas fa-book-open"></i> Library Management
            </a>
            <a class="nav-link" href="<?php echo APP_URL; ?>/pages/admin/manage-fees.php">
                <i class="fas fa-dollar-sign"></i> Manage Fees
            </a>
            <a class="nav-link" href="<?php echo APP_URL; ?>/pages/admin/reports.php">
                <i class="fas fa-chart-bar"></i> Reports
            </a>

        <?php elseif ($role == 'SubAdmin'): ?>
            <a class="nav-link" href="<?php echo APP_URL; ?>/pages/subadmin/dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a class="nav-link" href="<?php echo APP_URL; ?>/pages/subadmin/manage-students.php">
                <i class="fas fa-user-graduate"></i> Manage Students
            </a>
            <a class="nav-link" href="<?php echo APP_URL; ?>/pages/subadmin/manage-faculty.php">
                <i class="fas fa-chalkboard-user"></i> Manage Faculty
            </a>
            <a class="nav-link" href="<?php echo APP_URL; ?>/pages/subadmin/view-reports.php">
                <i class="fas fa-chart-bar"></i> Reports
            </a>

        <?php elseif ($role == 'Faculty'): ?>
            <a class="nav-link" href="<?php echo APP_URL; ?>/pages/faculty/dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a class="nav-link" href="<?php echo APP_URL; ?>/pages/faculty/manage-attendance.php">
                <i class="fas fa-clipboard-list"></i> Mark Attendance
            </a>
            <a class="nav-link" href="<?php echo APP_URL; ?>/pages/faculty/manage-marks.php">
                <i class="fas fa-pen-alt"></i> Manage Marks
            </a>
            <a class="nav-link" href="<?php echo APP_URL; ?>/pages/faculty/profile.php">
                <i class="fas fa-user"></i> Profile
            </a>

        <?php elseif ($role == 'Student'): ?>
            <a class="nav-link" href="<?php echo APP_URL; ?>/pages/student/dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a class="nav-link" href="<?php echo APP_URL; ?>/pages/student/view-marks.php">
                <i class="fas fa-chart-line"></i> My Marks
            </a>
            <a class="nav-link" href="<?php echo APP_URL; ?>/pages/student/view-attendance.php">
                <i class="fas fa-clipboard-list"></i> My Attendance
            </a>
            <a class="nav-link" href="<?php echo APP_URL; ?>/pages/student/library.php">
                <i class="fas fa-book"></i> Library
            </a>
            <a class="nav-link" href="<?php echo APP_URL; ?>/pages/student/profile.php">
                <i class="fas fa-user"></i> Profile
            </a>

        <?php elseif ($role == 'Librarian'): ?>
            <a class="nav-link" href="<?php echo APP_URL; ?>/pages/librarian/dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a class="nav-link" href="<?php echo APP_URL; ?>/pages/librarian/manage-books.php">
                <i class="fas fa-book"></i> Manage Books
            </a>
            <a class="nav-link" href="<?php echo APP_URL; ?>/pages/librarian/issue-book.php">
                <i class="fas fa-arrow-right"></i> Issue Book
            </a>
            <a class="nav-link" href="<?php echo APP_URL; ?>/pages/librarian/return-book.php">
                <i class="fas fa-arrow-left"></i> Return Book
            </a>

        <?php elseif ($role == 'Accountant'): ?>
            <a class="nav-link" href="<?php echo APP_URL; ?>/pages/accountant/dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a class="nav-link" href="<?php echo APP_URL; ?>/pages/accountant/manage-fees.php">
                <i class="fas fa-dollar-sign"></i> Manage Fees
            </a>
            <a class="nav-link" href="<?php echo APP_URL; ?>/pages/accountant/fee-report.php">
                <i class="fas fa-chart-bar"></i> Fee Report
            </a>
            <a class="nav-link" href="<?php echo APP_URL; ?>/pages/accountant/profile.php">
                <i class="fas fa-user"></i> Profile
            </a>
        <?php endif; ?>
    </nav>
</div>