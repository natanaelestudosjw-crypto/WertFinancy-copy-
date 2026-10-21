<?php
require_once "config/database.php";
require_once "src/Auth.php";
require_once "src/FinanceRepository.php";

$userId = Auth::requireLogin();
$repo = new FinanceRepository($pdo);

$saldoTotal            = $repo->getConsolidatedBalance($userId);
$receitasMes           = $repo->getCurrentMonthTotalByType($userId, 'income');
$despesasMes           = $repo->getCurrentMonthTotalByType($userId, 'expense');
$resultadoMes          = $receitasMes - $despesasMes;
$gastosPorCategoria    = $repo->getCurrentMonthTotalsByCategory($userId, 'expense');
$receitasPorCategoria  = $repo->getCurrentMonthTotalsByCategory($userId, 'income');
$ultimosLancamentos    = $repo->getRecentTransactions($userId, 8);
$metas                 = $repo->getUserGoals($userId);
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
    <div class="acoes-topo">
        <a class="btn-perfil" href="perfil.php" aria-label="Abrir meu perfil" title="Meu perfil">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5Z"/></svg>
        </a>
        <a class="sair" href="calendario.php" title="Abrir Calendário Financeiro">
            <svg style="width:16px;height:16px;fill:currentColor;flex-shrink:0;" viewBox="0 0 24 24"><path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zm0-12H5V6h14v2z"/></svg>
            Calendário
        </a>
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
            <span class="rotulo">Saldo Atual</span>
            <span class="valor">R$ <?= number_format($saldoTotal, 2, ',', '.') ?></span>
        </div>
        <div class="cartao">
            <span class="rotulo">Entradas do mês</span>
            <span class="valor positivo">R$ <?= number_format($receitasMes, 2, ',', '.') ?></span>
        </div>
        <div class="cartao">
            <span class="rotulo">Despesas do mês</span>
            <span class="valor negativo">R$ <?= number_format($despesasMes, 2, ',', '.') ?></span>
        </div>
        <div class="cartao">
            <span class="rotulo">Fim do mês</span>
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
                                    <form method="POST" action="excluir_gasto.php" style="display:inline;" onsubmit="return confirm('Deseja realmente excluir este lançamento?');">
                                        <?= Auth::csrfField() ?>
                                        <input type="hidden" name="id" value="<?= $l['id'] ?>">
                                        <button type="submit" class="btn-acao-lancamento btn-acao-excluir" title="Excluir lançamento">Excluir</button>
                                    </form>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="bloco grafico">
            <div class="cabecalho-secao cabecalho-grafico">
                <h2 style="margin:0;">Distribuição por Categoria</h2>
                <div class="aba-grafico" style="margin-bottom:0;">
                    <button type="button" class="btn-aba ativo" id="btnDespesas">Despesas</button>
                    <button type="button" class="btn-aba" id="btnReceitas">Receitas</button>
                    <button type="button" class="btn-aba" id="btnComparativo">Comparativo</button>
                </div>
            </div>

            <div id="totalTipoExibicao" class="total-exibicao-grafico"></div>

            <div id="containerGraficoCategorias" class="grafico-categorias-container">
                <canvas id="graficoCategorias"></canvas>
            </div>

            <div id="containerDetalhesCategorias" class="lista-detalhes-categorias"></div>

        </div>

    </section>

    <section class="secao-metas">
        <div class="bloco">
            <div class="cabecalho-secao cabecalho-metas">
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
    '#D4AF6C', '#00C896', '#3282F6', '#A78BFA',
    '#F472B6', '#E5B85C', '#64748B', '#38BDF8'
];

const ctx = document.getElementById('graficoCategorias');
const containerGrafico = document.getElementById('containerGraficoCategorias');
const containerDetalhes = document.getElementById('containerDetalhesCategorias');
const totalTipoExibicao = document.getElementById('totalTipoExibicao');
const btnDespesas = document.getElementById('btnDespesas');
const btnReceitas = document.getElementById('btnReceitas');
const btnComparativo = document.getElementById('btnComparativo');

let chartInstance = null;

