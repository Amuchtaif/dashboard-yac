<?php
// logic/documents/disposition_document.php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();
check_permission('document.disposition');

$db = new Database();
$conn = $db->getConnection();

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $document_id = isset($_POST['document_id']) ? intval($_POST['document_id']) : 0;
    $to_user_id = isset($_POST['to_user_id']) ? intval($_POST['to_user_id']) : 0;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;

    if ($document_id <= 0 || $to_user_id <= 0 || empty($notes)) {
        redirect("views/documents/incoming.php?error=Pegawai+penerima+dan+catatan+disposisi+wajib+diisi");
    }

    try {
        $conn->beginTransaction();

        // Check if document exists and is incoming
        $stmtDoc = $conn->prepare("SELECT title, type FROM documents WHERE id = ?");
        $stmtDoc->execute([$document_id]);
        $doc = $stmtDoc->fetch(PDO::FETCH_ASSOC);

        if (!$doc) {
            redirect("views/documents/incoming.php?error=Dokumen+tidak+ditemukan");
        }

        // Insert disposition
        $stmtDisp = $conn->prepare("INSERT INTO document_dispositions (document_id, from_user_id, to_user_id, notes, deadline, status) 
                                    VALUES (:doc_id, :from_id, :to_id, :notes, :deadline, 'pending')");
        $stmtDisp->execute([
            ':doc_id' => $document_id,
            ':from_id' => $user_id,
            ':to_id' => $to_user_id,
            ':notes' => $notes,
            ':deadline' => $deadline
        ]);
        $disp_id = $conn->lastInsertId();

        // Send notification to the recipient employee
        $stmtNotif = $conn->prepare("INSERT INTO notifications (user_id, title, body, type, reference_id) VALUES (?, ?, ?, 'disposition', ?)");
        $stmtNotif->execute([
            $to_user_id,
            'Disposisi Surat Baru',
            'Anda menerima disposisi surat "' . $doc['title'] . '" dari ' . $_SESSION['user_name'] . '. Catatan: ' . $notes,
            $document_id
        ]);

        $conn->commit();

        Logger::activity('Dokumen', 'CREATE_DISPOSITION', 'Membuat disposisi surat: ' . $doc['title'], ['id' => $disp_id]);
        redirect("views/documents/disposition.php?success=Disposisi+surat+berhasil+dikirim");

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        redirect("views/documents/incoming.php?error=Gagal+mengirim+disposisi:+" . urlencode($e->getMessage()));
    }
}
redirect("views/documents/incoming.php");
