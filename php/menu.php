<?php
include('header.php');

global $conn;

// Adjust to match your actual connection file/variable
include('config/dbcon.php');

// Get all categories that have at least one product
$categoryQuery = "SELECT category_id , name FROM categories ORDER BY name ASC";
$categoryResult = $conn->query($categoryQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu</title>
    <link rel="stylesheet" href="../css/index.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="../css/menu.css?v=<?= time(); ?>">
</head>
<body>

    <div class="backmenu">
        <?php include('navbar.php'); ?>
        <h1 class="MenuHeader">Menu</h1>
    </div>

    <div class="menu-container">

        <?php
        if ($categoryResult && $categoryResult->num_rows > 0) {
            while ($categoryRow = $categoryResult->fetch_assoc()) {
                $categoryId   = $categoryRow['category_id'];
                $categoryName = $categoryRow['name'];

                $stmt = $conn->prepare("SELECT * FROM products WHERE category_id = ?");
                $stmt->bind_param("i", $categoryId);
                $stmt->execute();
                $productResult = $stmt->get_result();

                if ($productResult->num_rows > 0) {
        ?>
                <section class="menu-section">
                    <h2 class="category-title"><?php echo htmlspecialchars($categoryName); ?></h2>
                    <div class="product-grid">
                        <?php
                        while ($product = $productResult->fetch_assoc()) {

                            // Build only the sizes that actually have a real price.
                            // A price counts as "set" if it is not null and greater than 0.
                            $sizes = [
                                'Small'  => $product['price_small'],
                                'Medium' => $product['price_medium'],
                                'Large'  => $product['price_large'],
                            ];

                            $availableSizes = [];
                            foreach ($sizes as $label => $price) {
                                if ($price !== null && (float)$price > 0) {
                                    $availableSizes[$label] = $price;
                                }
                            }

                            $isAvailable = count($availableSizes) > 0;

                            // If the product also has an explicit availability/stock column,
                            // respect that too (defaults to true if the column doesn't exist).
                            if (array_key_exists('is_available', $product)) {
                                $isAvailable = $isAvailable && (bool)$product['is_available'];
                            }
                        ?>
                            <div class="product-card<?php echo $isAvailable ? '' : ' unavailable'; ?>">
                                <div class="product-image">
                                    <img src="admin/productimages/<?php echo htmlspecialchars($product['image_path']); ?>"
                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                         loading="lazy"
                                         width="400" height="300"
                                         decoding="async">
                                    <span class="availability-badge <?php echo $isAvailable ? 'badge-available' : 'badge-unavailable'; ?>">
                                        <?php echo $isAvailable ? 'Available' : 'Not Available'; ?>
                                    </span>
                                </div>
                                <div class="product-info">
                                    <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                    <p class="description"><?php echo htmlspecialchars($product['description']); ?></p>

                                    <?php if ($isAvailable): ?>
                                        <?php if (count($availableSizes) === 1): ?>
                                            <div class="size-options single-price">
                                                <span class="size-price">
                                                    Rs. <?php echo number_format(reset($availableSizes), 2); ?>
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <div class="size-options">
                                                <?php foreach ($availableSizes as $label => $price): ?>
                                                    <label class="size-option">
                                                        <span class="size-label"><?php echo $label; ?></span>
                                                        <span class="size-price">Rs. <?php echo number_format($price, 2); ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="size-options">
                                            <span class="no-price">Currently unavailable</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </section>
        <?php
                }
                $stmt->close();
            }
        } else {
            echo "<p class='no-products'>No categories found.</p>";
        }
        ?>

    </div>

</body>
</html>