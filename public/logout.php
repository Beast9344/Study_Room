<?php
// logout.php
session_start();

// 1. Enforce POST method for logout
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    exit('Invalid request method');
}

// 2. CSRF Token Verification
if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403); // Forbidden
    exit('Invalid CSRF token');
}

// 3. Prevent session fixation
session_regenerate_id(true);

// 4. Clear session variables
$_SESSION = [];

// 5. Destroy session
if (session_id() !== "" || isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}
session_destroy();

// 6. Redirect to login page
header("Location: login.php");
exit();
?>
