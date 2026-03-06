<?php
function verify_jwt($token) {
    $secret = 'mise_en_place_super_secret_key_123'; 
    $tokenParts = explode('.', $token);
    if (count($tokenParts) !== 3) return false;

    $header = base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[0]));
    $payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1]));
    $signature_provided = $tokenParts[2];

    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    $signature_recalculated = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
    $base64UrlSignatureRecalculated = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature_recalculated));

    if (hash_equals($base64UrlSignatureRecalculated, $signature_provided)) {
        $decoded_payload = json_decode($payload, true);
        if (isset($decoded_payload['exp']) && $decoded_payload['exp'] < time()) return false;
        return $decoded_payload;
    }
    return false;
}

function get_bearer_token() {
    $headers = null;
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER["Authorization"]);
    } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }
    if (!empty($headers)) {
        if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) return $matches[1];
    }
    return null;
}
?>