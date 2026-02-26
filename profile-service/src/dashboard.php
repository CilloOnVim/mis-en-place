<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Catch the browser's preflight test and let it pass
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
    die(json_encode(['error' => 'Access denied. Invalid or expired token.']));
}

// Token is valid. We have the user_id.
$user_id = $payload['user_id'];

// Query the profile database
$stmt = $pdo->prepare("SELECT display_name, bio FROM profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$profile = $stmt->fetch();

if ($profile) {
    http_response_code(200);
    echo json_encode([
        'message' => 'Dashboard loaded successfully',
        'data' => $profile
    ]);
} else {
    // Profile row doesn't exist yet, return a graceful default
    http_response_code(200);
    echo json_encode([
        'message' => 'Dashboard loaded successfully',
        'data' => [
            'display_name' => 'New Chef',
            'bio' => 'Update your profile to tell us about your culinary journey.'
        ]
    ]);
}
?>