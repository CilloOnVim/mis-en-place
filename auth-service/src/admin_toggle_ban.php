<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit(); }

require 'db.php';
require 'jwt_verify.php';

$token = get_bearer_token();
if (!$token || !($payload = verify_jwt($token))) {
    http_response_code(401); die(json_encode(['error' => 'Access denied.']));
}

if ($payload['role'] !== 'admin') {
    http_response_code(403); die(json_encode(['error' => 'Unauthorized. God-mode required.']));
}

$data = json_decode(file_get_contents("php://input"));
$target_user_id = (int)($data->target_user_id ?? 0);

if (!$target_user_id) {
    http_response_code(400); die(json_encode(['error' => 'Invalid user ID.']));
}

if ($target_user_id === $payload['user_id']) {
    http_response_code(400); die(json_encode(['error' => 'You cannot ban yourself.']));
}

try {
    // Check current status
    $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->execute([$target_user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(404); die(json_encode(['error' => 'User not found.']));
    }

    // Toggle the status
    $new_status = ($user['status'] === 'banned') ? 'active' : 'banned';
    
    $update_stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
    $update_stmt->execute([$new_status, $target_user_id]);
    
    http_response_code(200);
    echo json_encode(['message' => "User status changed to $new_status", 'new_status' => $new_status]);
} catch (Exception $e) {
    http_response_code(500); echo json_encode(['error' => 'Database failure.']);
}