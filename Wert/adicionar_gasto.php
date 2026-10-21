<?php
require_once "config/database.php";
require_once "src/Auth.php";
require_once "src/FinanceRepository.php";

$userId = Auth::requireLogin();
$repo = new FinanceRepository($pdo);

[$contas, $categorias] = $repo->getOrInitUserAccountsAndCategories($userId);

// Data pré-selecionada (caso venha do clique em um dia específico do calendário)
$dataDefault = (!empty($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])) ? $_GET['date'] : date('Y-m-d');
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
    <div class="acoes-topo">
        <span style="font-size:14px; color:var(--color-text-secondary);">Novo Lançamento</span>
        <a class="btn-perfil" href="perfil.php" aria-label="Abrir meu perfil" title="Meu perfil">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5Z"/></svg>
        </a>
        <a class="sair" href="<?= (!empty($_GET['origem']) && $_GET['origem'] === 'calendario') ? 'calendario.php' : 'index.php' ?>">Voltar</a>
    </div>
</header>

<main class="painel">
<form class="form-lancamento" action="salvar_gasto.php" method="POST">
    <?= Auth::csrfField() ?>
    <?php if (!empty($_GET['origem'])): ?>
        <input type="hidden" name="origem" value="<?= htmlspecialchars($_GET['origem']) ?>">
    <?php endif; ?>

    <label>Tipo
        <select name="type" id="tipoSelect" required>
            <option value="income">Entrada</option>
            <option value="expense" selected>Saída</option>
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
        <input type="date" name="date" value="<?= htmlspecialchars($dataDefault) ?>" required>
    </label>

    <label>Data de vencimento / pagamento
        <input type="date" name="due_date" value="<?= htmlspecialchars($dataDefault) ?>">
    </label>

    <label>Status
        <select name="status" required>
            <option value="completed" selected>Pago / Recebido</option>
            <option value="pending">Pendente (Não pago)</option>
        </select>
    </label>

    <label id="urgenciaCampo">Urgência
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

function atualizarUrgencia() {
    const campoUrgencia = document.getElementById('urgenciaCampo');
    const selectUrgencia = campoUrgencia.querySelector('select');
    const ehEntrada = tipoSelect.value === 'income';

    campoUrgencia.hidden = ehEntrada;
    selectUrgencia.disabled = ehEntrada;
    selectUrgencia.required = !ehEntrada;
}

tipoSelect.addEventListener('change', () => {
    filtrarCategorias();
    atualizarUrgencia();
});
filtrarCategorias();
atualizarUrgencia();
</script>

</body>
</html>
