<?php
// MUST set ini values BEFORE session starts
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // Enable this only if using HTTPS
ini_set('session.use_strict_mode', 1);

// Display errors for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session (after ini_set)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
$servername = "localhost";
$username = "studyuser";
$password = "password";
$dbname = "study_app";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>
