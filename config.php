<?php
/*
|--------------------------------------------------------------------------
| Konfigurasi Database
|--------------------------------------------------------------------------
|
| Ganti nilai-nilai di bawah ini dengan kredensial database Anda.
|
*/

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'firmware_db');

// Membuat koneksi ke database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi Gagal: " . $conn->connect_error);
}

// Set charset ke utf8mb4 untuk mendukung karakter yang lebih luas
$conn->set_charset("utf8mb4");

?>
