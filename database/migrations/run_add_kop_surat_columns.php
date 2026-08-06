<?php
// database/migrations/run_add_kop_surat_columns.php
require_once __DIR__ . '/../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmtCols = $conn->query("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_templates'
    ");
    $columns = $stmtCols->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('header_logo', $columns)) {
        $conn->exec("ALTER TABLE `document_templates` ADD COLUMN `header_logo` VARCHAR(255) NULL DEFAULT 'uploads/kop_logos/logo_yac.png' AFTER `workflow_stages`;");
    }
    if (!in_array('header_line_1', $columns)) {
        $conn->exec("ALTER TABLE `document_templates` ADD COLUMN `header_line_1` VARCHAR(255) NULL DEFAULT 'YAYASAN AS SUNNAH CIREBON' AFTER `header_logo`;");
    }
    if (!in_array('header_line_2', $columns)) {
        $conn->exec("ALTER TABLE `document_templates` ADD COLUMN `header_line_2` VARCHAR(255) NULL DEFAULT 'BIDANG PENDIDIKAN' AFTER `header_line_1`;");
    }
    if (!in_array('header_address', $columns)) {
        $conn->exec("ALTER TABLE `document_templates` ADD COLUMN `header_address` TEXT NULL DEFAULT 'Jl. Kalitanjung No.52B Kel. Karyamulya Kec. Kesambi Kota Cirebon 45135' AFTER `header_line_2`;");
    }
    if (!in_array('header_image', $columns)) {
        $conn->exec("ALTER TABLE `document_templates` ADD COLUMN `header_image` VARCHAR(255) NULL DEFAULT NULL AFTER `header_address`;");
    }

    echo "SUCCESS: Kop Surat columns added or verified in document_templates table.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
