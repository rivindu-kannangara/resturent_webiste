<?php
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);

include 'config/auth.php';

header("Strict-Transport-Security: max-age=63072000; includeSubDomains; preload");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), interest-cohort=()");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

header("Content-Security-Policy: "
    . "default-src 'self'; "
    . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://ajax.googleapis.com https://unpkg.com https://kit.fontawesome.com https://maxcdn.bootstrapcdn.com; "
    . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com https://cdnjs.cloudflare.com https://unpkg.com; "
    . "img-src 'self' data:; "
    . "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com https://kit.fontawesome.com https://unpkg.com https://cdn.jsdelivr.net; "
    . "connect-src 'self' https://cdn.jsdelivr.net https://ajax.googleapis.com; "
    . "object-src 'none'; "
    . "base-uri 'self'; "
    . "form-action 'self';"
);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include('header.php');
include 'config/function.php';
include 'config/dbcon.php';

$errors = [];
$input_values = [
    'shop_workers_name'  => '',
    'shop_workers_email' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("CSRF token validation failed");
    }

    // ---------------- SIGNUP ----------------
    if (isset($_POST["signup"])) {
        $input_values = [
            'shop_workers_name'  => htmlspecialchars(trim($_POST["shop_workers_name"] ?? '')),
            'shop_workers_email' => filter_var(trim($_POST["shop_workers_email"] ?? ''), FILTER_SANITIZE_EMAIL)
        ];

        $shop_workers_password = $_POST["shop_workers_password"] ?? '';
        $signup_cpassword      = $_POST["signup_cpassword"] ?? '';
        $agreed_terms          = isset($_POST['terms']) && $_POST['terms'] == 'on' ? '1' : '0';

        if (empty($input_values['shop_workers_name'])) {
            $errors[] = "Username is required";
        }

        if (empty($input_values['shop_workers_email'])) {
            $errors[] = "Email is required";
        } elseif (!filter_var($input_values['shop_workers_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }

        if (empty($shop_workers_password)) {
            $errors[] = "Password is required";
        } elseif (strlen($shop_workers_password) < 8) {
            $errors[] = "Password must be at least 8 characters";
        } elseif ($shop_workers_password !== $signup_cpassword) {
            $errors[] = "Passwords do not match";
        }

        if ($agreed_terms !== '1') {
            $errors[] = "You must agree to the Terms and Conditions";
        }

        if (empty($errors)) {
            global $conn;
            $check_email_query = "SELECT email FROM shop_workers WHERE email = ?";
            $stmt = mysqli_prepare($conn, $check_email_query);
            mysqli_stmt_bind_param($stmt, "s", $input_values['shop_workers_email']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);

            if (mysqli_stmt_num_rows($stmt) > 0) {
                $errors[] = "Email already exists. Please use another.";
            }
            mysqli_stmt_close($stmt);
        }

        // Profile image upload
        $finalImage = "images/shop_workersimg/defaultimg.png";
        if (empty($errors) && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $image = $_FILES['image'];
            $allowed_types = ['jpg', 'jpeg', 'png'];
            $extension = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));

            if (!in_array($extension, $allowed_types)) {
                $errors[] = "Invalid image format. Only jpg, jpeg, and png allowed.";
            } elseif ($image['size'] > 2 * 1024 * 1024) {
                $errors[] = "Image size must be less than 2MB";
            } else {
                $image_info = getimagesize($image['tmp_name']);
                if ($image_info === false) {
                    $errors[] = "Uploaded file is not a valid image";
                } else {
                    $uploadDir    = "../images/shop_workersimg/";
                    $relativePath = "images/shop_workersimg/";
                    $filename     = bin2hex(random_bytes(8)) . '.' . $extension;
                    $newImagePath = $uploadDir . $filename;

                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    if (move_uploaded_file($image['tmp_name'], $newImagePath)) {
                        $finalImage = $relativePath . $filename;
                    } else {
                        error_log("Image upload failed. Check permissions on $uploadDir");
                        $errors[] = "Image upload failed. Please try again.";
                    }
                }
            }
        }

        // Insert directly into the database (no OTP step)
        if (empty($errors)) {
            $password_hash = password_hash($shop_workers_password, PASSWORD_BCRYPT, ['cost' => 12]);

            $insert_query = "INSERT INTO shop_workers (full_name, email, password_hash, profile_image)
                              VALUES (?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $insert_query);
            mysqli_stmt_bind_param(
                $stmt,
                "ssss",
                $input_values['shop_workers_name'],
                $input_values['shop_workers_email'],
                $password_hash,
                $finalImage
            );

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                header("Location: shop_worker_login.php?signup_success=1&message=" . urlencode("Account created. Please log in."));
                exit();
            } else {
                error_log("Signup insert failed: " . mysqli_stmt_error($stmt));
                $errors[] = "Something went wrong. Please try again.";
                mysqli_stmt_close($stmt);
            }
        }
    }

    // ---------------- LOGIN ----------------
    if (isset($_POST["signin"])) {
        $shop_workers_email    = filter_var(trim($_POST["shop_workers_email"] ?? ''), FILTER_SANITIZE_EMAIL);
        $shop_workers_password = $_POST["shop_workers_password"] ?? '';

        if (empty($shop_workers_email)) {
            $errors[] = "Email is required";
        } elseif (!filter_var($shop_workers_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }

        if (empty($shop_workers_password)) {
            $errors[] = "Password is required";
        }

        if (empty($errors)) {
            if (!isset($_SESSION['login_attempts'])) {
                $_SESSION['login_attempts']     = 0;
                $_SESSION['last_login_attempt'] = time();
            }

            if ($_SESSION['login_attempts'] > 5 && (time() - $_SESSION['last_login_attempt']) < 300) {
                $errors[] = "Too many login attempts. Please try again later.";
            } else {
                if (authenticate_shop_worker($shop_workers_email, $shop_workers_password)) {
                    unset($_SESSION['login_attempts']);
                    unset($_SESSION['last_login_attempt']);

                    session_regenerate_id(true);

                    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
                    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];

                    header("Location: admin/adminpanel.php");
                    exit();
                } else {
                    $_SESSION['login_attempts']++;
                    $_SESSION['last_login_attempt'] = time();
                    $errors[] = "Invalid email or password.";
                }
            }
        }
    }
}

