<?php
/**
 * Payment Model
 */
class PaymentModel {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create(array $data): int {
        return $this->db->insert('payments', $data);
    }

    public function createDetail(array $data): int {
        return $this->db->insert('payment_details', $data);
    }

    public function findById(int $id): ?array {
        return $this->db->fetch(
            "SELECT p.*, s.nis, s.name as student_name, u.full_name as cashier_name,
                    hm.month_name as hijri_month_name
             FROM payments p
             JOIN students s ON p.student_id = s.student_id
             JOIN users u ON p.cashier_id = u.user_id
             JOIN hijri_months hm ON p.hijri_month = hm.month_id
             WHERE p.payment_id = ?",
            [$id]
        );
    }

    public function getPaymentDetails(int $paymentId): array {
        return $this->db->fetchAll(
            "SELECT pd.*, b.payment_type, b.hijri_month, b.hijri_year, b.amount as bill_amount,
                    hm.month_name as hijri_month_name
             FROM payment_details pd
             JOIN bills b ON pd.bill_id = b.bill_id
             JOIN hijri_months hm ON b.hijri_month = hm.month_id
             WHERE pd.payment_id = ?",
            [$paymentId]
        );
    }

    public function getStudentPayments(int $studentId, ?string $type = null): array {
        $where = ['p.student_id = ?'];
        $params = [$studentId];

        if ($type) {
            $where[] = 'p.payment_type = ?';
            $params[] = $type;
        }

        return $this->db->fetchAll(
            "SELECT p.*, u.full_name as cashier_name, hm.month_name as hijri_month_name
             FROM payments p
             JOIN users u ON p.cashier_id = u.user_id
             JOIN hijri_months hm ON p.hijri_month = hm.month_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY p.payment_date DESC",
            $params
        );
    }

    public function getPaymentReport(array $filters = []): array {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['date_from'])) {
            $where[] = 'p.payment_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'p.payment_date <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['payment_type'])) {
            $where[] = 'p.payment_type = ?';
            $params[] = $filters['payment_type'];
        }
        if (!empty($filters['cashier_id'])) {
            $where[] = 'p.cashier_id = ?';
            $params[] = $filters['cashier_id'];
        }

        return $this->db->fetchAll(
            "SELECT p.*, s.nis, s.name as student_name, u.full_name as cashier_name,
                    hm.month_name as hijri_month_name
             FROM payments p
             JOIN students s ON p.student_id = s.student_id
             JOIN users u ON p.cashier_id = u.user_id
             JOIN hijri_months hm ON p.hijri_month = hm.month_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY p.payment_date DESC, p.created_at DESC",
            $params
        );
    }

    public function getTotalByType(string $type, ?string $dateFrom = null, ?string $dateTo = null): float {
        $where = ['payment_type = ?'];
        $params = [$type];
        if ($dateFrom) { $where[] = 'payment_date >= ?'; $params[] = $dateFrom; }
        if ($dateTo) { $where[] = 'payment_date <= ?'; $params[] = $dateTo; }
        
        return (float) $this->db->count(
            "SELECT COALESCE(SUM(total_amount), 0) FROM payments WHERE " . implode(' AND ', $where),
            $params
        );
    }

    public function getRecentPayments(int $limit = 10): array {
        return $this->db->fetchAll(
            "SELECT p.*, s.nis, s.name as student_name, u.full_name as cashier_name
             FROM payments p
             JOIN students s ON p.student_id = s.student_id
             JOIN users u ON p.cashier_id = u.user_id
             ORDER BY p.created_at DESC
             LIMIT ?",
            [$limit]
        );
    }
}
