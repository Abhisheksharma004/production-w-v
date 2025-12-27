<?php
/**
 * Database Setup Script
 * Run this file once through browser to create all necessary tables
 * URL: http://localhost/production-management/setup.php
 */

// Include database configuration
require_once 'config/database.php';

$setupStatus = [];
$errors = [];

// Function to execute SQL query
function executeQuery($conn, $sql, $description) {
    global $setupStatus, $errors;
    
    $stmt = sqlsrv_query($conn, $sql);
    
    if ($stmt === false) {
        $errors[] = $description . " - Failed: " . print_r(sqlsrv_errors(), true);
        $setupStatus[] = ['status' => 'error', 'message' => $description . " - Failed"];
        return false;
    } else {
        $setupStatus[] = ['status' => 'success', 'message' => $description . " - Success"];
        return true;
    }
}

// Get connection
$conn = getSQLSrvConnection();

// 1. Create Users Table
$sql = "
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='users' AND xtype='U')
BEGIN
    CREATE TABLE users (
        id INT IDENTITY(1,1) PRIMARY KEY,
        username NVARCHAR(100) NOT NULL,
        full_name NVARCHAR(200),
        email NVARCHAR(100) NOT NULL UNIQUE,
        phone NVARCHAR(20),
        password NVARCHAR(255) NOT NULL,
        role NVARCHAR(50) DEFAULT 'user',
        created_at DATETIME DEFAULT GETDATE(),
        updated_at DATETIME DEFAULT GETDATE()
    )
END
ELSE
BEGIN
    -- Add full_name column if it doesn't exist
    IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('users') AND name = 'full_name')
    BEGIN
        ALTER TABLE users ADD full_name NVARCHAR(200)
    END
    
    -- Add phone column if it doesn't exist
    IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('users') AND name = 'phone')
    BEGIN
        ALTER TABLE users ADD phone NVARCHAR(20)
    END
END
";
executeQuery($conn, $sql, "Create Users Table");

// 2. Insert Default Admin User
$sql = "
IF NOT EXISTS (SELECT * FROM users WHERE email = 'production@viros.com')
BEGIN
    INSERT INTO users (username, email, password, role, created_at, updated_at)
    VALUES ('Admin', 'production@viros.com', 'Admin@2025', 'admin', GETDATE(), GETDATE())
END
";
executeQuery($conn, $sql, "Insert Default Admin User");

// 3. Create Lines Table
$sql = "
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='lines' AND xtype='U')
BEGIN
    CREATE TABLE lines (
        id INT IDENTITY(1,1) PRIMARY KEY,
        line_name NVARCHAR(100) NOT NULL,
        user_email NVARCHAR(100) NOT NULL UNIQUE,
        password NVARCHAR(255) NOT NULL,
        status NVARCHAR(20) DEFAULT 'Active',
        created_at DATETIME DEFAULT GETDATE(),
        updated_at DATETIME DEFAULT GETDATE()
    )
END
";
executeQuery($conn, $sql, "Create Lines Table");

// 4. Create Parts Table
$sql = "
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='parts' AND xtype='U')
BEGIN
    CREATE TABLE parts (
        id INT IDENTITY(1,1) PRIMARY KEY,
        part_name NVARCHAR(200) NOT NULL,
        part_code NVARCHAR(100) NOT NULL UNIQUE,
        status NVARCHAR(20) DEFAULT 'Active',
        created_at DATETIME DEFAULT GETDATE(),
        updated_at DATETIME DEFAULT GETDATE()
    )
END
";
executeQuery($conn, $sql, "Create Parts Table");

// 5. Create Stages Metadata Table
$sql = "
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='stages_metadata' AND xtype='U')
BEGIN
    CREATE TABLE stages_metadata (
        id INT IDENTITY(1,1) PRIMARY KEY,
        part_id INT NOT NULL,
        part_code NVARCHAR(50) NOT NULL,
        table_name NVARCHAR(255) NOT NULL UNIQUE,
        stage_names NVARCHAR(MAX) NOT NULL,
        created_at DATETIME2 DEFAULT GETDATE(),
        FOREIGN KEY (part_id) REFERENCES parts(id) ON DELETE CASCADE
    )
END
";
executeQuery($conn, $sql, "Create Stages Metadata Table");

// 6. Create Material In Table
$sql = "
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='material_in' AND xtype='U')
BEGIN
    CREATE TABLE material_in (
        id INT IDENTITY(1,1) PRIMARY KEY,
        line_id INT NOT NULL,
        part_id INT NOT NULL,
        part_code NVARCHAR(50) NOT NULL,
        part_name NVARCHAR(255) NOT NULL,
        in_quantity INT NOT NULL,
        in_units NVARCHAR(20) NOT NULL,
        received_date DATETIME NOT NULL,
        batch_number NVARCHAR(100) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT GETDATE(),
        CONSTRAINT FK_material_in_line FOREIGN KEY (line_id) REFERENCES lines(id),
        CONSTRAINT FK_material_in_part FOREIGN KEY (part_id) REFERENCES parts(id)
    )
END
";
executeQuery($conn, $sql, "Create Material In Table");

// 7. Create Material In Indexes
$sql = "
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_material_in_line_id' AND object_id = OBJECT_ID('material_in'))
BEGIN
    CREATE INDEX IX_material_in_line_id ON material_in(line_id)
END

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_material_in_part_id' AND object_id = OBJECT_ID('material_in'))
BEGIN
    CREATE INDEX IX_material_in_part_id ON material_in(part_id)
