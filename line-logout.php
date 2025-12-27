<?php
session_start();

// Clear all line session variables
unset($_SESSION['line_id']);
unset($_SESSION['line_name']);
unset($_SESSION['line_email']);
unset($_SESSION['user_type']);

// Destroy the session
session_destroy();

// Redirect to line login page
header("Location: line-login.php");
exit();
?>
