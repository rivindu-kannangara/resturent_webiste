<?php
include('config/dbcon.php');
global $conn;

$search = '';
$products = [];
$errorMsg = '';
$infoMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['searchbtn'])) {
    $search = trim($_GET['search'] ?? '');

    if ($search !== '') {

        $sqlreq = "SELECT product_id, name, description, image_path,
                          price_small, price_medium, price_large,
                          offer, offer_price_small, offer_price_medium, offer_price_large,
                          is_available
                   FROM products
                   WHERE name LIKE ? OR description LIKE ?
                   LIMIT 60";
        $stmt = mysqli_prepare($conn, $sqlreq);

        if ($stmt) {
            $searchParam = '%' . $search . '%';
            mysqli_stmt_bind_param($stmt, "ss", $searchParam, $searchParam);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($result && $result->num_rows > 0) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $products[] = $row;
                }
            } else {
                $infoMsg = 'No products found for "' . htmlspecialchars($search) . '".';
            }

            mysqli_stmt_close($stmt);
        } else {
            $errorMsg = 'Database query failed: ' . htmlspecialchars(mysqli_error($conn));
        }
    } else {
        $infoMsg = 'Please enter a search term.';
    }
}


function render_price_row(string $label, $regular, $offer, bool $hasOffer): string
{
    // Do not display if price is empty, null, or zero
    if ($regular === null || $regular === '' || (float)$regular <= 0) {
        return '';
    }

    $html = '<div class="price-row">';

    $html .= '<span class="price-size">'
          . htmlspecialchars($label)
          . '</span>';

    // Display offer price only if offer exists and is lower than regular price
    if (
        $hasOffer &&
        $offer !== null &&
        $offer !== '' &&
        (float)$offer > 0 &&
        (float)$offer < (float)$regular
    ) {

        $html .= '<span class="price-old">
                    Rs. ' . number_format((float)$regular, 2) . '
                  </span>';

        $html .= '<span class="price-new">
                    Rs. ' . number_format((float)$offer, 2) . '
                  </span>';

    } else {

        $html .= '<span class="price-new">
                    Rs. ' . number_format((float)$regular, 2) . '
                  </span>';
    }

    $html .= '</div>';

    return $html;
}
?>

<?php include('header.php'); ?>

<head>

<title>Search results<?= $search !== '' ? ' — ' . htmlspecialchars($search) : '' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">

<link rel="stylesheet" href="../css/search.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="../css/index.css?v=<?php echo time(); ?>">

</head>

<?php 

include('navbar.php');

?>

<body>
    
    <div class="search-wrap">
      <h1 class="search-heading">
        <?= $search !== '' ? 'Results for "' . htmlspecialchars($search) . '"' : 'Search products' ?>
      </h1>
    
      <?php if ($errorMsg !== ''): ?>
        <p class="search-message error"><?= $errorMsg ?></p>
      <?php elseif ($infoMsg !== ''): ?>
        <p class="search-message"><?= $infoMsg ?></p>
      <?php elseif (!empty($products)): ?>
        <div class="product-grid">
          <?php foreach ($products as $product):
              $hasOffer = (int)($product['offer'] ?? 0) === 1;
              $isAvailable = strtolower((string)($product['availability'] ?? 'Available')) !== 'out of stock';
              $img = $product['image_path'] !== '' ? htmlspecialchars($product['image_path']) : 'images/placeholder.jpg';
          ?>
            <div class="product-card">
              <div class="product-media">
                <img src="admin/productimages/<?= $img ?>" alt="<?= htmlspecialchars($product['name']) ?>" loading="lazy" width="400" height="300">
                <?php if ($hasOffer): ?>
                  <span class="offer-flag">OFFER</span>
                <?php endif; ?>
                <span class="availability-badge <?= $isAvailable ? 'in-stock' : 'out-stock' ?>">
                  <?= $isAvailable ? 'Available' : 'Out of stock' ?>
                </span>
              </div>
              <div class="product-info">
                <h2 class="product-name"><?= htmlspecialchars($product['name']) ?></h2>
                <?php if (!empty($product['description'])): ?>
                  <p class="product-desc"><?= htmlspecialchars($product['description']) ?></p>
                <?php endif; ?>
                <div class="price-list">
                  <?= render_price_row('Small', $product['price_small'], $product['offer_price_small'], $hasOffer) ?>
                  <?= render_price_row('Medium', $product['price_medium'], $product['offer_price_medium'], $hasOffer) ?>
                  <?= render_price_row('Large', $product['price_large'], $product['offer_price_large'], $hasOffer) ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
                
    <?php include('footer.php'); ?>
                
</body>
</html>