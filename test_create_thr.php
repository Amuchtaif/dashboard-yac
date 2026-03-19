<?php
require 'config/database.php';
require 'config/PayrollDatabase.php';
require 'app/Payroll/Repositories/PayrollRepository.php';
require 'app/Payroll/Repositories/AttendanceRepository.php';
require 'app/Payroll/Services/PayrollService.php';

use App\Payroll\Repositories\PayrollRepository;
use App\Payroll\Repositories\AttendanceRepository;
use App\Payroll\Services\PayrollService;

try {
    $payrollDb = (new PayrollDatabase())->getConnection();
    $attendanceDb = (new Database())->getConnection();

    $payrollRepo = new PayrollRepository($payrollDb);
    $attendanceRepo = new AttendanceRepository($attendanceDb);
    $payrollService = new PayrollService($payrollRepo, $attendanceRepo);

    $testData = [
        'user_id' => 1, // Ensure this exists in attendance_db.employees
        'gaji_pokok' => 1000000,
        'periode_tahun' => '2025',
        'is_thr' => true
    ];

    $result = $payrollService->processNewPayroll($testData);
    echo "RESULT: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
