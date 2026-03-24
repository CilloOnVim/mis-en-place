<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit(); }

require 'db.php';

$post_id = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;

if (!$post_id) {
    http_response_code(400);
    die(json_encode(['error' => 'Post ID is required.']));
}

try {
    $stmt = $pdo->prepare("SELECT id, author_name, comment_text, created_at FROM comments WHERE post_id = ? ORDER BY created_at ASC");
    $stmt->execute([$post_id]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    http_response_code(200);
    echo json_encode(['data' => $comments]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch comments.']);
}
?>