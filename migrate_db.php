<?php
// migrate_db.php - Robust Database Migration Script
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

echo "<h1>Database Migration Tool</h1>";
echo "<pre>";

try {
    $db = new Database();
    $conn = $db->getConnection();

    echo "Connected to database.\n";

    // 1. Create Divisions Table
    echo "Checking 'divisions' table...\n";
    $conn->exec("CREATE TABLE IF NOT EXISTS `divisions` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(255) NOT NULL,
      `schedule_id` int(11) DEFAULT 1,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Table 'divisions' ensured.\n";

    // Seed Divisions
    $count = $conn->query("SELECT COUNT(*) FROM divisions")->fetchColumn();
    if ($count == 0) {
        echo "Seeding 'divisions' table...\n";
        $conn->exec("INSERT INTO `divisions` (`id`, `name`) VALUES 
        (1, 'All Departments (Migration)'),
        (2, 'Pendidikan'), 
        (3, 'Ekonomi'), 
        (4, 'Sosial'), 
        (5, 'Keuangan')");
    }

    // 2. Modify Units Table
    echo "Checking 'units' table...\n";
    $unitsColumns = $conn->query("DESCRIBE units")->fetchAll(PDO::FETCH_COLUMN);

    // Rename department_id -> division_id if it exists
    if (in_array('department_id', $unitsColumns)) {
        echo "Renaming 'department_id' to 'division_id' in units...\n";
        // Try to drop FK first (safely)
        try {
            $conn->exec("ALTER TABLE `units` DROP FOREIGN KEY `units_ibfk_1`");
            echo " - Old FK dropped.\n";
        } catch (PDOException $e) {
            echo " - No old FK to drop or already dropped.\n";
        }

        $conn->exec("ALTER TABLE `units` CHANGE `department_id` `division_id` int(11) NOT NULL");
        echo " - Column renamed.\n";
    }

    // Fix Orphan Data in Units
    echo "Fixing orphan unit data...\n";
    // Ensure all units point to a valid division (ID 1 is our fallback)
    $conn->exec("UPDATE `units` SET `division_id` = 1 WHERE `division_id` NOT IN (SELECT `id` FROM `divisions`)");

    // Add schedule_id
    if (!in_array('schedule_id', $unitsColumns)) {
        $conn->exec("ALTER TABLE `units` ADD COLUMN `schedule_id` int(11) DEFAULT 1");
        echo "Added 'schedule_id' to units.\n";
    }

    // Add FK Constraint (Safely)
    try {
        $conn->exec("ALTER TABLE `units` ADD CONSTRAINT `units_fk_division` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`) ON DELETE CASCADE");
        echo "Added FK constraint to units.\n";
    } catch (PDOException $e) {
        // Assume exists or duplicate
        echo "FK constraint for units likely already exists.\n";
    }

    // 3. Create Positions Table
    echo "Checking 'positions' table...\n";
    $conn->exec("CREATE TABLE IF NOT EXISTS `positions` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(100) NOT NULL,
      `level` int(11) NOT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Seed Positions
    $posCount = $conn->query("SELECT COUNT(*) FROM positions")->fetchColumn();
    if ($posCount == 0) {
        $conn->exec("INSERT INTO `positions` (`name`, `level`) VALUES 
        ('Mudir', 1), 
        ('Kepala Bidang', 2), 
        ('Kepala Unit', 3), 
        ('Guru', 4), 
        ('Staf', 5)");
        echo "Seeded 'positions' table.\n";
    }

    // 4. Update Employees Table
    echo "Checking 'employees' table...\n";
    $empColumns = $conn->query("DESCRIBE employees")->fetchAll(PDO::FETCH_COLUMN);

    if (in_array('department_id', $empColumns)) {
        echo "Renaming 'department_id' to 'division_id' in employees...\n";
        try {
            $conn->exec("ALTER TABLE `employees` DROP FOREIGN KEY `employees_ibfk_1`");
        } catch (PDOException $e) {
        }

        $conn->exec("ALTER TABLE `employees` CHANGE `department_id` `division_id` int(11) DEFAULT NULL");
    }

    if (!in_array('position_id', $empColumns)) {
        $conn->exec("ALTER TABLE `employees` ADD COLUMN `position_id` int(11) DEFAULT NULL");
        echo "Added 'position_id' to employees.\n";
    }
    if (!in_array('schedule_id', $empColumns)) {
        $conn->exec("ALTER TABLE `employees` ADD COLUMN `schedule_id` int(11) DEFAULT NULL");
        echo "Added 'schedule_id' to employees.\n";
    }

    // Fix Orphan Data in Employees
    echo "Fixing orphan employee data...\n";
    $conn->exec("UPDATE `employees` SET `division_id` = NULL WHERE `division_id` NOT IN (SELECT `id` FROM `divisions`)");

    // Add FK Constraint
    try {
        $conn->exec("ALTER TABLE `employees` ADD CONSTRAINT `employees_fk_division` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`) ON DELETE SET NULL");
        echo "Added FK constraint to employees.\n";
    } catch (PDOException $e) {
        echo "FK constraint for employees likely already exists.\n";
    }

    echo "\n\n----- MIGRATION SUCCESSFUL! -----\n";
    echo "You can now delete this file (migrate_db.php).";

} catch (PDOException $e) {
    echo "\n\nCRITICAL ERROR: " . $e->getMessage();
}
echo "</pre>";
?>