<?php
session_start();

if (empty($_SESSION['user_id'])) {
    header("Location: paginalogar.php");
    exit;
}

require_once "config/database.php";

$userId = $_SESSION['user_id'];

// Ação rápida: Alterar status da conta (pago / pendente / cancelado)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'toggle_status') {
    $transacaoId = (int)($_POST['id'] ?? 0);
    $novoStatus = $_POST['novo_status'] ?? '';
    $mesPost = (int)($_POST['mes'] ?? date('n'));
    $anoPost = (int)($_POST['ano'] ?? date('Y'));
    $criterioPost = $_POST['criterio'] ?? 'vencimento';

    if ($transacaoId > 0 && in_array($novoStatus, ['completed', 'pending', 'cancelled'], true)) {
        try {
            $stmt = $pdo->prepare("UPDATE transactions SET status = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$novoStatus, $transacaoId, $userId]);
            $msg = $novoStatus === 'completed' ? 'Conta marcada como paga/recebida!' : 'Conta marcada como pendente!';
            header("Location: calendario.php?mes=$mesPost&ano=$anoPost&criterio=$criterioPost&sucesso=" . urlencode($msg));
            exit;
        } catch (PDOException $e) {
            error_log("Erro ao atualizar status da transação: " . $e->getMessage());
        }
    }
}

// Parâmetros de data
$anoAtual = (int)date('Y');
$mesAtual = (int)date('n');
$diaAtual = (int)date('j');
$hojeStr = date('Y-m-d');

$mes = isset($_GET['mes']) && is_numeric($_GET['mes']) ? (int)$_GET['mes'] : $mesAtual;
$ano = isset($_GET['ano']) && is_numeric($_GET['ano']) ? (int)$_GET['ano'] : $anoAtual;

if ($mes < 1 || $mes > 12) { $mes = $mesAtual; }
if ($ano < 2000 || $ano > 2100) { $ano = $anoAtual; }

// Navegação de mês anterior e posterior
$mesAnt = $mes - 1;
$anoAnt = $ano;
if ($mesAnt < 1) {
    $mesAnt = 12;
    $anoAnt--;
}

$mesProx = $mes + 1;
$anoProx = $ano;
if ($mesProx > 12) {
    $mesProx = 1;
    $anoProx++;
}

$nomesMeses = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];
$nomeMesAtual = $nomesMeses[$mes];

// Critério de data: Data de Vencimento (padrão para contas a pagar) vs Data de Registro
$criterioData = $_GET['criterio'] ?? 'vencimento';
if ($criterioData === 'registro') {
    $campoDataSql = "t.date";
} else {
    $criterioData = 'vencimento';
    // Se tiver due_date (vencimento), usa ela; senão usa a data do lançamento
    $campoDataSql = "COALESCE(t.due_date, t.date)";
}

// -------------------------------------------------------------
// 1. SALDO ATUAL CONSOLIDADO (Realizado em caixa até hoje)
// -------------------------------------------------------------
// Saldo inicial de todas as contas
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

$saldoAtual = $saldoInicial + $totalReceitasRealizadas - $totalDespesasRealizadas;

// -------------------------------------------------------------
// 2. ENTRADAS E SAÍDAS DO MÊS NO CALENDÁRIO
// -------------------------------------------------------------
// Entradas do mês selecionado
$stmt = $pdo->prepare(
    "SELECT 
        COALESCE(SUM(amount), 0) AS total,
        COALESCE(SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END), 0) AS recebidas,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) AS pendentes
     FROM transactions t
     WHERE t.user_id = ? AND t.type = 'income' AND t.status != 'cancelled'
       AND MONTH($campoDataSql) = ? AND YEAR($campoDataSql) = ?"
);
$stmt->execute([$userId, $mes, $ano]);
$infoEntradas = $stmt->fetch(PDO::FETCH_ASSOC);
$entradasMes = (float) $infoEntradas['total'];
$entradasRecebidas = (float) $infoEntradas['recebidas'];
$entradasPendentes = (float) $infoEntradas['pendentes'];

