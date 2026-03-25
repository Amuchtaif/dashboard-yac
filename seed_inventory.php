<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Insert dummy locations
    $conn->exec("INSERT INTO inventory_locations (id, name, parent_id) VALUES 
        (1, 'Assunnah 1', NULL),
        (2, 'Assunnah 2', NULL),
        (3, 'Gedung Yayasan', 1),
        (4, 'Masjid', 1),
        (5, 'Sekolah', 1),
        (6, 'TKIT', 5),
        (7, 'SDIT', 5),
        (8, 'MTs', 5),
        (9, 'MA', 5),
        (10, 'Ma''had Aly', 5)
    ");

    $conn->exec("INSERT INTO inventory_items (name, location_id, qty, description) VALUES 
        ('Kursi Kantor', 3, 10, 'Kursi putar hitam'),
        ('Papan Tulis', 7, 5, 'Whiteboard SDIT'),
        ('Proyektor', 4, 1, 'Proyektor BenQ Masjid'),
        ('Meja Siswa', 8, 30, 'Meja kayu MTs')
    ");

    echo "Dummy data inserted.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
