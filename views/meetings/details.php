<?php
require_once '../../config/app.php';
require_once '../../config/db_mysqli.php';

check_login();

$page_title = "Detail Rapat";

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch Meeting Details
$stmt = $mysqli->prepare("SELECT m.*, d.name as division_name, e.full_name as creator_name 
                          FROM meetings m 
                          LEFT JOIN divisions d ON m.division_id = d.id 
                          LEFT JOIN employees e ON m.created_by = e.id 
                          WHERE m.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$meeting = $stmt->get_result()->fetch_assoc();

if (!$meeting) {
    header("Location: index.php");
    exit;
}

// Fetch Participants
$sqlPart = "SELECT mp.*, e.full_name
            FROM meeting_participants mp 
            JOIN employees e ON mp.employee_id = e.id 
            WHERE mp.meeting_id = ?";
$stmtPart = $mysqli->prepare($sqlPart);
$stmtPart->bind_param("i", $id);
$stmtPart->execute();
$resPart = $stmtPart->get_result();
$participants = [];
while ($row = $resPart->fetch_assoc()) {
    $participants[] = $row;
}

// Fetch Meeting Notes
$sqlNotes = "SELECT n.*, e.full_name as user_name 
             FROM meeting_notes n 
             LEFT JOIN employees e ON n.user_id = e.id 
             WHERE n.meeting_id = ? 
             ORDER BY n.created_at ASC";
$stmtNotes = $mysqli->prepare($sqlNotes);
$stmtNotes->bind_param("i", $id);
$stmtNotes->execute();
$resNotes = $stmtNotes->get_result();
$notes = [];
while ($row = $resNotes->fetch_assoc()) {
    $notes[] = $row;
}

$usulan = array_filter($notes, function($n) { return $n['type'] === 'usulan'; });
$notulen = array_filter($notes, function($n) { return $n['type'] === 'notulen'; });

include '../layouts/header.php';
?>

<div class="min-h-screen pb-10">
    <!-- Breadcrumb & Header -->
    <div class="flex justify-between items-start mb-8 pt-6">
        <div>
            <div class="flex items-center gap-2 text-sm text-slate-500 mb-1">
                <a href="index.php" class="hover:text-indigo-600">Manajemen Rapat</a>
                <i class="fa-solid fa-chevron-right h-3 w-3"></i>
                <span class="font-medium text-indigo-600">Detail Rapat</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900"><?= htmlspecialchars($meeting['title']) ?></h1>
            <p class="mt-1 text-slate-500"><?= htmlspecialchars($meeting['division_name']) ?></p>
        </div>
        <div class="flex space-x-3">
            <a href="edit.php?id=<?= $meeting['id'] ?>"
                class="bg-amber-50 text-amber-700 hover:bg-amber-100 px-4 py-2 rounded-lg text-sm font-medium flex items-center transition-colors">
                <i class="fa-solid fa-pen-to-square w-4 h-4 mr-2"></i>
                Edit Rapat
            </a>
            <a href="index.php"
                class="bg-slate-100 text-slate-700 hover:bg-slate-200 px-4 py-2 rounded-lg text-sm font-medium flex items-center transition-colors">
                <i class="fa-solid fa-arrow-left w-4 h-4 mr-2"></i>
                Kembali
            </a>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column: Meeting Info -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Meeting Details Card -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-6">
                    <h2 class="text-lg font-semibold text-slate-900 mb-4">Informasi Rapat</h2>
                    
                    <?php if (!empty($meeting['description'])): ?>
                    <p class="text-slate-600 mb-6"><?= nl2br(htmlspecialchars($meeting['description'])) ?></p>
                    <?php endif; ?>

                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <span class="block text-xs text-slate-400 font-semibold uppercase mb-1">Tanggal</span>
                            <span class="text-slate-800 font-medium">
                                <?= date('l, d F Y', strtotime($meeting['meeting_date'])) ?>
                            </span>
                        </div>
                        <div>
                            <span class="block text-xs text-slate-400 font-semibold uppercase mb-1">Waktu</span>
                            <span class="text-slate-800 font-medium">
                                <?= substr($meeting['start_time'], 0, 5) ?> - <?= substr($meeting['end_time'], 0, 5) ?> WIB
                            </span>
                        </div>
                        <div>
                            <span class="block text-xs text-slate-400 font-semibold uppercase mb-1">Tipe</span>
                            <?php if ($meeting['type'] === 'online'): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-800">
                                    <i class="fa-solid fa-video w-3 h-3 mr-1"></i>
                                    Online
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                    <i class="fa-solid fa-location-dot w-3 h-3 mr-1"></i>
                                    Offline
                                </span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="block text-xs text-slate-400 font-semibold uppercase mb-1">
                                <?= $meeting['type'] === 'online' ? 'Link' : 'Lokasi' ?>
                            </span>
                            <span class="text-slate-800 font-medium">
                                <?php if ($meeting['type'] === 'online' && !empty($meeting['location'])): ?>
                                    <a href="<?= htmlspecialchars($meeting['location']) ?>" target="_blank" class="text-indigo-600 hover:underline"><?= htmlspecialchars($meeting['location']) ?></a>
                                <?php else: ?>
                                    <?= htmlspecialchars($meeting['location'] ?: '-') ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div>
                            <span class="block text-xs text-slate-400 font-semibold uppercase mb-1">Pembuat</span>
                            <span class="text-slate-800 font-medium"><?= htmlspecialchars($meeting['creator_name']) ?></span>
                        </div>
                        <div>
                            <span class="block text-xs text-slate-400 font-semibold uppercase mb-1">Dibuat</span>
                            <span class="text-slate-800 font-medium"><?= date('d M Y, H:i', strtotime($meeting['created_at'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Participants Card -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-6 border-b border-slate-100">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-slate-900">Peserta Rapat</h2>
                        <span class="bg-indigo-100 text-indigo-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
                            <?= count($participants) ?> Orang
                        </span>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Nama</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Waktu Hadir</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            <?php if (empty($participants)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-slate-400">Belum ada peserta.</td>
                            </tr>
                            <?php endif; ?>
                            <?php foreach ($participants as $p): ?>
                            <tr id="row-<?= $p['id'] ?>" class="hover:bg-slate-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-8 w-8 rounded-full bg-slate-200 flex items-center justify-center text-slate-500 text-sm font-medium">
                                            <?= strtoupper(substr($p['full_name'], 0, 1)) ?>
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-medium text-slate-900"><?= htmlspecialchars($p['full_name']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $statusClass = 'bg-gray-100 text-gray-800';
                                    $statusText = 'Diundang';
                                    
                                    switch($p['status']) {
                                        case 'present':
                                            $statusClass = 'bg-green-100 text-green-800';
                                            $statusText = 'Hadir';
                                            break;
                                        case 'absent':
                                            $statusClass = 'bg-red-100 text-red-800';
                                            $statusText = 'Tidak Hadir';
                                            break;
                                    }
                                    ?>
                                    <span id="badge-<?= $p['id'] ?>" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $statusClass ?>">
                                        <?= $statusText ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                                    <?= $p['attendance_time'] ? date('H:i', strtotime($p['attendance_time'])) : '-' ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <button onclick="toggleStatus(<?= $p['id'] ?>, '<?= $p['status'] ?>')"
                                        class="text-indigo-600 hover:text-indigo-900 text-sm font-medium hover:bg-indigo-50 px-3 py-1.5 rounded-lg transition-colors">
                                        Toggle Kehadiran
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Usulan & Notulen -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Usulan Card -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-indigo-50/30">
                        <h2 class="text-lg font-semibold text-slate-900 flex items-center">
                            <i class="fa-solid fa-message w-5 h-5 mr-2 text-indigo-600"></i>
                            Usulan Rapat
                        </h2>
                        <button onclick="openNoteModal('usulan')" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium transition-colors hover:bg-indigo-100/50 px-2 py-1 rounded">
                            + Tambah
                        </button>
                    </div>
                    <div class="p-6 space-y-4 max-h-[400px] overflow-y-auto">
                        <?php if (empty($usulan)): ?>
                            <p class="text-center text-slate-400 text-sm py-4">Belum ada usulan.</p>
                        <?php endif; ?>
                        <?php foreach ($usulan as $u): ?>
                            <div class="bg-slate-50 rounded-lg p-4 border border-slate-100 hover:border-indigo-200 transition-colors">
                                <div class="flex justify-between items-start mb-2">
                                    <span class="text-xs font-semibold text-indigo-600"><?= htmlspecialchars($u['user_name']) ?></span>
                                    <span class="text-[10px] text-slate-400"><?= date('d/m H:i', strtotime($u['created_at'])) ?></span>
                                </div>
                                <p class="text-sm text-slate-700 whitespace-pre-wrap"><?= htmlspecialchars($u['content']) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Notulen Card -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-emerald-50/30">
                        <h2 class="text-lg font-semibold text-slate-900 flex items-center">
                            <i class="fa-solid fa-file-lines w-5 h-5 mr-2 text-emerald-600"></i>
                            Notulen Rapat
                        </h2>
                        <button onclick="openNoteModal('notulen')" class="text-emerald-600 hover:text-emerald-800 text-sm font-medium transition-colors hover:bg-emerald-100/50 px-2 py-1 rounded">
                            + Tambah
                        </button>
                    </div>
                    <div class="p-6 space-y-4 max-h-[400px] overflow-y-auto">
                        <?php if (empty($notulen)): ?>
                            <p class="text-center text-slate-400 text-sm py-4">Belum ada notulen.</p>
                        <?php endif; ?>
                        <?php foreach ($notulen as $n): ?>
                            <div class="bg-slate-50 rounded-lg p-4 border border-slate-100 hover:border-emerald-200 transition-colors">
                                <div class="flex justify-between items-start mb-2">
                                    <span class="text-xs font-semibold text-emerald-600"><?= htmlspecialchars($n['user_name']) ?></span>
                                    <span class="text-[10px] text-slate-400"><?= date('d/m H:i', strtotime($n['created_at'])) ?></span>
                                </div>
                                <p class="text-sm text-slate-700 whitespace-pre-wrap"><?= htmlspecialchars($n['content']) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: QR Code -->
        <div class="space-y-6">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <h2 class="text-lg font-semibold text-slate-900 mb-4 text-center">QR Code Presensi</h2>
                <div class="flex flex-col items-center">
                    <div class="bg-white p-3 rounded-xl border-2 border-dashed border-slate-200">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($meeting['qr_token']) ?>"
                            alt="QR Code" class="w-48 h-48 object-contain">
                    </div>
                    <p class="mt-4 text-xs text-slate-400 text-center font-mono select-all bg-slate-50 px-3 py-2 rounded">
                        <?= $meeting['qr_token'] ?>
                    </p>
                    <div class="mt-4 px-4 py-2 bg-indigo-100 text-indigo-700 text-xs rounded-full font-bold">
                        Scan untuk Presensi
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <h3 class="text-sm font-semibold text-slate-900 mb-4">Statistik Kehadiran</h3>
                <?php
                $presentCount = count(array_filter($participants, function($p) { return $p['status'] === 'present'; }));
                $absentCount = count(array_filter($participants, function($p) { return $p['status'] === 'absent'; }));
                $invitedCount = count(array_filter($participants, function($p) { return $p['status'] === 'invited'; }));
                ?>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-slate-600">Hadir</span>
                        <span class="text-sm font-semibold text-green-600"><?= $presentCount ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-slate-600">Tidak Hadir</span>
                        <span class="text-sm font-semibold text-red-600"><?= $absentCount ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-slate-600">Belum Konfirmasi</span>
                        <span class="text-sm font-semibold text-slate-600"><?= $invitedCount ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Catatan -->
<div id="noteModal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-slate-900 bg-opacity-50 transition-opacity" onclick="closeNoteModal()"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                        <h3 class="text-xl leading-6 font-bold text-slate-900 mb-4" id="noteModalTitle">Tambah Catatan</h3>
                        <div class="mt-2">
                            <input type="hidden" id="note_type">
                            <label for="note_content" class="block text-sm font-medium text-slate-700 mb-2">Konten</label>
                            <textarea id="note_content" rows="6" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-slate-300 rounded-xl p-3 bg-slate-50" placeholder="Ketik isi catatan di sini..."></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-slate-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-3">
                <button type="button" id="submitNoteBtn" onclick="submitNote()" class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-4 py-2.5 bg-indigo-600 text-base font-semibold text-white hover:bg-indigo-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm transition-all">
                    Simpan Catatan
                </button>
                <button type="button" onclick="closeNoteModal()" class="mt-3 w-full inline-flex justify-center rounded-xl border border-slate-300 shadow-sm px-4 py-2.5 bg-white text-base font-medium text-slate-700 hover:bg-slate-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-all">
                    Batal
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentNoteType = 'usulan';

function openNoteModal(type) {
    currentNoteType = type;
    document.getElementById('noteModalTitle').innerText = type === 'usulan' ? 'Tambah Usulan' : 'Tambah Notulen';
    document.getElementById('note_type').value = type;
    document.getElementById('noteModal').classList.remove('hidden');
    document.getElementById('note_content').focus();
}

function closeNoteModal() {
    document.getElementById('noteModal').classList.add('hidden');
    document.getElementById('note_content').value = '';
}

async function submitNote() {
    const content = document.getElementById('note_content').value;
    if (!content.trim()) {
        alert('Konten tidak boleh kosong');
        return;
    }

    const btn = document.getElementById('submitNoteBtn');
    const originalText = btn.innerText;
    btn.innerText = 'Menyimpan...';
    btn.disabled = true;

    try {
        const response = await fetch('../../logic/meetings/add_note.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                meeting_id: <?= $id ?>,
                type: currentNoteType,
                content: content
            })
        });

        const data = await response.json();

        if (data.success) {
            location.reload();
        } else {
            alert('Gagal: ' + (data.message || 'Kesalahan tidak diketahui'));
            btn.innerText = originalText;
            btn.disabled = false;
        }
    } catch (e) {
        console.error(e);
        alert('Error terhubung ke server');
        btn.innerText = originalText;
        btn.disabled = false;
    }
}

async function toggleStatus(id, currentStatus) {
    try {
        const btn = document.activeElement;
        const originalText = btn.innerText;
        btn.innerText = 'Memproses...';
        btn.disabled = true;

        const response = await fetch('../../logic/meetings/toggle_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, current_status: currentStatus })
        });

        const data = await response.json();

        if (data.success) {
            location.reload();
        } else {
            alert('Gagal: ' + (data.message || 'Kesalahan tidak diketahui'));
            btn.innerText = originalText;
            btn.disabled = false;
        }
    } catch (e) {
        console.error(e);
        alert('Error terhubung ke server');
    }
}
</script>

<?php include '../layouts/footer.php'; ?>