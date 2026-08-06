<?php
require_once '../../config/app.php';
require_once '../../config/db_mysqli.php';

check_login();

$page_title = "Manajemen Rapat";

// Filter Logic
$division_id = isset($_GET['division_id']) ? $_GET['division_id'] : '';
$whereClause = "";
$params = [];
$types = "";

if (!empty($division_id)) {
    $whereClause = "WHERE m.division_id = ?";
    $params[] = $division_id;
    $types .= "i";
}

// --- Pagination Logic ---
$limit = isset($_GET['limit']) && in_array((int) $_GET['limit'], [10, 20, 50, 100]) ? (int) $_GET['limit'] : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $limit;

// Total Count for Pagination
$count_sql = "SELECT COUNT(*) FROM meetings m $whereClause";
$count_stmt = $mysqli->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_row()[0];
$total_pages = ceil($total_rows / $limit);

// 1. Fetch Meetings with Pagination
$sql = "SELECT m.*, d.name as division_name, e.full_name as creator_name 
        FROM meetings m 
        LEFT JOIN divisions d ON m.division_id = d.id 
        LEFT JOIN employees e ON m.created_by = e.id
        $whereClause 
        ORDER BY m.meeting_date DESC, m.start_time ASC
        LIMIT ? OFFSET ?";

$stmt = $mysqli->prepare($sql);
if (!empty($params)) {
    $p_types = $types . "ii";
    $p_params = array_merge($params, [$limit, $offset]);
    $stmt->bind_param($p_types, ...$p_params);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}

$stmt->execute();
$result = $stmt->get_result();
$meetings = [];
while ($row = $result->fetch_assoc()) {
    $meetings[] = $row;
}

// 2. Fetch Divisions for Dropdown
$resDiv = $mysqli->query("SELECT * FROM divisions ORDER BY name ASC");
$divisions = [];
while ($row = $resDiv->fetch_assoc()) {
    $divisions[] = $row;
}

include '../layouts/header.php';
?>

