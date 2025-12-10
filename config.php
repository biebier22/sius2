<?php
// Set timezone ke WIB (Waktu Indonesia Barat)
date_default_timezone_set('Asia/Jakarta');

// Konfigurasi koneksi database SIUS
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'sius2';

$conn2 = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

if ($conn2->connect_error) {
    die('Koneksi database gagal: ' . $conn2->connect_error);
}

$conn2->set_charset('utf8mb4');


