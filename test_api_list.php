<?php
require 'config/PayrollDatabase.php';
require 'app/Payroll/Repositories/PayrollRepository.php';
require 'app/Payroll/Repositories/AttendanceRepository.php';
require 'app/Payroll/Services/PayrollService.php';

use App\Payroll\Repositories\PayrollRepository;
use App\Payroll\Repositories\AttendanceRepository;
use App\Payroll\Services\PayrollService;

$payrollDb = (new PayrollDatabase())->getConnection();
$attendanceDb = (new PayrollDatabase())->getConnection(); // Use same for test

$payrollRepo = new PayrollRepository($payrollDb);
$attendanceRepo = new AttendanceRepository($attendanceDb);
$payrollService = new PayrollService($payrollRepo, $attendanceRepo);

$list = $payrollService->getPayrollList(['limit' => 5, 'page' => 1]);
header('Content-Type: application/json');
echo json_encode($list, JSON_PRETTY_PRINT);
