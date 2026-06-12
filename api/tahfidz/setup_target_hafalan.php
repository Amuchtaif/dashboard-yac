<?php
require_once __DIR__ . '/../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "CREATE TABLE IF NOT EXISTS `target_hafalan` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tahun_ajaran_id` INT NOT NULL,
        `unit_id` INT NOT NULL,
        `program_id` VARCHAR(50) NULL, -- 'Boarding' or 'Fullday'
        `kelas_id` INT NOT NULL, -- 7, 8, 9, 10, 11, 12
        `target_juz` DECIMAL(5,2) NOT NULL,
        `status_aktif` ENUM('Aktif', 'Tidak Aktif') DEFAULT 'Aktif',
        `keterangan` TEXT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_target (tahun_ajaran_id, unit_id, program_id, kelas_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $conn->exec($sql);
    echo "SUCCESS: Table 'target_hafalan' created or already exists.\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
