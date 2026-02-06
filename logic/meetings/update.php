<?php
// logic/meetings/update.php
include_once '../../config/db_mysqli.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $meeting_date = $_POST['meeting_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $type = $_POST['type'];
    $location = $_POST['location'];
    $division_id = $_POST['division_id'];

    $sql = "UPDATE meetings SET 
            title = ?, 
            description = ?, 
            meeting_date = ?, 
            start_time = ?, 
            end_time = ?, 
            type = ?, 
            location = ?, 
            division_id = ? 
            WHERE id = ?";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("sssssssii", $title, $description, $meeting_date, $start_time, $end_time, $type, $location, $division_id, $id);

    if ($stmt->execute()) {
        header("Location: ../../views/meetings/index.php?msg=updated");
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>