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

$product_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['product_id']) ? intval($_POST['product_id']) : 0);

if ($product_id <= 0) {
    $_SESSION['error'] = 'Invalid product.';
    header("Location: dashboard.php");
    exit();
}

$errors = [];

// ---------------- HANDLE SAVE ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Session expired, please try again.';
        header("Location: product_update.php?id=" . $product_id);
        exit();
    }

    $name          = trim($_POST['name'] ?? '');
    $description   = trim($_POST['description'] ?? '');
    $category_id   = intval($_POST['category_id'] ?? 0);
    $is_available  = isset($_POST['is_available']) ? 1 : 0;

    // Prices: blank/negative treated as 0 (= "this size isn't sold")
    $price_small   = max(0, (float)($_POST['price_small']  ?? 0));
    $price_medium  = max(0, (float)($_POST['price_medium'] ?? 0));
    $price_large   = max(0, (float)($_POST['price_large']  ?? 0));

    // offer is a discount PERCENTAGE (0-100), applied to every active size
    $offer = intval($_POST['offer'] ?? 0);
    $offer = max(0, min(100, $offer));

    if ($name === '') {
        $errors[] = 'Product name is required.';
    }
    if ($price_small <= 0 && $price_medium <= 0 && $price_large <= 0) {
        $errors[] = 'At least one size must have a price greater than 0.';
    }

    // ---- optional image replace ----
    $image_path = null; // null = keep existing
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed, true)) {
            $errors[] = 'Image must be a jpg, jpeg, png, or webp file.';
        } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Image must be smaller than 5MB.';
        } else {
            $uploadDir = 'assets/images/products/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $newFileName = 'product_' . $product_id . '_' . time() . '.' . $ext;
            $destination = $uploadDir . $newFileName;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                $image_path = $destination;
            } else {
                $errors[] = 'Could not upload the image, please try again.';
            }
        }
    }

    if (empty($errors)) {
        if ($image_path !== null) {
            $sql = "UPDATE products SET
                        name = ?, description = ?, category_id = ?, is_available = ?,
                        price_small = ?, price_medium = ?, price_large = ?, offer = ?,
                        image_path = ?
                    WHERE product_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param(
                $stmt,
                'ssiidddisi',
                $name, $description, $category_id, $is_available,
                $price_small, $price_medium, $price_large, $offer,
                $image_path, $product_id
            );
        } else {
            $sql = "UPDATE products SET
                        name = ?, description = ?, category_id = ?, is_available = ?,
                        price_small = ?, price_medium = ?, price_large = ?, offer = ?
                    WHERE product_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param(
                $stmt,
                'ssiidddii',
                $name, $description, $category_id, $is_available,
                $price_small, $price_medium, $price_large, $offer,
                $product_id
            );
        }

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = 'Product updated successfully.';
            header("Location: dashboard.php");
            exit();
        } else {
            $errors[] = 'Database error, please try again.';
        }
    }
}

// ---------------- LOAD CURRENT PRODUCT ----------------
$stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE product_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $product_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($result);

if (!$product) {
    $_SESSION['error'] = 'Product not found.';
    header("Location: dashboard.php");
    exit();
}

$categories = [];
$catResult = mysqli_query($conn, "SELECT category_id, name FROM categories ORDER BY name");
if ($catResult) {
    while ($row = mysqli_fetch_assoc($catResult)) {
        $categories[] = $row;
    }
}

