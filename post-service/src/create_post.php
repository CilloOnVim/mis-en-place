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

if (!empty($data->title) && !empty($data->description)) {
    $category = $data->category ?? 'General';
    $points_cost = $data->points_cost ?? 0;
    
    // In the future, frontend will send ingredients here. For now, empty JSON object.
    $recipe_data = json_encode($data->recipe_data ?? []); 

    $stmt = $pdo->prepare("INSERT INTO posts (user_id, title, description, category, points_cost, recipe_data) VALUES (?, ?, ?, ?, ?, ?)");
    
    try {
        $stmt->execute([$user_id, $data->title, $data->description, $category, $points_cost, $recipe_data]);
        http_response_code(201);
        echo json_encode(['message' => 'Recipe published successfully!', 'post_id' => $pdo->lastInsertId()]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save recipe.']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Title and description are required.']);
}
?>