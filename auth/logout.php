<?php
/**
 * Logout Handler
 */

session_start();

// Destroy session
session_destroy();

// Redirect to login
header('Location: login.php');
exit;
