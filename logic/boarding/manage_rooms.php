<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ../../views/boarding/rooms/index.php?success=Operasi+berhasil');
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$action = $_POST['action'] ?? '';

try {
    if ($action === 'create_room') {
        $room_name = $_POST['room_name'] ?? '';
        $supervisor_ids = $_POST['supervisor_ids'] ?? [];

        if (empty($room_name) || empty($supervisor_ids)) {
            throw new Exception("Nama asrama dan musyrif harus diisi.");
        }

        $conn->beginTransaction();

        $stmt = $conn->prepare("INSERT INTO boarding_rooms (room_name, supervisor_id) VALUES (?, ?)");
        // We still keep supervisor_id in boarding_rooms for backward compatibility if needed, 
        // but we'll primarily use boarding_room_supervisors mapping.
        // We'll set the first one as primary in supervisor_id
        $stmt->execute([$room_name, $supervisor_ids[0]]);
        $room_id = $conn->lastInsertId();

        $stmtSup = $conn->prepare("INSERT INTO boarding_room_supervisors (room_id, supervisor_id) VALUES (?, ?)");
        foreach ($supervisor_ids as $sid) {
            $stmtSup->execute([$room_id, $sid]);
        }

        $conn->commit();

        $_SESSION['success'] = "Data asrama berhasil ditambahkan.";
    } 
    elseif ($action === 'update_room') {
        $room_id = $_POST['room_id'] ?? '';
        $room_name = $_POST['room_name'] ?? '';
        $supervisor_ids = $_POST['supervisor_ids'] ?? [];

        if (empty($room_id) || empty($room_name) || empty($supervisor_ids)) {
            throw new Exception("Semua data harus diisi.");
        }

        $conn->beginTransaction();

        $stmt = $conn->prepare("UPDATE boarding_rooms SET room_name = ?, supervisor_id = ? WHERE id = ?");
        $stmt->execute([$room_name, $supervisor_ids[0], $room_id]);

        // Refresh mapping
        $conn->prepare("DELETE FROM boarding_room_supervisors WHERE room_id = ?")->execute([$room_id]);
        $stmtSup = $conn->prepare("INSERT INTO boarding_room_supervisors (room_id, supervisor_id) VALUES (?, ?)");
        foreach ($supervisor_ids as $sid) {
            $stmtSup->execute([$room_id, $sid]);
        }

        $conn->commit();
        $_SESSION['success'] = "Data asrama berhasil diperbarui.";
    }
    elseif ($action === 'delete_room') {
        $room_id = $_POST['room_id'] ?? '';

        if (empty($room_id)) {
            throw new Exception("ID Asrama tidak ditemukan.");
        }

        $stmt = $conn->prepare("DELETE FROM boarding_rooms WHERE id = ?");
        $stmt->execute([$room_id]);

        $_SESSION['success'] = "Data asrama berhasil dihapus.";
    }
    elseif ($action === 'add_member') {
        $room_id = $_POST['room_id'] ?? '';
        $student_ids = $_POST['student_ids'] ?? [];

        if (empty($room_id) || empty($student_ids)) {
            throw new Exception("Santri harus dipilih.");
        }

        $added_count = 0;
        $stmt = $conn->prepare("INSERT INTO boarding_room_members (room_id, student_id) VALUES (?, ?)");
        $active_year_query = "SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1";
        $active_year_id = $conn->query($active_year_query)->fetchColumn();

        foreach ($student_ids as $sid) {
            // 1. Check if already a member in ANY room (one student per room)
            $check = $conn->prepare("SELECT id FROM boarding_room_members WHERE student_id = ?");
            $check->execute([$sid]);
            if ($check->fetch()) {
                continue; // Skip if already placed
            }

            // 2. Validate Unit (Exclude TKIT, SDIT, Playgroup)
            $unit_check = $conn->prepare("
                SELECT eu.name 
                FROM student_class_history sch 
                JOIN grade_levels gl ON sch.class_id = gl.id 
                JOIN education_units eu ON gl.education_unit_id = eu.id 
                WHERE sch.student_id = ? AND sch.academic_year_id = ?
            ");
            $unit_check->execute([$sid, $active_year_id]);
            $unit_name = $unit_check->fetchColumn();

            if ($unit_name) {
                // We use keyword matching to cover unit names like 'Playgroup' etc.
                $forbidden_keywords = ['TK', 'SD', 'Playgroup', 'PG'];
                $is_forbidden = false;
                foreach ($forbidden_keywords as $keyword) {
                    if (stripos($unit_name, $keyword) !== false) {
                        $is_forbidden = true;
                        break;
                    }
                }
                if ($is_forbidden) continue;
            }

            $stmt->execute([$room_id, $sid]);
            $added_count++;
        }

        $_SESSION['success'] = "$added_count santri berhasil ditempatkan ke asrama.";
        header('Location: ../../views/boarding/rooms/room_members.php?room_id=' . $room_id);
        exit;
    }
    elseif ($action === 'remove_member') {
        $member_id = $_POST['member_id'] ?? '';
        $room_id = $_POST['room_id'] ?? '';

        if (empty($member_id)) {
            throw new Exception("ID Anggota tidak ditemukan.");
        }

        $stmt = $conn->prepare("DELETE FROM boarding_room_members WHERE id = ?");
        $stmt->execute([$member_id]);

        $_SESSION['success'] = "Santri berhasil dikeluarkan dari asrama.";
        header('Location: ../../views/boarding/rooms/room_members.php?room_id=' . $room_id);
        exit;
    }

        header('Location: ../../views/boarding/rooms/index.php?success=Operasi+berhasil');

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: ' . $_SERVER['HTTP_REFERER']);
}
exit;
