<?php
// views/documents/outgoing.php
require_once '../../config/app.php';
require_once '../../config/database.php';
require_once '../../logic/helpers/document_helper.php';

check_login();
check_permission('document.create');

$page_title = "Surat Keluar";

$db = new Database();
$conn = $db->getConnection();
$user_id = $_SESSION['user_id'];
$is_admin = isset($_SESSION['position_name']) && $_SESSION['position_name'] === 'Administrator';

// Action handling
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$doc_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$user_unit_id = 0;
$user_div_id = 0;
if (!$is_admin) {
    $stmtEmpInfo = $conn->prepare("SELECT division_id, department_id, unit_id FROM employees WHERE id = ?");
    $stmtEmpInfo->execute([$user_id]);
    $emp_info = $stmtEmpInfo->fetch(PDO::FETCH_ASSOC);
    $user_div_id = $emp_info['division_id'] ?: ($emp_info['department_id'] ?: 0);
    $user_unit_id = $emp_info['unit_id'] ?? 0;
}

$document = null;
$template = null;
if (($action === 'edit' || $action === 'view') && $doc_id > 0) {
    $stmtDoc = $conn->prepare("
        SELECT d.*, dt.name as template_name, dt.content_template, dt.workflow_stages, dt.signer_type,
               dt.header_logo, dt.header_line_1, dt.header_line_2, dt.header_address, dt.header_image,
               e.full_name as creator_name, e.signature_path as creator_signature,
               divs.name as receiver_division_name, u.name as receiver_unit_name
        FROM documents d
        LEFT JOIN document_templates dt ON d.template_id = dt.id
        LEFT JOIN employees e ON d.creator_id = e.id
        LEFT JOIN divisions divs ON d.receiver_division_id = divs.id
        LEFT JOIN units u ON d.receiver_unit_id = u.id
        WHERE d.id = ? AND d.type = 'outgoing'
    ");
    $stmtDoc->execute([$doc_id]);
    $document = $stmtDoc->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        redirect("views/documents/outgoing.php?error=Dokumen+tidak+ditemukan");
    }

    // Access control: Surat Keluar is only viewable by creator, assigned approvers, or colleagues in the creator's unit/division
    if (!$is_admin) {
        $can_access = false;
        if ($document['creator_id'] == $user_id) {
            $can_access = true;
        }

        if (!$can_access && $action === 'view') {
            $stmtChkApp = $conn->prepare("SELECT 1 FROM document_approvals WHERE document_id = ? AND approver_id = ?");
            $stmtChkApp->execute([$doc_id, $user_id]);
            if ($stmtChkApp->fetchColumn()) {
                $can_access = true;
            }
        }

        if (!$can_access && $action === 'view' && $document['status'] !== 'draft') {
            $stmtCreatorEmp = $conn->prepare("SELECT division_id, unit_id FROM employees WHERE id = ?");
            $stmtCreatorEmp->execute([$document['creator_id']]);
            $creator_emp = $stmtCreatorEmp->fetch(PDO::FETCH_ASSOC);
            if ($creator_emp) {
                $c_unit = $creator_emp['unit_id'] ?? 0;
                $c_div = $creator_emp['division_id'] ?? 0;
                if ($user_unit_id != 0 && $c_unit == $user_unit_id) {
                    $can_access = true;
                } elseif ($user_unit_id == 0 && empty($c_unit) && $c_div == $user_div_id) {
                    $can_access = true;
                }
            }
        }

        if (!$can_access) {
            redirect("views/documents/outgoing.php?error=Anda+tidak+memiliki+akses+ke+surat+keluar+ini");
        }

        if ($action === 'edit' && ($document['creator_id'] != $user_id || !in_array($document['status'], ['draft', 'rejected']))) {
            redirect("views/documents/outgoing.php?error=Surat+keluar+tidak+dapat+diedit");
        }
    }
}

