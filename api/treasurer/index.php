<?php
/** Treasurer API */
Auth::requireAuth();
Auth::requireRole(['TREASURER', 'ADMIN']);

$segments = Router::getSegments();
$method = Router::getMethod();
$input = Router::getInput();
$query = Router::getQuery();

$action = $segments[1] ?? '';
$subAction = $segments[2] ?? null;

switch ($action) {
    case 'income':
        if ($method === 'POST') {
            if (empty($input['amount']) || empty($input['coa_credit']) || empty($input['description'])) {
                Response::error('amount, coa_credit, dan description wajib diisi');
            }
            $txnService = new TransactionService();
            $txnId = $txnService->recordIncome(
                $input['income_source'] ?? 'MANUAL',
                $input['coa_credit'],
                (float)$input['amount'],
                $input['description'],
                Auth::id()
            );
            $txnModel = new TransactionModel();
            Response::created($txnModel->findById($txnId), 'Pemasukan berhasil dicatat');
        }
        break;

    case 'expense':
        if ($method === 'POST') {
            if (empty($input['amount']) || empty($input['coa_debit']) || empty($input['description'])) {
                Response::error('amount, coa_debit, dan description wajib diisi');
            }
            $txnService = new TransactionService();
            $txnId = $txnService->recordExpense(
                $input['coa_debit'],
                (float)$input['amount'],
                $input['description'],
                Auth::id(),
                !empty($input['department_id']) ? (int)$input['department_id'] : null
            );
            $txnModel = new TransactionModel();
            Response::created($txnModel->findById($txnId), 'Pengeluaran berhasil dicatat');
        }
        break;

    case 'distribute':
        if ($method === 'POST') {
            $type = $input['source_type'] ?? '';
            $distService = new DistributionService();

            if ($type === 'SYAHRIAH') {
                $result = $distService->distributeSyahriah(Auth::id());
            } elseif ($type === 'PSB') {
                if (empty($input['amount'])) Response::error('Jumlah harus diisi untuk distribusi PSB');
                $result = $distService->distributePSB((float)$input['amount'], Auth::id());
            } elseif ($type === 'DAFTAR_ULANG') {
                if (empty($input['amount'])) Response::error('Jumlah harus diisi untuk distribusi Daftar Ulang');
                $result = $distService->distributeDaftarUlang((float)$input['amount'], Auth::id());
            } else {
                Response::error('source_type harus SYAHRIAH, PSB, atau DAFTAR_ULANG');
            }

            Response::success($result, 'Distribusi berhasil dijalankan');
        } elseif ($method === 'GET') {
            // Preview distribution
            $distService = new DistributionService();
            Response::success($distService->previewSyahriahDistribution());
        }
        break;

    case 'add-balance':
        // POST /api/treasurer/add-balance — manual transfer from kas utama to department
        if ($method === 'POST') {
            if (empty($input['department_id']) || empty($input['amount'])) {
                Response::error('department_id dan amount wajib diisi');
            }

            $amount = (float)$input['amount'];
            if ($amount <= 0) Response::error('Jumlah harus lebih dari 0');

            // Check kas utama has enough balance
            $ledgerModel = new LedgerModel();
            $kasBalance = $ledgerModel->getKasUtamaBalance();
            if ($kasBalance < $amount) {
                Response::error('Saldo Kas Utama tidak mencukupi. Saldo: Rp ' . number_format($kasBalance, 0, ',', '.'));
            }

            $txnService = new TransactionService();
            $txnId = $txnService->recordBalanceTransfer(
                (int)$input['department_id'],
                $amount,
                $input['description'] ?? '',
                Auth::id()
            );

            $txnModel = new TransactionModel();
            Response::created($txnModel->findById($txnId), 'Saldo bidang berhasil ditambahkan');
        }
        break;

    case 'reversal':
        if ($method === 'POST') {
            if (empty($input['transaction_id']) || empty($input['reason'])) {
                Response::error('transaction_id dan reason wajib diisi');
            }
            $txnService = new TransactionService();
            $reversalId = $txnService->reverseTransaction(
                (int)$input['transaction_id'],
                $input['reason'],
                Auth::id()
            );
            $txnModel = new TransactionModel();
            Response::created($txnModel->findById($reversalId), 'Transaksi berhasil direverse');
        }
        break;

    case 'transactions':
        $txnModel = new TransactionModel();
        if ($subAction && is_numeric($subAction)) {
            $txn = $txnModel->findById((int)$subAction);
            $txn ? Response::success($txn) : Response::error('Transaksi tidak ditemukan', 404);
        } else {
            $page = (int)($query['page'] ?? 1);
            $limit = (int)($query['limit'] ?? 50);
            $result = $txnModel->getTransactions($query, $page, $limit);
            Response::paginated($result['data'], $result['total'], $page, $limit);
        }
        break;

    case 'department-balances':
        $ledgerModel = new LedgerModel();
        $balances = $ledgerModel->getAllDepartmentBalances();
        $kasBalance = $ledgerModel->getKasUtamaBalance();
        Response::success([
            'kas_utama' => $kasBalance,
            'departments' => $balances
        ]);
        break;

    case 'distribution-history':
        $distModel = new DistributionModel();
        if ($subAction && is_numeric($subAction)) {
            $details = $distModel->getDistributionLogDetails((int)$subAction);
            Response::success($details);
        } else {
            Response::success($distModel->getDistributionHistory($query));
        }
        break;

    default:
        Response::error('Endpoint tidak ditemukan', 404);
}
