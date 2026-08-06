<?php
// logic/documents/save_routing_rules.php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$is_admin = isset($_SESSION['position_name']) && $_SESSION['position_name'] === 'Administrator';
if (!$is_admin && !hasPermission($_SESSION['user_id'], 'manage_documents')) {
    redirect("views/documents/index.php?error=Akses+ditolak");
}

$db = new Database();
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $division_id = !empty($_POST['division_id']) ? intval($_POST['division_id']) : 0;
        $unit_id = !empty($_POST['unit_id']) ? intval($_POST['unit_id']) : null;
        $employee_id = !empty($_POST['employee_id']) ? intval($_POST['employee_id']) : 0;
        $role_type = !empty($_POST['role_type']) && in_array($_POST['role_type'], ['handler', 'approver']) ? $_POST['role_type'] : 'handler';

        if (!$division_id || !$employee_id) {
            redirect("views/documents/routing_config.php?error=Bidang+dan+Pegawai+wajib+diisi");
        }

        try {
            // Check duplicate
            $stmtCheck = $conn->prepare("
                SELECT COUNT(*) FROM document_routing_rules 
                WHERE division_id = :div_id 
                  AND (unit_id = :unit_id OR (unit_id IS NULL AND :unit_id IS NULL)) 
                  AND employee_id = :emp_id 
                  AND role_type = :role
            ");
            $stmtCheck->execute([
                ':div_id' => $division_id,
                ':unit_id' => $unit_id,
                ':emp_id' => $employee_id,
                ':role' => $role_type
            ]);

            if ($stmtCheck->fetchColumn() > 0) {
                redirect("views/documents/routing_config.php?error=Pegawai+tersebut+sudah+terdaftar+pada+bidang/unit+ini");
            }

            $stmtAdd = $conn->prepare("
                INSERT INTO document_routing_rules (division_id, unit_id, employee_id, role_type)
                VALUES (:div_id, :unit_id, :emp_id, :role)
            ");
            $stmtAdd->execute([
                ':div_id' => $division_id,
                ':unit_id' => $unit_id,
                ':emp_id' => $employee_id,
                ':role' => $role_type
            ]);

            Logger::activity('Dokumen', 'ADD_ROUTING_RULE', 'Menambahkan penanggung jawab surat bidang/unit', ['employee_id' => $employee_id]);
            redirect("views/documents/routing_config.php?success=Penanggung+jawab+surat+berhasil+ditambahkan");
        } catch (Exception $e) {
            redirect("views/documents/routing_config.php?error=Gagal+menyimpan:+" . urlencode($e->getMessage()));
        }
    } elseif ($action === 'delete') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id > 0) {
            $stmtDel = $conn->prepare("DELETE FROM document_routing_rules WHERE id = ?");
            $stmtDel->execute([$id]);
            Logger::activity('Dokumen', 'DELETE_ROUTING_RULE', 'Menghapus penanggung jawab surat', ['id' => $id]);
            redirect("views/documents/routing_config.php?success=Penugasan+penerima+surat+berhasil+dihapus");
        }
    }
}

redirect("views/documents/routing_config.php");
?>
