<?php
include('includes/function.php');
include('../config/auth.php');
include('../config/dbcon.php');

global $conn;

if (!isLoggedIn()) {
    header("Location: admin_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    die("CSRF token validation failed");
}

$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

if ($product_id <= 0) {
    $_SESSION['error'] = "Invalid product.";
    header("Location: dashboard.php");
    exit();
}

// Look up the image path and name first — image so we can remove the file
// from disk, name so the confirmation toast can be specific
$stmt = mysqli_prepare($conn, "SELECT name, image_path FROM products WHERE product_id = ?");
mysqli_stmt_bind_param($stmt, "i", $product_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$product) {
    $_SESSION['error'] = "Product not found.";
    header("Location: dashboard.php");
    exit();
}

$delete = mysqli_prepare($conn, "DELETE FROM products WHERE product_id = ?");
mysqli_stmt_bind_param($delete, "i", $product_id);

if (mysqli_stmt_execute($delete)) {
    mysqli_stmt_close($delete);

    // Remove the uploaded image file, but never touch the default placeholder
    if (!empty($product['image_path']) && strpos($product['image_path'], 'no-image.png') === false) {
        $filePath = '../' . $product['image_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    $_SESSION['success'] = htmlspecialchars($product['name']) . " was deleted.";
} else {
    error_log("Product delete failed: " . mysqli_stmt_error($delete));
    $_SESSION['error'] = "Could not delete product. Please try again.";
    mysqli_stmt_close($delete);
}

header("Location: dashboard.php");
exit();