<?php
/**
 * COA, Department, Category, AcademicYear, Distribution, Setting, User Models
 * Combined into a single file for simpler maintenance
 */

class COAModel {
    private Database $db;
    public function __construct() { $this->db = Database::getInstance(); }

    public function findAll(): array {
        return $this->db->fetchAll("SELECT * FROM coa ORDER BY coa_id");
    }
    public function findById(string $id): ?array {
        return $this->db->fetch("SELECT * FROM coa WHERE coa_id = ?", [$id]);
    }
    public function create(array $data): string {
        $this->db->insert('coa', $data);
        return $data['coa_id'];
    }
    public function update(string $id, array $data): int {
        return $this->db->update('coa', $data, 'coa_id = ?', [$id]);
    }
    public function getByGroup(string $group): array {
        return $this->db->fetchAll("SELECT * FROM coa WHERE coa_group = ? AND is_active = 1 ORDER BY coa_id", [$group]);
    }
}

class DepartmentModel {
    private Database $db;
    public function __construct() { $this->db = Database::getInstance(); }

    public function findAll(): array {
        return $this->db->fetchAll(
            "SELECT d.*, dcm.coa_id, c.coa_name
             FROM departments d
             LEFT JOIN department_coa_mapping dcm ON d.department_id = dcm.department_id
             LEFT JOIN coa c ON dcm.coa_id = c.coa_id
             WHERE d.is_active = 1
             ORDER BY d.department_type, d.department_name"
        );
    }
    public function findById(int $id): ?array {
        return $this->db->fetch(
            "SELECT d.*, dcm.coa_id, c.coa_name
             FROM departments d
             LEFT JOIN department_coa_mapping dcm ON d.department_id = dcm.department_id
             LEFT JOIN coa c ON dcm.coa_id = c.coa_id
             WHERE d.department_id = ?", [$id]
        );
    }
    public function create(array $data): int {
        return $this->db->insert('departments', $data);
    }
    public function update(int $id, array $data): int {
        return $this->db->update('departments', $data, 'department_id = ?', [$id]);
    }
    public function getByType(string $type): array {
        return $this->db->fetchAll(
            "SELECT * FROM departments WHERE department_type = ? AND is_active = 1", [$type]
        );
    }
    public function getCOAMapping(int $departmentId): ?array {
        return $this->db->fetch(
            "SELECT dcm.*, c.coa_name FROM department_coa_mapping dcm
             JOIN coa c ON dcm.coa_id = c.coa_id
             WHERE dcm.department_id = ?", [$departmentId]
        );
    }
    public function findByName(string $name): ?array {
        return $this->db->fetch("SELECT * FROM departments WHERE department_name = ?", [$name]);
    }
}

class CategoryModel {
    private Database $db;
    public function __construct() { $this->db = Database::getInstance(); }

    public function findAll(): array {
        return $this->db->fetchAll("SELECT * FROM syahriah_categories ORDER BY category_name");
    }
    public function findById(int $id): ?array {
        return $this->db->fetch("SELECT * FROM syahriah_categories WHERE category_id = ?", [$id]);
    }
    public function create(array $data): int {
        return $this->db->insert('syahriah_categories', $data);
    }
    public function update(int $id, array $data): int {
        return $this->db->update('syahriah_categories', $data, 'category_id = ?', [$id]);
    }
    public function getActive(): array {
        return $this->db->fetchAll("SELECT * FROM syahriah_categories WHERE is_active = 1 ORDER BY monthly_fee ASC");
    }
}

class AcademicYearModel {
    private Database $db;
    public function __construct() { $this->db = Database::getInstance(); }

    public function findAll(): array {
        return $this->db->fetchAll("SELECT * FROM academic_years ORDER BY academic_year_id DESC");
    }
    public function findById(int $id): ?array {
        return $this->db->fetch("SELECT * FROM academic_years WHERE academic_year_id = ?", [$id]);
    }
    public function getActive(): ?array {
        return $this->db->fetch("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1");
    }
    public function create(array $data): int {
        return $this->db->insert('academic_years', $data);
    }
    public function update(int $id, array $data): int {
        return $this->db->update('academic_years', $data, 'academic_year_id = ?', [$id]);
    }
}