// Saídas do mês selecionado
$stmt = $pdo->prepare(
    "SELECT 
        COALESCE(SUM(amount), 0) AS total,
        COALESCE(SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END), 0) AS pagas,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) AS pendentes
     FROM transactions t
     WHERE t.user_id = ? AND t.type = 'expense' AND t.status != 'cancelled'
       AND MONTH($campoDataSql) = ? AND YEAR($campoDataSql) = ?"
);
$stmt->execute([$userId, $mes, $ano]);
$infoSaidas = $stmt->fetch(PDO::FETCH_ASSOC);
$saidasMes = (float) $infoSaidas['total'];
$saidasPagas = (float) $infoSaidas['pagas'];
$saidasPendentes = (float) $infoSaidas['pendentes'];

// Balanço / Resultado líquido do mês
$resultadoMes = $entradasMes - $saidasMes;

// Previsão do Final do Mês: Saldo atual + o que falta receber no mês - o que falta pagar no mês
$saldoFinalPrevisto = $saldoAtual + $entradasPendentes - $saidasPendentes;

// -------------------------------------------------------------
// 3. BUSCA DAS CONTAS / LANÇAMENTOS DO MÊS
// -------------------------------------------------------------
$stmt = $pdo->prepare(
    "SELECT t.id, t.description, t.amount, t.type, t.date, t.due_date, t.status, t.urgency,
            $campoDataSql AS data_ref,
            c.name AS categoria, a.name AS conta
     FROM transactions t
     LEFT JOIN categories c ON c.id = t.category_id
     LEFT JOIN accounts a ON a.id = t.account_id
     WHERE t.user_id = ?
       AND MONTH($campoDataSql) = ?
       AND YEAR($campoDataSql) = ?
     ORDER BY data_ref ASC, t.type DESC, t.id ASC"
);
$stmt->execute([$userId, $mes, $ano]);
$transacoesMes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupamento por dia
$transacoesPorDia = [];
$totaisPorDia = [];

foreach ($transacoesMes as $t) {
    $diaNum = (int)date('j', strtotime($t['data_ref']));
    $transacoesPorDia[$diaNum][] = $t;

    if (!isset($totaisPorDia[$diaNum])) {
        $totaisPorDia[$diaNum] = [
            'entradas' => 0.0,
            'saidas' => 0.0,
            'pendentes' => 0,
            'total_itens' => 0
        ];
    }
    $totaisPorDia[$diaNum]['total_itens']++;
    if ($t['status'] !== 'cancelled') {
        if ($t['type'] === 'income') {
            $totaisPorDia[$diaNum]['entradas'] += (float)$t['amount'];
        } else {
            $totaisPorDia[$diaNum]['saidas'] += (float)$t['amount'];
        }
    }
    if ($t['status'] === 'pending') {
        $totaisPorDia[$diaNum]['pendentes']++;
    }
}

