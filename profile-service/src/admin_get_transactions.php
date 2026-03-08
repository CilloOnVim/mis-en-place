<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit(); }

require 'db.php';
require 'jwt_verify.php';

$token = get_bearer_token();
if (!$token || !verify_jwt($token)) {
    http_response_code(401);
    die(json_encode(['error' => 'Access denied.']));
}

// Fetch the 50 most recent transactions
try {
    $stmt = $pdo->query("SELECT id, buyer_name, php_amount, points_added, created_at FROM transactions ORDER BY created_at DESC LIMIT 50");
    $transactions = $stmt->fetchAll();
    
    // Also get the total revenue to display in the quick stats
    $rev_stmt = $pdo->query("SELECT SUM(php_amount) as total_revenue, SUM(points_added) as total_points FROM transactions");
    $totals = $rev_stmt->fetch();

    http_response_code(200);
    echo json_encode([
        'data' => $transactions,
        'totals' => [
            'revenue' => $totals['total_revenue'] ?? 0,
            'points' => $totals['total_points'] ?? 0
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch ledger.']);
}
?>