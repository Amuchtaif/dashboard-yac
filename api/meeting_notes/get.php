<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$meeting_id = isset($_GET['meeting_id']) ? $_GET['meeting_id'] : die();

$query = "SELECT n.*, e.full_name as user_name 
          FROM meeting_notes n 
          LEFT JOIN employees e ON n.user_id = e.id 
          WHERE n.meeting_id = :meeting_id 
          ORDER BY n.created_at DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(":meeting_id", $meeting_id);
$stmt->execute();

$notes = array();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $notes[] = $row;
}

echo json_encode(array("success" => true, "data" => $notes));
?>
