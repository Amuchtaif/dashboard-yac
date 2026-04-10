<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ../../views/tahfidz/halaqah.php?success=Operasi+berhasil');
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$action = $_POST['action'] ?? '';

try {
    if ($action === 'create_group') {
        $group_name = $_POST['group_name'] ?? '';
        $teacher_id = $_POST['teacher_id'] ?? '';

        if (empty($group_name) || empty($teacher_id)) {
            throw new Exception("Nama kelompok dan pengampu harus diisi.");
        }

        $stmt = $conn->prepare("INSERT INTO halaqah_groups (group_name, teacher_id) VALUES (?, ?)");
        $stmt->execute([$group_name, $teacher_id]);

        $_SESSION['success'] = "Kelompok halaqah berhasil ditambahkan.";
    } 
    elseif ($action === 'update_group') {
        $group_id = $_POST['group_id'] ?? '';
        $group_name = $_POST['group_name'] ?? '';
        $teacher_id = $_POST['teacher_id'] ?? '';

        if (empty($group_id) || empty($group_name) || empty($teacher_id)) {
            throw new Exception("ID, Nama kelompok, dan pengampu harus diisi.");
        }

        $stmt = $conn->prepare("UPDATE halaqah_groups SET group_name = ?, teacher_id = ? WHERE id = ?");
        $stmt->execute([$group_name, $teacher_id, $group_id]);

        $_SESSION['success'] = "Kelompok halaqah berhasil diperbarui.";
    }
    elseif ($action === 'delete_group') {
        $group_id = $_POST['group_id'] ?? '';

        if (empty($group_id)) {
            throw new Exception("ID Kelompok tidak ditemukan.");
        }

        $stmt = $conn->prepare("DELETE FROM halaqah_groups WHERE id = ?");
        $stmt->execute([$group_id]);

        $_SESSION['success'] = "Kelompok halaqah berhasil dihapus.";
    }
    elseif ($action === 'add_member') {
        $group_id = $_POST['group_id'] ?? '';
        $student_ids = $_POST['student_ids'] ?? [];

        if (empty($group_id) || empty($student_ids)) {
            throw new Exception("Santri harus dipilih.");
        }

        $added_count = 0;
        $stmt = $conn->prepare("INSERT INTO halaqah_members (group_id, student_id) VALUES (?, ?)");

        foreach ($student_ids as $sid) {
            // Check if already a member
            $check = $conn->prepare("SELECT id FROM halaqah_members WHERE group_id = ? AND student_id = ?");
            $check->execute([$group_id, $sid]);
            if ($check->fetch()) {
                continue; // Skip if already member
            }

            $stmt->execute([$group_id, $sid]);
            $added_count++;
        }

        $_SESSION['success'] = "$added_count santri berhasil ditambahkan ke kelompok.";
        header('Location: ../../views/tahfidz/halaqah_members.php?group_id=' . $group_id);
        exit;
    }
    elseif ($action === 'remove_member') {
        $member_id = $_POST['member_id'] ?? '';
        $group_id = $_POST['group_id'] ?? '';

        if (empty($member_id)) {
            throw new Exception("ID Anggota tidak ditemukan.");
        }

        $stmt = $conn->prepare("DELETE FROM halaqah_members WHERE id = ?");
        $stmt->execute([$member_id]);

        $_SESSION['success'] = "Santri berhasil dihapus dari kelompok.";
        header('Location: ../../views/tahfidz/halaqah_members.php?group_id=' . $group_id);
        exit;
    }

        header('Location: ../../views/tahfidz/halaqah.php?success=Operasi+berhasil');

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: ' . $_SERVER['HTTP_REFERER']);
}
exit;
