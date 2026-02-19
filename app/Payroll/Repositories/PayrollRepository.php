<?php
namespace App\Payroll\Repositories;

use PDO;

class PayrollRepository
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function create($data)
    {
        $query = "INSERT INTO payrolls 
                  (id_payroll, tanggal, gaji_bulan, nik, nama, jabatan, 
                   gapok, tunjab, lembur, gaji_bruto, pph21, bpjs_kes, 
                   bpjs_tk, jumlah_potongan, gaji_netto) 
                  VALUES 
                  (:id_payroll, :tanggal, :gaji_bulan, :nik, :nama, :jabatan, 
                   :gapok, :tunjab, :lembur, :gaji_bruto, :pph21, :bpjs_kes, 
                   :bpjs_tk, :jumlah_potongan, :gaji_netto)";

        $stmt = $this->db->prepare($query);
        
        // Map data from repository format to table format
        $mappedData = [
            'id_payroll' => $data['id_payroll'],
            'tanggal' => date('Y-m-d'),
            'gaji_bulan' => "- " . $this->getMonthName((int)$data['periode_bulan']) . " " . $data['periode_tahun'],
            'nik' => $data['nik_snapshot'],
            'nama' => $data['name_snapshot'],
            'jabatan' => $data['position_snapshot'],
            'gapok' => $data['gaji_pokok'],
            'tunjab' => $data['tunjangan_jabatan'],
            'lembur' => $data['lembur'],
            'gaji_bruto' => $data['gaji_bruto'],
            'pph21' => $data['pajak_pph21'],
            'bpjs_kes' => $data['bpjs_kesehatan'],
            'bpjs_tk' => $data['bpjs_ketenagakerjaan'],
            'jumlah_potongan' => $data['jumlah_potongan'],
            'gaji_netto' => $data['gaji_netto']
        ];

        return $stmt->execute($mappedData);
    }

    private function getMonthName($month)
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        return $months[$month] ?? '';
    }

    public function list($filters)
    {
        $limit = $filters['limit'] ?? 10;
        $offset = ($filters['page'] - 1) * $limit;
        
        $query = "SELECT * FROM payrolls WHERE 1=1";
        $params = [];
        
        if (isset($filters['nik']) && $filters['nik'] !== null) {
            $query .= " AND nik = :nik";
            $params['nik'] = $filters['nik'];
        }
        
        // Filter by Month and Year using gaji_bulan or tanggal
        if (isset($filters['bulan']) && $filters['bulan'] !== null && isset($filters['tahun']) && $filters['tahun'] !== null) {
            $monthName = $this->getMonthName((int)$filters['bulan']);
            $year = $filters['tahun'];
            $query .= " AND (gaji_bulan LIKE :period OR (MONTH(tanggal) = :m AND YEAR(tanggal) = :y))";
            $params['period'] = "%$monthName $year%";
            $params['m'] = (int)$filters['bulan'];
            $params['y'] = (int)$filters['tahun'];
        }
        
        $query .= " ORDER BY id DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($query);
        foreach ($params as $key => $val) {
            if ($key === 'm' || $key === 'y') {
                $stmt->bindValue(":$key", $val, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(":$key", $val);
            }
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = $stmt->fetchAll();
        return array_map([$this, 'mapToRepositoryFormat'], $results);
    }

    private function mapToRepositoryFormat($row)
    {
        // Default to date from 'tanggal'
        $bulan = date('m', strtotime($row['tanggal']));
        $tahun = date('Y', strtotime($row['tanggal']));

        // Try to extract from gaji_bulan (Format: "- September 2025")
        if (preg_match('/(Januari|Februari|Maret|April|Mei|Juni|Juli|Agustus|September|Oktober|November|Desember)\s+(\d{4})/i', $row['gaji_bulan'], $matches)) {
            $monthMap = [
                'Januari' => '01', 'Februari' => '02', 'Maret' => '03', 'April' => '04',
                'Mei' => '05', 'Juni' => '06', 'Juli' => '07', 'Agustus' => '08',
                'September' => '09', 'Oktober' => '10', 'November' => '11', 'Desember' => '12'
            ];
            $bulan = $monthMap[ucfirst(strtolower($matches[1]))] ?? $bulan;
            $tahun = $matches[2];
        }

        return [
            'id_payroll' => $row['id_payroll'],
            'tanggal' => $row['tanggal'],
            'gaji_bulan' => $row['gaji_bulan'],
            'periode_bulan' => $bulan,
            'periode_tahun' => $tahun,
            'nik' => $row['nik'],
            'nama' => $row['nama'],
            'sta_peg' => $row['sta_peg'],
            'masker' => $row['masker'],
            'gol_r' => $row['gol_r'],
            'gapok' => (float)$row['gapok'],
            'tunjab' => (float)$row['tunjab'],
            'tunkel' => (float)$row['tunkel'],
            'tunnak' => (float)$row['tunnak'],
            'kjm' => (float)$row['kjm'],
            'kjk' => (float)$row['kjk'],
            'tunkus' => (float)$row['tunkus'],
            'ikm' => (float)$row['ikm'],
            'lembur' => (float)$row['lembur'],
            'bpjs_tk_jht_ip' => (float)$row['bpjs_tk_jht_ip'],
            'bpjs_keshtn' => (float)$row['bpjs_keshtn'],
            'tunj_pph21' => (float)$row['tunj_pph21'],
            'gaji_bruto' => (float)$row['gaji_bruto'],
            'hutang_yac' => (float)$row['hutang_yac'],
            'pend_anak' => (float)$row['pend_anak'],
            'paket' => (float)$row['paket'],
            'pph21' => (float)$row['pph21'],
            'infak' => (float)$row['infak'],
            'donasi_radio_ap' => (float)$row['donasi_radio_ap'],
            'bpjs_tk' => (float)$row['bpjs_tk'],
            'jht_ip' => (float)$row['jht_ip'],
            'bpjs_kes' => (float)$row['bpjs_kes'],
            'kredit_brg' => (float)$row['kredit_brg'],
            'belanja' => (float)$row['belanja'],
            'hutang_kop' => (float)$row['hutang_kop'],
            'simp_wajib' => (float)$row['simp_wajib'],
            'simp_pokok' => (float)$row['simp_pokok'],
            'simp_sukarela' => (float)$row['simp_sukarela'],
            'jumlah_potongan' => (float)$row['jumlah_potongan'],
            'gaji_netto' => (float)$row['gaji_netto'],
            'tgl_lahir' => $row['tgl_lahir'],
            'jabatan' => $row['jabatan'],
            'martial' => $row['martial'],
            'pendidikan' => $row['pendidikan'],
            'jml_tunjangan' => (float)$row['jml_tunjangan'],
            'saldo_hutang_yayasan' => (float)$row['saldo_hutang_yayasan'],
            'hut_assunnah_mart' => (float)$row['hut_assunnah_mart'],
            'saldo_murabahah' => (float)$row['saldo_murabahah'],
            'saldo_pendidikan_anak' => (float)$row['saldo_pendidikan_anak'],
            'saldo_qordul_hasan' => (float)$row['saldo_qordul_hasan'],
            'jml_hari' => (int)$row['jml_hari'],
            'status' => 'TERBAYAR',
            'created_at' => $row['created_at']
        ];
    }

    public function getById($id_payroll)
    {
        $query = "SELECT * FROM payrolls WHERE id_payroll = :id LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['id' => $id_payroll]);
        $row = $stmt->fetch();
        return $row ? $this->mapToRepositoryFormat($row) : null;
    }
}
