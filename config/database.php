<?php
/**
 * Database Configuration
 * SQL Server connection settings for the Production Management System
 */

// SQL Server connection settings
$serverName = "MSI\SQLEXPRESS";
$port = 1433;
$connectionOptions = [
    "Database"=>"product-w",
    "Uid"=>"",
    "PWD"=>"",
    "TrustServerCertificate"=>true,
    "LoginTimeout"=>30,
    "ConnectRetryCount"=>3
];

// Make variables globally accessible
global $serverName, $connectionOptions, $port;

// Create connection using sqlsrv functions
$conn = sqlsrv_connect($serverName, $connectionOptions);

// If connection fails, try with port
if($conn == false) {
    $serverWithPort = $serverName . "," . $port;
    $conn = sqlsrv_connect($serverWithPort, $connectionOptions);
}

if($conn == false) {
    die(print_r( sqlsrv_errors(), true));
}

// For backward compatibility, also create a PDO connection wrapper
try {
    $dsn = "sqlsrv:Server=" . $serverName . ";Database=" . $connectionOptions["Database"] . ";TrustServerCertificate=1";
    
    $pdo = new PDO($dsn, 
                   $connectionOptions["Uid"], 
                   $connectionOptions["PWD"],
                   [
                       PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                       PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                       PDO::ATTR_EMULATE_PREPARES => false
                   ]);
} catch(PDOException $e) {
    try {
        $serverWithPort = $serverName . "," . $port;
        $dsn = "sqlsrv:Server=" . $serverWithPort . ";Database=" . $connectionOptions["Database"] . ";TrustServerCertificate=1";
        
        $pdo = new PDO($dsn, 
                       $connectionOptions["Uid"], 
                       $connectionOptions["PWD"],
                       [
                           PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                           PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                           PDO::ATTR_EMULATE_PREPARES => false
                       ]);
    } catch(PDOException $e2) {
        die("PDO Connection failed: " . $e->getMessage() . " and " . $e2->getMessage());
    }
}

// Function to get database connection
function getDB() {
    global $pdo;
    return $pdo;
}

// Function to get native SQL Server connection
function getSQLSrvConnection() {
    global $conn;
    return $conn;
}

// Close connection function
function closeConnection($conn) {
    if ($conn) {
        sqlsrv_close($conn);
    }
}
?>
