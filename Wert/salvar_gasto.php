<?php
require_once "config/database.php";
require_once "src/Auth.php";
require_once "src/FinanceRepository.php";

$userId = Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

if (!Auth::validateCsrf($_POST['csrf_token'] ?? null)) {
    die("Solicitação inválida (CSRF). Atualize a página e tente novamente.");
}

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

if (!in_array($type, ['income', 'expense'], true)
    || !is_numeric($amount) || $amount <= 0
    || !ctype_digit((string)$accountId)
    || !ctype_digit((string)$categoryId)
    || empty($date)
    || !in_array($status, ['pending', 'completed', 'cancelled'], true)
    || !in_array($urgency, ['baixa', 'media', 'alta'], true)
) {
    die("Dados inválidos.");
}

$repo = new FinanceRepository($pdo);

try {
    $salvo = $repo->createTransaction($userId, [
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

    if (!$salvo) {
        die("Conta ou categoria inválida para este usuário.");
    }
} catch (PDOException $e) {
    error_log("Erro ao salvar lançamento: " . $e->getMessage());
    die("Não foi possível salvar o lançamento.");
}

$origem = $_POST['origem'] ?? '';
if ($origem === 'calendario') {
    $refDate = $dueDate ?: $date;
    $m = date('n', strtotime($refDate));
    $y = date('Y', strtotime($refDate));
    header("Location: calendario.php?mes=$m&ano=$y&sucesso=" . urlencode("Lançamento salvo com sucesso!"));
} else {
    header("Location: index.php?sucesso=" . urlencode("Lançamento salvo com sucesso!"));
}
exit;
