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

if (!$post_id) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid post ID.']));
}

try {
    // Check the ledger to see if this user already liked this post
    $check_stmt = $pdo->prepare("SELECT * FROM post_likes WHERE user_id = ? AND post_id = ?");
    $check_stmt->execute([$user_id, $post_id]);
    $exists = $check_stmt->fetch();

    if ($exists) {
        // UNLIKE: Remove from ledger and decrement count
        $pdo->prepare("DELETE FROM post_likes WHERE user_id = ? AND post_id = ?")->execute([$user_id, $post_id]);
        $pdo->prepare("UPDATE posts SET likes_count = GREATEST(likes_count - 1, 0) WHERE id = ?")->execute([$post_id]);
        $action = 'unliked';
    } else {
        // LIKE: Add to ledger and increment count
        $pdo->prepare("INSERT INTO post_likes (user_id, post_id) VALUES (?, ?)")->execute([$user_id, $post_id]);
        $pdo->prepare("UPDATE posts SET likes_count = likes_count + 1 WHERE id = ?")->execute([$post_id]);
        $action = 'liked';
    }

    // Grab the new actual count to send back to the frontend
    $count_stmt = $pdo->prepare("SELECT likes_count FROM posts WHERE id = ?");
    $count_stmt->execute([$post_id]);
    $new_count = $count_stmt->fetchColumn();

    http_response_code(200);
    echo json_encode(['message' => "Successfully $action", 'action' => $action, 'new_count' => $new_count]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database failure while toggling like.']);
}
?>