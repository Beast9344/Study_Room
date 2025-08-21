<?php
require '../config/config.php';
require '../utils/auth.php';

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    exit();
}

$noteId = intval($_GET['id']);

$stmt = $conn->prepare("DELETE FROM notes WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $noteId, $_SESSION['user']);
$stmt->execute();