class DistributionModel {
    private Database $db;
    public function __construct() { $this->db = Database::getInstance(); }

    public function getRules(?string $sourceType = null): array {
        $where = $sourceType ? "WHERE source_type = ? AND is_active = 1" : "WHERE is_active = 1";
        $params = $sourceType ? [$sourceType] : [];
        return $this->db->fetchAll("SELECT * FROM distribution_rules {$where} ORDER BY rule_id", $params);
    }

    public function getRuleDetails(int $ruleId): array {
        return $this->db->fetchAll(
            "SELECT drd.*, d.department_name
             FROM distribution_rule_details drd
             JOIN departments d ON drd.department_id = d.department_id
             WHERE drd.rule_id = ?
             ORDER BY drd.priority, d.department_name",
            [$ruleId]
        );
    }

    public function getRuleByType(string $sourceType): ?array {
        return $this->db->fetch(
            "SELECT * FROM distribution_rules WHERE source_type = ? AND is_active = 1 LIMIT 1",
            [$sourceType]
        );
    }

    public function createLog(array $data): int {
        return $this->db->insert('distribution_logs', $data);
    }

    public function createLogDetail(array $data): int {
        return $this->db->insert('distribution_log_details', $data);
    }

    public function getDistributionHistory(array $filters = []): array {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['source_type'])) {
            $where[] = 'dr.source_type = ?';
            $params[] = $filters['source_type'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'dl.distribution_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'dl.distribution_date <= ?';
            $params[] = $filters['date_to'];
        }

        return $this->db->fetchAll(
            "SELECT dl.*, dr.rule_name, dr.source_type, u.full_name as created_by_name,
                    hm.month_name as hijri_month_name
             FROM distribution_logs dl
             JOIN distribution_rules dr ON dl.rule_id = dr.rule_id
             JOIN users u ON dl.created_by = u.user_id
             JOIN hijri_months hm ON dl.hijri_month = hm.month_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY dl.created_at DESC",
            $params
        );
    }

    public function getDistributionLogDetails(int $logId): array {
        return $this->db->fetchAll(
            "SELECT dld.*, d.department_name
             FROM distribution_log_details dld
             JOIN departments d ON dld.department_id = d.department_id
             WHERE dld.log_id = ?
             ORDER BY d.department_name",
            [$logId]
        );
    }

    public function updateRuleDetail(int $detailId, array $data): int {
        return $this->db->update('distribution_rule_details', $data, 'detail_id = ?', [$detailId]);
    }
}

class SettingModel {
    private Database $db;
    public function __construct() { $this->db = Database::getInstance(); }

    public function get(string $key): ?string {
        $row = $this->db->fetch("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
        return $row ? $row['setting_value'] : null;
    }
    public function set(string $key, string $value): void {
        $exists = $this->db->count("SELECT COUNT(*) FROM settings WHERE setting_key = ?", [$key]);
        if ($exists) {
            $this->db->update('settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
        } else {
            $this->db->insert('settings', ['setting_key' => $key, 'setting_value' => $value]);
        }
    }
    public function getAll(): array {
        return $this->db->fetchAll("SELECT * FROM settings ORDER BY setting_key");
    }
}

class UserModel {
    private Database $db;
    public function __construct() { $this->db = Database::getInstance(); }

    public function findAll(): array {
        return $this->db->fetchAll("SELECT user_id, username, full_name, role, is_active, created_at FROM users ORDER BY role, full_name");
    }
    public function findById(int $id): ?array {
        return $this->db->fetch("SELECT user_id, username, full_name, role, is_active, created_at FROM users WHERE user_id = ?", [$id]);
    }
    public function create(array $data): int {
        $data['password_hash'] = Auth::hashPassword($data['password']);
        unset($data['password']);
        return $this->db->insert('users', $data);
    }
    public function update(int $id, array $data): int {
        if (isset($data['password'])) {
            $data['password_hash'] = Auth::hashPassword($data['password']);
            unset($data['password']);
        }
        return $this->db->update('users', $data, 'user_id = ?', [$id]);
    }

    public function getHijriMonths(): array {
        return $this->db->fetchAll("SELECT * FROM hijri_months ORDER BY month_id");
    }
}
