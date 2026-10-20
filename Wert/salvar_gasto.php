<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header("Location: paginalogar.php");
    exit;
}
require_once "config/database.php";
$userId = $_SESSION['user_id'];

$type       = $_POST['type'] ?? '';
$description = trim($_POST['description'] ?? '');
$amount     = $_POST['amount'] ?? '';
$accountId  = $_POST['account_id'] ?? '';
$categoryId = $_POST['category_id'] ?? '';
$date       = $_POST['date'] ?? '';
$dueDate    = $_POST['due_date'] ?? null;
$status     = $_POST['status'] ?? 'pending';
$urgency    = $_POST['urgency'] ?? 'media';

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

if (empty($dueDate)) {
    $dueDate = null;
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO transactions
            (user_id, account_id, category_id, type, amount, description, date, due_date, status, urgency)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $userId, $accountId, $categoryId, $type, $amount,
        $description, $date, $dueDate, $status, $urgency
    ]);
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
