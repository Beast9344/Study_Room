<?php
require '../config/config.php';
require '../utils/auth.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user'])) {
        throw new Exception('Unauthorized');
    }

    $user_id = $_SESSION['user'];
    $stmt = $conn->prepare("
        SELECT id, title, description, deadline, priority, progress, status 
        FROM tasks 
        WHERE user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $tasks = [];
    while ($row = $result->fetch_assoc()) {
        $tasks[] = $row;
    }
    
    echo json_encode($tasks);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>