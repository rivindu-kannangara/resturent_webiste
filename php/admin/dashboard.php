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
    while ($catRow = mysqli_fetch_assoc($catResult)) {
        $categories[] = $catRow;
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

// filemtime() instead of time() so the CSS actually caches between page loads
$dashCssVer = filemtime(__DIR__ . '/../../css/dashboard.css');
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../css/dashboard.css?v=<?= $dashCssVer; ?>">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <title>Dashboard | Restaurant</title>
</head>

<body>
<?php include('includes/navbar.php'); ?>

<h4 class="header">Dashboard</h4>

<div class="container">
    <?= alertMessage(); ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Filter bar -->
    <form method="get" class="filter-bar">
        <select name="category_id">
            <option value="0">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= (int) $cat['category_id']; ?>" <?= $selected_category == $cat['category_id'] ? 'selected' : ''; ?>>
                    <?= htmlspecialchars($cat['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="availability">
            <option value="all" <?= $availability === 'all' ? 'selected' : ''; ?>>All Products</option>
            <option value="available" <?= $availability === 'available' ? 'selected' : ''; ?>>Available Only</option>
            <option value="unavailable" <?= $availability === 'unavailable' ? 'selected' : ''; ?>>Unavailable Only</option>
        </select>

        <input type="text" name="search" placeholder="Search products..." value="<?= htmlspecialchars($search_term); ?>">

        <button type="submit">Apply Filters</button>
        <a href="dashboard.php" class="reset-link">Reset</a>
    </form>

    <div class="ads-grid">
        <?php if ($products && mysqli_num_rows($products) > 0): ?>
            <?php while ($item = mysqli_fetch_assoc($products)): ?>
                <div class="ad-card">
                    <img src="productimages/<?= htmlspecialchars($item['image_path']); ?>" alt="<?= htmlspecialchars($item['name']); ?>">
                    <div class="ad-card-body">
                        <h5><?= htmlspecialchars($item['name']); ?></h5>
                        <p class="price">
                            Small: $<?= number_format($item['price_small'], 2); ?> |
                            Medium: $<?= number_format($item['price_medium'], 2); ?> |
                            Large: $<?= number_format($item['price_large'], 2); ?>
                        </p>
                        <?php if (!empty($item['category_name'])): ?>
                            <p class="category"><?= htmlspecialchars($item['category_name']); ?></p>
                        <?php endif; ?>

                        <?php if ($item['is_available']): ?>
                            <span class="badge-status badge-available">Available</span>
                        <?php else: ?>
                            <span class="badge-status badge-unavailable">Unavailable</span>
                        <?php endif; ?>

                        <div class="ad-buttons">
                            <a href="../product_view.php?id=<?= $item['product_id']; ?>" class="card-button" id="view">View</a>

                            <form action="product_edit.php" class="cardform" method="get">
                                <input type="hidden" name="id" value="<?= $item['product_id']; ?>">
                                <button type="submit" class="card-button" id="edit">Edit</button>
                            </form>

                            <form action="product_delete.php" class="cardform" method="post" onsubmit="return confirm('Delete this product?');">
                                <input type="hidden" name="product_id" value="<?= $item['product_id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <button type="submit" class="card-button" id="delete">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-products">No products found.</div>
        <?php endif; ?>
    </div>
</div>

<script>
    function checkSession() {
        fetch('check_session.php')
            .then(res => res.json())
            .then(data => {
                if (!data.valid) window.location.href = 'shop_worker_login.php';
            });
    }
    window.onload = checkSession;
    window.onpageshow = function (event) {
        if (event.persisted) checkSession();
    };
</script>

</body>
</html>