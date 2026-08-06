<?php
// views/documents/incoming.php
require_once '../../config/app.php';
require_once '../../config/database.php';

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

$document = null;
if (($action === 'edit' || $action === 'disposition') && $doc_id > 0) {
    $stmtDoc = $conn->prepare("
        SELECT d.*, u.name as receiver_unit_name
        FROM documents d
        LEFT JOIN units u ON d.receiver_unit_id = u.id
        WHERE d.id = ? AND d.type = 'incoming'
    ");
    $stmtDoc->execute([$doc_id]);
    $document = $stmtDoc->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        redirect("views/documents/incoming.php?error=Dokumen+tidak+ditemukan");
    }
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

// Fetch logged-in employee's division & unit details for target filtering
$stmtEmpInfo = $conn->prepare("SELECT division_id, department_id, unit_id FROM employees WHERE id = ?");
$stmtEmpInfo->execute([$user_id]);
$emp_info = $stmtEmpInfo->fetch(PDO::FETCH_ASSOC);
$user_div_id = $emp_info['division_id'] ?: ($emp_info['department_id'] ?: 0);
$user_unit_id = $emp_info['unit_id'] ?? 0;

// Pagination & search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$params = [];
if ($is_admin) {
    // Administrator sees external incoming letters AND completed/non-draft internal letters addressed to any division/unit
    $where_clause = "(d.type = 'incoming' OR (d.type = 'outgoing' AND d.status != 'draft' AND d.receiver_division_id IS NOT NULL))";
} else {
    // Staff sees external incoming letters AND internal letters addressed to their division or unit
    $where_clause = "(
        d.type = 'incoming' 
        OR (
            d.type = 'outgoing' AND d.status != 'draft' AND (
                (d.receiver_division_id = :user_div_id AND (d.receiver_unit_id IS NULL OR d.receiver_unit_id = :user_unit_id OR :user_unit_id = 0))
                OR (d.receiver_unit_id = :user_unit_id AND :user_unit_id != 0)
            )
        )
    )";
    $params[':user_div_id'] = $user_div_id;
    $params[':user_unit_id'] = $user_unit_id;
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
    SELECT d.id, d.document_number, d.title, d.type, d.status, d.file_path, d.created_at,
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
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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
    <?php if ($action === 'disposition' && $document): ?>
        <div class="mt-8 max-w-2xl mx-auto bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
            <div class="border-b border-slate-100 pb-4">
                <span class="inline-flex items-center rounded-md bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700">Aksi Disposisi</span>
                <h2 class="text-lg font-bold text-slate-800 mt-2">Disposisikan Surat: "<?php echo htmlspecialchars($document['title']); ?>"</h2>
                <p class="text-xs text-slate-400 mt-1">Pengirim: <span class="font-bold text-slate-600"><?php echo htmlspecialchars($document['sender']); ?></span> &bull; No: <?php echo htmlspecialchars($document['document_number']); ?></p>
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
                                    <?php if ($doc['type'] === 'outgoing'): ?>
                                        <a href="<?php url('views/documents/outgoing.php?action=view&id=' . $doc['id']); ?>" class="inline-flex items-center rounded-lg bg-indigo-50 px-2.5 py-1 text-[10px] font-bold text-indigo-600 hover:bg-indigo-100 transition-colors">
                                            Buka Dokumen
                                        </a>
                                    <?php elseif (!empty($doc['file_path'])): ?>
                                        <a href="<?php echo BASE_URL . '/' . htmlspecialchars($doc['file_path']); ?>" target="_blank" class="inline-flex items-center rounded-lg bg-indigo-50 px-2.5 py-1 text-[10px] font-bold text-indigo-600 hover:bg-indigo-100 transition-colors">
                                            Unduh
                                        </a>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-400 italic">Tidak ada</span>
                                    <?php endif; ?>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-center text-xs font-medium space-x-1.5">
                                    <!-- Aksi: Disposisi -->
                                    <a href="?action=disposition&id=<?php echo $doc['id']; ?>" class="inline-flex items-center rounded-lg bg-emerald-50 px-2.5 py-1.5 text-[10px] font-bold text-emerald-600 hover:bg-emerald-100 transition-colors" title="Disposisikan Surat">
                                        <i class="fa-solid fa-share mr-1.5 text-[10px]"></i>
                                        Disposisi
                                    </a>

                                    <!-- Aksi: Edit -->
                                    <a href="?action=edit&id=<?php echo $doc['id']; ?>" class="inline-flex items-center rounded-lg bg-indigo-50 p-1.5 text-indigo-600 hover:bg-indigo-100 transition-colors" title="Edit Rincian">
                                        <i class="fa-solid fa-pen text-xs"></i>
                                    </a>

                                    <!-- Aksi: Delete -->
                                    <a href="<?php url('logic/documents/create_document.php'); ?>" class="inline-flex items-center rounded-lg bg-rose-50 p-1.5 text-rose-600 hover:bg-rose-100 transition-colors" title="Hapus Catatan"
                                       onclick="event.preventDefault(); if(confirm('Hapus pencatatan surat masuk ini beserta berkasnya?')){ document.getElementById('delete-incoming-<?php echo $doc['id']; ?>').submit(); }">
                                        <i class="fa-solid fa-trash text-xs"></i>
                                    </a>
                                    <form id="delete-incoming-<?php echo $doc['id']; ?>" action="<?php url('logic/documents/create_document.php'); ?>" method="POST" class="hidden">
                                        <input type="hidden" name="action" value="delete_incoming">
                                        <input type="hidden" name="id" value="<?php echo $doc['id']; ?>">
                                    </form>
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
