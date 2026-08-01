<?php
include('includes/header.php');
include('includes/function.php');
include('../config/auth.php');
include('../config/dbcon.php');
global $conn;

$_SESSION['admin_id'] = '1';


if (!isLoggedIn()) {
    header("Location: admin_login.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ---------------- FILTERS ----------------
$selected_category = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$search_term        = isset($_GET['search']) ? trim($_GET['search']) : '';
$availability        = isset($_GET['availability']) ? $_GET['availability'] : 'all';

$categories = [];
$catResult = mysqli_query($conn, "SELECT category_id, name FROM categories ORDER BY name");
if ($catResult) {
    while ($row = mysqli_fetch_assoc($catResult)) {
        $categories[] = $row;
    }
}

$sql = "SELECT p.product_id, p.name, p.image_path, p.is_available,
               p.price_small, p.price_medium, p.price_large, p.offer,
               c.name AS category_name
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

/**
 * Builds the price markup for one product.
 * - Only sizes with a base price > 0 are shown.
 * - If exactly one size has a price, it's shown alone with no size label.
 * - `offer` is a discount PERCENTAGE (0-100) stored once per product. When
 *   it's > 0, every active size shows its base price crossed out next to
 *   the discounted price.
 */
function renderProductPrices(array $item): string
{
    $sizes = [
        'Small'  => (float)($item['price_small']  ?? 0),
        'Medium' => (float)($item['price_medium'] ?? 0),
        'Large'  => (float)($item['price_large']  ?? 0),
    ];

    $active = array_filter($sizes, fn($base) => $base > 0);

    if (empty($active)) {
        return '<span class="price-empty">Price not set</span>';
    }

    $offerPercent = (int)($item['offer'] ?? 0);
    $hasOffer = $offerPercent > 0 && $offerPercent < 100;
    $showLabels = count($active) > 1;

    $html = '<div class="price-group">';
    if ($hasOffer) {
        $html .= '<span class="offer-badge">-' . $offerPercent . '%</span>';
    }

    foreach ($active as $label => $base) {
        $html .= '<div class="price-row">';
        if ($showLabels) {
            $html .= '<span class="size-label">' . htmlspecialchars($label) . '</span>';
        }
        if ($hasOffer) {
            $discounted = $base - ($base * $offerPercent / 100);
            $html .= '<span class="price-old">$' . number_format($base, 2) . '</span>';
            $html .= '<span class="price-new">$' . number_format($discounted, 2) . '</span>';
        } else {
            $html .= '<span class="price">$' . number_format($base, 2) . '</span>';
        }
        $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
}
?>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../../css/dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../../css/admin-theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <title>Dashboard | Restaurant</title>

</head>

<body data-toast-success="<?= isset($_SESSION['success']) ? htmlspecialchars($_SESSION['success']) : ''; unset($_SESSION['success']); ?>"
      data-toast-error="<?= isset($_SESSION['error']) ? htmlspecialchars($_SESSION['error']) : ''; unset($_SESSION['error']); ?>">
<?php include('includes/navbar.php'); ?>

<div class="page-head">
    <h1>Dashboard</h1>
</div>

<div class="container">
    <?= alertMessage(); ?>

    <form method="get" class="filter-bar">
        <select name="category_id">
            <option value="0">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['category_id']; ?>" <?= $selected_category == $cat['category_id'] ? 'selected' : ''; ?>>
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

    <div class="product-grid">
        <?php if ($products && mysqli_num_rows($products) > 0): ?>
            <?php while ($item = mysqli_fetch_assoc($products)): ?>
                <div class="product-card">
                    <div class="thumb">
                        <img src="productimages/<?= htmlspecialchars($item['image_path'] ?: 'assets/images/no-image.png'); ?>" alt="<?= htmlspecialchars($item['name']); ?>">
                        <?php if ($item['is_available']): ?>
                            <span class="status-flag available">Available</span>
                        <?php else: ?>
                            <span class="status-flag unavailable">Unavailable</span>
                        <?php endif; ?>
                    </div>

                    <div class="card-body">
                        <h5><?= htmlspecialchars($item['name']); ?></h5>

                        <?= renderProductPrices($item); ?>

                        <?php if (!empty($item['category_name'])): ?>
                            <p class="category"><?= htmlspecialchars($item['category_name']); ?></p>
                        <?php endif; ?>

                        <div class="card-buttons">
                            <a href="product_view.php?id=<?= $item['product_id']; ?>" class="btn btn-view">View</a>

                            <form action="product_update.php" method="get">
                                <input type="hidden" name="id" value="<?= $item['product_id']; ?>">
                                <button type="submit" class="btn btn-edit">Edit</button>
                            </form>

                            <form action="product_delete.php" method="post" class="js-delete-form" data-item-name="<?= htmlspecialchars($item['name']); ?>">
                                <input type="hidden" name="product_id" value="<?= $item['product_id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <button type="submit" class="btn btn-delete">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-products">No products match these filters.</div>
        <?php endif; ?>
    </div>
</div>


<div id="confirmModal" class="modal-overlay" hidden>
    <div class="modal-card">
        <div class="modal-icon"><i class="fa-solid fa-trash"></i></div>
        <h3>Delete <span id="modalItemName">this product</span>?</h3>
        <p>This will permanently remove it from the menu. This can't be undone.</p>
        <div class="modal-actions">
            <button type="button" class="btn btn-cancel" id="modalCancel">Cancel</button>
            <button type="button" class="btn btn-delete" id="modalConfirm">Delete</button>
        </div>
    </div>
</div>

<script src="../../js/admin-theme.js?v=<?= time(); ?>"></script>
<script>
function checkSession() {
    fetch('check_session.php')
        .then(res => res.json())
        .then(data => { if (!data.valid) window.location.href = 'admin_login.php'; });
}
window.onload = checkSession;
window.onpageshow = function(event) { if (event.persisted) checkSession(); };

let __pendingDeleteForm = null;

function initDeleteModal() {
    const overlay = document.getElementById('confirmModal');
    if (!overlay) return;

    const cancelBtn  = document.getElementById('modalCancel');
    const confirmBtn = document.getElementById('modalConfirm');
    const nameEl     = document.getElementById('modalItemName');

    document.querySelectorAll('.js-delete-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            __pendingDeleteForm = form;

            if (nameEl) {
                const label = form.getAttribute('data-item-name') || 'this item';
                nameEl.textContent = label;
            }

            overlay.hidden = false;
        });
    });

    function closeModal() {
        overlay.hidden = true;
        __pendingDeleteForm = null;
    }

    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) closeModal();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !overlay.hidden) closeModal();
    });

    if (confirmBtn) {
        confirmBtn.addEventListener('click', function () {
            if (__pendingDeleteForm) {
                overlay.hidden = true;
                __pendingDeleteForm.submit();
            }
        });
    }
}

