<?php
session_start();

// Unset stage session variables
unset($_SESSION['stage_user']);
unset($_SESSION['user_type']);

// Destroy the session
session_destroy();

// Redirect to stage login page
header("Location: stage-login.php");
exit();
?>
