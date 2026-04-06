<?php
/**
 * Bill Model
 */
class BillModel {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array {
        return $this->db->fetch("SELECT b.*, hm.month_name as hijri_month_name FROM bills b JOIN hijri_months hm ON b.hijri_month = hm.month_id WHERE b.bill_id = ?", [$id]);
    }

    public function getStudentBills(int $studentId, ?string $type = null, ?string $status = null): array {
        $where = ['b.student_id = ?'];
        $params = [$studentId];

        if ($type) {
            $where[] = 'b.payment_type = ?';
            $params[] = $type;
        }
        if ($status) {
            $where[] = 'b.status = ?';
            $params[] = $status;
        }

        return $this->db->fetchAll(
            "SELECT b.*, hm.month_name as hijri_month_name
             FROM bills b
             JOIN hijri_months hm ON b.hijri_month = hm.month_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY b.hijri_year ASC, b.hijri_month ASC",
            $params
        );
    }

    public function getOutstandingBills(int $studentId, string $type): array {
        return $this->db->fetchAll(
            "SELECT b.*, hm.month_name as hijri_month_name
             FROM bills b
             JOIN hijri_months hm ON b.hijri_month = hm.month_id
             WHERE b.student_id = ? AND b.payment_type = ? AND b.status IN ('UNPAID','PARTIAL')
             ORDER BY b.hijri_year ASC, b.hijri_month ASC",
            [$studentId, $type]
        );
    }

    public function create(array $data): int {
        return $this->db->insert('bills', $data);
    }

    public function updatePayment(int $billId, float $paidAmount): void {
        $bill = $this->findById($billId);
        if (!$bill) throw new Exception('Bill not found');

        $newPaid = $bill['paid_amount'] + $paidAmount;
        $status = 'PARTIAL';
        if ($newPaid >= $bill['amount']) {
            $status = 'PAID';
            $newPaid = $bill['amount'];
        }

        $this->db->update('bills', [
            'paid_amount' => $newPaid,
            'status' => $status
        ], 'bill_id = ?', [$billId]);
    }

    public function exists(int $studentId, string $type, int $hijriMonth, int $hijriYear): bool {
        return $this->db->count(
            "SELECT COUNT(*) FROM bills WHERE student_id = ? AND payment_type = ? AND hijri_month = ? AND hijri_year = ?",
            [$studentId, $type, $hijriMonth, $hijriYear]
        ) > 0;
    }

    public function getOutstandingReport(array $filters = []): array {
        $where = ["b.status IN ('UNPAID','PARTIAL')"];
        $params = [];

        if (!empty($filters['student_id'])) {
            $where[] = 'b.student_id = ?';
            $params[] = $filters['student_id'];
        }
        if (!empty($filters['payment_type'])) {
            $where[] = 'b.payment_type = ?';
            $params[] = $filters['payment_type'];
        }
        if (!empty($filters['hijri_year'])) {
            $where[] = 'b.hijri_year = ?';
            $params[] = $filters['hijri_year'];
        }

        return $this->db->fetchAll(
            "SELECT b.*, s.nis, s.name as student_name, hm.month_name as hijri_month_name
             FROM bills b
             JOIN students s ON b.student_id = s.student_id
             JOIN hijri_months hm ON b.hijri_month = hm.month_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY s.name, b.hijri_year, b.hijri_month",
            $params
        );
    }

    /**
     * Get outstanding bills summarized per month (no student names)
     * Rekap tunggakan per bulan per payment_type
     */
    public function getOutstandingMonthlySummary(): array {
        return $this->db->fetchAll(
            "SELECT b.hijri_month, b.hijri_year, hm.month_name as hijri_month_name,
                    b.payment_type,
                    COUNT(*) as bill_count,
                    COALESCE(SUM(b.amount), 0) as total_amount,
                    COALESCE(SUM(b.paid_amount), 0) as total_paid,
                    COALESCE(SUM(b.amount - b.paid_amount), 0) as total_outstanding
             FROM bills b
             JOIN hijri_months hm ON b.hijri_month = hm.month_id
             WHERE b.status IN ('UNPAID','PARTIAL')
             GROUP BY b.hijri_year, b.hijri_month, hm.month_name, b.payment_type
             ORDER BY b.hijri_year ASC, b.hijri_month ASC, b.payment_type"
        );
    }

    public function getTotalOutstanding(?string $type = null): float {
        $sql = "SELECT COALESCE(SUM(amount - paid_amount), 0) FROM bills WHERE status IN ('UNPAID','PARTIAL')";
        $params = [];
        if ($type) {
            $sql .= " AND payment_type = ?";
            $params[] = $type;
        }
        return (float) $this->db->count($sql, $params);
    }
}
