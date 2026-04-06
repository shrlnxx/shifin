-- =====================================================
-- SHIFFIN (Shiffa Finance) - Database Schema
-- Pesantren Financial Management System
-- =====================================================

CREATE DATABASE IF NOT EXISTS shifin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE shifin;

-- =====================================================
-- 1. USERS (System authentication & roles)
-- =====================================================
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('ADMIN','TREASURER','CASHIER') NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- 2. HIJRI MONTHS (Reference table)
-- =====================================================
CREATE TABLE hijri_months (
    month_id INT PRIMARY KEY,
    month_name VARCHAR(30) NOT NULL
) ENGINE=InnoDB;

-- =====================================================
-- 3. ACADEMIC YEARS
-- =====================================================
CREATE TABLE academic_years (
    academic_year_id INT AUTO_INCREMENT PRIMARY KEY,
    year_name VARCHAR(30) NOT NULL,
    hijri_year_start INT NOT NULL,
    hijri_month_start INT NOT NULL,
    hijri_year_end INT NOT NULL,
    hijri_month_end INT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- 4. CHART OF ACCOUNTS (COA)
-- =====================================================
CREATE TABLE coa (
    coa_id VARCHAR(10) PRIMARY KEY,
    coa_name VARCHAR(100) NOT NULL,
    coa_group ENUM('ASSET','LIABILITY','EQUITY','REVENUE','EXPENSE') NOT NULL,
    coa_type VARCHAR(50) NOT NULL,
    normal_balance ENUM('DEBIT','CREDIT') NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- 5. DEPARTMENTS (Bidang)
-- =====================================================
CREATE TABLE departments (
    department_id INT AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(100) NOT NULL,
    department_type ENUM('REVENUE','COST','MIX') NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- 6. DEPARTMENT - COA MAPPING
-- =====================================================
CREATE TABLE department_coa_mapping (
    mapping_id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    coa_id VARCHAR(10) NOT NULL,
    mapping_type ENUM('INCOME','EXPENSE') NOT NULL DEFAULT 'INCOME',
    FOREIGN KEY (department_id) REFERENCES departments(department_id),
    FOREIGN KEY (coa_id) REFERENCES coa(coa_id),
    UNIQUE KEY unique_dept_coa (department_id, coa_id)
) ENGINE=InnoDB;

-- =====================================================
-- 7. SYAHRIAH CATEGORIES (Student fee tiers)
-- =====================================================
CREATE TABLE syahriah_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(50) NOT NULL,
    monthly_fee DECIMAL(15,2) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- 8. STUDENTS
-- =====================================================
CREATE TABLE students (
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    nis VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    student_type ENUM('Mukim','Kampung') NOT NULL,
    syahriah_category_id INT NOT NULL,
    entry_date DATE NOT NULL,
    entry_hijri_month INT NOT NULL,
    entry_hijri_year INT NOT NULL,
    academic_year_id INT NOT NULL,
    status ENUM('ACTIVE','GRADUATED','DROPPED') DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (syahriah_category_id) REFERENCES syahriah_categories(category_id),
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(academic_year_id),
    FOREIGN KEY (entry_hijri_month) REFERENCES hijri_months(month_id)
) ENGINE=InnoDB;

CREATE INDEX idx_students_nis ON students(nis);
CREATE INDEX idx_students_name ON students(name);
CREATE INDEX idx_students_status ON students(status);

-- =====================================================
-- 9. BILLS (Student billing records)
-- =====================================================
CREATE TABLE bills (
    bill_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    payment_type ENUM('SYAHRIAH','PSB','DAFTAR_ULANG') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    paid_amount DECIMAL(15,2) DEFAULT 0.00,
    hijri_month INT NOT NULL,
    hijri_year INT NOT NULL,
    academic_year_id INT NOT NULL,
    status ENUM('UNPAID','PARTIAL','PAID') DEFAULT 'UNPAID',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id),
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(academic_year_id),
    FOREIGN KEY (hijri_month) REFERENCES hijri_months(month_id)
) ENGINE=InnoDB;

CREATE INDEX idx_bills_student ON bills(student_id);
CREATE INDEX idx_bills_status ON bills(status);
CREATE INDEX idx_bills_type ON bills(payment_type);
CREATE INDEX idx_bills_period ON bills(hijri_year, hijri_month);

-- =====================================================
-- 10. PAYMENTS (Payment header)
-- =====================================================
CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    payment_date DATE NOT NULL,
    hijri_month INT NOT NULL,
    hijri_year INT NOT NULL,
    total_amount DECIMAL(15,2) NOT NULL,
    payment_type ENUM('SYAHRIAH','PSB','DAFTAR_ULANG') NOT NULL,
    cashier_id INT NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id),
    FOREIGN KEY (cashier_id) REFERENCES users(user_id),
    FOREIGN KEY (hijri_month) REFERENCES hijri_months(month_id)
) ENGINE=InnoDB;

CREATE INDEX idx_payments_student ON payments(student_id);
CREATE INDEX idx_payments_date ON payments(payment_date);
CREATE INDEX idx_payments_type ON payments(payment_type);