// Configuração dos dias da grade
$totalDiasMes = (int)date('t', mktime(0, 0, 0, $mes, 1, $ano));
$primeiroDiaSemana = (int)date('w', mktime(0, 0, 0, $mes, 1, $ano)); // 0 = Domingo, 6 = Sábado
$totalDiasMesAnt = (int)date('t', mktime(0, 0, 0, $mesAnt, 1, $anoAnt));
$diasMesAntInicio = $totalDiasMesAnt - $primeiroDiaSemana + 1;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wert - Calendário de Contas</title>
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
        <a class="sair" href="index.php" title="Voltar ao Painel Geral">
            <svg style="width:16px;height:16px;fill:currentColor;flex-shrink:0;" viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
            Painel
        </a>
        <a class="btn-header-primary" href="adicionar_gasto.php?origem=calendario">+ Novo lançamento</a>
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

    <!-- 4 CARDS PRINCIPAIS CONFORME SOLICITADO -->
    <section class="cartoes">
        <div class="cartao">
            <span class="rotulo">Saldo Atual (Caixa)</span>
            <span class="valor <?= $saldoAtual >= 0 ? 'positivo' : 'negativo' ?>">
                R$ <?= number_format($saldoAtual, 2, ',', '.') ?>
            </span>
            <span style="font-size:12px; color:var(--color-text-secondary); margin-top:2px;">
                Saldo consolidado realizado
            </span>
        </div>
        
        <div class="cartao">
            <span class="rotulo">Entradas do mês</span>
            <span class="valor positivo">+ R$ <?= number_format($entradasMes, 2, ',', '.') ?></span>
            <div style="font-size:12px; color:var(--color-text-secondary); display:flex; gap:10px; margin-top:2px;">
                <span title="Já recebido" style="color:var(--color-emerald);">✓ R$ <?= number_format($entradasRecebidas, 2, ',', '.') ?></span>
                <span title="A receber">⏳ R$ <?= number_format($entradasPendentes, 2, ',', '.') ?></span>
            </div>
        </div>

        <div class="cartao">
            <span class="rotulo">Saídas do mês</span>
            <span class="valor negativo">- R$ <?= number_format($saidasMes, 2, ',', '.') ?></span>
            <div style="font-size:12px; color:var(--color-text-secondary); display:flex; gap:10px; margin-top:2px;">
                <span title="Já pago" style="color:var(--color-danger);">✓ R$ <?= number_format($saidasPagas, 2, ',', '.') ?></span>
                <span title="A pagar / Pendente" style="color:var(--color-warning);">⏳ R$ <?= number_format($saidasPendentes, 2, ',', '.') ?></span>
            </div>
        </div>

        <div class="cartao">
            <span class="rotulo">Final do mês</span>
            <span class="valor <?= $saldoFinalPrevisto >= 0 ? 'positivo' : 'negativo' ?>">
                R$ <?= number_format($saldoFinalPrevisto, 2, ',', '.') ?>
            </span>
            <div style="font-size:12px; color:var(--color-text-secondary); display:flex; justify-content:space-between; margin-top:2px;">
                <span>Previsão de saldo</span>
                <span style="color: <?= $resultadoMes >= 0 ? 'var(--color-emerald)' : 'var(--color-danger)' ?>;" title="Balanço líquido do mês">
                    Balanço: <?= $resultadoMes >= 0 ? '+' : '' ?>R$ <?= number_format($resultadoMes, 2, ',', '.') ?>
                </span>
            </div>
        </div>
    </section>

    <!-- BARRA DE CONTROLE DO CALENDÁRIO -->
    <section class="calendario-painel-topo">
        <div class="calendario-navegacao-mes">
            <a href="calendario.php?mes=<?= $mesAnt ?>&ano=<?= $anoAnt ?>&criterio=<?= $criterioData ?>" class="btn-mes-nav" title="Mês anterior">
                &#9664; <?= $nomesMeses[$mesAnt] ?>
            </a>

            <div class="calendario-titulo-mes">
                <h2><?= $nomeMesAtual ?> <span><?= $ano ?></span></h2>
                <?php if ($mes !== $mesAtual || $ano !== $anoAtual): ?>
                    <a href="calendario.php?mes=<?= $mesAtual ?>&ano=<?= $anoAtual ?>&criterio=<?= $criterioData ?>" class="btn-mes-hoje">Ir para Mês Atual</a>
                <?php endif; ?>
            </div>

            <a href="calendario.php?mes=<?= $mesProx ?>&ano=<?= $anoProx ?>&criterio=<?= $criterioData ?>" class="btn-mes-nav" title="Próximo mês">
                <?= $nomesMeses[$mesProx] ?> &#9654;
            </a>
        </div>

        <div class="calendario-opcoes-rapidas">
            <!-- Seletor Rápido de Mês e Ano -->
            <form method="GET" action="calendario.php" class="form-seletor-data">
                <input type="hidden" name="criterio" value="<?= htmlspecialchars($criterioData) ?>">
                <select name="mes" onchange="this.form.submit()" class="select-mes-ano">
                    <?php foreach ($nomesMeses as $numM => $nomM): ?>
                        <option value="<?= $numM ?>" <?= $numM === $mes ? 'selected' : '' ?>><?= $nomM ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="ano" onchange="this.form.submit()" class="select-mes-ano">
                    <?php for ($y = $anoAtual - 2; $y <= $anoAtual + 3; $y++): ?>
                        <option value="<?= $y ?>" <?= $y === $ano ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </form>

            <!-- Critério de Data (Vencimento vs Registro) -->
            <div class="filtro-criterio-data">
                <span class="label-criterio">Data por:</span>
                <a href="calendario.php?mes=<?= $mes ?>&ano=<?= $ano ?>&criterio=vencimento" 
                   class="btn-criterio <?= $criterioData === 'vencimento' ? 'ativo' : '' ?>" 
                   title="Organizar pelo dia em que a conta deve ser paga / recebida">Vencimento</a>
                <a href="calendario.php?mes=<?= $mes ?>&ano=<?= $ano ?>&criterio=registro" 
                   class="btn-criterio <?= $criterioData === 'registro' ? 'ativo' : '' ?>" 
                   title="Organizar pela data em que o lançamento foi registrado">Registro</a>
            </div>

            <!-- Filtro visual de visualização rápida no navegador -->
            <div class="filtros-status-calendario">
                <button type="button" class="btn-filtro ativo" data-filtro="todos">Todos</button>
                <button type="button" class="btn-filtro" data-filtro="pending">⏳ A Pagar/Receber</button>
                <button type="button" class="btn-filtro" data-filtro="completed">✓ Pagos</button>
                <button type="button" class="btn-filtro" data-filtro="expense">Saídas</button>
                <button type="button" class="btn-filtro" data-filtro="income">Entradas</button>
            </div>
        </div>
    </section>

    <!-- GRADE DO CALENDÁRIO -->
    <div class="calendario-container-grade">
        
        <!-- Cabeçalho dos Dias da Semana -->
        <div class="calendario-dias-semana">
            <div>Domingo</div>
            <div>Segunda</div>
            <div>Terça</div>
            <div>Quarta</div>
            <div>Quinta</div>
            <div>Sexta</div>
            <div>Sábado</div>
        </div>

        <div class="calendario-corpo-grade">
            <?php
            // Dias do mês anterior para preenchimento da primeira semana
            for ($i = 0; $i < $primeiroDiaSemana; $i++):
                $diaAntNum = $diasMesAntInicio + $i;
            ?>
                <div class="calendario-dia outro-mes">
                    <div class="calendario-dia-cabecalho">
                        <span class="num-dia"><?= $diaAntNum ?></span>
                    </div>
                </div>
            <?php endfor; ?>

            <?php
            // Dias do mês atual
            for ($dia = 1; $dia <= $totalDiasMes; $dia++):
                $dataIso = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
                $ehHoje = ($dataIso === $hojeStr);
                $itensDia = $transacoesPorDia[$dia] ?? [];
                $totaisDia = $totaisPorDia[$dia] ?? ['entradas' => 0, 'saidas' => 0, 'pendentes' => 0, 'total_itens' => 0];
                $temPendentes = $totaisDia['pendentes'] > 0;
            ?>
                <div class="calendario-dia <?= $ehHoje ? 'hoje' : '' ?> <?= $temPendentes ? 'tem-pendencia' : '' ?>"
                     data-data="<?= $dataIso ?>"
                     data-dia="<?= $dia ?>"
                     data-mes="<?= $mes ?>"
                     data-ano="<?= $ano ?>"
                     onclick="abrirDetalhesDia(<?= $dia ?>)">
                    
                    <div class="calendario-dia-cabecalho" onclick="event.stopPropagation();">
                        <div class="dia-info-wrapper">
                            <span class="num-dia"><?= $dia ?></span>
                            <?php if ($ehHoje): ?>
                                <span class="badge-hoje">Hoje</span>
                            <?php endif; ?>
                            <?php if ($temPendentes): ?>
                                <span class="badge-pendente-mini" title="<?= $totaisDia['pendentes'] ?> pendência(s) neste dia">⏳ <?= $totaisDia['pendentes'] ?></span>
                            <?php endif; ?>
                        </div>
                        <a href="adicionar_gasto.php?date=<?= $dataIso ?>&origem=calendario" class="btn-add-dia" title="Novo lançamento para <?= date('d/m/Y', strtotime($dataIso)) ?>">+</a>
                    </div>

                    <div class="calendario-lista-itens">
                        <?php 
                        $maxExibicao = 3;
                        $contagem = 0;
                        foreach ($itensDia as $item):
                            $contagem++;
                            if ($contagem > $maxExibicao) break;
                            $ehEntrada = $item['type'] === 'income';
                            $ehPendente = $item['status'] === 'pending';
                        ?>
                            <div class="item-calendario <?= $item['type'] ?> status-<?= $item['status'] ?> <?= $item['urgency'] === 'alta' ? 'urgente' : '' ?>"
                                 data-status="<?= $item['status'] ?>"
                                 data-type="<?= $item['type'] ?>"
                                 title="<?= htmlspecialchars($item['description'] ?: $item['categoria'] ?: 'Lançamento') ?> (R$ <?= number_format($item['amount'], 2, ',', '.') ?>)">
                                <span class="indicador-status"><?= $item['status'] === 'completed' ? '✓' : ($item['status'] === 'pending' ? '⏳' : '✕') ?></span>
                                <span class="item-desc"><?= htmlspecialchars($item['description'] ?: $item['categoria'] ?: 'Sem descrição') ?></span>
                                <span class="item-valor"><?= $ehEntrada ? '+' : '-' ?><?= number_format($item['amount'], 2, ',', '.') ?></span>
                            </div>
                        <?php endforeach; ?>

                        <?php if (count($itensDia) > $maxExibicao): ?>
                            <div class="item-mais-contas">
                                +<?= count($itensDia) - $maxExibicao ?> mais...
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($totaisDia['total_itens'] > 0): ?>
                        <div class="calendario-dia-totais">
                            <?php if ($totaisDia['saidas'] > 0): ?>
                                <span class="total-saida" title="Total a pagar / pago no dia">-R$ <?= number_format($totaisDia['saidas'], 2, ',', '.') ?></span>
                            <?php endif; ?>
                            <?php if ($totaisDia['entradas'] > 0): ?>
                                <span class="total-entrada" title="Total a receber / recebido no dia">+R$ <?= number_format($totaisDia['entradas'], 2, ',', '.') ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>

            <?php
            // Preenche o restante dos dias da última semana
            $totalDiasExibidos = $primeiroDiaSemana + $totalDiasMes;
            $diasFaltantes = (7 - ($totalDiasExibidos % 7)) % 7;
            for ($j = 1; $j <= $diasFaltantes; $j++):
            ?>
                <div class="calendario-dia outro-mes">
                    <div class="calendario-dia-cabecalho">
                        <span class="num-dia"><?= $j ?></span>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>

