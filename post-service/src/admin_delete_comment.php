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
$comment_id = (int)($data->comment_id ?? 0);

if (!$comment_id) {
    http_response_code(400); die(json_encode(['error' => 'Invalid comment ID.']));
}

try {
    $pdo->prepare("DELETE FROM comments WHERE id = ?")->execute([$comment_id]);
    
    http_response_code(200);
    echo json_encode(['message' => 'Comment permanently annihilated.']);
} catch (Exception $e) {
    http_response_code(500); echo json_encode(['error' => 'Failed to delete the comment.']);
}
?>