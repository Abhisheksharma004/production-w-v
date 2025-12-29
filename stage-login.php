<?php
session_start();

// Redirect if already logged in as stage user
if (isset($_SESSION['stage_user'])) {
    header("Location: stage-scan.php");
    exit();
}

$error = "";

// Static credentials
$STAGE_USER_ID = "abc";
$STAGE_PASSWORD = "1234";

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = trim($_POST['user_id']);
    $password = trim($_POST['password']);
    
    // Check static credentials
    if ($user_id === $STAGE_USER_ID && $password === $STAGE_PASSWORD) {
        // Set session variables for stage user
        $_SESSION['stage_user'] = $user_id;
        $_SESSION['user_type'] = 'stage';
        
        // Redirect to stage scanning page
        header("Location: stage-scan.php");
        exit();
    } else {
        $error = "Invalid user ID or password";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stage Login - Production Management System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Stage Login</h1>
            <p>Production Management System</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="user_id">Stage User ID</label>
                <input type="text" 
                       id="user_id" 
                       name="user_id" 
                       placeholder="Enter your stage user ID" 
                       required 
                       autocomplete="username">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       placeholder="Enter your password" 
                       required 
                       autocomplete="current-password">
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
