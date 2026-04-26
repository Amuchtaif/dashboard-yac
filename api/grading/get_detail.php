<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get ID from multiple possible sources (GET, POST, or JSON body)
$id = 0;
if (isset($_GET['id']) && $_GET['id'] !== '') {
    $id = (int)$_GET['id'];
} elseif (isset($_GET['assessment_id']) && $_GET['assessment_id'] !== '') {
    $id = (int)$_GET['assessment_id'];
} else {
    // Try to get from POST or JSON body
    $input = json_decode(file_get_contents("php://input"), true);
    if (isset($input['id'])) {
        $id = (int)$input['id'];
    } elseif (isset($input['assessment_id'])) {
        $id = (int)$input['assessment_id'];
    } elseif (isset($_POST['id'])) {
        $id = (int)$_POST['id'];
    } elseif (isset($_POST['assessment_id'])) {
        $id = (int)$_POST['assessment_id'];
    }
}

if ($id > 0) {
    try {
        // 1. Get header data
        $query = "
            SELECT 
                sa.*, 
                s.name as subject_name, 
                gl.name as class_name, 
                at.name as assessment_type_name,
                (SELECT COUNT(*) + 1 
                 FROM student_assessments sa2 
                 WHERE sa2.assessment_type_id = sa.assessment_type_id 
                   AND sa2.grade_level_id = sa.grade_level_id 
                   AND sa2.subject_id = sa.subject_id 
                   AND (sa2.assessment_date < sa.assessment_date 
                        OR (sa2.assessment_date = sa.assessment_date AND sa2.id < sa.id))
                ) as sequence_number,
                e.full_name as teacher_name,
                (SELECT COUNT(*) FROM student_assessment_details WHERE assessment_id = sa.id) as student_count
            FROM student_assessments sa
            JOIN subjects s ON sa.subject_id = s.id
            JOIN grade_levels gl ON sa.grade_level_id = gl.id
            JOIN assessment_types at ON sa.assessment_type_id = at.id
            LEFT JOIN employees e ON sa.teacher_id = e.id
            WHERE sa.id = :id
        ";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $header = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($header) {
            // Add aliases for header
            $header['teacher_name'] = $header['teacher_name'] ?? '-';
            $header['nama_guru'] = $header['teacher_name'];
            $header['guru'] = $header['teacher_name'];
            
            $header['class_name'] = $header['class_name'] ?? '-';
            $header['kelas'] = $header['class_name'];
            
            $header['subject_name'] = $header['subject_name'] ?? '-';
            $header['mapel'] = $header['subject_name'];
            
            // Format date for convenience
            $header['formatted_date'] = date('d F Y', strtotime($header['assessment_date']));
            $header['tanggal'] = $header['formatted_date'];

            // 2. Get student details
            $query_details = "
                SELECT 
                    sad.*, 
                    st.nama_siswa, 
                    st.nomor_induk,
                    st.nama_siswa as student_name,
                    st.nomor_induk as nis,
                    st.id as student_id_ref
                FROM student_assessment_details sad
                LEFT JOIN students st ON sad.student_id = st.id
                WHERE sad.assessment_id = :assessment_id
                ORDER BY CASE WHEN st.nama_siswa IS NULL THEN 1 ELSE 0 END, st.nama_siswa ASC
            ";
            $stmt_details = $db->prepare($query_details);
            $stmt_details->bindParam(':assessment_id', $id, PDO::PARAM_INT);
            $stmt_details->execute();
            $details = $stmt_details->fetchAll(PDO::FETCH_ASSOC);
            
            // Map details to ensure no nulls and provide more aliases
            $mapped_details = array_map(function($row) {
                $name = !empty($row['nama_siswa']) ? $row['nama_siswa'] : (!empty($row['student_name']) ? $row['student_name'] : 'Siswa (ID: '.$row['student_id'].')');
                $nis = !empty($row['nomor_induk']) ? $row['nomor_induk'] : (!empty($row['nis']) ? $row['nis'] : '-');
                
                return array_merge($row, [
                    'nama_siswa' => $name,
                    'student_name' => $name,
                    'name' => $name,
                    'nomor_induk' => $nis,
                    'nis' => $nis,
                    'score' => (float)$row['score']
                ]);
            }, $details);

            $header['details'] = $mapped_details;
            
            // Provide multiple keys for student count
            $count = count($mapped_details);
            $header['student_count'] = $count;
            $header['total_siswa'] = $count;
            $header['total_siswa_count'] = $count;
            $header['total_students'] = $count;
            $header['count'] = $count;

            echo json_encode(["success" => true, "data" => $header]);
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Data penilaian tidak ditemukan (ID: $id)"]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode([
        "success" => false, 
        "message" => "ID tidak valid atau parameter 'id' / 'assessment_id' tidak ditemukan.",
        "received_id" => isset($_GET['id']) ? $_GET['id'] : (isset($_GET['assessment_id']) ? $_GET['assessment_id'] : 'missing')
    ]);
}
?>
