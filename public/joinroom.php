<?php
require '../config/config.php';
require '../utils/auth.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$room_id = intval($_GET['id']);
$user_id = $_SESSION['user']['id'];

try {
    $conn->begin_transaction();
    
    // Check available space
    $stmt = $conn->prepare("SELECT participant_limit, current_participants 
                           FROM rooms WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $room = $stmt->get_result()->fetch_assoc();
    
    if ($room['current_participants'] >= $room['participant_limit']) {
        throw new Exception("Room is full");
    }
    
    // Add participant
    $stmt = $conn->prepare("INSERT INTO room_participants (room_id, user_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $room_id, $user_id);
    $stmt->execute();
    
    // Update participant count
    $stmt = $conn->prepare("UPDATE rooms SET current_participants = current_participants + 1 WHERE id = ?");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    
    $conn->commit();
    header("Location: dashboard.php");
    exit();
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = $e->getMessage();
    header("Location: dashboard.php");
    exit();
}