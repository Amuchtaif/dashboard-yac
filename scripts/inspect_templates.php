<?php
require_once __DIR__ . '/../config/database.php';
$db = new Database();
$conn = $db->getConnection();
print_r($conn->query('DESCRIBE document_templates')->fetchAll(PDO::FETCH_ASSOC));
?>
