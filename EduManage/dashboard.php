<?php
include('includes/db.php');
include('includes/session.php');

switch ($_SESSION['role_name']) {
    case 'Admin':
        header("Location: pages/admin/dashboard.php");
        break;
    case 'SubAdmin':
        header("Location: pages/subadmin/dashboard.php");
        break;
    case 'Faculty':
        header("Location: pages/faculty/dashboard.php");
        break;
    case 'Student':
        header("Location: pages/student/dashboard.php");
        break;
    case 'Librarian':
        header("Location: pages/librarian/dashboard.php");
        break;
    case 'Accountant':
        header("Location: pages/accountant/dashboard.php");
        break;
    default:
        header("Location: login.php");
        break;
}
exit();
?>
