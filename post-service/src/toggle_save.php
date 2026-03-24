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

$user_id = $payload['user_id'];
$data = json_decode(file_get_contents("php://input"));
$post_id = (int)($data->post_id ?? 0);

if (!$post_id) {
    http_response_code(400); die(json_encode(['error' => 'Invalid post ID.']));
}

try {
    // Check if the bookmark already exists
    $stmt = $pdo->prepare("SELECT id FROM saved_recipes WHERE user_id = ? AND post_id = ?");
    $stmt->execute([$user_id, $post_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        // If it exists, tear it down (Unsave)
        $pdo->prepare("DELETE FROM saved_recipes WHERE id = ?")->execute([$existing['id']]);
        http_response_code(200);
        echo json_encode(['message' => 'Recipe removed from your vault.', 'status' => 'unsaved']);
    } else {
        // If it doesn't exist, build it (Save)
        $pdo->prepare("INSERT INTO saved_recipes (user_id, post_id) VALUES (?, ?)")->execute([$user_id, $post_id]);
        http_response_code(200);
        echo json_encode(['message' => 'Recipe saved to your vault!', 'status' => 'saved']);
    }
} catch (Exception $e) {
    http_response_code(500); echo json_encode(['error' => 'Database failure while toggling save state.']);
}