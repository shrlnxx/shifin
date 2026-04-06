<?php
/**
 * SHIFFIN - Distribution Engine
 * Handles PSB, Daftar Ulang, and Syahriah fund distribution
 */
class DistributionService {
    private DistributionModel $distModel;
    private TransactionService $txnService;
    private LedgerModel $ledgerModel;
    private DepartmentModel $deptModel;
    private SettingModel $settingModel;
    private Database $db;

    public function __construct() {
        $this->distModel = new DistributionModel();
        $this->txnService = new TransactionService();
        $this->ledgerModel = new LedgerModel();
        $this->deptModel = new DepartmentModel();
        $this->settingModel = new SettingModel();
        $this->db = Database::getInstance();
    }

    /**
     * Distribute PSB payment proportionally across departments
     * Called automatically when a PSB payment is received
     */
    public function distributePSB(float $paymentAmount, int $userId): array {
        return $this->distributeByPercentage('PSB', $paymentAmount, $userId);
    }

    /**
     * Distribute Daftar Ulang payment proportionally
     */
    public function distributeDaftarUlang(float $paymentAmount, int $userId): array {
        return $this->distributeByPercentage('DAFTAR_ULANG', $paymentAmount, $userId);
    }

    /**
     * Distribute Syahriah funds using fixed allocation with priority
     */
    public function distributeSyahriah(int $userId): array {
        $this->db->beginTransaction();

        try {
            $rule = $this->distModel->getRuleByType('SYAHRIAH');
            if (!$rule) throw new Exception('Aturan distribusi Syahriah tidak ditemukan');

            $details = $this->distModel->getRuleDetails($rule['rule_id']);
            if (empty($details)) throw new Exception('Detail aturan distribusi kosong');

            // Get available Syahriah balance from income
            $syahriahDept = $this->deptModel->findByName('Syahriah');
            if (!$syahriahDept) throw new Exception('Departemen Syahriah tidak ditemukan');

            // Calculate available balance: total Syahriah income minus already distributed
            $balance = $this->calculateSyahriahAvailableBalance();

            if ($balance <= 0) {
                throw new Exception('Saldo Syahriah tidak mencukupi untuk distribusi');
            }

            $hijriMonth = (int) $this->settingModel->get('current_hijri_month');
            $hijriYear = (int) $this->settingModel->get('current_hijri_year');

            // Group by priority
            $priorities = [];
            foreach ($details as $detail) {
                $priorities[$detail['priority']][] = $detail;
            }
            ksort($priorities);

            $allocations = [];
            $totalDistributed = 0;

            foreach ($priorities as $priority => $depts) {
                if ($balance <= 0) break;

                // Calculate total needed at this priority
                $totalNeeded = 0;
                foreach ($depts as $dept) {
                    $totalNeeded += $dept['allocation_value'];
                }

                if ($balance >= $totalNeeded) {
                    // Full allocation
                    foreach ($depts as $dept) {
                        $allocAmount = $dept['allocation_value'];
                        $mapping = $this->deptModel->getCOAMapping($dept['department_id']);
                        $debitCoa = $mapping ? $mapping['coa_id'] : '5117';

                        $txnId = $this->txnService->recordDistribution(
                            $dept['department_id'],
                            $allocAmount,
                            "Distribusi Syahriah - {$dept['department_name']} (Priority {$priority})",
                            $userId,
                            $debitCoa,
                            '4101' // Credit Syahriah income
                        );

                        $allocations[] = [
                            'department_id' => $dept['department_id'],
                            'department_name' => $dept['department_name'],
                            'amount' => $allocAmount,
                            'transaction_id' => $txnId,
                            'status' => 'FULL'
                        ];
                        $totalDistributed += $allocAmount;
                    }
                    $balance -= $totalNeeded;
                } else {
                    // Proportional allocation within priority
                    foreach ($depts as $dept) {
                        $proportion = $dept['allocation_value'] / $totalNeeded;
                        $allocAmount = round($balance * $proportion, 2);

                        if ($allocAmount > 0) {
                            $mapping = $this->deptModel->getCOAMapping($dept['department_id']);
                            $debitCoa = $mapping ? $mapping['coa_id'] : '5117';

                            $txnId = $this->txnService->recordDistribution(
                                $dept['department_id'],
                                $allocAmount,
                                "Distribusi Syahriah (Partial) - {$dept['department_name']} (Priority {$priority})",
                                $userId,
                                $debitCoa,
                                '4101'
                            );

                            $allocations[] = [
                                'department_id' => $dept['department_id'],
                                'department_name' => $dept['department_name'],
                                'amount' => $allocAmount,
                                'transaction_id' => $txnId,
                                'status' => 'PARTIAL'
                            ];
                            $totalDistributed += $allocAmount;
                        }
                    }
                    $balance = 0;
                }
            }

            // Log distribution
            $logId = $this->distModel->createLog([
                'distribution_date' => date('Y-m-d'),
                'hijri_month' => $hijriMonth,
                'hijri_year' => $hijriYear,
                'rule_id' => $rule['rule_id'],
                'total_distributed' => $totalDistributed,
                'status' => $balance > 0 ? 'SUCCESS' : 'PARTIAL',
                'created_by' => $userId
            ]);

            foreach ($allocations as $alloc) {
                $this->distModel->createLogDetail([
                    'log_id' => $logId,
                    'department_id' => $alloc['department_id'],
                    'amount' => $alloc['amount'],
                    'transaction_id' => $alloc['transaction_id']
                ]);
            }

            $this->db->commit();

            return [
                'log_id' => $logId,
                'total_distributed' => $totalDistributed,
                'remaining_balance' => $balance,
                'allocations' => $allocations
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Generic percentage-based distribution (PSB, Daftar Ulang)
     */
    private function distributeByPercentage(string $sourceType, float $paymentAmount, int $userId): array {
        $this->db->beginTransaction();

        try {
            $rule = $this->distModel->getRuleByType($sourceType);
            if (!$rule) throw new Exception("Aturan distribusi {$sourceType} tidak ditemukan");

            $details = $this->distModel->getRuleDetails($rule['rule_id']);
            if (empty($details)) throw new Exception('Detail aturan distribusi kosong');

            $hijriMonth = (int) $this->settingModel->get('current_hijri_month');
            $hijriYear = (int) $this->settingModel->get('current_hijri_year');

            $allocations = [];
            $totalDistributed = 0;

            foreach ($details as $detail) {
                $allocAmount = round($paymentAmount * $detail['allocation_value'] / 100, 2);

                if ($allocAmount > 0) {
                    $mapping = $this->deptModel->getCOAMapping($detail['department_id']);
                    $debitCoa = $mapping ? $mapping['coa_id'] : '5117';

                    // For revenue departments, use the income COA
                    $dept = $this->deptModel->findById($detail['department_id']);
                    if ($dept && $dept['department_type'] === 'REVENUE') {
                        $debitCoa = $mapping ? $mapping['coa_id'] : '4101';
                    }

                    $sourceCoa = $sourceType === 'PSB' ? '4103' : '4102';

                    $txnId = $this->txnService->recordDistribution(
                        $detail['department_id'],
                        $allocAmount,
                        "Distribusi {$sourceType} - {$detail['department_name']} ({$detail['allocation_value']}%)",
                        $userId,
                        $debitCoa,
                        $sourceCoa
                    );

                    $allocations[] = [
                        'department_id' => $detail['department_id'],
                        'department_name' => $detail['department_name'],
                        'percentage' => $detail['allocation_value'],
                        'amount' => $allocAmount,
                        'transaction_id' => $txnId
                    ];
                    $totalDistributed += $allocAmount;
                }
            }

            // Log
            $logId = $this->distModel->createLog([
                'distribution_date' => date('Y-m-d'),
                'hijri_month' => $hijriMonth,
                'hijri_year' => $hijriYear,
                'rule_id' => $rule['rule_id'],
                'total_distributed' => $totalDistributed,
                'status' => 'SUCCESS',
                'created_by' => $userId
            ]);

            foreach ($allocations as $alloc) {
                $this->distModel->createLogDetail([
                    'log_id' => $logId,
                    'department_id' => $alloc['department_id'],
                    'amount' => $alloc['amount'],
                    'transaction_id' => $alloc['transaction_id']
                ]);
            }

            $this->db->commit();

            return [
                'log_id' => $logId,
                'total_distributed' => $totalDistributed,
                'allocations' => $allocations
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Calculate available Syahriah balance for distribution
     */
    private function calculateSyahriahAvailableBalance(): float {
        $db = Database::getInstance();
        
        // Total Syahriah income
        $income = $db->fetch(
            "SELECT COALESCE(SUM(amount), 0) as total FROM financial_transactions 
             WHERE transaction_type = 'INCOME' AND income_source = 'SYAHRIAH' AND is_reversed = 0"
        );

        // Already distributed from Syahriah
        $distributed = $db->fetch(
            "SELECT COALESCE(SUM(amount), 0) as total 
             FROM financial_transactions 
             WHERE transaction_type = 'DISTRIBUTION' AND is_reversed = 0
             AND coa_credit = '4101'"
        );

        return (float)($income['total'] ?? 0) - (float)($distributed['total'] ?? 0);
    }

    /**
     * Preview distribution without executing
     */
    public function previewSyahriahDistribution(): array {
        $balance = $this->calculateSyahriahAvailableBalance();
        $rule = $this->distModel->getRuleByType('SYAHRIAH');
        
        if (!$rule) return ['available_balance' => $balance, 'allocations' => []];
        
        $details = $this->distModel->getRuleDetails($rule['rule_id']);
        
        $priorities = [];
        foreach ($details as $detail) {
            $priorities[$detail['priority']][] = $detail;
        }
        ksort($priorities);

        $preview = [];
        $remaining = $balance;

        foreach ($priorities as $priority => $depts) {
            $totalNeeded = array_sum(array_column($depts, 'allocation_value'));
            
            foreach ($depts as $dept) {
                if ($remaining >= $totalNeeded) {
                    $allocAmount = $dept['allocation_value'];
                } elseif ($remaining > 0) {
                    $allocAmount = round($remaining * ($dept['allocation_value'] / $totalNeeded), 2);
                } else {
                    $allocAmount = 0;
                }

                $preview[] = [
                    'department_name' => $dept['department_name'],
                    'needed' => $dept['allocation_value'],
                    'allocated' => $allocAmount,
                    'priority' => $priority,
                    'status' => $allocAmount >= $dept['allocation_value'] ? 'FULL' : ($allocAmount > 0 ? 'PARTIAL' : 'UNFUNDED')
                ];
            }

            $remaining = max(0, $remaining - $totalNeeded);
        }

        return [
            'available_balance' => $balance,
            'total_needed' => array_sum(array_column($details, 'allocation_value')),
            'allocations' => $preview
        ];
    }
}
