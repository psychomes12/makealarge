<?php
// C:\xampp\htdocs\makealarge\config\database.php

$host = 'localhost';
$db_name = 'db_makealarge';
$username = 'root';
$password = ''; // Default XAMPP biasanya kosong

try {
    // Membuat koneksi dengan PDO (Lebih aman dari SQL Injection daripada mysqli biasa)
    $conn = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    
    // Set mode error ke Exception (Agar jika error, program tidak diam saja)
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set default fetch mode ke Associative Array
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    // Jika gagal, tampilkan pesan error
    die("Koneksi Database Gagal: " . $e->getMessage());
}
?>