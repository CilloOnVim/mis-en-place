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
$post_id = (int)$data->post_id;
$points_paid = (int)$data->points_paid;

try {
    $stmt = $pdo->prepare("INSERT INTO unlocked_recipes (user_id, post_id, points_paid) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $post_id, $points_paid]);
    
    http_response_code(200); 
    echo json_encode(['message' => 'Recipe successfully unlocked.']);
} catch(PDOException $e) {
    if ($e->getCode() == 23000) { // MySQL code for Duplicate Key Constraint
        http_response_code(200); 
        echo json_encode(['message' => 'Recipe is already unlocked.']);
    } else {
        http_response_code(500); 
        echo json_encode(['error' => 'Failed to write receipt to ledger.']);
    }
}
?>