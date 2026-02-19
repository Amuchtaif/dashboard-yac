<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Setup paths
$baseDir = dirname(__DIR__);
require_once $baseDir . '/config/database.php';
require_once $baseDir . '/config/PayrollDatabase.php';
require_once $baseDir . '/app/Payroll/Repositories/PayrollRepository.php';
require_once $baseDir . '/app/Payroll/Repositories/AttendanceRepository.php';
require_once $baseDir . '/app/Payroll/Services/PayrollService.php';
require_once $baseDir . '/app/Payroll/Controllers/PayrollController.php';

use App\Payroll\Repositories\PayrollRepository;
use App\Payroll\Repositories\AttendanceRepository;
use App\Payroll\Services\PayrollService;
use App\Payroll\Controllers\PayrollController;

try {
    // 1. Setup Connections
    $attendanceDb = (new Database())->getConnection();
    $payrollDb = (new PayrollDatabase())->getConnection();

    if (!$attendanceDb || !$payrollDb) {
        throw new Exception("Database connection failed", 500);
    }

    // 2. Instantiate Layers
    $payrollRepo = new PayrollRepository($payrollDb);
    $attendanceRepo = new AttendanceRepository($attendanceDb);
    $payrollService = new PayrollService($payrollRepo, $attendanceRepo);
    $controller = new PayrollController($payrollService);

    // 3. Handle Request
    $controller->handleRequest();

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        "status" => false, 
        "message" => "Server Error: " . $e->getMessage()
    ]);
}
?>
