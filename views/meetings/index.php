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

// 1. Fetch Meetings
$sql = "SELECT m.*, d.name as division_name, e.full_name as creator_name 
        FROM meetings m 
        LEFT JOIN divisions d ON m.division_id = d.id 
        LEFT JOIN employees e ON m.created_by = e.id
        $whereClause 
        ORDER BY m.meeting_date DESC, m.start_time ASC";

$stmt = $mysqli->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
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
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
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
                    <option value="">Semua Divisi</option>
                    <?php foreach ($divisions as $div): ?>
                        <option value="<?= $div['id'] ?>" <?= $div['id'] == $division_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($div['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <a href="create.php"
                class="bg-indigo-50 text-indigo-700 hover:bg-indigo-100 px-4 py-2 rounded-lg text-sm font-medium flex items-center transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
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
                        <th scope="col" class="px-6 py-4 text-left min-w-[250px]">Judul / Divisi</th>
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
                            <td colspan="5" class="px-6 py-10 text-center text-slate-400">
                                Tidak ada jadwal rapat ditemukan.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($meetings as $m): ?>
                        <tr class="hover:bg-slate-50 transition-colors duration-150">
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
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>
                                    <!-- Edit Button -->
                                    <a href="edit.php?id=<?= $m['id'] ?>"
                                        class="p-2 text-amber-600 hover:text-amber-900 bg-amber-50 hover:bg-amber-100 rounded-lg transition-colors"
                                        title="Edit Rapat">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    <!-- Delete Button -->
                                    <button onclick="confirmDelete(<?= $m['id'] ?>, '<?= htmlspecialchars(addslashes($m['title'])) ?>')"
                                        class="p-2 text-rose-600 hover:text-rose-900 bg-rose-50 hover:bg-rose-100 rounded-lg transition-colors"
                                        title="Hapus Rapat">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
                    window.location.reload();
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
<div id="meetingDeleteModal" class="fixed inset-0 z-[60] hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <!-- Backdrop -->
    <div id="meetingDeleteBackdrop" onclick="closeMeetingDeleteModal()" class="fixed inset-0 bg-slate-900/50 transition-opacity duration-300 opacity-0 backdrop-blur-sm"></div>

    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <!-- Modal Panel -->
        <div id="meetingDeletePanel" class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-2xl transition-all duration-300 opacity-0 scale-95 sm:my-8 sm:w-full sm:max-w-lg">
            <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
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
                    <svg class="w-4 h-4 mr-2 hidden animate-spin" id="meetingDeleteSpinner" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
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