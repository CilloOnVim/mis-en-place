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

// We read from $_POST instead of JSON because this is a multipart form
$title = $_POST['title'] ?? '';
$description = $_POST['description'] ?? '';
$category = $_POST['category'] ?? '';
$author_name = $_POST['author_name'] ?? 'Unknown Chef';
$points_cost = isset($_POST['points_cost']) ? (int)$_POST['points_cost'] : 0;
$recipe_data = $_POST['recipe_data'] ?? '{}';

if (empty($title) || empty($description)) {
    http_response_code(400);
    die(json_encode(['error' => 'Title and description are required.']));
}

$image_url = null;

// Handle the Binary File Upload
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }

    $file_tmp = $_FILES['photo']['tmp_name'];
    $file_name = $_FILES['photo']['name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (in_array($file_ext, $allowed_exts)) {
        // Generate a secure, unique filename to prevent overwrites
        $new_file_name = uniqid('post_') . '.' . $file_ext;
        $destination = $upload_dir . $new_file_name;

        if (move_uploaded_file($file_tmp, $destination)) {
            $image_url = $new_file_name;
        } else {
            http_response_code(500);
            die(json_encode(['error' => 'Failed to save uploaded image to server.']));
        }
    } else {
        http_response_code(400);
        die(json_encode(['error' => 'Invalid file type. Only JPG, PNG, and WebP are allowed.']));
    }
}

try {
    $stmt = $pdo->prepare("INSERT INTO posts (user_id, author_name, title, description, category, points_cost, recipe_data, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $author_name, $title, $description, $category, $points_cost, $recipe_data, $image_url]);
    
    http_response_code(201);
    echo json_encode(['message' => 'Post created successfully.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database failure.']);
}
?>