<?php
$conn = new PDO('mysql:host=localhost;dbname=attendance_db', 'root', '');
$stmt = $conn->query("DESCRIBE class_journals");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
