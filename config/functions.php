<?php
// C:\xampp\htdocs\makealarge\config\functions.php

// Fungsi untuk membersihkan input user (Mencegah XSS Attack/Hack)
function cleanInput($data) {
    $data = trim($data);            // Hapus spasi di awal/akhir
    $data = stripslashes($data);    // Hapus backslash
    $data = htmlspecialchars($data); // Ubah karakter html jadi teks biasa
    return $data;
}

// Fungsi redirect halaman agar tidak error "Header already sent"
function redirect($url) {
    echo "<script>window.location.href='$url';</script>";
    exit;
}

// Fungsi Alert (Pemberitahuan Pop-up)
function alert($message) {
    echo "<script>alert('$message');</script>";
}

// Fungsi Upload Gambar (Dengan Validasi Ketat)
function uploadImage($file, $destinationFolder) {
    $fileName = $file['name'];
    $fileTmp = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileError = $file['error'];

    // Cek apakah ada file yang diupload
    if ($fileError === 4) {
        return false; // Tidak ada gambar
    }

    // Validasi Ekstensi
    $allowedExt = ['jpg', 'jpeg', 'png'];
    $fileExt = explode('.', $fileName);
    $fileActualExt = strtolower(end($fileExt));

    if (!in_array($fileActualExt, $allowedExt)) {
        alert("Format file tidak didukung! Gunakan JPG/JPEG/PNG.");
        return false;
    }

    // Validasi Ukuran (Max 2MB)
    if ($fileSize > 2000000) {
        alert("Ukuran file terlalu besar! Maksimal 2MB.");
        return false;
    }

    // Generate Nama Baru (Agar tidak duplikat/menimpa file lain)
    $newFileName = uniqid('', true) . "." . $fileActualExt;
    $fileDestination = $destinationFolder . $newFileName;

    // Pindahkan file
    if(move_uploaded_file($fileTmp, $fileDestination)) {
        return $newFileName;
    } else {
        alert("Gagal mengupload gambar.");
        return false;
    }
}
?>