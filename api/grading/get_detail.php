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
            $header['class_id'] = $header['grade_level_id'];
            
            $header['subject_name'] = $header['subject_name'] ?? '-';
            $header['mapel'] = $header['subject_name'];
            
            // Format date for convenience
            $header['formatted_date'] = date('d F Y', strtotime($header['assessment_date']));
            $header['tanggal'] = $header['formatted_date'];

            // 2. Get existing student scores for this assessment
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
            ";
            $stmt_details = $db->prepare($query_details);
            $stmt_details->bindParam(':assessment_id', $id, PDO::PARAM_INT);
            $stmt_details->execute();
            $existing_details = $stmt_details->fetchAll(PDO::FETCH_ASSOC);

            // Index existing scores by student_id
            $scores_by_student = [];
            foreach ($existing_details as $row) {
                $scores_by_student[$row['student_id']] = $row;
            }

            // 3. Get all active students enrolled in this class
            $class_id = (int)$header['grade_level_id'];
            $active_year_id = $db->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetchColumn();
            if (!$active_year_id) {
                $active_year_id = 1;
            }

            $query_students = "
                SELECT 
                    s.id as student_id, 
                    s.nama_siswa, 
                    s.nomor_induk,
                    s.nama_siswa as student_name,
                    s.nomor_induk as nis,
                    s.id as student_id_ref
                FROM students s
                JOIN student_class_history sch ON s.id = sch.student_id
                WHERE sch.class_id = :class_id 
                  AND sch.academic_year_id = :academic_year_id
                  AND sch.status = 'ACTIVE'
                  AND s.status = 'Aktif'
                ORDER BY s.nama_siswa ASC
            ";
            $stmt_students = $db->prepare($query_students);
            $stmt_students->bindParam(':class_id', $class_id, PDO::PARAM_INT);
            $stmt_students->bindParam(':academic_year_id', $active_year_id, PDO::PARAM_INT);
            $stmt_students->execute();
            $class_students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);

            // 4. Combine: class roster merged with existing scores
            $combined_list = [];
            $seen_student_ids = [];

            if (!empty($class_students)) {
                foreach ($class_students as $st) {
                    $sid = $st['student_id'];
                    $seen_student_ids[$sid] = true;

                    if (isset($scores_by_student[$sid])) {
                        $detail_item = $scores_by_student[$sid];
                        $score_val = ($detail_item['score'] !== null && $detail_item['score'] !== '') ? (float)$detail_item['score'] : null;
                    } else {
                        $detail_item = [];
                        $score_val = null;
                    }

                    $name = !empty($st['nama_siswa']) ? $st['nama_siswa'] : 'Siswa (ID: ' . $sid . ')';
                    $nis = !empty($st['nomor_induk']) ? $st['nomor_induk'] : '-';

                    $combined_list[] = array_merge($detail_item, [
                        'student_id' => (int)$sid,
                        'nama_siswa' => $name,
                        'student_name' => $name,
                        'name' => $name,
                        'nomor_induk' => $nis,
                        'nis' => $nis,
                        'score' => $score_val,
                    ]);
                }
            }

            // Also keep any students that have scores in assessment details but weren't in current class roster
            foreach ($existing_details as $row) {
                $sid = $row['student_id'];
                if (!isset($seen_student_ids[$sid])) {
                    $name = !empty($row['nama_siswa']) ? $row['nama_siswa'] : (!empty($row['student_name']) ? $row['student_name'] : 'Siswa (ID: ' . $sid . ')');
                    $nis = !empty($row['nomor_induk']) ? $row['nomor_induk'] : (!empty($row['nis']) ? $row['nis'] : '-');
                    $score_val = ($row['score'] !== null && $row['score'] !== '') ? (float)$row['score'] : null;

                    $combined_list[] = array_merge($row, [
                        'student_id' => (int)$sid,
                        'nama_siswa' => $name,
                        'student_name' => $name,
                        'name' => $name,
                        'nomor_induk' => $nis,
                        'nis' => $nis,
                        'score' => $score_val,
                    ]);
                }
            }

            // Sort by nama_siswa ascending
            usort($combined_list, function($a, $b) {
                return strcasecmp($a['nama_siswa'] ?? '', $b['nama_siswa'] ?? '');
            });

            $header['details'] = $combined_list;
            
            // Provide multiple keys for student count
            $total_count = count($combined_list);
            $graded_count = count(array_filter($combined_list, function($r) { return $r['score'] !== null; }));
            $header['student_count'] = $total_count;
            $header['total_siswa'] = $total_count;
            $header['total_siswa_count'] = $total_count;
            $header['total_students'] = $total_count;
            $header['count'] = $total_count;
            $header['graded_count'] = $graded_count;

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
