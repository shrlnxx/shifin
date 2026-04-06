<?php
/**
 * SHIFFIN - Billing Engine
 * Generates monthly Syahriah bills based on Hijri calendar
 */
class BillingService {
    private BillModel $billModel;
    private StudentModel $studentModel;
    private SettingModel $settingModel;

    public function __construct() {
        $this->billModel = new BillModel();
        $this->studentModel = new StudentModel();
        $this->settingModel = new SettingModel();
    }

    /**
     * Generate Syahriah bills for all active students up to the current Hijri period
     */
    public function generateSyahriahBills(int $targetHijriMonth, int $targetHijriYear): array {
        $students = $this->studentModel->getActiveStudents();
        $generated = [];

        foreach ($students as $student) {
            $bills = $this->generateStudentSyahriahBills(
                $student['student_id'],
                $student['entry_hijri_month'],
                $student['entry_hijri_year'],
                $student['monthly_fee'],
                $targetHijriMonth,
                $targetHijriYear,
                $student['academic_year_id']
            );
            if (!empty($bills)) {
                $generated[$student['student_id']] = $bills;
            }
        }

        return $generated;
    }

    /**
     * Generate Syahriah bills for a single student from entry month to target month
     */
    public function generateStudentSyahriahBills(
        int $studentId,
        int $entryHijriMonth,
        int $entryHijriYear,
        float $monthlyFee,
        int $targetHijriMonth,
        int $targetHijriYear,
        int $academicYearId
    ): array {
        if ($monthlyFee <= 0) return [];

        $generated = [];
        $currentMonth = $entryHijriMonth;
        $currentYear = $entryHijriYear;

        while ($this->isBeforeOrEqual($currentMonth, $currentYear, $targetHijriMonth, $targetHijriYear)) {
            // Skip if bill already exists
            if (!$this->billModel->exists($studentId, 'SYAHRIAH', $currentMonth, $currentYear)) {
                $billId = $this->billModel->create([
                    'student_id' => $studentId,
                    'payment_type' => 'SYAHRIAH',
                    'amount' => $monthlyFee,
                    'hijri_month' => $currentMonth,
                    'hijri_year' => $currentYear,
                    'academic_year_id' => $academicYearId,
                    'status' => 'UNPAID'
                ]);
                $generated[] = ['bill_id' => $billId, 'month' => $currentMonth, 'year' => $currentYear];
            }

            // Advance to next month
            $currentMonth++;
            if ($currentMonth > 12) {
                $currentMonth = 1;
                $currentYear++;
            }
        }

        return $generated;
    }

    /**
     * Generate PSB bill for a student
     */
    public function generatePSBBill(int $studentId, int $hijriMonth, int $hijriYear, int $academicYearId): ?int {
        $psbFee = (float) $this->settingModel->get('psb_total_fee');
        if ($psbFee <= 0) return null;

        if ($this->billModel->exists($studentId, 'PSB', $hijriMonth, $hijriYear)) {
            return null;
        }

        return $this->billModel->create([
            'student_id' => $studentId,
            'payment_type' => 'PSB',
            'amount' => $psbFee,
            'hijri_month' => $hijriMonth,
            'hijri_year' => $hijriYear,
            'academic_year_id' => $academicYearId,
            'status' => 'UNPAID'
        ]);
    }

    /**
     * Generate Daftar Ulang bill for a student
     */
    public function generateDaftarUlangBill(int $studentId, int $hijriMonth, int $hijriYear, int $academicYearId): ?int {
        $duFee = (float) $this->settingModel->get('daftar_ulang_fee');
        if ($duFee <= 0) return null;

        if ($this->billModel->exists($studentId, 'DAFTAR_ULANG', $hijriMonth, $hijriYear)) {
            return null;
        }

        return $this->billModel->create([
            'student_id' => $studentId,
            'payment_type' => 'DAFTAR_ULANG',
            'amount' => $duFee,
            'hijri_month' => $hijriMonth,
            'hijri_year' => $hijriYear,
            'academic_year_id' => $academicYearId,
            'status' => 'UNPAID'
        ]);
    }

    private function isBeforeOrEqual(int $m1, int $y1, int $m2, int $y2): bool {
        if ($y1 < $y2) return true;
        if ($y1 === $y2 && $m1 <= $m2) return true;
        return false;
    }
}
