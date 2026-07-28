<!DOCTYPE html>
<html lang="en">

<?php include('php/header.php'); ?>

<head>
    <link rel="stylesheet" href="css/index.css">

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
                  <a class="nav-link" aria-current="page" href="#">Home</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="#">Contact Us</a>
                </li>

                <li class="nav-item">
                  <a class="nav-link" href="php/admin_login.php">Login</a>
                </li>


                <li class="nav-item dropdown">
                  <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    Menu
                  </a>
                  <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#">Action</a></li>
                    <li><a class="dropdown-item" href="#">Another action</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#">Something else here</a></li>
                  </ul>
                </li>
              </ul>

              <ul class="navbar-nav mb-2 mb-lg-0">
                <li class="nav-item">
                  <a class="nav-link" href="#"><i class="fa-solid fa-cart-arrow-down"></i></a>
                </li>
              </ul>

              <form class="d-flex" role="search">
                <input class="form-control me-2" type="search" placeholder="Search" aria-label="Search"/>
                <button class="btn btn-outline-success" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
              </form>
            </div>
          </div>
        </nav>


         <div class="upperbodyquate">
            Crafted with Passion, <br> Served with Love...
        </div>

        <div class="button">
            <button class="upper_order_button">Order Now <i class="fa-solid fa-arrow-down fa-float"></i></button>
        </div>


    </div>

    <div class="middle_body">
        <div class="offers">
            <div class="header">TODAY BEST OFFERS</div>
        </div>

        <div class="cards">
          <div class="contaier swiper">
            <div class="slide-container">
              <div class="card-wrapper swiper-wrapper">
                <div class="card swiper-slide">
                  <div class="image-box">
                    <!--<img src="images/showImg/fullDev.jpg" alt="" />-->
                  </div>
                  <div class="profile-details">
                    <!--<img src="images/profile/profile1.jpg" alt="" />-->
                    <div class="name-job">
                      <h3 class="name">David Cardlos</h3>
                      <h4 class="job">Full Stack Developer</h4>
                    </div>
                  </div>
                </div>
                <div class="card swiper-slide">
                  <div class="image-box">
                    <!--<img src="images/showImg/photographer.jpg" alt="" />-->
                  </div>
                  <div class="profile-details">
                    <!--<img src="images/profile/profile2.jpg" alt="" />-->
                    <div class="name-job">
                      <h3 class="name">Siliana Ramis</h3>
                      <h4 class="job">Photographer</h4>
                    </div>
                  </div>
                </div>
                <div class="card swiper-slide">
                  <div class="image-box">
                    <!--<img src="images/showImg/dataAna.jpg" alt="" />-->
                  </div>
                  <div class="profile-details">
                    <!--<img src="images/profile/profile3.jpg" alt="" />-->
                    <div class="name-job">
                      <h3 class="name">Richard Bond</h3>
                      <h4 class="job">Data Analyst</h4>
                    </div>
                  </div>
                </div>
                <div class="card swiper-slide">
                  <div class="image-box">
                    <!--<img src="images/showImg/appDev.jpg" alt="" />-->
                  </div>
                  <div class="profile-details">
                    <!--<img src="images/profile/profile4.jpg" alt="" />-->
                    <div class="name-job">
                      <h3 class="name">Priase</h3>
                      <h4 class="job">App Developer</h4>
                    </div>
                  </div>
                </div>
                <div class="card swiper-slide">
                  <div class="image-box">
                    <img src="20221214_210547.jpg" alt="" />
                  </div>
                  <div class="profile-details">
                    <img src="20221214_191713.jpg" alt="" />
                    <div class="name-job">
                      <h3 class="name">James Laze</h3>
                      <h4 class="job">Blogger</h4>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="swiper-button-next swiper-navBtn"></div>
            <div class="swiper-button-prev swiper-navBtn"></div>
            <div class="swiper-pagination"></div>
          </div>
        </div>


    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script src="javascript/swiper-bundle.min.js"></script>
    <script src="javascript/script.js"></script>
</body>
</html>