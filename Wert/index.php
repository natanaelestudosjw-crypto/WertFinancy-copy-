<?php
session_start();

if (empty($_SESSION['user_id'])) {
    header("Location: paginalogar.php");
    exit;
}

require_once "config/database.php";

$userId = $_SESSION['user_id'];

// Saldo inicial das contas
$stmt = $pdo->prepare("SELECT COALESCE(SUM(initial_balance), 0) FROM accounts WHERE user_id = ?");
$stmt->execute([$userId]);
$saldoInicial = (float) $stmt->fetchColumn();

// Total histórico de receitas realizadas
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = ? AND type = 'income' AND status = 'completed'");
$stmt->execute([$userId]);
$totalReceitasRealizadas = (float) $stmt->fetchColumn();

// Total histórico de despesas realizadas
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = ? AND type = 'expense' AND status = 'completed'");
$stmt->execute([$userId]);
$totalDespesasRealizadas = (float) $stmt->fetchColumn();

// Saldo total acumulado
$saldoTotal = $saldoInicial + $totalReceitasRealizadas - $totalDespesasRealizadas;

// Receitas do mês atual
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(amount), 0) FROM transactions
     WHERE user_id = ? AND type = 'income'
     AND MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())"
);
$stmt->execute([$userId]);
$receitasMes = (float) $stmt->fetchColumn();

// Despesas do mês atual
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(amount), 0) FROM transactions
     WHERE user_id = ? AND type = 'expense'
     AND MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())"
);
$stmt->execute([$userId]);
$despesasMes = (float) $stmt->fetchColumn();

$resultadoMes = $receitasMes - $despesasMes;

// Despesas por categoria no mês atual
$stmt = $pdo->prepare(
    "SELECT COALESCE(c.name, 'Sem categoria') AS categoria, COALESCE(SUM(t.amount), 0) AS total
     FROM transactions t
     LEFT JOIN categories c ON c.id = t.category_id
     WHERE t.user_id = ? AND t.type = 'expense'
       AND MONTH(t.date) = MONTH(CURDATE()) AND YEAR(t.date) = YEAR(CURDATE())
     GROUP BY t.category_id, c.name
     ORDER BY total DESC"
);
$stmt->execute([$userId]);
$gastosPorCategoria = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Receitas por categoria no mês atual
$stmt = $pdo->prepare(
    "SELECT COALESCE(c.name, 'Sem categoria') AS categoria, COALESCE(SUM(t.amount), 0) AS total
     FROM transactions t
     LEFT JOIN categories c ON c.id = t.category_id
     WHERE t.user_id = ? AND t.type = 'income'
       AND MONTH(t.date) = MONTH(CURDATE()) AND YEAR(t.date) = YEAR(CURDATE())
     GROUP BY t.category_id, c.name
     ORDER BY total DESC"
);
$stmt->execute([$userId]);
$receitasPorCategoria = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Últimos lançamentos
$stmt = $pdo->prepare(
    "SELECT t.id, t.description, t.amount, t.type, t.date, t.due_date, t.status, t.urgency, c.name AS categoria
     FROM transactions t
     LEFT JOIN categories c ON c.id = t.category_id
     WHERE t.user_id = ?
     ORDER BY t.date DESC, t.id DESC
     LIMIT 8"
);
$stmt->execute([$userId]);
$ultimosLancamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Metas financeiras do usuário
$stmt = $pdo->prepare(
    "SELECT id, name, target_amount, current_amount, deadline
     FROM goals
     WHERE user_id = ?
     ORDER BY deadline ASC, id DESC"
);
$stmt->execute([$userId]);
$metas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wert - Painel Financeiro</title>
    <link rel="stylesheet" href="style_index.css?v=<?= filemtime('style_index.css') ?>">
</head>
<body>

<header class="topo">
    <div class="brand-wrapper">
        <div class="brand-icon">W</div>
        <div class="brand-text">Wert<span>.</span></div>
        <div class="user-greeting">Olá, <strong><?= htmlspecialchars($_SESSION['user_name'] ?? 'Usuário') ?></strong></div>
    </div>
    <div style="display:flex; gap:12px; align-items:center;">
        <a class="btn-header-primary" href="adicionar_gasto.php">+ Novo lançamento</a>
        <a class="sair" href="logout.php">Sair</a>
    </div>
</header>

