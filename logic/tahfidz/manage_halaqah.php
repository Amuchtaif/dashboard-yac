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
        $new_group_id = $conn->lastInsertId();

        $t_stmt = $conn->prepare("SELECT full_name FROM employees WHERE id = ? LIMIT 1");
        $t_stmt->execute([$teacher_id]);
        $t_name = $t_stmt->fetchColumn() ?: "ID $teacher_id";

        Logger::activity(
            'Tahfidz',
            'CREATE_HALAQAH',
            "Membuat kelompok halaqah baru '$group_name' (Pengampu: $t_name)",
            [
                'table' => 'halaqah_groups',
                'record_id' => $new_group_id,
                'new_data' => ['group_name' => $group_name, 'teacher' => $t_name]
            ]
        );

        $_SESSION['success'] = "Kelompok halaqah berhasil ditambahkan.";
    } 
    elseif ($action === 'update_group') {
        $group_id = $_POST['group_id'] ?? '';
        $group_name = $_POST['group_name'] ?? '';
        $teacher_id = $_POST['teacher_id'] ?? '';

        if (empty($group_id) || empty($group_name) || empty($teacher_id)) {
            throw new Exception("ID, Nama kelompok, dan pengampu harus diisi.");
        }

        $old_g_stmt = $conn->prepare("SELECT group_name FROM halaqah_groups WHERE id = ? LIMIT 1");
        $old_g_stmt->execute([$group_id]);
        $old_g_name = $old_g_stmt->fetchColumn() ?: "ID $group_id";

        $stmt = $conn->prepare("UPDATE halaqah_groups SET group_name = ?, teacher_id = ? WHERE id = ?");
        $stmt->execute([$group_name, $teacher_id, $group_id]);

        $t_stmt = $conn->prepare("SELECT full_name FROM employees WHERE id = ? LIMIT 1");
        $t_stmt->execute([$teacher_id]);
        $t_name = $t_stmt->fetchColumn() ?: "ID $teacher_id";

        Logger::activity(
            'Tahfidz',
            'UPDATE_HALAQAH',
            "Mengubah kelompok halaqah '$old_g_name' menjadi '$group_name' (Pengampu: $t_name)",
            [
                'table' => 'halaqah_groups',
                'record_id' => $group_id,
                'old_data' => ['group_name' => $old_g_name],
                'new_data' => ['group_name' => $group_name, 'teacher' => $t_name]
            ]
        );

        $_SESSION['success'] = "Kelompok halaqah berhasil diperbarui.";
    }
    elseif ($action === 'delete_group') {
        $group_id = $_POST['group_id'] ?? '';

        if (empty($group_id)) {
            throw new Exception("ID Kelompok tidak ditemukan.");
        }

        $old_g_stmt = $conn->prepare("SELECT group_name FROM halaqah_groups WHERE id = ? LIMIT 1");
        $old_g_stmt->execute([$group_id]);
        $old_g_name = $old_g_stmt->fetchColumn() ?: "ID $group_id";

        $stmt = $conn->prepare("DELETE FROM halaqah_groups WHERE id = ?");
        $stmt->execute([$group_id]);

        Logger::activity(
            'Tahfidz',
            'DELETE_HALAQAH',
            "Menghapus kelompok halaqah '$old_g_name'",
            [
                'table' => 'halaqah_groups',
                'record_id' => $group_id,
                'old_data' => ['group_name' => $old_g_name]
            ]
        );

        $_SESSION['success'] = "Kelompok halaqah berhasil dihapus.";
    }
    elseif ($action === 'add_member') {
        $group_id = $_POST['group_id'] ?? '';
        $student_ids = $_POST['student_ids'] ?? [];

        if (empty($group_id) || empty($student_ids)) {
            throw new Exception("Santri harus dipilih.");
        }

        $g_stmt = $conn->prepare("SELECT group_name FROM halaqah_groups WHERE id = ? LIMIT 1");
        $g_stmt->execute([$group_id]);
        $g_name = $g_stmt->fetchColumn() ?: "ID $group_id";

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

        Logger::activity(
            'Tahfidz',
            'ADD_HALAQAH_MEMBER',
            "Menambahkan $added_count santri ke kelompok halaqah '$g_name'",
            [
                'table' => 'halaqah_members',
                'new_data' => ['group_name' => $g_name, 'added_count' => $added_count]
            ]
        );

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

        $m_stmt = $conn->prepare("
            SELECT s.nama_siswa, hg.group_name 
            FROM halaqah_members hm
            LEFT JOIN students s ON hm.student_id = s.id
            LEFT JOIN halaqah_groups hg ON hm.group_id = hg.id
            WHERE hm.id = ? LIMIT 1
        ");
        $m_stmt->execute([$member_id]);
        $m_info = $m_stmt->fetch(PDO::FETCH_ASSOC);
        $s_name = $m_info['nama_siswa'] ?? "ID Member $member_id";
        $g_name = $m_info['group_name'] ?? "ID $group_id";

        $stmt = $conn->prepare("DELETE FROM halaqah_members WHERE id = ?");
        $stmt->execute([$member_id]);

        Logger::activity(
            'Tahfidz',
            'REMOVE_HALAQAH_MEMBER',
            "Menghapus santri '$s_name' dari kelompok halaqah '$g_name'",
            [
                'table' => 'halaqah_members',
                'record_id' => $member_id,
                'old_data' => ['nama_siswa' => $s_name, 'group_name' => $g_name]
            ]
        );

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