function ensureToastContainer() {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    return container;
}

function showToast(message, type) {
    if (!message) return;
    type = type === 'error' ? 'error' : 'success';

    const container = ensureToastContainer();
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;

    const icon = document.createElement('span');
    icon.className = 'toast-icon';
    icon.textContent = type === 'error' ? '!' : '\u2713';

    const msg = document.createElement('span');
    msg.className = 'toast-msg';
    msg.textContent = message;

    const closeBtn = document.createElement('button');
    closeBtn.className = 'toast-close';
    closeBtn.setAttribute('aria-label', 'Dismiss');
    closeBtn.textContent = '\u00d7';

    toast.appendChild(icon);
    toast.appendChild(msg);
    toast.appendChild(closeBtn);
    container.appendChild(toast);

    function remove() {
        toast.classList.add('leaving');
        setTimeout(function () { toast.remove(); }, 200);
    }

    closeBtn.addEventListener('click', remove);
    setTimeout(remove, 4500);
}

document.addEventListener('DOMContentLoaded', function () {
    initDeleteModal();

    const body = document.body;
    const successMsg = body.getAttribute('data-toast-success');
    const errorMsg = body.getAttribute('data-toast-error');

    if (successMsg) showToast(successMsg, 'success');
    if (errorMsg) showToast(errorMsg, 'error');
});
</script>

</body>
</html>