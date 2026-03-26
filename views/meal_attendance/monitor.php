<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$db = new Database();
$conn = $db->getConnection();

// --- ROLE-BASED ACCESS CONTROL ---
// Get current user info (position and supervised room)
$user_id = $_SESSION['user_id'];
$user_stmt = $conn->prepare("SELECT e.id, p.name as position_name, br.id as supervised_room_id FROM employees e JOIN positions p ON e.position_id = p.id LEFT JOIN boarding_rooms br ON br.supervisor_id = e.id WHERE e.id = ?");
$user_stmt->execute([$user_id]);
$currentUser = $user_stmt->fetch(PDO::FETCH_ASSOC);

$is_admin = ($currentUser['position_name'] === 'Administrator');
$is_musyrif = (strpos(strtolower($currentUser['position_name']), 'musyrif') !== false);
$supervised_room = $currentUser['supervised_room_id'] ?? null;

// Page Title & Access Control
$page_title = "Absensi Makan Santri (List)";
if (!$is_admin && !$is_musyrif && !can('can_access_kesantrian')) {
    redirect('views/dashboard/index.php?error=unauthorized');
}

// Fetch Filters Data
$grades = $conn->query("SELECT id, name FROM grade_levels ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$rooms = $conn->query("SELECT id, room_name FROM boarding_rooms ORDER BY room_name ASC")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../layouts/header.php';
?>

<input type="hidden" id="supervised_room" value="<?php echo $supervised_room; ?>">
<input type="hidden" id="is_musyrif" value="<?php echo $is_musyrif ? '1' : '0'; ?>">

    <div class="sm:flex sm:items-center sm:justify-between mb-8">
        <div>
            <h2 class="text-xl font-bold text-slate-900">Absensi Makan Santri</h2>
            <p class="mt-2 text-sm text-slate-500">Tandai kehadiran makan santri per kelas atau asrama.</p>
        </div>
        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
            <!-- Refresh button removed as per request -->
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <div class="flex items-center">
                <div class="p-3 rounded-xl bg-emerald-50 text-emerald-600 mr-4">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Sudah Makan</p>
                    <h3 class="text-2xl font-black text-slate-900" id="stat-eaten">0</h3>
                </div>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <div class="flex items-center">
                <div class="p-3 rounded-xl bg-orange-50 text-orange-600 mr-4">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Belum Makan</p>
                    <h3 class="text-2xl font-black text-slate-900" id="stat-remaining">0</h3>
                </div>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <div class="flex items-center">
                <div class="p-3 rounded-xl bg-blue-50 text-blue-600 mr-4">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" /></svg>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Santri</p>
                    <h3 class="text-2xl font-black text-slate-900" id="stat-total">0</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Multi-Filter Bar -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 mb-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
            <!-- Date -->
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Pilih Tanggal</label>
                <input type="date" id="filter-date" value="<?php echo date('Y-m-d'); ?>" onchange="fetchData()" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-4 focus:ring-cyan-500/10 focus:border-cyan-500 text-sm font-semibold outline-none bg-slate-50 transition-all">
            </div>

            <!-- Meal Type -->
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Waktu Makan</label>
                <select id="filter-meal-type" onchange="fetchData()" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-4 focus:ring-cyan-500/10 focus:border-cyan-500 text-sm font-semibold outline-none bg-slate-50 transition-all appearance-none cursor-pointer">
                    <option value="Pagi">Makan Pagi</option>
                    <option value="Siang" selected>Makan Siang</option>
                    <option value="Malam">Makan Malam</option>
                </select>
            </div>

            <!-- Class Filter -->
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Filter Per Kelas</label>
                <select id="filter-grade" onchange="resetOtherFilter('room')" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-4 focus:ring-cyan-500/10 focus:border-cyan-500 text-sm font-semibold outline-none bg-slate-50 transition-all appearance-none cursor-pointer">
                    <option value="">Semua Kelas</option>
                    <?php foreach($grades as $g): ?>
                        <option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Room Filter -->
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Filter Per Asrama</label>
                <select id="filter-room" onchange="resetOtherFilter('grade')" 
                    class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-4 focus:ring-cyan-500/10 focus:border-cyan-500 text-sm font-semibold outline-none bg-slate-50 transition-all appearance-none cursor-pointer disabled:bg-slate-100 disabled:text-slate-400"
                    <?php echo $is_musyrif ? 'disabled' : ''; ?>>
                    <option value="">Semua Asrama</option>
                    <?php foreach($rooms as $r): ?>
                        <option value="<?php echo $r['id']; ?>" <?php echo ($is_musyrif && $supervised_room == $r['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($r['room_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="mt-6">
            <button onclick="fetchData()" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-3.5 rounded-xl transition-all shadow-lg shadow-cyan-600/20 hover:shadow-cyan-600/40 flex items-center justify-center gap-2 active:scale-[0.98]">
                <svg class="w-5 h-5 font-bold" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                Tampilkan Daftar Santri
            </button>
        </div>
    </div>

    <!-- Attendance Roster -->
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden min-h-[400px]">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                        <th class="px-6 py-4 w-12 text-center">No.</th>
                        <th class="px-6 py-4">Santri & Identitas</th>
                        <th class="px-6 py-4">Kelas / Kamar</th>
                        <th class="px-6 py-4 text-center">Status Jatah</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody id="roster-body" class="divide-y divide-slate-100 text-sm">
                    <tr>
                        <td colspan="5" class="px-6 py-20 text-center text-slate-400 italic">Silakan pilih filter dan klik "Tampilkan Daftar Santri"</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

<script>
    window.onload = function() {
        const isMusyrif = document.getElementById('is_musyrif').value === '1';
        const supervisedRoom = document.getElementById('supervised_room').value;
        
        if(isMusyrif && supervisedRoom) {
            document.getElementById('filter-room').value = supervisedRoom;
            fetchData(); // Auto fetch for musyrif
        }
    };

    function resetOtherFilter(type) {
        if(type === 'room') document.getElementById('filter-room').value = '';
        if(type === 'grade') document.getElementById('filter-grade').value = '';
    }

    async function fetchData() {
        const date = document.getElementById('filter-date').value;
        const meal_type = document.getElementById('filter-meal-type').value;
        const grade_id = document.getElementById('filter-grade').value;
        const room_id = document.getElementById('filter-room').value;

        const rosterBody = document.getElementById('roster-body');
        rosterBody.innerHTML = '<tr><td colspan="5" class="px-6 py-20 text-center text-cyan-600 font-bold animate-pulse">Memuat data santri...</td></tr>';

        try {
            const url = `../../api/meal_attendance/get_students_list.php?date=${date}&meal_type=${meal_type}&grade_id=${grade_id}&room_id=${room_id}`;
            const res = await fetch(url);
            const json = await res.json();
            
            if(json.success) {
                renderRoster(json.data, meal_type);
                updateStats(json.data);
            } else {
                showToast(json.message, "error");
            }
        } catch (e) {
            console.error(e);
            showToast("Gagal mengambil data.", "error");
        }
    }

    function updateStats(data) {
        const total = data.length;
        const eaten = data.filter(s => s.attendance_id).length;
        const remaining = total - eaten;

        document.getElementById('stat-eaten').innerText = eaten;
        document.getElementById('stat-remaining').innerText = remaining;
        document.getElementById('stat-total').innerText = total;
    }

    function renderRoster(data, mealType) {
        const tbody = document.getElementById('roster-body');
        tbody.innerHTML = '';

        if(data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-20 text-center text-slate-400 italic">Tidak ada data santri ditemukan.</td></tr>';
            return;
        }

        data.forEach((s, index) => {
            const hasEaten = !!s.attendance_id;
            const statusBadge = hasEaten 
                ? `<span class="inline-flex px-3 py-1 text-[10px] font-bold rounded-full bg-emerald-50 text-emerald-600 border border-emerald-100">SUDAH MAKAN (${s.check_time.substring(0, 5)})</span>`
                : `<span class="inline-flex px-3 py-1 text-[10px] font-bold rounded-full bg-slate-100 text-slate-400 border border-slate-200">BELUM AMBIL</span>`;

            const actionBtn = hasEaten
                ? `<button onclick="unmarkMeal(${s.attendance_id})" class="text-xs font-bold text-red-500 hover:text-red-700 px-3 py-2 rounded-lg hover:bg-red-50 transition-all">Batal Makan</button>`
                : `<button onclick="markMeal(${s.id}, '${mealType}')" class="bg-cyan-50 text-cyan-700 hover:bg-cyan-600 hover:text-white px-4 py-2 rounded-lg text-xs font-bold border border-cyan-100 transition-all">Ambil Jatah</button>`;

            tbody.innerHTML += `
                <tr class="hover:bg-slate-50/50 transition-colors group">
                    <td class="px-6 py-4 text-slate-400 font-medium text-center">${index + 1}.</td>
                    <td class="px-6 py-4">
                        <div class="flex items-center">
                            <div class="h-10 w-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-500 font-bold border border-white ring-2 ring-slate-50 group-hover:ring-cyan-50 group-hover:bg-cyan-50 group-hover:text-cyan-600 transition-all">
                                ${s.nama_siswa.substring(0, 1).toUpperCase()}
                            </div>
                            <div class="ml-4">
                                <p class="font-bold text-slate-700 group-hover:text-cyan-600 transition-colors">${s.nama_siswa}</p>
                                <p class="text-[11px] text-slate-400 font-medium tracking-tight">NIS: ${s.nomor_induk || '-'}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <p class="text-xs font-bold text-slate-600">Kelas: ${s.kelas || '-'}</p>
                        <p class="text-[10px] text-slate-400 font-medium uppercase tracking-wider">${s.room_name || 'Tanpa Asrama'}</p>
                    </td>
                    <td class="px-6 py-4 text-center">
                        ${statusBadge}
                    </td>
                    <td class="px-6 py-4 text-right">
                        ${actionBtn}
                    </td>
                </tr>
            `;
        });
    }

    async function markMeal(studentId, type) {
        try {
            const res = await fetch('../../api/meal_attendance/scan.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ student_id: studentId, meal_type: type })
            });
            const json = await res.json();
            if(json.success) {
                showToast(json.message, "success");
                fetchData();
            } else {
                showToast(json.message, "error");
            }
        } catch (e) {
            showToast("Gagal koneksi ke server.", "error");
        }
    }

    async function unmarkMeal(attendanceId) {
        if(!confirm("Batalkan status sudah makan santri ini?")) return;
        try {
            const res = await fetch('../../api/meal_attendance/delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: attendanceId })
            });
            const json = await res.json();
            if(json.success) {
                showToast(json.message, "success");
                fetchData();
            } else {
                showToast(json.message, "error");
            }
        } catch (e) {
            showToast("Gagal koneksi ke server.", "error");
        }
    }
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
