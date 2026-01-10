<?php
require_once '../../config/database.php';
$db = new Database();
$conn = $db->getConnection();

function checkColumn($conn, $table, $column)
{
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM $table LIKE '$column'");
        return $stmt->fetch() ? true : false;
    } catch (PDOException $e) {
        return false;
    }
}

$dept_has_schedule = checkColumn($conn, 'departments', 'schedule_id') ? "Yes" : "No";
$emp_has_schedule = checkColumn($conn, 'employees', 'schedule_id') ? "Yes" : "No";

echo "Departments has schedule_id: $dept_has_schedule\n";
echo "Employees has schedule_id: $emp_has_schedule\n";

if ($dept_has_schedule == "No") {
    $conn->exec("ALTER TABLE departments ADD COLUMN schedule_id INT NULL");
    echo "Added schedule_id to departments.\n";
}

if ($emp_has_schedule == "No") {
    $conn->exec("ALTER TABLE employees ADD COLUMN schedule_id INT NULL");
    echo "Added schedule_id to employees.\n";
}
?>