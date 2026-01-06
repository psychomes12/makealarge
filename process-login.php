<?php
// C:\xampp\htdocs\makealarge\process-login.php
session_start(); // Mulai sesi
header('Content-Type: application/json');
require_once 'config/database.php';
require_once 'config/functions.php';

$response = array('status' => 'error', 'message' => '');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = cleanInput($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $response['message'] = 'Email dan Password wajib diisi!';
        echo json_encode($response);
        exit;
    }

    try {
        // Cari user berdasarkan email
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Jika user ditemukan
        if ($user) {
            // Verifikasi Password Hash
            if (password_verify($password, $user['password'])) {
                // Password Benar! Buat Session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['email'] = $user['email'];

                $response['status'] = 'success';
                $response['message'] = 'Login berhasil!';
            } else {
                $response['message'] = 'Password salah!';
            }
        } else {
            $response['message'] = 'Email tidak ditemukan!';
        }
    } catch (PDOException $e) {
        $response['message'] = 'Database Error: ' . $e->getMessage();
    }
}

echo json_encode($response);
?>