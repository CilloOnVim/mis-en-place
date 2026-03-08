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

if (isset($data->points) && isset($data->php_amount)) {
    $points = (int)$data->points;
    $php_amount = (float)$data->php_amount;
    $buyer_name = $data->buyer_name ?? 'Unknown Chef';

    try {
        $pdo->beginTransaction();

        // 1. Update the user's wallet (UPSERT just in case they don't have a profile row yet)
        $stmt1 = $pdo->prepare("INSERT INTO profiles (user_id, points_balance) VALUES (?, ?) ON DUPLICATE KEY UPDATE points_balance = points_balance + ?");
        $stmt1->execute([$user_id, $points, $points]);

        // 2. Log the transaction for the admin
        $stmt2 = $pdo->prepare("INSERT INTO transactions (user_id, buyer_name, php_amount, points_added) VALUES (?, ?, ?, ?)");
        $stmt2->execute([$user_id, $buyer_name, $php_amount, $points]);

        $pdo->commit();
        http_response_code(200);
        echo json_encode(['message' => "Successfully purchased $points points!"]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Transaction failed.']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid package data.']);
}
?>