</main>

<!-- MODAL DE DETALHES DO DIA -->
<div id="modalDetalhesDia" class="modal-dia-backdrop" onclick="fecharModalDetalhes(event)">
    <div class="modal-dia-conteudo" onclick="event.stopPropagation();">
        <div class="modal-dia-header">
            <div>
                <h3 id="modalTituloDia">Lançamentos do Dia</h3>
                <span id="modalSubtituloDia" class="modal-subtitulo"></span>
            </div>
            <button type="button" class="btn-fechar-modal" onclick="fecharModal()">&times;</button>
        </div>

        <div id="modalResumoDia" class="modal-resumo-cards"></div>

        <div id="modalListaContas" class="modal-lista-contas">
            <!-- Inserido dinamicamente via JavaScript -->
        </div>

        <div class="modal-dia-footer">
            <a id="btnModalNovoLancamento" href="adicionar_gasto.php?origem=calendario" class="btn-header-primary">+ Adicionar Conta Neste Dia</a>
            <button type="button" class="sair" onclick="fecharModal()">Fechar</button>
        </div>
    </div>
</div>

<script>
// Dados completos dos lançamentos do mês em JSON para o modal interativo
const transacoesPorDia = <?= json_encode($transacoesPorDia, JSON_UNESCAPED_UNICODE) ?>;
const totaisPorDia = <?= json_encode($totaisPorDia, JSON_UNESCAPED_UNICODE) ?>;
const mesAtualNum = <?= $mes ?>;
const anoAtualNum = <?= $ano ?>;
const criterioAtual = <?= json_encode($criterioData) ?>;

