<nav class="navbar navbar-expand-lg ">
          <div class="container-fluid">
            <a class="navbar-brand" href="../index.php"><img src="../images/logo.png" alt=""  srcset=""></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
              <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
              <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                  <a class="nav-link" aria-current="page" href="../index.php"><i class="fa-solid fa-house"></i> Home</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="contact.php"><i class="fa-solid fa-address-card"></i> Contact Us</a>
                </li>

                <li class="nav-item">
                  <a class="nav-link" href="admin_login.php"><i class="fa-solid fa-user-lock"></i> Login</a>
                </li>

                <li class="nav-item">
                  <a class="nav-link" href="menu.php"><i class="fa-solid fa-book"></i> Menu</a>
                </li>
              </ul>

              <form class="d-flex" action="search.php" method="GET" role="search">
                <input class="form-control me-2" name = "search" type="search" placeholder="Search" aria-label="Search" required/>
                <button class="btn btn-outline-warning" name="searchbtn" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
              </form>
            </div>
          </div>
        </nav>