<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "fall_detection";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("เชื่อมต่อฐานข้อมูลไม่สำเร็จ");
}
