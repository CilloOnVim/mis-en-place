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
$post_id = (int)($data->post_id ?? 0);

if (!$post_id) {
    http_response_code(400); die(json_encode(['error' => 'Invalid post ID.']));
}

try {
    // 1. Wipe all comments attached to this post
    $pdo->prepare("DELETE FROM comments WHERE post_id = ?")->execute([$post_id]);
    
    // 2. Wipe all likes attached to this post
    $pdo->prepare("DELETE FROM post_likes WHERE post_id = ?")->execute([$post_id]);
    
    // 3. Wipe the paywall unlock records for this post
    $pdo->prepare("DELETE FROM unlocked_recipes WHERE post_id = ?")->execute([$post_id]);

    // 4. Finally, execute the post itself
    $pdo->prepare("DELETE FROM posts WHERE id = ?")->execute([$post_id]);
    
    http_response_code(200);
    echo json_encode(['message' => 'Post and all associated data permanently annihilated.']);
} catch (Exception $e) {
    http_response_code(500); echo json_encode(['error' => 'Database failure during post deletion.']);
}
?>