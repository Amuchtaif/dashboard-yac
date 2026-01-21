<?php
// add_manager_to_divisions.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

echo "<h1>Adding Manager Column to Divisions</h1>";
echo "<pre>";

try {
    $db = new Database();
    $conn = $db->getConnection();

    echo "Connected to database.\n";

    // Check columns
    $columns = $conn->query("DESCRIBE divisions")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('manager_id', $columns)) {
        echo "Adding 'manager_id' column...\n";
        $conn->exec("ALTER TABLE `divisions` ADD COLUMN `manager_id` int(11) DEFAULT NULL AFTER `name`");
        echo "Column added.\n";

        // Add FK
        try {
            $conn->exec("ALTER TABLE `divisions` ADD CONSTRAINT `divisions_fk_manager` FOREIGN KEY (`manager_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL");
            echo "Foreign Key constraint added.\n";
        } catch (PDOException $e) {
            echo "Warning: Could not add FK (might already exist or data mismatch): " . $e->getMessage() . "\n";
        }
    } else {
        echo "Column 'manager_id' already exists.\n";
    }

    echo "\nSuccess! You can delete this file.";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
echo "</pre>";
?>