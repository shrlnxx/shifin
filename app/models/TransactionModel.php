<?php
/**
 * Financial Transaction Model (Immutable records)
 */
class TransactionModel {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create(array $data): int {
        return $this->db->insert('financial_transactions', $data);
    }

    public function findById(int $id): ?array {
        return $this->db->fetch(
            "SELECT ft.*, hm.month_name as hijri_month_name,
                    cd.coa_name as debit_account_name, cc.coa_name as credit_account_name,
                    u.full_name as created_by_name
             FROM financial_transactions ft
             JOIN hijri_months hm ON ft.hijri_month = hm.month_id
             JOIN coa cd ON ft.coa_debit = cd.coa_id
             JOIN coa cc ON ft.coa_credit = cc.coa_id
             JOIN users u ON ft.created_by = u.user_id
             WHERE ft.transaction_id = ?",
            [$id]
        );
    }

    public function markReversed(int $id): void {
        $this->db->query(
            "UPDATE financial_transactions SET is_reversed = 1 WHERE transaction_id = ?",
            [$id]
        );
    }

    public function getTransactions(array $filters = [], int $page = 1, int $limit = 50): array {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['transaction_type'])) {
            $where[] = 'ft.transaction_type = ?';
            $params[] = $filters['transaction_type'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'ft.transaction_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'ft.transaction_date <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['coa_id'])) {
            $where[] = '(ft.coa_debit = ? OR ft.coa_credit = ?)';
            $params[] = $filters['coa_id'];
            $params[] = $filters['coa_id'];
        }
        if (isset($filters['is_reversed'])) {
            $where[] = 'ft.is_reversed = ?';
            $params[] = $filters['is_reversed'];
        }

        $whereClause = implode(' AND ', $where);
        $total = $this->db->count(
            "SELECT COUNT(*) FROM financial_transactions ft WHERE {$whereClause}", $params
        );

        $offset = ($page - 1) * $limit;
        $rows = $this->db->fetchAll(
            "SELECT ft.*, hm.month_name as hijri_month_name,
                    cd.coa_name as debit_account_name, cc.coa_name as credit_account_name,
                    u.full_name as created_by_name
             FROM financial_transactions ft
             JOIN hijri_months hm ON ft.hijri_month = hm.month_id
             JOIN coa cd ON ft.coa_debit = cd.coa_id
             JOIN coa cc ON ft.coa_credit = cc.coa_id
             JOIN users u ON ft.created_by = u.user_id
             WHERE {$whereClause}
             ORDER BY ft.created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );

        return ['data' => $rows, 'total' => $total];
    }

    public function getGeneralJournal(string $dateFrom, string $dateTo): array {
        return $this->db->fetchAll(
            "SELECT ft.*, hm.month_name as hijri_month_name,
                    cd.coa_name as debit_account_name, cc.coa_name as credit_account_name,
                    u.full_name as created_by_name
             FROM financial_transactions ft
             JOIN hijri_months hm ON ft.hijri_month = hm.month_id
             JOIN coa cd ON ft.coa_debit = cd.coa_id
             JOIN coa cc ON ft.coa_credit = cc.coa_id
             JOIN users u ON ft.created_by = u.user_id
             WHERE ft.transaction_date BETWEEN ? AND ? AND ft.is_reversed = 0
             ORDER BY ft.transaction_date, ft.transaction_id",
            [$dateFrom, $dateTo]
        );
    }

    public function getGeneralLedger(string $coaId, string $dateFrom, string $dateTo): array {
        return $this->db->fetchAll(
            "SELECT ft.*, hm.month_name as hijri_month_name,
                    CASE WHEN ft.coa_debit = ? THEN ft.amount ELSE 0 END as debit,
                    CASE WHEN ft.coa_credit = ? THEN ft.amount ELSE 0 END as credit
             FROM financial_transactions ft
             JOIN hijri_months hm ON ft.hijri_month = hm.month_id
             WHERE (ft.coa_debit = ? OR ft.coa_credit = ?)
               AND ft.transaction_date BETWEEN ? AND ?
               AND ft.is_reversed = 0
             ORDER BY ft.transaction_date, ft.transaction_id",
            [$coaId, $coaId, $coaId, $coaId, $dateFrom, $dateTo]
        );
    }

    public function getIncomeByPeriod(string $dateFrom, string $dateTo, ?int $departmentId = null): array {
        if ($departmentId) {
            return $this->db->fetchAll(
                "SELECT ft.transaction_id, ft.transaction_date, ft.amount, ft.description,
                        cc.coa_id, cc.coa_name, d.department_name
                 FROM financial_transactions ft
                 JOIN coa cc ON ft.coa_credit = cc.coa_id
                 LEFT JOIN departments d ON ft.department_target = d.department_id
                 WHERE ft.transaction_type = 'INCOME'
                   AND ft.transaction_date BETWEEN ? AND ?
                   AND ft.is_reversed = 0
                   AND (ft.department_target = ? OR EXISTS (
                       SELECT 1 FROM department_coa_mapping dcm 
                       WHERE dcm.department_id = ? AND dcm.coa_id = ft.coa_credit
                   ))
                 ORDER BY ft.transaction_date DESC",
                [$dateFrom, $dateTo, $departmentId, $departmentId]
            );
        }
        return $this->db->fetchAll(
            "SELECT c.coa_id, c.coa_name, COALESCE(SUM(ft.amount), 0) as total
             FROM coa c
             LEFT JOIN financial_transactions ft ON ft.coa_credit = c.coa_id
               AND ft.transaction_type = 'INCOME'
               AND ft.transaction_date BETWEEN ? AND ?
               AND ft.is_reversed = 0
             WHERE c.coa_group = 'REVENUE'
             GROUP BY c.coa_id, c.coa_name
             ORDER BY c.coa_id",
            [$dateFrom, $dateTo]
        );
    }

    public function getExpenseByPeriod(string $dateFrom, string $dateTo, ?int $departmentId = null): array {
        if ($departmentId) {
            return $this->db->fetchAll(
                "SELECT ft.transaction_id, ft.transaction_date, ft.amount, ft.description,
                        cd.coa_id, cd.coa_name, d.department_name
                 FROM financial_transactions ft
                 JOIN coa cd ON ft.coa_debit = cd.coa_id
                 LEFT JOIN departments d ON ft.department_target = d.department_id
                 WHERE ft.transaction_type = 'EXPENSE'
                   AND ft.transaction_date BETWEEN ? AND ?
                   AND ft.is_reversed = 0
                   AND ft.department_target = ?
                 ORDER BY ft.transaction_date DESC",
                [$dateFrom, $dateTo, $departmentId]
            );
        }
        return $this->db->fetchAll(
            "SELECT c.coa_id, c.coa_name, COALESCE(SUM(ft.amount), 0) as total
             FROM coa c
             LEFT JOIN financial_transactions ft ON ft.coa_debit = c.coa_id
               AND ft.transaction_type = 'EXPENSE'
               AND ft.transaction_date BETWEEN ? AND ?
               AND ft.is_reversed = 0
             WHERE c.coa_group = 'EXPENSE'
             GROUP BY c.coa_id, c.coa_name
             ORDER BY c.coa_id",
            [$dateFrom, $dateTo]
        );
    }

    public function getFinancialSummary(string $dateFrom, string $dateTo): array {
        $income = $this->db->fetch(
            "SELECT COALESCE(SUM(amount), 0) as total FROM financial_transactions 
             WHERE transaction_type = 'INCOME' AND is_reversed = 0
             AND transaction_date BETWEEN ? AND ?",
            [$dateFrom, $dateTo]
        );
        $expense = $this->db->fetch(
            "SELECT COALESCE(SUM(amount), 0) as total FROM financial_transactions 
             WHERE transaction_type = 'EXPENSE' AND is_reversed = 0
             AND transaction_date BETWEEN ? AND ?",
            [$dateFrom, $dateTo]
        );
        $kasBalance = $this->db->fetch(
            "SELECT COALESCE(SUM(CASE WHEN coa_debit = '1101' THEN amount ELSE 0 END), 0) 
                  - COALESCE(SUM(CASE WHEN coa_credit = '1101' THEN amount ELSE 0 END), 0) as balance
             FROM financial_transactions WHERE is_reversed = 0"
        );

        return [
            'total_income' => (float)($income['total'] ?? 0),
            'total_expense' => (float)($expense['total'] ?? 0),
            'net_income' => (float)($income['total'] ?? 0) - (float)($expense['total'] ?? 0),
            'kas_balance' => (float)($kasBalance['balance'] ?? 0)
        ];
    }
}