END

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_material_in_received_date' AND object_id = OBJECT_ID('material_in'))
BEGIN
    CREATE INDEX IX_material_in_received_date ON material_in(received_date)
END

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_material_in_batch_number' AND object_id = OBJECT_ID('material_in'))
BEGIN
    CREATE INDEX IX_material_in_batch_number ON material_in(batch_number)
END

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_material_in_created_at' AND object_id = OBJECT_ID('material_in'))
BEGIN
    CREATE INDEX IX_material_in_created_at ON material_in(created_at)
END
";
executeQuery($conn, $sql, "Create Material In Indexes");

// 8. Add Production Closure Columns
$sql = "
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('material_in') AND name = 'final_production_quantity')
BEGIN
    ALTER TABLE material_in ADD final_production_quantity INT NULL
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('material_in') AND name = 'scrap_quantity')
BEGIN
    ALTER TABLE material_in ADD scrap_quantity INT NULL
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('material_in') AND name = 'production_status')
BEGIN
    ALTER TABLE material_in ADD production_status NVARCHAR(20) NOT NULL DEFAULT 'Open'
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('material_in') AND name = 'closed_at')
BEGIN
    ALTER TABLE material_in ADD closed_at DATETIME NULL
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('material_in') AND name = 'wing_scale_id')
BEGIN
    ALTER TABLE material_in ADD wing_scale_id INT NULL
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('material_in') AND name = 'wing_scale_code')
BEGIN
    ALTER TABLE material_in ADD wing_scale_code NVARCHAR(100) NULL
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('material_in') AND name = 'wing_scale_name')
BEGIN
    ALTER TABLE material_in ADD wing_scale_name NVARCHAR(200) NULL
END
";
executeQuery($conn, $sql, "Add Production Closure and Wing Scale Columns");

// 9. Create Wing Scales Table
$sql = "
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='wing_scales' AND xtype='U')
BEGIN
    CREATE TABLE wing_scales (
        id INT IDENTITY(1,1) PRIMARY KEY,
        scale_name NVARCHAR(200) NOT NULL,
        scale_code NVARCHAR(100) NOT NULL UNIQUE,
        status NVARCHAR(20) DEFAULT 'Active',
        created_at DATETIME DEFAULT GETDATE(),
        updated_at DATETIME DEFAULT GETDATE()
    )
END
";
executeQuery($conn, $sql, "Create Wing Scales Table");

// Close connection
closeConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - Production Management System</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .setup-container {
            max-width: 800px;
            margin: 50px auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .setup-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #667eea;
        }
        
        .setup-header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .status-list {
            list-style: none;
            padding: 0;
        }
        
        .status-item {
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
            display: inline-block;
            width: 120px;
        }
        
        .btn-login {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            text-align: center;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .summary {
            background: #f9fafb;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .summary-stats {
            display: flex;
            justify-content: space-around;
            margin-top: 15px;
        }
        
        .stat {
            text-align: center;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <h1>🚀 Database Setup Complete</h1>
            <p>Production Management System - SQL Server</p>
        </div>
        
        <div class="summary">
            <h3>Setup Summary</h3>
            <div class="summary-stats">
                <div class="stat">
                    <div class="stat-number"><?php echo count(array_filter($setupStatus, fn($s) => $s['status'] === 'success')); ?></div>
                    <div class="stat-label">Successful</div>
                </div>
                <div class="stat">
                    <div class="stat-number"><?php echo count(array_filter($setupStatus, fn($s) => $s['status'] === 'error')); ?></div>
                    <div class="stat-label">Errors</div>
                </div>
                <div class="stat">
                    <div class="stat-number"><?php echo count($setupStatus); ?></div>
                    <div class="stat-label">Total Operations</div>
                </div>
            </div>
        </div>
        
        <h3>Setup Operations:</h3>
        <ul class="status-list">
            <?php foreach ($setupStatus as $status): ?>
                <li class="status-item <?php echo $status['status']; ?>">
                    <span class="status-icon"><?php echo $status['status'] === 'success' ? '✓' : '✗'; ?></span>
                    <span><?php echo htmlspecialchars($status['message']); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        
        <?php if (!empty($errors)): ?>
            <div class="credentials-box" style="background: #fee; border-color: #dc2626;">
                <h3 style="color: #991b1b;">⚠️ Errors Encountered:</h3>
                <?php foreach ($errors as $error): ?>
                    <div style="color: #991b1b; padding: 5px 0; font-size: 12px;">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="credentials-box">
            <h3>🔐 Default Login Credentials</h3>
            <div class="credential-item">
                <strong>Email:</strong> production@viros.com
            </div>
            <div class="credential-item">
                <strong>Password:</strong> Admin@2025
            </div>
            <div class="credential-item">
                <strong>Role:</strong> Administrator
            </div>
        </div>
        
        <div style="background: #fef3c7; padding: 15px; border-radius: 5px; border-left: 4px solid #f59e0b; margin: 20px 0;">
            <strong>⚠️ Security Notice:</strong> For security reasons, please delete this setup.php file after successful setup.
        </div>
        
        <div style="text-align: center;">
            <a href="index.php" class="btn-login">Go to Login Page →</a>
        </div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0; color: #666; font-size: 12px; text-align: center;">
            <p><strong>Database:</strong> product-w</p>
            <p><strong>Server:</strong> MSI\SQLEXPRESS</p>
            <p><strong>Tables Created:</strong> users, lines, parts, stages_metadata, material_in, wing_scales</p>
        </div>
    </div>
</body>
</html>
