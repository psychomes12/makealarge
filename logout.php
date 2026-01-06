<?php
// C:\xampp\htdocs\makealarge\logout.php
session_start();
session_unset();
session_destroy();

// Redirect ke halaman utama
header("Location: index.html"); 
exit;
?>