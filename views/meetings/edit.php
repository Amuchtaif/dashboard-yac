<?php
require_once '../../config/app.php';
require_once '../../config/db_mysqli.php';

check_login();

$page_title = "Edit Rapat";

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch existing data
$stmt = $mysqli->prepare("SELECT * FROM meetings WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$meeting = $stmt->get_result()->fetch_assoc();

if (!$meeting) {
    header("Location: index.php");
    exit;
}

// Fetch divisions
$resDiv = $mysqli->query("SELECT * FROM divisions ORDER BY name ASC");
$divisions = [];
while ($row = $resDiv->fetch_assoc()) {
    $divisions[] = $row;
}

include '../layouts/header.php';
?>

<div class="min-h-screen pb-10">
    <!-- Breadcrumb & Header -->
    <div class="flex justify-between items-start mb-8 pt-6">
        <div>
            <div class="flex items-center gap-2 text-sm text-slate-500 mb-1">
                <a href="index.php" class="hover:text-indigo-600">Manajemen Rapat</a>
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
                <span class="font-medium text-indigo-600">Edit Rapat</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900">Edit Rapat</h1>
            <p class="mt-1 text-slate-500">Ubah informasi rapat yang sudah ada.</p>
        </div>
        <div class="flex space-x-3">
            <a href="details.php?id=<?= $meeting['id'] ?>"
                class="bg-indigo-50 text-indigo-700 hover:bg-indigo-100 px-4 py-2 rounded-lg text-sm font-medium flex items-center transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                Lihat Detail
            </a>
            <a href="index.php"
                class="bg-slate-100 text-slate-700 hover:bg-slate-200 px-4 py-2 rounded-lg text-sm font-medium flex items-center transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Kembali
            </a>
        </div>
    </div>

    <!-- Form Card -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-100">
            <h2 class="text-lg font-semibold text-slate-900">Informasi Rapat</h2>
            <p class="text-sm text-slate-500 mt-1">Perbarui detail rapat di bawah ini.</p>
        </div>
        
        <form action="../../logic/meetings/update.php" method="POST" class="p-6 space-y-6">
            <input type="hidden" name="id" value="<?= $meeting['id'] ?>">

            <!-- Title -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Judul Rapat <span class="text-red-500">*</span></label>
                <input type="text" name="title" required value="<?= htmlspecialchars($meeting['title']) ?>"
                    class="block w-full px-4 py-2.5 border border-slate-300 rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm transition-colors"
                    placeholder="Masukkan judul rapat">
            </div>

            <!-- Description -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Deskripsi</label>
                <textarea name="description" rows="3"
                    class="block w-full px-4 py-2.5 border border-slate-300 rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm transition-colors"
                    placeholder="Tambahkan deskripsi rapat (opsional)"><?= htmlspecialchars($meeting['description']) ?></textarea>
            </div>

            <!-- Date & Division -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Tanggal <span class="text-red-500">*</span></label>
                    <input type="date" name="meeting_date" required value="<?= $meeting['meeting_date'] ?>"
                        class="block w-full px-4 py-2.5 border border-slate-300 rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm transition-colors">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Bidang <span class="text-red-500">*</span></label>
                    <select name="division_id" required
                        class="block w-full px-4 py-2.5 border border-slate-300 rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm transition-colors">
                        <?php foreach ($divisions as $div): ?>
                            <option value="<?= $div['id'] ?>" <?= $meeting['division_id'] == $div['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($div['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Time -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Waktu Mulai <span class="text-red-500">*</span></label>
                    <input type="time" name="start_time" required value="<?= $meeting['start_time'] ?>"
                        class="block w-full px-4 py-2.5 border border-slate-300 rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm transition-colors">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Waktu Selesai <span class="text-red-500">*</span></label>
                    <input type="time" name="end_time" required value="<?= $meeting['end_time'] ?>"
                        class="block w-full px-4 py-2.5 border border-slate-300 rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm transition-colors">
                </div>
            </div>

            <!-- Type & Location -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Tipe Rapat <span class="text-red-500">*</span></label>
                    <select name="type" id="meeting_type" required onchange="toggleLocationField()"
                        class="block w-full px-4 py-2.5 border border-slate-300 rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm transition-colors">
                        <option value="offline" <?= $meeting['type'] == 'offline' ? 'selected' : '' ?>>Offline (Tatap Muka)</option>
                        <option value="online" <?= $meeting['type'] == 'online' ? 'selected' : '' ?>>Online (Virtual)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1" id="location_label">
                        <?= $meeting['type'] == 'online' ? 'Link Meeting' : 'Lokasi' ?>
                    </label>
                    <input type="text" name="location" id="location_input" value="<?= htmlspecialchars($meeting['location']) ?>"
                        placeholder="<?= $meeting['type'] == 'online' ? 'https://zoom.us/j/...' : 'Ruang Rapat Lt. 2' ?>"
                        class="block w-full px-4 py-2.5 border border-slate-300 rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm transition-colors">
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end space-x-3 pt-6 border-t border-slate-100">
                <a href="index.php"
                    class="px-6 py-2.5 bg-white border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                    Batal
                </a>
                <button type="submit"
                    class="px-6 py-2.5 bg-indigo-600 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleLocationField() {
    const type = document.getElementById('meeting_type').value;
    const label = document.getElementById('location_label');
    const input = document.getElementById('location_input');
    
    if (type === 'online') {
        label.textContent = 'Link Meeting';
        input.placeholder = 'https://zoom.us/j/...';
    } else {
        label.textContent = 'Lokasi';
        input.placeholder = 'Ruang Rapat Lt. 2';
    }
}
</script>

<?php include '../layouts/footer.php'; ?>