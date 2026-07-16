<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if (isset($_GET['id'])) {
    $id = $_GET['id'];

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
            $student_unit_check->execute([':id' => $id, ':active_year_id' => $active_year_id]);
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
                header("Location: ../../views/students/index.php?error=Akses+ditolak+Siswa+di+luar+unit+kerja+Anda");
                exit();
            }
        }

        $stmt = $conn->prepare("DELETE FROM students WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        header("Location: ../../views/students/index.php?success=Siswa+berhasil+dihapus");
    } catch (PDOException $e) {
        header("Location: ../../views/students/index.php?error=Gagal+menghapus+siswa");
    }
}
