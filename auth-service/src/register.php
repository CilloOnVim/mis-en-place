<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// The browser's preflight test
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require 'db.php';

// Get raw POST data
$data = json_decode(file_get_contents("php://input"));

if (!empty($data->email) && !empty($data->password)) {
    $email = $data->email;
    $password_hash = password_hash($data->password, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)");
    
    try {
        $stmt->execute([$email, $password_hash]);
        $user_id = $pdo->lastInsertId();
        http_response_code(201);
        echo json_encode(['message' => 'User registered successfully', 'user_id' => $user_id]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => 'Email already exists or database error']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Incomplete data. Email and password required.']);
}
?>