<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents("php://input"), true);
$idToken = $input['credential'] ?? null;

if (!$idToken) {
    echo json_encode(['success' => false, 'error' => 'No credential provided']);
    exit();
}

$client = new Google_Client(['client_id' => '28282326291-fjromftq1ui77v6d09j0naksrrsdvvsl.apps.googleusercontent.com']);

try {
    $payload = $client->verifyIdToken($idToken);
    if ($payload) {
        $email = $payload['email'];
        $stmt = $conn->prepare("SELECT id, username, role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $email,
                'role' => $user['role']
            ];
            echo json_encode(['success' => true, 'redirect' => 'index.php']);
        } else {
            echo json_encode(['success' => false, 'error' => 'User not registered']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid token']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
