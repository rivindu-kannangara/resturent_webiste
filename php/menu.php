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
    <link rel="stylesheet" href="../css/index.css">
    <link rel="stylesheet" href="../css/menu.css">
</head>
<body>

    <?php include('navbar.php'); ?>

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
                        <?php while ($product = $productResult->fetch_assoc()) { ?>
                            <div class="product-card">
                                <div class="product-image">
                                    <img src="php/uploads/<?php echo htmlspecialchars($product['image']); ?>"
                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                         loading="lazy">
                                </div>
                                <div class="product-info">
                                    <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                    <p class="description"><?php echo htmlspecialchars($product['description']); ?></p>
                            
                                    <div class="size-options">
                                        <label class="size-option">                                  
                                            <span class="size-label">Small</span>
                                            <span class="size-price">Rs. <?php echo number_format($product['price_small'], 2); ?></span>
                                        </label>
                                        <label class="size-option">
                                            <span class="size-label">Medium</span>
                                            <span class="size-price">Rs. <?php echo number_format($product['price_medium'], 2); ?></span>
                                        </label>
                                        <label class="size-option">
                                            <span class="size-label">Large</span>
                                            <span class="size-price">Rs. <?php echo number_format($product['price_large'], 2); ?></span>
                                        </label>
                                    </div>
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