<div class="min-h-screen pb-10">
    <!-- Top Header Section -->
    <div class="flex justify-between items-start mb-8 pt-6">
        <div>
            <div class="flex items-center gap-2 text-sm text-slate-500 mb-1">
                <span>Dashboard</span>
                <i class="fa-solid fa-chevron-right h-3 w-3"></i>
                <span class="font-medium text-indigo-600">Manajemen Rapat</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900">Manajemen Rapat</h1>
            <p class="mt-1 text-slate-500">Kelola jadwal rapat dan notulensi antar divisi.</p>
        </div>
        <div class="flex space-x-3">
            <!-- Filter Dropdown -->
            <form method="GET" class="relative">
                <select name="division_id" onchange="this.form.submit()"
                    class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-lg shadow-sm border bg-white">
                    <option value="">Semua Bidang</option>
                    <?php foreach ($divisions as $div): ?>
                        <option value="<?= $div['id'] ?>" <?= $div['id'] == $division_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($div['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <a href="create.php"
                class="bg-indigo-50 text-indigo-700 hover:bg-indigo-100 px-4 py-2 rounded-lg text-sm font-medium flex items-center transition-colors">
                <i class="fa-solid fa-plus w-4 h-4 mr-2"></i>
                Buat Rapat Baru
            </a>
        </div>
    </div>

    <!-- Stats / Cards View for Mobile, Table for Desktop -->
    <div class="bg-white shadow-sm rounded-xl overflow-hidden border border-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                        <th scope="col" class="px-6 py-4 text-left w-12">No.</th>
                        <th scope="col" class="px-6 py-4 text-left min-w-[250px]">Judul / Bidang</th>
                        <th scope="col" class="px-6 py-4 text-left min-w-[180px]">Dibuat Oleh</th>
                        <th scope="col" class="px-6 py-4 text-left min-w-[180px]">Tanggal & Waktu</th>
                        <th scope="col" class="px-6 py-4 text-left min-w-[100px]">Tipe</th>
                        <th scope="col" class="px-6 py-4 text-left min-w-[200px]">Lokasi</th>
                        <th scope="col" class="px-6 py-4 text-center w-32 border-none">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-slate-200">
                    <?php if (count($meetings) === 0): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-10 text-center text-slate-400">
                                Tidak ada jadwal rapat ditemukan.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($meetings as $index => $m): ?>
                        <tr class="hover:bg-slate-50 transition-colors duration-150">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 font-medium">
                                <?= $offset + $index + 1 ?>.
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex flex-col">
                                    <span class="text-sm font-semibold text-slate-900">
                                        <?= htmlspecialchars($m['title']) ?>
                                    </span>
                                    <span class="text-xs text-indigo-500 font-medium">
                                        <?= htmlspecialchars($m['division_name']) ?>
                                    </span>
                                </div>

                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                <?= htmlspecialchars($m['creator_name'] ?? 'Unknown') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-slate-700 font-medium">
                                    <?= date('D, d M Y', strtotime($m['meeting_date'])) ?>
                                </div>
                                <div class="text-xs text-slate-500">
                                    <?= date('H:i', strtotime($m['start_time'])) ?> -
                                    <?= date('H:i', strtotime($m['end_time'])) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($m['type'] === 'online'): ?>
                                    <span
                                        class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-sky-100 text-sky-800 border border-sky-200">
                                        Online
                                    </span>
                                <?php else: ?>
                                    <span
                                        class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        Offline
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-slate-600 truncate max-w-xs"
                                    title="<?= htmlspecialchars($m['location']) ?>">
                                    <?= htmlspecialchars($m['location']) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                <div class="flex items-center justify-center gap-2">
                                    <!-- View Button -->
                                    <a href="details.php?id=<?= $m['id'] ?>"
                                        class="p-2 text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors"
                                        title="Lihat Detail">
                                        <i class="fa-solid fa-eye w-4 h-4"></i>
                                    </a>
                                    <!-- Edit Button -->
                                    <a href="edit.php?id=<?= $m['id'] ?>"
                                        class="p-2 text-amber-600 hover:text-amber-900 bg-amber-50 hover:bg-amber-100 rounded-lg transition-colors"
                                        title="Edit Rapat">
                                        <i class="fa-solid fa-pen-to-square w-4 h-4"></i>
                                    </a>
                                    <!-- Delete Button -->
                                    <button
                                        onclick="confirmDelete(<?= $m['id'] ?>, '<?= htmlspecialchars(addslashes($m['title'])) ?>')"
                                        class="p-2 text-rose-600 hover:text-rose-900 bg-rose-50 hover:bg-rose-100 rounded-lg transition-colors"
                                        title="Hapus Rapat">
                                        <i class="fa-solid fa-trash w-4 h-4"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div
            class="flex flex-col sm:flex-row items-center justify-between border-t border-slate-200 bg-white px-4 py-4 md:py-3 sm:px-6 gap-4">
            <!-- Mobile Pagination Info -->
            <div class="flex sm:hidden flex-col items-center gap-2">
                <p class="text-xs text-slate-500">
                    Menampilkan <span
                        class="font-bold text-slate-900"><?php echo ($total_rows > 0) ? $offset + 1 : 0; ?></span> -
                    <span class="font-bold text-slate-900"><?php echo min($offset + $limit, $total_rows); ?></span> dari
                    <span class="font-bold text-slate-900"><?php echo $total_rows; ?></span>
                </p>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>&division_id=<?php echo $division_id; ?>"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50">Prev</a>
                    <?php endif; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>&division_id=<?php echo $division_id; ?>"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50">Next</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <select
                        onchange="window.location.href='?page=1&division_id=<?php echo $division_id; ?>&limit='+this.value"
                        class="block rounded-lg border-slate-300 py-1.5 pl-3 pr-8 text-slate-900 ring-1 ring-inset ring-slate-100 focus:ring-2 focus:ring-indigo-600 sm:text-xs">
                        <?php foreach ([10, 20, 50, 100] as $val): ?>
                            <option value="<?php echo $val; ?>" <?php echo $limit == $val ? 'selected' : ''; ?>>
                                <?php echo $val; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-slate-500">
                        Menampilkan <span
                            class="font-bold text-slate-900"><?php echo ($total_rows > 0) ? $offset + 1 : 0; ?></span>
                        sampai <span
                            class="font-bold text-slate-900"><?php echo min($offset + $limit, $total_rows); ?></span>
                        dari <span class="font-bold text-slate-900"><?php echo $total_rows; ?></span> hasil
                    </p>
                </div>
                <div>
                    <nav class="isolate inline-flex -space-x-px rounded-xl shadow-sm border border-slate-200 overflow-hidden"
                        aria-label="Pagination">
                        <!-- Prev -->
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>&division_id=<?php echo $division_id; ?>"
                                class="relative inline-flex items-center px-3 py-2 text-slate-400 hover:bg-slate-50 transition-colors">
                                <i class="fa-solid fa-chevron-left h-5 w-5"></i>
                            </a>
                        <?php endif; ?>

                        <?php
                        $range = 2;
                        for ($i = 1; $i <= $total_pages; $i++):
                            if ($i == 1 || $i == $total_pages || ($i >= $page - $range && $i <= $page + $range)): ?>
                                <a href="?page=<?php echo $i; ?>&limit=<?php echo $limit; ?>&division_id=<?php echo $division_id; ?>"
                                    class="relative inline-flex items-center px-4 py-2 text-sm font-semibold <?php echo ($i == $page) ? 'bg-indigo-600 text-white' : 'text-slate-900 hover:bg-slate-50'; ?> border-x border-slate-100 transition-colors">
                                    <?php echo $i; ?>
                                </a>
                            <?php elseif ($i == 2 || $i == $total_pages - 1): ?>
                                <span
                                    class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-slate-400">...</span>
                            <?php endif;
                        endfor; ?>

                        <!-- Next -->
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>&division_id=<?php echo $division_id; ?>"
                                class="relative inline-flex items-center px-3 py-2 text-slate-400 hover:bg-slate-50 transition-colors">
                                <i class="fa-solid fa-chevron-right h-5 w-5"></i>
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function confirmDelete(id, title) {
        // Set the meeting info in the modal
        document.getElementById('meetingDeleteName').textContent = '"' + title + '"';
        document.getElementById('meetingConfirmDeleteBtn').setAttribute('data-id', id);

        // Open modal
        const modal = document.getElementById('meetingDeleteModal');
        const backdrop = document.getElementById('meetingDeleteBackdrop');
        const panel = document.getElementById('meetingDeletePanel');

        modal.classList.remove('hidden');
        setTimeout(() => {
            backdrop.classList.remove('opacity-0');
            panel.classList.remove('opacity-0', 'scale-95');
        }, 10);
    }

    function closeMeetingDeleteModal() {
        const modal = document.getElementById('meetingDeleteModal');
        const backdrop = document.getElementById('meetingDeleteBackdrop');
        const panel = document.getElementById('meetingDeletePanel');

        backdrop.classList.add('opacity-0');
        panel.classList.add('opacity-0', 'scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    async function executeMeetingDelete() {
        const btn = document.getElementById('meetingConfirmDeleteBtn');
        const id = btn.getAttribute('data-id');
        const spinner = document.getElementById('meetingDeleteSpinner');
        const btnText = document.getElementById('meetingDeleteBtnText');

        // Show loading state
        btn.disabled = true;
        spinner.classList.remove('hidden');
        btnText.textContent = 'Menghapus...';

        try {
            const response = await fetch('../../logic/meetings/delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: parseInt(id) })
            });

            const data = await response.json();

            if (data.success) {
                btnText.textContent = 'Berhasil!';
                setTimeout(() => {
                    window.location.href = 'index.php?success=' + encodeURIComponent(data.message || 'Rapat berhasil dihapus');
                }, 500);
            } else {
                alert('Gagal menghapus: ' + (data.message || 'Terjadi kesalahan'));
                btn.disabled = false;
                spinner.classList.add('hidden');
                btnText.textContent = 'Ya, Hapus';
            }
        } catch (err) {
            console.error('Delete error:', err);
            alert('Tidak dapat terhubung ke server');
            btn.disabled = false;
            spinner.classList.add('hidden');
            btnText.textContent = 'Ya, Hapus';
        }
    }
</script>

<!-- Custom Delete Modal for Meetings -->
<div id="meetingDeleteModal" class="fixed inset-0 z-[60] hidden overflow-y-auto" aria-labelledby="modal-title"
    role="dialog" aria-modal="true">
    <!-- Backdrop -->
    <div id="meetingDeleteBackdrop" onclick="closeMeetingDeleteModal()"
        class="fixed inset-0 bg-slate-900/50 transition-opacity duration-300 opacity-0 backdrop-blur-sm"></div>

    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <!-- Modal Panel -->
        <div id="meetingDeletePanel"
            class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-2xl transition-all duration-300 opacity-0 scale-95 sm:my-8 sm:w-full sm:max-w-lg">
            <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div
                        class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <i class="fa-solid fa-triangle-exclamation h-6 w-6 text-red-600"></i>
                    </div>
                    <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                        <h3 class="text-lg font-bold leading-6 text-slate-900">Hapus Rapat?</h3>
                        <div class="mt-2">
                            <p class="text-sm text-slate-500">Anda yakin ingin menghapus rapat:</p>
                            <p class="text-sm font-semibold text-slate-800 mt-1" id="meetingDeleteName"></p>
                            <p class="text-xs text-slate-400 mt-2">Tindakan ini tidak dapat dibatalkan.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                <button type="button" id="meetingConfirmDeleteBtn" onclick="executeMeetingDelete()"
                    class="inline-flex w-full justify-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:w-auto transition-all transform active:scale-95">
                    <i id="meetingDeleteSpinner" class="fa-solid fa-spinner fa-spin w-4 h-4 mr-2 hidden animate-spin"></i>
                    <span id="meetingDeleteBtnText">Ya, Hapus</span>
                </button>
                <button type="button" onclick="closeMeetingDeleteModal()"
                    class="mt-3 inline-flex w-full justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto transition-all">
                    Batal
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>