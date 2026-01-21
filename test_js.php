<?php
require_once 'config/app.php';
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();
$grade_levels_all = $conn->query("SELECT * FROM grade_levels ORDER BY category ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<script>
    const allGradeLevels = <?php echo json_encode($grade_levels_all); ?>;
    console.log(allGradeLevels);
</script>
<h1>Check Console</h1>