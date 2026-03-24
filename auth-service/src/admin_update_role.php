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

// THE GATEKEEPER: Kick out anyone who isn't an admin
if ($payload['role'] !== 'admin') {
    http_response_code(403); die(json_encode(['error' => 'Unauthorized. God-mode required.']));
}

$data = json_decode(file_get_contents("php://input"));
$target_user_id = (int)($data->target_user_id ?? 0);
$new_role = $data->new_role ?? '';

$valid_roles = ['user', 'chef', 'admin'];

if (!$target_user_id || !in_array($new_role, $valid_roles)) {
    http_response_code(400); die(json_encode(['error' => 'Invalid user ID or role.']));
}

// Prevent the admin from accidentally demoting themselves and locking themselves out
if ($target_user_id === $payload['user_id'] && $new_role !== 'admin') {
    http_response_code(400); die(json_encode(['error' => 'You cannot demote yourself.']));
}

try {
    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->execute([$new_role, $target_user_id]);
    
    http_response_code(200);
    echo json_encode(['message' => 'User role successfully updated.']);
} catch (Exception $e) {
    http_response_code(500); echo json_encode(['error' => 'Database failure while updating role.']);
}
?>