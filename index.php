<?php
session_start();

// Include database configuration
require_once 'config/database.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
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
            $email = trim($_POST['username']);
            $pass = trim($_POST['password']);
            
            // Query to check user credentials using SQL Server
            $sql = "SELECT id, username, email, password, role FROM users WHERE email = ?";
            $params = [$email];
            $stmt = sqlsrv_query($conn, $sql, $params);
            
            if ($stmt === false) {
                $error = "Database query failed";
            } else {
                $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                
                if ($row) {
                    // Direct password comparison (not hashed as per requirement)
                    if ($pass === $row['password']) {
                        // Set session variables
                        $_SESSION['user_id'] = $row['id'];
                        $_SESSION['username'] = $row['username'];
                        $_SESSION['email'] = $row['email'];
                        $_SESSION['role'] = $row['role'];
                        
                        // Redirect to dashboard
                        header("Location: dashboard.php");
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
        $error = "An error occurred. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Production Management System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Welcome Back</h1>
            <p>Production Management System</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="form-group">
                <label for="username">Email Address</label>
                <input 
                    type="email" 
                    id="username" 
                    name="username" 
                    required 
                    autocomplete="email"
                    placeholder="production@viros.com"
                    value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                >
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required 
                    autocomplete="current-password"
                >
            </div>
            
            <button type="submit" class="btn-login">Login</button>
        </form>
        
        <div class="login-footer">
            <a href="line-login.php" style="color: #3b82f6; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
                <i class="fas fa-industry"></i>
                Production Line Login
            </a>
        </div>
    </div>
</body>
</html>
