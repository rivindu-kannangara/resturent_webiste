<?php
include('config/dbcon.php');

global $conn;


if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['searchbtn'])) {
    $search = trim($_GET['search'] ?? '');

    if ($search !== '') {
        $sqlreq = "SELECT * FROM products WHERE name LIKE ? OR description LIKE ?";
        $stmt = mysqli_prepare($conn, $sqlreq);

        if ($stmt) {
            $searchParam = '%' . $search . '%';
            mysqli_stmt_bind_param($stmt, "ss", $searchParam, $searchParam);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($result && $result->num_rows > 0) {
                while ($product = mysqli_fetch_assoc($result)) {
                    echo '<div>';
                    echo '<h2>' . htmlspecialchars($product['name']) . '</h2>';
                    echo '<p>' . htmlspecialchars($product['description']) . '</p>';
                    echo '</div>';
                }
            } else {
                echo 'No products found for "' . htmlspecialchars($search) . '".';
            }

            mysqli_stmt_close($stmt);
        } else {
            echo 'Database query failed: ' . htmlspecialchars(mysqli_error($conn));
        }
    } else {
        echo 'Please enter a search term.';
    }
}


?>