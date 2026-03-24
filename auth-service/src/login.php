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
require 'jwt.php';

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->email) && !empty($data->password)) {
    // 1. Ask the database for the status alongside the other credentials
    $stmt = $pdo->prepare("SELECT id, password_hash, role, status FROM users WHERE email = ?");
    $stmt->execute([$data->email]);
    $user = $stmt->fetch();

    if ($user && password_verify($data->password, $user['password_hash'])) {
        
        // THE BOUNCER: Check if the user is banned BEFORE generating the token
        // Adding isset() just in case the column is completely missing from a legacy row
        if (isset($user['status']) && $user['status'] === 'banned') {
            http_response_code(403);
            die(json_encode(['error' => 'This account has been permanently banned by an Administrator.']));
        }

        // Inject the role into the JWT payload
        $payload = [
            'user_id' => $user['id'],
            'role' => $user['role'], 
            'exp' => time() + 3600
        ];
        
        $token = generate_jwt($payload);
        
        http_response_code(200);
        // Send the role back in the JSON response so the frontend knows immediately
        echo json_encode([
            'message' => 'Login successful',
            'token' => $token,
            'role' => $user['role'] // THIS IS CRITICAL
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Incomplete data. Email and password required.']);
}
?>