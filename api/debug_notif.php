<?php
// api/debug_notif.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle CLI vs Web
if (php_sapi_name() === 'cli') {
    $user_id = $argv[1] ?? '';
} else {
    $user_id = $_GET['user_id'] ?? '';
}

// Manually connect to DB to avoid CLI localhost issue
$host = "127.0.0.1";
$db_name = "attendance_db";
$username = "root";
$password = "";

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("DB Connection Failed: " . $e->getMessage() . "\n");
}

if (!$user_id) {
    echo "=== DEBUG MODE: LIST STAFF (Matches 'Idris' or 'Ma') ===\n";

    $stmt = $conn->query("SELECT e.id, e.name, e.unit_id, p.name as pos FROM employees e LEFT JOIN positions p ON e.position_id = p.id WHERE e.name LIKE '%Idris%' OR e.name LIKE '%Ma%' LIMIT 20");
    if ($stmt) {
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "ID: " . str_pad($r['id'], 4) . " | " . str_pad(substr($r['name'], 0, 20), 22) . " | Unit: " . ($r['unit_id'] ?? '-') . " | " . $r['pos'] . "\n";
        }
    } else {
        echo "Query failed.\n";
    }
    exit;
}

echo "=== DIAGNOSA PENCARIAN ATASAN ===\n";
echo "Checking Staff ID: $user_id\n\n";

// 1. Ambil Data Staff
$stmt = $conn->prepare("
    SELECT e.id, e.name, e.unit_id, e.division_id, p.level, p.name as position_name
    FROM employees e 
    LEFT JOIN positions p ON e.position_id = p.id 
    WHERE e.id = :id
");
$stmt->execute([':id' => $user_id]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$emp) {
    die("Error: Staff dengan ID $user_id tidak ditemukan.\n");
}

echo "INFO STAFF:\n";
echo "Nama       : " . $emp['name'] . "\n";
echo "Jabatan    : " . $emp['position_name'] . " (Level " . $emp['level'] . ")\n";
echo "Unit ID    : " . ($emp['unit_id'] ?? 'NULL') . "\n";
echo "Division ID: " . ($emp['division_id'] ?? 'NULL') . "\n";
echo "------------------------------------------------\n\n";

// 2. Simulasi Logika Pencarian
$level = (int) $emp['level'];
$unit_id = $emp['unit_id'];
$division_id = $emp['division_id'];
$approver_id = null;

if ($level >= 4) {
    echo "[LOGIC] Staff Level $level detected inside Scenario 1 (Staff/Guru).\n";
    echo "[LOGIC] Mencari Atasan di Division ID: $division_id\n";
    echo "[LOGIC] Kriteria: Level 2 atau 3, Unit ID cocok ($unit_id) ATAU NULL.\n";

    $sqlBoss = "SELECT e.id, e.name, e.unit_id, p.level, e.fcm_token 
                FROM employees e 
                JOIN positions p ON e.position_id = p.id 
                WHERE e.division_id = :div_id 
                AND (e.unit_id = :unit_id OR e.unit_id IS NULL)
                AND p.level IN (2, 3)
                AND e.status = 'active'
                ORDER BY p.level DESC, e.unit_id DESC";

    $stmtBoss = $conn->prepare($sqlBoss);
    $stmtBoss->execute([
        ':div_id' => $division_id,
        ':unit_id' => $unit_id
    ]);

    $candidates = $stmtBoss->fetchAll(PDO::FETCH_ASSOC);

    echo "HASIL PENCARIAN:\n";
    if (count($candidates) == 0) {
        echo "TIDAK DITEMUKAN kandidat atasan yang cocok.\n";
    } else {
        foreach ($candidates as $k => $c) {
            $tokenStatus = !empty($c['fcm_token']) ? "ADA TOKEN" : "TIDAK ADA TOKEN";
            $marker = ($k == 0) ? " <--- AKAN DIPILIH" : "";
            echo "- [ID: " . $c['id'] . "] " . $c['name'] . " | Level " . $c['level'] . " | Unit: " . ($c['unit_id'] ?? 'NULL') . " | $tokenStatus $marker\n";
        }
        $approver_id = $candidates[0]['id'];
    }

} elseif ($level == 3) {
    echo "[LOGIC] Staff adalah Level 3 (Ka. Unit).\n";
    // ... logic level 3 incomplete in this debug script but focused on user issue
}

// Safety Net
if (!$approver_id && $level != 1) {
    echo "\n[FALLBACK] Mencari MUDIR (Level 1)...\n";
    $stmtMudir = $conn->query("SELECT e.id, e.name, e.fcm_token FROM employees e JOIN positions p ON e.position_id = p.id WHERE p.level = 1 LIMIT 1");
    $mudir = $stmtMudir->fetch(PDO::FETCH_ASSOC);
    if ($mudir) {
        echo "Found Mudir: " . $mudir['name'] . "\n";
        $approver_id = $mudir['id'];
    }
}

echo "\nKESIMPULAN AKHIR:\n";
if ($approver_id) {
    echo "Approver ID: $approver_id\n";
} else {
    echo "GAGAL: Tidak ada Approver.\n";
}
?>