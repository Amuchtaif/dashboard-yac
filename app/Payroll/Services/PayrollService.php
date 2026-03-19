<?php
namespace App\Payroll\Services;

class PayrollService
{
    private $payrollRepo;
    private $attendanceRepo;

    public function __construct($payrollRepo, $attendanceRepo)
    {
        $this->payrollRepo = $payrollRepo;
        $this->attendanceRepo = $attendanceRepo;
    }

    public function generatePayrollId()
    {
        $period = date('Ym');
        $lastId = $this->payrollRepo->getLastIdByPeriod($period);
        
        if ($lastId) {
            $parts = explode('-', $lastId);
            $sequence = (int)end($parts) + 1;
        } else {
            $sequence = 1;
        }

        return "PR-$period-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    public function processNewPayroll($data)
    {
        // 1. Ambil Snapshot data Karyawan
        $employee = $this->attendanceRepo->getEmployeeWithPosition($data['user_id']);
        if (!$employee) {
            throw new \Exception("Karyawan tidak ditemukan");
        }

        // 2. Set Default Values & Hitung-hitungan
        $gaji_pokok = (float)($data['gaji_pokok'] ?? 0);
        $tunjangan_jabatan = (float)($data['tunjangan_jabatan'] ?? 0);
        $bonus_performa = (float)($data['bonus_performa'] ?? 0);
        $lembur = (float)($data['lembur'] ?? 0);

        $pajak_pph21 = (float)($data['pajak_pph21'] ?? 0);
        $bpjs_kesehatan = (float)($data['bpjs_kesehatan'] ?? 0);
        $bpjs_ketenagakerjaan = (float)($data['bpjs_ketenagakerjaan'] ?? 0);
        $potongan_kehadiran = (float)($data['potongan_kehadiran'] ?? 0);

        $gaji_bruto = $gaji_pokok + $tunjangan_jabatan + $bonus_performa + $lembur;
        $jumlah_potongan = $pajak_pph21 + $bpjs_kesehatan + $bpjs_ketenagakerjaan + $potongan_kehadiran;
        $gaji_netto = $gaji_bruto - $jumlah_potongan;

        // 3. Siapkan Array Data Final (Sesuai kolom di tabel payrolls)
        $finalData = [
            'id_payroll' => $this->generatePayrollId(),
            'user_id' => $data['user_id'],
            'nik_snapshot' => $employee['nik'],
            'name_snapshot' => $employee['full_name'],
            'position_snapshot' => $employee['position_name'],
            'periode_bulan' => $data['periode_bulan'] ?? date('m'),
            'periode_tahun' => $data['periode_tahun'] ?? date('Y'),
            'gaji_pokok' => $gaji_pokok,
            'tunjangan_jabatan' => $tunjangan_jabatan,
            'bonus_performa' => $bonus_performa,
            'lembur' => $lembur,
            'gaji_bruto' => $gaji_bruto,
            'pajak_pph21' => $pajak_pph21,
            'bpjs_kesehatan' => $bpjs_kesehatan,
            'bpjs_ketenagakerjaan' => $bpjs_ketenagakerjaan,
            'potongan_kehadiran' => $potongan_kehadiran,
            'jumlah_potongan' => $jumlah_potongan,
            'gaji_netto' => $gaji_netto,
            'is_thr' => $data['is_thr'] ?? false
        ];

        $id = $this->payrollRepo->create($finalData);
        if ($id) {
            $finalData['id'] = (int)$id;
            return $finalData;
        }
        return false;
    }

    public function getPayrollList($filters)
    {
        if (isset($filters['user_id'])) {
            $employee = $this->attendanceRepo->getEmployeeWithPosition($filters['user_id']);
            if ($employee) {
                $filters['nik'] = $employee['nik'];
            }
        }
        return $this->payrollRepo->list($filters);
    }

    public function getPayrollDetail($id_payroll)
    {
        return $this->payrollRepo->getById($id_payroll);
    }
}
