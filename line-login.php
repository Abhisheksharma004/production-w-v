<?php
session_start();

// Include database configuration
require_once 'config/database.php';

// Redirect if already logged in as line user
if (isset($_SESSION['line_id'])) {
    header("Location: line-dashboard.php");
    exit();
}

$error = "";

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $conn = getSQLSrvConnection();
        
        if ($conn === false) {
            $error = "Database connection failed";
        } else {
            $email = trim($_POST['email']);
            $pass = trim($_POST['password']);
            
            // Query to check line credentials
            $sql = "SELECT id, line_name, user_email, password, status FROM lines WHERE user_email = ?";
            $params = [$email];
            $stmt = sqlsrv_query($conn, $sql, $params);
            
            if ($stmt === false) {
                $error = "Database query failed";
            } else {
                $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                
                if ($row) {
                    // Check if line is active
                    if ($row['status'] !== 'Active') {
                        $error = "This line is currently inactive. Please contact administrator.";
                    } elseif ($pass === $row['password']) {
                        // Set session variables for line user
                        $_SESSION['line_id'] = $row['id'];
                        $_SESSION['line_name'] = $row['line_name'];
                        $_SESSION['line_email'] = $row['user_email'];
                        $_SESSION['user_type'] = 'line';
                        
                        // Redirect to line dashboard page
                        header("Location: line-dashboard.php");
                        exit();
                    } else {
                        $error = "Invalid email or password";
                    }
                } else {
                    $error = "Invalid email or password";
                }
                
                sqlsrv_free_stmt($stmt);
            }
        }
    } catch (Exception $e) {
        $error = "Login failed: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Line Login - Production Management System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Line Login</h1>
            <p>Production Management System</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Line User Id </label>
                <input type="text" id="email" name="email" 
                       placeholder="Enter your line user id" 
                       required autocomplete="email">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" 
                       placeholder="Enter your password" 
                       required autocomplete="current-password">
            </div>
            
            <button type="submit" class="btn-login">Login</button>
        </form>
        
        <div class="login-footer">
            <a href="index.php" style="color: #3b82f6; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
                <i class="fas fa-user-shield"></i>
                Admin Login
            </a>
        </div>
    </div>
</body>
</html>
