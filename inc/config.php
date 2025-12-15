<?php
$host = "localhost";
$user = "root";       // user DB
$pass = "";           // password DB
$db   = "siomas";     // nama database Anda

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

?>