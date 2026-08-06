<?php
// views/documents/outgoing.php
require_once '../../config/app.php';
require_once '../../config/database.php';

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

$document = null;
$template = null;
if (($action === 'edit' || $action === 'view') && $doc_id > 0) {
    $stmtDoc = $conn->prepare("
        SELECT d.*, dt.name as template_name, dt.content_template, dt.workflow_stages, e.full_name as creator_name,
               divs.name as receiver_division_name, u.name as receiver_unit_name
        FROM documents d
        LEFT JOIN document_templates dt ON d.template_id = dt.id
        LEFT JOIN employees e ON d.creator_id = e.id
        LEFT JOIN divisions divs ON d.receiver_division_id = divs.id
        LEFT JOIN units u ON d.receiver_unit_id = u.id
        WHERE d.id = ?
    ");
    $stmtDoc->execute([$doc_id]);
    $document = $stmtDoc->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        redirect("views/documents/outgoing.php?error=Dokumen+tidak+ditemukan");
    }
}

// Fetch active templates for dropdown/modal
$templates = $conn->query("SELECT id, name, code FROM document_templates ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

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
    $stmtEmpInfo = $conn->prepare("SELECT division_id, department_id, unit_id FROM employees WHERE id = ?");
    $stmtEmpInfo->execute([$user_id]);
    $emp_info = $stmtEmpInfo->fetch(PDO::FETCH_ASSOC);
    $user_div_id = $emp_info['division_id'] ?: ($emp_info['department_id'] ?: 0);
    $user_unit_id = $emp_info['unit_id'] ?? 0;

    $where_clause .= " AND (
        d.creator_id = :creator_id 
        OR (
            d.status != 'draft' AND (
                (d.receiver_division_id = :user_div_id AND (d.receiver_unit_id IS NULL OR d.receiver_unit_id = :user_unit_id OR :user_unit_id = 0))
                OR (d.receiver_unit_id = :user_unit_id AND :user_unit_id != 0)
            )
        )
    )";
    $params[':creator_id'] = $user_id;
    $params[':user_div_id'] = $user_div_id;
    $params[':user_unit_id'] = $user_unit_id;
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
}

