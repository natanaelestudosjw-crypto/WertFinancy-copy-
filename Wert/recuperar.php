<?php
session_start();

if (!empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$mensagem = '';
$sucesso = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = 'Informe um e-mail válido.';
    } else {
        // Simulação/preparação para envio de token/e-mail
        $mensagem = 'Se houver uma conta associada a este e-mail, as instruções de recuperação serão enviadas.';
        $sucesso = true;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style_login.css?v=<?= filemtime('style_login.css') ?>">
    <title>Wert - Recuperar Senha</title>
</head>
<body>

<div class="brand-header">
    <div class="brand-icon">W</div>
    <div class="brand-text">Wert<span>.</span></div>
</div>

<div>
    <h1>Recuperar senha</h1>
    <p class="subtitulo-auth">Instruções serão enviadas para o seu e-mail</p>

    <?php if (!empty($mensagem)): ?>
        <div class="alerta <?= $sucesso ? 'alerta-sucesso' : 'alerta-erro' ?>">
            <?= htmlspecialchars($mensagem) ?>
        </div>
    <?php endif; ?>

    <div class="login">
        <form action="recuperar.php" method="POST">
            <input type="email" name="email" placeholder="Digite seu e-mail cadastrado" required autofocus>
            <button type="submit">Enviar instruções</button>
        </form>
    </div>

    <div class="outro">
        <button type="button" onclick="window.location.href='paginalogar.php'">Voltar ao Login</button>
        <button type="button" onclick="window.location.href='cadastrar.php'">Criar Conta</button>
    </div>
</div>
</body>
</html>