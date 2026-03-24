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

// THE GATEKEEPER: Kick out anyone who isn't an admin
if ($payload['role'] !== 'admin') {
    http_response_code(403); die(json_encode(['error' => 'Unauthorized. God-mode required.']));
}

try {
    // Join with posts table so the admin can see which recipe the comment is attached to
    $stmt = $pdo->query("
        SELECT c.id, c.post_id, c.author_name, c.comment_text, c.created_at, p.title as post_title 
        FROM comments c 
        LEFT JOIN posts p ON c.post_id = p.id 
        ORDER BY c.created_at DESC 
        LIMIT 100
    ");
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    http_response_code(200);
    echo json_encode(['data' => $comments]);
} catch (Exception $e) {
    http_response_code(500); echo json_encode(['error' => 'Failed to fetch the platform comments.']);
}
?>