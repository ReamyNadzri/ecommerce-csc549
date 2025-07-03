<?php
require_once '../config/connection.php';

// Start the session
session_start();

// Unset all of the session variables
$_SESSION = array();

// Clear all session data
session_unset();
session_destroy();

// Redirect to home page
header('Location: ../index.php');
exit;
?>