-- =====================================================
-- SHIFFIN (Shiffa Finance) - Seed Data
-- =====================================================

USE shifin;

-- =====================================================
-- HIJRI MONTHS
-- =====================================================
INSERT INTO hijri_months (month_id, month_name) VALUES
(1, 'Muharram'),
(2, 'Safar'),
(3, 'Rabiul Awal'),
(4, 'Rabiul Akhir'),
(5, 'Jumadil Awal'),
(6, 'Jumadil Akhir'),
(7, 'Rajab'),
(8, 'Sya\'ban'),
(9, 'Ramadhan'),
(10, 'Syawal'),
(11, 'Dzulqa\'dah'),
(12, 'Dzulhijjah');

-- =====================================================
-- CHART OF ACCOUNTS
-- =====================================================
INSERT INTO coa (coa_id, coa_name, coa_group, coa_type, normal_balance) VALUES
-- Asset
('1101', 'Kas Utama', 'ASSET', 'Kas', 'DEBIT'),
-- Revenue
('4101', 'Pendapatan Syahriah', 'REVENUE', 'Pendapatan Operasional', 'CREDIT'),
('4102', 'Pendapatan Daftar Ulang', 'REVENUE', 'Pendapatan Operasional', 'CREDIT'),
('4103', 'Pendapatan PSB', 'REVENUE', 'Pendapatan Operasional', 'CREDIT'),
('4104', 'Pendapatan Bantuan', 'REVENUE', 'Pendapatan Operasional', 'CREDIT'),
('4105', 'Pendapatan Penjualan Seragam', 'REVENUE', 'Pendapatan Operasional', 'CREDIT'),
('4106', 'Pendapatan Penjualan Kalender', 'REVENUE', 'Pendapatan Operasional', 'CREDIT'),
('4107', 'Pendapatan Ujian dan Buku', 'REVENUE', 'Pendapatan Operasional', 'CREDIT'),
-- Expense
('5101', 'Beban Administrasi', 'EXPENSE', 'Beban Operasional', 'DEBIT'),
('5102', 'Beban Perlengkapan', 'EXPENSE', 'Beban Operasional', 'DEBIT'),
('5103', 'Beban Pendidikan', 'EXPENSE', 'Beban Operasional', 'DEBIT'),
('5104', 'Beban Keamanan', 'EXPENSE', 'Beban Operasional', 'DEBIT'),
('5105', 'Beban Media', 'EXPENSE', 'Beban Operasional', 'DEBIT'),
('5106', 'Beban Humas', 'EXPENSE', 'Beban Operasional', 'DEBIT'),
('5107', 'Beban Kesehatan', 'EXPENSE', 'Beban Operasional', 'DEBIT'),
('5108', 'Beban Kebersihan', 'EXPENSE', 'Beban Operasional', 'DEBIT'),
('5109', 'Beban Organisasi', 'EXPENSE', 'Beban Operasional', 'DEBIT'),
('5110', 'Beban Operasional Pondok', 'EXPENSE', 'Beban Operasional', 'DEBIT'),
('5111', 'HPP Seragam', 'EXPENSE', 'HPP', 'DEBIT'),
('5112', 'HPP Kalender', 'EXPENSE', 'HPP', 'DEBIT'),
('5113', 'Beban Madin', 'EXPENSE', 'Beban Operasional', 'DEBIT'),
('5114', 'Beban Haflah', 'EXPENSE', 'Beban Operasional', 'DEBIT'),
('5115', 'Beban Bantuan', 'EXPENSE', 'Beban Operasional', 'DEBIT'),
('5116', 'Beban BPH', 'EXPENSE', 'Beban Operasional', 'DEBIT'),
('5117', 'Beban Lain lain', 'EXPENSE', 'Beban Operasional', 'DEBIT'),
('5118', 'Beban Uang Gedung', 'EXPENSE', 'Beban Operasional', 'DEBIT'),
('5119', 'Beban Kartu Makan', 'EXPENSE', 'Beban Operasional', 'DEBIT');

