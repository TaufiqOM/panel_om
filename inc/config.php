<?php
$host = "localhost";
$user = "siomas";       // user DB
$pass = "siomas123#@!";           // password DB
$db   = "siomas";     // nama database Anda

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

?>