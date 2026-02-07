<?php
// db/update_db_v2.php
include_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    echo "Updating database...\n";

    // 1. Add can_create_meeting column to positions table if not exists
    $checkColumn = $db->query("SHOW COLUMNS FROM positions LIKE 'can_create_meeting'");
    if ($checkColumn->rowCount() == 0) {
        $sql = "ALTER TABLE positions ADD COLUMN can_create_meeting TINYINT(1) DEFAULT 0";
        $db->exec($sql);
        echo "Added column 'can_create_meeting' to 'positions' table.\n";
    } else {
        echo "Column 'can_create_meeting' already exists in 'positions' table.\n";
    }

    // 2. Create meetings table
    $sqlMeetings = "CREATE TABLE IF NOT EXISTS meetings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        meeting_date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        type ENUM('online', 'offline') NOT NULL,
        location VARCHAR(255),
        created_by INT NOT NULL,
        division_id INT NOT NULL,
        qr_token VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES employees(id) ON DELETE CASCADE,
        FOREIGN KEY (division_id) REFERENCES divisions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;";
    $db->exec($sqlMeetings);
    echo "Table 'meetings' checked/created.\n";

    // 3. Create meeting_participants table
    $sqlParticipants = "CREATE TABLE IF NOT EXISTS meeting_participants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        meeting_id INT NOT NULL,
        employee_id INT NOT NULL,
        status ENUM('invited', 'present', 'absent') DEFAULT 'invited',
        attendance_time DATETIME NULL,
        FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;";
    $db->exec($sqlParticipants);
    echo "Table 'meeting_participants' checked/created.\n";

    echo "Database update completed successfully.\n";

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
