<?php
// C:\xampp\htdocs\makealarge\profile.php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = "";

// Ambil Data User Saat Ini
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Proses Update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullName = cleanInput($_POST['full_name']);
    $phone = cleanInput($_POST['phone']);
    $newPassword = $_POST['password'];
    
    // Update Data Dasar
    $sql = "UPDATE users SET full_name = ?, phone = ? WHERE id = ?";
    $params = [$fullName, $phone, $user_id];
    
    // Jika Password Diisi, Update Password
    if (!empty($newPassword)) {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET full_name = ?, phone = ?, password = ? WHERE id = ?";
        $params = [$fullName, $phone, $hash, $user_id];
    }
    
    // Eksekusi Update Text
    $stmt = $conn->prepare($sql);
    if($stmt->execute($params)) {
        $_SESSION['name'] = $fullName; // Update session name
        $message = "Profil berhasil diperbarui!";
        // Refresh data user
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
    }

    // Proses Upload KTP (Verifikasi)
    if (!empty($_FILES['ktp_image']['name'])) {
        $ktp = uploadImage($_FILES['ktp_image'], 'assets/uploads/');
        if ($ktp) {
            $stmt = $conn->prepare("UPDATE users SET ktp_image = ?, is_verified = 1 WHERE id = ?");
            $stmt->execute([$ktp, $user_id]);
            $message .= " Verifikasi KTP berhasil diupload!";
            $user['is_verified'] = 1; // Update tampilan status
        } else {
            $message .= " Gagal upload KTP.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profil - Make a LARGE</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #FAF9F6; padding-top: 80px; }
        .profile-container { max-width: 700px; margin: 0 auto; background: white; padding: 40px; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
        .verified-badge { background: #E8F5E9; color: #2E7D32; padding: 5px 10px; border-radius: 5px; font-size: 0.8rem; font-weight: bold; display: inline-flex; align-items: center; gap: 5px; }
        .unverified-badge { background: #FFEBEE; color: #C62828; padding: 5px 10px; border-radius: 5px; font-size: 0.8rem; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="profile-container">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h2><i class="fas fa-user-edit"></i> Edit Profil</h2>
                <?php if($user['is_verified']): ?>
                    <span class="verified-badge"><i class="fas fa-check-circle"></i> Terverifikasi</span>
                <?php else: ?>
                    <span class="unverified-badge"><i class="fas fa-times-circle"></i> Belum Verifikasi</span>
                <?php endif; ?>
            </div>

            <?php if($message): ?>
                <div style="background: #e0f2f1; color: #00695c; padding: 15px; margin-bottom: 20px; border-radius: 8px;">
                    <?= $message ?>
                </div>
            <?php endif; ?>
            
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Email (Tidak dapat diubah)</label>
                    <input type="text" value="<?= $user['email'] ?>" disabled style="background:#eee; cursor:not-allowed;">
                </div>

                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label>No. Telepon</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($user['phone']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Ganti Password (Biarkan kosong jika tidak ingin mengganti)</label>
                    <input type="password" name="password" placeholder="Password Baru">
                </div>

                <hr style="margin: 30px 0; border:0; border-top:1px solid #eee;">

                <h3 style="margin-bottom:15px; font-size:1.1rem; color:#5D4037;">Verifikasi Identitas</h3>
                <div class="form-group">
                    <label>Upload Foto KTP</label>
                    <?php if($user['ktp_image']): ?>
                        <p style="font-size:0.8rem; color:green; margin-bottom:5px;">File KTP sudah ada di server.</p>
                    <?php endif; ?>
                    <input type="file" name="ktp_image" accept="image/*">
                    <small style="color:#888;">Upload ulang jika ingin mengganti foto KTP.</small>
                </div>

                <button type="submit" class="btn-submit">Simpan Perubahan</button>
                <a href="dashboard.php" style="display:block; text-align:center; margin-top:15px; color:#888;">Kembali ke Dashboard</a>
            </form>
        </div>
    </div>
</body>
</html>