<main class="painel">

    <?php if (!empty($_GET['sucesso'])): ?>
        <div class="alerta-dashboard">
            <span><?= htmlspecialchars($_GET['sucesso']) ?></span>
            <span style="cursor:pointer; font-weight:bold; font-size:16px;" onclick="this.parentElement.remove();">&times;</span>
        </div>
    <?php endif; ?>

    <section class="cartoes">
        <div class="cartao">
            <span class="rotulo">Saldo total</span>
            <span class="valor">R$ <?= number_format($saldoTotal, 2, ',', '.') ?></span>
        </div>
        <div class="cartao">
            <span class="rotulo">Receitas do mês</span>
            <span class="valor positivo">R$ <?= number_format($receitasMes, 2, ',', '.') ?></span>
        </div>
        <div class="cartao">
            <span class="rotulo">Despesas do mês</span>
            <span class="valor negativo">R$ <?= number_format($despesasMes, 2, ',', '.') ?></span>
        </div>
        <div class="cartao">
            <span class="rotulo">Resultado do mês</span>
            <span class="valor <?= $resultadoMes >= 0 ? 'positivo' : 'negativo' ?>">
                R$ <?= number_format($resultadoMes, 2, ',', '.') ?>
            </span>
        </div>
    </section>

    <section class="grade">

        <div class="bloco lancamentos">
            <h2>Últimos lançamentos</h2>
            <?php if (empty($ultimosLancamentos)): ?>
                <p class="vazio">Nenhum lançamento registrado ainda.</p>
            <?php else: ?>
                <ul class="lista-lancamentos">
                    <?php foreach ($ultimosLancamentos as $l): ?>
                        <li>
                            <div class="info">
                                <span class="descricao"><?= htmlspecialchars($l['description'] ?: 'Sem descrição') ?></span>
                                <span class="categoria">
                                    <?= htmlspecialchars($l['categoria'] ?? 'Sem categoria') ?> · <?= date('d/m/Y', strtotime($l['date'])) ?>
                                    · <span class="badge-status badge-<?= $l['status'] ?>"><?= $l['status'] === 'completed' ? 'Pago' : ($l['status'] === 'cancelled' ? 'Cancelado' : 'Pendente') ?></span>
                                    <?php if (!empty($l['due_date'])): ?> · vence <?= date('d/m/Y', strtotime($l['due_date'])) ?><?php endif; ?>
                                    <?php if (!empty($l['urgency'])): ?> · urgência <span class="<?= $l['urgency'] === 'alta' ? 'badge-urgencia-alta' : '' ?>"><?= ['baixa' => 'baixa', 'media' => 'média', 'alta' => 'alta'][$l['urgency']] ?? $l['urgency'] ?></span><?php endif; ?>
                                </span>
                            </div>
                            <div style="display:flex; flex-direction:column; align-items:flex-end; gap:6px;">
                                <span class="valor-item <?= $l['type'] === 'income' ? 'positivo' : 'negativo' ?>">
                                    <?= $l['type'] === 'income' ? '+' : '-' ?> R$ <?= number_format($l['amount'], 2, ',', '.') ?>
                                </span>
                                <div style="display:flex; gap:6px;">
                                    <a href="editar_gasto.php?id=<?= $l['id'] ?>" class="btn-acao-lancamento" title="Editar este lançamento">Editar</a>
                                    <a href="excluir_gasto.php?id=<?= $l['id'] ?>" class="btn-acao-lancamento btn-acao-excluir" onclick="return confirm('Deseja realmente excluir este lançamento?');" title="Excluir lançamento">Excluir</a>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="bloco grafico">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 12px;">
                <h2 style="margin:0;">Distribuição por Categoria</h2>
                <div class="aba-grafico" style="margin-bottom:0;">
                    <button type="button" class="btn-aba ativo" id="btnDespesas">Despesas</button>
                    <button type="button" class="btn-aba" id="btnReceitas">Receitas</button>
                </div>
            </div>

            <div id="totalTipoExibicao" class="total-exibicao-grafico"></div>

            <div style="position: relative; max-width: 260px; margin: 10px auto;">
                <canvas id="graficoCategorias"></canvas>
            </div>

            <div id="containerDetalhesCategorias" class="lista-detalhes-categorias"></div>
        </div>

    </section>

    <section class="secao-metas">
        <div class="bloco">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h2 style="margin:0;">Metas Financeiras</h2>
                <a class="sair" href="metas.php" style="font-size:12px; padding: 5px 12px;">Gerenciar Metas</a>
            </div>

            <?php if (empty($metas)): ?>
                <p class="vazio" style="margin-top:14px;">Você ainda não possui metas cadastradas. Crie uma meta para acompanhar suas economias e sonhos!</p>
            <?php else: ?>
                <div class="grade-metas">
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
                                <span style="font-size:11px; color:var(--color-text-secondary);">Prazo: <?= date('d/m/Y', strtotime($m['deadline'])) ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

