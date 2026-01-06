<?php
// C:\xampp\htdocs\makealarge\check-login.php
session_start();
header('Content-Type: application/json');

$response = array('loggedIn' => false);

if (isset($_SESSION['user_id'])) {
    $response['loggedIn'] = true;
    $response['user'] = array(
        'name' => $_SESSION['name'],
        'role' => $_SESSION['role']
    );
}

echo json_encode($response);
?>