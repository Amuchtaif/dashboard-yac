<?php
// api/debug_boss.php
include_once '../config/database.php';

$database = new Database();
$conn = $database->getConnection();

// ID Staff yang mengajukan izin (Berita ID Andi dari screenshot = 40)
$staffId = isset($_GET['id']) ? $_GET['id'] : 40;

echo "<h1>🔍 Diagnosa Pencarian Atasan (PDO Version)</h1>";

// 1. Cek Data Staff
echo "<h3>1. Data Staff (ID: $staffId)</h3>";

$sqlStaff = "SELECT e.*, p.name as position_name, p.level 
             FROM employees e 
             LEFT JOIN positions p ON e.position_id = p.id 
             WHERE e.id = :id";
$stmt = $conn->prepare($sqlStaff);
$stmt->execute([':id' => $staffId]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);

if ($staff) {
    echo "Nama: " . $staff['full_name'] . "<br>";
    echo "Jabatan: " . $staff['position_name'] . " (Level: " . $staff['level'] . ")<br>";
    echo "Unit ID: " . ($staff['unit_id'] ? $staff['unit_id'] : "NULL") . "<br>";
    echo "Division ID: " . ($staff['division_id'] ? $staff['division_id'] : "NULL") . "<br>";
} else {
    die("❌ Staff tidak ditemukan. Cek ID nya.");
}

// 2. Cek Kandidat Atasan
echo "<h3>2. Kandidat Atasan Ditemukan</h3>";

// Query yang kita curigai bermasalah. Kita coba cari siapapun yang punya Divisi sama.
$divId = $staff['division_id'];
$unitId = $staff['unit_id'];

// Kita cari semua orang di divisi ini yang punya level 1, 2, atau 3
$sqlBoss = "
    SELECT e.id, e.full_name, e.unit_id, e.division_id, e.position_id, e.fcm_token, p.name as position_name, p.level
    FROM employees e 
    JOIN positions p ON e.position_id = p.id
    WHERE e.division_id = :divId 
    AND p.level IN (1, 2, 3) 
    ORDER BY p.level ASC
";

$stmtBoss = $conn->prepare($sqlBoss);
$stmtBoss->execute([':divId' => $divId]);
$bosses = $stmtBoss->fetchAll(PDO::FETCH_ASSOC);

if (count($bosses) > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f2f2f2;'>
            <th>ID</th>
            <th>Nama</th>
            <th>Jabatan (Level)</th>
            <th>Unit</th>
            <th>Token FCM</th>
            <th>Status Match Logic</th>
          </tr>";

    foreach ($bosses as $boss) {
        $tokenStatus = (strlen($boss['fcm_token'] ?? '') > 10) ? "✅ ADA" : "❌ KOSONG";

        // Cek Logika Match sesuai submit_permit.php
        $matchInfo = "";
        $isCandidate = false;

        // Logika flexible unit: Unit sama ATAU Unit si boss NULL
        if ($boss['unit_id'] == $unitId) {
            $matchInfo = "<span style='color:green;'>✅ MATCH (Unit Sama)</span>";
            $isCandidate = true;
        } elseif (is_null($boss['unit_id']) || $boss['unit_id'] == '') {
            $matchInfo = "<span style='color:blue;'>✅ MATCH (Atasan Global/Null Unit)</span>";
            $isCandidate = true;
        } else {
            $matchInfo = "<span style='color:red;'>❌ BEDA UNIT (Punya Unit: " . $boss['unit_id'] . ")</span>";
        }

        $rowStyle = $isCandidate ? "style='background-color: #e6fffa;'" : "";

        echo "<tr $rowStyle>";
        echo "<td>{$boss['id']}</td>";
        echo "<td>{$boss['full_name']}</td>";
        echo "<td>{$boss['position_name']} (Lvl {$boss['level']})</td>";
        echo "<td>" . ($boss['unit_id'] ?? 'NULL') . "</td>";
        echo "<td>$tokenStatus</td>";
        echo "<td>$matchInfo</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p><i>Catatan: Baris berwarna hijau/biru adalah kandidat yang akan dipilih oleh sistem (diprioritaskan Level 3 dulu, lalu Unit ID spesifik).</i></p>";
} else {
    echo "❌ TIDAK ADA data atasan (Level 1, 2, 3) dengan Division ID: $divId";
}
?>