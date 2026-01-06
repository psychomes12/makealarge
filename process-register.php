<?php
// C:\xampp\htdocs\makealarge\register.php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = cleanInput($_POST['full_name']);
    $email = cleanInput($_POST['email']);
    $phone = cleanInput($_POST['phone']);
    $password = $_POST['password'];

    // Cek email kembar
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        $error = "Email sudah terdaftar! Silakan login.";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        // Default Role = user
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password, role) VALUES (?, ?, ?, ?, 'user')");
        if ($stmt->execute([$name, $email, $phone, $hash])) {
            echo "<script>alert('Pendaftaran Berhasil! Silakan Masuk.'); window.location.href='login.php';</script>";
            exit;
        } else {
            $error = "Terjadi kesalahan sistem.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar - Make a LARGE</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background: #FAF9F6; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; font-family: sans-serif; }
        .auth-box { background: white; padding: 40px; border-radius: 15px; width: 100%; max-width: 400px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); text-align: center; }
        .auth-box h2 { color: #5D4037; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; text-align: left; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; }
        .btn-submit { width: 100%; padding: 12px; background: #2E7D32; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; }
        .btn-submit:hover { background: #1B5E20; }
        .error-msg { background: #ffebee; color: #c62828; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="auth-box">
        <h2>Buat Akun Baru</h2>
        <?php if($error): ?>
            <div class="error-msg"><?= $error ?></div>
        <?php endif; ?>
        
        <form action="" method="POST">
            <div class="form-group">
                <input type="text" name="full_name" required placeholder="Nama Lengkap">
            </div>
            <div class="form-group">
                <input type="email" name="email" required placeholder="Alamat Email">
            </div>
            <div class="form-group">
                <input type="text" name="phone" required placeholder="Nomor WhatsApp">
            </div>
            <div class="form-group">
                <input type="password" name="password" required placeholder="Password">
            </div>
            <button type="submit" class="btn-submit">Daftar Sekarang</button>
        </form>
        <p style="margin-top: 20px; color: #666;">
            Sudah punya akun? <a href="login.php" style="color: #2E7D32;">Login disini</a>
        </p>
        <a href="index.html" style="font-size: 0.8rem; color: #999; text-decoration: none;">Kembali ke Beranda</a>
    </div>
</body>
</html>