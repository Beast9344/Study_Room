<?php
require '../config/config.php';
require '../utils/auth.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user'])) {
        throw new Exception('Unauthorized');
    }

    $data = json_decode(file_get_contents('php://input'), true);
    
    $user_id = $_SESSION['user'];
    $id = $data['id'] ?? null;
    $title = $data['title'];
    $description = $data['description'];
    $deadline = $data['deadline'];
    $priority = $data['priority'];
    $progress = $data['progress'];
    $status = $data['status'];

    if ($id) {
        // Update existing task
        $stmt = $conn->prepare("
            UPDATE tasks SET 
                title = ?,
                description = ?,
                deadline = ?,
                priority = ?,
                progress = ?,
                status = ?
            WHERE id = ? AND user_id = ?
        ");
        $stmt->bind_param("sssssiii", $title, $description, $deadline, $priority, $progress, $status, $id, $user_id);
    } else {
        // Create new task
        $stmt = $conn->prepare("
            INSERT INTO tasks 
                (title, description, deadline, priority, progress, status, user_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssisi", $title, $description, $deadline, $priority, $progress, $status, $user_id);
    }

    $stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Task saved']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>