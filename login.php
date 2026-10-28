<?php
session_start();

require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.html');
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    header('Location: index.html?error=empty');
    exit;
}

$sql = "SELECT user_id, full_name, email, password_hash, role, status
        FROM users
        WHERE email = :email
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute(['email' => $email]);
$user = $stmt->fetch();

if (
    $user &&
    $user['status'] === 'Active' &&
    password_verify($password, $user['password_hash'])
) {
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];

    header('Location: dashboard.php');
    exit;
}

header('Location: index.html?error=invalid');
exit;