-- =====================================================
-- DEPARTMENTS (Bidang)
-- =====================================================
INSERT INTO departments (department_name, department_type) VALUES
('Syahriah', 'REVENUE'),
('Daftar Ulang', 'REVENUE'),
('PSB', 'REVENUE'),
('Bantuan', 'REVENUE'),
('Penjualan Seragam', 'REVENUE'),
('Penjualan Kalender', 'REVENUE'),
('Ujian dan Buku', 'REVENUE'),
('Administrasi', 'COST'),
('Perlengkapan', 'COST'),
('Pendidikan', 'COST'),
('Keamanan', 'COST'),
('Media', 'COST'),
('Humas', 'COST'),
('Kesehatan', 'COST'),
('Kebersihan', 'COST'),
('Organisasi', 'COST'),
('Operasional Pondok', 'COST'),
('Madin', 'COST'),
('Haflah', 'COST'),
('BPH', 'COST'),
('Lain lain', 'COST'),
('Uang Gedung', 'COST'),
('Kartu Makan', 'COST');

-- =====================================================
-- DEPARTMENT-COA MAPPINGS
-- =====================================================
INSERT INTO department_coa_mapping (department_id, coa_id, mapping_type) VALUES
-- Revenue mappings
(1, '4101', 'INCOME'),   -- Syahriah
(2, '4102', 'INCOME'),   -- Daftar Ulang
(3, '4103', 'INCOME'),   -- PSB
(4, '4104', 'INCOME'),   -- Bantuan
(5, '4105', 'INCOME'),   -- Penjualan Seragam
(6, '4106', 'INCOME'),   -- Penjualan Kalender
(7, '4107', 'INCOME'),   -- Ujian dan Buku
-- Cost mappings (expense COA)
(8, '5101', 'EXPENSE'),  -- Administrasi
(9, '5102', 'EXPENSE'),  -- Perlengkapan
(10, '5103', 'EXPENSE'), -- Pendidikan
(11, '5104', 'EXPENSE'), -- Keamanan
(12, '5105', 'EXPENSE'), -- Media
(13, '5106', 'EXPENSE'), -- Humas
(14, '5107', 'EXPENSE'), -- Kesehatan
(15, '5108', 'EXPENSE'), -- Kebersihan
(16, '5109', 'EXPENSE'), -- Organisasi
(17, '5110', 'EXPENSE'), -- Operasional Pondok
(18, '5113', 'EXPENSE'), -- Madin
(19, '5114', 'EXPENSE'), -- Haflah
(20, '5116', 'EXPENSE'), -- BPH
(21, '5117', 'EXPENSE'), -- Lain lain
(22, '5118', 'EXPENSE'), -- Uang Gedung
(23, '5119', 'EXPENSE'); -- Kartu Makan

-- =====================================================
-- SYAHRIAH CATEGORIES
-- =====================================================
INSERT INTO syahriah_categories (category_name, monthly_fee) VALUES
('Mukim', 350000),
('Kampung', 250000),
('Bersaudara', 300000),
('Abdi 1', 0),
('Abdi 2', 100000),
('Abdi 3', 150000),
('Khusus 1', 200000),
('Khusus 2', 175000);

-- =====================================================
-- DISTRIBUTION RULES
-- =====================================================

-- PSB Distribution (percentage-based)
INSERT INTO distribution_rules (rule_name, source_type) VALUES ('Distribusi PSB', 'PSB');
SET @psb_rule = LAST_INSERT_ID();

INSERT INTO distribution_rule_details (rule_id, department_id, allocation_type, allocation_value, priority) VALUES
(@psb_rule, 22, 'PERCENTAGE', 43.7500, 1), -- Uang Gedung
(@psb_rule, 23, 'PERCENTAGE', 7.5000, 1),  -- Kartu Makan
(@psb_rule, 5, 'PERCENTAGE', 10.6300, 1),  -- Penjualan Seragam
(@psb_rule, 2, 'PERCENTAGE', 18.7500, 1),  -- Daftar Ulang
(@psb_rule, 1, 'PERCENTAGE', 7.1900, 1),   -- Syahriah
(@psb_rule, 18, 'PERCENTAGE', 0.6300, 1),  -- Madin
(@psb_rule, 8, 'PERCENTAGE', 3.7500, 1),   -- Administrasi
(@psb_rule, 16, 'PERCENTAGE', 1.5600, 1),  -- Organisasi
(@psb_rule, 9, 'PERCENTAGE', 6.2500, 1);   -- Perlengkapan