</main>

<script src="js/chart.umd.min.js"></script>
<script>
if (typeof Chart === 'undefined') {
    document.write('<script src="https://cdn.jsdelivr.net/npm/chart.js"><\/script>');
}
</script>
<script>
const dadosDespesas = <?= json_encode($gastosPorCategoria) ?>;
const dadosReceitas = <?= json_encode($receitasPorCategoria) ?>;

const palette = [
    '#39E58C', '#48CFFF', '#FF647C', '#F5B942',
    '#57F0A2', '#818CF8', '#A78BFA', '#F472B6', '#38BDF8'
];

const ctx = document.getElementById('graficoCategorias');
const containerDetalhes = document.getElementById('containerDetalhesCategorias');
const totalTipoExibicao = document.getElementById('totalTipoExibicao');
const btnDespesas = document.getElementById('btnDespesas');
const btnReceitas = document.getElementById('btnReceitas');

let chartInstance = null;

function renderizarGrafico(lista, tituloTipo) {
    const totalGeral = lista.reduce((acc, item) => acc + parseFloat(item.total), 0);
    const temDados = lista.length > 0 && totalGeral > 0;

    totalTipoExibicao.textContent = 'Total de ' + tituloTipo + ': R$ ' + totalGeral.toLocaleString('pt-BR', { minimumFractionDigits: 2 });

    const labels = temDados ? lista.map(item => item.categoria) : ['Sem ' + tituloTipo.toLowerCase() + ' no mês'];
    const data = temDados ? lista.map(item => parseFloat(item.total)) : [1];
    const cores = temDados ? palette.slice(0, data.length) : ['#1B2632'];

    if (chartInstance) {
        chartInstance.destroy();
    }

    chartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: cores,
                borderColor: '#111821',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    enabled: temDados,
                    backgroundColor: '#1B2632',
                    borderColor: '#273442',
                    borderWidth: 1,
                    titleColor: '#F1F5F9',
                    bodyColor: '#F1F5F9',
                    padding: 10,
                    callbacks: {
                        label: function(context) {
                            const val = context.parsed || 0;
                            const pct = totalGeral > 0 ? ((val / totalGeral) * 100).toFixed(1) : 0;
                            return ' ' + context.label + ': R$ ' + val.toLocaleString('pt-BR', { minimumFractionDigits: 2 }) + ' (' + pct + '%)';
                        }
                    }
                }
            }
        }
    });

    // Renderiza lista detalhada com porcentagens
    containerDetalhes.innerHTML = '';
    if (!temDados) {
        containerDetalhes.innerHTML = '<p class="vazio" style="text-align:center; margin:8px 0;">Nenhum lançamento registrado neste mês.</p>';
        return;
    }

    lista.forEach((item, idx) => {
        const val = parseFloat(item.total);
        const pct = ((val / totalGeral) * 100).toFixed(1);
        const cor = cores[idx % cores.length];

        const row = document.createElement('div');
        row.className = 'item-detalhe-cat';
        row.innerHTML = `
            <div style="display:flex; align-items:center;">
                <span class="cat-indicador" style="background:${cor};"></span>
                <span>${item.categoria}</span>
            </div>
            <div style="display:flex; gap:12px; font-weight:bold;">
                <span style="color:var(--texto-secundario); font-size:12px;">${pct}%</span>
                <span>R$ ${val.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</span>
            </div>
        `;
        containerDetalhes.appendChild(row);
    });
}

btnDespesas.addEventListener('click', () => {
    btnDespesas.classList.add('ativo');
    btnReceitas.classList.remove('ativo');
    renderizarGrafico(dadosDespesas, 'Despesas');
});

btnReceitas.addEventListener('click', () => {
    btnReceitas.classList.add('ativo');
    btnDespesas.classList.remove('ativo');
    renderizarGrafico(dadosReceitas, 'Receitas');
});

// Inicializa mostrando Despesas
renderizarGrafico(dadosDespesas, 'Despesas');
</script>

</body>
</html>