function renderizarGrafico(lista, tituloTipo) {
    containerGrafico.classList.remove('modo-comparativo');
    const totalGeral = lista.reduce((acc, item) => acc + parseFloat(item.total), 0);
    const temDados = lista.length > 0 && totalGeral > 0;

    totalTipoExibicao.textContent = 'Total de ' + tituloTipo + ': R$ ' + totalGeral.toLocaleString('pt-BR', { minimumFractionDigits: 2 });

    const labels = temDados ? lista.map(item => item.categoria) : ['Sem ' + tituloTipo.toLowerCase() + ' no mês'];
    const data = temDados ? lista.map(item => parseFloat(item.total)) : [1];
    const cores = temDados ? palette.slice(0, data.length) : ['#1A2A30'];

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
                borderColor: '#0F171B',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    enabled: temDados,
                    backgroundColor: '#1A2A30',
                    borderColor: '#2B3A43',
                    borderWidth: 1,
                    titleColor: '#F4EFE3',
                    bodyColor: '#F4EFE3',
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

function renderizarGraficoComparativo() {
    containerGrafico.classList.add('modo-comparativo');
    const categorias = [...new Set([
        ...dadosReceitas.map(item => item.categoria),
        ...dadosDespesas.map(item => item.categoria)
    ])];
    const labels = categorias.length ? categorias : ['Sem lançamentos no período'];
    const totalPorCategoria = lista => lista.reduce((totais, item) => {
        totais[item.categoria] = (totais[item.categoria] || 0) + parseFloat(item.total);
        return totais;
    }, {});
    const entradas = totalPorCategoria(dadosReceitas);
    const saidas = totalPorCategoria(dadosDespesas);

    totalTipoExibicao.textContent = 'Entradas e saídas por categoria';
    containerDetalhes.innerHTML = '';

    if (chartInstance) {
        chartInstance.destroy();
    }

    chartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Entradas',
                    data: labels.map(categoria => Object.hasOwn(entradas, categoria) ? entradas[categoria] : null),
                    backgroundColor: 'rgba(0, 200, 150, 0.78)',
                    borderColor: '#00C896',
                    borderWidth: 1,
                    borderRadius: 5,
                    categoryPercentage: 0.72,
                    barPercentage: 0.9,
                    skipNull: true
                },
                {
                    label: 'Saídas',
                    data: labels.map(categoria => Object.hasOwn(saidas, categoria) ? saidas[categoria] : null),
                    backgroundColor: 'rgba(239, 68, 68, 0.78)',
                    borderColor: '#EF4444',
                    borderWidth: 1,
                    borderRadius: 5,
                    categoryPercentage: 0.72,
                    barPercentage: 0.9,
                    skipNull: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    offset: true,
                    ticks: { color: '#A7B0BA', maxRotation: 40, minRotation: 0 },
                    grid: { display: false }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#A7B0BA',
                        callback: value => 'R$ ' + Number(value).toLocaleString('pt-BR')
                    },
                    grid: { color: 'rgba(167, 176, 186, 0.12)' }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    labels: { color: '#F4EFE3', usePointStyle: true, padding: 16 }
                },
                tooltip: {
                    backgroundColor: '#1A2A30',
                    borderColor: '#2B3A43',
                    borderWidth: 1,
                    titleColor: '#F4EFE3',
                    bodyColor: '#F4EFE3',
                    callbacks: {
                        label: context => `${context.dataset.label}: R$ ${context.parsed.y.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`
                    }
                }
            }
        }
    });
}

btnDespesas.addEventListener('click', () => {
    btnDespesas.classList.add('ativo');
    btnReceitas.classList.remove('ativo');
    btnComparativo.classList.remove('ativo');
    renderizarGrafico(dadosDespesas, 'Despesas');
});

btnReceitas.addEventListener('click', () => {
    btnReceitas.classList.add('ativo');
    btnDespesas.classList.remove('ativo');
    btnComparativo.classList.remove('ativo');
    renderizarGrafico(dadosReceitas, 'Receitas');
});

btnComparativo.addEventListener('click', () => {
    btnComparativo.classList.add('ativo');
    btnDespesas.classList.remove('ativo');
    btnReceitas.classList.remove('ativo');
    renderizarGraficoComparativo();
});

// Inicializa mostrando Despesas
renderizarGrafico(dadosDespesas, 'Despesas');
</script>

</body>
</html>