function abrirDetalhesDia(dia) {
    const contas = transacoesPorDia[dia] || [];
    const totais = totaisPorDia[dia] || { entradas: 0, saidas: 0, pendentes: 0, total_itens: 0 };
    const diaFormatado = String(dia).padStart(2, '0');
    const mesFormatado = String(mesAtualNum).padStart(2, '0');
    const dataCompletaFormatada = `${diaFormatado}/${mesFormatado}/${anoAtualNum}`;
    const dataIso = `${anoAtualNum}-${mesFormatado}-${diaFormatado}`;

    document.getElementById('modalTituloDia').textContent = `Dia ${diaFormatado} de ${document.querySelector('.calendario-titulo-mes h2').childNodes[0].textContent.trim()} de ${anoAtualNum}`;
    document.getElementById('modalSubtituloDia').textContent = `${contas.length} lançamento(s) registrado(s)`;

    // Atualiza link de adicionar gasto para esta data
    document.getElementById('btnModalNovoLancamento').href = `adicionar_gasto.php?date=${dataIso}&origem=calendario`;

    // Resumo do dia
    const saldoDia = totais.entradas - totais.saidas;
    document.getElementById('modalResumoDia').innerHTML = `
        <div class="resumo-mini-item">
            <span>Entradas</span>
            <strong style="color:var(--color-emerald);">+ R$ ${totais.entradas.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</strong>
        </div>
        <div class="resumo-mini-item">
            <span>Saídas</span>
            <strong style="color:var(--color-danger);">- R$ ${totais.saidas.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</strong>
        </div>
        <div class="resumo-mini-item">
            <span>Balanço do dia</span>
            <strong style="color:${saldoDia >= 0 ? 'var(--color-emerald)' : 'var(--color-danger)'};">${saldoDia >= 0 ? '+' : ''} R$ ${saldoDia.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</strong>
        </div>
    `;

    // Lista de contas
    const containerLista = document.getElementById('modalListaContas');
    if (contas.length === 0) {
        containerLista.innerHTML = `
            <div class="modal-vazio">
                <p>Nenhuma conta ou lançamento para este dia.</p>
                <p style="font-size:12px; margin-top:6px; color:var(--color-text-secondary);">Clique no botão abaixo para adicionar uma receita ou despesa.</p>
            </div>
        `;
    } else {
        let html = '';
        contas.forEach(item => {
            const ehEntrada = item.type === 'income';
            const statusTexto = item.status === 'completed' ? 'Pago / Concluído' : (item.status === 'pending' ? 'Pendente' : 'Cancelado');
            const statusClass = item.status === 'completed' ? 'badge-pago' : (item.status === 'pending' ? 'badge-pendente' : 'badge-cancelado');
            const proximoStatus = item.status === 'completed' ? 'pending' : 'completed';
            const botaoToggleTexto = item.status === 'completed' ? '⏳ Marcar como Pendente' : '✓ Marcar como Pago';

            html += `
                <div class="modal-item-conta ${item.type}">
                    <div class="modal-item-info">
                        <div class="modal-item-titulo">
                            <strong>${escapeHtml(item.description || item.categoria || 'Sem descrição')}</strong>
                            <span class="badge-status ${statusClass}">${statusTexto}</span>
                            ${item.urgency === 'alta' ? '<span class="badge-urgencia-alta">Urgência Alta</span>' : ''}
                        </div>
                        <div class="modal-item-subinfo">
                            <span>Categoria: <strong>${escapeHtml(item.categoria || 'Sem categoria')}</strong></span>
                            ${item.conta ? `<span>Conta: <strong>${escapeHtml(item.conta)}</strong></span>` : ''}
                            ${item.due_date ? `<span>Vencimento: <strong>${formatarDataBr(item.due_date)}</strong></span>` : ''}
                        </div>
                    </div>
                    <div class="modal-item-acoes">
                        <div class="modal-item-valor ${ehEntrada ? 'positivo' : 'negativo'}">
                            ${ehEntrada ? '+' : '-'} R$ ${parseFloat(item.amount).toLocaleString('pt-BR', {minimumFractionDigits: 2})}
                        </div>
                        <div class="modal-botoes-linha">
                            <form method="POST" action="calendario.php" style="display:inline;">
                                <input type="hidden" name="acao" value="toggle_status">
                                <input type="hidden" name="id" value="${item.id}">
                                <input type="hidden" name="novo_status" value="${proximoStatus}">
                                <input type="hidden" name="mes" value="${mesAtualNum}">
                                <input type="hidden" name="ano" value="${anoAtualNum}">
                                <input type="hidden" name="criterio" value="${escapeHtml(criterioAtual)}">
                                <button type="submit" class="btn-acao-lancamento" title="Alterar status de pagamento">${botaoToggleTexto}</button>
                            </form>
                            <a href="editar_gasto.php?id=${item.id}&origem=calendario" class="btn-acao-lancamento" title="Editar este lançamento">Editar</a>
                            <a href="excluir_gasto.php?id=${item.id}&origem=calendario&mes=${mesAtualNum}&ano=${anoAtualNum}" 
                               class="btn-acao-lancamento btn-acao-excluir" 
                               onclick="return confirm('Deseja realmente excluir este lançamento?');" 
                               title="Excluir lançamento">Excluir</a>
                        </div>
                    </div>
                </div>
            `;
        });
        containerLista.innerHTML = html;
    }

    document.getElementById('modalDetalhesDia').classList.add('aberto');
}

