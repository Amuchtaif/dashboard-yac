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
                <span class="font-medium text-indigo-600">Detail Rapat</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900"><?= htmlspecialchars($meeting['title']) ?></h1>
            <p class="mt-1 text-slate-500"><?= htmlspecialchars($meeting['division_name']) ?></p>
        </div>
        <div class="flex space-x-3">
            <a href="edit.php?id=<?= $meeting['id'] ?>"
                class="bg-amber-50 text-amber-700 hover:bg-amber-100 px-4 py-2 rounded-lg text-sm font-medium flex items-center transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Edit Rapat
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
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    Online
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
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
                                    $statusClass = match($p['status']) {
                                        'present' => 'bg-green-100 text-green-800',
                                        'absent' => 'bg-red-100 text-red-800',
                                        default => 'bg-gray-100 text-gray-800'
                                    };
                                    $statusText = match($p['status']) {
                                        'present' => 'Hadir',
                                        'absent' => 'Tidak Hadir',
                                        default => 'Diundang'
                                    };
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
                $presentCount = count(array_filter($participants, fn($p) => $p['status'] === 'present'));
                $absentCount = count(array_filter($participants, fn($p) => $p['status'] === 'absent'));
                $invitedCount = count(array_filter($participants, fn($p) => $p['status'] === 'invited'));
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

<script>
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