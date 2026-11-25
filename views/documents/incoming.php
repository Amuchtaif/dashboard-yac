<?php
// views/documents/incoming.php
require_once '../../config/app.php';
require_once '../../config/database.php';
require_once '../../logic/helpers/document_helper.php';

check_login();
check_permission('document.create');

$page_title = "Surat Masuk";

$db = new Database();
$conn = $db->getConnection();
$user_id = $_SESSION['user_id'];
$is_admin = isset($_SESSION['position_name']) && $_SESSION['position_name'] === 'Administrator';

// Action handling
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$doc_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch logged-in employee's division & unit details for target filtering
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
$disposition_history = [];
$resolved_signer_name = '';
$resolved_signer_position = '';
$resolved_signature_path = '';

if (($action === 'edit' || $action === 'disposition' || $action === 'view') && $doc_id > 0) {
    $stmtDoc = $conn->prepare("
        SELECT d.*, dt.name as template_name, dt.content_template, dt.signer_type,
               dt.header_logo, dt.header_line_1, dt.header_line_2, dt.header_address, dt.header_image,
               e.full_name as creator_name, e.signature_path as creator_signature,
               p.name as creator_position, u_creator.name as creator_unit_name,
               divs.name as receiver_division_name, u.name as receiver_unit_name
        FROM documents d
        LEFT JOIN document_templates dt ON d.template_id = dt.id
        LEFT JOIN employees e ON d.creator_id = e.id
        LEFT JOIN positions p ON e.position_id = p.id
        LEFT JOIN units u_creator ON e.unit_id = u_creator.id
        LEFT JOIN divisions divs ON d.receiver_division_id = divs.id
        LEFT JOIN units u ON d.receiver_unit_id = u.id
        WHERE d.id = ?
    ");
    $stmtDoc->execute([$doc_id]);
    $document = $stmtDoc->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        redirect("views/documents/incoming.php?error=Dokumen+tidak+ditemukan");
    }

    // Access control for incoming document
    if (!$is_admin) {
        $can_access = false;

        if ($document['type'] === 'incoming') {
            // External incoming: accessible if addressed to user's unit/division, or user registered it
            if ($user_unit_id != 0 && $document['receiver_unit_id'] == $user_unit_id) {
                $can_access = true;
            } elseif (($document['receiver_unit_id'] === null || $document['receiver_unit_id'] == 0) &&
                      ($document['receiver_division_id'] == $user_div_id || $document['receiver_department_id'] == $user_div_id)) {
                $can_access = true;
            } elseif ($document['creator_id'] == $user_id) {
                $can_access = true;
            }
        } elseif ($document['type'] === 'outgoing') {
            // Internal document in Surat Masuk: MUST BE COMPLETED, and addressed to user's unit/division
            if ($document['status'] === 'completed') {
                if ($user_unit_id != 0 && $document['receiver_unit_id'] == $user_unit_id) {
                    $can_access = true;
                } elseif (($document['receiver_unit_id'] === null || $document['receiver_unit_id'] == 0) &&
                          $document['receiver_division_id'] == $user_div_id) {
                    $can_access = true;
                }
            }
        }

        // Check if dispositioned to this user
        if (!$can_access) {
            $stmtDispCheck = $conn->prepare("SELECT 1 FROM document_dispositions WHERE document_id = ? AND to_user_id = ?");
            $stmtDispCheck->execute([$doc_id, $user_id]);
            if ($stmtDispCheck->fetchColumn()) {
                $can_access = true;
            }
        }

        if (!$can_access) {
            redirect("views/documents/incoming.php?error=Anda+tidak+memiliki+akses+ke+surat+masuk+ini");
        }

        // Only external incoming letters can be edited, and only by creator or admin
        if ($action === 'edit' && ($document['type'] !== 'incoming' || $document['creator_id'] != $user_id)) {
            redirect("views/documents/incoming.php?error=Surat+masuk+ini+tidak+dapat+diedit");
        }
    }

    // Resolve signer info for internal outgoing letters
    if ($document['type'] === 'outgoing') {
        $creator_signers = getDocumentSignersForEmployee($conn, $document['creator_id']);
        $signer_type = $document['signer_type'] ?? 'unit_head';
        $target_signer = $creator_signers[$signer_type] ?? ($creator_signers['unit_head'] ?? $creator_signers['division_head']);
        $resolved_signer_name = $target_signer['full_name'] ?? $document['creator_name'];
        $resolved_signer_position = $target_signer['position_name'] ?? 'Pejabat Berwenang';
        $resolved_signature_path = $target_signer['signature_path'] ?? ($document['creator_signature'] ?? '');
    }

    // Fetch disposition history for this document
    $stmtHist = $conn->prepare("
        SELECT dd.*, e_from.full_name as from_name, e_to.full_name as to_name,
               p_from.name as from_position, p_to.name as to_position
        FROM document_dispositions dd
        JOIN employees e_from ON dd.from_user_id = e_from.id
        JOIN employees e_to ON dd.to_user_id = e_to.id
        LEFT JOIN positions p_from ON e_from.position_id = p_from.id
        LEFT JOIN positions p_to ON e_to.position_id = p_to.id
        WHERE dd.document_id = ?
        ORDER BY dd.created_at ASC
    ");
    $stmtHist->execute([$doc_id]);
    $disposition_history = $stmtHist->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch all units for dropdown selection
$units = $conn->query("SELECT id, name FROM units ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch active employees for disposition routing
$employees = $conn->query("
    SELECT e.id, e.full_name, p.name as position_name 
    FROM employees e 
    LEFT JOIN positions p ON e.position_id = p.id 
    WHERE e.status = 'active' AND e.id != $user_id
    ORDER BY e.full_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Pagination & search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$params = [];
if ($is_admin) {
    // Administrator sees external incoming letters AND completed internal letters addressed to any division/unit
    $where_clause = "(d.type = 'incoming' OR (d.type = 'outgoing' AND d.status = 'completed' AND d.receiver_division_id IS NOT NULL))";
} else {
    // Staff sees external incoming letters addressed to their unit/division OR completed internal letters addressed to their unit/division
    $where_clause = "(
        (d.type = 'incoming' AND (
            (d.receiver_unit_id = :user_unit_id AND :user_unit_id != 0)
            OR ((d.receiver_unit_id IS NULL OR d.receiver_unit_id = 0) AND (d.receiver_division_id = :user_div_id OR d.receiver_department_id = :user_div_id))
            OR d.creator_id = :user_id
            OR EXISTS (SELECT 1 FROM document_dispositions disp WHERE disp.document_id = d.id AND disp.to_user_id = :disp_uid)
        ))
        OR (d.type = 'outgoing' AND d.status = 'completed' AND (
            (d.receiver_unit_id = :user_unit_id2 AND :user_unit_id2 != 0)
            OR ((d.receiver_unit_id IS NULL OR d.receiver_unit_id = 0) AND d.receiver_division_id = :user_div_id2)
            OR EXISTS (SELECT 1 FROM document_dispositions disp2 WHERE disp2.document_id = d.id AND disp2.to_user_id = :disp_uid2)
        ))
    )";
    $params[':user_unit_id'] = $user_unit_id;
    $params[':user_div_id'] = $user_div_id;
    $params[':user_id'] = $user_id;
    $params[':disp_uid'] = $user_id;
    $params[':user_unit_id2'] = $user_unit_id;
    $params[':user_div_id2'] = $user_div_id;
    $params[':disp_uid2'] = $user_id;
}

if (!empty($search)) {
    $where_clause .= " AND (d.title LIKE :search OR d.document_number LIKE :search OR d.sender LIKE :search OR e.full_name LIKE :search)";
    $params[':search'] = "%$search%";
}

// Get total count
$stmtCount = $conn->prepare("
    SELECT COUNT(*) 
    FROM documents d
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
    SELECT d.id, d.document_number, d.title, d.type, d.status, d.file_path, d.created_at, d.creator_id,
           COALESCE(NULLIF(d.sender, ''), e.full_name, 'Internal') as sender_name,
           divs.name as receiver_division_name, u.name as receiver_unit_name
    FROM documents d
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

include '../layouts/header.php';
?>
<!-- Font Awesome CDN -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">

<div class="pb-10">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between border-b border-slate-200 pb-5">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Manajemen Surat Masuk</h1>
            <p class="mt-1 text-sm text-slate-500">Registrasi surat eksternal yang masuk dan teruskan melalui disposisi pimpinan.</p>
        </div>
        <div class="mt-4 sm:mt-0 flex gap-2">
            <?php if ($action !== 'list'): ?>
                <a href="<?php url('views/documents/incoming.php'); ?>" class="inline-flex items-center justify-center rounded-xl bg-white border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                    Kembali ke Daftar
                </a>
            <?php else: ?>
                <a href="?action=new" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-indigo-700 transition-colors">
                    <i class="fa-solid fa-plus mr-2 text-xs"></i>
                    Catat Surat Masuk
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- FORM DISPOSISI -->
    <?php if ($action === 'disposition' && $document): 
        $disp_sender = ($document['type'] === 'outgoing') ? ($document['creator_name'] . ' (' . ($document['creator_unit_name'] ?: 'Yayasan') . ')') : htmlspecialchars($document['sender']);
    ?>
        <div class="mt-8 max-w-2xl mx-auto bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
            <div class="border-b border-slate-100 pb-4">
                <span class="inline-flex items-center rounded-md bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700">Aksi Disposisi</span>
                <h2 class="text-lg font-bold text-slate-800 mt-2">Disposisikan Surat: "<?php echo htmlspecialchars($document['title']); ?>"</h2>
                <p class="text-xs text-slate-400 mt-1">Pengirim: <span class="font-bold text-slate-600"><?php echo $disp_sender; ?></span> &bull; No: <?php echo htmlspecialchars($document['document_number']); ?></p>
            </div>

            <form action="<?php url('logic/documents/disposition_document.php'); ?>" method="POST" class="mt-6 space-y-6">
                <input type="hidden" name="document_id" value="<?php echo $document['id']; ?>">

                <!-- Penerima Disposisi -->
                <div>
                    <label class="block text-sm font-bold text-slate-700">Diberikan Kepada (Pegawai)</label>
                    <select name="to_user_id" required
                            class="select-custom mt-2 block w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm bg-slate-50 border p-3 text-slate-700">
                        <option value="">-- Pilih Pegawai Penerima --</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['full_name']); ?> (<?php echo htmlspecialchars($emp['position_name'] ?? 'Pegawai'); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Batas Waktu / Deadline -->
                <div>
                    <label class="block text-sm font-bold text-slate-700">Batas Waktu Tindak Lanjut (Deadline)</label>
                    <input type="date" name="deadline"
                            class="mt-2 block w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm bg-slate-50 border p-3 text-slate-700">
                </div>

                <!-- Catatan / Instruksi Disposisi -->
                <div>
                    <label class="block text-sm font-bold text-slate-700">Instruksi / Catatan Disposisi</label>
                    <textarea name="notes" rows="5" required
                              class="mt-2 block w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm bg-slate-50 border p-3 text-slate-700 placeholder:text-slate-400"
                              placeholder="Ketik instruksi tindak lanjut surat disini (misal: mohon hadiri acara mewakili yayasan, harap koordinasikan dengan humas)."></textarea>
                </div>

                <!-- Action buttons -->
                <div class="flex justify-end gap-3 border-t border-slate-100 pt-5">
                    <a href="<?php url('views/documents/incoming.php'); ?>" class="inline-flex items-center justify-center rounded-xl bg-white border border-slate-300 px-5 py-2.5 text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                        Batal
                    </a>
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-indigo-700 transition-colors">
                        Kirim Disposisi
                    </button>
                </div>
            </form>
        </div>

    <!-- FORM CREATE / EDIT -->
    <?php elseif ($action === 'new' || $action === 'edit'): 
        $is_edit = ($action === 'edit');
    ?>
        <div class="mt-8 max-w-2xl mx-auto bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-800 border-b border-slate-100 pb-4"><?php echo $is_edit ? 'Edit Catatan Surat Masuk' : 'Pencatatan Surat Masuk Baru'; ?></h2>

            <form action="<?php url('logic/documents/create_document.php'); ?>" method="POST" enctype="multipart/form-data" class="mt-6 space-y-6">
                <input type="hidden" name="action" value="<?php echo $is_edit ? 'edit_incoming' : 'create_incoming'; ?>">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="id" value="<?php echo $document['id']; ?>">
                <?php endif; ?>

                <!-- Nomor Surat -->
                <div>
                    <label class="block text-sm font-bold text-slate-700">Nomor Surat Masuk</label>
                    <input type="text" name="document_number" required value="<?php echo $is_edit ? htmlspecialchars($document['document_number']) : ''; ?>"
                           class="mt-2 block w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm bg-slate-50 border p-3 text-slate-700"
                           placeholder="Contoh: 023/YPB/X/2026">
                </div>

                <!-- Pengirim / Instansi -->
                <div>
                    <label class="block text-sm font-bold text-slate-700">Pengirim (Nama Orang / Lembaga / Instansi)</label>
                    <input type="text" name="sender" required value="<?php echo $is_edit ? htmlspecialchars($document['sender']) : ''; ?>"
                           class="mt-2 block w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm bg-slate-50 border p-3 text-slate-700"
                           placeholder="Contoh: Dinas Pendidikan Kabupaten">
                </div>

                <!-- Perihal / Judul -->
                <div>
                    <label class="block text-sm font-bold text-slate-700">Perihal / Hal Surat</label>
                    <input type="text" name="title" required value="<?php echo $is_edit ? htmlspecialchars($document['title']) : ''; ?>"
                           class="mt-2 block w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm bg-slate-50 border p-3 text-slate-700"
                           placeholder="Contoh: Undangan Koordinasi Akreditasi Sekolah">
                </div>

                <!-- Unit Penerima -->
                <div>
                    <label class="block text-sm font-bold text-slate-700">Ditujukan Ke Unit (Penerima Internal)</label>
                    <select name="receiver_unit_id" required
                            class="select-custom mt-2 block w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm bg-slate-50 border p-3 text-slate-700">
                        <option value="">-- Pilih Unit --</option>
                        <?php foreach ($units as $ut): ?>
                            <option value="<?php echo $ut['id']; ?>" <?php echo $is_edit && $document['receiver_unit_id'] == $ut['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($ut['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Lampiran Berkas (PDF / Image) -->
                <div>
                    <label class="block text-sm font-bold text-slate-700">Berkas Lampiran Fisik (Scan PDF / Image)</label>
                    <p class="text-xs text-slate-400 mt-1">Jenis file yang didukung: PDF, PNG, JPG (maks 5MB).</p>
                    <input type="file" name="attachment" <?php echo $is_edit ? '' : 'required'; ?>
                           class="mt-2 block w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 file:cursor-pointer">
                    <?php if ($is_edit && !empty($document['file_path'])): ?>
                        <p class="text-[10px] text-indigo-600 font-medium mt-2">
                            <a href="<?php echo BASE_URL . '/' . htmlspecialchars($document['file_path']); ?>" target="_blank" class="underline">Lihat Lampiran Saat Ini</a>
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Form Submit Buttons -->
                <div class="flex justify-end gap-3 border-t border-slate-100 pt-5">
                    <a href="<?php url('views/documents/incoming.php'); ?>" class="inline-flex items-center justify-center rounded-xl bg-white border border-slate-300 px-5 py-2.5 text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                        Batal
                    </a>
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-indigo-700 transition-colors">
                        Simpan Registrasi
                    </button>
                </div>
            </form>
        </div>

    <!-- DETAIL / VIEW INCOMING DOCUMENT -->
    <?php elseif ($action === 'view' && $document): 
        $is_outgoing = ($document['type'] === 'outgoing');
        $sender_display = $is_outgoing ? ($document['creator_name'] . ' (' . ($document['creator_unit_name'] ?: 'Yayasan') . ')') : htmlspecialchars($document['sender']);
    ?>
        <div class="mt-8 grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left 2 Cols: Document Sheet or File Viewer -->
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-2xl border border-slate-200 p-8 shadow-sm">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4 mb-6 gap-3">
                        <div>
                            <span class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-bold <?php echo $is_outgoing ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'; ?>">
                                <?php echo $is_outgoing ? '<i class="fa-solid fa-building-columns mr-1.5"></i> Surat Resmi Internal Yayasan' : '<i class="fa-solid fa-envelope-open-text mr-1.5"></i> Surat Masuk Eksternal'; ?>
                            </span>
                            <h2 class="text-xl font-bold text-slate-800 mt-2"><?php echo htmlspecialchars($document['title']); ?></h2>
                            <p class="text-xs text-slate-400 mt-1">Nomor Surat: <span class="font-mono font-bold text-slate-700"><?php echo htmlspecialchars($document['document_number']); ?></span></p>
                        </div>
                        <div class="flex items-center gap-2">
                            <?php if ($is_outgoing): ?>
                                <a href="<?php url('views/documents/preview.php?id=' . $document['id']); ?>" target="_blank" class="inline-flex items-center rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 px-3.5 py-2 text-xs font-bold transition-colors">
                                    <i class="fa-solid fa-print mr-1.5"></i> Cetak / PDF
                                </a>
                            <?php elseif (!empty($document['file_path'])): ?>
                                <a href="<?php echo BASE_URL . '/' . htmlspecialchars($document['file_path']); ?>" target="_blank" class="inline-flex items-center rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white px-3.5 py-2 text-xs font-bold transition-colors shadow-sm">
                                    <i class="fa-solid fa-download mr-1.5"></i> Unduh Lampiran
                                </a>
                            <?php endif; ?>
                            <a href="?action=disposition&id=<?php echo $document['id']; ?>" class="inline-flex items-center rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-3.5 py-2 text-xs font-bold transition-colors shadow-sm">
                                <i class="fa-solid fa-share mr-1.5"></i> Disposisi
                            </a>
                        </div>
                    </div>

                    <?php if ($is_outgoing): ?>
                        <!-- A4 Clean Paper Render for Official Letter -->
                        <div class="bg-slate-100 p-4 sm:p-6 rounded-xl overflow-x-auto flex justify-center">
                            <div class="bg-white shadow-md border border-slate-300 p-8 sm:p-12 w-full max-w-[210mm] min-h-[297mm] text-slate-800" style="font-family: 'Times New Roman', Times, serif; font-size: 12pt; line-height: 1.6;">
                                <?php 
                                if (strpos($document['content'], 'kop-surat') === false) {
                                    echo renderDocumentKopHtml($document);
                                }
                                ?>
                                <div class="document-body my-4">
                                    <?php 
                                    $view_incoming_content = processDocumentSignatureHtml($document['content'], $document['status'], $document['qr_token'], $resolved_signature_path);
                                    echo $view_incoming_content; 
                                    ?>
                                </div>
                                <?php 
                                if (stripos($view_incoming_content, 'tanda tangan') === false && stripos($view_incoming_content, 'signature-box') === false) {
                                    echo renderDocumentSignatureBoxHtml($resolved_signer_name, $resolved_signer_position, $document['status'], $document['qr_token'], $resolved_signature_path);
                                }
                                ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- External Letter Detail & File Viewer -->
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200 text-xs">
                                <div>
                                    <p class="text-slate-400 font-semibold uppercase text-[10px]">Asal Pengirim</p>
                                    <p class="font-bold text-slate-800 text-sm mt-0.5"><?php echo htmlspecialchars($document['sender']); ?></p>
                                </div>
                                <div>
                                    <p class="text-slate-400 font-semibold uppercase text-[10px]">Tujuan Unit Internal</p>
                                    <p class="font-bold text-indigo-700 text-sm mt-0.5"><?php echo htmlspecialchars($document['receiver_unit_name'] ?: 'Semua Unit'); ?></p>
                                </div>
                                <div>
                                    <p class="text-slate-400 font-semibold uppercase text-[10px]">Nomor Surat Pengirim</p>
                                    <p class="font-mono font-bold text-slate-800 mt-0.5"><?php echo htmlspecialchars($document['document_number']); ?></p>
                                </div>
                                <div>
                                    <p class="text-slate-400 font-semibold uppercase text-[10px]">Tanggal Diterima</p>
                                    <p class="font-bold text-slate-800 mt-0.5"><?php echo date('d F Y', strtotime($document['created_at'])); ?></p>
                                </div>
                            </div>

                            <?php if (!empty($document['file_path'])): 
                                $ext = strtolower(pathinfo($document['file_path'], PATHINFO_EXTENSION));
                                $file_url = BASE_URL . '/' . htmlspecialchars($document['file_path']);
                            ?>
                                <div class="mt-4 border border-slate-200 rounded-xl overflow-hidden">
                                    <div class="bg-slate-50 px-4 py-2.5 border-b border-slate-200 flex items-center justify-between text-xs">
                                        <span class="font-bold text-slate-700"><i class="fa-solid fa-paperclip mr-1.5 text-slate-400"></i> Berkas Lampiran Fisik (<?php echo strtoupper($ext); ?>)</span>
                                        <a href="<?php echo $file_url; ?>" target="_blank" class="text-indigo-600 hover:text-indigo-800 font-bold inline-flex items-center">
                                            Buka di Tab Baru <i class="fa-solid fa-arrow-up-right-from-square ml-1 text-[10px]"></i>
                                        </a>
                                    </div>
                                    <div class="p-2 bg-slate-100 flex justify-center">
                                        <?php if ($ext === 'pdf'): ?>
                                            <iframe src="<?php echo $file_url; ?>" class="w-full h-[600px] rounded-lg border border-slate-300" frameborder="0"></iframe>
                                        <?php elseif (in_array($ext, ['jpg', 'jpeg', 'png'])): ?>
                                            <img src="<?php echo $file_url; ?>" alt="Berkas Lampiran" class="max-h-[600px] object-contain rounded-lg shadow-sm">
                                        <?php else: ?>
                                            <div class="p-8 text-center">
                                                <p class="text-sm text-slate-600">File berkas lampiran: <strong><?php echo basename($document['file_path']); ?></strong></p>
                                                <a href="<?php echo $file_url; ?>" download class="mt-3 inline-flex items-center rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700">Unduh File</a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="p-6 text-center bg-slate-50 rounded-xl border border-dashed border-slate-300 text-xs text-slate-400">
                                    <i class="fa-regular fa-file text-2xl text-slate-300 mb-1"></i>
                                    <p>Tidak ada berkas digital terlampir pada surat masuk ini.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right 1 Col: Meta & Disposition Timeline -->
            <div class="space-y-6">
                <!-- Meta Info Card -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider border-b border-slate-100 pb-3">Ringkasan Surat Masuk</h3>
                    <dl class="divide-y divide-slate-100 text-xs mt-3">
                        <div class="py-2.5 flex justify-between">
                            <dt class="text-slate-400 font-medium">Jenis Surat</dt>
                            <dd class="font-bold text-slate-700 text-right"><?php echo $is_outgoing ? 'Internal Yayasan' : 'Eksternal'; ?></dd>
                        </div>
                        <div class="py-2.5 flex justify-between">
                            <dt class="text-slate-400 font-medium">Status Dokumen</dt>
                            <dd><span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-200">Resmi Selesai</span></dd>
                        </div>
                        <div class="py-2.5 flex justify-between">
                            <dt class="text-slate-400 font-medium">Asal Pengirim</dt>
                            <dd class="font-bold text-slate-700 text-right"><?php echo $sender_display; ?></dd>
                        </div>
                        <div class="py-2.5 flex justify-between">
                            <dt class="text-slate-400 font-medium">Penerima Dituju</dt>
                            <dd class="font-bold text-indigo-700 text-right"><?php echo htmlspecialchars($document['receiver_unit_name'] ?: ($document['receiver_division_name'] ?: 'Semua Unit')); ?></dd>
                        </div>
                        <div class="py-2.5 flex justify-between">
                            <dt class="text-slate-400 font-medium">Tanggal Diterima</dt>
                            <dd class="font-bold text-slate-700 text-right"><?php echo date('d M Y, H:i', strtotime($document['created_at'])); ?></dd>
                        </div>
                    </dl>
                </div>

                <!-- Disposition Timeline Card -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                        <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Alur Disposisi Surat</h3>
                        <a href="?action=disposition&id=<?php echo $document['id']; ?>" class="text-[10px] font-bold text-indigo-600 hover:text-indigo-800 inline-flex items-center">
                            + Disposisi
                        </a>
                    </div>

                    <?php if (count($disposition_history) > 0): ?>
                        <div class="space-y-4">
                            <?php foreach ($disposition_history as $disp): ?>
                                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs">
                                    <div class="flex items-center justify-between mb-1.5">
                                        <span class="font-bold text-slate-800"><?php echo htmlspecialchars($disp['from_name']); ?></span>
                                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded <?php echo $disp['status'] === 'completed' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'; ?>">
                                            <?php echo strtoupper($disp['status']); ?>
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-slate-400 flex items-center gap-1">
                                        <i class="fa-solid fa-arrow-right text-[9px] text-indigo-500"></i> Diberikan kepada: <span class="font-semibold text-slate-600"><?php echo htmlspecialchars($disp['to_name']); ?></span>
                                    </p>
                                    <div class="mt-2 text-slate-700 bg-white p-2.5 rounded-lg border border-slate-200/60 text-[11px]">
                                        <p class="italic text-slate-600">"<?php echo nl2br(htmlspecialchars($disp['notes'])); ?>"</p>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between text-[10px] text-slate-400">
                                        <span><?php echo date('d M Y, H:i', strtotime($disp['created_at'])); ?></span>
                                        <?php if (!empty($disp['deadline'])): ?>
                                            <span class="text-rose-600 font-semibold"><i class="fa-regular fa-clock mr-1"></i> Batas: <?php echo date('d M Y', strtotime($disp['deadline'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="py-6 text-center text-slate-400 text-xs">
                            <i class="fa-solid fa-share-nodes text-2xl text-slate-300 mb-1"></i>
                            <p class="mt-1">Surat belum pernah didisposisikan.</p>
                            <a href="?action=disposition&id=<?php echo $document['id']; ?>" class="mt-2 inline-flex items-center text-xs font-bold text-indigo-600 hover:text-indigo-800">
                                Disposisikan sekarang &rarr;
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <!-- MAIN TABLE LIST VIEW -->
    <?php else: ?>
        <!-- Search filter bar -->
        <div class="mt-6 bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-4 justify-between items-center">
            <form class="relative w-full sm:w-96" method="GET">
                <input type="hidden" name="action" value="list">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <i class="fa-solid fa-search text-slate-400 text-sm"></i>
                </div>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                    class="block w-full rounded-lg border-slate-200 pl-10 pr-3 pt-2 pb-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 bg-slate-50 border placeholder:text-slate-400 text-slate-600"
                    placeholder="Cari perihal, nomor, atau pengirim..." onchange="this.form.submit()">
            </form>
        </div>

        <!-- Data table -->
        <div class="mt-6 overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-xl border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left sm:pl-6 w-12">No.</th>
                        <th scope="col" class="px-3 py-3.5 text-left">Nomor Surat</th>
                        <th scope="col" class="px-3 py-3.5 text-left">Perihal / Hal</th>
                        <th scope="col" class="px-3 py-3.5 text-left">Pengirim</th>
                        <th scope="col" class="px-3 py-3.5 text-left">Unit Penerima</th>
                        <th scope="col" class="px-3 py-3.5 text-center">Tanggal Terima</th>
                        <th scope="col" class="px-3 py-3.5 text-center">Berkas</th>
                        <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <?php if (count($documents) > 0): ?>
                        <?php foreach ($documents as $index => $doc): ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="whitespace-nowrap py-4 pl-4 pr-3 text-xs text-slate-400 sm:pl-6">
                                    <?php echo $offset + $index + 1; ?>.
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-xs font-bold text-slate-700">
                                    <?php echo htmlspecialchars($doc['document_number']); ?>
                                </td>
                                <td class="px-3 py-4 text-xs text-slate-600 font-medium max-w-xs truncate">
                                    <?php echo htmlspecialchars($doc['title']); ?>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-xs text-slate-500">
                                    <?php echo htmlspecialchars($doc['sender_name']); ?>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-xs text-slate-500">
                                    <span class="inline-flex items-center rounded-md bg-indigo-50 px-2.5 py-0.5 text-[10px] font-bold text-indigo-700 border border-indigo-100">
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
                                <td class="whitespace-nowrap px-3 py-4 text-xs text-slate-400 text-center">
                                    <?php echo date('d/m/Y', strtotime($doc['created_at'])); ?>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-center">
                                    <a href="<?php url('views/documents/incoming.php?action=view&id=' . $doc['id']); ?>" class="inline-flex items-center rounded-lg bg-indigo-50 px-2.5 py-1 text-[10px] font-bold text-indigo-600 hover:bg-indigo-100 transition-colors">
                                        <i class="fa-solid fa-eye mr-1 text-[10px]"></i> Buka Surat
                                    </a>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-center text-xs font-medium space-x-1.5">
                                    <!-- Aksi: Disposisi -->
                                    <a href="?action=disposition&id=<?php echo $doc['id']; ?>" class="inline-flex items-center rounded-lg bg-emerald-50 px-2.5 py-1.5 text-[10px] font-bold text-emerald-600 hover:bg-emerald-100 transition-colors" title="Disposisikan Surat">
                                        <i class="fa-solid fa-share mr-1.5 text-[10px]"></i>
                                        Disposisi
                                    </a>

                                    <?php if ($doc['type'] === 'incoming' && ($is_admin || (isset($doc['creator_id']) && $doc['creator_id'] == $user_id))): ?>
                                        <!-- Aksi: Edit (Hanya Surat Masuk Eksternal) -->
                                        <a href="?action=edit&id=<?php echo $doc['id']; ?>" class="inline-flex items-center rounded-lg bg-indigo-50 p-1.5 text-indigo-600 hover:bg-indigo-100 transition-colors" title="Edit Rincian">
                                            <i class="fa-solid fa-pen text-xs"></i>
                                        </a>

                                        <!-- Aksi: Delete (Hanya Surat Masuk Eksternal) -->
                                        <a href="<?php url('logic/documents/create_document.php'); ?>" class="inline-flex items-center rounded-lg bg-rose-50 p-1.5 text-rose-600 hover:bg-rose-100 transition-colors" title="Hapus Catatan"
                                           onclick="event.preventDefault(); if(confirm('Hapus pencatatan surat masuk ini beserta berkasnya?')){ document.getElementById('delete-incoming-<?php echo $doc['id']; ?>').submit(); }">
                                            <i class="fa-solid fa-trash text-xs"></i>
                                        </a>
                                        <form id="delete-incoming-<?php echo $doc['id']; ?>" action="<?php url('logic/documents/create_document.php'); ?>" method="POST" class="hidden">
                                            <input type="hidden" name="action" value="delete_incoming">
                                            <input type="hidden" name="id" value="<?php echo $doc['id']; ?>">
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="py-10 text-center text-sm text-slate-500 italic">Data surat masuk tidak ditemukan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
        <?php if ($total_pages > 1): ?>
            <div class="mt-6 flex items-center justify-between border-t border-slate-200 px-4 py-3 sm:px-6">
                <div class="flex flex-1 justify-between sm:hidden">
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" class="relative inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Previous</a>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" class="relative ml-3 inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Next</a>
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
                                <a href="?page=<?php echo $p; ?>&search=<?php echo urlencode($search); ?>" 
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

<?php include '../layouts/footer.php'; ?>
