<?php
// api/mobile/student_activities.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../app/Services/Activity/StudentActivityService.php';

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0);
if ($user_id <= 0) {
    $input = json_decode(file_get_contents("php://input"), true);
    $user_id = isset($input['user_id']) ? (int)$input['user_id'] : 0;
}

if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "User ID wajib diisi."]);
    exit;
}

$service = new StudentActivityService();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id > 0) {
                $res = $service->getActivity($id);
                if (!$res) {
                    http_response_code(404);
                    echo json_encode(["success" => false, "message" => "Aktivitas tidak ditemukan."]);
                } else {
                    echo json_encode(["success" => true, "data" => $res]);
                }
            } else {
                $filters = [
                    'student_id' => isset($_GET['student_id']) ? (int)$_GET['student_id'] : null,
                    'activity_type_id' => isset($_GET['activity_type_id']) ? (int)$_GET['activity_type_id'] : null,
                    'status' => isset($_GET['status']) ? $_GET['status'] : null,
                    'start_date' => isset($_GET['activity_date']) ? $_GET['activity_date'] : (isset($_GET['start_date']) ? $_GET['start_date'] : null),
                    'end_date' => isset($_GET['activity_date']) ? $_GET['activity_date'] : (isset($_GET['end_date']) ? $_GET['end_date'] : null),
                    'created_by' => $user_id
                ];
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
                $res = $service->listActivities($filters, $page, $limit);
                echo json_encode(array_merge(["success" => true], $res));
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents("php://input"), true) ?? $_POST;
            $action = isset($_GET['action']) ? $_GET['action'] : '';
            
            if ($action === 'batch') {
                $activity_type_id = isset($input['activity_type_id']) ? (int)$input['activity_type_id'] : 0;
                $activity_date = isset($input['activity_date']) ? trim($input['activity_date']) : '';
                $status = isset($input['status']) ? trim($input['status']) : 'completed';
                $student_ids = isset($input['student_ids']) ? $input['student_ids'] : [];
                $note = isset($input['note']) ? trim($input['note']) : null;
                $start_time = !empty($input['start_time']) ? trim($input['start_time']) : null;
                $end_time = !empty($input['end_time']) ? trim($input['end_time']) : null;

                if ($activity_type_id <= 0) throw new Exception("Jenis aktivitas wajib diisi.");
                if (empty($activity_date)) throw new Exception("Tanggal wajib diisi.");
                if (empty($student_ids)) throw new Exception("Daftar siswa wajib diisi.");

                $students_in_scope = $service->getStudentsForUser($user_id);
                $scope_ids = array_map(function($s) { return (int)$s['id']; }, $students_in_scope);

                foreach ($student_ids as $sid) {
                    if (!in_array((int)$sid, $scope_ids)) {
                        http_response_code(403);
                        echo json_encode(["success" => false, "message" => "Forbidden: Santri ID $sid tidak berada dalam cakupan tugas Anda."]);
                        exit;
                    }
                }

                $inserted_ids = [];
                foreach ($student_ids as $student_id) {
                    $item = [
                        'activity_type_id' => $activity_type_id,
                        'student_id' => (int)$student_id,
                        'activity_date' => $activity_date,
                        'status' => $status,
                        'note' => $note,
                        'start_time' => $start_time,
                        'end_time' => $end_time
                    ];
                    $inserted_ids[] = $service->createActivity($item, $user_id);
                }

                http_response_code(201);
                echo json_encode(["success" => true, "message" => "Batch aktivitas berhasil dicatat.", "ids" => $inserted_ids]);
            } else {
                $student_id = isset($input['student_id']) ? (int)$input['student_id'] : 0;
                $students_in_scope = $service->getStudentsForUser($user_id);
                $scope_ids = array_map(function($s) { return (int)$s['id']; }, $students_in_scope);
                
                if (!in_array($student_id, $scope_ids)) {
                    http_response_code(403);
                    echo json_encode(["success" => false, "message" => "Forbidden: Santri tidak berada dalam cakupan tugas Anda."]);
                    exit;
                }

                $id = $service->createActivity($input, $user_id);
                http_response_code(201);
                echo json_encode(["success" => true, "message" => "Aktivitas berhasil dicatat.", "id" => $id]);
            }
            break;

        case 'PUT':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID wajib diisi."]);
                break;
            }
            $input = json_decode(file_get_contents("php://input"), true) ?? $_POST;
            
            if (isset($input['student_id'])) {
                $student_id = (int)$input['student_id'];
                $students_in_scope = $service->getStudentsForUser($user_id);
                $scope_ids = array_map(function($s) { return (int)$s['id']; }, $students_in_scope);
                
                if (!in_array($student_id, $scope_ids)) {
                    http_response_code(403);
                    echo json_encode(["success" => false, "message" => "Forbidden: Santri tidak berada dalam cakupan tugas Anda."]);
                    exit;
                }
            }

            $service->updateActivity($id, $input, $user_id);
            echo json_encode(["success" => true, "message" => "Aktivitas berhasil diperbarui."]);
            break;

        case 'DELETE':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID wajib diisi."]);
                break;
            }
            $service->deleteActivity($id);
            echo json_encode(["success" => true, "message" => "Aktivitas berhasil dihapus."]);
            break;

        default:
            http_response_code(405);
            echo json_encode(["success" => false, "message" => "Method not allowed."]);
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
