<?php
session_start();
include('admin/includes/header.php');
include('admin/includes/function.php');
include('config/dbcon.php');
global $conn;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ---------------- FILTERS ----------------
$selected_category = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$search_term        = isset($_GET['search']) ? trim($_GET['search']) : '';

// Categories for the filter dropdown
$categories = [];
$catResult = mysqli_query($conn, "SELECT category_id, name FROM categories ORDER BY name");
if ($catResult) {
    while ($catRow = mysqli_fetch_assoc($catResult)) {
        $categories[] = $catRow;
    }
}

// Only ever show available products to customers
$sql = "SELECT p.product_id, p.name, p.description, p.price_small, p.price_medium, p.price_large,
               p.offer, p.image_path, c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.category_id
        WHERE p.is_available = 1";

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

$sql .= " ORDER BY p.name";

$stmt = mysqli_prepare($conn, $sql);
if ($params) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$products = mysqli_stmt_get_result($stmt);

$cssVer = filemtime(__DIR__ . '/../css/productdisplay.css');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../css/productdisplay.css?v=<?= $cssVer; ?>">
    <link rel="stylesheet" href="../css/index.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" media="print" onload="this.media='all'">
    <title>Menu | Restaurant</title>
</head>
<?php include('header.php'); ?>
<body>
<?php include('navbar.php'); ?>

<div class="menu-container">

    <form method="get" class="filter-bar">
        <select name="category_id" onchange="this.form.submit()">
            <option value="0">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= (int) $cat['category_id']; ?>" <?= $selected_category == $cat['category_id'] ? 'selected' : ''; ?>>
                    <?= htmlspecialchars($cat['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input type="text" name="search" placeholder="Search the menu..." value="<?= htmlspecialchars($search_term); ?>">
        <button type="submit">Search</button>
    </form>

    <div class="menu-grid">
        <?php if ($products && mysqli_num_rows($products) > 0): ?>
            <?php while ($item = mysqli_fetch_assoc($products)): ?>
                <?php
                    $hasOffer   = !empty($item['offer']) && $item['offer'] > 0;
                    $offerMult  = $hasOffer ? (1 - ($item['offer'] / 100)) : 1;
                ?>
                <div class="product-card" data-product-id="<?= (int) $item['product_id']; ?>">
                    <div class="product-img-wrap">
                        <img
                            src="<?= htmlspecialchars($item['image_path']); ?>"
                            alt="<?= htmlspecialchars($item['name']); ?>"
                            loading="lazy"
                            decoding="async"
                            width="300" height="200">
                        <?php if ($hasOffer): ?>
                            <span class="offer-badge"><?= (int) $item['offer']; ?>% OFF</span>
                        <?php endif; ?>
                    </div>

                    <div class="product-body">
                        <h3 class="product-name"><?= htmlspecialchars($item['name']); ?></h3>
                        <?php if (!empty($item['category_name'])): ?>
                            <span class="product-category"><?= htmlspecialchars($item['category_name']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($item['description'])): ?>
                            <p class="product-desc"><?= htmlspecialchars($item['description']); ?></p>
                        <?php endif; ?>

                        <div class="portion-select">
                            <label>
                                <input type="radio" name="portion_<?= $item['product_id']; ?>" value="small"
                                       data-price="<?= round($item['price_small'] * $offerMult, 2); ?>" checked>
                                Small – $<?= number_format($item['price_small'] * $offerMult, 2); ?>
                            </label>
                            <label>
                                <input type="radio" name="portion_<?= $item['product_id']; ?>" value="medium"
                                       data-price="<?= round($item['price_medium'] * $offerMult, 2); ?>">
                                Medium – $<?= number_format($item['price_medium'] * $offerMult, 2); ?>
                            </label>
                            <label>
                                <input type="radio" name="portion_<?= $item['product_id']; ?>" value="large"
                                       data-price="<?= round($item['price_large'] * $offerMult, 2); ?>">
                                Large – $<?= number_format($item['price_large'] * $offerMult, 2); ?>
                            </label>
                        </div>

                        <div class="qty-row">
                            <button type="button" class="qty-btn qty-minus">−</button>
                            <input type="number" class="qty-input" value="1" min="1" max="20">
                            <button type="button" class="qty-btn qty-plus">+</button>
                        </div>

                        <div class="product-actions">
                            <button type="button" class="btn-add-cart">Add to Cart</button>
                            <button type="button" class="btn-order-now">Order Now</button>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-products">No products found.</div>
        <?php endif; ?>
    </div>
</div>

<div id="toast" class="toast"></div>

<script>
const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token']); ?>;

function showToast(message, isError = false) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = 'toast show' + (isError ? ' error' : '');
    setTimeout(() => { toast.className = 'toast'; }, 2200);
}

document.querySelectorAll('.product-card').forEach(card => {
    const productId = card.dataset.productId;
    const qtyInput  = card.querySelector('.qty-input');

    card.querySelector('.qty-minus').addEventListener('click', () => {
        qtyInput.value = Math.max(1, parseInt(qtyInput.value || 1) - 1);
    });
    card.querySelector('.qty-plus').addEventListener('click', () => {
        qtyInput.value = Math.min(20, parseInt(qtyInput.value || 1) + 1);
    });

    function getSelectedPortion() {
        const checked = card.querySelector('input[type="radio"]:checked');
        return { portion: checked.value, price: parseFloat(checked.dataset.price) };
    }

    function sendToCart(action, button) {
        const { portion, price } = getSelectedPortion();
        const qty = parseInt(qtyInput.value || 1);
        const originalText = button.textContent;

        button.disabled = true;
        button.textContent = '...';

        fetch('cart_add.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                csrf_token: CSRF_TOKEN,
                product_id: productId,
                portion: portion,
                price: price,
                quantity: qty,
                action: action // 'cart' or 'order_now'
            })
        })
        .then(res => res.json())
        .then(data => {
            button.disabled = false;
            button.textContent = originalText;

            if (data.success) {
                if (action === 'order_now') {
                    window.location.href = 'checkout.php';
                } else {
                    showToast(`Added ${qty} × ${data.product_name} to cart`);
                    const cartCount = document.getElementById('cart-count');
                    if (cartCount) cartCount.textContent = data.cart_count;
                }
            } else {
                showToast(data.message || 'Something went wrong', true);
            }
        })
        .catch(() => {
            button.disabled = false;
            button.textContent = originalText;
            showToast('Network error — please try again', true);
        });
    }

    card.querySelector('.btn-add-cart').addEventListener('click', function () {
        sendToCart('cart', this);
    });
    card.querySelector('.btn-order-now').addEventListener('click', function () {
        sendToCart('order_now', this);
    });
});
</script>

</body>
</html>