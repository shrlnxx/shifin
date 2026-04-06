<?php
/**
 * SHIFFIN - Payment Service (FIFO Allocation + Bill Selection)
 */
class PaymentService {
    private PaymentModel $paymentModel;
    private BillModel $billModel;
    private TransactionService $txnService;
    private DepartmentModel $deptModel;
    private Database $db;

    public function __construct() {
        $this->paymentModel = new PaymentModel();
        $this->billModel = new BillModel();
        $this->txnService = new TransactionService();
        $this->deptModel = new DepartmentModel();
        $this->db = Database::getInstance();
    }

    /**
     * Process a student payment
     * For SYAHRIAH: uses bill_ids (specific bills selected via checkbox)
     * For DAFTAR_ULANG: uses FIFO allocation with free amount input (cicilan)
     * For PSB: uses FIFO allocation
     */
    public function processPayment(
        int $studentId,
        string $paymentType,
        float $amount,
        int $cashierId,
        int $hijriMonth,
        int $hijriYear,
        ?string $notes = null,
        ?string $paymentDate = null,
        ?array $billIds = null
    ): array {
        $this->db->beginTransaction();

        try {
            $payDate = $paymentDate ?: date('Y-m-d');

            if ($paymentType === 'SYAHRIAH' && !empty($billIds)) {
                // SYAHRIAH: Pay specific selected bills (checkbox-based)
                return $this->paySelectedBills($studentId, $paymentType, $billIds, $cashierId, $hijriMonth, $hijriYear, $notes, $payDate);
            } else {
                // DAFTAR_ULANG / PSB: FIFO allocation with free amount
                return $this->payFIFO($studentId, $paymentType, $amount, $cashierId, $hijriMonth, $hijriYear, $notes, $payDate);
            }

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Pay specific selected bills (for SYAHRIAH checkbox)
     */
    private function paySelectedBills(
        int $studentId,
        string $paymentType,
        array $billIds,
        int $cashierId,
        int $hijriMonth,
        int $hijriYear,
        ?string $notes,
        string $payDate
    ): array {
        $totalAmount = 0;
        $details = [];

        // Validate all bills belong to this student and type
        foreach ($billIds as $billId) {
            $bill = $this->billModel->findById((int)$billId);
            if (!$bill) throw new Exception("Tagihan ID {$billId} tidak ditemukan");
            if ($bill['student_id'] != $studentId) throw new Exception("Tagihan tidak milik santri ini");
            if ($bill['payment_type'] !== $paymentType) throw new Exception("Tipe tagihan tidak sesuai");
            if ($bill['status'] === 'PAID') throw new Exception("Tagihan {$billId} sudah lunas");
        }

        // Create payment header
        // First calculate total
        foreach ($billIds as $billId) {
            $bill = $this->billModel->findById((int)$billId);
            $remaining = $bill['amount'] - $bill['paid_amount'];
            $totalAmount += $remaining;
        }

        $paymentId = $this->paymentModel->create([
            'student_id' => $studentId,
            'payment_date' => $payDate,
            'hijri_month' => $hijriMonth,
            'hijri_year' => $hijriYear,
            'total_amount' => $totalAmount,
            'payment_type' => $paymentType,
            'cashier_id' => $cashierId,
            'notes' => $notes
        ]);

        // Pay each selected bill in full
        foreach ($billIds as $billId) {
            $bill = $this->billModel->findById((int)$billId);
            $remaining = $bill['amount'] - $bill['paid_amount'];

            $this->paymentModel->createDetail([
                'payment_id' => $paymentId,
                'bill_id' => $bill['bill_id'],
                'paid_amount' => $remaining
            ]);

            $this->billModel->updatePayment($bill['bill_id'], $remaining);

            $details[] = [
                'bill_id' => $bill['bill_id'],
                'hijri_month' => $bill['hijri_month'],
                'hijri_month_name' => $bill['hijri_month_name'],
                'hijri_year' => $bill['hijri_year'],
                'allocated' => $remaining
            ];
        }

        // Record financial transaction
        $coaCredit = $this->getRevenueCOA($paymentType);
        $txnId = $this->txnService->recordIncome(
            $paymentType,
            $coaCredit,
            $totalAmount,
            "Pembayaran {$paymentType} - Student ID: {$studentId}",
            $cashierId,
            $paymentId,
            'PAYMENT',
            $payDate
        );

        $this->db->commit();

        return [
            'payment_id' => $paymentId,
            'transaction_id' => $txnId,
            'total_paid' => $totalAmount,
            'allocated' => $totalAmount,
            'overpayment' => 0,
            'details' => $details
        ];
    }

    /**
     * Pay using FIFO allocation (for DAFTAR_ULANG cicilan and PSB)
     */
    private function payFIFO(
        int $studentId,
        string $paymentType,
        float $amount,
        int $cashierId,
        int $hijriMonth,
        int $hijriYear,
        ?string $notes,
        string $payDate
    ): array {
        // Get outstanding bills (FIFO order)
        $bills = $this->billModel->getOutstandingBills($studentId, $paymentType);
        
        if (empty($bills)) {
            throw new Exception('Tidak ada tagihan yang belum dibayar untuk jenis pembayaran ini');
        }

        // Create payment header
        $paymentId = $this->paymentModel->create([
            'student_id' => $studentId,
            'payment_date' => $payDate,
            'hijri_month' => $hijriMonth,
            'hijri_year' => $hijriYear,
            'total_amount' => $amount,
            'payment_type' => $paymentType,
            'cashier_id' => $cashierId,
            'notes' => $notes
        ]);

        // FIFO allocation
        $remaining = $amount;
        $details = [];

        foreach ($bills as $bill) {
            if ($remaining <= 0) break;

            $outstanding = $bill['amount'] - $bill['paid_amount'];
            $allocate = min($remaining, $outstanding);

            $this->paymentModel->createDetail([
                'payment_id' => $paymentId,
                'bill_id' => $bill['bill_id'],
                'paid_amount' => $allocate
            ]);

            $this->billModel->updatePayment($bill['bill_id'], $allocate);

            $details[] = [
                'bill_id' => $bill['bill_id'],
                'hijri_month' => $bill['hijri_month'],
                'hijri_month_name' => $bill['hijri_month_name'],
                'hijri_year' => $bill['hijri_year'],
                'allocated' => $allocate
            ];

            $remaining -= $allocate;
        }

        // Record financial transaction
        $coaCredit = $this->getRevenueCOA($paymentType);
        $txnId = $this->txnService->recordIncome(
            $paymentType,
            $coaCredit,
            $amount,
            "Pembayaran {$paymentType} - Student ID: {$studentId}",
            $cashierId,
            $paymentId,
            'PAYMENT',
            $payDate
        );

        $this->db->commit();

        return [
            'payment_id' => $paymentId,
            'transaction_id' => $txnId,
            'total_paid' => $amount,
            'allocated' => $amount - $remaining,
            'overpayment' => max(0, $remaining),
            'details' => $details
        ];
    }

    /**
     * Get receipt data for a payment
     */
    public function getReceipt(int $paymentId): array {
        $payment = $this->paymentModel->findById($paymentId);
        if (!$payment) throw new Exception('Payment not found');

        $details = $this->paymentModel->getPaymentDetails($paymentId);
        $settingModel = new SettingModel();

        return [
            'payment' => $payment,
            'details' => $details,
            'pesantren_name' => $settingModel->get('pesantren_name'),
            'pesantren_address' => $settingModel->get('pesantren_address')
        ];
    }

    private function getRevenueCOA(string $paymentType): string {
        return match($paymentType) {
            'SYAHRIAH' => '4101',
            'PSB' => '4103',
            'DAFTAR_ULANG' => '4102',
            default => '4101'
        };
    }
}
