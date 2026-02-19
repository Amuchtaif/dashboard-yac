<?php
namespace App\Payroll\Controllers;

class PayrollController
{
    private $payrollService;

    public function __construct($payrollService)
    {
        $this->payrollService = $payrollService;
    }

    public function handleRequest()
    {
        $method = $_SERVER['REQUEST_METHOD'];

        try {
            switch ($method) {
                case 'POST':
                    $this->createPayroll();
                    break;
                case 'GET':
                    if (isset($_GET['id'])) {
                        $this->getDetail();
                    } else {
                        $this->getList();
                    }
                    break;
                default:
                    $this->response(405, ["success" => false, "message" => "Method Not Allowed"]);
                    break;
            }
        } catch (\Exception $e) {
            $this->response(500, ["success" => false, "message" => $e->getMessage()]);
        }
    }

    private function createPayroll()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (!$data || !isset($data['user_id'])) {
            $this->response(400, ["success" => false, "message" => "User ID wajib diisi"]);
            return;
        }

        $result = $this->payrollService->processNewPayroll($data);
        if ($result) {
            $this->response(201, ["success" => true, "message" => "Payroll created successfully", "data" => $result]);
        } else {
            $this->response(500, ["success" => false, "message" => "Gagal membuat payroll"]);
        }
    }

    private function getList()
    {
        $bulan = $_GET['bulan'] ?? null;
        $tahun = $_GET['tahun'] ?? null;
        $user_id = $_GET['user_id'] ?? null;
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 10);

        $filters = [
            'bulan' => $bulan,
            'tahun' => $tahun,
            'page' => $page,
            'limit' => $limit
        ];

        if ($user_id) $filters['user_id'] = $user_id;

        $list = $this->payrollService->getPayrollList($filters);
        $this->response(200, ["success" => true, "data" => $list]);
    }

    private function getDetail()
    {
        if (!isset($_GET['id'])) {
            $this->response(400, ["success" => false, "message" => "ID is required"]);
            return;
        }
        
        $id = $_GET['id'];
        $detail = $this->payrollService->getPayrollDetail($id);
        
        if ($detail) {
            $this->response(200, ["success" => true, "data" => $detail]);
        } else {
            $this->response(404, ["success" => false, "message" => "Payroll tidak ditemukan"]);
        }
    }

    private function response($code, $data)
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
