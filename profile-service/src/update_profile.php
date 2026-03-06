<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require 'db.php';
require 'jwt_verify.php';

$token = get_bearer_token();
if (!$token) {
    http_response_code(401);
    die(json_encode(['error' => 'Access denied. No token provided.']));
}

$payload = verify_jwt($token);
if (!$payload) {
    http_response_code(401);
    die(json_encode(['error' => 'Access denied. Invalid token.']));
}

$user_id = $payload['user_id'];
$data = json_decode(file_get_contents("php://input"));

if (!empty($data->display_name)) {
    $display_name = $data->display_name;
    $bio = $data->bio ?? 'Update your profile to tell us about your culinary journey.';

    // The UPSERT command: Inserts a new profile, or updates the existing one based on the PRIMARY KEY (user_id)
    $stmt = $pdo->prepare("INSERT INTO profiles (user_id, display_name, bio) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE display_name = ?, bio = ?");
    
    try {
        $stmt->execute([$user_id, $display_name, $bio, $display_name, $bio]);
        http_response_code(200);
        echo json_encode(['message' => 'Profile updated successfully!', 'display_name' => $display_name]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update profile.']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Display name is required.']);
}
?>