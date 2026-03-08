<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require 'db.php';

// Manually verify token to avoid dependency conflicts in the auth folder
function verify_admin_token() {
    $headers = null;
    if (isset($_SERVER['Authorization'])) { $headers = trim($_SERVER["Authorization"]); }
    elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) { $headers = trim($_SERVER["HTTP_AUTHORIZATION"]); }
    elseif (function_exists('apache_request_headers')) {
        $reqHeaders = apache_request_headers();
        $reqHeaders = array_combine(array_map('ucwords', array_keys($reqHeaders)), array_values($reqHeaders));
        if (isset($reqHeaders['Authorization'])) { $headers = trim($reqHeaders['Authorization']); }
    }
    if (!empty($headers) && preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
        $token = $matches[1];
        $tokenParts = explode('.', $token);
        if (count($tokenParts) === 3) {
            $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1])), true);
            return $payload;
        }
    }
    return false;
}

$payload = verify_admin_token();
if (!$payload || !isset($payload['user_id'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Access denied.']));
}

$admin_id = $payload['user_id'];

// Check if the requesting user is actually an admin
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$admin_id]);
$admin_user = $stmt->fetch();

if (!$admin_user || $admin_user['role'] !== 'admin') {
    http_response_code(403);
    die(json_encode(['error' => 'Forbidden. Admins only.']));
}

// Fetch all registered users for the monitoring table
$stmt = $pdo->query("SELECT id, email, role, created_at FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

http_response_code(200);
echo json_encode(['data' => $users]);
?>