<?php
/** Master Data API - Students, Categories, Academic Years, COA, Departments, Users, Settings, Distribution Rules */
Auth::requireAuth();

$segments = Router::getSegments();
$method = Router::getMethod();
$input = Router::getInput();
$query = Router::getQuery();

// /api/master/{resource}/{id?}/{sub?}
$resource = $segments[1] ?? '';
$id = $segments[2] ?? null;
$sub = $segments[3] ?? null;

switch ($resource) {
    case 'students':
        Auth::requireRole(['ADMIN', 'TREASURER']);
        $model = new StudentModel();

        // Handle CSV import: POST /api/master/students/import
        if ($id === 'import' && $method === 'POST') {
            if (empty($_FILES['csv_file'])) {
                Response::error('File CSV wajib diupload');
            }

            $file = $_FILES['csv_file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                Response::error('Error upload file: ' . $file['error']);
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext !== 'csv') {
                Response::error('File harus berformat CSV');
            }

            $handle = fopen($file['tmp_name'], 'r');
            if (!$handle) Response::error('Gagal membaca file CSV');

            // Read header row
            $header = fgetcsv($handle, 0, ',');
            if (!$header) {
                // Try semicolon delimiter
                rewind($handle);
                $header = fgetcsv($handle, 0, ';');
            }
            if (!$header) Response::error('Format header CSV tidak valid');

            // Normalize headers
            $header = array_map(function($h) {
                return strtolower(trim(str_replace([' ', '-'], '_', $h)));
            }, $header);

            // Required columns
            $requiredCols = ['nis', 'name'];
            foreach ($requiredCols as $col) {
                if (!in_array($col, $header)) {
                    Response::error("Kolom '{$col}' wajib ada di CSV. Header ditemukan: " . implode(', ', $header));
                }
            }

            // Get defaults
            $settingModel = new SettingModel();
            $defaultCatId = 1; // First category as default
            $defaultAyId = 1;
            $defaultHijriMonth = (int)$settingModel->get('current_hijri_month');
            $defaultHijriYear = (int)$settingModel->get('current_hijri_year');

            // Get active academic year
            $ayModel = new AcademicYearModel();
            $activeAy = $ayModel->getActive();
            if ($activeAy) $defaultAyId = $activeAy['academic_year_id'];

            $students = [];
            $lineNum = 1;
            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                $lineNum++;
                if (count($row) === 1 && strpos($row[0], ';') !== false) {
                    $row = str_getcsv($row[0], ';');
                }
                if (count($row) < count($header)) continue;

                $data = array_combine($header, array_slice($row, 0, count($header)));
                
                $students[] = [
                    'nis' => trim($data['nis'] ?? ''),
                    'name' => trim($data['name'] ?? ''),
                    'student_type' => trim($data['student_type'] ?? $data['tipe'] ?? 'Mukim'),
                    'syahriah_category_id' => (int)($data['syahriah_category_id'] ?? $data['category_id'] ?? $data['kategori'] ?? $defaultCatId),
                    'entry_date' => trim($data['entry_date'] ?? $data['tanggal_masuk'] ?? date('Y-m-d')),
                    'entry_hijri_month' => (int)($data['entry_hijri_month'] ?? $data['bulan_hijri'] ?? $defaultHijriMonth),
                    'entry_hijri_year' => (int)($data['entry_hijri_year'] ?? $data['tahun_hijri'] ?? $defaultHijriYear),
                    'academic_year_id' => (int)($data['academic_year_id'] ?? $data['tahun_ajaran'] ?? $defaultAyId),
                    'status' => 'ACTIVE'
                ];
            }
            fclose($handle);

            if (empty($students)) Response::error('Tidak ada data santri yang valid di file CSV');

            $results = $model->bulkCreate($students);

            // Also generate bills for successfully created students
            $billingService = new BillingService();
            $currentMonth = (int)$settingModel->get('current_hijri_month');
            $currentYear = (int)$settingModel->get('current_hijri_year');

            foreach ($results as &$result) {
                if ($result['status'] === 'success' && !empty($result['student_id'])) {
                    $student = $model->findById($result['student_id']);
                    if ($student) {
                        try {
                            $billingService->generatePSBBill($result['student_id'], $student['entry_hijri_month'], $student['entry_hijri_year'], $student['academic_year_id']);
                            $billingService->generateDaftarUlangBill($result['student_id'], $student['entry_hijri_month'], $student['entry_hijri_year'], $student['academic_year_id']);
                            if ($student['monthly_fee'] > 0) {
                                $billingService->generateStudentSyahriahBills(
                                    $result['student_id'], $student['entry_hijri_month'], $student['entry_hijri_year'],
                                    (float)$student['monthly_fee'], $currentMonth, $currentYear, $student['academic_year_id']
                                );
                            }
                        } catch (Exception $e) {
                            $result['bill_error'] = $e->getMessage();
                        }
                    }
                }
            }

            $successCount = count(array_filter($results, fn($r) => $r['status'] === 'success'));
            $errorCount = count($results) - $successCount;
            Response::success($results, "Import selesai: {$successCount} berhasil, {$errorCount} gagal");
        }

        if ($method === 'GET' && $id && is_numeric($id)) {
            $student = $model->findById((int)$id);
            $student ? Response::success($student) : Response::error('Santri tidak ditemukan', 404);
        } elseif ($method === 'GET') {
            $page = (int)($query['page'] ?? 1);
            $limit = (int)($query['limit'] ?? 20);
            $result = $model->findAll($query, $page, $limit);
            Response::paginated($result['data'], $result['total'], $page, $limit);
        } elseif ($method === 'POST') {
            if (empty($input['nis']) || empty($input['name'])) {
                Response::error('NIS dan nama wajib diisi');
            }
            // Auto-generate bills on student creation
            $studentId = $model->create([
                'nis' => $input['nis'],
                'name' => $input['name'],
                'student_type' => $input['student_type'] ?? 'Mukim',
                'syahriah_category_id' => (int)$input['syahriah_category_id'],
                'entry_date' => $input['entry_date'] ?? date('Y-m-d'),
                'entry_hijri_month' => (int)$input['entry_hijri_month'],
                'entry_hijri_year' => (int)$input['entry_hijri_year'],
                'academic_year_id' => (int)$input['academic_year_id'],
                'status' => 'ACTIVE'
            ]);

            // Generate initial bills
            $billingService = new BillingService();
            $settingModel = new SettingModel();
            $currentMonth = (int) $settingModel->get('current_hijri_month');
            $currentYear = (int) $settingModel->get('current_hijri_year');

            $billingService->generatePSBBill($studentId, (int)$input['entry_hijri_month'], (int)$input['entry_hijri_year'], (int)$input['academic_year_id']);
            $billingService->generateDaftarUlangBill($studentId, (int)$input['entry_hijri_month'], (int)$input['entry_hijri_year'], (int)$input['academic_year_id']);

            $category = (new CategoryModel())->findById((int)$input['syahriah_category_id']);
            if ($category) {
                $billingService->generateStudentSyahriahBills(
                    $studentId,
                    (int)$input['entry_hijri_month'],
                    (int)$input['entry_hijri_year'],
                    (float)$category['monthly_fee'],
                    $currentMonth,
                    $currentYear,
                    (int)$input['academic_year_id']
                );
            }

            Response::created($model->findById($studentId), 'Santri berhasil ditambahkan');
        } elseif ($method === 'PUT' && $id) {
            $model->update((int)$id, $input);
            Response::success($model->findById((int)$id), 'Santri berhasil diupdate');
        } elseif ($method === 'DELETE' && $id) {
            $student = $model->findById((int)$id);
            if (!$student) Response::error('Santri tidak ditemukan', 404);
            $model->delete((int)$id);
            Response::success(null, 'Santri berhasil dihapus (status: DROPPED)');
        }
        break;

    case 'categories':
        Auth::requireRole(['ADMIN']);
        $model = new CategoryModel();
        if ($method === 'GET') {
            Response::success($model->findAll());
        } elseif ($method === 'POST') {
            $catId = $model->create([
                'category_name' => $input['category_name'],
                'monthly_fee' => (float)$input['monthly_fee'],
                'is_active' => 1
            ]);
            Response::created($model->findById($catId));
        } elseif ($method === 'PUT' && $id) {
            $model->update((int)$id, $input);
            Response::success($model->findById((int)$id), 'Kategori berhasil diupdate');
        }
        break;

    case 'academic-years':
        Auth::requireRole(['ADMIN']);
        $model = new AcademicYearModel();
        if ($method === 'GET') {
            Response::success($model->findAll());
        } elseif ($method === 'POST') {
            $ayId = $model->create($input);
            Response::created($model->findById($ayId));
        } elseif ($method === 'PUT' && $id) {
            $model->update((int)$id, $input);
            Response::success($model->findById((int)$id), 'Tahun ajaran berhasil diupdate');
        }
        break;

    case 'coa':
        Auth::requireRole(['ADMIN', 'TREASURER']);
        $model = new COAModel();
        if ($method === 'GET') {
            Response::success($model->findAll());
        } elseif ($method === 'POST') {
            $model->create($input);
            Response::created($model->findById($input['coa_id']));
        } elseif ($method === 'PUT' && $id) {
            $model->update($id, $input);
            Response::success($model->findById($id), 'COA berhasil diupdate');
        }
        break;

    case 'departments':
        Auth::requireRole(['ADMIN', 'TREASURER']);
        $model = new DepartmentModel();
        if ($method === 'GET') {
            Response::success($model->findAll());
        } elseif ($method === 'POST') {
            $deptId = $model->create($input);
            Response::created($model->findById($deptId));
        } elseif ($method === 'PUT' && $id) {
            $model->update((int)$id, $input);
            Response::success($model->findById((int)$id), 'Bidang berhasil diupdate');
        }
        break;

    case 'distribution-rules':
        Auth::requireRole(['ADMIN']);
        $model = new DistributionModel();
        if ($method === 'GET' && $id) {
            $details = $model->getRuleDetails((int)$id);
            Response::success($details);
        } elseif ($method === 'GET') {
            $type = $query['source_type'] ?? null;
            Response::success($model->getRules($type));
        } elseif ($method === 'PUT' && $id) {
            $model->updateRuleDetail((int)$id, $input);
            Response::success(null, 'Aturan distribusi berhasil diupdate');
        }
        break;

    case 'users':
        Auth::requireRole(['ADMIN']);
        $model = new UserModel();
        if ($method === 'GET') {
            Response::success($model->findAll());
        } elseif ($method === 'POST') {
            if (empty($input['username']) || empty($input['password'])) {
                Response::error('Username dan password wajib diisi');
            }
            $userId = $model->create($input);
            Response::created($model->findById($userId));
        } elseif ($method === 'PUT' && $id) {
            $model->update((int)$id, $input);
            Response::success($model->findById((int)$id), 'User berhasil diupdate');
        }
        break;

    case 'settings':
        Auth::requireRole(['ADMIN']);
        $model = new SettingModel();
        if ($method === 'GET') {
            Response::success($model->getAll());
        } elseif ($method === 'PUT') {
            foreach ($input as $key => $value) {
                $model->set($key, $value);
            }
            Response::success(null, 'Pengaturan berhasil disimpan');
        }
        break;

    case 'hijri-months':
        $model = new UserModel();
        Response::success($model->getHijriMonths());
        break;

    case 'generate-bills':
        Auth::requireRole(['ADMIN']);
        $billingService = new BillingService();
        $targetMonth = (int)($input['hijri_month'] ?? $query['hijri_month'] ?? 0);
        $targetYear = (int)($input['hijri_year'] ?? $query['hijri_year'] ?? 0);
        if (!$targetMonth || !$targetYear) {
            $settingModel = new SettingModel();
            $targetMonth = (int) $settingModel->get('current_hijri_month');
            $targetYear = (int) $settingModel->get('current_hijri_year');
        }
        $result = $billingService->generateSyahriahBills($targetMonth, $targetYear);
        $count = array_sum(array_map('count', $result));
        Response::success(['generated' => $count, 'details' => $result], "Berhasil generate {$count} tagihan");
        break;

    default:
        Response::error('Resource tidak ditemukan', 404);
}
