<?php

session_start();

include('includes/header.php');
include('includes/function.php');
include('../config/auth.php');
include('../config/dbcon.php');
global $conn;

if (!isLoggedIn()) {
    header("Location: admin_login.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$worker_id = intval($_SESSION['admin_id'] ?? 0);

if ($worker_id <= 0) {
    $_SESSION['error'] = 'Session invalid, please log in again.';
    header("Location: admin_login.php");
    exit();
}

$errors  = [];
$success = null;

// ---------------- HANDLE SAVE ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Session expired, please try again.';
        header("Location: admin_profile.php");
        exit();
    }

    $action = $_POST['action'] ?? '';

    // ---------- Profile details form ----------
    if ($action === 'update_profile') {
        $name  = trim($_POST['name']  ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($name === '') {
            $errors[] = 'Name is required.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }

        // optional avatar replace
        $profile_image = null; // null = keep existing
        if (!empty($_FILES['profile_image']['name']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed, true)) {
                $errors[] = 'Image must be a jpg, jpeg, png, or webp file.';
            } elseif ($_FILES['profile_image']['size'] > 3 * 1024 * 1024) {
                $errors[] = 'Image must be smaller than 3MB.';
            } else {
                $uploadDir = 'assets/images/shop_workers/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $newFileName = 'admin_' . $worker_id . '_' . time() . '.' . $ext;
                $destination = $uploadDir . $newFileName;

                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $destination)) {
                    $profile_image = $destination;
                } else {
                    $errors[] = 'Could not upload the image, please try again.';
                }
            }
        }

        if (empty($errors)) {
            if ($profile_image !== null) {
                $sql = "UPDATE shop_workers SET full_name = ?, email = ?, profile_image = ? WHERE worker_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, 'sssi', $name, $email, $profile_image, $worker_id);
            } else {
                $sql = "UPDATE shop_workers SET full_name = ?, email = ? WHERE worker_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, 'ssi', $name, $email, $worker_id);
            }

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success'] = 'Profile updated successfully.';
                header("Location: dashboard.php");
                exit();
            } else {
                $errors[] = 'Database error, please try again.';
            }
        }
    }

    // ---------- Change password form ----------
    if ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password      = $_POST['new_password']      ?? '';
        $confirm_password  = $_POST['confirm_password']  ?? '';

        $stmt = mysqli_prepare($conn, "SELECT password_hash FROM shop_workers WHERE worker_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $worker_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_stmt_get_result($stmt)->fetch_assoc();

        if (!$row || !password_verify($current_password, $row['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($new_password) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif ($new_password !== $confirm_password) {
            $errors[] = 'New password and confirmation do not match.';
        }

        if (empty($errors)) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "UPDATE shop_workers SET password_hash = ? WHERE worker_id = ?");
            mysqli_stmt_bind_param($stmt, 'si', $hashed, $worker_id);

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success'] = 'Password changed successfully.';
                header("Location: dashboard.php");
                exit();
            } else {
                $errors[] = 'Database error, please try again.';
            }
        }
    }
}

// ---------------- LOAD CURRENT ADMIN ----------------
$stmt = mysqli_prepare($conn, "SELECT * FROM shop_workers WHERE worker_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $worker_id);
mysqli_stmt_execute($stmt);
$admin = mysqli_stmt_get_result($stmt)->fetch_assoc();

if (!$admin) {
    $_SESSION['error'] = 'Account not found.';
    header("Location: dashboard.php");
    exit();
}

if (!empty($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

$form = [
    'name'  => $_POST['full_name']  ?? $admin['full_name'],
    'email' => $_POST['email'] ?? $admin['email'],
];
?>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../../css/admin-theme.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <title>My Profile | Restaurant</title>
</head>

<body>
<?php include('includes/navbar.php'); ?>

<div class="product-adding">
    <span class="heading">My Profile</span>

    <?php if ($success): ?>
        <div class="form-alert success"><?= htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="form-alert error">
            <?php foreach ($errors as $err): ?>
                <div><?= htmlspecialchars($err); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- ---------- Profile details ---------- -->
    <form method="post" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="action" value="update_profile">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">

        <div class="inputarea">
            <label>Profile Photo</label>
            <input type="file" name="profile_image" accept=".jpg,.jpeg,.png,.webp" onchange="previewAvatar(event)">
            <div class="imagedisplayarea">
                <img id="avatarPreview" class="avatar-preview"
                     src="<?= htmlspecialchars($admin['profile_image'] ?: ''); ?>"
                     style="<?= $admin['profile_image'] ? 'display:block;' : ''; ?>">
            </div>
        </div>

        <div class="inputarea">
            <label for="name">Full Name</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($form['name']); ?>" required>
        </div>

        <div class="inputarea">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($form['email']); ?>" required readonly>
        </div>

        <?php if (!empty($admin['username'])): ?>
            <div class="inputarea">
                <label>Username</label>
                <input type="text" value="<?= htmlspecialchars($admin['username']); ?>" disabled>
                <span class="field-hint">Username can't be changed here.</span>
            </div>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit">Save Profile</button>
        </div>
    </form>

    <!-- ---------- Change password ---------- -->
    <span class="subheading">Change Password</span>

    <form method="post" novalidate>
        <input type="hidden" name="action" value="change_password">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">

        <div class="inputarea">
            <label for="current_password">Current Password</label>
            <input type="password" id="current_password" name="current_password" required>
        </div>

        <div class="inputarea">
            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password" required minlength="8">
            <span class="field-hint">At least 8 characters.</span>
        </div>

        <div class="inputarea">
            <label for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
        </div>

        <div class="form-actions">
            <button type="submit">Update Password</button>
        </div>
    </form>
</div>

<script src="../../js/admin-theme.js?v=<?= time(); ?>"></script>
<script>
function previewAvatar(event) {
    const file = event.target.files[0];
    const preview = document.getElementById('avatarPreview');
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function (e) {
        preview.src = e.target.result;
        preview.style.display = 'block';
    };
    reader.readAsDataURL(file);
}
</script>
</body>
</html>