function fecharModal() {
    document.getElementById('modalDetalhesDia').classList.remove('aberto');
}

function fecharModalDetalhes(e) {
    if (e.target.id === 'modalDetalhesDia') {
        fecharModal();
    }
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') fecharModal();
});

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatarDataBr(dataYmd) {
    if (!dataYmd) return '';
    const partes = dataYmd.split('-');
    if (partes.length === 3) return `${partes[2]}/${partes[1]}/${partes[0]}`;
    return dataYmd;
}

// Filtros rápidos no calendário
const botoesFiltro = document.querySelectorAll('.filtros-status-calendario .btn-filtro');
botoesFiltro.forEach(btn => {
    btn.addEventListener('click', () => {
        botoesFiltro.forEach(b => b.classList.remove('ativo'));
        btn.classList.add('ativo');
        const filtro = btn.getAttribute('data-filtro');

        const itens = document.querySelectorAll('.item-calendario');
        itens.forEach(item => {
            const status = item.getAttribute('data-status');
            const type = item.getAttribute('data-type');

            if (filtro === 'todos') {
                item.style.display = '';
            } else if (filtro === 'pending' || filtro === 'completed') {
                item.style.display = (status === filtro) ? '' : 'none';
            } else if (filtro === 'expense' || filtro === 'income') {
                item.style.display = (type === filtro) ? '' : 'none';
            }
        });
    });
});
</script>

</body>
</html>