-- =====================================================
-- 11. PAYMENT DETAILS (Line items linking to bills)
-- =====================================================
CREATE TABLE payment_details (
    detail_id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    bill_id INT NOT NULL,
    paid_amount DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (payment_id) REFERENCES payments(payment_id),
    FOREIGN KEY (bill_id) REFERENCES bills(bill_id)
) ENGINE=InnoDB;

-- =====================================================
-- 12. FINANCIAL TRANSACTIONS (Immutable ledger)
-- =====================================================
CREATE TABLE financial_transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_date DATE NOT NULL,
    hijri_month INT NOT NULL,
    hijri_year INT NOT NULL,
    academic_year_id INT,
    transaction_type ENUM('INCOME','EXPENSE','DISTRIBUTION','REVERSAL','REFUND') NOT NULL,
    income_source VARCHAR(50),
    department_source INT,
    department_target INT,
    coa_debit VARCHAR(10) NOT NULL,
    coa_credit VARCHAR(10) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    description TEXT,
    reference_id INT,
    reference_type VARCHAR(30),
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_reversed TINYINT(1) DEFAULT 0,
    reversal_of INT DEFAULT NULL,
    FOREIGN KEY (coa_debit) REFERENCES coa(coa_id),
    FOREIGN KEY (coa_credit) REFERENCES coa(coa_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id),
    FOREIGN KEY (hijri_month) REFERENCES hijri_months(month_id)
) ENGINE=InnoDB;

CREATE INDEX idx_ft_date ON financial_transactions(transaction_date);
CREATE INDEX idx_ft_type ON financial_transactions(transaction_type);
CREATE INDEX idx_ft_ref ON financial_transactions(reference_id, reference_type);

-- =====================================================
-- 13. LEDGER ENTRIES (Department running balances)
-- =====================================================
CREATE TABLE ledger_entries (
    ledger_id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    transaction_id INT NOT NULL,
    debit DECIMAL(15,2) DEFAULT 0.00,
    credit DECIMAL(15,2) DEFAULT 0.00,
    balance DECIMAL(15,2) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(department_id),
    FOREIGN KEY (transaction_id) REFERENCES financial_transactions(transaction_id)
) ENGINE=InnoDB;

CREATE INDEX idx_ledger_dept ON ledger_entries(department_id);
CREATE INDEX idx_ledger_txn ON ledger_entries(transaction_id);

-- =====================================================
-- 14. DISTRIBUTION RULES
-- =====================================================
CREATE TABLE distribution_rules (
    rule_id INT AUTO_INCREMENT PRIMARY KEY,
    rule_name VARCHAR(50) NOT NULL,
    source_type ENUM('SYAHRIAH','PSB','DAFTAR_ULANG') NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- 15. DISTRIBUTION RULE DETAILS
-- =====================================================
CREATE TABLE distribution_rule_details (
    detail_id INT AUTO_INCREMENT PRIMARY KEY,
    rule_id INT NOT NULL,
    department_id INT NOT NULL,
    allocation_type ENUM('PERCENTAGE','FIXED') NOT NULL,
    allocation_value DECIMAL(15,4) NOT NULL,
    priority INT DEFAULT 0,
    FOREIGN KEY (rule_id) REFERENCES distribution_rules(rule_id),
    FOREIGN KEY (department_id) REFERENCES departments(department_id)
) ENGINE=InnoDB;

-- =====================================================
-- 16. DISTRIBUTION LOGS
-- =====================================================
CREATE TABLE distribution_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    distribution_date DATE NOT NULL,
    hijri_month INT NOT NULL,
    hijri_year INT NOT NULL,
    rule_id INT NOT NULL,
    total_distributed DECIMAL(15,2) NOT NULL,
    status ENUM('SUCCESS','PARTIAL','FAILED') DEFAULT 'SUCCESS',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rule_id) REFERENCES distribution_rules(rule_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id),
    FOREIGN KEY (hijri_month) REFERENCES hijri_months(month_id)
) ENGINE=InnoDB;

-- =====================================================
-- 17. DISTRIBUTION LOG DETAILS
-- =====================================================
CREATE TABLE distribution_log_details (
    detail_id INT AUTO_INCREMENT PRIMARY KEY,
    log_id INT NOT NULL,
    department_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    transaction_id INT NOT NULL,
    FOREIGN KEY (log_id) REFERENCES distribution_logs(log_id),
    FOREIGN KEY (department_id) REFERENCES departments(department_id),
    FOREIGN KEY (transaction_id) REFERENCES financial_transactions(transaction_id)
) ENGINE=InnoDB;

-- =====================================================
-- 18. SETTINGS (Key-value config store)
-- =====================================================
CREATE TABLE settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    description VARCHAR(200),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- TRIGGERS: Prevent DELETE/UPDATE on financial_transactions
-- =====================================================
DELIMITER //

CREATE TRIGGER prevent_ft_delete
BEFORE DELETE ON financial_transactions
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Financial transactions cannot be deleted. Use reversal transactions.';
END//

CREATE TRIGGER prevent_ft_update
BEFORE UPDATE ON financial_transactions
FOR EACH ROW
BEGIN
    IF OLD.is_reversed = 0 AND NEW.is_reversed = 1 THEN
        -- Allow marking as reversed
        SET @dummy = 1;
    ELSE
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Financial transactions cannot be modified. Use reversal transactions.';
    END IF;
END//

DELIMITER ;
