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

// We verify the token so only logged-in users can see the feed
$token = get_bearer_token();
if (!$token || !verify_jwt($token)) {
    http_response_code(401);
    die(json_encode(['error' => 'Access denied.']));
}

// Fetch the 20 most recent posts
// Added author_name to the SELECT statement
$stmt = $pdo->query("SELECT id, user_id, author_name, title, description, category, points_cost, likes_count, created_at FROM posts ORDER BY created_at DESC LIMIT 20");
$posts = $stmt->fetchAll();

http_response_code(200);
echo json_encode(['data' => $posts]);
?>