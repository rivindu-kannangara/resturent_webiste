<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function authenticate_shop_worker($email, $password) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT worker_id , email , password_hash, full_name FROM shop_workers WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $worker = $result->fetch_assoc();
        if (password_verify($password, $worker['password_hash'])) {
            // Set all worker session data
            $_SESSION['auth'] = true;
            $_SESSION['auth_worker'] = [
                'worker_id' => $worker['worker_id'],
                'email' => $worker['email'],
                'full_name' => $worker['full_name'],
            ];
            return true;
        }
    }
    return false;
}


function isLoggedIn() {
    if (isset($_SESSION['auth']) && $_SESSION['auth'] === true) {
        return true;
    }
    return false;
}

function logoutworker() {
    // Only logout when user manually clicks logout
    session_destroy();
    $_SESSION = [];
    return true;
}

?>