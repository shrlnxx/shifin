<?php
/** Cashier API */
Auth::requireAuth();
Auth::requireRole(['CASHIER', 'ADMIN']);

$segments = Router::getSegments();
$method = Router::getMethod();
$input = Router::getInput();
$query = Router::getQuery();

$action = $segments[1] ?? '';
$subAction = $segments[2] ?? null;
$param = $segments[3] ?? null;

switch ($action) {
    case 'search':
        // GET /api/cashier/search?q=...
        $q = $query['q'] ?? '';
        if (strlen($q) < 2) Response::error('Minimal 2 karakter untuk pencarian');
        $model = new StudentModel();
        Response::success($model->search($q));
        break;

    case 'students':
        $studentModel = new StudentModel();
        if ($subAction === 'bills' || $param === 'bills') {
            // GET /api/cashier/students/{id}/bills
            $studentId = is_numeric($subAction) ? (int)$subAction : (int)$param;
            if ($subAction !== 'bills') $studentId = (int)$subAction;
            
            $billModel = new BillModel();
            $type = $query['type'] ?? null;
            $bills = $billModel->getStudentBills($studentId, $type);
            
            $student = $studentModel->findById($studentId);
            Response::success([
                'student' => $student,
                'bills' => $bills,
                'summary' => [
                    'total_outstanding' => array_sum(array_map(function($b) {
                        return $b['status'] !== 'PAID' ? ($b['amount'] - $b['paid_amount']) : 0;
                    }, $bills))
                ]
            ]);
        } elseif (is_numeric($subAction)) {
            $student = $studentModel->findById((int)$subAction);
            $student ? Response::success($student) : Response::error('Santri tidak ditemukan', 404);
        }
        break;

    case 'payments':
        if ($method === 'POST') {
            // Record payment
            if (empty($input['student_id']) || empty($input['payment_type'])) {
                Response::error('student_id dan payment_type wajib diisi');
            }

            // For SYAHRIAH, bill_ids is required; for others, amount is required
            if ($input['payment_type'] === 'SYAHRIAH' && empty($input['bill_ids'])) {
                Response::error('Pilih minimal 1 bulan tagihan untuk pembayaran Syahriah');
            }
            if ($input['payment_type'] !== 'SYAHRIAH' && empty($input['amount'])) {
                Response::error('Jumlah pembayaran wajib diisi');
            }

            $settingModel = new SettingModel();
            $hijriMonth = (int)($input['hijri_month'] ?? $settingModel->get('current_hijri_month'));
            $hijriYear = (int)($input['hijri_year'] ?? $settingModel->get('current_hijri_year'));

            $paymentService = new PaymentService();
            $result = $paymentService->processPayment(
                (int)$input['student_id'],
                $input['payment_type'],
                (float)($input['amount'] ?? 0),
                Auth::id(),
                $hijriMonth,
                $hijriYear,
                $input['notes'] ?? null,
                $input['payment_date'] ?? null,
                $input['bill_ids'] ?? null
            );

            // Auto-distribute for PSB and Daftar Ulang
            if ($input['payment_type'] === 'PSB') {
                $distService = new DistributionService();
                $distResult = $distService->distributePSB((float)($input['amount'] ?? $result['total_paid']), Auth::id());
                $result['distribution'] = $distResult;
            } elseif ($input['payment_type'] === 'DAFTAR_ULANG') {
                $distService = new DistributionService();
                $distResult = $distService->distributeDaftarUlang((float)($input['amount'] ?? $result['total_paid']), Auth::id());
                $result['distribution'] = $distResult;
            }

            Response::created($result, 'Pembayaran berhasil dicatat');
        } elseif ($method === 'GET' && $subAction) {
            if ($param === 'receipt' || $subAction === 'receipt') {
                $paymentId = is_numeric($subAction) ? (int)$subAction : (int)$param;
                $paymentService = new PaymentService();
                Response::success($paymentService->getReceipt($paymentId));
            } else {
                $paymentModel = new PaymentModel();
                $payment = $paymentModel->findById((int)$subAction);
                $payment ? Response::success($payment) : Response::error('Pembayaran tidak ditemukan', 404);
            }
        } elseif ($method === 'GET') {
            // Payment history
            $paymentModel = new PaymentModel();
            if (!empty($query['student_id'])) {
                Response::success($paymentModel->getStudentPayments((int)$query['student_id'], $query['type'] ?? null));
            } else {
                Response::success($paymentModel->getPaymentReport($query));
            }
        }
        break;

    case 'receipt':
        // GET /api/cashier/receipt/{id}
        if ($subAction) {
            $paymentService = new PaymentService();
            Response::success($paymentService->getReceipt((int)$subAction));
        }
        break;

    default:
        Response::error('Endpoint tidak ditemukan', 404);
}
