<?php
session_start();

if (empty($_SESSION['user_id'])) {
    header("Location: paginalogar.php");
    exit;
}

require_once "config/database.php";

$userId = $_SESSION['user_id'];
$id = $_GET['id'] ?? $_POST['id'] ?? 0;

if (ctype_digit((string)$id) && (int)$id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
        $stmt->execute([(int)$id, $userId]);
    } catch (PDOException $e) {
        error_log("Erro ao excluir lançamento: " . $e->getMessage());
    }
}

$origem = $_GET['origem'] ?? $_POST['origem'] ?? '';
if ($origem === 'calendario') {
    $redirect = "calendario.php?sucesso=" . urlencode("Lançamento excluído com sucesso!");
    if (!empty($_GET['mes']) && !empty($_GET['ano'])) {
        $redirect .= "&mes=" . (int)$_GET['mes'] . "&ano=" . (int)$_GET['ano'];
    }
    header("Location: " . $redirect);
} else {
    header("Location: index.php?sucesso=" . urlencode("Lançamento excluído com sucesso!"));
}
exit;
