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
$points_to_spend = (int)$data->points;

try {
    $pdo->beginTransaction();
    
    // Lock the row to prevent double-spending race conditions
    $stmt = $pdo->prepare("SELECT points_balance FROM profiles WHERE user_id = ? FOR UPDATE");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch();
    
    if (!$profile || $profile['points_balance'] < $points_to_spend) {
        $pdo->rollBack();
        http_response_code(400); 
        die(json_encode(['error' => 'Insufficient points. Please top up your wallet.']));
    }
    
    // Deduct the points
    $update = $pdo->prepare("UPDATE profiles SET points_balance = points_balance - ? WHERE user_id = ?");
    $update->execute([$points_to_spend, $user_id]);
    
    $pdo->commit();
    http_response_code(200); 
    echo json_encode(['message' => 'Points deducted successfully.']);
} catch(Exception $e) {
    $pdo->rollBack(); 
    http_response_code(500); 
    echo json_encode(['error' => 'Transaction failed.']);
}
?>