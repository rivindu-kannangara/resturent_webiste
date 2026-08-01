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
    <link rel="stylesheet" href="../css/contactus.css?v=<?= time(); ?>">
</head>
<body>

    <div class="backcontact">
        <?php include('navbar.php'); ?>
        <h1 class="contactHeader">Contact Us</h1>
    </div>

    <div class="contact-container">

            <span>Contact Numbers : +94 7123445 , +94 78231123</span>
            <span>Addres: 2131 / edman road</span>
            <span>
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3961.1990982453485!2d79.8821046113554!3d6.8667292931032184!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3ae25bccf2c136cb%3A0x41301876361abd86!2sTRONIC.LK!5e0!3m2!1sen!2slk!4v1785501346227!5m2!1sen!2slk" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
            </span>

    </div>
    
    <?php include('footer.php'); ?>

</body>
</html>