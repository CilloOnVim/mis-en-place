<?php
function generate_jwt($payload) {
    // DO NOT use this secret key in production, but it is fine for your class.
    $secret = 'mise_en_place_super_secret_key_123'; 

    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));
    
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}
?>