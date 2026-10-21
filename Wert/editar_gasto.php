<?php
require_once "config/database.php";
require_once "src/Auth.php";
require_once "src/FinanceRepository.php";

$userId = Auth::requireLogin();
$repo = new FinanceRepository($pdo);

$id = $_GET['id'] ?? $_POST['id'] ?? 0;

if (!ctype_digit((string)$id) || (int)$id <= 0) {
    header("Location: index.php");
    exit;
}

$id = (int)$id;

// Busca o lançamento garantindo que pertença ao usuário logado
$lancamento = $repo->findTransactionByIdAndUser($id, $userId);

if (!$lancamento) {
    header("Location: index.php");
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type        = $_POST['type'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $amount      = $_POST['amount'] ?? '';
    $accountId   = $_POST['account_id'] ?? '';
    $categoryId  = $_POST['category_id'] ?? '';
    $date        = $_POST['date'] ?? '';
    $dueDate     = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $status      = $_POST['status'] ?? 'pending';
    $urgency     = $_POST['urgency'] ?? 'media';

    // Urgência não se aplica a entradas; nunca aceite um valor enviado pelo navegador.
    if ($type === 'income') {
        $urgency = 'media';
    }

    if (!Auth::validateCsrf($_POST['csrf_token'] ?? null)) {
        $erro = 'Não foi possível validar a solicitação. Atualize a página e tente novamente.';
    } elseif (!in_array($type, ['income', 'expense'], true)
        || !is_numeric($amount) || $amount <= 0
        || !ctype_digit((string)$accountId)
        || !ctype_digit((string)$categoryId)
        || empty($date)
        || !in_array($status, ['pending', 'completed', 'cancelled'], true)
        || !in_array($urgency, ['baixa', 'media', 'alta'], true)
    ) {
        $erro = 'Por favor, preencha todos os campos obrigatórios com valores válidos.';
    } else {
        try {
            $atualizado = $repo->updateTransaction($id, $userId, [
                'account_id'  => (int)$accountId,
                'category_id' => (int)$categoryId,
                'type'        => $type,
                'amount'      => $amount,
                'description' => $description,
                'date'        => $date,
                'due_date'    => $dueDate,
                'status'      => $status,
                'urgency'     => $urgency,
            ]);

            if (!$atualizado) {
                $erro = 'Conta ou categoria selecionada é inválida para sua conta.';
            } else {
                $origem = $_POST['origem'] ?? $_GET['origem'] ?? '';
                if ($origem === 'calendario') {
                    $refDate = $dueDate ?: $date;
                    $m = date('n', strtotime($refDate));
                    $y = date('Y', strtotime($refDate));
                    header("Location: calendario.php?mes=$m&ano=$y&sucesso=" . urlencode("Lançamento atualizado com sucesso!"));
                } else {
                    header("Location: index.php?sucesso=" . urlencode("Lançamento atualizado com sucesso!"));
                }
                exit;
            }
        } catch (PDOException $e) {
            error_log("Erro ao atualizar lançamento: " . $e->getMessage());
            $erro = 'Erro ao salvar as alterações no banco de dados.';
        }
    }

    // Mantém os dados submetidos para o formulário
    $lancamento['type']        = $type;
    $lancamento['description'] = $description;
    $lancamento['amount']      = $amount;
    $lancamento['account_id']  = $accountId;
    $lancamento['category_id'] = $categoryId;
    $lancamento['date']        = $date;
    $lancamento['due_date']    = $dueDate;
    $lancamento['status']      = $status;
    $lancamento['urgency']     = $urgency;
}

[$contas, $categorias] = $repo->getOrInitUserAccountsAndCategories($userId);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar Lançamento - Wert</title>
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
        <span style="font-size:14px; color:var(--color-text-secondary);">Editar Lançamento</span>
        <a class="btn-perfil" href="perfil.php" aria-label="Abrir meu perfil" title="Meu perfil">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5Z"/></svg>
        </a>
        <a class="sair" href="<?= (!empty($_GET['origem']) && $_GET['origem'] === 'calendario') ? 'calendario.php' : 'index.php' ?>">Voltar</a>
    </div>
</header>

<main class="painel">

    <?php if (!empty($erro)): ?>
        <div style="max-width: 480px; margin: 0 auto 16px auto; background: rgba(239, 68, 68, 0.10); border: 1px solid rgba(239, 68, 68, 0.35); color: #FCA5A5; padding: 12px 16px; border-radius: var(--radius-md); font-size: 13px;">
            <?= htmlspecialchars($erro) ?>
        </div>
    <?php endif; ?>

    <form class="form-lancamento" action="editar_gasto.php" method="POST">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <?php if (!empty($_GET['origem']) || !empty($_POST['origem'])): ?>
            <input type="hidden" name="origem" value="<?= htmlspecialchars($_POST['origem'] ?? $_GET['origem'] ?? '') ?>">
        <?php endif; ?>

        <label>Tipo
            <select name="type" id="tipoSelect" required>
                <option value="expense" <?= $lancamento['type'] === 'expense' ? 'selected' : '' ?>>Despesa</option>
                <option value="income" <?= $lancamento['type'] === 'income' ? 'selected' : '' ?>>Receita</option>
            </select>
        </label>

        <label>Descrição (opcional)
            <input type="text" name="description" maxlength="255" value="<?= htmlspecialchars($lancamento['description'] ?? '') ?>" placeholder="Ex: Supermercado, Salário...">
        </label>

        <label>Valor (R$)
            <input type="number" step="0.01" min="0.01" name="amount" value="<?= htmlspecialchars($lancamento['amount'] ?? '') ?>" required autofocus>
        </label>

        <label>Conta
            <select name="account_id" required>
                <?php foreach ($contas as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $lancamento['account_id'] == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Categoria
            <select name="category_id" id="categoriaSelect" required>
                <?php foreach ($categorias as $c): ?>
                    <option value="<?= $c['id'] ?>" data-type="<?= $c['type'] ?>" <?= $lancamento['category_id'] == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?> (<?= $c['type'] === 'income' ? 'receita' : 'despesa' ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Data do lançamento
            <input type="date" name="date" value="<?= htmlspecialchars($lancamento['date'] ?? date('Y-m-d')) ?>" required>
        </label>

        <label>Data de vencimento / pagamento
            <input type="date" name="due_date" value="<?= htmlspecialchars($lancamento['due_date'] ?? '') ?>">
        </label>

        <label>Status
            <select name="status" required>
                <option value="completed" <?= $lancamento['status'] === 'completed' ? 'selected' : '' ?>>Pago / Recebido</option>
                <option value="pending" <?= $lancamento['status'] === 'pending' ? 'selected' : '' ?>>Pendente (Não pago)</option>
                <option value="cancelled" <?= $lancamento['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelado</option>
            </select>
        </label>

        <label id="urgenciaCampo">Urgência
            <select name="urgency" required>
                <option value="baixa" <?= $lancamento['urgency'] === 'baixa' ? 'selected' : '' ?>>Baixa</option>
                <option value="media" <?= $lancamento['urgency'] === 'media' ? 'selected' : '' ?>>Média</option>
                <option value="alta" <?= $lancamento['urgency'] === 'alta' ? 'selected' : '' ?>>Alta</option>
            </select>
        </label>

        <div style="display:flex; gap:12px; margin-top:8px;">
            <button type="submit" style="flex:1;">Salvar Alterações</button>
            <a href="index.php" class="sair" style="text-align:center; display:flex; align-items:center; justify-content:center; text-decoration:none;">Cancelar</a>
        </div>
    </form>
</main>

<script>
const tipoSelect = document.getElementById('tipoSelect');
const catSelect = document.getElementById('categoriaSelect');

function filtrarCategorias() {
    const tipo = tipoSelect.value;
    let primeiroVisivel = null;
    let selecionadoAindaVisivel = false;

    Array.from(catSelect.options).forEach(opt => {
        const optType = opt.getAttribute('data-type');
        if (optType === tipo) {
            opt.style.display = '';
            opt.disabled = false;
            if (!primeiroVisivel) primeiroVisivel = opt;
            if (opt.selected) selecionadoAindaVisivel = true;
        } else {
            opt.style.display = 'none';
            opt.disabled = true;
        }
    });

    if (!selecionadoAindaVisivel && primeiroVisivel) {
        catSelect.value = primeiroVisivel.value;
    }
}

function atualizarUrgencia() {
    const campoUrgencia = document.getElementById('urgenciaCampo');
    const selectUrgencia = campoUrgencia.querySelector('select');
    const ehEntrada = tipoSelect.value === 'income';

    campoUrgencia.hidden = ehEntrada;
    selectUrgencia.disabled = ehEntrada;
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
