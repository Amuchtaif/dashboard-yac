<?php
require 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

// --- MAPPINGS ---
$ma_aly_map = [
    'mahasiswa semester 2' => 'Semester 2 Ikhwan',
    'mahasiswi semester 2' => 'Semester 2 Akhwat',
    'mahasiswa semester 4' => 'Semester 4 Ikhwan',
    'mahasiswi semester 4' => 'Semester 4 Akhwat',
    'mahasiswa semester 6' => 'Semester 6 Ikhwan',
    'mahasiswi semester 6' => 'Semester 6 Akhwat',
    'mahasiswa semester 8' => 'Semester 8 Ikhwan',
    'mahasiswi semester 8' => 'Semester 8 Akhwat',
    'idad lughoh putra'    => 'IDAD Lughoh Putra',
    'idad lughoh putri'    => 'IDAD Lughoh Putri'
];

$subject_map = [
    'ips' => 'Ilmu Pengetahuan Sosial',
    'ipa' => 'Ilmu Pengetahuan Alam',
    'bind' => 'Bahasa Indonesia',
    'bing' => 'Bahasa Inggris',
    'pkn' => 'Pendidikan Pancasila & Kewarganegaraan',
    'sunda' => 'Bahasa Sunda',
    'arab' => 'Bahasa Arab'
];

// --- PRE-FETCH ---
$grades = $conn->query("SELECT id, name, education_unit_id FROM grade_levels")->fetchAll(PDO::FETCH_ASSOC);
$employees = $conn->query("SELECT id, full_name as name FROM employees")->fetchAll(PDO::FETCH_ASSOC);
$subjects = $conn->query("SELECT id, name FROM subjects")->fetchAll(PDO::FETCH_ASSOC);

function getOrCreateLP($conn, $uid, $start, $end) {
    $stmt = $conn->prepare("SELECT id FROM lesson_periods WHERE education_unit_id = ? AND start_time = ? LIMIT 1");
    $stmt->execute([$uid, $start.':00']);
    $lp = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($lp) return $lp['id'];

    // Create missing LP for demo
    $stmt = $conn->prepare("SELECT MAX(period_number) FROM lesson_periods WHERE education_unit_id = ?");
    $stmt->execute([$uid]);
    $next_period = (int)$stmt->fetchColumn() + 1;

    $stmt = $conn->prepare("INSERT INTO lesson_periods (education_unit_id, period_number, start_time, end_time) VALUES (?, ?, ?, ?)");
    $stmt->execute([$uid, $next_period, $start, $end]);
    return $conn->lastInsertId();
}

// --- PROCESSING ---
$csvFile = 'd:/xampp/htdocs/dashboard-yac/data-jadwal.csv';
$lines = file($csvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$header = array_shift($lines);

$stats = ['total' => 0, 'success' => 0, 'conflict' => 0, 'no_match' => 0, 'logs' => []];
$busy = [];

foreach ($lines as $line) {
    if (empty(trim($line))) continue;
    $cols = explode("\t", $line);
    if (count($cols) < 6) continue;
    $stats['total']++;

    $raw_class = trim($cols[1]);
    $raw_sub = trim($cols[2]);
    $raw_guru = trim($cols[3]);
    $raw_hari = ucfirst(strtolower(trim($cols[4])));
    $raw_jam = str_replace(' ', '', trim($cols[5]));

    // 1. Resolve Grade
    $clean_class = strtolower(preg_replace('/\s+TA\s+\d+.*$/i', '', $raw_class));
    $search_class = $ma_aly_map[$clean_class] ?? $clean_class;
    
    $gid = null; $uid = null;
    foreach ($grades as $g) {
        if (strtolower($g['name']) == strtolower($search_class)) {
            $gid = $g['id']; $uid = $g['education_unit_id']; break;
        }
    }

    // 2. Resolve Guru
    $eid = null;
    $low_guru = strtolower($raw_guru);
    // Try exact match first
    foreach ($employees as $e) {
        if (strtolower($e['name']) == $low_guru) {
            $eid = $e['id']; break;
        }
    }
    // Try substring if not found
    if (!$eid) {
        foreach ($employees as $e) {
            if (strpos(strtolower($e['name']), $low_guru) !== false) {
                $eid = $e['id']; break;
            }
        }
    }

    // 3. Resolve Subject
    $sid = null;
    $low_sub = strtolower($raw_sub);
    if ($low_sub == 'ips') $search_sub = 'ilmu pengetahuan sosial';
    elseif ($low_sub == 'ipa') $search_sub = 'ilmu pengetahuan alam';
    elseif ($low_sub == 'pkn') $search_sub = 'pendidikan pancasila';
    else $search_sub = $low_sub;

    // Try exact match first
    foreach ($subjects as $s) {
        if (strtolower($s['name']) == $search_sub) {
            $sid = $s['id']; break;
        }
    }
    // Try substring if not found
    if (!$sid) {
        foreach ($subjects as $s) {
            if (strpos(strtolower($s['name']), $search_sub) !== false) {
                // Special case for IPS/IPA to avoid 'Skripsi'
                if (strlen($search_sub) <= 3 && strtolower($s['name']) != $search_sub) continue;
                $sid = $s['id']; break;
            }
        }
    }

    // 4. Resolve Time & LP
    $time_parts = explode('-', $raw_jam);
    if (count($time_parts) != 2) { $stats['no_match']++; continue; }
    $start = date('H:i', strtotime($time_parts[0]));
    $end = date('H:i', strtotime($time_parts[1]));

    $lpid = null;
    if ($uid && $start != '00:00') { // Only try to get LP if unit ID and start time are valid
        $lpid = getOrCreateLP($conn, $uid, $start, $end);
    }

    if (!$gid || !$eid || !$sid || !$lpid) {
        $stats['no_match']++;
        if (count($stats['logs']) < 30) {
            $reason = [];
            if (!$gid) $reason[] = "Grade($raw_class)";
            if (!$eid) $reason[] = "Employee($raw_guru)";
            if (!$sid) $reason[] = "Subject($raw_sub)";
            if (!$lpid) $reason[] = "LP($start)";
            $stats['logs'][] = "Row ".($stats['total']).": ".implode(", ", $reason);
        }
        continue;
    }

    // 5. Overlap Guard
    $conflict = false;
    foreach(['teacher' => $eid, 'grade' => $gid] as $type => $id) {
        if (isset($busy[$type][$id][$raw_hari])) {
            foreach ($busy[$type][$id][$raw_hari] as $b) {
                if ($start < $b['end'] && $end > $b['start']) { $conflict = true; break; }
            }
        }
    }
    if ($conflict) { $stats['conflict']++; continue; }

    // 6. Final Insertion
    try {
        $stmt = $conn->prepare("INSERT INTO class_schedules (academic_year_id, grade_level_id, employee_id, subject_id, day, lesson_period_id) VALUES (1, ?, ?, ?, ?, ?)");
        $stmt->execute([$gid, $eid, $sid, $raw_hari, $lpid]);
        $busy['teacher'][$eid][$raw_hari][] = ['start' => $start, 'end' => $end];
        $busy['grade'][$gid][$raw_hari][] = ['start' => $start, 'end' => $end];
        $stats['success']++;
    } catch (Exception $e) {
        $stats['no_match']++;
    }
}

echo json_encode($stats, JSON_PRETTY_PRINT);
unlink('mega_migrate.php');
