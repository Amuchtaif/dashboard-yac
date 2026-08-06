<?php
// logic/documents/approve_document.php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

$db = new Database();
$conn = $db->getConnection();

$user_id = $_SESSION['user_id'];

// Helper to convert arabic month numbers to roman numerals
function getRomanMonth($month) {
    $map = [
        '01' => 'I', '02' => 'II', '03' => 'III', '04' => 'IV', 
        '05' => 'V', '06' => 'VI', '07' => 'VII', '08' => 'VIII', 
        '09' => 'IX', '10' => 'X', '11' => 'XI', '12' => 'XII'
    ];
    return $map[$month] ?? $month;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['direct_approve'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);
    $status_input = isset($_POST['status']) ? $_POST['status'] : 'approved';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $direct = isset($_GET['direct_approve']) ? true : false;

    if ($id <= 0) {
        redirect("views/documents/approval.php?error=ID+dokumen+tidak+valid");
    }

    try {
        $conn->beginTransaction();

        // Fetch document details
        $stmtDoc = $conn->prepare("SELECT d.*, dt.workflow_stages, dt.number_format, dt.counter, dt.code as template_code,
                                          e.full_name as creator_name, u.name as creator_unit
                                   FROM documents d 
                                   LEFT JOIN document_templates dt ON d.template_id = dt.id 
                                   LEFT JOIN employees e ON d.creator_id = e.id
                                   LEFT JOIN units u ON e.unit_id = u.id
                                   WHERE d.id = ?");
        $stmtDoc->execute([$id]);
        $document = $stmtDoc->fetch(PDO::FETCH_ASSOC);

        if (!$document) {
            redirect("views/documents/approval.php?error=Dokumen+tidak+ditemukan");
        }

        if ($direct) {
            // Automatic instant approval (if no workflow stages defined)
            $is_final = true;
        } else {
            // Normal workflow approval check
            // Find user's active pending approval stage
            $stmtCheck = $conn->prepare("SELECT * FROM document_approvals 
                                         WHERE document_id = ? AND approver_id = ? AND status = 'pending' 
                                         LIMIT 1");
            $stmtCheck->execute([$id, $user_id]);
            $my_approval = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (!$my_approval) {
                redirect("views/documents/approval.php?error=Anda+tidak+memiliki+akses+approval+untuk+dokumen+ini");
            }

            $current_stage_order = intval($my_approval['stage_order']);

            if ($status_input === 'rejected') {
                // Reject: Set this stage to rejected, update document status
                $stmtUpdateApp = $conn->prepare("UPDATE document_approvals SET status = 'rejected', notes = ?, approved_at = NOW() 
                                                 WHERE document_id = ? AND stage_order = ?");
                $stmtUpdateApp->execute([$notes, $id, $current_stage_order]);

                $stmtUpdateDoc = $conn->prepare("UPDATE documents SET status = 'rejected' WHERE id = ?");
                $stmtUpdateDoc->execute([$id]);

                // Send notification to creator
                $stmtNotif = $conn->prepare("INSERT INTO notifications (user_id, title, body, type, reference_id) VALUES (?, ?, ?, 'document', ?)");
                $stmtNotif->execute([
                    $document['creator_id'],
                    'Dokumen Ditolak',
                    'Dokumen "' . $document['title'] . '" ditolak oleh ' . $_SESSION['user_name'] . '. Catatan: ' . $notes,
                    $id
                ]);

                $conn->commit();
                Logger::activity('Dokumen', 'REJECT_DOCUMENT', 'Menolak dokumen: ' . $document['title'], ['id' => $id]);
                redirect("views/documents/approval.php?success=Dokumen+berhasil+ditolak");
            } else {
                // Approve current stage
                $stmtUpdateApp = $conn->prepare("UPDATE document_approvals SET status = 'approved', notes = ?, approved_at = NOW() 
                                                 WHERE document_id = ? AND stage_order = ? AND approver_id = ?");
                $stmtUpdateApp->execute([$notes, $id, $current_stage_order, $user_id]);

                // Set other approvers of the same stage as resolved (or delete them)
                $stmtResolveOthers = $conn->prepare("UPDATE document_approvals SET status = 'approved', notes = 'Disetujui oleh perwakilan jabatan', approved_at = NOW() 
                                                     WHERE document_id = ? AND stage_order = ? AND status = 'pending'");
                $stmtResolveOthers->execute([$id, $current_stage_order]);

                // Check workflow stages to see if we have a next stage
                $stages = json_decode($document['workflow_stages'], true);
                $is_final = true;
                $next_stage = null;

                foreach ($stages as $stg) {
                    if (intval($stg['step']) > $current_stage_order) {
                        $is_final = false;
                        $next_stage = $stg;
                        break;
                    }
                }

                if (!$is_final && $next_stage) {
                    // Route to next stage
                    $next_stage_order = intval($next_stage['step']);
                    $next_position_id = intval($next_stage['position_id']);

                    // Find approvers for next stage
                    $stmtNextApprovers = $conn->prepare("SELECT id FROM employees WHERE position_id = ? AND status = 'active' ORDER BY id ASC");
                    $stmtNextApprovers->execute([$next_position_id]);
                    $next_approver_ids = $stmtNextApprovers->fetchAll(PDO::FETCH_COLUMN);

                    if (empty($next_approver_ids)) {
                        // Ultimate fallback: Assign to administrator
                        $next_approver_ids[] = 1;
                    }

                    $stmtApprove = $conn->prepare("INSERT INTO document_approvals (document_id, stage_order, approver_id, status) VALUES (?, ?, ?, 'pending')");
                    foreach ($next_approver_ids as $next_app_id) {
                        $stmtApprove->execute([$id, $next_stage_order, $next_app_id]);

                        // Send notification
                        $stmtNotif = $conn->prepare("INSERT INTO notifications (user_id, title, body, type, reference_id) VALUES (?, ?, ?, 'document', ?)");
                        $stmtNotif->execute([
                            $next_app_id,
                            'Persetujuan Dokumen Baru',
                            'Dokumen "' . $document['title'] . '" menunggu persetujuan Anda (Tahap ' . $next_stage_order . ').',
                            $id
                        ]);
                    }
                }
            }
        }

        if ($is_final) {
            // Document is fully approved! Process document numbering and finalize status
            $final_number = $document['document_number'];

            if ($document['template_id'] > 0) {
                // Generate sequential letter number using template rules
                $counter = intval($document['counter']);
                $num_format = $document['number_format'] ?: '{nomor}/{unit}/{bulan_romawi}/{tahun}';
                
                $padded_counter = str_pad($counter, 3, '0', STR_PAD_LEFT);
                $unit_placeholder = $document['creator_unit'] ?: 'YAC';
                $code_placeholder = $document['template_code'] ?: 'DOC';
                $roman_month = getRomanMonth(date('m'));
                $arabic_month = date('m');
                $year = date('Y');

                $final_number = str_replace([
                    '{nomor}', '{unit}', '{kode}', '{bulan_romawi}', '{bulan}', '{tahun}'
                ], [
                    $padded_counter, $unit_placeholder, $code_placeholder, $roman_month, $arabic_month, $year
                ], $num_format);

                // Increment counter in document_templates
                $stmtInc = $conn->prepare("UPDATE document_templates SET counter = counter + 1 WHERE id = ?");
                $stmtInc->execute([$document['template_id']]);
            }

            // Finalize document status
            $stmtFinal = $conn->prepare("UPDATE documents SET status = 'completed', document_number = ? WHERE id = ?");
            $stmtFinal->execute([$final_number, $id]);

            // Notify creator
            $stmtNotif = $conn->prepare("INSERT INTO notifications (user_id, title, body, type, reference_id) VALUES (?, ?, ?, 'document', ?)");
            $stmtNotif->execute([
                $document['creator_id'],
                'Dokumen Selesai',
                'Dokumen "' . $document['title'] . '" telah selesai disetujui dengan nomor surat: ' . $final_number,
                $id
            ]);
        }

        $conn->commit();
        
        $msg = $is_final ? 'Dokumen+telah+selesai+diproses+dan+diberi+nomor+surat' : 'Dokumen+berhasil+disetujui';
        redirect("views/documents/approval.php?success=" . $msg);

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        redirect("views/documents/approval.php?error=Gagal+memproses+approval:+" . urlencode($e->getMessage()));
    }
}
redirect("views/documents/approval.php");
