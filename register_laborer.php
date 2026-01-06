<?php
// C:\xampp\htdocs\makealarge\register_laborer.php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}

// Cek apakah user sudah pernah daftar jadi kuli?
$check = $conn->prepare("SELECT id FROM laborers WHERE user_id = ?");
$check->execute([$_SESSION['user_id']]);
if ($check->rowCount() > 0) {
    echo "<script>alert('Anda sudah terdaftar sebagai kuli!'); window.location.href='dashboard.php';</script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $exp = cleanInput($_POST['experience']);
    $skills = cleanInput($_POST['skills']);
    $domicile = cleanInput($_POST['domicile']);
    $age = cleanInput($_POST['age']);
    
    // Upload Foto Profil Kuli
    $foto = uploadImage($_FILES['profile_image'], 'assets/uploads/');

    if ($foto) {
        try {
            $sql = "INSERT INTO laborers (user_id, experience_year, skills, domicile, age, profile_image) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$_SESSION['user_id'], $exp, $skills, $domicile, $age, $foto]);
            
            echo "<script>alert('Pendaftaran Berhasil! Profil Anda kini tampil di halaman Kuli.'); window.location.href='dashboard.php';</script>";
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
        }
    } else {
        echo "<script>alert('Gagal upload foto profil.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Jadi Kuli - Make a LARGE</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background-color: #FAF9F6; padding-top: 80px; }
        .form-container { max-width: 500px; margin: 0 auto; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h2 style="text-align:center; color:#2E7D32; margin-bottom:20px;"><i class="fas fa-hard-hat"></i> Daftar Mitra Kuli</h2>
            
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Pengalaman Kerja (Tahun)</label>
                    <input type="number" name="experience" required placeholder="Contoh: 5">
                </div>

                <div class="form-group">
                    <label>Keahlian / Spesialisasi</label>
                    <input type="text" name="skills" required placeholder="Contoh: Pasang Keramik, Cat, Pondasi">
                </div>

                <div class="form-group">
                    <label>Domisili (Kota Tempat Tinggal)</label>
                    <input type="text" name="domicile" required placeholder="Contoh: Jakarta Selatan">
                </div>

                <div class="form-group">
                    <label>Umur (Tahun)</label>
                    <input type="number" name="age" required placeholder="Contoh: 30">
                </div>

                <div class="form-group">
                    <label>Foto Profil Diri (Wajib Rapi)</label>
                    <input type="file" name="profile_image" required accept="image/*">
                </div>

                <button type="submit" class="btn-submit" style="background: #2E7D32;">Daftar Sekarang</button>
                <a href="dashboard.php" style="display:block; text-align:center; margin-top:15px; color:#888;">Batal</a>
            </form>
        </div>
    </div>
</body>
</html>