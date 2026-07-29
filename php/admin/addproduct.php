<?php
include('includes/header.php');
include('includes/function.php');
include('../config/auth.php');
include('../config/dbcon.php');
global $conn;

if (!isLoggedIn()) {
    header("Location: shop_worker_login.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}




// ---------------- FILTERS ----------------
$selected_category = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$search_term        = isset($_GET['search']) ? trim($_GET['search']) : '';
$availability        = isset($_GET['availability']) ? $_GET['availability'] : 'all'; // all | available | unavailable

// Categories for the filter dropdown
$categories = [];
$catResult = mysqli_query($conn, "SELECT category_id, name FROM categories ORDER BY name");
if ($catResult) {
    while ($row = mysqli_fetch_assoc($catResult)) {
        $categories[] = $row;
    }
}

// Build the product query dynamically based on filters
$sql = "SELECT p.product_id, p.name, p.price_small, p.price_medium, p.price_large, p.image_path, p.is_available, c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.category_id
        WHERE 1=1";

$params = [];
$types  = '';

if ($selected_category > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $selected_category;
    $types   .= 'i';
}

if ($search_term !== '') {
    $sql .= " AND p.name LIKE ?";
    $params[] = '%' . $search_term . '%';
    $types   .= 's';
}

if ($availability === 'available') {
    $sql .= " AND p.is_available = 1";
} elseif ($availability === 'unavailable') {
    $sql .= " AND p.is_available = 0";
}

$sql .= " ORDER BY p.name";

$stmt = mysqli_prepare($conn, $sql);
if ($params) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$products = mysqli_stmt_get_result($stmt);

// Use file modification time for cache-busting instead of time()
$dashCssVer = filemtime(__DIR__ . '/../../css/dashboard.css');
$prodCssVer = filemtime(__DIR__ . '/../../css/productadd.css');
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../css/dashboard.css?v=<?= $dashCssVer; ?>">
    <link rel="stylesheet" href="../../css/productadd.css?v=<?= $prodCssVer; ?>">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <title>Add A Product | Restaurant</title>
</head>

<body>
    <?php include('includes/navbar.php'); ?>

    <div class="product-adding">

        <span class="heading">Let's Add A Product</span>

        <form action="ProductBackEnd.php" method="POST" enctype="multipart/form-data" id="productForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">

            <div class="inputarea">
                <label for="product_name">Product Name</label>
                <input type="text" placeholder="Cheese Pizza" name="product_name" id="product_name" required>
            </div>

            <div class="inputarea">
                <label for="category_id">Category</label>
                <select name="category_id" id="category_id" required>
                    <option value="" disabled selected>Select a category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int) $cat['category_id']; ?>">
                            <?= htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="inputarea">
                <label class="group-label">Portion Pricing</label>
                <div class="price-row">
                    <div class="price-field">
                        <label for="price_small">Small</label>
                        <div class="price-input">
                            <span class="currency">$</span>
                            <input type="number" step="0.01" min="0" placeholder="10.00"
                                   name="price_small" id="price_small" required>
                        </div>
                    </div>
                    <div class="price-field">
                        <label for="price_medium">Medium</label>
                        <div class="price-input">
                            <span class="currency">$</span>
                            <input type="number" step="0.01" min="0" placeholder="14.00"
                                   name="price_medium" id="price_medium" required>
                        </div>
                    </div>
                    <div class="price-field">
                        <label for="price_large">Large</label>
                        <div class="price-input">
                            <span class="currency">$</span>
                            <input type="number" step="0.01" min="0" placeholder="18.00"
                                   name="price_large" id="price_large" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="inputarea">
                <label for="offer">Offer</label>
                <div class="percent-input">
                    <input type="number" step="1" min="0" max="100" placeholder="20" name="offer" id="offer">
                    <span class="percent-sign">%</span>
                </div>
            </div>

            <div class="inputarea">
                <label for="product_image">Image</label>
                <input type="file" name="product_image" id="product_image" accept="image/png, image/jpeg, image/webp">
                <div class="imagedisplayarea">
                    <img src="" alt="Image preview" class="display_image">
                </div>
            </div>

            <div class="inputarea">
                <label for="description">Description</label>
                <textarea name="description" id="description" rows="3" placeholder="Short description of the dish"></textarea>
            </div>

            <div class="inputarea">
                <button type="submit" name="submitBtn" id="submitBtn">Add Product</button>
            </div>

        </form>
    </div>

    <script>

        // Prevent double-submits and give instant feedback (perceived speed)
        document.getElementById('productForm').addEventListener('submit', function () {
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.textContent = 'Adding...';
        });

        // Show a preview of the uploaded image below the file input
        const imageInput = document.getElementById('product_image');
        const previewImg = document.querySelector('.display_image');

        imageInput.addEventListener('change', function () {
            const file = this.files && this.files[0];

            if (!file) {
                previewImg.src = '';
                previewImg.style.display = 'none';
                return;
            }

            // Basic client-side guard so we don't try to preview non-images
            if (!file.type.startsWith('image/')) {
                previewImg.src = '';
                previewImg.style.display = 'none';
                return;
            }

            const reader = new FileReader();
            reader.onload = function (e) {
                previewImg.src = e.target.result;
                previewImg.style.display = 'block';
            };
            reader.readAsDataURL(file);
        });

    </script>
</body>