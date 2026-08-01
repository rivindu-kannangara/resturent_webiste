<?php

if (!isset($conn)) {
    include('php/config/dbcon.php');
}

$offersSql = "SELECT product_id, name, image_path,
                     price_small, price_medium, price_large,
                     offer_price_small, offer_price_medium, offer_price_large
              FROM products
              WHERE offer != 0 
                AND is_available = 1
                
              ORDER BY product_id DESC
              LIMIT 12";

$offersResult = mysqli_query($conn, $offersSql);
$offerProducts = [];

if ($offersResult) {
    while ($row = mysqli_fetch_assoc($offersResult)) {
        $offerProducts[] = $row;
    }
}

$hasOffers = !empty($offerProducts);

// Adjust this to wherever product images are actually served from publicly
$imageBasePath = 'php/admin/productimages/';

function get_offer_display(array $row): array {
    $sizes = [
        'small'  => ['price' => (float)$row['price_small'],  'offer' => (float)$row['offer_price_small']],
        'medium' => ['price' => (float)$row['price_medium'], 'offer' => (float)$row['offer_price_medium']],
        'large'  => ['price' => (float)$row['price_large'],  'offer' => (float)$row['offer_price_large']],
    ];

    foreach ($sizes as $s) {
        if ($s['price'] > 0 && $s['offer'] > 0 && $s['offer'] < $s['price']) {
            $percent = round((1 - ($s['offer'] / $s['price'])) * 100);
            return ['original' => $s['price'], 'offer' => $s['offer'], 'percent' => $percent];
        }
    }

    return ['original' => 0, 'offer' => 0, 'percent' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">

<?php include('php/header.php'); ?>

<head>
    <link rel="stylesheet" href="css/index.css?v<?php echo time() ?>">
    <link rel="stylesheet" href="css/footer.css?v<?php echo time() ?>">
    <title>Resturent</title>
</head>

<body>

    <!--navbar-->

    <div class="upperbody">

        <nav class="navbar navbar-expand-lg ">
          <div class="container-fluid">
            <a class="navbar-brand" href="#"><img src="images/Barbecue Burger.jpg" alt=""  srcset=""></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
              <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
              <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                  <a class="nav-link" aria-current="page" href="index.php"><i class="fa-solid fa-house"></i> Home</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="php/contact.php"><i class="fa-solid fa-address-card"></i> Contact Us</a>
                </li>

                <li class="nav-item">
                  <a class="nav-link" href="php/admin_login.php"><i class="fa-solid fa-user-lock"></i> Login</a>
                </li>                
                
                <li class="nav-item">
                  <a class="nav-link" href="php/menu.php"><i class="fa-solid fa-book"></i> Menu</a>
                </li>
              </ul>

              <ul class="navbar-nav mb-2 mb-lg-0">
                <li class="nav-item">
                  <a class="nav-link" href="#"><i class="fa-solid fa-phone"></i> +94 71234567</a>
                </li>
              </ul>

              <form class="d-flex" action="php/search.php" method="GET" role="search">
                <input class="form-control me-2" name = "search" type="search" placeholder="Search" aria-label="Search"/>
                <button class="btn btn-outline-warning" name="searchbtn" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
              </form>
            </div>
          </div>
        </nav>


         <div class="upperbodyquate">
            Crafted with Passion, <br> Served with Love...
        </div>

        <div class="button">
            <button class="upper_order_button" onclick="window.location.href='php/menu.php';">View Menu <i class="fa-solid fa-arrow-right fa-jello"></i></button>
        </div>
    </div>

    <!-- subparts -->
    <div class="middle_body">

        <?php if ($hasOffers): ?>

        <link rel="preconnect" href="https://cdn.jsdelivr.net">

        <section class="offers" aria-label="Today's best offers">
            <div class="offers-head">
                <p class="offers-eyebrow">Limited Time</p>
                <h2 class="offers-title">Today's Best Offers</h2>
            </div>

            <div class="offers-swiper swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($offerProducts as $product):
                        $deal = get_offer_display($product);
                        $imgSrc = $imageBasePath . $product['image_path'];
                    ?>
                        <div class="swiper-slide">
                                <div class="offer-media">
                                    <img src="<?=htmlspecialchars($imgSrc); ?>"
                                         alt="<?= htmlspecialchars($product['name']); ?>"
                                         loading="lazy"
                                         decoding="async"
                                         width="320" height="220">
                                    <?php if ($deal['percent'] > 0): ?>
                                        <span class="offer-ribbon"><?= (int) $deal['percent']; ?>% OFF</span>
                                    <?php endif; ?>
                                </div>
                                <div class="offer-info">
                                    <h3 class="offer-name"><?= htmlspecialchars($product['name']); ?></h3>
                                    <div class="offer-prices">
                                        <span class="offer-price-old">$<?= number_format($deal['original'], 2); ?></span>
                                        <span class="offer-price-new">$<?= number_format($deal['offer'], 2); ?></span>
                                    </div>
                                </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="swiper-button-next offers-nav"></div>
                <div class="swiper-button-prev offers-nav"></div>
                <div class="swiper-pagination"></div>
            </div>
        </section>

        <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js" defer></script>
        <script defer>
        document.addEventListener('DOMContentLoaded', function () {
            function initOffersSwiper() {
                if (typeof Swiper === 'undefined') {
                    return setTimeout(initOffersSwiper, 50);
                }
                new Swiper('.offers-swiper', {
                    loop: false,
                    speed: 500,
                    grabCursor: true,
                    autoplay: { delay: 4000, disableOnInteraction: false },
                    spaceBetween: 20,
                    slidesPerView: 1.15,
                    navigation: {
                        nextEl: '.offers-swiper .swiper-button-next',
                        prevEl: '.offers-swiper .swiper-button-prev',
                    },
                    pagination: {
                        el: '.offers-swiper .swiper-pagination',
                        clickable: true,
                    },
                    breakpoints: {
                        560:  { slidesPerView: 2.1 },
                        860:  { slidesPerView: 3.1 },
                        1180: { slidesPerView: 4 },
                    },
                });
            }
            initOffersSwiper();
        });

        </script>

        <?php endif; ?>

        <div class="thirdbody">
          <div class="col1">
            <span class="topic">Taste That Brings People Together <i class="fa-solid fa-utensils fa-float"></i></span>
            <span class="TopicTence">Every meal is prepared with fresh ingredients, rich flavors, and a passion for creating unforgettable dining experiences.</span>
          </div>
                                        
          <div class="col2">
              <img loading="lazy" src="images/thirdborydimage.png" alt="">
          </div>

        </div>

        <div class="fourthbody">

          <span class="topic">Custormer Reviews <i class="fa-solid fa-book"></i></span>
          
            <div class="cardset">
                                        
                <div class="cards">
                    <span class="name">John Doe</span>
                    <span class="">
                        "Absolutely amazing! The food was fresh, full of flavor, and delivered right on time. I'll definitely be ordering again!"
                    </span>
                </div>
                                        
                <div class="cards">
                  <span class="name">John Doe</span>
                  <span class="">
                      "Absolutely amazing! The food was fresh, full of flavor, and delivered right on time. I'll definitely be ordering again!"
                  </span>
                </div>
                                        
                <div class="cards">
                  <span class="name">John Doe</span>
                  <span class="">
                      "Absolutely amazing! The food was fresh, full of flavor, and delivered right on time. I'll definitely be ordering again!"
                  </span>
                </div>
                                        
            </div>

        </div>



    </div>

    <?php include('php/footer.php'); ?>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script src="javascript/swiper-bundle.min.js"></script>
    <script src="javascript/script.js"></script>
</body>
</html>