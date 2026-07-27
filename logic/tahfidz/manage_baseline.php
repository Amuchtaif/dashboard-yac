<?php
// logic/tahfidz/manage_baseline.php
require_once '../../config/app.php';
require_once '../../config/database.php';
require_once '../../app/Services/Tahfidz/BaselineService.php';

check_login();
check_permission('manage_tahfidz');

$service = new BaselineService();
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'save';

try {
    if ($action === 'delete') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            throw new Exception("ID Baseline tidak valid.");
        }
        $service->deleteBaseline($id);
        header("Location: ../../views/tahfidz/baselines.php?success=" . urlencode("Baseline hafalan berhasil dihapus."));
        exit();
    } elseif ($action === 'save') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception("Metode request tidak diizinkan.");
        }

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
        $academic_year_id = isset($_POST['academic_year_id']) ? (int)$_POST['academic_year_id'] : 0;
        $baseline_juz = isset($_POST['baseline_juz']) ? (float)$_POST['baseline_juz'] : 0.0;
        $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

        if ($student_id <= 0) {
            throw new Exception("Silakan pilih santri terlebih dahulu.");
        }

        $data = [
            'student_id' => $student_id,
            'academic_year_id' => $academic_year_id,
            'baseline_juz' => $baseline_juz,
            'notes' => $notes
        ];

        if ($id > 0) {
            $service->updateBaseline($id, $data);
            $msg = "Baseline hafalan santri berhasil diperbarui.";
        } else {
            $service->createBaseline($data);
            $msg = "Baseline hafalan santri berhasil disimpan.";
        }

        header("Location: ../../views/tahfidz/baselines.php?success=" . urlencode($msg));
        exit();
    } else {
        throw new Exception("Aksi tidak dikenal.");
    }
} catch (Exception $e) {
    header("Location: ../../views/tahfidz/baselines.php?error=" . urlencode($e->getMessage()));
    exit();
}