// Repopulate the form with submitted values if validation failed, otherwise use DB values
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$form = [
    'name'         => $_POST['name']         ?? $product['name'],
    'description'  => $_POST['description']  ?? $product['description'],
    'category_id'  => $_POST['category_id']  ?? $product['category_id'],
    'is_available' => $isPost ? isset($_POST['is_available']) : (bool)$product['is_available'],
    'price_small'  => $_POST['price_small']  ?? $product['price_small'],
    'price_medium' => $_POST['price_medium'] ?? $product['price_medium'],
    'price_large'  => $_POST['price_large']  ?? $product['price_large'],
    'offer'        => $_POST['offer']        ?? $product['offer'],
];
?>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../../css/dashboard.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="../../css/admin-theme.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <title>Edit Product | Restaurant</title>
    <style>
        body { background-color: var(--bg, #15120F); }

        .form-container {
            width: 100%;
            max-width: 760px;
            margin: 0 auto;
            padding: 0 20px 3rem;
        }

        .edit-card {
            background: var(--panel, #1e1a15);
            border: 1px solid var(--border, #2c261e);
            border-radius: 14px;
            padding: 1.75rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem 1.25rem;
        }

        .form-grid .full { grid-column: 1 / -1; }

        .field label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--muted, #9c948b);
            margin-bottom: 0.35rem;
        }

        .field input[type="text"],
        .field input[type="number"],
        .field textarea,
        .field select {
            width: 100%;
            padding: 0.6rem 0.75rem;
            border-radius: 8px;
            border: 1px solid var(--border, #2c261e);
            background: var(--bg, #15120F);
            color: #eee;
            font-size: 0.95rem;
            font-family: inherit;
        }

        .field textarea {
            resize: vertical;
            min-height: 80px;
        }

        .price-tiers {
            grid-column: 1 / -1;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-top: 0.25rem;
        }

        .tier-box {
            border: 1px solid var(--border, #2c261e);
            border-radius: 10px;
            padding: 0.85rem;
        }

        .tier-box h4 {
            margin: 0 0 0.6rem;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: var(--accent, #e8a33d);
        }

        .availability-row {
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .current-image {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 0.5rem;
        }

        .current-image img {
            width: 64px;
            height: 64px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--border, #2c261e);
        }

        .form-errors {
            background: rgba(220, 80, 80, 0.12);
            border: 1px solid rgba(220, 80, 80, 0.4);
            color: #e88;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
        }
        .form-errors ul { margin: 0; padding-left: 1.1rem; }

        .form-actions {
            grid-column: 1 / -1;
            display: flex;
            gap: 0.75rem;
            margin-top: 0.5rem;
        }

        /* ---- responsive ---- */
        @media (max-width: 640px) {
            .form-grid { grid-template-columns: 1fr; }
            .price-tiers { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body>
<?php include('includes/navbar.php'); ?>

<div class="page-head">
    <p class="eyebrow">Menu Management</p>
    <h1>Edit Product</h1>
</div>

<div class="form-container">
    <div class="edit-card">

        <?php if (!empty($errors)): ?>
            <div class="form-errors">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="product_id" value="<?= $product_id; ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">

            <div class="form-grid">

                <div class="field full">
                    <label>Current Image</label>
                    <div class="current-image">
                        <img src="<?= htmlspecialchars($product['image_path'] ?: 'assets/images/no-image.png'); ?>" alt="">
                        <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
                    </div>
                </div>

                <div class="field">
                    <label for="name">Product Name</label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($form['name']); ?>" required>
                </div>

                <div class="field">
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['category_id']; ?>" <?= $form['category_id'] == $cat['category_id'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field full">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"><?= htmlspecialchars($form['description']); ?></textarea>
                </div>

                <div class="field full availability-row">
                    <input type="checkbox" id="is_available" name="is_available" <?= $form['is_available'] ? 'checked' : ''; ?>>
                    <label for="is_available" style="margin:0;">Available for sale</label>
                </div>

                <div class="field">
                    <label for="offer">Offer Discount (%)</label>
                    <input type="number" min="0" max="100" id="offer" name="offer" value="<?= htmlspecialchars($form['offer']); ?>">
                </div>

                <div class="price-tiers">
                    <div class="tier-box">
                        <h4>Small</h4>
                        <div class="field">
                            <label for="price_small">Price</label>
                            <input type="number" step="0.01" min="0" id="price_small" name="price_small" value="<?= htmlspecialchars($form['price_small']); ?>">
                        </div>
                    </div>

                    <div class="tier-box">
                        <h4>Medium</h4>
                        <div class="field">
                            <label for="price_medium">Price</label>
                            <input type="number" step="0.01" min="0" id="price_medium" name="price_medium" value="<?= htmlspecialchars($form['price_medium']); ?>">
                        </div>
                    </div>

                    <div class="tier-box">
                        <h4>Large</h4>
                        <div class="field">
                            <label for="price_large">Price</label>
                            <input type="number" step="0.01" min="0" id="price_large" name="price_large" value="<?= htmlspecialchars($form['price_large']); ?>">
                        </div>
                    </div>
                </div>

                <p class="full" style="font-size:0.8rem; color: var(--muted, #9c948b); margin: -0.25rem 0 0;">
                    Leave a size's price at 0 to hide it on the dashboard. Set "Offer Discount" above 0
                    to show that percentage off on every size that has a price.
                </p>

                <div class="form-actions">
                    <button type="submit" class="btn btn-edit">Save Changes</button>
                    <a href="dashboard.php" class="reset-link">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="../../js/admin-theme.js?v=<?= time(); ?>"></script>
</body>
</html>