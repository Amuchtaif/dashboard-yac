<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Create Locations Table
    $sql_locations = "CREATE TABLE IF NOT EXISTS inventory_locations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        parent_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_parent_location 
            FOREIGN KEY (parent_id) 
            REFERENCES inventory_locations(id) 
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $conn->exec($sql_locations);
    echo "Table 'inventory_locations' created successfully.\n";

    // Create Items Table
    $sql_items = "CREATE TABLE IF NOT EXISTS inventory_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        location_id INT NOT NULL,
        qty INT DEFAULT 0,
        description TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_item_location 
            FOREIGN KEY (location_id) 
            REFERENCES inventory_locations(id) 
            ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $conn->exec($sql_items);
    echo "Table 'inventory_items' created successfully.\n";

} catch (PDOException $e) {
    echo "Error creating tables: " . $e->getMessage() . "\n";
}
?>
