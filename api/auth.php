<?php
session_start();
include 'db.php'; // Memanggil file koneksi di folder yang sama

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// --- REGISTER ---
if ($action == 'register') {
    $username = htmlspecialchars($_POST['username'] ?? '');
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    // Cek email ganda
    $cek = $conn->query("SELECT id FROM users WHERE email = '$email'");
    if ($cek->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Email sudah terdaftar!"]);
        exit;
    }

    $passHash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'user')");
    $stmt->bind_param("sss", $username, $email, $passHash);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Daftar berhasil! Silakan login."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Gagal mendaftar."]);
    }

// --- LOGIN ---
} elseif ($action == 'login') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            echo json_encode(["status" => "success", "role" => $row['role'], "user" => $row]);
        } else {
            echo json_encode(["status" => "error", "message" => "Password salah!"]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Email tidak ditemukan!"]);
    }

// --- CEK SESSION ---
} elseif ($action == 'check_session') {
    if (isset($_SESSION['user_id'])) {
        echo json_encode([
            "status" => "logged_in",
            "user" => [
                "id" => $_SESSION['user_id'],
                "username" => $_SESSION['username'],
                "role" => $_SESSION['role']
            ]
        ]);
    } else {
        echo json_encode(["status" => "guest"]);
    }

// --- LOGOUT ---
} elseif ($action == 'logout') {
    session_destroy();
    echo json_encode(["status" => "success"]);
}
?>