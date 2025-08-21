<?php
require '../config/config.php';
require '../utils/auth.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user'])) {
        throw new Exception('Unauthorized');
    }

    $task_id = $_GET['id'];
    $user_id = $_SESSION['user'];

    $stmt = $conn->prepare("
        DELETE FROM tasks 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->bind_param("ii", $task_id, $user_id);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new Exception('Task not found or unauthorized');
    }

    echo json_encode(['success' => true, 'message' => 'Task deleted']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>