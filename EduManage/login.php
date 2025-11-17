<?php
session_start();
require_once('config/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');

if (isLoggedIn()) {
    redirectByRole();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($_POST['username']);
    $password = sanitize($_POST['password']);

    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password';
    } else {
        global $conn;
        $query = "SELECT u.*, r.role_name FROM users u 
                  JOIN roles r ON u.role_id = r.role_id 
                  WHERE u.username = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            // For demo purpose, comparing plain passwords. In production, use password_verify()
            if ($password === $user['password_hash']) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role_name'];
                $_SESSION['dept_id'] = $user['dept_id'];
                
                logActivity($user['user_id'], 'User logged in');
                redirectByRole();
            } else {
                $error = 'Invalid password';
            }
        } else {
            $error = 'User not found';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Define the primary color variable for consistency */
        :root {
            --primary-color: #5b6df5; /* Matching the Login button/NavBar color */
            --primary-dark: #4753c1;
        }

        body {
            /* Changed to solid white background */
            background: #FFFFFF;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
        }
        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }
        .login-header {
            /* Kept primary color for the header inside the box */
            background: var(--primary-color);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .login-header h2 {
            margin: 0;
            font-weight: 600;
        }
        .login-body {
            padding: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-control {
            border: 1px solid #ddd;
            padding: 10px 15px;
            border-radius: 5px;
        }
        .form-control:focus {
            /* Updated focus color to primary */
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(91, 109, 245, 0.25);
        }
        .btn-login {
            /* Kept primary color for the login button */
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
            width: 100%;
            font-weight: 600;
            transition: background-color 0.2s ease-in-out;
        }
        .btn-login:hover {
            /* Use a slightly darker primary color on hover */
            background-color: var(--primary-dark);
            color: white;
            opacity: 1;
        }
        .alert {
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-graduation-cap fa-3x mb-2"></i>
            <h2><?php echo APP_NAME; ?></h2>
        </div>
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-login">Login</button>
            </form>
        </div>
    </div>
</body>
</html>