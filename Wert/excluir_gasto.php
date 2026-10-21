<?php
require_once "config/database.php";
require_once "src/Auth.php";
require_once "src/FinanceRepository.php";

$userId = Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Auth::validateCsrf($_POST['csrf_token'] ?? null)) {
    header("Location: index.php");
    exit;
}

$id = $_POST['id'] ?? 0;

if (ctype_digit((string)$id) && (int)$id > 0) {
    try {
        $repo = new FinanceRepository($pdo);
        $repo->deleteTransaction((int)$id, $userId);
    } catch (PDOException $e) {
        error_log("Erro ao excluir lançamento: " . $e->getMessage());
    }
}

$origem = $_POST['origem'] ?? '';
if ($origem === 'calendario') {
    $redirect = "calendario.php?sucesso=" . urlencode("Lançamento excluído com sucesso!");
    if (!empty($_POST['mes']) && !empty($_POST['ano'])) {
        $redirect .= "&mes=" . (int)$_POST['mes'] . "&ano=" . (int)$_POST['ano'];
    }
    header("Location: " . $redirect);
} else {
    header("Location: index.php?sucesso=" . urlencode("Lançamento excluído com sucesso!"));
}
exit;
