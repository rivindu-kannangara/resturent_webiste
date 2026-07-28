<?php

$server = "localhost";
$username = "root";
$password = "rivindu20050415kannangara";
$dbname = "food_ordering";

$conn = mysqli_connect($server, $username, $password, $dbname);




if(!$conn){
    die("connection fail:".mysqli_connect_error());
}


?>