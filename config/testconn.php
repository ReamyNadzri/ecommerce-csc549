<?php
$condb = mysqli_connect("localhost", "root", "", "ecommercedb", 3307);

if (!$condb) {
    die("Connection failed: " . mysqli_connect_error());
}
echo "DATABASE connected successfully";
?>