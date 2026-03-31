<?php
require_once '../../../config/app.php';
require_once '../../../config/database.php';

check_permission('can_access_kesantrian');

$room_id = $_GET['room_id'] ?? null;
$date = $_GET['date'] ?? date('Y-m-d');

if (!$room_id) {
    header('Location: index.php');
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Fetch room info with multiple supervisors
$room_stmt = $conn->prepare("
    SELECT br.*, 
    (SELECT GROUP_CONCAT(e.full_name SEPARATOR ', ') FROM boarding_room_supervisors brs JOIN employees e ON brs.supervisor_id = e.id WHERE brs.room_id = br.id) as supervisor_name 
    FROM boarding_rooms br 
    WHERE br.id = ?
");
$room_stmt->execute([$room_id]);
$room = $room_stmt->fetch(PDO::FETCH_ASSOC);

if (!$room) {
    header('Location: index.php');
    exit;
}

$page_title = "Absensi ASRAMA - " . $room['room_name'];

$filter = 'all'; // Default to show all students

// Fetch members with their attendance status for the selected date
$members_query = "
    SELECT s.id as student_id, s.nama_siswa, s.nomor_induk, s.kelas,
           ba.status as attendance_status, ba.notes as attendance_notes
    FROM boarding_room_members brm
    JOIN students s ON brm.student_id = s.id
    LEFT JOIN boarding_attendances ba ON ba.student_id = s.id AND ba.date = ?
    WHERE brm.room_id = ?
";

if ($filter === 'marked') {
    $members_query .= " AND ba.status IS NOT NULL ";
}

$members_query .= " ORDER BY s.nama_siswa ASC";

$members_stmt = $conn->prepare($members_query);
$members_stmt->execute([$date, $room_id]);
$members = $members_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Summary
$summary = ['Hadir' => 0, 'Sakit' => 0, 'Izin' => 0, 'Alpha' => 0, 'Belum Absen' => 0];
foreach ($members as $m) {
    if ($m['attendance_status']) {
        $summary[$m['attendance_status']]++;
    } else {
        $summary['Belum Absen']++;
    }
}

include '../../layouts/header.php';
?>

<div class="space-y-6 pb-12">
    <!-- Header/Breadcrumbs -->
    <div class="flex flex-col gap-2">
        <nav class="flex text-sm text-slate-400 font-medium">
            <a href="index.php?date=<?php echo $date; ?>" class="hover:text-indigo-600 transition-colors">Absensi Asrama</a>
            <span class="mx-2 text-slate-300">/</span>
            <span class="text-slate-600"><?php echo htmlspecialchars($room['room_name']); ?></span>
        </nav>
        <div class="sm:flex sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Data Absensi Santri</h1>
                <p class="text-slate-500 mt-1">Tanggal: <span class="font-bold text-slate-700"><?php echo date('d M Y', strtotime($date)); ?></span></p>
            </div>
            <div class="mt-4 sm:mt-0">
                <div class="flex items-center gap-3 bg-white p-2 rounded-xl border border-slate-200">
                    <label class="text-xs font-bold text-slate-400 uppercase ml-2">Tanggal:</label>
                    <input type="date" value="<?php echo $date; ?>" 
                        onchange="window.location.href='?room_id=<?php echo $room_id; ?>&date=' + this.value"
                        class="border-none bg-transparent text-sm font-bold text-slate-700 focus:ring-0">
                </div>
            </div>
        </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="bg-white p-4 rounded-2xl border border-slate-200 flex flex-col items-center justify-center text-center">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest leading-none mb-2">Hadir</p>
            <p class="text-2xl font-black text-emerald-600"><?php echo $summary['Hadir']; ?></p>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200 flex flex-col items-center justify-center text-center">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest leading-none mb-2">Sakit</p>
            <p class="text-2xl font-black text-amber-500"><?php echo $summary['Sakit']; ?></p>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200 flex flex-col items-center justify-center text-center">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest leading-none mb-2">Izin</p>
            <p class="text-2xl font-black text-blue-500"><?php echo $summary['Izin']; ?></p>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200 flex flex-col items-center justify-center text-center">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest leading-none mb-2">Alpha</p>
            <p class="text-2xl font-black text-rose-500"><?php echo $summary['Alpha']; ?></p>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-indigo-100 bg-indigo-50/30 flex flex-col items-center justify-center text-center">
            <p class="text-[10px] font-bold text-indigo-400 uppercase tracking-widest leading-none mb-2">Belum Absen</p>
            <p class="text-2xl font-black text-indigo-600"><?php echo $summary['Belum Absen']; ?></p>
        </div>
    </div>

    <!-- Attendance Data -->
    <div id="attendance-view" class="mt-8">
        <input type="hidden" name="room_id" value="<?php echo $room_id; ?>">
        <input type="hidden" name="date" value="<?php echo $date; ?>">

        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                            <th class="px-6 py-4 w-12 text-center">No.</th>
                            <th class="px-6 py-4 min-w-[200px]">Santri</th>
                            <th class="px-6 py-4 text-center min-w-[150px]">Status Kehadiran</th>
                            <th class="px-6 py-4 min-w-[200px] border-none">Keterangan / Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <?php if (count($members) > 0): ?>
                            <?php foreach ($members as $index => $m): ?>
                                <?php 
                                    $status = $m['attendance_status'];
                                    $badge_class = 'bg-slate-100 text-slate-500 border-slate-200';
                                    $label = 'Belum Absen';
                                    
                                    if ($status === 'Hadir') {
                                        $badge_class = 'bg-emerald-50 text-emerald-700 border-emerald-100';
                                        $label = 'Hadir';
                                    } elseif ($status === 'Sakit') {
                                        $badge_class = 'bg-amber-50 text-amber-700 border-amber-100';
                                        $label = 'Sakit';
                                    } elseif ($status === 'Izin') {
                                        $badge_class = 'bg-blue-50 text-blue-700 border-blue-100';
                                        $label = 'Izin';
                                    } elseif ($status === 'Alpha') {
                                        $badge_class = 'bg-rose-50 text-rose-700 border-rose-100';
                                        $label = 'Alpha';
                                    }
                                ?>
                                <tr class="hover:bg-slate-50/50 transition-colors group">
                                    <td class="px-6 py-4 text-slate-400 font-medium text-center"><?php echo $index + 1; ?>.</td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="h-10 w-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-500 font-bold border border-white ring-2 ring-slate-50 group-hover:ring-indigo-50 group-hover:bg-indigo-50 group-hover:text-indigo-600 transition-all">
                                                <?php echo strtoupper(substr($m['nama_siswa'], 0, 1)); ?>
                                            </div>
                                            <div class="ml-4">
                                                <p class="font-bold text-slate-700 group-hover:text-indigo-600 transition-colors"><?php echo htmlspecialchars($m['nama_siswa']); ?></p>
                                                <p class="text-[11px] text-slate-400 font-medium tracking-tight"><?php echo htmlspecialchars($m['nomor_induk'] ?? '-'); ?> • Kelas <?php echo htmlspecialchars($m['kelas'] ?? '-'); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-flex items-center px-4 py-1.5 rounded-full text-xs font-bold border <?php echo $badge_class; ?>">
                                            <?php echo $label; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-xs text-slate-500 italic">
                                            <?php echo !empty($m['attendance_notes']) ? htmlspecialchars($m['attendance_notes']) : '- tidak ada catatan -'; ?>
                                        </p>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="px-6 py-20 text-center">
                                    <div class="flex flex-col items-center justify-center text-slate-400">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mb-3 opacity-20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <p class="italic font-medium text-slate-500">
                                            <?php echo $filter === 'marked' ? 'Belum ada santri yang diabsen pada tanggal ini.' : 'Belum ada santri terdaftar di asrama ini.'; ?>
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    </div>
</div>

<?php include '../../layouts/header.php'; ?>