if (!function_exists('authenticate_shop_worker')) {
    function authenticate_shop_worker($email, $password) {
        global $conn;

        $query = "SELECT worker_id, full_name, email, password_hash
                  FROM shop_workers WHERE email = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $query);

        if (!$stmt) {
            error_log("MySQL prepare failed: " . mysqli_error($conn));
            return false;
        }

        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($worker = mysqli_fetch_assoc($result)) {
            if (password_verify($password, $worker['password_hash'])) {
                $_SESSION['auth_shop_worker'] = [
                    'worker_id'   => $worker['worker_id'],
                    'full_name'   => $worker['full_name'],
                    'email'       => $worker['email'],
                    'login_time'  => time()
                ];

                mysqli_stmt_close($stmt);
                return true;
            }
        }

        mysqli_stmt_close($stmt);
        return false;
    }
}

function display_errors($errors) {
    if (!empty($errors)) {
        echo '<div class="alert alert-danger">';
        foreach ($errors as $error) {
            echo htmlspecialchars($error) . '<br>';
        }
        echo '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../css/login.css?v=<?php echo time(); ?>" />
    <link rel="stylesheet" href="../css/index.css?v=<?php echo time(); ?>" />
    <title>Shop Worker Login | Restaurant</title>
</head>
<body>

<?php include('navbar.php'); ?>

<div class="container" style="top:7rem; background: rgb(39, 38, 38); box-shadow: 0 5px 10px rgba(0,0,0,0.2);">
    <input type="checkbox" id="flip">
    <div class="cover">
        <div class="front">
            <div class="text">
                <img src="../images/Barbecue Burger.jpg" alt="Login Background" class="backImg">
            </div>
        </div>
    </div>
    <div class="forms">
        <div class="form-content">

            <!-- LOGIN FORM -->
            <div class="login-form">
                <div class="title">Shop Worker Login</div>
                <?php display_errors($errors); ?>

                <form action="#" class="sign-in-form" method="post">
                    <div class="input-boxes">
                        <div class="input-box">
                            <i class="fas fa-envelope"></i>
                            <input type="text" placeholder="Email" name="shop_workers_email"
                                   value="<?php echo htmlspecialchars($_POST['shop_workers_email'] ?? ''); ?>" required />
                        </div>
                        <div class="input-box">
                            <i class="fas fa-lock"></i>
                            <input type="password" placeholder="Password" name="shop_workers_password" required id="signin-password" />
                            <span class="toggle-password" toggle="#signin-password"><i class="fas fa-eye"></i></span>
                        </div>
                        <div class="button input-box">
                            <input type="submit" value="Login" name="signin" />
                        </div>
                        <div class="text">
                            <a href="forgot_password.php" class="forgot-link">Forgot Password?</a>
                        </div>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                        <div class="text sign-up-text">Don't have an account? <label for="flip">Register now</label></div>
                    </div>
                </form>
            </div>

            <!-- SIGNUP FORM -->
            <div class="signup-form">
                <div class="title">Register</div>
                <?php display_errors($errors); ?>

                <form action="#" class="sign-up-form" method="post" enctype="multipart/form-data">
                    <div class="input-box">
                        <i class="fas fa-user"></i>
                        <input type="text" placeholder="Username (required)" name="shop_workers_name"
                               value="<?php echo htmlspecialchars($input_values['shop_workers_name']); ?>" required />
                    </div>
                    <div class="input-box">
                        <i class="fas fa-envelope"></i>
                        <input type="email" placeholder="Email (required)" name="shop_workers_email"
                               value="<?php echo htmlspecialchars($input_values['shop_workers_email']); ?>" required />
                    </div>
                    <div class="input-box">
                        <i class="fas fa-lock"></i>
                        <input type="password" placeholder="Password (required)" name="shop_workers_password" required id="signup-password" />
                        <span class="toggle-password" toggle="#signup-password"><i class="fas fa-eye"></i></span>
                    </div>
                    <small class="password-hint">Minimum 8 characters</small>
                    <div class="input-box">
                        <i class="fas fa-lock"></i>
                        <input type="password" placeholder="Confirm Password (required)" name="signup_cpassword" required id="signup-cpassword" />
                        <span class="toggle-password" toggle="#signup-cpassword"><i class="fas fa-eye"></i></span>
                    </div>
                    <div class="row-3">
                        <div class="col-md-12">
                            <span class="img">Profile Image</span>
                            <input type="file" name="image" id="image" class="form-control" accept="image/*" onchange="previewImage(event)">
                            <small class="image-hint">Max 2MB (JPG, PNG, JPEG)</small>
                            <div id="imagePreviewContainer" style="display: none; margin-top: 10px; position: relative;">
                                <img id="imagePreview" src="#" alt="Preview" style="max-width: 200px; max-height: 200px;">
                                <button type="button" id="removeImageBtn">×</button>
                            </div>
                        </div>
                    </div>
                    <br>
                    <div class="input">
                        <input type="checkbox" name="terms" id="terms" required>
                        <div class="term"> I accept all <a href="termsandcondition.php" class="termlink">Terms & Conditions</a></div>
                    </div>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                    <div class="button input-box">
                        <input type="submit" name="signup" value="Register" />
                    </div>
                    <div class="text sign-up-text">Already have an account? <label for="flip">Login now</label></div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.toggle-password').forEach(function(toggle) {
        toggle.addEventListener('click', function() {
            const input = document.querySelector(this.getAttribute('toggle'));
            if (input) {
                if (input.type === 'password') {
                    input.type = 'text';
                    this.innerHTML = '<i class="fas fa-eye-slash"></i>';
                } else {
                    input.type = 'password';
                    this.innerHTML = '<i class="fas fa-eye"></i>';
                }
            }
        });
    });

    const imageInput = document.getElementById('image');
    if (imageInput) {
        imageInput.addEventListener('change', previewImage);
    }
});

function previewImage(event) {
    const input = event.target;
    const previewContainer = document.getElementById('imagePreviewContainer');
    const preview = document.getElementById('imagePreview');
    const removeBtn = document.getElementById('removeImageBtn');

    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            previewContainer.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }

    removeBtn.onclick = function() {
        input.value = '';
        preview.src = '#';
        previewContainer.style.display = 'none';
    };
}

window.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const success = urlParams.get('signup_success');
    const message = urlParams.get('message');

    if (success === '1' && message) {
        alert(decodeURIComponent(message));
        const cleanUrl = window.location.origin + window.location.pathname;
        window.history.replaceState({}, document.title, cleanUrl);
    }
});
</script>

</body>
</html>