<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/students/promotion.php');
}

$db = new Database();
$conn = $db->getConnection();

$action_type = $_POST['action_type'] ?? 'promote';
$student_ids = $_POST['student_ids'] ?? [];
$redirect_params = isset($_POST['redirect_params']) ? $_POST['redirect_params'] : '';
$redirect_qs = $redirect_params ? '&' . $redirect_params : '';

if (empty($student_ids)) {
    redirect('views/students/promotion.php?error=' . urlencode('Harap pilih minimal satu Siswa.') . $redirect_qs);
}

if ($action_type === 'promote') {
    $target_class_id = $_POST['target_class_id'] ?? '';
    $target_year_id = $_POST['target_year_id'] ?? '';

    if (empty($target_class_id) || empty($target_year_id)) {
        redirect('views/students/promotion.php?error=' . urlencode('Harap pilih Kelas Tujuan, Tahun Ajaran Tujuan, dan minimal satu Siswa.') . $redirect_qs);
    }

    try {
        // Fetch target class & year names
        $target_info_stmt = $conn->prepare("
            SELECT gl.name as class_name, ay.name as year_name, ay.semester 
            FROM grade_levels gl 
            LEFT JOIN academic_years ay ON ay.id = :year_id 
            WHERE gl.id = :class_id LIMIT 1
        ");
        $target_info_stmt->execute([':class_id' => $target_class_id, ':year_id' => $target_year_id]);
        $target_info = $target_info_stmt->fetch(PDO::FETCH_ASSOC);
        $target_class_name = $target_info['class_name'] ?? "ID $target_class_id";
        $target_year_name = $target_info ? ($target_info['year_name'] . ' ' . $target_info['semester']) : "ID $target_year_id";

        // Fetch student names and their previous active class names
        $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
        $stmt_students = $conn->prepare("
            SELECT 
                s.id, 
                s.nama_siswa, 
                gl.name as old_class_name
            FROM students s
            LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.status = 'ACTIVE'
            LEFT JOIN grade_levels gl ON sch.class_id = gl.id
            WHERE s.id IN ($placeholders)
        ");
        $stmt_students->execute(array_values($student_ids));
        $students_data = $stmt_students->fetchAll(PDO::FETCH_ASSOC);

        $conn->beginTransaction();

        // Prepare Insert Statement
        $sql = "INSERT INTO student_class_history (student_id, class_id, academic_year_id, status, joined_at) 
                VALUES (:student_id, :class_id, :academic_year_id, 'ACTIVE', NOW())
                ON DUPLICATE KEY UPDATE class_id = VALUES(class_id), status = 'ACTIVE'";

        $stmt = $conn->prepare($sql);

        $count = 0;
        foreach ($student_ids as $student_id) {
            $stmt->execute([
                ':student_id' => $student_id,
                ':class_id' => $target_class_id,
                ':academic_year_id' => $target_year_id
            ]);
            $count++;
        }

        $conn->commit();

        $student_details = [];
        $log_old_data = [];
        $log_new_data = [];

        foreach ($students_data as $s_item) {
            $s_name = $s_item['nama_siswa'];
            $old_c = $s_item['old_class_name'] ?: 'Tanpa Kelas';
            $student_details[] = "'$s_name' (dari $old_c ke $target_class_name)";
            $log_old_data[] = [
                'student_id' => $s_item['id'],
                'nama_siswa' => $s_name,
                'kelas_asal' => $old_c
            ];
            $log_new_data[] = [
                'student_id' => $s_item['id'],
                'nama_siswa' => $s_name,
                'kelas_tujuan' => $target_class_name,
                'tahun_ajaran' => $target_year_name
            ];
        }

        if (count($student_ids) === 1) {
            $log_desc = "Mutasi Kenaikan Kelas: Siswa " . $student_details[0];
        } else {
            $log_desc = "Mutasi Kenaikan Kelas ($count siswa) ke kelas '$target_class_name': " . implode(', ', $student_details);
        }

        Logger::activity(
            'Siswa',
            'MUTATION',
            $log_desc,
            [
                'table' => 'student_class_history',
                'old_data' => $log_old_data,
                'new_data' => $log_new_data
            ]
        );

        redirect('views/students/promotion.php?success=' . urlencode("Berhasil menaikkan kelas $count siswa.") . $redirect_qs);

    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        redirect('views/students/promotion.php?error=' . urlencode('Terjadi kesalahan database: ' . $e->getMessage()) . $redirect_qs);
    }
} elseif ($action_type === 'graduate') {
    $source_class_id = $_POST['source_class_id'] ?? '';
    $source_year_id = $_POST['source_year_id'] ?? '';
    $student_source_class = $_POST['student_source_class'] ?? [];
    $student_source_year = $_POST['student_source_year'] ?? [];

    // Validation per student
    foreach ($student_ids as $student_id) {
        $class_id = $student_source_class[$student_id] ?? $source_class_id;
        $year_id = $student_source_year[$student_id] ?? $source_year_id;
        if (empty($class_id) || empty($year_id)) {
            redirect('views/students/promotion.php?error=' . urlencode('Data kelas asal atau tahun ajaran asal tidak lengkap untuk sebagian siswa terpilih.') . $redirect_qs);
            exit;
        }
    }

    try {
        // Fetch student names and old class names
        $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
        $stmt_students = $conn->prepare("
            SELECT 
                s.id, 
                s.nama_siswa, 
                gl.name as old_class_name
            FROM students s
            LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.status = 'ACTIVE'
            LEFT JOIN grade_levels gl ON sch.class_id = gl.id
            WHERE s.id IN ($placeholders)
        ");
        $stmt_students->execute(array_values($student_ids));
        $students_data = $stmt_students->fetchAll(PDO::FETCH_ASSOC);

        $conn->beginTransaction();

        // Update student status to 'Lulus'
        $stmtStudent = $conn->prepare("UPDATE students SET status = 'Lulus' WHERE id = :student_id");

        // Update class history status to 'GRADUATED'
        $stmtHistory = $conn->prepare("UPDATE student_class_history 
                                       SET status = 'GRADUATED', left_at = NOW() 
                                       WHERE student_id = :student_id 
                                         AND class_id = :class_id 
                                         AND academic_year_id = :academic_year_id");

        $count = 0;
        foreach ($student_ids as $student_id) {
            $class_id = $student_source_class[$student_id] ?? $source_class_id;
            $year_id = $student_source_year[$student_id] ?? $source_year_id;

            // Update student status
            $stmtStudent->execute([':student_id' => $student_id]);

            // Update student class history
            $stmtHistory->execute([
                ':student_id' => $student_id,
                ':class_id' => $class_id,
                ':academic_year_id' => $year_id
            ]);
            
            $count++;
        }

        $conn->commit();

        $student_details = [];
        $log_old_data = [];
        $log_new_data = [];

        foreach ($students_data as $s_item) {
            $s_name = $s_item['nama_siswa'];
            $old_c = $s_item['old_class_name'] ?: 'Tanpa Kelas';
            $student_details[] = "'$s_name' (Kelas $old_c)";
            $log_old_data[] = [
                'student_id' => $s_item['id'],
                'nama_siswa' => $s_name,
                'status' => 'Aktif',
                'kelas' => $old_c
            ];
            $log_new_data[] = [
                'student_id' => $s_item['id'],
                'nama_siswa' => $s_name,
                'status' => 'Lulus'
            ];
        }

        if (count($student_ids) === 1) {
            $log_desc = "Kelulusan Siswa: " . $student_details[0];
        } else {
            $log_desc = "Kelulusan Siswa ($count siswa): " . implode(', ', $student_details);
        }

        Logger::activity(
            'Siswa',
            'MUTATION',
            $log_desc,
            [
                'table' => 'students',
                'old_data' => $log_old_data,
                'new_data' => $log_new_data
            ]
        );

        redirect('views/students/promotion.php?success=' . urlencode("Berhasil meluluskan $count siswa.") . $redirect_qs);

    } catch (Exception $e) {
        $conn->rollBack();
        redirect('views/students/promotion.php?error=' . urlencode('Terjadi kesalahan database: ' . $e->getMessage()) . $redirect_qs);
    }
} else {
    redirect('views/students/promotion.php?error=' . urlencode('Tindakan tidak valid.') . $redirect_qs);
}
