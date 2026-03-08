<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../views/boarding/rooms/index.php');
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$action = $_POST['action'] ?? '';

try {
    if ($action === 'create_room') {
        $room_name = $_POST['room_name'] ?? '';
        $supervisor_id = $_POST['supervisor_id'] ?? '';

        if (empty($room_name) || empty($supervisor_id)) {
            throw new Exception("Nama asrama dan musyrif harus diisi.");
        }

        $stmt = $conn->prepare("INSERT INTO boarding_rooms (room_name, supervisor_id) VALUES (?, ?)");
        $stmt->execute([$room_name, $supervisor_id]);

        $_SESSION['success'] = "Data asrama berhasil ditambahkan.";
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

        foreach ($student_ids as $sid) {
            // Check if already a member in ANY room (one student per room)
            $check = $conn->prepare("SELECT id FROM boarding_room_members WHERE student_id = ?");
            $check->execute([$sid]);
            if ($check->fetch()) {
                continue; // Skip if already placed
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

    header('Location: ../../views/boarding/rooms/index.php');

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: ' . $_SERVER['HTTP_REFERER']);
}
exit;
