<?php
session_start();

// Se o usuário já estiver logado, redireciona ao dashboard
if (!empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

require_once "config/database.php";

$erro = '';
$nome = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome           = trim($_POST['nome'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $senha          = $_POST['senha'] ?? '';
    $confirmarSenha = $_POST['confirmar_senha'] ?? '';

    if (empty($nome) || empty($email) || empty($senha) || empty($confirmarSenha)) {
        $erro = 'Por favor, preencha todos os campos.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um endereço de e-mail válido.';
    } elseif (mb_strlen($senha) < 6) {
        $erro = 'A senha deve conter no mínimo 6 caracteres.';
    } elseif ($senha !== $confirmarSenha) {
        $erro = 'As senhas digitadas não coincidem.';
    } else {
        try {
            // Verifica se o e-mail já está cadastrado
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                $erro = 'Este e-mail já está cadastrado. Tente fazer login ou use outro.';
            } else {
                // Inicia transação para criar usuário e dados iniciais
                $pdo->beginTransaction();

                $hash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'user')");
                $stmt->execute([$nome, $email, $hash]);
                $userId = (int) $pdo->lastInsertId();

                // Cria uma conta inicial padrão
                $stmtAccount = $pdo->prepare("INSERT INTO accounts (user_id, name, initial_balance) VALUES (?, 'Carteira Principal', 0.00)");
                $stmtAccount->execute([$userId]);

                // Categorias essenciais padrão para o novo usuário
                $categoriasIniciais = [
                    ['Alimentação', 'expense'],
                    ['Moradia', 'expense'],
                    ['Transporte', 'expense'],
                    ['Lazer', 'expense'],
                    ['Saúde', 'expense'],
                    ['Educação', 'expense'],
                    ['Salário', 'income'],
                    ['Investimentos', 'income'],
                    ['Outros', 'income']
                ];

                $stmtCat = $pdo->prepare("INSERT INTO categories (user_id, name, type) VALUES (?, ?, ?)");
                foreach ($categoriasIniciais as [$catNome, $catTipo]) {
                    $stmtCat->execute([$userId, $catNome, $catTipo]);
                }

                $pdo->commit();

                // Autentica o usuário automaticamente
                session_regenerate_id(true);
                $_SESSION['user_id']   = $userId;
                $_SESSION['user_name'] = $nome;
                $_SESSION['user_role'] = 'user';

                header("Location: index.php");
                exit;
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Erro no cadastro de usuário: " . $e->getMessage());
            $erro = 'Ocorreu um erro ao processar o cadastro. Tente novamente mais tarde.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style_login.css?v=<?= filemtime('style_login.css') ?>">
    <title>Wert - Criar Conta</title>
</head>
<body>

<div class="brand-header">
    <div class="brand-icon">W</div>
    <div class="brand-text">Wert<span>.</span></div>
</div>

<div>
    <h1>Crie sua conta</h1>
    <p class="subtitulo-auth">Dê o primeiro passo para organizar suas finanças</p>

    <?php if (!empty($erro)): ?>
        <div class="alerta alerta-erro">
            <?= htmlspecialchars($erro) ?>
        </div>
    <?php endif; ?>

    <div class="login">
        <form action="cadastrar.php" method="POST">
            <input type="text" name="nome" placeholder="Seu nome completo" value="<?= htmlspecialchars($nome) ?>" required autofocus>
            <input type="email" name="email" placeholder="Seu melhor e-mail" value="<?= htmlspecialchars($email) ?>" required>
            <input type="password" name="senha" placeholder="Senha (mínimo 6 caracteres)" minlength="6" required>
            <input type="password" name="confirmar_senha" placeholder="Confirme sua senha" minlength="6" required>
            <button type="submit">Começar agora</button>
        </form>
    </div>

    <div class="outro">
        <button type="button" onclick="window.location.href='paginalogar.php'">Já tenho conta</button>
        <button type="button" onclick="window.location.href='recuperar.php'">Recuperar senha</button>
    </div>
</div>
</body>
</html>
