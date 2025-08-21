<?php
require '../config/config.php';
require '../utils/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode([]);
    exit();
}

$sql = "SELECT * FROM notes WHERE user_id = ? ORDER BY updated_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user']);
$stmt->execute();
$result = $stmt->get_result();

$notes = [];
while ($row = $result->fetch_assoc()) {
    $row['content'] = htmlspecialchars($row['content']);
    $notes[] = $row;
}

echo json_encode($notes);