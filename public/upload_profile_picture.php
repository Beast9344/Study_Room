<?php
session_start();
require '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user'];

    // Check if the uploads directory exists, and create it if it doesn't
    $target_dir = "uploads/";
    if (!is_dir($target_dir)) {
        if (!mkdir($target_dir, 0755, true)) {
            die("Failed to create uploads directory.");
        }
    }

    // Generate a unique file name to avoid overwriting existing files
    $target_file = $target_dir . uniqid() . "_" . basename($_FILES["profile_picture"]["name"]);
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Check if the file is an actual image
    $check = getimagesize($_FILES["profile_picture"]["tmp_name"]);
    if ($check === false) {
        die("File is not an image.");
    }

    // Check file size (max 5MB)
    if ($_FILES["profile_picture"]["size"] > 5000000) {
        die("File is too large. Maximum size is 5MB.");
    }

    // Allow only certain file formats
    $allowed_types = ["jpg", "jpeg", "png"];
    if (!in_array($imageFileType, $allowed_types)) {
        die("Only JPG, JPEG, and PNG files are allowed.");
    }

    // Upload the file
    if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
        // Update the database with the new profile picture path
        $sql = "UPDATE users SET profile_picture = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $target_file, $user_id);

        if ($stmt->execute()) {
            // Redirect back to the profile page
            header("Location: index.php");
            exit();
        } else {
            die("Error updating database: " . $stmt->error);
        }
    } else {
        die("Error uploading file. Please check directory permissions.");
    }
}
?>