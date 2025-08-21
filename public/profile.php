<?php
require '../config/config.php';
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white">
    <div class="container mx-auto mt-10 p-6 bg-gray-800 rounded shadow-lg">
        <h2 class="text-2xl">Welcome, <?= htmlspecialchars($user['username']); ?></h2>
        <p>Email: <?= htmlspecialchars($user['email']); ?></p>
        <a href="logout.php" class="bg-red-500 px-4 py-2 rounded">Logout</a>
    </div>
</body>
</html>
