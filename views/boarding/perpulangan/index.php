<?php
require_once '../../../config/app.php';
require_once '../../../config/database.php';

check_permission('can_access_kesantrian');

$page_title = "Monitoring Perpulangan Santri";
$db = new Database();
$conn = $db->getConnection();

$now = date('Y-m-d H:i:s');
$search = isset($_GET['search']) ? $_GET['search'] : '';

// 1. Fetch Stats
$stats_query = "
    SELECT category, COUNT(*) as count
    FROM boarding_permits
    WHERE status = 'Disetujui'
    AND :now BETWEEN start_date AND end_date
    GROUP BY category
";
$stmt_stats = $conn->prepare($stats_query);
$stmt_stats->bindParam(':now', $now);
$stmt_stats->execute();
$stats_rows = $stmt_stats->fetchAll(PDO::FETCH_ASSOC);

$total_at_home = 0;
$stats_map = [
    'Izin' => 0,
    'Sakit' => 0,
    'Libur' => 0
];

foreach ($stats_rows as $row) {
    if (isset($stats_map[$row['category']])) {
        $stats_map[$row['category']] = (int) $row['count'];
        $total_at_home += (int) $row['count'];
    }
}

// 2. Fetch All Rooms to ensure we see all dorms
$rooms_query = "SELECT id, room_name FROM boarding_rooms ORDER BY room_name ASC";
$rooms = $conn->query($rooms_query)->fetchAll(PDO::FETCH_ASSOC);

// 3. Fetch Active Homecoming Students with Room Info
$active_query = "
    SELECT bp.id, bp.student_id, s.nama_siswa, s.nomor_induk, s.kelas, s.foto,
           bp.category, bp.reason, bp.start_date, bp.end_date, bp.status,
           br.room_name, br.id as room_id
    FROM boarding_permits bp
    JOIN students s ON bp.student_id = s.id
    LEFT JOIN boarding_room_members brm ON s.id = brm.student_id
    LEFT JOIN boarding_rooms br ON brm.room_id = br.id
    WHERE bp.status = 'Disetujui'
    AND :now BETWEEN bp.start_date AND bp.end_date
";

if (!empty($search)) {
    $active_query .= " AND s.nama_siswa LIKE :search";
}

$stmt_active = $conn->prepare($active_query);
$stmt_active->bindParam(':now', $now);
if (!empty($search)) {
    $search_param = "%$search%";
    $stmt_active->bindParam(':search', $search_param);
}
$stmt_active->execute();
$active_list = $stmt_active->fetchAll(PDO::FETCH_ASSOC);

// 4. Group by Room
$grouped_data = [];
$room_counts = [];
foreach ($rooms as $room) {
    $grouped_data[$room['room_name']] = [];
    $room_counts[$room['room_name']] = 0;
}
$grouped_data['Tanpa Asrama'] = []; // For students not assigned to a room

foreach ($active_list as $student) {
    $rName = $student['room_name'] ?? 'Tanpa Asrama';
    $grouped_data[$rName][] = $student;
    if (isset($room_counts[$rName])) $room_counts[$rName]++;
}

include '../../layouts/header.php';
?>

