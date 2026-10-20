<?php
session_start();

if (empty($_SESSION['user_id'])) {
    header("Location: paginalogar.php");
    exit;
}

require_once "config/database.php";

$userId = $_SESSION['user_id'];
$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? 'criar';

    if ($acao === 'criar') {
        $nome          = trim($_POST['name'] ?? '');
        $targetAmount  = $_POST['target_amount'] ?? '';
        $currentAmount = $_POST['current_amount'] ?? 0;
        $deadline      = !empty($_POST['deadline']) ? $_POST['deadline'] : null;

        if (empty($nome) || !is_numeric($targetAmount) || $targetAmount <= 0) {
            $erro = 'Informe um nome para a meta e um valor-alvo válido maior que zero.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO goals (user_id, name, target_amount, current_amount, deadline)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->execute([$userId, $nome, $targetAmount, $currentAmount ?: 0, $deadline]);
                $sucesso = 'Meta criada com sucesso!';
            } catch (PDOException $e) {
                error_log("Erro ao criar meta: " . $e->getMessage());
                $erro = 'Erro ao salvar meta no banco de dados.';
            }
        }
    } elseif ($acao === 'atualizar') {
        $goalId        = $_POST['goal_id'] ?? 0;
        $novoValor     = $_POST['current_amount'] ?? 0;

        if (ctype_digit((string)$goalId) && is_numeric($novoValor) && $novoValor >= 0) {
            $stmt = $pdo->prepare("UPDATE goals SET current_amount = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$novoValor, $goalId, $userId]);
            $sucesso = 'Progresso da meta atualizado!';
        }
    } elseif ($acao === 'excluir') {
        $goalId = $_POST['goal_id'] ?? 0;
        if (ctype_digit((string)$goalId)) {
            $stmt = $pdo->prepare("DELETE FROM goals WHERE id = ? AND user_id = ?");
            $stmt->execute([$goalId, $userId]);
            $sucesso = 'Meta removida.';
        }
    }
}

// Carrega as metas do usuário
$stmt = $pdo->prepare("SELECT * FROM goals WHERE user_id = ? ORDER BY deadline ASC, id DESC");
$stmt->execute([$userId]);
$metas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Metas Financeiras - Wert</title>
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
        <span style="font-size:14px; color:var(--color-text-secondary);">Metas Financeiras</span>
        <a class="btn-perfil" href="perfil.php" aria-label="Abrir meu perfil" title="Meu perfil">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5Z"/></svg>
        </a>
        <a class="sair" href="calendario.php" title="Visualizar Calendário">Calendário</a>
        <a class="sair" href="index.php">Voltar ao Painel</a>
    </div>
</header>

<main class="painel">

    <?php if (!empty($erro)): ?>
        <div style="background: rgba(239, 68, 68, 0.10); border: 1px solid rgba(239, 68, 68, 0.35); color: #FCA5A5; padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px; font-size: 13px;">
            <?= htmlspecialchars($erro) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($sucesso)): ?>
        <div class="alerta-dashboard">
            <span><?= htmlspecialchars($sucesso) ?></span>
            <span style="cursor:pointer; font-weight:bold; font-size:16px;" onclick="this.parentElement.remove();">&times;</span>
        </div>
    <?php endif; ?>

    <section class="grade">

        <!-- Lista e acompanhamento de metas -->
        <div class="bloco">
            <h2>Suas Metas Ativas</h2>
            <?php if (empty($metas)): ?>
                <p class="vazio" style="margin-top:14px;">Nenhuma meta cadastrada ainda. Utilize o formulário ao lado para planejar suas economias e sonhos!</p>
            <?php else: ?>
                <div style="display:flex; flex-direction:column; gap:16px; margin-top:16px;">
                    <?php foreach ($metas as $m): 
                        $pct = $m['target_amount'] > 0 ? min(100, round(($m['current_amount'] / $m['target_amount']) * 100)) : 0;
                    ?>
                        <div class="cartao-meta">
                            <div class="meta-topo">
                                <span><?= htmlspecialchars($m['name']) ?></span>
                                <span class="meta-pct"><?= $pct ?>%</span>
                            </div>

                            <div class="meta-barra">
                                <div class="meta-progresso" style="width: <?= $pct ?>%;"></div>
                            </div>

                            <div class="meta-valores">
                                <span>R$ <?= number_format($m['current_amount'], 2, ',', '.') ?> acumulado</span>
                                <span>Alvo: R$ <?= number_format($m['target_amount'], 2, ',', '.') ?></span>
                            </div>

                            <?php if (!empty($m['deadline'])): ?>
                                <span style="font-size:12px; color:var(--color-text-secondary);">Prazo limite: <?= date('d/m/Y', strtotime($m['deadline'])) ?></span>
                            <?php endif; ?>

                            <!-- Formulário rápido para atualizar valor ou excluir -->
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px; border-top:1px solid var(--color-border); padding-top:10px;">
                                <form action="metas.php" method="POST" style="display:flex; gap:8px; align-items:center;">
                                    <input type="hidden" name="acao" value="atualizar">
                                    <input type="hidden" name="goal_id" value="<?= $m['id'] ?>">
                                    <input type="number" step="0.01" min="0" name="current_amount" value="<?= $m['current_amount'] ?>" style="height:32px; width:100px; padding:0 8px; font-size:12px;">
                                    <button type="submit" class="btn-aba ativo" style="padding:4px 10px; font-size:11px;">Atualizar</button>
                                </form>

                                <form action="metas.php" method="POST" onsubmit="return confirm('Deseja realmente excluir esta meta?');">
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="goal_id" value="<?= $m['id'] ?>">
                                    <button type="submit" class="btn-acao-lancamento btn-acao-excluir">Excluir</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Formulário para nova meta -->
        <div class="bloco">
            <h2>+ Nova Meta</h2>
            <form action="metas.php" method="POST" class="form-lancamento" style="max-width:100%; padding:0; background:transparent; border:none; box-shadow:none;">
                <input type="hidden" name="acao" value="criar">

                <label>Nome da Meta
                    <input type="text" name="name" placeholder="Ex: Reserva de emergência, Viagem..." required>
                </label>

                <label>Valor Alvo (R$)
                    <input type="number" step="0.01" min="0.01" name="target_amount" placeholder="0,00" required>
                </label>

                <label>Valor já economizado (R$)
                    <input type="number" step="0.01" min="0" name="current_amount" value="0.00">
                </label>

                <label>Data Limite (opcional)
                    <input type="date" name="deadline">
                </label>

                <button type="submit">Salvar Meta</button>
            </form>
        </div>

    </section>

</main>

</body>
</html>
