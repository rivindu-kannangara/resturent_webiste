<?php
session_start();

include('includes/function.php');
include('../config/auth.php');
include('../config/dbcon.php');
global $conn;

// Guard: don't let people hit this endpoint directly or when not logged in
if (!isLoggedIn()) {
    header("Location: admin_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: addproduct1.php');
    exit();
}
// ---------------- CSRF CHECK ----------------
if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    $_SESSION['flash_error'] = 'Invalid form submission. Please try again.';
    header('Location: addproduct2.php');
    exit();
}

// ---------------- VALIDATE / SANITIZE INPUT ----------------
$category_id  = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
$name         = trim($_POST['product_name'] ?? '');
$price_small  = isset($_POST['price_small'])  ? (float) $_POST['price_small']  : 0;
$price_medium = isset($_POST['price_medium']) ? (float) $_POST['price_medium'] : 0;
$price_large  = isset($_POST['price_large'])  ? (float) $_POST['price_large']  : 0;
$offer        = isset($_POST['offer']) && $_POST['offer'] !== '' ? (int) $_POST['offer'] : 0;
$description  = trim($_POST['description'] ?? '');

if ($category_id <= 0 && $name === '' && $price_small <= 0 && $price_medium <= 0 && $price_large <= 0) {
    $_SESSION['flash_error'] = 'Please fill in all required fields correctly.';
    header('Location: addproduct3.php');
    exit();
}

// ---------------- HANDLE FILE UPLOAD ----------------
if (!isset($_FILES['product_image']) || $_FILES['product_image']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['flash_error'] = 'Please choose a valid image to upload.';
    header('Location: addproduct4.php');
    exit();
}

$allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$mimeType = mime_content_type($_FILES['product_image']['tmp_name']);

if (!isset($allowedTypes[$mimeType])) {
    $_SESSION['flash_error'] = 'Only JPG, PNG, or WEBP images are allowed.';
    header('Location: addproduct5.php');
    exit();
}

$maxBytes = 5 * 1024 * 1024; // 5MB
if ($_FILES['product_image']['size'] > $maxBytes) {
    $_SESSION['flash_error'] = 'Image must be smaller than 5MB.';
    header('Location: addproduct6.php');
    exit();
}

$uploadDir = __DIR__ . '/productimages/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Unique filename so uploads never collide or overwrite each other
$extension   = $allowedTypes[$mimeType];
$newFileName = bin2hex(random_bytes(16)) . '.' . $extension;
$destination = $uploadDir . $newFileName;

if (!move_uploaded_file($_FILES['product_image']['tmp_name'], $destination)) {
    $_SESSION['flash_error'] = 'Image upload failed. Please try again.';
    header('Location: addproduct7.php');
    exit();
}

// ---------------- INSERT (prepared statement) ----------------
$sql = "INSERT INTO products
        (name, price_small, price_medium, price_large, offer, description, image_path, category_id, is_available)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param(
    $stmt,
    'sdddisss',
    $name,
    $price_small,
    $price_medium,
    $price_large,
    $offer,
    $description,
    $newFileName,
    $category_id
);

if (mysqli_stmt_execute($stmt)) {
    $_SESSION['flash_success'] = 'Product added successfully.';
} else {
    // Roll back the uploaded file if the DB insert failed, so we don't leave orphan images
    unlink($destination);
    $_SESSION['flash_error'] = 'Database error: could not save the product.';
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

header('Location: dashboard.php');
exit();