<div class="space-y-6 pb-12">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800"><?php echo $page_title; ?></h1>
            <p class="text-slate-500 mt-1">Pantau santri yang sedang tidak berada di asrama.</p>
        </div>
        <div class="flex gap-2 mt-4 sm:mt-0">
             <form method="GET" class="relative group">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                    placeholder="Cari nama santri..." 
                    class="pl-10 pr-4 py-2 bg-white border border-slate-200 rounded-xl text-sm focus:ring-emerald-500 focus:border-emerald-500 w-64 transition-all">
                <div class="absolute left-3.5 top-2.5 text-slate-400 group-focus-within:text-emerald-500 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </div>
            </form>
            <a href="../permits/index.php" class="inline-flex items-center justify-center rounded-xl bg-slate-800 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-slate-700 transition-all">
                Kelola Izin
            </a>
        </div>
    </div>

    <!-- Stats Section -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- Main Highlight Card -->
        <div class="md:col-span-1 bg-gradient-to-br from-emerald-600 to-teal-700 rounded-2xl p-6 text-white shadow-lg relative overflow-hidden group">
            <div class="relative z-10">
                <p class="text-xs font-bold uppercase tracking-widest text-emerald-100/80">Santri di Rumah</p>
                <div class="flex items-end gap-2 mt-2">
                    <h2 class="text-5xl font-black"><?php echo $total_at_home; ?></h2>
                    <span class="text-sm font-medium mb-1.5 text-emerald-100/90">Santri</span>
                </div>
                <p class="text-[11px] mt-4 text-emerald-50/80 font-medium leading-relaxed">Saat ini santri sedang dalam masa perizinan atau libur.</p>
            </div>
            <!-- Decore Icon from image -->
            <div class="absolute -right-4 -bottom-4 opacity-20 transform group-hover:scale-110 transition-transform duration-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-32 w-32" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
            </div>
        </div>

        <!-- Breakdown Cards -->
        <div class="bg-white border border-slate-200 rounded-2xl p-5 flex flex-col justify-between shadow-sm hover:shadow-md transition-shadow">
            <div class="h-10 w-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
            </div>
            <div>
                <p class="text-sm font-bold text-slate-500">Izin Pulang</p>
                <p class="text-3xl font-black text-slate-800 mt-1"><?php echo $stats_map['Izin']; ?></p>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl p-5 flex flex-col justify-between shadow-sm hover:shadow-md transition-shadow">
            <div class="h-10 w-10 bg-orange-50 text-orange-600 rounded-xl flex items-center justify-center mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
            </div>
            <div>
                <p class="text-sm font-bold text-slate-500">Santri Sakit</p>
                <p class="text-3xl font-black text-slate-800 mt-1"><?php echo $stats_map['Sakit']; ?></p>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl p-5 flex flex-col justify-between shadow-sm hover:shadow-md transition-shadow">
            <div class="h-10 w-10 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <div>
                <p class="text-sm font-bold text-slate-500">Masa Libur</p>
                <p class="text-3xl font-black text-slate-800 mt-1"><?php echo $stats_map['Libur']; ?></p>
            </div>
        </div>
    </div>

    <!-- Active List Container -->
    <div class="space-y-10 pt-4">
        <?php foreach ($grouped_data as $roomName => $students): ?>
            <?php 
                // Skip 'Tanpa Asrama' if it's empty
                if ($roomName === 'Tanpa Asrama' && empty($students)) continue;
                $count = count($students);
            ?>
            <div id="room-group-<?php echo md5($roomName); ?>" class="space-y-6">
                <!-- Room Header -->
                <div class="flex items-center justify-between border-b border-slate-200 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-xl bg-white shadow-sm border border-slate-100 flex items-center justify-center text-slate-400">
                             <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-slate-800"><?php echo htmlspecialchars($roomName); ?></h2>
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-widest mt-0.5"><?php echo $count; ?> Santri di Rumah</p>
                        </div>
                    </div>
                    <?php if ($count === 0): ?>
                        <span class="inline-flex items-center px-3 py-1 rounded-full bg-rose-50 text-rose-600 text-[10px] font-black uppercase tracking-tight border border-rose-100 italic">
                            Belum Ada yang Pulang
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center px-3 py-1 rounded-full bg-emerald-50 text-emerald-600 text-[10px] font-black uppercase tracking-tight border border-emerald-100">
                            Aktif
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Student Grid for this Room -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if ($count > 0): ?>
                        <?php foreach ($students as $student): ?>
                            <!-- Student Card (Same as before) -->
                            <div class="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 group">
                                <div class="flex items-start justify-between mb-5">
                                    <div class="flex items-center gap-4">
                                        <div class="relative">
                                            <?php 
                                                $avatar_url = !empty($student['foto']) 
                                                    ? BASE_URL . '/public/uploads/students/' . $student['foto'] 
                                                    : "https://ui-avatars.com/api/?name=" . urlencode($student['nama_siswa']) . "&background=random&size=100";
                                            ?>
                                            <img src="<?php echo $avatar_url; ?>" class="h-14 w-14 rounded-2xl object-cover ring-4 ring-slate-50 group-hover:ring-emerald-50 transition-all" alt="Santri">
                                            <div class="absolute -bottom-1 -right-1 h-5 w-5 bg-emerald-500 border-2 border-white rounded-full"></div>
                                        </div>
                                        <div class="min-w-0">
                                            <h3 class="font-bold text-slate-800 truncate leading-tight"><?php echo htmlspecialchars($student['nama_siswa']); ?></h3>
                                            <p class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1"><?php echo htmlspecialchars($student['category']); ?> • <span class="text-emerald-600"><?php echo htmlspecialchars($student['kelas']); ?></span></p>
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center px-2 py-1 rounded-lg bg-emerald-50 text-emerald-600 text-[10px] font-black uppercase tracking-tight border border-emerald-100/50 group-hover:bg-emerald-500 group-hover:text-white transition-colors">
                                        Aktif
                                    </span>
                                </div>

                                <div class="bg-slate-50/80 rounded-2xl p-4 space-y-3 mb-5 border border-slate-100/50">
                                    <div class="flex items-center gap-2">
                                        <div class="h-6 w-6 rounded-lg bg-white shadow-sm flex items-center justify-center text-slate-400">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        </div>
                                        <p class="text-xs text-slate-600 font-medium truncate italic">"<?php echo htmlspecialchars($student['reason']); ?>"</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="h-6 w-6 rounded-lg bg-white shadow-sm flex items-center justify-center text-slate-400">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                        </div>
                                        <p class="text-[11px] text-slate-500 font-bold uppercase tracking-tight">KEMBALI: <span class="text-slate-800"><?php echo date('d M Y', strtotime($student['end_date'])); ?></span></p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 pt-1">
                                    <button onclick="confirmReturn('<?php echo $student['id']; ?>', '<?php echo addslashes($student['nama_siswa']); ?>')" 
                                        class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 hover:bg-emerald-600 hover:text-white hover:border-emerald-600 transition-all group/btn shadow-sm active:scale-95">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 group-hover/btn:animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                                        Konfirmasi Kembali
                                    </button>
                                    <a href="../permits/index.php" class="p-2.5 bg-slate-50 text-slate-400 rounded-xl hover:bg-slate-100 hover:text-slate-600 transition-colors border border-transparent">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Empty State for this Room -->
                        <div class="col-span-full py-12 bg-slate-50 border-2 border-dashed border-slate-200 rounded-[32px] text-center flex flex-col items-center justify-center opacity-70">
                             <p class="text-slate-400 font-bold text-sm">Belum ada santri dari asrama ini yang terdata pulang.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (count($active_list) === 0): ?>
            <div class="col-span-full py-24 bg-white border-2 border-dashed border-slate-200 rounded-[32px] text-center flex flex-col items-center justify-center">
                <div class="h-20 w-20 bg-emerald-50 text-emerald-300 rounded-3xl flex items-center justify-center mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                </div>
                <p class="text-slate-500 font-bold text-lg">Tidak ada santri yang sedang tidak di asrama.</p>
                <?php if ($search): ?>
                    <a href="?" class="mt-6 px-6 py-2 bg-slate-800 text-white text-xs font-bold rounded-xl hover:bg-slate-700 transition-all">Lihat Semua</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function confirmReturn(id, name) {
        if(confirm(`Konfirmasi bahwa santri ${name} sudah kembali ke asrama?`)) {
            // Use existing logic if available or direct post
            const f = document.createElement('form'); f.method='POST'; f.action='../../../logic/boarding/manage_permits.php';
            const a = document.createElement('input'); a.type='hidden'; a.name='action'; a.value='update_status'; f.appendChild(a);
            const i = document.createElement('input'); i.type='hidden'; i.name='id'; i.value=id; f.appendChild(i);
            const s = document.createElement('input'); s.type='hidden'; s.name='status'; s.value='Kembali'; f.appendChild(s);
            document.body.appendChild(f); f.submit();
        }
    }
</script>

<?php include '../../layouts/footer.php'; ?>
