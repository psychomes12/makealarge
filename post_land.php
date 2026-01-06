<?php
// C:\xampp\htdocs\makealarge\post_land.php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php'; // Pastikan file functions.php ada (dari Tahap 1)

// Cek Login
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    $title = cleanInput($_POST['title']);
    $desc = cleanInput($_POST['description']);
    $area = cleanInput($_POST['area_size']);
    $price = cleanInput($_POST['price']);
    $location = cleanInput($_POST['location']);

    // Proses Upload 2 Gambar
    $uploadDir = 'assets/uploads/';
    
    // Upload Gambar 1
    $img1 = uploadImage($_FILES['image1'], $uploadDir);
    // Upload Gambar 2
    $img2 = uploadImage($_FILES['image2'], $uploadDir);

    if ($img1 && $img2) {
        try {
            $sql = "INSERT INTO lands (user_id, title, description, area_size, price, location, image1, image2) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$user_id, $title, $desc, $area, $price, $location, $img1, $img2]);
            
            echo "<script>alert('Tanah berhasil diiklankan!'); window.location.href='dashboard.php';</script>";
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
        }
    } else {
        $message = "Gagal upload gambar. Pastikan format JPG/PNG dan ukuran maks 2MB.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jual Tanah - Make a LARGE</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background-color: #FAF9F6; padding-top: 80px; }
        .form-container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
        .page-title { text-align: center; color: #5D4037; margin-bottom: 20px; }
    </style>
</head>
<body>
    <?php include 'includes/header_simple.php'; // Opsional, atau buat navbar manual ?>
    
    <div class="container">
        <div class="form-container">
            <h2 class="page-title"><i class="fas fa-map-marked-alt"></i> Iklankan Tanah</h2>
            
            <?php if($message): ?>
                <div style="background: #ffebee; color: #c62828; padding: 10px; margin-bottom: 15px; border-radius: 5px;">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Judul Iklan</label>
                    <input type="text" name="title" required placeholder="Contoh: Tanah Kavling Murah di Bogor">
                </div>
                
                <div class="form-group">
                    <label>Deskripsi Lengkap</label>
                    <textarea name="description" rows="4" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;" placeholder="Jelaskan kondisi tanah, akses jalan, surat-surat, dll..."></textarea>
                </div>

                <div class="form-group">
                    <label>Luas Tanah</label>
                    <input type="text" name="area_size" required placeholder="Contoh: 500 m2">
                </div>

                <div class="form-group">
                    <label>Harga (Rp)</label>
                    <input type="number" name="price" required placeholder="Contoh: 150000000">
                </div>

                <div class="form-group">
                    <label>Alamat Lengkap (Lokasi)</label>
                    <input type="text" name="location" required placeholder="Jl. Raya...">
                </div>

                <div class="form-group">
                    <label>Foto Tanah 1 (Wajib)</label>
                    <input type="file" name="image1" required accept="image/*">
                </div>

                <div class="form-group">
                    <label>Foto Tanah 2 (Wajib)</label>
                    <input type="file" name="image2" required accept="image/*">
                </div>

                <button type="submit" class="btn-submit">Posting Iklan</button>
                <a href="dashboard.php" style="display:block; text-align:center; margin-top:15px; color:#888;">Kembali ke Dashboard</a>
            </form>
        </div>
    </div>
</body>
</html>