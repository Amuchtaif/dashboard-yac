<?php
// logic/documents/manage_template.php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();
check_permission('document.template.manage');

$db = new Database();
$conn = $db->getConnection();

$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create' || $action === 'update') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $name = trim($_POST['name']);
        $code = strtoupper(trim($_POST['code']));
        $content_template = $_POST['content_template'];
        $number_format = trim($_POST['number_format']);
        $reset_cycle = $_POST['reset_cycle'];
        
        // Build workflow stages JSON from post parameters
        // Example structure: [{"step": 1, "position_id": 3, "position_name": "Kepala Sekolah"}]
        $workflow_input = isset($_POST['workflow_stages']) ? $_POST['workflow_stages'] : [];
        $workflow_stages = [];
        $step = 1;
        foreach ($workflow_input as $pos_id) {
            $pos_id = intval($pos_id);
            if ($pos_id > 0) {
                // Fetch position name
                $stmt = $conn->prepare("SELECT name FROM positions WHERE id = ?");
                $stmt->execute([$pos_id]);
                $pos_name = $stmt->fetchColumn();
                if ($pos_name) {
                    $workflow_stages[] = [
                        'step' => $step++,
                        'position_id' => $pos_id,
                        'position_name' => $pos_name
                    ];
                }
            }
        }
        $workflow_stages_json = json_encode($workflow_stages);

        $header_line_1 = isset($_POST['header_line_1']) ? trim($_POST['header_line_1']) : 'YAYASAN AS SUNNAH CIREBON';
        $header_line_2 = isset($_POST['header_line_2']) ? trim($_POST['header_line_2']) : 'BIDANG PENDIDIKAN';
        $header_address = isset($_POST['header_address']) ? trim($_POST['header_address']) : 'Jl. Kalitanjung No.52B Kel. Karyamulya Kec. Kesambi Kota Cirebon 45135';

        // Fetch existing template if updating
        $existing_logo = 'uploads/kop_logos/logo_yac.png';
        $existing_image = null;
        if ($id > 0) {
            $stmtEx = $conn->prepare("SELECT header_logo, header_image FROM document_templates WHERE id = ?");
            $stmtEx->execute([$id]);
            $exData = $stmtEx->fetch(PDO::FETCH_ASSOC);
            if ($exData) {
                $existing_logo = $exData['header_logo'] ?: $existing_logo;
                $existing_image = $exData['header_image'];
            }
        }

        // Handle header logo upload
        $header_logo = $existing_logo;
        if (isset($_FILES['header_logo']) && $_FILES['header_logo']['error'] === UPLOAD_ERR_OK) {
            $fileTmp = $_FILES['header_logo']['tmp_name'];
            $fileName = $_FILES['header_logo']['name'];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
            
            if (in_array($fileExt, $allowedExts)) {
                $uploadDir = BASE_PATH . '/uploads/kop_logos/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $newFileName = 'logo_' . time() . '_' . rand(100, 999) . '.' . $fileExt;
                $targetFile = $uploadDir . $newFileName;
                if (move_uploaded_file($fileTmp, $targetFile)) {
                    $header_logo = 'uploads/kop_logos/' . $newFileName;
                }
            }
        }

        // Handle full header image upload
        $header_image = $existing_image;
        if (isset($_FILES['header_image']) && $_FILES['header_image']['error'] === UPLOAD_ERR_OK) {
            $fileTmp = $_FILES['header_image']['tmp_name'];
            $fileName = $_FILES['header_image']['name'];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($fileExt, $allowedExts)) {
                $uploadDir = BASE_PATH . '/uploads/kop_headers/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $newFileName = 'header_' . time() . '_' . rand(100, 999) . '.' . $fileExt;
                $targetFile = $uploadDir . $newFileName;
                if (move_uploaded_file($fileTmp, $targetFile)) {
                    $header_image = 'uploads/kop_headers/' . $newFileName;
                }
            }
        }

        if (empty($name) || empty($code) || empty($content_template)) {
            redirect("views/documents/template_config.php?error=Semua+field+wajib+diisi");
        }

        try {
            if ($action === 'create') {
                $stmt = $conn->prepare("INSERT INTO document_templates (name, code, content_template, number_format, reset_cycle, workflow_stages, header_logo, header_line_1, header_line_2, header_address, header_image) 
                                        VALUES (:name, :code, :content_template, :number_format, :reset_cycle, :workflow_stages, :header_logo, :header_line_1, :header_line_2, :header_address, :header_image)");
                $stmt->execute([
                    ':name' => $name,
                    ':code' => $code,
                    ':content_template' => $content_template,
                    ':number_format' => $number_format,
                    ':reset_cycle' => $reset_cycle,
                    ':workflow_stages' => $workflow_stages_json,
                    ':header_logo' => $header_logo,
                    ':header_line_1' => $header_line_1,
                    ':header_line_2' => $header_line_2,
                    ':header_address' => $header_address,
                    ':header_image' => $header_image
                ]);
                Logger::activity('Dokumen', 'CREATE_TEMPLATE', 'Membuat template surat baru: ' . $name, ['code' => $code]);
                redirect("views/documents/template_config.php?success=Template+berhasil+dibuat");
            } else {
                $stmt = $conn->prepare("UPDATE document_templates 
                                        SET name = :name, code = :code, content_template = :content_template, 
                                            number_format = :number_format, reset_cycle = :reset_cycle, workflow_stages = :workflow_stages,
                                            header_logo = :header_logo, header_line_1 = :header_line_1, header_line_2 = :header_line_2,
                                            header_address = :header_address, header_image = :header_image
                                        WHERE id = :id");
                $stmt->execute([
                    ':name' => $name,
                    ':code' => $code,
                    ':content_template' => $content_template,
                    ':number_format' => $number_format,
                    ':reset_cycle' => $reset_cycle,
                    ':workflow_stages' => $workflow_stages_json,
                    ':header_logo' => $header_logo,
                    ':header_line_1' => $header_line_1,
                    ':header_line_2' => $header_line_2,
                    ':header_address' => $header_address,
                    ':header_image' => $header_image,
                    ':id' => $id
                ]);
                Logger::activity('Dokumen', 'UPDATE_TEMPLATE', 'Mengubah template surat: ' . $name, ['id' => $id]);
                redirect("views/documents/template_config.php?success=Template+berhasil+diperbarui");
            }
        } catch (PDOException $e) {
            redirect("views/documents/template_config.php?error=Gagal+menyimpan+template:+" . urlencode($e->getMessage()));
        }
    } elseif ($action === 'delete') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id > 0) {
            try {
                // Fetch template name first for logging
                $stmtName = $conn->prepare("SELECT name FROM document_templates WHERE id = ?");
                $stmtName->execute([$id]);
                $tplName = $stmtName->fetchColumn();

                $stmt = $conn->prepare("DELETE FROM document_templates WHERE id = ?");
                $stmt->execute([$id]);

                Logger::activity('Dokumen', 'DELETE_TEMPLATE', 'Menghapus template surat: ' . $tplName, ['id' => $id]);
                redirect("views/documents/template_config.php?success=Template+berhasil+dihapus");
            } catch (PDOException $e) {
                redirect("views/documents/template_config.php?error=Gagal+menghapus+template:+" . urlencode($e->getMessage()));
            }
        }
    }
}
redirect("views/documents/template_config.php");
