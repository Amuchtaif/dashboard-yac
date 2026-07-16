<?php
// logic/students/update_status.php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'] ?? null;
    $status = $_POST['status'] ?? null;

    $allowed_statuses = ['Aktif', 'Non_aktif', 'Lulus', 'Pindah', 'Dikeluarkan'];

    if ($student_id && in_array($status, $allowed_statuses)) {
        try {
            $db = new Database();
            $conn = $db->getConnection();

            $active_year_id = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetchColumn();

            $is_admin = (isset($_SESSION['position_name']) && $_SESSION['position_name'] === 'Administrator');
            $user_stmt = $conn->prepare("
                SELECT e.unit_id, p.level, u.name as unit_name
                FROM employees e 
                LEFT JOIN positions p ON e.position_id = p.id 
                LEFT JOIN units u ON e.unit_id = u.id
                WHERE e.id = :user_id LIMIT 1
            ");
            $user_stmt->execute([':user_id' => $_SESSION['user_id']]);
            $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
            $user_level = $user_data ? (int)$user_data['level'] : 5;
            $user_unit_name = $user_data ? $user_data['unit_name'] : '';

            $mapped_education_unit_ids = [];
            if (!empty($user_unit_name)) {
                $clean_unit_name = str_replace(["'", " "], ["", ""], strtolower($user_unit_name));
                $edu_stmt = $conn->query("SELECT id, name FROM education_units");
                while ($edu_row = $edu_stmt->fetch(PDO::FETCH_ASSOC)) {
                    $clean_edu_name = str_replace(["'", " "], ["", ""], strtolower($edu_row['name']));
                    if (strpos($clean_unit_name, $clean_edu_name) !== false || strpos($clean_edu_name, $clean_unit_name) !== false) {
                        $mapped_education_unit_ids[] = (int)$edu_row['id'];
                    }
                }
            }

            if (!$is_admin && $user_level > 2 && !empty($mapped_education_unit_ids)) {
                // Verify target student belongs to user unit
                $student_unit_check = $conn->prepare("
                    SELECT gl.education_unit_id, s.tingkat 
                    FROM students s
                    LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = :active_year_id AND sch.status = 'ACTIVE'
                    LEFT JOIN grade_levels gl ON sch.class_id = gl.id
                    WHERE s.id = :id LIMIT 1
                ");
                $student_unit_check->execute([':id' => $student_id, ':active_year_id' => $active_year_id]);
                $student_unit_data = $student_unit_check->fetch(PDO::FETCH_ASSOC);
                
                $student_edu_unit_id = $student_unit_data ? $student_unit_data['education_unit_id'] : null;
                $student_tingkat = $student_unit_data ? $student_unit_data['tingkat'] : '';
                
                $edu_names_stmt = $conn->prepare("SELECT name FROM education_units WHERE id IN (" . implode(',', $mapped_education_unit_ids) . ")");
                $edu_names_stmt->execute();
                $edu_names = $edu_names_stmt->fetchAll(PDO::FETCH_COLUMN);
                
                $allowed = false;
                if ($student_edu_unit_id && in_array((int)$student_edu_unit_id, $mapped_education_unit_ids)) {
                    $allowed = true;
                } else if ($student_tingkat && in_array($student_tingkat, $edu_names)) {
                    $allowed = true;
                }
                
                if (!$allowed) {
                    $_SESSION['error_msg'] = "Akses ditolak: Siswa berada di luar unit kerja Anda.";
                    $referer = $_SERVER['HTTP_REFERER'] ?? '../../views/students/index.php';
                    header("Location: " . $referer);
                    exit();
                }
            }

            $stmt = $conn->prepare("UPDATE students SET status = :status WHERE id = :id");
            $result = $stmt->execute([
                ':status' => $status,
                ':id' => $student_id
            ]);

            if ($result) {
                $status_msg = str_replace('_', ' ', $status);
                $_SESSION['success_msg'] = "Status siswa berhasil diubah menjadi: " . ucwords($status_msg);
            } else {
                $_SESSION['error_msg'] = "Gagal memperbarui status siswa.";
            }

        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_msg'] = "Data tidak valid atau status tidak dikenali.";
    }
}

// Redirect back to referring page or index
$referer = $_SERVER['HTTP_REFERER'] ?? '../../views/students/index.php';
header("Location: " . $referer);
exit();