include '../layouts/header.php';
?>
<!-- Font Awesome CDN -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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
    <?php if ($action === 'view' && $document): ?>
        <div class="mt-8 grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left: Document Preview -->
            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div>
                        <span class="inline-flex items-center rounded-md bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600"><?php echo htmlspecialchars($document['template_name'] ?? 'Manual'); ?></span>
                        <h2 class="text-lg font-bold text-slate-800 mt-2"><?php echo htmlspecialchars($document['title']); ?></h2>
                        <p class="text-xs text-slate-400 mt-1">Nomor Surat: <span class="font-bold text-slate-600"><?php echo htmlspecialchars($document['document_number']); ?></span></p>
                    </div>
                    <div>
                        <?php 
                        $status = $document['status'];
                        $badge_class = 'bg-slate-100 text-slate-700 border-slate-200';
                        if ($status === 'pending_approval') $badge_class = 'bg-amber-50 text-amber-700 border-amber-200/50';
                        elseif ($status === 'completed') $badge_class = 'bg-emerald-50 text-emerald-700 border-emerald-200/50';
                        elseif ($status === 'rejected') $badge_class = 'bg-rose-50 text-rose-700 border-rose-200/50';
                        ?>
                        <span class="inline-flex items-center rounded-xl border px-3 py-1 text-xs font-bold uppercase tracking-wider <?php echo $badge_class; ?>">
                            <?php echo $status === 'pending_approval' ? 'Pending Approval' : ($status === 'completed' ? 'Selesai' : ($status === 'rejected' ? 'Ditolak' : 'Draft')); ?>
                        </span>
                    </div>
                </div>

                <!-- Document Content Container (styled paper look) -->
                <div class="mt-6 border border-slate-300 rounded-xl bg-slate-50 p-8 min-h-[400px] overflow-auto relative">
                    <!-- Preview Watermark -->
                    <?php if ($status !== 'completed'): ?>
                        <div class="absolute inset-0 flex items-center justify-center opacity-[0.03] select-none pointer-events-none">
                            <span class="text-7xl font-black rotate-45">PRATINJAU DRAF</span>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Real Letter Output -->
                    <div class="prose max-w-none text-slate-800 text-sm leading-relaxed" style="font-family: serif;">
                        <?php echo $document['content']; ?>
                    </div>
                </div>

                <!-- Options in view mode -->
                <div class="mt-6 flex justify-end gap-3">
                    <?php if ($status === 'draft' || $status === 'rejected'): ?>
                        <button type="button" 
                                data-id="<?php echo $document['id']; ?>"
                                data-title="<?php echo htmlspecialchars($document['title'], ENT_QUOTES); ?>"
                                onclick="triggerSubmitModal(this)" 
                                class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-emerald-700 transition-colors">
                            <i class="fa-solid fa-paper-plane mr-2 text-xs"></i>
                            Kirim Pengajuan Approval
                        </button>
                    <?php endif; ?>
                    <?php if ($status === 'completed'): ?>
                        <a href="<?php url('views/documents/preview.php?id=' . $document['id']); ?>" target="_blank" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-indigo-700 transition-colors">
                            <i class="fa-solid fa-print mr-2 text-xs"></i>
                            Cetak / Unduh PDF
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: Metadata & Workflow Status -->
            <div class="space-y-6">
                <!-- Info Box -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Detail Dokumen</h3>
                    <div class="mt-4 space-y-3.5 text-xs">
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
                    </div>
                </div>

                <!-- Workflow Process Trace -->
                <?php if ($document['template_id'] > 0): ?>
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Alur Persetujuan</h3>
                        <div class="mt-5 relative border-l border-slate-200 pl-5 ml-2.5 space-y-6 text-xs">
                            <?php
                            // Fetch all workflow steps from template, and their approval logs
                            $stages_def = json_decode($document['workflow_stages'], true) ?: [];
                            
                            // Fetch resolved approvals
                            $stmtApprovals = $conn->prepare("SELECT * FROM document_approvals WHERE document_id = ? ORDER BY stage_order ASC");
                            $stmtApprovals->execute([$doc_id]);
                            $resolves = $stmtApprovals->fetchAll(PDO::FETCH_ASSOC);
                            
                            $step_map = [];
                            foreach ($resolves as $res) {
                                $step_map[$res['stage_order']][] = $res;
                            }

                            foreach ($stages_def as $idx => $stg):
                                $step_num = $stg['step'];
                                $stage_resolves = $step_map[$step_num] ?? [];
                                
                                // Determine step status
                                $step_status = 'waiting'; // waiting, pending, approved, rejected
                                $approved_by_name = '';
                                $date_string = '';
                                $note_string = '';

                                if (!empty($stage_resolves)) {
                                    $has_reject = false;
                                    $has_pending = false;
                                    $has_approve = false;
                                    
                                    foreach ($stage_resolves as $sr) {
                                        if ($sr['status'] === 'rejected') $has_reject = true;
                                        elseif ($sr['status'] === 'pending') $has_pending = true;
                                        elseif ($sr['status'] === 'approved') {
                                            $has_approve = true;
                                            // Get employee name
                                            $stmtEmpName = $conn->prepare("SELECT full_name FROM employees WHERE id = ?");
                                            $stmtEmpName->execute([$sr['approver_id']]);
                                            $approved_by_name = $stmtEmpName->fetchColumn();
                                            $date_string = date('d M Y, H:i', strtotime($sr['approved_at']));
                                            if (!empty($sr['notes'])) $note_string = $sr['notes'];
                                        }
                                    }

                                    if ($has_reject) $step_status = 'rejected';
                                    elseif ($has_pending) $step_status = 'pending';
                                    elseif ($has_approve) $step_status = 'approved';
                                } else {
                                    // If previous steps are all approved, and document is pending_approval, current step is pending
                                    if ($document['status'] === 'pending_approval') {
                                        // Simple logic: if previous step has no resolutions, we remain waiting. If previous step is approved, this becomes pending.
                                        $prev_all_approved = true;
                                        for ($p = 1; $p < $step_num; $p++) {
                                            $prev_stage_res = $step_map[$p] ?? [];
                                            $prev_ok = false;
                                            foreach ($prev_stage_res as $psr) {
                                                if ($psr['status'] === 'approved') $prev_ok = true;
                                            }
                                            if (!$prev_ok) $prev_all_approved = false;
                                        }
                                        if ($prev_all_approved) $step_status = 'pending';
                                    }
                                }

                                // Style based on status
                                $bullet_color = 'bg-slate-200 border-white ring-slate-100';
                                if ($step_status === 'approved') $bullet_color = 'bg-emerald-500 border-white ring-emerald-100';
                                elseif ($step_status === 'pending') $bullet_color = 'bg-amber-500 border-white ring-amber-100';
                                elseif ($step_status === 'rejected') $bullet_color = 'bg-rose-500 border-white ring-rose-100';
                            ?>
                                <div class="relative">
                                    <!-- Bullet -->
                                    <span class="absolute -left-[29px] top-0.5 flex h-4 w-4 items-center justify-center rounded-full border-2 ring-4 <?php echo $bullet_color; ?>"></span>
                                    
                                    <div>
                                        <h4 class="font-bold text-slate-800">Tahap <?php echo $step_num; ?>: <?php echo htmlspecialchars($stg['position_name']); ?></h4>
                                        
                                        <?php if ($step_status === 'approved'): ?>
                                            <p class="text-slate-500 mt-0.5">Disetujui oleh: <span class="font-bold text-emerald-600"><?php echo htmlspecialchars($approved_by_name); ?></span></p>
                                            <p class="text-[10px] text-slate-400 mt-0.5"><?php echo $date_string; ?></p>
                                            <?php if (!empty($note_string)): ?>
                                                <p class="text-[10px] bg-slate-50 border border-slate-100 rounded-lg p-2 mt-1 italic text-slate-600 font-medium">"<?php echo htmlspecialchars($note_string); ?>"</p>
                                            <?php endif; ?>
                                        <?php elseif ($step_status === 'pending'): ?>
                                            <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-[9px] font-bold text-amber-700 ring-1 ring-inset ring-amber-600/20 mt-1">Menunggu Keputusan</span>
                                        <?php elseif ($step_status === 'rejected'): ?>
                                            <span class="inline-flex items-center rounded-md bg-rose-50 px-2 py-0.5 text-[9px] font-bold text-rose-700 ring-1 ring-inset ring-rose-600/20 mt-1">Ditolak</span>
                                        <?php else: ?>
                                            <p class="text-slate-400 mt-0.5">Belum dimulai</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <!-- FORM DRAFT NEW / EDIT -->
    <?php elseif (($action === 'new' || $action === 'edit')): 
        $is_edit = ($action === 'edit');
        $tpl_id = $is_edit ? $document['template_id'] : $template['id'] ?? 0;
        $active_template = $is_edit ? $document : $template;
        
        // Find placeholder names in template content
        $placeholder_names = [];
        if ($tpl_id > 0 && !empty($active_template['content_template'])) {
            preg_match_all('/\{\{([^}]+)\}\}/', $active_template['content_template'], $matches);
            if (!empty($matches[1])) {
                $placeholder_names = array_unique($matches[1]);
            }
        }
        
        $saved_placeholders = [];
        if ($is_edit && !empty($document['placeholder_values'])) {
            $saved_placeholders = json_decode($document['placeholder_values'], true) ?: [];
        }
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
                    <label class="block text-sm font-bold text-slate-700">Perihal / Judul Surat</label>
                    <input type="text" name="title" required value="<?php echo $is_edit ? htmlspecialchars($document['title']) : ''; ?>"
                           class="mt-2 block w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm bg-slate-50 border p-3 placeholder:text-slate-400 text-slate-700" 
                           placeholder="Contoh: Surat Penunjukan Panitia Ujian Tengah Semester">
                </div>

                <!-- Tujuan Bidang (Wajib) & Unit (Opsional) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-slate-700">Tujuan Bidang <span class="text-rose-500">*</span></label>
                        <select name="receiver_division_id" id="receiver_division_id" required onchange="filterUnitsByDivision(this.value)"
                                class="select-custom mt-2 block w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm bg-slate-50 border p-3 text-slate-700">
                            <option value="">-- Pilih Bidang --</option>
                            <?php foreach ($divisions as $div): ?>
                                <option value="<?php echo $div['id']; ?>" <?php echo $is_edit && isset($document['receiver_division_id']) && $document['receiver_division_id'] == $div['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($div['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700">Unit Spesifik <span class="text-xs font-normal text-slate-400">(Opsional)</span></label>
                        <select name="receiver_unit_id" id="receiver_unit_id"
                                class="select-custom mt-2 block w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm bg-slate-50 border p-3 text-slate-700">
                            <option value="">-- Langsung ke Bidang (Semua Unit) --</option>
                        </select>
                    </div>
                </div>

                <!-- Template Placeholders fields -->
                <?php if ($tpl_id > 0 && !empty($placeholder_names)): ?>
                    <div class="bg-indigo-50/50 rounded-2xl border border-indigo-100/50 p-6 space-y-5">
                        <h3 class="text-xs font-bold text-indigo-900 uppercase tracking-widest border-b border-indigo-100 pb-2">Variabel Template</h3>
                        <p class="text-[11px] text-indigo-700 mt-1">Masukkan rincian data di bawah untuk otomatis dimasukkan ke dalam kerangka surat.</p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-4">
                            <?php foreach ($placeholder_names as $pname): 
                                // Auto check fallback for creator name, date, etc.
                                $fallback_val = '';
                                if ($pname === 'nama') $fallback_val = $_SESSION['user_name'];
                                elseif ($pname === 'jabatan') $fallback_val = $_SESSION['position_name'];
                                elseif ($pname === 'tanggal') $fallback_val = date('d F Y');
                                
                                $val = $is_edit ? ($saved_placeholders[$pname] ?? '') : $fallback_val;
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
                <?php else: ?>
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

<script>
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

document.addEventListener('DOMContentLoaded', () => {
    const divSelect = document.getElementById('receiver_division_id');
    if (divSelect && divSelect.value) {
        filterUnitsByDivision(divSelect.value, savedUnitId);
    }
});
</script>

<?php include '../layouts/footer.php'; ?>
