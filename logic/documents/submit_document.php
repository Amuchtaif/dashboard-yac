<?php
// logic/documents/submit_document.php
require_once '../../config/database.php';
require_once '../../config/app.php';
require_once '../../logic/helpers/document_helper.php';

check_login();
check_permission('document.submit');

$db = new Database();
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if ($id <= 0) {
        redirect("views/documents/outgoing.php?error=ID+dokumen+tidak+valid");
    }

    try {
        // Fetch document details
        $stmtDoc = $conn->prepare("SELECT d.*, dt.workflow_stages, dt.signer_type FROM documents d 
                                   LEFT JOIN document_templates dt ON d.template_id = dt.id 
                                   WHERE d.id = ?");
        $stmtDoc->execute([$id]);
        $document = $stmtDoc->fetch(PDO::FETCH_ASSOC);

        if (!$document) {
            redirect("views/documents/outgoing.php?error=Dokumen+tidak+ditemukan");
        }

        if ($document['status'] !== 'draft' && $document['status'] !== 'rejected') {
            redirect("views/documents/outgoing.php?error=Hanya+dokumen+berstatus+Draft+atau+Ditolak+yang+dapat+diajukan");
        }

        // Determine approver IDs based on template signing hierarchy
        $approver_ids = [];
        $first_wa_phone = '';
        $first_handler_name = '';

        // Decode workflow stages if any
        $stages = [];
        if (!empty($document['workflow_stages'])) {
            $stages = json_decode($document['workflow_stages'], true);
        }

        // 1. If template is multi-stage workflow, route to Stage 1 positions
        if (!empty($stages) && is_array($stages) && count($stages) > 0 && ($document['signer_type'] === 'workflow' || empty($document['signer_type']))) {
            $first_stage = $stages[0];
            $position_id = intval($first_stage['position_id']);
            $stmtApprovers = $conn->prepare("SELECT id, phone_number, full_name FROM employees WHERE position_id = ? AND status = 'active' ORDER BY id ASC");
            $stmtApprovers->execute([$position_id]);
            $stage_emps = $stmtApprovers->fetchAll(PDO::FETCH_ASSOC);
            foreach ($stage_emps as $se) {
                $approver_ids[] = intval($se['id']);
                if (empty($first_wa_phone) && !empty($se['phone_number'])) {
                    $clean_phone = preg_replace('/[^0-9]/', '', $se['phone_number']);
                    if (strpos($clean_phone, '0') === 0) $clean_phone = '62' . substr($clean_phone, 1);
                    $first_wa_phone = $clean_phone;
                    $first_handler_name = $se['full_name'];
                }
            }
        }

        // 2. Direct hierarchical signer (unit_head, division_head, leader)
        if (empty($approver_ids) && !empty($document['signer_type']) && $document['signer_type'] !== 'workflow') {
            $resolvedSigner = getResolvedSignerInfo($conn, $document['creator_id'], $document['signer_type']);
            if (!empty($resolvedSigner['id'])) {
                $approver_ids[] = intval($resolvedSigner['id']);
                if (empty($first_wa_phone) && !empty($resolvedSigner['phone'])) {
                    $clean_phone = preg_replace('/[^0-9]/', '', $resolvedSigner['phone']);
                    if (strpos($clean_phone, '0') === 0) $clean_phone = '62' . substr($clean_phone, 1);
                    $first_wa_phone = $clean_phone;
                    $first_handler_name = $resolvedSigner['name'];
                }
            }
        }

        // 3. Fallback: supervisor (reports_to) or Admin
        if (empty($approver_ids)) {
            $stmtEmp = $conn->prepare("SELECT reports_to FROM employees WHERE id = ?");
            $stmtEmp->execute([$document['creator_id']]);
            $reports_to = $stmtEmp->fetchColumn();
            if ($reports_to) {
                $approver_ids[] = intval($reports_to);
            } else {
                $approver_ids[] = 1; // Admin fallback
            }
        }

        // Fetch assigned handlers for receiver division/unit to receive notification (informational)
        $assigned_handlers = [];
        if (!empty($document['receiver_division_id'])) {
            $stmtRules = $conn->prepare("
                SELECT r.employee_id, r.role_type, e.full_name, e.phone_number
                FROM document_routing_rules r
                JOIN employees e ON r.employee_id = e.id
                WHERE r.division_id = :div_id 
                  AND (r.unit_id = :unit_id OR r.unit_id IS NULL)
                  AND e.status = 'active'
                ORDER BY r.role_type DESC, r.id ASC
            ");
            $stmtRules->execute([
                ':div_id' => $document['receiver_division_id'],
                ':unit_id' => $document['receiver_unit_id'] ?? null
            ]);
            $assigned_handlers = $stmtRules->fetchAll(PDO::FETCH_ASSOC);
        }

        // Insert pending approvals & notifications
        $conn->beginTransaction();

        $stmtClear = $conn->prepare("DELETE FROM document_approvals WHERE document_id = ?");
        $stmtClear->execute([$id]);

        $stmtApprove = $conn->prepare("INSERT INTO document_approvals (document_id, stage_order, approver_id, status) VALUES (?, 1, ?, 'pending')");
        foreach ($approver_ids as $app_id) {
            $stmtApprove->execute([$id, $app_id]);

            // Save in-browser notification for approver
            $stmtNotif = $conn->prepare("INSERT INTO notifications (user_id, title, body, type, reference_id) VALUES (?, ?, ?, 'document', ?)");
            $stmtNotif->execute([
                $app_id,
                'Persetujuan Dokumen Baru',
                'Surat "' . $document['title'] . '" dari ' . $_SESSION['user_name'] . ' menunggu persetujuan Anda.',
                $id
            ]);
        }

        // Save in-browser notification for all assigned handlers
        foreach ($assigned_handlers as $ah) {
            if (!in_array($ah['employee_id'], $approver_ids)) {
                $stmtNotif = $conn->prepare("INSERT INTO notifications (user_id, title, body, type, reference_id) VALUES (?, ?, ?, 'document', ?)");
                $stmtNotif->execute([
                    $ah['employee_id'],
                    'Surat Masuk Baru',
                    'Surat "' . $document['title'] . '" dari ' . $_SESSION['user_name'] . ' masuk ke Bidang/Unit Anda.',
                    $id
                ]);
            }
        }

        // Update document status and signature placeholder
        $pending_content = processDocumentSignatureHtml($document['content'], 'pending_approval', $document['qr_token'] ?? '');
        $stmtUpdate = $conn->prepare("UPDATE documents SET status = 'pending_approval', content = ? WHERE id = ?");
        $stmtUpdate->execute([$pending_content, $id]);

        $conn->commit();
        
        Logger::activity('Dokumen', 'SUBMIT_DOCUMENT', 'Mengajukan surat keluar ke alur persetujuan: ' . $document['title'], ['id' => $id]);

        // Build wa.me text
        $wa_param = '';
        if (!empty($first_wa_phone)) {
            $wa_text = "Assalamu'alaikum wr wb, Yth. " . $first_handler_name . ". Terdapat Surat Masuk Baru dari " . $_SESSION['user_name'] . " perihal \"" . $document['title'] . "\". Mohon dapat ditindaklanjuti di Dashboard Persuratan YAC.";
            $wa_param = "&wa_phone=" . urlencode($first_wa_phone) . "&wa_text=" . urlencode($wa_text);
        }

        redirect("views/documents/outgoing.php?success=Surat+berhasil+diajukan+ke+alur+persetujuan" . $wa_param);

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        redirect("views/documents/outgoing.php?error=Gagal+mengajukan+surat:+" . urlencode($e->getMessage()));
    }
}
redirect("views/documents/outgoing.php");
