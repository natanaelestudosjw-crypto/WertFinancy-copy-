<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header("Location: paginalogar.php");
    exit;
}
require_once "config/database.php";
$userId = $_SESSION['user_id'];

// Carrega contas do usuário
$contasStmt = $pdo->prepare("SELECT id, name FROM accounts WHERE user_id = ?");
$contasStmt->execute([$userId]);
$contas = $contasStmt->fetchAll(PDO::FETCH_ASSOC);

// Garante ao menos uma conta se o usuário não possuir
if (empty($contas)) {
    $ins = $pdo->prepare("INSERT INTO accounts (user_id, name, initial_balance) VALUES (?, 'Carteira Principal', 0.00)");
    $ins->execute([$userId]);
    $contas = [['id' => $pdo->lastInsertId(), 'name' => 'Carteira Principal']];
}

// Carrega categorias do usuário
$catStmt = $pdo->prepare("SELECT id, name, type FROM categories WHERE user_id = ? ORDER BY name ASC");
$catStmt->execute([$userId]);
$categorias = $catStmt->fetchAll(PDO::FETCH_ASSOC);

// Garante categorias padrão caso não existam
if (empty($categorias)) {
    $defaults = [
        ['Alimentação', 'expense'],
        ['Moradia', 'expense'],
        ['Transporte', 'expense'],
        ['Lazer', 'expense'],
        ['Saúde', 'expense'],
        ['Salário', 'income'],
        ['Investimentos', 'income'],
        ['Outros', 'expense']
    ];
    $insCat = $pdo->prepare("INSERT INTO categories (user_id, name, type) VALUES (?, ?, ?)");
    foreach ($defaults as [$cName, $cType]) {
        $insCat->execute([$userId, $cName, $cType]);
    }
    $catStmt->execute([$userId]);
    $categorias = $catStmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Novo lançamento - Wert</title>
    <link rel="stylesheet" href="style_index.css?v=<?= filemtime('style_index.css') ?>">
    <link rel="stylesheet" href="style_form.css?v=<?= filemtime('style_form.css') ?>">
</head>
<body>
<header class="topo">
    <div class="brand-wrapper">
        <div class="brand-icon">W</div>
        <div class="brand-text">Wert<span>.</span></div>
    </div>
    <div style="display:flex; gap:12px; align-items:center;">
        <span style="font-size:14px; color:var(--color-text-secondary);">Novo Lançamento</span>
        <a class="sair" href="index.php">Voltar</a>
    </div>
</header>

<main class="painel">
<form class="form-lancamento" action="salvar_gasto.php" method="POST">

    <label>Tipo
        <select name="type" id="tipoSelect" required>
            <option value="expense" selected>Despesa</option>
            <option value="income">Receita</option>
        </select>
    </label>

    <label>Descrição (opcional)
        <input type="text" name="description" maxlength="255" placeholder="Ex: Supermercado, Salário...">
    </label>

    <label>Valor (R$)
        <input type="number" step="0.01" min="0.01" name="amount" placeholder="0,00" required autofocus>
    </label>

    <label>Conta
        <select name="account_id" required>
            <?php foreach ($contas as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>Categoria
        <select name="category_id" id="categoriaSelect" required>
            <?php foreach ($categorias as $c): ?>
                <option value="<?= $c['id'] ?>" data-type="<?= $c['type'] ?>">
                    <?= htmlspecialchars($c['name']) ?> (<?= $c['type'] === 'income' ? 'receita' : 'despesa' ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>Data do lançamento
        <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
    </label>

    <label>Data de vencimento / pagamento
        <input type="date" name="due_date" value="<?= date('Y-m-d') ?>">
    </label>

    <label>Status
        <select name="status" required>
            <option value="completed" selected>Pago / Recebido</option>
            <option value="pending">Pendente (Não pago)</option>
        </select>
    </label>

    <label>Urgência
        <select name="urgency" required>
            <option value="baixa">Baixa</option>
            <option value="media" selected>Média</option>
            <option value="alta">Alta</option>
        </select>
    </label>

    <button type="submit">Salvar Lançamento</button>
</form>
</main>

<script>
// Filtra dinamicamente as categorias de acordo com o tipo selecionado (despesa ou receita)
const tipoSelect = document.getElementById('tipoSelect');
const catSelect = document.getElementById('categoriaSelect');

function filtrarCategorias() {
    const tipo = tipoSelect.value;
    let primeiroVisivel = null;

    Array.from(catSelect.options).forEach(opt => {
        const optType = opt.getAttribute('data-type');
        if (optType === tipo) {
            opt.style.display = '';
            opt.disabled = false;
            if (!primeiroVisivel) primeiroVisivel = opt;
        } else {
            opt.style.display = 'none';
            opt.disabled = true;
        }
    });

    if (primeiroVisivel && catSelect.selectedOptions[0]?.disabled) {
        catSelect.value = primeiroVisivel.value;
    }
}

tipoSelect.addEventListener('change', filtrarCategorias);
filtrarCategorias();
</script>

</body>
</html>