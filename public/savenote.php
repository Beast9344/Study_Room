<?php
require '../config/config.php';
require '../utils/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

try {
    if (!empty($data['id'])) {
        // Update existing note
        $stmt = $conn->prepare("UPDATE notes SET title = ?, content = ?, category = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("sssii", $data['title'], $data['content'], $data['category'], $data['id'], $_SESSION['user']);
    } else {
        // Create new note
        $stmt = $conn->prepare("INSERT INTO notes (user_id, title, content, category) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $_SESSION['user'], $data['title'], $data['content'], $data['category']);
    }
    
    $stmt->execute();
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}