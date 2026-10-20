<?php
session_start();

require_once "config/database.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: paginalogar.php");
    exit;
}

$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';

if (empty($email) || empty($senha)) {
    header("Location: paginalogar.php?erro=" . urlencode("Por favor, preencha todos os campos."));
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erro ao buscar usuário: " . $e->getMessage());
    header("Location: paginalogar.php?erro=" . urlencode("Erro no servidor. Tente novamente mais tarde."));
    exit;
}

if (!$user || !password_verify($senha, $user['password'])) {
    header("Location: paginalogar.php?erro=" . urlencode("E-mail ou senha incorretos."));
    exit;
}

// Regenera o ID da sessão para prevenir Session Fixation
session_regenerate_id(true);

$_SESSION['user_id']   = $user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_role'] = $user['role'] ?? 'user';

header("Location: index.php");
exit;