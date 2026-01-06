<?php
// C:\xampp\htdocs\makealarge\login.php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

// Kalau sudah login, lempar ke dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";

// LOGIKA PEMROSESAN LOGIN
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = cleanInput($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Simpan sesi
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['full_name'];
        $_SESSION['email'] = $user['email'];
        
        // Redirect ke dashboard
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Email atau Password salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Masuk - Make a LARGE</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background: #FAF9F6; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; font-family: sans-serif; }
        .auth-box { background: white; padding: 40px; border-radius: 15px; width: 100%; max-width: 400px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); text-align: center; }
        .auth-box h2 { color: #5D4037; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; text-align: left; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; }
        .btn-submit { width: 100%; padding: 12px; background: #8B4513; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; }
        .btn-submit:hover { background: #5D4037; }
        .error-msg { background: #ffebee; color: #c62828; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="auth-box">
        <h2>Masuk Akun</h2>
        <?php if($error): ?>
            <div class="error-msg"><?= $error ?></div>
        <?php endif; ?>
        
        <form action="" method="POST">
            <div class="form-group">
                <input type="email" name="email" required placeholder="Alamat Email">
            </div>
            <div class="form-group">
                <input type="password" name="password" required placeholder="Password">
            </div>
            <button type="submit" class="btn-submit">Masuk Sekarang</button>
        </form>
        <p style="margin-top: 20px; color: #666;">
            Belum punya akun? <a href="register.php" style="color: #8B4513;">Daftar disini</a>
        </p>
        <a href="index.html" style="font-size: 0.8rem; color: #999; text-decoration: none;">Kembali ke Beranda</a>
    </div>
</body>
</html>