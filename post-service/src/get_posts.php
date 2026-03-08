<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require 'db.php';
require 'jwt_verify.php';

// 1. Identify the user asking for the feed
$token = get_bearer_token();
if (!$token || !($payload = verify_jwt($token))) {
    http_response_code(401);
    die(json_encode(['error' => 'Access denied. You must be logged in.']));
}

$current_user_id = $payload['user_id'];

try {
    // 2. Fetch posts AND check the ledger simultaneously
    $query = "
        SELECT p.id, p.user_id, p.author_name, p.title, p.description, p.category, 
               p.points_cost, p.recipe_data, p.likes_count, p.created_at,
               IF(u.id IS NOT NULL, 1, 0) as is_unlocked
        FROM posts p
        LEFT JOIN unlocked_recipes u ON p.id = u.post_id AND u.user_id = ?
        ORDER BY p.created_at DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$current_user_id]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Enforce the Paywall
    foreach ($posts as &$post) {
        // If it costs money AND you aren't the author AND you haven't bought it...
        if ($post['points_cost'] > 0 && $post['user_id'] != $current_user_id && $post['is_unlocked'] == 0) {
            // Scrub the ingredients from the payload entirely
            $post['recipe_data'] = null; 
            $post['locked'] = true;
        } else {
            $post['locked'] = false;
        }
    }
    
    http_response_code(200);
    echo json_encode(['data' => $posts]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch posts.']);
}
?>