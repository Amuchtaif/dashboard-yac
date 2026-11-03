<?php
// portal_wali/switch_student.php
require_once __DIR__ . '/init.php';
check_guardian_auth();

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$redirect = !empty($_GET['redirect']) ? $_GET['redirect'] : 'index.php';

if ($student_id > 0) {
    $db = new Database();
    $conn = $db->getConnection();

    // Pastikan siswa ini memang milik akun wali yang sedang login
    $stmt = $conn->prepare("
        SELECT student_id FROM guardian_students 
        WHERE guardian_id = :gid AND student_id = :sid AND is_verified = 1
        LIMIT 1
    ");
    $stmt->execute([':gid' => $_SESSION['guardian_id'], ':sid' => $student_id]);
    $valid = $stmt->fetchColumn();

    if ($valid) {
        $_SESSION['active_student_id'] = $student_id;
    }
}

// Sanitasi redirect untuk mencegah open-redirect
$parsed = parse_url($redirect);
$target = !empty($parsed['path']) ? basename($parsed['path']) : 'index.php';
if (!empty($parsed['query'])) {
    $target .= '?' . $parsed['query'];
}

header("Location: " . $target);
exit;

