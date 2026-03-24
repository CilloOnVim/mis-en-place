<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit(); }

require 'db.php';

$data = json_decode(file_get_contents("php://input"));
$token = $data->token ?? '';
$new_password = $data->new_password ?? '';

if (empty($token) || empty($new_password) || strlen($new_password) < 6) {
    http_response_code(400);
    die(json_encode(['error' => 'Valid token and a minimum 6-character password are required.']));
}

// Find user by token AND check if it's still unexpired
$stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid or expired reset token.']));
}

// Hash the new password
$password_hash = password_hash($new_password, PASSWORD_BCRYPT);

// Update the password and wipe the token data for security
$update = $pdo->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
if ($update->execute([$password_hash, $user['id']])) {
    http_response_code(200);
    echo json_encode(['message' => 'Password has been successfully reset. You can now log in.']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to reset password.']);
}
?>