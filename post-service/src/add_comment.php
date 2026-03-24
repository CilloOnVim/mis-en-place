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
    http_response_code(401);
    die(json_encode(['error' => 'Access denied.']));
}

$user_id = $payload['user_id'];
$data = json_decode(file_get_contents("php://input"));
$post_id = (int)($data->post_id ?? 0);
$author_name = $data->author_name ?? 'Unknown Chef';
$comment_text = trim($data->comment_text ?? '');

if (!$post_id || empty($comment_text)) {
    http_response_code(400);
    die(json_encode(['error' => 'Post ID and comment text are required.']));
}

try {
    $stmt = $pdo->prepare("INSERT INTO comments (post_id, user_id, author_name, comment_text) VALUES (?, ?, ?, ?)");
    $stmt->execute([$post_id, $user_id, $author_name, $comment_text]);
    
    http_response_code(201);
    echo json_encode(['message' => 'Comment posted successfully.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to post comment.']);
}
?>