<?php
// api/tahfidz/dashboard_pimpinan.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../app/Services/Tahfidz/DashboardTahfidzService.php';

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Parameter user_id is required."]);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : 'executive_summary';

// Global filters mapping
$filters = [
    'unit' => isset($_GET['unit']) && $_GET['unit'] !== '' ? $_GET['unit'] : null,
    'kelas' => isset($_GET['kelas']) && $_GET['kelas'] !== '' ? $_GET['kelas'] : null,
    'halaqah_id' => isset($_GET['halaqah_id']) && $_GET['halaqah_id'] !== '' ? (int)$_GET['halaqah_id'] : null,
    'pengampu_id' => isset($_GET['pengampu_id']) && $_GET['pengampu_id'] !== '' ? (int)$_GET['pengampu_id'] : null,
    'date' => isset($_GET['date']) && $_GET['date'] !== '' ? $_GET['date'] : null,
    'search' => isset($_GET['search']) && $_GET['search'] !== '' ? $_GET['search'] : null,
];

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

$service = new DashboardTahfidzService();

try {
    $result_data = null;
    
    switch ($action) {
        case 'executive_summary':
            $result_data = $service->getExecutiveSummary($user_id, $filters);
            break;
            
        case 'attendance':
            $result_data = $service->getAttendanceDashboard($user_id, $filters);
            break;
            
        case 'live_activity':
            $result_data = $service->getLiveActivity($user_id, $filters, $limit, $page);
            break;
            
        case 'progress_hafalan':
            $result_data = $service->getProgressHafalan($user_id, $filters);
            break;
            
        case 'distribusi_hafalan':
            $result_data = $service->getDistribusiHafalan($user_id, $filters);
            break;
            
        case 'monitoring_halaqoh':
            $result_data = $service->getMonitoringHalaqoh($user_id, $filters, $limit, $page);
            break;
            
        case 'detail_halaqoh':
            $halaqah_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($halaqah_id <= 0) {
                throw new Exception("Halaqah ID is required for detail_halaqoh action.");
            }
            $result_data = $service->getDetailHalaqoh($user_id, $halaqah_id, $filters);
            break;
            
        case 'monitoring_pengampu':
            $result_data = $service->getMonitoringPengampu($user_id, $filters);
            break;
            
        case 'detail_pengampu':
            $teacher_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($teacher_id <= 0) {
                throw new Exception("Teacher ID is required for detail_pengampu action.");
            }
            $result_data = $service->getDetailPengampu($user_id, $teacher_id, $filters);
            break;
            
        case 'monitoring_santri':
            $result_data = $service->getMonitoringSantri($user_id, $filters, $limit, $page);
            break;
            
        case 'detail_santri':
            $student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($student_id <= 0) {
                throw new Exception("Student ID is required for detail_santri action.");
            }
            $result_data = $service->getDetailSantri($user_id, $student_id);
            break;
            
        case 'attention_needed':
            $result_data = $service->getSantriAttentionNeeded($user_id, $filters);
            break;
            
        case 'historical':
            $period = isset($_GET['period']) ? $_GET['period'] : 'month';
            $result_data = $service->getHistoricalStats($user_id, $period, $filters);
            break;
            
        case 'executive_insight':
            $result_data = $service->getExecutiveInsight($user_id, $filters);
            break;
            
        case 'compare_units':
            $result_data = $service->getCompareUnits($user_id, $filters);
            break;
            
        case 'ranking':
            $type = isset($_GET['type']) ? $_GET['type'] : 'halaqah';
            $metric = isset($_GET['metric']) ? $_GET['metric'] : 'progress';
            $result_data = $service->getRanking($user_id, $type, $metric, $filters);
            break;
            
        case 'health_score':
            $result_data = $service->getHealthScore($user_id, $filters);
            break;
            
        case 'drill_down':
            $level = isset($_GET['level']) ? $_GET['level'] : 'unit';
            $parent_id = isset($_GET['parent_id']) ? $_GET['parent_id'] : null;
            $result_data = $service->getDrillDown($user_id, $level, $parent_id, $filters);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Invalid action parameter."]);
            exit;
    }
    
    echo json_encode([
        "success" => true,
        "action" => $action,
        "data" => $result_data
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
