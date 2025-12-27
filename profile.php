<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Include database configuration
require_once 'config/database.php';

$current_user = $_SESSION['username'];
$activePage = 'profile';
$pageTitle = 'Profile';

$message = '';
$messageType = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $userId = $_SESSION['user_id'];
    $fullName = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    
    try {
        $conn = getSQLSrvConnection();
        if ($conn !== false) {
            $sql = "UPDATE users SET full_name = ?, email = ?, phone = ?, updated_at = GETDATE() WHERE id = ?";
            $params = array($fullName, $email, $phone, $userId);
            $stmt = sqlsrv_query($conn, $sql, $params);
            
            if ($stmt !== false) {
                $message = "Profile updated successfully!";
                $messageType = "success";
                sqlsrv_free_stmt($stmt);
            } else {
                $message = "Error updating profile.";
                $messageType = "error";
            }
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $userId = $_SESSION['user_id'];
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    if ($newPassword !== $confirmPassword) {
        $message = "New passwords do not match!";
        $messageType = "error";
    } else {
        try {
            $conn = getSQLSrvConnection();
            if ($conn !== false) {
                // Verify current password
                $sql = "SELECT password FROM users WHERE id = ?";
                $params = array($userId);
                $stmt = sqlsrv_query($conn, $sql, $params);
                
                if ($stmt !== false) {
                    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                    sqlsrv_free_stmt($stmt);
                    
                    if ($currentPassword === $row['password']) {
                        // Update password (plain text)
                        $sql = "UPDATE users SET password = ?, updated_at = GETDATE() WHERE id = ?";
                        $params = array($newPassword, $userId);
                        $stmt = sqlsrv_query($conn, $sql, $params);
                        
                        if ($stmt !== false) {
                            $message = "Password changed successfully!";
                            $messageType = "success";
                            sqlsrv_free_stmt($stmt);
                        } else {
                            $message = "Error updating password.";
                            $messageType = "error";
                        }
                    } else {
                        $message = "Current password is incorrect!";
                        $messageType = "error";
                    }
                }
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Fetch user data
$userData = [];
try {
    $conn = getSQLSrvConnection();
    if ($conn !== false) {
        $sql = "SELECT username, full_name, email, phone, created_at FROM users WHERE id = ?";
        $params = array($_SESSION['user_id']);
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt !== false) {
            $userData = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmt);
        }
    }
} catch (Exception $e) {
    // Handle error
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Production Management System</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/line-management.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px;
            border-radius: 16px;
            color: white;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .profile-header-content {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 5px solid rgba(255, 255, 255, 0.3);
            overflow: hidden;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-info h2 {
            margin: 0 0 10px 0;
            font-size: 32px;
            font-weight: 700;
        }

        .profile-info p {
            margin: 5px 0;
            opacity: 0.9;
            font-size: 16px;
        }

        .profile-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
        }

        .profile-card {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .profile-card h3 {
            margin: 0 0 25px 0;
            font-size: 20px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
        }

        .profile-card h3 i {
            color: #667eea;
        }

        .form-group-profile {
            margin-bottom: 20px;
        }

        .form-group-profile label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #475569;
            font-size: 14px;
        }

        .form-group-profile input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
        }

        .form-group-profile input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group-profile input:disabled {
            background: #f8fafc;
            cursor: not-allowed;
        }

        .password-input-wrapper {
            position: relative;
        }

        .password-input-wrapper input {
            padding-right: 45px;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #94a3b8;
            font-size: 18px;
            transition: color 0.3s;
        }

        .password-toggle:hover {
            color: #667eea;
        }

        .btn-save {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            margin-top: 10px;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #64748b;
        }

        .info-value {
            color: #1e293b;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include 'includes/header.php'; ?>

        <div class="dashboard-container">
            <div class="profile-container">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <!-- Profile Header -->
                <div class="profile-header">
                    <div class="profile-header-content">
                        <div class="profile-avatar">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($userData['username'] ?? 'User'); ?>&background=667eea&color=fff&size=200" alt="Profile">
                        </div>
                        <div class="profile-info">
                            <h2><?php echo htmlspecialchars($userData['full_name'] ?? $userData['username'] ?? 'User'); ?></h2>
                            <p><i class="fas fa-user"></i> @<?php echo htmlspecialchars($userData['username'] ?? ''); ?></p>
                            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($userData['email'] ?? 'Not set'); ?></p>
                            <p><i class="fas fa-calendar"></i> Member since <?php echo isset($userData['created_at']) && $userData['created_at'] ? $userData['created_at']->format('F Y') : 'N/A'; ?></p>
                        </div>
                    </div>
                </div>

                <div class="profile-cards">
                    <!-- Edit Profile Card -->
                    <div class="profile-card">
                        <h3><i class="fas fa-user-edit"></i> Edit Profile</h3>
                        <form method="POST">
                            <div class="form-group-profile">
                                <label for="username">Username</label>
                                <input type="text" id="username" value="<?php echo htmlspecialchars($userData['username'] ?? ''); ?>" disabled>
                            </div>
                            <div class="form-group-profile">
                                <label for="full_name">Full Name</label>
                                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($userData['full_name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group-profile">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group-profile">
                                <label for="phone">Phone</label>
                                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($userData['phone'] ?? ''); ?>">
                            </div>
                            <button type="submit" name="update_profile" class="btn-save">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </form>
                    </div>

                    <!-- Change Password Card -->
                    <div class="profile-card">
                        <h3><i class="fas fa-lock"></i> Change Password</h3>
                        <form method="POST">
                            <div class="form-group-profile">
                                <label for="current_password">Current Password</label>
                                <div class="password-input-wrapper">
                                    <input type="password" id="current_password" name="current_password" required>
                                    <i class="fas fa-eye password-toggle" onclick="togglePassword('current_password', this)"></i>
                                </div>
                            </div>
                            <div class="form-group-profile">
                                <label for="new_password">New Password</label>
                                <div class="password-input-wrapper">
                                    <input type="password" id="new_password" name="new_password" required minlength="6">
                                    <i class="fas fa-eye password-toggle" onclick="togglePassword('new_password', this)"></i>
                                </div>
                            </div>
                            <div class="form-group-profile">
                                <label for="confirm_password">Confirm New Password</label>
                                <div class="password-input-wrapper">
                                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                                    <i class="fas fa-eye password-toggle" onclick="togglePassword('confirm_password', this)"></i>
                                </div>
                            </div>
                            <button type="submit" name="change_password" class="btn-save">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/scripts.php'; ?>
    
    <script>
        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Form validation for password match
        document.querySelector('form[name="change_password"]')?.addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match!');
            }
        });
    </script>
</body>
</html>
