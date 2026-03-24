<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit(); }

require 'db.php';
require 'jwt_verify.php';

$token = get_bearer_token();
if (!$token || !($payload = verify_jwt($token))) {
    http_response_code(401); die(json_encode(['error' => 'Access denied.']));
}

$user_id = $payload['user_id'];

try {
    // Join the posts table with the saved_recipes table
    $stmt = $pdo->prepare("
        SELECT p.*, 
               (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as likes_count,
               (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id AND user_id = ?) as has_liked
        FROM posts p
        INNER JOIN saved_recipes sr ON p.id = sr.post_id
        WHERE sr.user_id = ?
        ORDER BY sr.created_at DESC
    ");
    $stmt->execute([$user_id, $user_id]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode the recipe_data JSON string back into a real object
    foreach ($posts as &$post) {
        if (is_string($post['recipe_data'])) {
            $post['recipe_data'] = json_decode($post['recipe_data'], true);
        }
    }

    http_response_code(200);
    echo json_encode(['data' => $posts]);
} catch (Exception $e) {
    http_response_code(500); echo json_encode(['error' => 'Failed to fetch saved recipes.']);
}
?>