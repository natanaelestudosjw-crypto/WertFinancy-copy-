<?php

final class FinanceRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Calcula o saldo consolidado realizado do usuário:
     * Saldo inicial das contas + Receitas concluídas - Despesas concluídas.
     */
    public function getConsolidatedBalance(int $userId): float
    {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(initial_balance), 0) FROM accounts WHERE user_id = ?");
        $stmt->execute([$userId]);
        $saldoInicial = (float) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = ? AND type = 'income' AND status = 'completed'");
        $stmt->execute([$userId]);
        $receitasRealizadas = (float) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = ? AND type = 'expense' AND status = 'completed'");
        $stmt->execute([$userId]);
        $despesasRealizadas = (float) $stmt->fetchColumn();

        return $saldoInicial + $receitasRealizadas - $despesasRealizadas;
    }

    /**
     * Total do mês por tipo (income ou expense).
     */
    public function getCurrentMonthTotalByType(int $userId, string $type): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM transactions
             WHERE user_id = ? AND type = ?
             AND MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())"
        );
        $stmt->execute([$userId, $type]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Totais agrupados por categoria no mês atual.
     */
    public function getCurrentMonthTotalsByCategory(int $userId, string $type): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(c.name, 'Sem categoria') AS categoria, COALESCE(SUM(t.amount), 0) AS total
             FROM transactions t
             LEFT JOIN categories c ON c.id = t.category_id
             WHERE t.user_id = ? AND t.type = ?
               AND MONTH(t.date) = MONTH(CURDATE()) AND YEAR(t.date) = YEAR(CURDATE())
             GROUP BY t.category_id, c.name
             ORDER BY total DESC"
        );
        $stmt->execute([$userId, $type]);
        return $stmt->fetchAll();
    }

    /**
     * Lista os últimos lançamentos do usuário.
     */
    public function getRecentTransactions(int $userId, int $limit = 8): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT t.id, t.description, t.amount, t.type, t.date, t.due_date, t.status, t.urgency, c.name AS categoria
             FROM transactions t
             LEFT JOIN categories c ON c.id = t.category_id
             WHERE t.user_id = ?
             ORDER BY t.date DESC, t.id DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Busca as metas financeiras do usuário.
     */
    public function getUserGoals(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, name, target_amount, current_amount, deadline
             FROM goals
             WHERE user_id = ?
             ORDER BY deadline ASC, id DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Garante que o usuário possua pelo menos uma conta e categorias padrão, retornando-as.
     */
    public function getOrInitUserAccountsAndCategories(int $userId): array
    {
        $contasStmt = $this->pdo->prepare("SELECT id, name FROM accounts WHERE user_id = ?");
        $contasStmt->execute([$userId]);
        $contas = $contasStmt->fetchAll();

        if (empty($contas)) {
            $ins = $this->pdo->prepare("INSERT INTO accounts (user_id, name, initial_balance) VALUES (?, 'Carteira Principal', 0.00)");
            $ins->execute([$userId]);
            $contas = [['id' => (int) $this->pdo->lastInsertId(), 'name' => 'Carteira Principal']];
        }

        $catStmt = $this->pdo->prepare("SELECT id, name, type FROM categories WHERE user_id = ? ORDER BY name ASC");
        $catStmt->execute([$userId]);
        $categorias = $catStmt->fetchAll();

        if (empty($categorias)) {
            $this->seedDefaultCategories($userId);
            $catStmt->execute([$userId]);
            $categorias = $catStmt->fetchAll();
        }

        return [$contas, $categorias];
    }

    /**
     * Cria conta e categorias iniciais para um novo usuário recém-cadastrado.
     */
    public function initNewUserDefaults(int $userId): void
    {
        $stmtAccount = $this->pdo->prepare("INSERT INTO accounts (user_id, name, initial_balance) VALUES (?, 'Carteira Principal', 0.00)");
        $stmtAccount->execute([$userId]);
        $this->seedDefaultCategories($userId);
    }

    private function seedDefaultCategories(int $userId): void
    {
        $defaults = [
            ['Alimentação', 'expense'],
            ['Moradia', 'expense'],
            ['Transporte', 'expense'],
            ['Lazer', 'expense'],
            ['Saúde', 'expense'],
            ['Educação', 'expense'],
            ['Outros', 'expense'],
            ['Salário', 'income'],
            ['Investimentos', 'income'],
            ['Outros', 'income'],
        ];

        $stmtCat = $this->pdo->prepare("INSERT INTO categories (user_id, name, type) VALUES (?, ?, ?)");
        foreach ($defaults as [$cName, $cType]) {
            $stmtCat->execute([$userId, $cName, $cType]);
        }
    }

    /**
     * Verifica se a conta pertence ao usuário logado (Prevenção de IDOR).
     */
    public function userOwnsAccount(int $userId, int $accountId): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM accounts WHERE id = ? AND user_id = ?");
        $stmt->execute([$accountId, $userId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Verifica se a categoria pertence ao usuário logado e corresponde ao tipo (Prevenção de IDOR).
     */
    public function userOwnsCategory(int $userId, int $categoryId, ?string $expectedType = null): bool
    {
        if ($expectedType !== null) {
            $stmt = $this->pdo->prepare("SELECT 1 FROM categories WHERE id = ? AND user_id = ? AND type = ?");
            $stmt->execute([$categoryId, $userId, $expectedType]);
        } else {
            $stmt = $this->pdo->prepare("SELECT 1 FROM categories WHERE id = ? AND user_id = ?");
            $stmt->execute([$categoryId, $userId]);
        }

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Busca um lançamento específico garantindo que pertença ao usuário.
     */
    public function findTransactionByIdAndUser(int $id, int $userId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM transactions WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Cria um novo lançamento validando ownership de conta e categoria.
     */
    public function createTransaction(int $userId, array $data): bool
    {
        $accountId  = (int) $data['account_id'];
        $categoryId = (int) $data['category_id'];
        $type       = $data['type'];

        if (!$this->userOwnsAccount($userId, $accountId) || !$this->userOwnsCategory($userId, $categoryId, $type)) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO transactions
                (user_id, account_id, category_id, type, amount, description, date, due_date, status, urgency)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        return $stmt->execute([
            $userId,
            $accountId,
            $categoryId,
            $type,
            $data['amount'],
            $data['description'],
            $data['date'],
            $data['due_date'],
            $data['status'],
            $data['urgency'],
        ]);
    }

    /**
     * Atualiza um lançamento existente validando ownership de conta, categoria e transação.
     */
    public function updateTransaction(int $id, int $userId, array $data): bool
    {
        $accountId  = (int) $data['account_id'];
        $categoryId = (int) $data['category_id'];
        $type       = $data['type'];

        if (!$this->userOwnsAccount($userId, $accountId) || !$this->userOwnsCategory($userId, $categoryId, $type)) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            "UPDATE transactions
             SET account_id = ?, category_id = ?, type = ?, amount = ?,
                 description = ?, date = ?, due_date = ?, status = ?, urgency = ?
             WHERE id = ? AND user_id = ?"
        );

        return $stmt->execute([
            $accountId,
            $categoryId,
            $type,
            $data['amount'],
            $data['description'],
            $data['date'],
            $data['due_date'],
            $data['status'],
            $data['urgency'],
            $id,
            $userId,
        ]);
    }

    /**
     * Exclui um lançamento garantindo que pertença ao usuário logado.
     */
    public function deleteTransaction(int $id, int $userId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
        return $stmt->execute([$id, $userId]);
    }
}
