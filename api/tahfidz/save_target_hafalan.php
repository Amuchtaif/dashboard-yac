<?php
// api/tahfidz/save_target_hafalan.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));

if (
    $data && 
    !empty($data->tahun_ajaran_id) && 
    !empty($data->unit_id) && 
    !empty($data->kelas_id) && 
    isset($data->target_juz)
) {
    try {
        $id = !empty($data->id) ? intval($data->id) : null;
        $tahun_ajaran_id = intval($data->tahun_ajaran_id);
        $unit_id = intval($data->unit_id);
        $kelas_id = intval($data->kelas_id);
        $target_juz = floatval($data->target_juz);
        $status_aktif = !empty($data->status_aktif) ? $data->status_aktif : 'Aktif';
        $keterangan = isset($data->keterangan) ? trim($data->keterangan) : null;

        // Program MTs only applies to MTs (unit_id = 5)
        $program_id = ($unit_id == 5 && !empty($data->program_id)) ? $data->program_id : null;

        // Uniqueness validation check
        if ($program_id === null) {
            $check_query = "SELECT id FROM target_hafalan WHERE tahun_ajaran_id = :ta_id AND unit_id = :u_id AND program_id IS NULL AND kelas_id = :k_id";
            if ($id !== null) {
                $check_query .= " AND id != :id";
            }
            $check_stmt = $db->prepare($check_query);
        } else {
            $check_query = "SELECT id FROM target_hafalan WHERE tahun_ajaran_id = :ta_id AND unit_id = :u_id AND program_id = :p_id AND kelas_id = :k_id";
            if ($id !== null) {
                $check_query .= " AND id != :id";
            }
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':p_id', $program_id);
        }

        $check_stmt->bindParam(':ta_id', $tahun_ajaran_id);
        $check_stmt->bindParam(':u_id', $unit_id);
        $check_stmt->bindParam(':k_id', $kelas_id);
        if ($id !== null) {
            $check_stmt->bindParam(':id', $id);
        }
        
        $check_stmt->execute();
        if ($check_stmt->fetch(PDO::FETCH_ASSOC)) {
            http_response_code(400);
            echo json_encode([
                "success" => false, 
                "message" => "Gagal: Target hafalan untuk kombinasi Tahun Ajaran, Unit, Program, dan Kelas tersebut sudah terdaftar."
            ]);
            exit();
        }

        if ($id !== null) {
            // Update
            $query = "UPDATE target_hafalan 
                      SET tahun_ajaran_id = :ta_id, 
                          unit_id = :u_id, 
                          program_id = :p_id, 
                          kelas_id = :k_id, 
                          target_juz = :target_juz, 
                          status_aktif = :status_aktif, 
                          keterangan = :keterangan
                      WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
        } else {
            // Insert
            $query = "INSERT INTO target_hafalan 
                      (tahun_ajaran_id, unit_id, program_id, kelas_id, target_juz, status_aktif, keterangan) 
                      VALUES (:ta_id, :u_id, :p_id, :k_id, :target_juz, :status_aktif, :keterangan)";
            $stmt = $db->prepare($query);
        }

        $stmt->bindParam(':ta_id', $tahun_ajaran_id);
        $stmt->bindParam(':u_id', $unit_id);
        
        // Bind program_id (PDO requires passing by reference or bindValue, so bindValue is safer for null)
        if ($program_id === null) {
            $stmt->bindValue(':p_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(':p_id', $program_id);
        }

        $stmt->bindParam(':k_id', $kelas_id);
        $stmt->bindParam(':target_juz', $target_juz);
        $stmt->bindParam(':status_aktif', $status_aktif);
        
        if ($keterangan === null) {
            $stmt->bindValue(':keterangan', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(':keterangan', $keterangan);
        }

        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Target hafalan berhasil disimpan."]);
        } else {
            echo json_encode(["success" => false, "message" => "Gagal menyimpan target hafalan."]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error database: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Data tidak lengkap. Semua field bertanda bintang wajib diisi."]);
}
?>
