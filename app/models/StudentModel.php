<?php
/**
 * Student Model
 */
class StudentModel {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function findAll(array $filters = [], int $page = 1, int $limit = 20): array {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 's.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['student_type'])) {
            $where[] = 's.student_type = ?';
            $params[] = $filters['student_type'];
        }
        if (!empty($filters['academic_year_id'])) {
            $where[] = 's.academic_year_id = ?';
            $params[] = $filters['academic_year_id'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(s.nis LIKE ? OR s.name LIKE ?)';
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }

        $whereClause = implode(' AND ', $where);
        
        $total = $this->db->count(
            "SELECT COUNT(*) FROM students s WHERE {$whereClause}", $params
        );

        $offset = ($page - 1) * $limit;
        $rows = $this->db->fetchAll(
            "SELECT s.*, sc.category_name, sc.monthly_fee, ay.year_name as academic_year_name,
                    hm.month_name as entry_hijri_month_name
             FROM students s
             JOIN syahriah_categories sc ON s.syahriah_category_id = sc.category_id
             JOIN academic_years ay ON s.academic_year_id = ay.academic_year_id
             JOIN hijri_months hm ON s.entry_hijri_month = hm.month_id
             WHERE {$whereClause}
             ORDER BY s.created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );

        return ['data' => $rows, 'total' => $total];
    }

    public function findById(int $id): ?array {
        return $this->db->fetch(
            "SELECT s.*, sc.category_name, sc.monthly_fee, ay.year_name as academic_year_name,
                    hm.month_name as entry_hijri_month_name
             FROM students s
             JOIN syahriah_categories sc ON s.syahriah_category_id = sc.category_id
             JOIN academic_years ay ON s.academic_year_id = ay.academic_year_id
             JOIN hijri_months hm ON s.entry_hijri_month = hm.month_id
             WHERE s.student_id = ?",
            [$id]
        );
    }

    public function findByNis(string $nis): ?array {
        return $this->db->fetch(
            "SELECT s.*, sc.category_name, sc.monthly_fee, ay.year_name as academic_year_name,
                    hm.month_name as entry_hijri_month_name
             FROM students s
             JOIN syahriah_categories sc ON s.syahriah_category_id = sc.category_id
             JOIN academic_years ay ON s.academic_year_id = ay.academic_year_id
             JOIN hijri_months hm ON s.entry_hijri_month = hm.month_id
             WHERE s.nis = ?",
            [$nis]
        );
    }

    public function search(string $query): array {
        return $this->db->fetchAll(
            "SELECT s.*, sc.category_name, sc.monthly_fee
             FROM students s
             JOIN syahriah_categories sc ON s.syahriah_category_id = sc.category_id
             WHERE (s.nis LIKE ? OR s.name LIKE ?) AND s.status = 'ACTIVE'
             ORDER BY s.name
             LIMIT 20",
            ['%' . $query . '%', '%' . $query . '%']
        );
    }

    public function create(array $data): int {
        return $this->db->insert('students', $data);
    }

    public function update(int $id, array $data): int {
        return $this->db->update('students', $data, 'student_id = ?', [$id]);
    }

    /**
     * Soft delete - set status to DROPPED
     */
    public function delete(int $id): int {
        return $this->db->update('students', ['status' => 'DROPPED'], 'student_id = ?', [$id]);
    }

    public function getActiveStudents(): array {
        return $this->db->fetchAll(
            "SELECT s.*, sc.monthly_fee 
             FROM students s
             JOIN syahriah_categories sc ON s.syahriah_category_id = sc.category_id
             WHERE s.status = 'ACTIVE'"
        );
    }

    /**
     * Bulk create students from CSV data
     */
    public function bulkCreate(array $students): array {
        $created = [];
        foreach ($students as $data) {
            try {
                $id = $this->create($data);
                $created[] = ['student_id' => $id, 'nis' => $data['nis'], 'name' => $data['name'], 'status' => 'success'];
            } catch (Exception $e) {
                $created[] = ['nis' => $data['nis'] ?? 'unknown', 'name' => $data['name'] ?? 'unknown', 'status' => 'error', 'message' => $e->getMessage()];
            }
        }
        return $created;
    }
}