-- Daftar Ulang Distribution (percentage-based)
INSERT INTO distribution_rules (rule_name, source_type) VALUES ('Distribusi Daftar Ulang', 'DAFTAR_ULANG');
SET @du_rule = LAST_INSERT_ID();

INSERT INTO distribution_rule_details (rule_id, department_id, allocation_type, allocation_value, priority) VALUES
(@du_rule, 6, 'PERCENTAGE', 11.6700, 1),  -- Penjualan Kalender
(@du_rule, 19, 'PERCENTAGE', 25.0000, 1), -- Haflah
(@du_rule, 16, 'PERCENTAGE', 38.3300, 1), -- Organisasi
(@du_rule, 9, 'PERCENTAGE', 25.0000, 1);  -- Perlengkapan

-- Syahriah Distribution (fixed with priority)
INSERT INTO distribution_rules (rule_name, source_type) VALUES ('Distribusi Syahriah', 'SYAHRIAH');
SET @sy_rule = LAST_INSERT_ID();

INSERT INTO distribution_rule_details (rule_id, department_id, allocation_type, allocation_value, priority) VALUES
-- Priority 1
(@sy_rule, 17, 'FIXED', 59000000, 1),  -- Operasional Pondok
-- Priority 2
(@sy_rule, 21, 'FIXED', 3000000, 2),   -- Lain lain
-- Priority 3
(@sy_rule, 18, 'FIXED', 4500000, 3),   -- Madin
-- Priority 4
(@sy_rule, 8, 'FIXED', 1500000, 4),    -- Administrasi
(@sy_rule, 9, 'FIXED', 6500000, 4),    -- Perlengkapan
(@sy_rule, 10, 'FIXED', 1666667, 4),   -- Pendidikan
(@sy_rule, 11, 'FIXED', 1000000, 4),   -- Keamanan
(@sy_rule, 12, 'FIXED', 1000000, 4),   -- Media
(@sy_rule, 13, 'FIXED', 200000, 4),    -- Humas
-- Priority 5
(@sy_rule, 14, 'FIXED', 666667, 5),    -- Kesehatan
(@sy_rule, 15, 'FIXED', 2083333, 5),   -- Kebersihan
-- Priority 6
(@sy_rule, 16, 'FIXED', 4583333, 6),   -- Organisasi
-- Priority 7
(@sy_rule, 20, 'FIXED', 833333, 7);    -- BPH

-- =====================================================
-- DEFAULT ACADEMIC YEAR
-- =====================================================
INSERT INTO academic_years (year_name, hijri_year_start, hijri_month_start, hijri_year_end, hijri_month_end) VALUES
('1447/1448 H', 1447, 10, 1448, 9);

-- =====================================================
-- DEFAULT USERS (password for all: password)
-- =====================================================
INSERT INTO users (username, password_hash, full_name, role) VALUES
('admin', '$2y$10$QmCp.Ij7RGwONQZnmOAZse7SVK.3HTjnzefOGcHB7BCQm6riw0U6y', 'Administrator', 'ADMIN'),
('bendahara', '$2y$10$QmCp.Ij7RGwONQZnmOAZse7SVK.3HTjnzefOGcHB7BCQm6riw0U6y', 'Bendahara Pondok', 'TREASURER'),
('kasir', '$2y$10$QmCp.Ij7RGwONQZnmOAZse7SVK.3HTjnzefOGcHB7BCQm6riw0U6y', 'Kasir Pondok', 'CASHIER');

-- =====================================================
-- DEFAULT SETTINGS
-- =====================================================
INSERT INTO settings (setting_key, setting_value, description) VALUES
('pesantren_name', 'Pondok Pesantren Al-Hikmah', 'Nama Pesantren'),
('pesantren_address', 'Jl. Pesantren No. 1', 'Alamat Pesantren'),
('current_hijri_month', '10', 'Bulan Hijriah Aktif'),
('current_hijri_year', '1447', 'Tahun Hijriah Aktif'),
('psb_total_fee', '1600000', 'Total Biaya PSB'),
('daftar_ulang_fee', '300000', 'Total Biaya Daftar Ulang');
