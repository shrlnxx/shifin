<?php
/**
 * SHIFFIN - Transaction Service
 * Records financial transactions with double-entry bookkeeping
 */
class TransactionService {
    private TransactionModel $txnModel;
    private LedgerModel $ledgerModel;
    private DepartmentModel $deptModel;
    private SettingModel $settingModel;

    public function __construct() {
        $this->txnModel = new TransactionModel();
        $this->ledgerModel = new LedgerModel();
        $this->deptModel = new DepartmentModel();
        $this->settingModel = new SettingModel();
    }

    /**
     * Record an income transaction
     * Debit: Kas Utama (1101), Credit: Revenue COA
     */
    public function recordIncome(
        string $incomeSource,
        string $creditCoa,
        float $amount,
        string $description,
        int $userId,
        ?int $referenceId = null,
        ?string $referenceType = null,
        ?string $transactionDate = null
    ): int {
        $hijriMonth = (int) $this->settingModel->get('current_hijri_month');
        $hijriYear = (int) $this->settingModel->get('current_hijri_year');

        $txnId = $this->txnModel->create([
            'transaction_date' => $transactionDate ?: date('Y-m-d'),
            'hijri_month' => $hijriMonth,
            'hijri_year' => $hijriYear,
            'transaction_type' => 'INCOME',
            'income_source' => $incomeSource,
            'coa_debit' => '1101',     // Kas Utama
            'coa_credit' => $creditCoa,
            'amount' => $amount,
            'description' => $description,
            'reference_id' => $referenceId,
            'reference_type' => $referenceType,
            'created_by' => $userId
        ]);

        return $txnId;
    }

    /**
     * Record an expense transaction
     * Debit: Expense COA, Credit: Kas Utama (1101)
     */
    public function recordExpense(
        string $debitCoa,
        float $amount,
        string $description,
        int $userId,
        ?int $departmentId = null,
        ?string $transactionDate = null
    ): int {
        $hijriMonth = (int) $this->settingModel->get('current_hijri_month');
        $hijriYear = (int) $this->settingModel->get('current_hijri_year');

        $txnId = $this->txnModel->create([
            'transaction_date' => $transactionDate ?: date('Y-m-d'),
            'hijri_month' => $hijriMonth,
            'hijri_year' => $hijriYear,
            'transaction_type' => 'EXPENSE',
            'department_target' => $departmentId,
            'coa_debit' => $debitCoa,
            'coa_credit' => '1101',
            'amount' => $amount,
            'description' => $description,
            'created_by' => $userId
        ]);

        // Update department ledger if applicable
        if ($departmentId) {
            $this->ledgerModel->create($departmentId, $txnId, 0, $amount, $description);
        }

        return $txnId;
    }

    /**
     * Record a distribution transaction
     * Moves funds from income source to department
     */
    public function recordDistribution(
        int $departmentId,
        float $amount,
        string $description,
        int $userId,
        string $debitCoa,
        string $creditCoa = '1101'
    ): int {
        $hijriMonth = (int) $this->settingModel->get('current_hijri_month');
        $hijriYear = (int) $this->settingModel->get('current_hijri_year');

        $txnId = $this->txnModel->create([
            'transaction_date' => date('Y-m-d'),
            'hijri_month' => $hijriMonth,
            'hijri_year' => $hijriYear,
            'transaction_type' => 'DISTRIBUTION',
            'department_target' => $departmentId,
            'coa_debit' => $debitCoa,
            'coa_credit' => $creditCoa,
            'amount' => $amount,
            'description' => $description,
            'created_by' => $userId
        ]);

        // Update department ledger
        $this->ledgerModel->create($departmentId, $txnId, $amount, 0, $description);

        return $txnId;
    }

    /**
     * Record a manual balance transfer from Kas Utama to a department
     * Debit: Department's expense COA, Credit: Kas Utama (1101)
     */
    public function recordBalanceTransfer(
        int $departmentId,
        float $amount,
        string $description,
        int $userId
    ): int {
        $hijriMonth = (int) $this->settingModel->get('current_hijri_month');
        $hijriYear = (int) $this->settingModel->get('current_hijri_year');

        // Get department COA mapping
        $mapping = $this->deptModel->getCOAMapping($departmentId);
        $dept = $this->deptModel->findById($departmentId);
        $debitCoa = $mapping ? $mapping['coa_id'] : '5117'; // Default to Beban Lain-lain

        $txnId = $this->txnModel->create([
            'transaction_date' => date('Y-m-d'),
            'hijri_month' => $hijriMonth,
            'hijri_year' => $hijriYear,
            'transaction_type' => 'DISTRIBUTION',
            'department_target' => $departmentId,
            'coa_debit' => $debitCoa,
            'coa_credit' => '1101', // From Kas Utama
            'amount' => $amount,
            'description' => $description ?: ('Tambah Saldo - ' . ($dept['department_name'] ?? 'Unknown')),
            'created_by' => $userId
        ]);

        // Update department ledger (debit = balance increase)
        $this->ledgerModel->create($departmentId, $txnId, $amount, 0, $description ?: 'Tambah Saldo dari Kas Utama');

        return $txnId;
    }

    /**
     * Reverse a transaction
     */
    public function reverseTransaction(int $originalTxnId, string $reason, int $userId): int {
        $original = $this->txnModel->findById($originalTxnId);
        if (!$original) throw new Exception('Transaction not found');
        if ($original['is_reversed']) throw new Exception('Transaction already reversed');

        $hijriMonth = (int) $this->settingModel->get('current_hijri_month');
        $hijriYear = (int) $this->settingModel->get('current_hijri_year');

        // Create reversal (swap debit/credit)
        $reversalId = $this->txnModel->create([
            'transaction_date' => date('Y-m-d'),
            'hijri_month' => $hijriMonth,
            'hijri_year' => $hijriYear,
            'transaction_type' => 'REVERSAL',
            'income_source' => $original['income_source'],
            'department_source' => $original['department_source'],
            'department_target' => $original['department_target'],
            'coa_debit' => $original['coa_credit'],   // Swap
            'coa_credit' => $original['coa_debit'],   // Swap
            'amount' => $original['amount'],
            'description' => 'REVERSAL: ' . $reason . ' (ref: TXN-' . $originalTxnId . ')',
            'reference_id' => $originalTxnId,
            'reference_type' => 'REVERSAL',
            'created_by' => $userId,
            'reversal_of' => $originalTxnId
        ]);

        // Mark original as reversed
        $this->txnModel->markReversed($originalTxnId);

        // Reverse ledger entries if department was involved
        if ($original['department_target']) {
            $this->ledgerModel->create(
                $original['department_target'],
                $reversalId,
                0, $original['amount'],
                'REVERSAL: ' . $reason
            );
        }

        return $reversalId;
    }
}
