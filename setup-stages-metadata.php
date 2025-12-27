<?php
// Setup script to create stages_metadata table
require_once 'config/database.php';

$conn = getSQLSrvConnection();

if ($conn === false) {
    die("Database connection failed: " . print_r(sqlsrv_errors(), true));
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>Setup Stages Metadata Table</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { color: #1e293b; margin-bottom: 20px; }
        .success { color: #10b981; padding: 15px; background: #d1fae5; border-radius: 6px; margin: 20px 0; }
        .info { color: #3b82f6; padding: 15px; background: #dbeafe; border-radius: 6px; margin: 20px 0; }
        .error { color: #ef4444; padding: 15px; background: #fee2e2; border-radius: 6px; margin: 20px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #3b82f6; color: white; text-decoration: none; border-radius: 6px; margin-top: 20px; }
        .btn:hover { background: #2563eb; }
    </style>
</head>
<body>
<div class='container'>
<h2>Creating stages_metadata table...</h2>";

// Check if table already exists
$checkSQL = "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'stages_metadata'";
$checkStmt = sqlsrv_query($conn, $checkSQL);

if ($checkStmt === false) {
    echo "<div class='error'>Error checking for existing table: " . print_r(sqlsrv_errors(), true) . "</div>";
} else {
    $row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
    
    if ($row['cnt'] > 0) {
        echo "<div class='info'>✓ stages_metadata table already exists. No action needed.</div>";
    } else {
        // Create stages_metadata table
        $createTableSQL = "
        CREATE TABLE stages_metadata (
            id INT IDENTITY(1,1) PRIMARY KEY,
            part_id INT NOT NULL,
            part_code NVARCHAR(50) NOT NULL,
            table_name NVARCHAR(255) NOT NULL UNIQUE,
            stage_names NVARCHAR(MAX) NOT NULL,
            created_at DATETIME2 DEFAULT GETDATE(),
            FOREIGN KEY (part_id) REFERENCES parts(id) ON DELETE CASCADE
        )";

        $stmt = sqlsrv_query($conn, $createTableSQL);

        if ($stmt === false) {
            $errors = sqlsrv_errors();
            echo "<div class='error'>Error creating stages_metadata table: " . print_r($errors, true) . "</div>";
        } else {
            echo "<div class='success'>✓ stages_metadata table created successfully!</div>";
            sqlsrv_free_stmt($stmt);
        }
    }
    
    sqlsrv_free_stmt($checkStmt);
}

sqlsrv_close($conn);

echo "<a href='stages-management.php' class='btn'>Go to Stages Management</a>";
echo "</div></body></html>";
?>