// Fetch active templates filtered by user scope & unit
if ($is_admin) {
    $templates = $conn->query("SELECT id, name, code, scope FROM document_templates ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmtTpl = $conn->prepare("
        SELECT id, name, code, scope 
        FROM document_templates 
        WHERE created_by = :uid 
           OR (scope = 'unit' AND unit_id = :unit_id) 
           OR scope = 'global'
           OR (scope IS NULL AND (unit_id IS NULL OR unit_id = :unit_id2))
        ORDER BY name ASC
    ");
    $stmtTpl->execute([':uid' => $user_id, ':unit_id' => $user_unit_id, ':unit_id2' => $user_unit_id]);
    $templates = $stmtTpl->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch active divisions (Bidang) & units for cascading dropdown selection
$divisions = $conn->query("SELECT id, name FROM divisions ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$units = $conn->query("SELECT id, division_id, name FROM units ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Pagination & search for list view
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$limit = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$where_clause = "d.type = 'outgoing'";
$params = [];

if (!$is_admin) {
    $where_clause .= " AND (
        d.creator_id = :creator_id 
        OR (
            d.status != 'draft' AND (
                (e.unit_id = :user_unit_id AND :user_unit_id != 0)
                OR ((e.unit_id IS NULL OR e.unit_id = 0) AND :user_unit_id2 = 0 AND e.division_id = :user_div_id)
            )
        )
        OR EXISTS (SELECT 1 FROM document_approvals da WHERE da.document_id = d.id AND da.approver_id = :approver_uid)
    )";
    $params[':creator_id'] = $user_id;
    $params[':user_unit_id'] = $user_unit_id;
    $params[':user_unit_id2'] = $user_unit_id;
    $params[':user_div_id'] = $user_div_id;
    $params[':approver_uid'] = $user_id;
}

if (!empty($search)) {
    $where_clause .= " AND (d.title LIKE :search OR d.document_number LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($status_filter)) {
    $where_clause .= " AND d.status = :status";
    $params[':status'] = $status_filter;
}

// Get total count
$stmtCount = $conn->prepare("
    SELECT COUNT(*) 
    FROM documents d
    LEFT JOIN document_templates dt ON d.template_id = dt.id
    LEFT JOIN employees e ON d.creator_id = e.id
    LEFT JOIN divisions divs ON d.receiver_division_id = divs.id
    LEFT JOIN units u ON d.receiver_unit_id = u.id
    WHERE $where_clause
");
$stmtCount->execute($params);
$total_rows = $stmtCount->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Fetch list
$stmtList = $conn->prepare("
    SELECT d.id, d.document_number, d.title, d.status, d.created_at, d.receiver_division_id, d.receiver_unit_id, 
           dt.name as template_name, e.full_name as creator_name, 
           divs.name as receiver_division_name, u.name as receiver_unit_name
    FROM documents d
    LEFT JOIN document_templates dt ON d.template_id = dt.id
    LEFT JOIN employees e ON d.creator_id = e.id
    LEFT JOIN divisions divs ON d.receiver_division_id = divs.id
    LEFT JOIN units u ON d.receiver_unit_id = u.id
    WHERE $where_clause
    ORDER BY d.created_at DESC
    LIMIT :limit OFFSET :offset
");
$stmtList->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmtList->bindValue(':offset', $offset, PDO::PARAM_INT);
foreach ($params as $k => $v) {
    $stmtList->bindValue($k, $v);
}
$stmtList->execute();
$documents = $stmtList->fetchAll(PDO::FETCH_ASSOC);

// Handle template selection redirection
if (isset($_GET['select_template'])) {
    $tpl_id = intval($_GET['select_template']);
    redirect("views/documents/outgoing.php?action=new&template_id=" . $tpl_id);
}

// Retrieve template details if starting a new document with template
$new_template_id = isset($_GET['template_id']) ? intval($_GET['template_id']) : 0;
if ($action === 'new' && $new_template_id > 0) {
    $stmtTpl = $conn->prepare("SELECT * FROM document_templates WHERE id = ?");
    $stmtTpl->execute([$new_template_id]);
    $template = $stmtTpl->fetch(PDO::FETCH_ASSOC);

    // Verify user has access to use this template
    if ($template && !$is_admin) {
        $has_access = ($template['created_by'] == $user_id) 
            || ($template['scope'] === 'unit' && $template['unit_id'] == $user_unit_id)
            || ($template['scope'] === 'global')
            || ($template['scope'] === null && ($template['unit_id'] === null || $template['unit_id'] == $user_unit_id));
        if (!$has_access) {
            redirect("views/documents/outgoing.php?error=Anda+tidak+memiliki+akses+ke+template+ini");
        }
    }
}

include '../layouts/header.php';
?>
<!-- Font Awesome CDN -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">

<div class="pb-10">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between border-b border-slate-200 pb-5">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Manajemen Surat Keluar</h1>
            <p class="mt-1 text-sm text-slate-500">Buat, kelola, dan pantau persetujuan surat keluar digital yayasan.</p>
        </div>
        <div class="mt-4 sm:mt-0 flex gap-2">
            <?php if ($action !== 'list'): ?>
                <a href="<?php url('views/documents/outgoing.php'); ?>" class="inline-flex items-center justify-center rounded-xl bg-white border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                    Kembali ke Daftar
                </a>
            <?php else: ?>
                <a href="<?php url('views/documents/templates.php'); ?>" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-indigo-700 transition-colors">
                    <i class="fa-solid fa-plus mr-2 text-xs"></i>
                    Buat Surat Baru
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- VIEW DRAFT / OUTGOING -->
    <?php 
    if ($action === 'view' && $document): 
        // Approvals and signer resolution
        $approvals_list = [];
        $can_approve = false;
        $user_pending_approval_id = 0;
        $active_signer = null;

        $stmtApprovals = $conn->prepare("
            SELECT da.*, e.full_name as approver_name, p.name as approver_position, u.name as approver_unit, e.signature_path
            FROM document_approvals da
            JOIN employees e ON da.approver_id = e.id
            LEFT JOIN positions p ON e.position_id = p.id
            LEFT JOIN units u ON e.unit_id = u.id
            WHERE da.document_id = ?
            ORDER BY da.stage_order ASC, da.id ASC
        ");
        $stmtApprovals->execute([$doc_id]);
        $approvals_list = $stmtApprovals->fetchAll(PDO::FETCH_ASSOC);

        if ($document['status'] === 'pending_approval') {
            foreach ($approvals_list as $appItem) {
                if ($appItem['status'] === 'pending' && ($appItem['approver_id'] == $user_id || $is_admin)) {
                    $can_approve = true;
                    $user_pending_approval_id = $appItem['id'];
                    break;
                }
            }
        }

        // Active signer resolution
        foreach (array_reverse($approvals_list) as $app) {
            if ($app['status'] === 'approved') {
                $active_signer = [
                    'name' => $app['approver_name'],
                    'position' => $app['approver_position'],
                    'signature_path' => $app['signature_path']
                ];
                break;
            }
        }
        if (!$active_signer && !empty($approvals_list)) {
            $firstPending = $approvals_list[0];
            $active_signer = [
                'name' => $firstPending['approver_name'],
                'position' => $firstPending['approver_position'],
                'signature_path' => $firstPending['signature_path']
            ];
        }
        if (!$active_signer) {
            $resolved = getResolvedSignerInfo($conn, $document['creator_id'], $document['signer_type'] ?? 'unit_head');
            $active_signer = [
                'name' => $resolved['name'],
                'position' => $resolved['position'],
                'signature_path' => $resolved['signature_path']
            ];
        }

        $formatted_doc_date = formatIndonesianDate($document['created_at']);
        $status = $document['status'];

        // Prepare clean document view content
        $view_content = $document['content'];
        $view_content = str_replace('{{nomor_surat}}', htmlspecialchars($document['document_number']), $view_content);
        $view_content = str_replace('{{perihal}}', htmlspecialchars($document['title']), $view_content);
        $view_content = str_replace('{{tanggal_surat}}', htmlspecialchars($formatted_doc_date), $view_content);
        if ($active_signer) {
            $view_content = str_replace('{{nama_penandatangan}}', htmlspecialchars($active_signer['name']), $view_content);
            $view_content = str_replace('{{jabatan_penandatangan}}', htmlspecialchars($active_signer['position']), $view_content);
        }

        // Dynamically process signature stamp according to document status
        $view_content = processDocumentSignatureHtml($view_content, $status, $document['qr_token'], $active_signer['signature_path'] ?? '');

        // If template doesn't contain a signature block at all, append standard box
        if (stripos($view_content, 'tanda tangan') === false && stripos($view_content, 'signature-box') === false && $active_signer) {
            $view_content .= renderDocumentSignatureBoxHtml($active_signer['name'], $active_signer['position'], $status, $document['qr_token'], $active_signer['signature_path'] ?? '');
        }

        // Render Kop Surat if not already present
        $has_kop = (strpos($view_content, 'kop-surat') !== false);
        $rendered_kop = (!$has_kop && $document['template_id'] > 0) ? renderDocumentKopHtml($document) : '';

        $badge_class = 'bg-slate-100 text-slate-700 border-slate-200';
        if ($status === 'pending_approval') $badge_class = 'bg-amber-50 text-amber-700 border-amber-200/50';
        elseif ($status === 'completed') $badge_class = 'bg-emerald-50 text-emerald-700 border-emerald-200/50';
        elseif ($status === 'rejected') $badge_class = 'bg-rose-50 text-rose-700 border-rose-200/50';
    ?>
        <div class="mt-8 grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
            <!-- Left: Document Preview (Real A4 Sheet) -->
            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4 gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center rounded-md bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700"><?php echo htmlspecialchars($document['template_name'] ?? 'Manual'); ?></span>
                            <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider <?php echo $badge_class; ?>">
                                <?php echo $status === 'pending_approval' ? 'Pending Approval' : ($status === 'completed' ? 'Selesai & Resmi' : ($status === 'rejected' ? 'Ditolak' : 'Draft')); ?>
                            </span>
                        </div>
                        <h2 class="text-lg font-bold text-slate-800 mt-2"><?php echo htmlspecialchars($document['title']); ?></h2>
                        <p class="text-xs text-slate-400 mt-1">Nomor Surat: <span class="font-bold text-slate-700 font-mono"><?php echo htmlspecialchars($document['document_number']); ?></span></p>
                    </div>
                    <div class="flex items-center gap-2">
                        <?php if ($status === 'completed'): ?>
                            <a href="<?php url('views/documents/preview.php?id=' . $document['id']); ?>" target="_blank" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-3.5 py-2 text-xs font-bold text-white shadow-sm hover:bg-indigo-700 transition-colors">
                                <i class="fa-solid fa-print mr-1.5 text-xs"></i> Cetak / Unduh
                            </a>
                            <a href="<?php url('views/documents/verify.php?token=' . urlencode($document['qr_token'])); ?>" target="_blank" class="inline-flex items-center justify-center rounded-xl bg-slate-100 border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-200 transition-colors">
                                <i class="fa-solid fa-qrcode mr-1.5 text-xs text-indigo-600"></i> QR Verifikasi
                            </a>
                        <?php elseif ($status === 'draft' || $status === 'rejected'): ?>
                            <a href="?action=edit&id=<?php echo $document['id']; ?>" class="inline-flex items-center justify-center rounded-xl bg-white border border-slate-300 px-3.5 py-2 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                                <i class="fa-solid fa-pen mr-1.5 text-xs"></i> Edit
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Document Paper Canvas (Styled Official Paper) -->
                <div class="mt-6 bg-slate-100/80 p-4 sm:p-8 rounded-2xl flex justify-center border border-slate-200 shadow-inner overflow-x-auto">
                    <div class="bg-white shadow-xl border border-slate-300 w-full max-w-[794px] min-h-[960px] p-8 sm:p-14 text-slate-900 relative rounded-sm" style="font-family: 'Times New Roman', Times, serif; font-size: 12pt; line-height: 1.55;">
                        <!-- Watermark if not completed -->
                        <?php if ($status !== 'completed'): ?>
                            <div class="absolute inset-0 flex items-center justify-center opacity-[0.035] select-none pointer-events-none">
                                <span class="text-7xl font-black rotate-[-30deg] tracking-widest text-slate-900">PRATINJAU DRAF</span>
                            </div>
                        <?php endif; ?>

                        <!-- KOP SURAT RESMI -->
                        <?php echo $rendered_kop; ?>

                        <!-- KONTEN SURAT LENGKAP -->
                        <div class="document-content-body not-prose text-slate-900 text-justify" style="font-family: 'Times New Roman', Times, serif; font-size: 12pt; line-height: 1.6;">
                            <?php echo $view_content; ?>
                        </div>
                    </div>
                </div>

                <!-- Options in view mode -->
                <div class="mt-6 flex flex-wrap justify-between items-center gap-3 border-t border-slate-100 pt-4">
                    <div>
                        <a href="<?php url('views/documents/outgoing.php'); ?>" class="inline-flex items-center justify-center rounded-xl bg-slate-100 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200 transition-colors">
                            <i class="fa-solid fa-arrow-left mr-1.5"></i> Kembali ke Daftar
                        </a>
                    </div>
                    <div class="flex gap-2.5">
                        <?php if ($status === 'draft' || $status === 'rejected'): ?>
                            <a href="?action=edit&id=<?php echo $document['id']; ?>" class="inline-flex items-center justify-center rounded-xl bg-white border border-slate-300 px-4 py-2 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                                <i class="fa-solid fa-pen mr-1.5"></i> Edit Draf
                            </a>
                            <button type="button" 
                                    data-id="<?php echo $document['id']; ?>"
                                    data-title="<?php echo htmlspecialchars($document['title'], ENT_QUOTES); ?>"
                                    onclick="triggerSubmitModal(this)" 
                                    class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 transition-colors">
                                <i class="fa-solid fa-paper-plane mr-1.5 text-xs"></i>
                                Kirim Pengajuan Approval
                            </button>
                        <?php endif; ?>

                        <?php if ($can_approve): ?>
                            <button type="button" onclick="openDirectApproveModal('approved')" class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 transition-colors">
                                <i class="fa-solid fa-signature mr-1.5 text-xs"></i>
                                Setujui & Tanda Tangani
                            </button>
                            <button type="button" onclick="openDirectApproveModal('rejected')" class="inline-flex items-center justify-center rounded-xl bg-rose-50 border border-rose-200 px-4 py-2 text-xs font-bold text-rose-700 shadow-sm hover:bg-rose-100 transition-colors">
                                <i class="fa-solid fa-xmark mr-1.5 text-xs"></i>
                                Tolak Surat
                            </button>
                        <?php endif; ?>

                        <?php if ($status === 'completed'): ?>
                            <a href="<?php url('views/documents/preview.php?id=' . $document['id']); ?>" target="_blank" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-indigo-700 transition-colors">
                                <i class="fa-solid fa-print mr-1.5 text-xs"></i>
                                Cetak / Unduh PDF
                            </a>
                            <a href="<?php url('views/documents/verify.php?token=' . urlencode($document['qr_token'])); ?>" target="_blank" class="inline-flex items-center justify-center rounded-xl bg-slate-100 border border-slate-300 px-4 py-2 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-200 transition-colors">
                                <i class="fa-solid fa-qrcode mr-1.5 text-xs text-indigo-600"></i>
                                Cek QR Verifikasi
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right: Metadata & Workflow Status -->
            <div class="space-y-6 sticky top-6">
                <!-- Action Card if current logged-in user is approver -->
                <?php if ($can_approve): ?>
                    <div class="bg-gradient-to-br from-amber-500/10 via-emerald-500/10 to-indigo-500/10 rounded-2xl border-2 border-emerald-500/30 p-5 shadow-sm space-y-3">
                        <div class="flex items-center gap-2">
                            <span class="flex h-3 w-3 rounded-full bg-emerald-500 animate-ping"></span>
                            <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Aksi Penandatangan Dokumen</h3>
                        </div>
                        <p class="text-xs text-slate-600">Dokumen ini saat ini menunggu keputusan persetujuan resmi dari Anda.</p>
                        <div class="flex flex-col gap-2 pt-1">
                            <button type="button" onclick="openDirectApproveModal('approved')" class="w-full inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 transition-colors">
                                <i class="fa-solid fa-signature mr-2"></i>
                                Setujui & Tanda Tangani
                            </button>
                            <button type="button" onclick="openDirectApproveModal('rejected')" class="w-full inline-flex items-center justify-center rounded-xl bg-white border border-rose-200 px-4 py-2 text-xs font-bold text-rose-700 shadow-sm hover:bg-rose-50 transition-colors">
                                <i class="fa-solid fa-xmark mr-2"></i>
                                Tolak Surat
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Info Box: Detail Dokumen -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Detail Dokumen</h3>
                    <div class="mt-4 space-y-3 text-xs">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Pembuat:</span>
                            <span class="font-bold text-slate-700"><?php echo htmlspecialchars($document['creator_name']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Tujuan Surat:</span>
                            <span class="font-bold text-indigo-700">
                                <?php 
                                $dest_view = '-';
                                if (!empty($document['receiver_division_name'])) {
                                    $dest_view = htmlspecialchars($document['receiver_division_name']);
                                    if (!empty($document['receiver_unit_name'])) {
                                        $dest_view .= ' &bull; ' . htmlspecialchars($document['receiver_unit_name']);
                                    }
                                } elseif (!empty($document['receiver_unit_name'])) {
                                    $dest_view = htmlspecialchars($document['receiver_unit_name']);
                                }
                                echo $dest_view;
                                ?>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Tanggal Dibuat:</span>
                            <span class="font-bold text-slate-700"><?php echo date('d M Y, H:i', strtotime($document['created_at'])); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Tipe Dokumen:</span>
                            <span class="font-bold text-slate-700"><?php echo htmlspecialchars($document['template_name'] ?? 'Kustom/Manual'); ?></span>
                        </div>
                        <div class="flex justify-between border-t border-slate-100 pt-2 mt-2">
                            <span class="text-slate-500">Pejabat Pengesah:</span>
                            <span class="font-bold text-slate-800 text-right"><?php echo htmlspecialchars($active_signer['name']); ?><br><span class="text-[10px] text-slate-400 font-normal"><?php echo htmlspecialchars($active_signer['position']); ?></span></span>
                        </div>
                    </div>
                </div>

                <!-- Alur Persetujuan Box -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                            <i class="fa-solid fa-sitemap text-indigo-600"></i>
                            Alur Persetujuan
                        </h3>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase <?php echo $badge_class; ?>"><?php echo $document['status']; ?></span>
                    </div>
                    
                    <div class="mt-5 relative border-l-2 border-slate-200 pl-5 ml-2.5 space-y-6 text-xs">
                        <?php if (!empty($approvals_list)): ?>
                            <?php foreach ($approvals_list as $idx => $appItem): 
                                $app_st = $appItem['status'];
                                $bColor = 'bg-amber-500 ring-amber-100';
                                if ($app_st === 'approved') $bColor = 'bg-emerald-500 ring-emerald-100';
                                elseif ($app_st === 'rejected') $bColor = 'bg-rose-500 ring-rose-100';
                            ?>
                                <div class="relative">
                                    <span class="absolute -left-[27px] top-0.5 flex h-3.5 w-3.5 items-center justify-center rounded-full border-2 border-white ring-4 <?php echo $bColor; ?>"></span>
                                    <div>
                                        <h4 class="font-bold text-slate-800">Tahap <?php echo $appItem['stage_order']; ?>: <?php echo htmlspecialchars($appItem['approver_position'] ?: 'Pejabat Penandatangan'); ?></h4>
                                        <p class="text-slate-600 text-[11px] mt-0.5"><?php echo htmlspecialchars($appItem['approver_name']); ?></p>
                                        
                                        <?php if ($app_st === 'approved'): ?>
                                            <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-[9px] font-bold text-emerald-700 ring-1 ring-inset ring-emerald-600/20 mt-1">
                                                <i class="fa-solid fa-check mr-1 text-[8px]"></i> Disetujui
                                            </span>
                                            <p class="text-[10px] text-slate-400 mt-1"><?php echo date('d M Y, H:i', strtotime($appItem['approved_at'])); ?></p>
                                            <?php if (!empty($appItem['notes'])): ?>
                                                <p class="text-[10px] bg-slate-50 border border-slate-100 rounded-lg p-2 mt-1 italic text-slate-600 font-medium">"<?php echo htmlspecialchars($appItem['notes']); ?>"</p>
                                            <?php endif; ?>
                                        <?php elseif ($app_st === 'rejected'): ?>
                                            <span class="inline-flex items-center rounded-md bg-rose-50 px-2 py-0.5 text-[9px] font-bold text-rose-700 ring-1 ring-inset ring-rose-600/20 mt-1">
                                                <i class="fa-solid fa-xmark mr-1 text-[8px]"></i> Ditolak
                                            </span>
                                            <p class="text-[10px] text-slate-400 mt-1"><?php echo date('d M Y, H:i', strtotime($appItem['approved_at'])); ?></p>
                                            <?php if (!empty($appItem['notes'])): ?>
                                                <p class="text-[10px] bg-rose-50 border border-rose-100 rounded-lg p-2 mt-1 italic text-rose-700 font-medium">"<?php echo htmlspecialchars($appItem['notes']); ?>"</p>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-[9px] font-bold text-amber-700 ring-1 ring-inset ring-amber-600/20 mt-1">
                                                <i class="fa-solid fa-clock mr-1 text-[8px]"></i> Menunggu Keputusan
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <!-- Single stage preview for draft -->
                            <div class="relative">
                                <span class="absolute -left-[27px] top-0.5 flex h-3.5 w-3.5 items-center justify-center rounded-full border-2 border-white ring-4 bg-slate-300 ring-slate-100"></span>
                                <div>
                                    <h4 class="font-bold text-slate-800">Tahap 1: <?php echo htmlspecialchars($active_signer['position']); ?></h4>
                                    <p class="text-slate-600 text-[11px] mt-0.5"><?php echo htmlspecialchars($active_signer['name']); ?></p>
                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[9px] font-bold text-slate-600 mt-1">
                                        Draf Belum Diajukan
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    <!-- FORM DRAFT NEW / EDIT -->
    <?php elseif (($action === 'new' || $action === 'edit')): 
        $is_edit = ($action === 'edit');
        $tpl_id = $is_edit ? $document['template_id'] : $template['id'] ?? 0;
        $active_template = $is_edit ? $document : $template;
        
        // Find custom placeholder names (excluding reserved system tags)
        $reserved_tags = getReservedTemplateTags();
        $placeholder_names = [];
        if ($tpl_id > 0 && !empty($active_template['content_template'])) {
            preg_match_all('/\{\{([^}]+)\}\}/', $active_template['content_template'], $matches);
            if (!empty($matches[1])) {
                $raw_matches = array_unique($matches[1]);
                $placeholder_names = array_values(array_diff($raw_matches, $reserved_tags));
            }
        }
        
        $saved_placeholders = [];
        if ($is_edit && !empty($document['placeholder_values'])) {
            $saved_placeholders = json_decode($document['placeholder_values'], true) ?: [];
        }

        // Detect signer for current creator
        $detectedSigner = getResolvedSignerInfo($conn, $user_id, $active_template['signer_type'] ?? 'unit_head');
    ?>
        <div class="mt-8 max-w-4xl mx-auto bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-800 border-b border-slate-100 pb-4"><?php echo $is_edit ? 'Edit Draft Surat Keluar' : 'Draf Surat Keluar Baru'; ?></h2>

            <form action="<?php url('logic/documents/create_document.php'); ?>" method="POST" class="mt-6 space-y-6">
                <input type="hidden" name="action" value="<?php echo $is_edit ? 'edit_outgoing' : 'create_outgoing'; ?>">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="id" value="<?php echo $document['id']; ?>">
                <?php endif; ?>
                <?php if ($tpl_id > 0): ?>
                    <input type="hidden" name="template_id" value="<?php echo $tpl_id; ?>">
                <?php endif; ?>

                <!-- Judul / Perihal -->
                <div>
                    <label class="block text-sm font-bold text-slate-700">Perihal / Judul Surat <span class="text-rose-500">*</span></label>
                    <input type="text" name="title" required value="<?php echo $is_edit ? htmlspecialchars($document['title']) : ''; ?>"
                           class="mt-2 block w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm bg-slate-50 border p-3 placeholder:text-slate-400 text-slate-700" 
                           placeholder="Contoh: Surat Edaran Pelaksanaan Ujian Sekolah MA">
                </div>

                <!-- Tujuan Surat (Kepada Yth.) -->
                <div>
                    <label class="block text-sm font-bold text-slate-700">Tujuan Surat (Kepada Yth.) <span class="text-rose-500">*</span></label>
                    <input type="text" name="tujuan_text" id="tujuan_text" required 
                           value="<?php echo $is_edit && !empty($document['tujuan_text']) ? htmlspecialchars($document['tujuan_text']) : ''; ?>"
                           class="mt-2 block w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm bg-slate-50 border p-3 placeholder:text-slate-400 text-slate-700" 
                           placeholder="Contoh: Kepala MA As-Sunnah Cirebon">
                    <p class="text-[11px] text-slate-400 mt-1">Nama penerima resmi atau instansi yang dituju (otomatis disarankan saat memilih Bidang/Unit di bawah).</p>
                </div>

                <!-- Tujuan Distribusi (Bidang & Unit) & Tanggal Surat -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-slate-700">Tujuan Bidang <span class="text-rose-500">*</span></label>
                        <select name="receiver_division_id" id="receiver_division_id" required onchange="filterUnitsByDivision(this.value); updateTujuanSuggestion();"
                                class="select-custom mt-2 block w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm bg-slate-50 border p-3 text-slate-700">
                            <option value="">-- Pilih Bidang --</option>
                            <?php foreach ($divisions as $div): ?>
                                <option value="<?php echo $div['id']; ?>" <?php echo $is_edit && isset($document['receiver_division_id']) && $document['receiver_division_id'] == $div['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($div['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700">Unit Spesifik <span class="text-xs font-normal text-slate-400">(Opsional)</span></label>
                        <select name="receiver_unit_id" id="receiver_unit_id" onchange="updateTujuanSuggestion();"
                                class="select-custom mt-2 block w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm bg-slate-50 border p-3 text-slate-700">
                            <option value="">-- Langsung ke Bidang (Semua Unit) --</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700">Tanggal Surat <span class="text-rose-500">*</span></label>
                        <input type="date" name="document_date" required value="<?php echo $is_edit && !empty($document['created_at']) ? date('Y-m-d', strtotime($document['created_at'])) : date('Y-m-d'); ?>"
                               class="mt-2 block w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm bg-slate-50 border p-3 text-slate-700">
                    </div>
                </div>

                <!-- Informative Card: Pejabat Penandatangan Terdeteksi -->
                <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-3">
                        <div class="h-9 w-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold">
                            <i class="fa-solid fa-signature"></i>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-700">Pejabat Penandatangan Resmi:</span>
                            <p class="font-bold text-slate-800 text-sm mt-0.5"><?php echo htmlspecialchars($detectedSigner['name']); ?> <span class="font-normal text-slate-500">(<?php echo htmlspecialchars($detectedSigner['position']); ?>)</span></p>
                        </div>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-white text-indigo-700 border border-indigo-200 shadow-sm self-start sm:self-auto">
                        <i class="fa-solid fa-shield-halved mr-1"></i> Hierarki Terverifikasi
                    </span>
                </div>

                <!-- Template Placeholders fields (Only if custom variables exist) -->
                <?php if ($tpl_id > 0 && !empty($placeholder_names)): ?>
                    <div class="bg-indigo-50/50 rounded-2xl border border-indigo-100/50 p-6 space-y-5">
                        <h3 class="text-xs font-bold text-indigo-900 uppercase tracking-widest border-b border-indigo-100 pb-2">Variabel Khusus Template</h3>
                        <p class="text-[11px] text-indigo-700 mt-1">Lengkapi variabel khusus yang ada pada template ini.</p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-4">
                            <?php foreach ($placeholder_names as $pname): 
                                $val = $is_edit ? ($saved_placeholders[$pname] ?? '') : '';
                            ?>
                                <div>
                                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider"><?php echo str_replace('_', ' ', $pname); ?></label>
                                    <input type="text" name="placeholders[<?php echo $pname; ?>]" required value="<?php echo htmlspecialchars($val); ?>"
                                           class="mt-2 block w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 text-xs bg-white border p-3 placeholder:text-slate-300 text-slate-700"
                                           placeholder="Ketik nilai untuk {{<?php echo $pname; ?>}}">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php elseif ($tpl_id == 0): ?>
                    <!-- Manual Editor Fallback (For Custom Manual Letters) -->
                    <div>
                        <label class="block text-sm font-bold text-slate-700">Isi Surat (HTML/Teks)</label>
                        <p class="text-xs text-slate-400 mt-1">Tulis isi surat lengkap di sini. Gunakan tag paragraf `<p>` untuk merapikan.</p>
                        <textarea name="content" rows="12" required
                                  class="mt-2 block w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm bg-slate-50 border p-3 text-slate-700"
                                  placeholder="Tulis isi surat secara manual di sini..."><?php echo $is_edit ? htmlspecialchars($document['content']) : ''; ?></textarea>
                    </div>
                <?php endif; ?>

                <!-- Form Submit Buttons -->
                <div class="flex justify-end gap-3 border-t border-slate-100 pt-5">
                    <a href="<?php url('views/documents/outgoing.php'); ?>" class="inline-flex items-center justify-center rounded-xl bg-white border border-slate-300 px-5 py-2.5 text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                        Batal
                    </a>
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-indigo-700 transition-colors">
                        Simpan Draft
                    </button>
                </div>
            </form>
        </div>

    <!-- MAIN LIST VIEW -->
    <?php else: ?>
        <!-- Search and Filter Bar -->
        <div class="mt-6 bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-4 justify-between items-center">
            <form class="flex flex-wrap gap-3 w-full" method="GET">
                <input type="hidden" name="action" value="list">
                
                <!-- Search Query -->
                <div class="relative w-full sm:w-80">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <i class="fa-solid fa-search text-slate-400 text-sm"></i>
                    </div>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                        class="block w-full rounded-lg border-slate-200 pl-10 pr-3 pt-2 pb-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 bg-slate-50 border placeholder:text-slate-400 text-slate-600"
                        placeholder="Cari perihal atau nomor surat...">
                </div>

                <!-- Status Filter -->
                <select name="status" onchange="this.form.submit()"
                        class="select-custom rounded-lg border-slate-200 text-sm focus:border-indigo-500 focus:ring-indigo-500 bg-slate-50 border py-2 pl-3 pr-8 text-slate-600">
                    <option value="">Semua Status</option>
                    <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="pending_approval" <?php echo $status_filter === 'pending_approval' ? 'selected' : ''; ?>>Pending Approval</option>
                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Selesai</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Ditolak</option>
                </select>

                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-slate-800 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-slate-700 transition-colors">
                    Filter
                </button>
            </form>
        </div>

        <!-- Documents Table Grid -->
        <div class="mt-6 overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-xl border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left sm:pl-6 w-12">No.</th>
                        <th scope="col" class="px-3 py-3.5 text-left">Nomor Surat</th>
                        <th scope="col" class="px-3 py-3.5 text-left">Perihal / Judul</th>
                        <th scope="col" class="px-3 py-3.5 text-left">Tujuan (Bidang / Unit)</th>
                        <th scope="col" class="px-3 py-3.5 text-left">Template</th>
                        <th scope="col" class="px-3 py-3.5 text-left">Pembuat</th>
                        <th scope="col" class="px-3 py-3.5 text-center">Tanggal</th>
                        <th scope="col" class="px-3 py-3.5 text-center">Status</th>
                        <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <?php if (count($documents) > 0): ?>
                        <?php foreach ($documents as $index => $doc): 
                            $status = $doc['status'];
                            $badge_class = 'bg-slate-100 text-slate-700 border-slate-200';
                            if ($status === 'pending_approval') $badge_class = 'bg-amber-50 text-amber-700 border-amber-200/50';
                            elseif ($status === 'completed') $badge_class = 'bg-emerald-50 text-emerald-700 border-emerald-200/50';
                            elseif ($status === 'rejected') $badge_class = 'bg-rose-50 text-rose-700 border-rose-200/50';
                        ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="whitespace-nowrap py-4 pl-4 pr-3 text-xs text-slate-400 sm:pl-6">
                                    <?php echo $offset + $index + 1; ?>.
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-xs font-bold text-slate-700">
                                    <?php echo htmlspecialchars($doc['document_number'] ?: '-'); ?>
                                </td>
                                <td class="px-3 py-4 text-xs text-slate-600 font-medium max-w-xs truncate">
                                    <?php echo htmlspecialchars($doc['title']); ?>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-xs text-slate-500">
                                    <span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-700 border border-indigo-100">
                                        <?php 
                                        $d_name = $doc['receiver_division_name'] ?? '';
                                        $u_name = $doc['receiver_unit_name'] ?? '';
                                        if (!empty($d_name) && !empty($u_name)) {
                                            echo htmlspecialchars($d_name) . ' &bull; ' . htmlspecialchars($u_name);
                                        } elseif (!empty($d_name)) {
                                            echo htmlspecialchars($d_name);
                                        } elseif (!empty($u_name)) {
                                            echo htmlspecialchars($u_name);
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-xs text-slate-500">
                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 border border-slate-200"><?php echo htmlspecialchars($doc['template_name'] ?? 'Manual'); ?></span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-xs text-slate-500">
                                    <?php echo htmlspecialchars($doc['creator_name']); ?>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-xs text-slate-400 text-center">
                                    <?php echo date('d/m/Y', strtotime($doc['created_at'])); ?>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-center">
                                    <span class="inline-flex items-center rounded-xl border px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-wider <?php echo $badge_class; ?>">
                                        <?php echo $status === 'pending_approval' ? 'Pending Approval' : ($status === 'completed' ? 'Selesai' : ($status === 'rejected' ? 'Ditolak' : 'Draft')); ?>
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-center text-xs font-medium space-x-1.5">
                                    <!-- Aksi: View -->
                                    <a href="<?php url('views/documents/outgoing.php?action=view&id=' . $doc['id']); ?>" class="inline-flex items-center rounded-lg bg-slate-100 p-1.5 text-slate-600 hover:bg-slate-200 transition-colors" title="Buka Pratinjau">
                                        <i class="fa-solid fa-eye text-xs"></i>
                                    </a>

                                    <!-- Aksi: Submit (Only for draft) -->
                                    <?php if ($status === 'draft' || $status === 'rejected'): ?>
                                        <button type="button" 
                                                data-id="<?php echo $doc['id']; ?>"
                                                data-title="<?php echo htmlspecialchars($doc['title'], ENT_QUOTES); ?>"
                                                onclick="triggerSubmitModal(this)"
                                                class="inline-flex items-center rounded-lg bg-emerald-50 p-1.5 text-emerald-600 hover:bg-emerald-100 transition-colors" 
                                                title="Kirim Pengajuan Approval">
                                            <i class="fa-solid fa-paper-plane text-xs"></i>
                                        </button>

                                        <!-- Aksi: Edit -->
                                        <a href="<?php url('views/documents/outgoing.php?action=edit&id=' . $doc['id']); ?>" class="inline-flex items-center rounded-lg bg-indigo-50 p-1.5 text-indigo-600 hover:bg-indigo-100 transition-colors" title="Edit Draf">
                                            <i class="fa-solid fa-pen text-xs"></i>
                                        </a>

                                        <!-- Aksi: Delete -->
                                        <a href="<?php url('logic/documents/create_document.php'); ?>" class="inline-flex items-center rounded-lg bg-rose-50 p-1.5 text-rose-600 hover:bg-rose-100 transition-colors" title="Hapus Draf"
                                           onclick="event.preventDefault(); if(confirm('Hapus draf surat ini secara permanen?')){ document.getElementById('delete-form-<?php echo $doc['id']; ?>').submit(); }">
                                            <i class="fa-solid fa-trash text-xs"></i>
                                        </a>
                                        <form id="delete-form-<?php echo $doc['id']; ?>" action="<?php url('logic/documents/create_document.php'); ?>" method="POST" class="hidden">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $doc['id']; ?>">
                                        </form>
                                    <?php endif; ?>

                                    <!-- Aksi: Print (Only for completed) -->
                                    <?php if ($status === 'completed'): ?>
                                        <a href="<?php url('views/documents/preview.php?id=' . $doc['id']); ?>" target="_blank" class="inline-flex items-center rounded-lg bg-indigo-50 p-1.5 text-indigo-600 hover:bg-indigo-100 transition-colors" title="Cetak Surat">
                                            <i class="fa-solid fa-print text-xs"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="py-10 text-center text-sm text-slate-500 italic">Data surat keluar tidak ditemukan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
        <?php if ($total_pages > 1): ?>
            <div class="mt-6 flex items-center justify-between border-t border-slate-200 px-4 py-3 sm:px-6">
                <div class="flex flex-1 justify-between sm:hidden">
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" class="relative inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Previous</a>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" class="relative ml-3 inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Next</a>
                </div>
                <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs text-slate-700">
                            Menampilkan halaman <span class="font-bold"><?php echo $page; ?></span> dari <span class="font-bold"><?php echo $total_pages; ?></span> halaman (Total <span class="font-bold"><?php echo $total_rows; ?></span> surat).
                        </p>
                    </div>
                    <div>
                        <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                                <a href="?page=<?php echo $p; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" 
                                   class="relative inline-flex items-center px-4 py-2 text-xs font-bold <?php echo $p === $page ? 'bg-indigo-600 text-white z-10' : 'text-slate-900 bg-white border border-slate-300 hover:bg-slate-50'; ?>">
                                    <?php echo $p; ?>
                                </a>
                            <?php endfor; ?>
                        </nav>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Modal Konfirmasi Kirim Pengajuan Approval -->
<div id="submitApprovalModal" style="display: none;" class="fixed inset-0 z-50 items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 transition-opacity">
    <div class="relative w-full max-w-md bg-white rounded-3xl p-6 shadow-2xl border border-slate-100 transform transition-all">
        <!-- Close Button -->
        <button type="button" onclick="closeSubmitApprovalModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 p-1 rounded-lg">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <div class="text-center">
            <!-- Icon -->
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600 mb-4 ring-8 ring-emerald-50">
                <i class="fa-solid fa-paper-plane text-xl"></i>
            </div>

            <!-- Title & Desc -->
            <h3 class="text-lg font-bold text-slate-900">Kirim Pengajuan Approval?</h3>
            <p class="text-xs text-slate-500 mt-1.5 leading-relaxed">
                Surat ini akan dikirimkan ke alur persetujuan pejabat/kabid yang berwenang untuk ditindaklanjuti.
            </p>

            <!-- Document Title Snippet -->
            <div class="mt-4 rounded-2xl bg-slate-50 border border-slate-200/60 p-3 text-left">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Dokumen yang Diajukan</span>
                <p id="modalSubmitDocTitle" class="text-xs font-bold text-slate-800 mt-0.5 truncate"></p>
            </div>

            <!-- Form & Action Buttons -->
            <form action="<?php url('logic/documents/submit_document.php'); ?>" method="POST" class="mt-6 flex items-center justify-end gap-3">
                <input type="hidden" name="id" id="modalSubmitDocId" value="0">
                
                <button type="button" onclick="closeSubmitApprovalModal()" 
                        class="w-full inline-flex justify-center items-center rounded-xl bg-slate-100 px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-200 transition-colors">
                    Batal
                </button>
                
                <button type="submit" 
                        class="w-full inline-flex justify-center items-center rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-emerald-600/20 hover:bg-emerald-700 transition-colors">
                    <i class="fa-solid fa-paper-plane mr-1.5 text-xs"></i>
                    Ya, Kirim Sekarang
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Modal Direct Approve / Reject -->
<div id="directApproveModal" style="display: none;" class="fixed inset-0 z-50 items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 transition-opacity">
    <div class="relative w-full max-w-md bg-white rounded-3xl p-6 shadow-2xl border border-slate-100 transform transition-all">
        <!-- Close Button -->
        <button type="button" onclick="closeDirectApproveModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 p-1 rounded-lg">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <div class="text-center">
            <!-- Icon -->
            <div id="approveModalIcon" class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600 mb-4 ring-8 ring-emerald-50">
                <i class="fa-solid fa-signature text-xl"></i>
            </div>

            <!-- Title & Desc -->
            <h3 id="approveModalTitle" class="text-lg font-bold text-slate-900">Setujui & Tanda Tangani?</h3>
            <p id="approveModalDesc" class="text-xs text-slate-500 mt-1.5 leading-relaxed">
                Anda akan membubuhkan persetujuan resmi dan tanda tangan digital pada dokumen ini.
            </p>

            <form action="<?php url('logic/documents/approve_document.php'); ?>" method="POST" onsubmit="return validateDirectApprove()" class="mt-5 space-y-4 text-left">
                <input type="hidden" name="id" value="<?php echo $doc_id ?? 0; ?>">
                <input type="hidden" name="status" id="approveModalStatus" value="approved">
                <input type="hidden" name="redirect_back" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? ''); ?>">

                <div>
                    <label id="approveNotesLabel" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Catatan Persetujuan (Opsional)</label>
                    <textarea name="notes" id="approveModalNotes" rows="3"
                              class="mt-1.5 block w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 text-xs bg-slate-50 border p-3 text-slate-700 placeholder:text-slate-400"
                              placeholder="Ketik catatan atau instruksi jika ada..."></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" onclick="closeDirectApproveModal()" 
                            class="w-full inline-flex justify-center items-center rounded-xl bg-slate-100 px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-200 transition-colors">
                        Batal
                    </button>
                    
                    <button type="submit" id="approveModalSubmitBtn"
                            class="w-full inline-flex justify-center items-center rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-emerald-600/20 hover:bg-emerald-700 transition-colors">
                        <i class="fa-solid fa-check mr-1.5 text-xs"></i>
                        Ya, Konfirmasi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script nonce="<?php echo csp_nonce(); ?>">
const allUnits = <?php echo json_encode($units ?? []); ?>;
const savedUnitId = <?php echo json_encode((isset($action) && $action === 'edit' && !empty($document['receiver_unit_id'])) ? $document['receiver_unit_id'] : null); ?>;

function filterUnitsByDivision(divId, preselectedUnitId = null) {
    const unitSelect = document.getElementById('receiver_unit_id');
    if (!unitSelect) return;

    unitSelect.innerHTML = '<option value="">-- Langsung ke Bidang (Semua Unit) --</option>';

    if (!divId) return;

    const filtered = allUnits.filter(u => u.division_id == divId);
    filtered.forEach(u => {
        const opt = document.createElement('option');
        opt.value = u.id;
        opt.textContent = u.name;
        if (preselectedUnitId && u.id == preselectedUnitId) {
            opt.selected = true;
        }
        unitSelect.appendChild(opt);
    });
}

function updateTujuanSuggestion() {
    const tujuanInput = document.getElementById('tujuan_text');
    if (!tujuanInput) return;
    const unitSelect = document.getElementById('receiver_unit_id');
    const divSelect = document.getElementById('receiver_division_id');
    if (unitSelect && unitSelect.value) {
        const uText = unitSelect.options[unitSelect.selectedIndex].text;
        tujuanInput.value = 'Kepala ' + uText;
    } else if (divSelect && divSelect.value) {
        const dText = divSelect.options[divSelect.selectedIndex].text;
        tujuanInput.value = 'Kepala Bidang ' + dText;
    }
}

function triggerSubmitModal(btn) {
    const docId = btn.getAttribute('data-id');
    const docTitle = btn.getAttribute('data-title');
    
    const modal = document.getElementById('submitApprovalModal');
    const formIdInput = document.getElementById('modalSubmitDocId');
    const docTitleText = document.getElementById('modalSubmitDocTitle');
    
    if (formIdInput) formIdInput.value = docId;
    if (docTitleText) docTitleText.textContent = docTitle ? `"${docTitle}"` : 'Draf Surat';
    
    if (modal) {
        modal.style.display = 'flex';
    }
}

function closeSubmitApprovalModal() {
    const modal = document.getElementById('submitApprovalModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function openDirectApproveModal(action) {
    const modal = document.getElementById('directApproveModal');
    const statusInput = document.getElementById('approveModalStatus');
    const titleEl = document.getElementById('approveModalTitle');
    const descEl = document.getElementById('approveModalDesc');
    const iconEl = document.getElementById('approveModalIcon');
    const notesLabel = document.getElementById('approveNotesLabel');
    const submitBtn = document.getElementById('approveModalSubmitBtn');

    if (!modal) return;
    statusInput.value = action;

    if (action === 'rejected') {
        titleEl.textContent = 'Tolak Surat Keluar?';
        descEl.textContent = 'Dokumen akan ditolak dan dikembalikan ke pembuat draf disertai alasan penolakan resmi.';
        iconEl.className = 'mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-rose-100 text-rose-600 mb-4 ring-8 ring-rose-50';
        iconEl.innerHTML = '<i class="fa-solid fa-xmark text-xl"></i>';
        notesLabel.innerHTML = 'Alasan Penolakan <span class="text-rose-500">* (Wajib diisi)</span>';
        submitBtn.className = 'w-full inline-flex justify-center items-center rounded-xl bg-rose-600 px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-rose-600/20 hover:bg-rose-700 transition-colors';
        submitBtn.innerHTML = '<i class="fa-solid fa-xmark mr-1.5 text-xs"></i> Ya, Tolak Surat';
    } else {
        titleEl.textContent = 'Setujui & Tanda Tangani?';
        descEl.textContent = 'Anda akan membubuhkan persetujuan resmi dan tanda tangan digital pada dokumen ini.';
        iconEl.className = 'mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600 mb-4 ring-8 ring-emerald-50';
        iconEl.innerHTML = '<i class="fa-solid fa-signature text-xl"></i>';
        notesLabel.innerHTML = 'Catatan Persetujuan (Opsional)';
        submitBtn.className = 'w-full inline-flex justify-center items-center rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-emerald-600/20 hover:bg-emerald-700 transition-colors';
        submitBtn.innerHTML = '<i class="fa-solid fa-check mr-1.5 text-xs"></i> Ya, Setujui & Tanda Tangani';
    }

    modal.style.display = 'flex';
}

function closeDirectApproveModal() {
    const modal = document.getElementById('directApproveModal');
    if (modal) modal.style.display = 'none';
}

function validateDirectApprove() {
    const status = document.getElementById('approveModalStatus').value;
    const notes = document.getElementById('approveModalNotes').value.trim();
    if (status === 'rejected' && notes.length === 0) {
        alert('Mohon tuliskan alasan penolakan secara jelas.');
        return false;
    }
    return true;
}

<?php include '../layouts/footer.php'; ?>
