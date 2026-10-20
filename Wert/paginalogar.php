<?php
session_start();

// Se o usuário já estiver logado, redireciona diretamente ao dashboard
if (!empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$erro = $_GET['erro'] ?? '';
$sucesso = $_GET['sucesso'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style_login.css?v=<?= filemtime('style_login.css') ?>">
    <title>Wert - Login</title>
</head>
<body>

<div class="brand-header">
    <div class="brand-icon">W</div>
    <div class="brand-text">Wert<span>.</span></div>
</div>

<div>
    <h1>Acesse sua conta</h1>
    <p class="subtitulo-auth">Gestão financeira pessoal com clareza e controle</p>

    <?php if (!empty($erro)): ?>
        <div class="alerta alerta-erro">
            <?= htmlspecialchars($erro) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($sucesso)): ?>
        <div class="alerta alerta-sucesso">
            <?= htmlspecialchars($sucesso) ?>
        </div>
    <?php endif; ?>

    <div class="login">
        <form action="login.php" method="POST">
            <input type="email" name="email" placeholder="Seu e-mail" required autofocus>
            <input type="password" name="senha" placeholder="Sua senha" required>
            <button type="submit">Entrar no Wert</button>
        </form>
    </div>

    <div class="outro">
        <button type="button" onclick="window.location.href='cadastrar.php'">Criar conta</button>
        <button type="button" onclick="window.location.href='recuperar.php'">Recuperar senha</button>
    </div>
</div>

</body>
</html>
