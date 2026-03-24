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

if ($payload['role'] !== 'admin') {
    http_response_code(403); die(json_encode(['error' => 'Unauthorized. God-mode required.']));
}

try {
    // UPGRADED: Pulls the complete post payload for the Admin Inspection Modal
    $stmt = $pdo->query("SELECT id, user_id, author_name, title, description, category, points_cost, recipe_data, image_url, likes_count, created_at FROM posts ORDER BY created_at DESC");
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    http_response_code(200);
    echo json_encode(['data' => $posts]);
} catch (Exception $e) {
    http_response_code(500); echo json_encode(['error' => 'Failed to fetch the platform posts.']);
}
?>