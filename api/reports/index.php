<?php
/** Reports API */
Auth::requireAuth();
Auth::requireRole(['TREASURER', 'ADMIN', 'CASHIER']);

$segments = Router::getSegments();
$query = Router::getQuery();

$report = $segments[1] ?? '';

$txnModel = new TransactionModel();
$paymentModel = new PaymentModel();
$billModel = new BillModel();
$ledgerModel = new LedgerModel();
$distModel = new DistributionModel();

// Check if this is an Excel export request
$exportExcel = !empty($query['export']) && $query['export'] === 'excel';

switch ($report) {
    case 'student-payments':
        Response::success($paymentModel->getPaymentReport($query));
        break;

    case 'outstanding-bills':
        Response::success($billModel->getOutstandingReport($query));
        break;

    case 'outstanding-monthly-summary':
        $data = $billModel->getOutstandingMonthlySummary();
        if ($exportExcel) {
            exportCSV('tunggakan_bulanan', ['Bulan Hijri', 'Tahun Hijri', 'Nama Bulan', 'Jenis', 'Jml Tagihan', 'Total Nominal', 'Total Dibayar', 'Total Tunggakan'], $data, ['hijri_month', 'hijri_year', 'hijri_month_name', 'payment_type', 'bill_count', 'total_amount', 'total_paid', 'total_outstanding']);
        }
        Response::success($data);
        break;

    case 'monthly-income':
        $dateFrom = $query['date_from'] ?? date('Y-m-01');
        $dateTo = $query['date_to'] ?? date('Y-m-t');
        $deptId = !empty($query['department_id']) ? (int)$query['department_id'] : null;
        $data = $txnModel->getIncomeByPeriod($dateFrom, $dateTo, $deptId);
        if ($exportExcel) {
            if ($deptId) {
                exportCSV('pemasukan_detail', ['ID', 'Tanggal', 'Jumlah', 'Keterangan', 'Kode Akun', 'Nama Akun', 'Bidang'], $data, ['transaction_id', 'transaction_date', 'amount', 'description', 'coa_id', 'coa_name', 'department_name']);
            } else {
                exportCSV('pemasukan', ['Kode Akun', 'Nama Akun', 'Total'], $data, ['coa_id', 'coa_name', 'total']);
            }
        }
        Response::success($data);
        break;

    case 'monthly-expense':
        $dateFrom = $query['date_from'] ?? date('Y-m-01');
        $dateTo = $query['date_to'] ?? date('Y-m-t');
        $deptId = !empty($query['department_id']) ? (int)$query['department_id'] : null;
        $data = $txnModel->getExpenseByPeriod($dateFrom, $dateTo, $deptId);
        if ($exportExcel) {
            if ($deptId) {
                exportCSV('pengeluaran_detail', ['ID', 'Tanggal', 'Jumlah', 'Keterangan', 'Kode Akun', 'Nama Akun', 'Bidang'], $data, ['transaction_id', 'transaction_date', 'amount', 'description', 'coa_id', 'coa_name', 'department_name']);
            } else {
                exportCSV('pengeluaran', ['Kode Akun', 'Nama Akun', 'Total'], $data, ['coa_id', 'coa_name', 'total']);
            }
        }
        Response::success($data);
        break;

    case 'general-ledger':
        if (empty($query['coa_id'])) Response::error('coa_id wajib diisi');
        $dateFrom = $query['date_from'] ?? date('Y-m-01');
        $dateTo = $query['date_to'] ?? date('Y-m-t');
        Response::success($txnModel->getGeneralLedger($query['coa_id'], $dateFrom, $dateTo));
        break;

    case 'general-journal':
        $dateFrom = $query['date_from'] ?? date('Y-m-01');
        $dateTo = $query['date_to'] ?? date('Y-m-t');
        $data = $txnModel->getGeneralJournal($dateFrom, $dateTo);
        if ($exportExcel) {
            exportCSV('jurnal_umum', ['ID', 'Tanggal', 'Tipe', 'Akun Debit', 'Akun Kredit', 'Jumlah', 'Keterangan'], $data, ['transaction_id', 'transaction_date', 'transaction_type', 'debit_account_name', 'credit_account_name', 'amount', 'description']);
        }
        Response::success($data);
        break;

    case 'distribution-history':
        Response::success($distModel->getDistributionHistory($query));
        break;

    case 'financial-summary':
        $dateFrom = $query['date_from'] ?? date('Y-m-01');
        $dateTo = $query['date_to'] ?? date('Y-m-t');
        Response::success($txnModel->getFinancialSummary($dateFrom, $dateTo));
        break;

    case 'department-ledger':
        if (empty($query['department_id'])) Response::error('department_id wajib diisi');
        $dateFrom = $query['date_from'] ?? null;
        $dateTo = $query['date_to'] ?? null;
        Response::success($ledgerModel->getDepartmentLedger((int)$query['department_id'], $dateFrom, $dateTo));
        break;

    case 'department-balances':
        $data = $ledgerModel->getAllDepartmentBalances();
        $kasBalance = $ledgerModel->getKasUtamaBalance();
        if ($exportExcel) {
            exportCSV('saldo_bidang', ['Bidang', 'Tipe', 'Saldo'], $data, ['department_name', 'department_type', 'balance']);
        }
        Response::success([
            'kas_utama' => $kasBalance,
            'departments' => $data
        ]);
        break;

    case 'dashboard':
        Auth::requireRole(['TREASURER', 'ADMIN', 'CASHIER']);
        $settingModel = new SettingModel();
        $dateFrom = date('Y-m-01');
        $dateTo = date('Y-m-t');
        
        $summary = $txnModel->getFinancialSummary($dateFrom, $dateTo);
        $recentPayments = $paymentModel->getRecentPayments(5);
        $outstandingSyahriah = $billModel->getTotalOutstanding('SYAHRIAH');
        $outstandingPSB = $billModel->getTotalOutstanding('PSB');
        $deptBalances = $ledgerModel->getAllDepartmentBalances();

        Response::success([
            'financial_summary' => $summary,
            'recent_payments' => $recentPayments,
            'outstanding' => [
                'syahriah' => $outstandingSyahriah,
                'psb' => $outstandingPSB,
                'total' => $outstandingSyahriah + $outstandingPSB
            ],
            'department_balances' => $deptBalances,
            'current_period' => [
                'hijri_month' => $settingModel->get('current_hijri_month'),
                'hijri_year' => $settingModel->get('current_hijri_year')
            ]
        ]);
        break;

    default:
        Response::error('Laporan tidak ditemukan', 404);
}

/**
 * Export data as CSV for Excel download
 */
function exportCSV(string $filename, array $headers, array $data, array $keys): void {
    ob_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.csv"');
    
    // BOM for UTF-8 Excel compatibility
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    fputcsv($output, $headers, ';');
    
    foreach ($data as $row) {
        $csvRow = [];
        foreach ($keys as $key) {
            $csvRow[] = $row[$key] ?? '';
        }
        fputcsv($output, $csvRow, ';');
    }
    
    fclose($output);
    exit;
}
