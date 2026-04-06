<?php
/**
 * Ledger Entry Model
 */
class LedgerModel {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create(int $departmentId, int $transactionId, float $debit, float $credit, string $description = ''): int {
        // Get current balance
        $current = $this->db->fetch(
            "SELECT balance FROM ledger_entries WHERE department_id = ? ORDER BY ledger_id DESC LIMIT 1",
            [$departmentId]
        );
        $currentBalance = $current ? (float)$current['balance'] : 0;
        $newBalance = $currentBalance + $debit - $credit;

        return $this->db->insert('ledger_entries', [
            'department_id' => $departmentId,
            'transaction_id' => $transactionId,
            'debit' => $debit,
            'credit' => $credit,
            'balance' => $newBalance,
            'description' => $description
        ]);
    }

    public function getDepartmentBalance(int $departmentId): float {
        $result = $this->db->fetch(
            "SELECT balance FROM ledger_entries WHERE department_id = ? ORDER BY ledger_id DESC LIMIT 1",
            [$departmentId]
        );
        return $result ? (float)$result['balance'] : 0;
    }

    public function getAllDepartmentBalances(): array {
        return $this->db->fetchAll(
            "SELECT d.department_id, d.department_name, d.department_type,
                    COALESCE(l.balance, 0) as balance
             FROM departments d
             LEFT JOIN (
                 SELECT department_id, balance
                 FROM ledger_entries le1
                 WHERE ledger_id = (
                     SELECT MAX(ledger_id) FROM ledger_entries le2 WHERE le2.department_id = le1.department_id
                 )
             ) l ON d.department_id = l.department_id
             WHERE d.is_active = 1
             ORDER BY d.department_type, d.department_name"
        );
    }

    public function getDepartmentLedger(int $departmentId, ?string $dateFrom = null, ?string $dateTo = null): array {
        $where = ['le.department_id = ?'];
        $params = [$departmentId];

        if ($dateFrom) {
            $where[] = 'ft.transaction_date >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $where[] = 'ft.transaction_date <= ?';
            $params[] = $dateTo;
        }

        return $this->db->fetchAll(
            "SELECT le.*, ft.transaction_date, ft.transaction_type, ft.description as txn_description
             FROM ledger_entries le
             JOIN financial_transactions ft ON le.transaction_id = ft.transaction_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY le.ledger_id",
            $params
        );
    }

    public function getKasUtamaBalance(): float {
        $result = $this->db->fetch(
            "SELECT COALESCE(SUM(CASE WHEN coa_debit = '1101' THEN amount ELSE 0 END), 0) 
                  - COALESCE(SUM(CASE WHEN coa_credit = '1101' THEN amount ELSE 0 END), 0) as balance
             FROM financial_transactions WHERE is_reversed = 0"
        );
        return (float)($result['balance'] ?? 0);
    }

    public function getDepartmentIncomeBalance(int $departmentId): float {
        $result = $this->db->fetch(
            "SELECT COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance
             FROM ledger_entries WHERE department_id = ?",
            [$departmentId]
        );
        return (float)($result['balance'] ?? 0);
    }
}
