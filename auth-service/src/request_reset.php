<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit(); }

require 'db.php';

$data = json_decode(file_get_contents("php://input"));
$email = $data->email ?? '';

if (empty($email)) {
    http_response_code(400);
    die(json_encode(['error' => 'Email is required.']));
}

// Check if user exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    // Security best practice: Do not reveal if an email is registered or not to prevent scraping.
    // Just pretend it worked.
    http_response_code(200);
    die(json_encode(['message' => 'If that email exists, a reset token has been generated.']));
}

// Generate a secure random token
$token = bin2hex(random_bytes(32));
// Set expiration for 15 minutes from now
$expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

// Save token to database
$update = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
$update->execute([$token, $expires, $user['id']]);

http_response_code(200);
// DEVELOPER OVERRIDE: In production, you use PHPMailer here. 
// Because we have no email server, we are returning the token directly in the JSON so you can test it.
echo json_encode([
    'message' => 'Token generated successfully.',
    'dev_token' => $token // You will copy this from the frontend alert to test the reset
]);
?>