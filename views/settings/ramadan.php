<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_employees');

$page_title = "Pengaturan Ramadan";

$db = new Database();
$conn = $db->getConnection();

// Fetch current ramadan settings
$stmt = $conn->query("SELECT * FROM ramadan_settings WHERE id = 1");
$ramadan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ramadan) {
    // Fallback if record missing
    $ramadan = ['is_active' => 0, 'half_day_end_time' => '12:00:00'];
}

// Fetch all units
$stmt = $conn->query("SELECT * FROM units ORDER BY name ASC");
$units = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all overrides
$stmtOverrides = $conn->query("SELECT * FROM ramadan_overrides ORDER BY label ASC, id ASC");
$overrides = $stmtOverrides->fetchAll(PDO::FETCH_ASSOC);

// Group overrides by label and unit_ids to reconstruct the grid UI
$grouped_overrides = [];
foreach ($overrides as $ov) {
    $key = $ov['label'] . '|' . $ov['unit_ids'];
    if (!isset($grouped_overrides[$key])) {
        $grouped_overrides[$key] = [
            'label' => $ov['label'],
            'unit_ids' => explode(',', $ov['unit_ids']),
            'days_data' => []
        ];
    }
    
    $days = explode(',', $ov['days']);
    foreach ($days as $d) {
        $grouped_overrides[$key]['days_data'][$d] = [
            'start' => date('H:i', strtotime($ov['start_time'])),
            'end' => date('H:i', strtotime($ov['end_time'])),
            'is_off' => 0
        ];
    }
}

$days_map = [
    'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 
    'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'
];
$days_list = array_keys($days_map);

include '../layouts/header.php';
?>

<div class="pb-10">
    <!-- Breadcrumbs -->
    <nav class="flex mb-4" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3 text-xs text-slate-500">
            <li class="inline-flex items-center">
                <a href="<?php url('views/dashboard/index.php'); ?>" class="hover:text-slate-800 flex items-center gap-1">
                    <i class="fa-solid fa-house w-3 h-3"></i>
                    Home
                </a>
            </li>
            <li>
                <div class="flex items-center">
                    <i class="fa-solid fa-chevron-right w-3 h-3 text-gray-400 mx-1"></i>
                    <a href="<?php url('views/settings/index.php'); ?>" class="ml-1 text-slate-500 hover:text-slate-800">Settings</a>
                </div>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <i class="fa-solid fa-chevron-right w-3 h-3 text-gray-400 mx-1"></i>
                    <span class="ml-1 font-medium text-slate-800">Pengaturan Ramadan</span>
                </div>
            </li>
        </ol>
    </nav>

    <!-- Page Header & Main Form -->
    <form id="ramadan-form" action="<?php echo url('logic/settings/update_ramadan.php'); ?>" method="POST">
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-slate-900 sm:truncate sm:text-3xl sm:tracking-tight">Pengaturan Jam Kerja Ramadan</h2>
            <p class="mt-1 text-sm text-slate-500">Konfigurasi jadwal khusus selama bulan suci Ramadan.</p>
        </div>
        <div class="mt-4 flex md:ml-4 md:mt-0 items-center justify-between md:justify-start gap-4 bg-white px-6 py-3 rounded-2xl shadow-sm border border-slate-200">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-moon w-5 h-5 text-cyan-600"></i>
                <span class="text-xs font-black text-slate-600 uppercase tracking-widest leading-none mt-1">Aktifasi Jadwal Ramadhan</span>
            </div>
            <label class="relative inline-flex items-center cursor-pointer group shrink-0">
                <input type="checkbox" name="is_active" form="ramadan-form" id="ramadan-toggle" onchange="syncHeaderToggle(this)" value="1" class="hidden peer header-ramadan-toggle" <?php echo $ramadan['is_active'] ? 'checked' : ''; ?>>
                <div class="w-14 h-7 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-7 peer-checked:after:border-white after:content-[''] after:absolute after:top-[4px] after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-cyan-600 shadow-inner group-hover:shadow transition-all"></div>
            </label>
        </div>
    </div>

    <!-- Dynamic Notification Bar for Toggle/Actions -->
    <div id="dynamic-notification-container" class="mb-6 h-0 overflow-visible relative z-50"></div>

    <div id="group-container" class="space-y-12">

        <!-- Override Groups -->
            <?php 
                $group_idx = 0;
                if (empty($grouped_overrides)) {
                    // Seed one empty group
                    $grouped_overrides['new|'] = ['label' => 'Grup Jadwal 1', 'unit_ids' => [], 'days_data' => []];
                }
                foreach ($grouped_overrides as $ov): 
            ?>
            <div class="group-card bg-white rounded-[3rem] shadow-xl border border-slate-200 overflow-hidden animate-fade-in" data-group-idx="<?php echo $group_idx; ?>">
                <!-- Group Header -->
                <div class="px-10 py-8 bg-slate-50/80 border-b border-slate-100 flex flex-wrap justify-between items-center gap-6">
                    <div class="flex-1 min-w-[300px]">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2 ml-1">Identitas Grup</label>
                        <div class="flex items-center gap-4">
                            <div class="p-3 bg-cyan-600 rounded-2xl text-white shadow-lg shadow-cyan-600/20">
                                <i class="fa-solid fa-clock w-6 h-6"></i>
                            </div>
                            <input type="text" name="groups[<?php echo $group_idx; ?>][label]" value="<?php echo htmlspecialchars($ov['label'] ?? "Grup #".($group_idx+1)); ?>" placeholder="Nama Grup (Contoh: Unit Satpam)" class="w-full max-w-md bg-transparent border-0 border-b-2 border-slate-200 focus:border-0 focus:border-b-2 focus:border-b-cyan-600 focus:ring-0 focus:ring-offset-0 focus:outline-none text-xl font-black text-slate-800 placeholder:text-slate-300 shadow-none focus:shadow-none hover:border-b-slate-300 transition-all px-0 py-2">
                        </div>
                    </div>
                    <button type="button" onclick="this.closest('.group-card').remove()" class="px-6 py-2.5 bg-rose-50 text-rose-600 rounded-xl text-[10px] font-black uppercase tracking-widest border border-rose-100 hover:bg-rose-100 transition-all">Hapus Grup Ini</button>
                </div>

                <div class="p-10 grid grid-cols-1 lg:grid-cols-12 gap-12">
                    <!-- Left: Day Grid (Grid Jadwal) -->
                    <div class="lg:col-span-12 xl:col-span-7">
                        <div class="flex items-center justify-between mb-6">
                            <h4 class="text-sm font-black text-slate-800 uppercase tracking-widest flex items-center gap-2">
                                <span class="w-2 h-6 bg-cyan-600 rounded-full"></span>
                                Konfigurasi Harian
                            </h4>
                        </div>
                        
                        <div class="border border-slate-200 rounded-3xl overflow-x-auto shadow-sm">
                            <table class="w-full text-left">
                                <thead class="bg-slate-50 border-b border-slate-200">
                                    <tr class="text-[10px] font-black text-slate-400 uppercase tracking-[0.1em]">
                                        <th class="px-6 py-4">Hari</th>
                                        <th class="px-6 py-4 text-center w-24">Libur</th>
                                        <th class="px-6 py-4">Jam Masuk</th>
                                        <th class="px-6 py-4">Jam Pulang</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($days_list as $day_eng): 
                                        $day_data = $ov['days_data'][$day_eng] ?? ['start' => '08:00', 'end' => '15:00', 'is_off' => 1];
                                        $id_prefix = "g{$group_idx}_{$day_eng}";
                                    ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <td class="px-6 py-4 font-black text-slate-700 text-sm"><?php echo $days_map[$day_eng]; ?></td>
                                        <td class="px-6 py-4 text-center">
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input type="checkbox" name="groups[<?php echo $group_idx; ?>][days][<?php echo $day_eng; ?>][is_off]" value="1" class="hidden peer day-off-toggle" onchange="toggleDayInputs(this)" <?php echo $day_data['is_off'] ? 'checked' : ''; ?>>
                                                <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-5 peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-rose-500"></div>
                                            </label>
                                        </td>
                                        <td class="px-6 py-4 cursor-pointer hover:bg-cyan-50 transition-colors" onclick="this.querySelector('input')?.showPicker()">
                                            <input type="time" name="groups[<?php echo $group_idx; ?>][days][<?php echo $day_eng; ?>][start]" value="<?php echo $day_data['start']; ?>" class="w-full bg-transparent border-0 text-xs font-bold text-slate-700 focus:ring-0 transition-all disabled:text-slate-400" <?php echo $day_data['is_off'] ? 'disabled' : ''; ?>>
                                        </td>
                                        <td class="px-6 py-4 cursor-pointer hover:bg-cyan-50 transition-colors" onclick="this.querySelector('input')?.showPicker()">
                                            <input type="time" name="groups[<?php echo $group_idx; ?>][days][<?php echo $day_eng; ?>][end]" value="<?php echo $day_data['end']; ?>" class="w-full bg-transparent border-0 text-xs font-bold text-slate-700 focus:ring-0 transition-all disabled:text-slate-400" <?php echo $day_data['is_off'] ? 'disabled' : ''; ?>>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Right: Unit Selection (Cakupan Unit) -->
                    <div class="lg:col-span-12 xl:col-span-5">
                        <div class="flex items-center justify-between mb-6">
                            <h4 class="text-sm font-black text-slate-800 uppercase tracking-widest flex items-center gap-2">
                                <span class="w-2 h-6 bg-emerald-600 rounded-full"></span>
                                Unit Terdampak
                            </h4>
                            <button type="button" onclick="selectAllInGroup(this, true)" class="text-[10px] font-black text-cyan-600 uppercase hover:underline">Pilih Semua</button>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 max-h-[460px] overflow-y-auto px-1">
                            <?php foreach ($units as $unit): 
                                $is_selected = in_array((string)$unit['id'], (array)$ov['unit_ids']);
                            ?>
                            <label class="relative cursor-pointer group/unit">
                                <input type="checkbox" name="groups[<?php echo $group_idx; ?>][units][]" value="<?php echo $unit['id']; ?>" class="hidden peer unit-checkbox" <?php echo $is_selected ? 'checked' : ''; ?>>
                                <div class="px-5 py-4 rounded-2xl border-2 border-slate-100 bg-slate-50 peer-checked:bg-emerald-600 peer-checked:border-emerald-600 peer-checked:text-white transition-all flex items-center gap-3 group-hover/unit:border-emerald-200 shadow-sm peer-checked:shadow-lg peer-checked:shadow-emerald-600/20">
                                    <div class="w-4 h-4 rounded-full border-2 border-slate-300 peer-checked:border-white relative flex items-center justify-center after:content-[''] after:w-2 after:h-2 after:bg-white after:rounded-full after:scale-0 peer-checked:after:scale-100 after:transition-transform"></div>
                                    <span class="text-xs font-bold leading-none"><?php echo htmlspecialchars($unit['name']); ?></span>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php $group_idx++; endforeach; ?>

        </div>

        <!-- Action Bar -->
        <div class="mt-16 mb-24 max-w-5xl mx-auto">
            <div class="bg-white/95 backdrop-blur-xl rounded-[2.5rem] p-3 flex flex-col sm:flex-row items-center justify-between border border-slate-200 shadow-[0_20px_50px_-12px_rgba(15,23,42,0.1)] ring-1 ring-slate-900/5 gap-4 sm:gap-6 relative z-40 group/bar">
                
                <div class="absolute inset-0 bg-gradient-to-r from-cyan-50 to-blue-50 rounded-[2.5rem] opacity-0 group-hover/bar:opacity-100 transition-opacity pointer-events-none"></div>

                <!-- Left: Add Group Button -->
                <button type="button" onclick="addNewGroup()" class="w-full sm:w-auto group relative px-6 py-3.5 bg-slate-50 border border-slate-200 rounded-[2rem] transition-all duration-300 flex items-center justify-center gap-4 overflow-hidden shadow-inner flex-shrink-0">
                    <div class="w-10 h-10 rounded-xl bg-cyan-50 text-cyan-600 border border-cyan-100 flex items-center justify-center group-hover:scale-110 group-hover:bg-cyan-600 group-hover:text-white group-hover:border-cyan-600 transition-all duration-300">
                        <i class="fa-solid fa-plus w-5 h-5"></i>
                    </div>
                    <div class="text-left">
                        <span class="block text-xs font-black text-slate-800 uppercase tracking-widest group-hover:text-cyan-600 transition-colors">Tambah Grup</span>
                        <span class="block text-[9px] font-bold text-slate-500 uppercase tracking-widest mt-0.5">Skema Jadwal Baru</span>
                    </div>
                </button>

                <!-- Center: Info -->
                <div class="hidden md:flex flex-1 items-center justify-center text-center">
                    <div>
                        <div class="flex items-center justify-center gap-2 mb-1">
                            <span class="w-2 h-2 rounded-full bg-cyan-500 animate-pulse outline outline-2 outline-offset-1 outline-cyan-500/20"></span>
                            <span class="text-slate-800 font-black text-xs uppercase tracking-widest italic">Konfigurasi Ramadan</span>
                        </div>
                        <p class="text-slate-500 text-[10px] font-bold uppercase tracking-[0.15em] relative">
                            Terapkan perubahan jadwal ke dalam sistem
                            <span class="absolute -bottom-2 left-1/2 -translate-x-1/2 w-12 h-0.5 bg-gradient-to-r from-transparent via-slate-300 to-transparent rounded-full opacity-50"></span>
                        </p>
                    </div>
                </div>

                <!-- Right: Save Button -->
                <button type="submit" class="w-full sm:w-auto min-w-[200px] group relative px-8 py-4 bg-gradient-to-r from-cyan-500 to-blue-600 text-white border border-transparent text-xs font-black uppercase tracking-[0.2em] rounded-[2rem] shadow-[0_4px_20px_rgba(6,182,212,0.3)] hover:shadow-[0_8px_30px_rgba(6,182,212,0.5)] hover:-translate-y-1 transition-all duration-300 active:scale-95 overflow-hidden flex items-center justify-center gap-3 flex-shrink-0">
                    <span class="relative z-10 flex items-center gap-3">
                        Simpan Setup
                        <div class="w-6 h-6 rounded-full bg-white/20 flex items-center justify-center group-hover:bg-white group-hover:text-blue-600 transition-colors duration-300 relative">
                            <i class="fa-solid fa-arrow-right w-3.5 h-3.5 relative z-10 transition-transform group-hover:translate-x-0.5"></i>
                        </div>
                    </span>
                    <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-[150%] skew-x-[-30deg] group-hover:animate-shine transition-transform duration-1000"></div>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
    let groupCounter = <?php echo $group_idx; ?>;

    function addNewGroup() {
        const container = document.getElementById('group-container');
        const idx = groupCounter;
        const div = document.createElement('div');
        div.className = 'group-card bg-white rounded-[3rem] shadow-xl border border-slate-200 overflow-hidden animate-fade-in mb-12';
        div.setAttribute('data-group-idx', idx);
        
        div.innerHTML = `
            <div class="px-10 py-8 bg-slate-50/80 border-b border-slate-100 flex flex-wrap justify-between items-center gap-6">
                <div class="flex-1 min-w-[300px]">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2 ml-1">Identitas Grup</label>
                    <div class="flex items-center gap-4">
                        <div class="p-3 bg-cyan-600 rounded-2xl text-white shadow-lg shadow-cyan-600/20">
                            <i class="fa-solid fa-clock w-6 h-6"></i>
                        </div>
                        <input type="text" name="groups[${idx}][label]" value="Grup Baru ${idx + 1}" placeholder="Nama Grup (Contoh: Unit Satpam)" class="w-full max-w-md bg-transparent border-0 border-b-2 border-slate-200 focus:border-0 focus:border-b-2 focus:border-b-cyan-600 focus:ring-0 focus:ring-offset-0 focus:outline-none text-xl font-black text-slate-800 placeholder:text-slate-300 shadow-none focus:shadow-none hover:border-b-slate-300 transition-all px-0 py-2">
                    </div>
                </div>
                <button type="button" onclick="this.closest('.group-card').remove()" class="px-6 py-2.5 bg-rose-50 text-rose-600 rounded-xl text-[10px] font-black uppercase tracking-widest border border-rose-100 hover:bg-rose-100 transition-all">Hapus Grup Ini</button>
            </div>
            <div class="p-10 grid grid-cols-1 lg:grid-cols-12 gap-12">
                <div class="lg:col-span-12 xl:col-span-7">
                    <div class="flex items-center justify-between mb-6">
                        <h4 class="text-sm font-black text-slate-800 uppercase tracking-widest flex items-center gap-2">
                            <span class="w-2 h-6 bg-cyan-600 rounded-full"></span>
                            Konfigurasi Harian
                        </h4>
                    </div>
                    <div class="border border-slate-200 rounded-3xl overflow-x-auto shadow-sm">
                        <table class="w-full text-left">
                            <thead class="bg-slate-50 border-b border-slate-200">
                                <tr class="text-[10px] font-black text-slate-400 uppercase tracking-[0.1em]">
                                    <th class="px-6 py-4">Hari</th>
                                    <th class="px-6 py-4 text-center w-24">Libur</th>
                                    <th class="px-6 py-4">Jam Masuk</th>
                                    <th class="px-6 py-4">Jam Pulang</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($days_list as $day_eng): ?>
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-4 font-black text-slate-700 text-sm"><?php echo $days_map[$day_eng]; ?></td>
                                    <td class="px-6 py-4 text-center">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="groups[${idx}][days][<?php echo $day_eng; ?>][is_off]" value="1" class="hidden peer day-off-toggle" onchange="toggleDayInputs(this)">
                                            <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-5 peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-rose-500"></div>
                                        </label>
                                    </td>
                                    <td class="px-6 py-4 cursor-pointer hover:bg-cyan-50 transition-colors" onclick="this.querySelector('input')?.showPicker()">
                                        <input type="time" name="groups[${idx}][days][<?php echo $day_eng; ?>][start]" value="08:00" class="w-full bg-transparent border-0 text-xs font-bold text-slate-700 focus:ring-0 transition-all disabled:text-slate-400">
                                    </td>
                                    <td class="px-6 py-4 cursor-pointer hover:bg-cyan-50 transition-colors" onclick="this.querySelector('input')?.showPicker()">
                                        <input type="time" name="groups[${idx}][days][<?php echo $day_eng; ?>][end]" value="15:00" class="w-full bg-transparent border-0 text-xs font-bold text-slate-700 focus:ring-0 transition-all disabled:text-slate-400">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="lg:col-span-12 xl:col-span-5">
                    <div class="flex items-center justify-between mb-6">
                        <h4 class="text-sm font-black text-slate-800 uppercase tracking-widest flex items-center gap-2">
                            <span class="w-2 h-6 bg-emerald-600 rounded-full"></span>
                            Unit Terdampak
                        </h4>
                        <button type="button" onclick="selectAllInGroup(this, true)" class="text-[10px] font-black text-cyan-600 uppercase hover:underline">Pilih Semua</button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 max-h-[460px] overflow-y-auto px-1">
                        <?php foreach ($units as $unit): ?>
                        <label class="relative cursor-pointer group/unit">
                            <input type="checkbox" name="groups[${idx}][units][]" value="<?php echo $unit['id']; ?>" class="hidden peer unit-checkbox">
                            <div class="px-5 py-4 rounded-2xl border-2 border-slate-100 bg-slate-50 peer-checked:bg-emerald-600 peer-checked:border-emerald-600 peer-checked:text-white transition-all flex items-center gap-3 group-hover/unit:border-emerald-200 shadow-sm peer-checked:shadow-lg peer-checked:shadow-emerald-600/20">
                                <div class="w-4 h-4 rounded-full border-2 border-slate-300 peer-checked:border-white relative flex items-center justify-center after:content-[''] after:w-2 after:h-2 after:bg-white after:rounded-full after:scale-0 peer-checked:after:scale-100 after:transition-transform"></div>
                                <span class="text-xs font-bold leading-none"><?php echo htmlspecialchars($unit['name']); ?></span>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(div);
        groupCounter++;
    }

    function toggleDayInputs(checkbox) {
        if (!checkbox) return;
        const row = checkbox.closest('tr');
        if (row) {
            const inputs = row.querySelectorAll('input[type="time"]');
            inputs.forEach(input => input.disabled = checkbox.checked);
        }
    }

    function selectAllInGroup(btn, checked) {
        if (!btn) return;
        const card = btn.closest('.group-card');
        if (card) {
            card.querySelectorAll('.unit-checkbox').forEach(cb => cb.checked = checked);
        }
    }

    // Reuse notification logic
    function showNotification(message, type = 'success') {
        const container = document.getElementById('dynamic-notification-container');
        const config = {
            'success': { bg: 'bg-emerald-600', label: 'BERHASIL' },
            'danger': { bg: 'bg-rose-600', label: 'ERROR' }
        }[type] || { bg: 'bg-slate-800', label: 'NOTIF' };
        
        const bar = document.createElement('div');
        bar.className = `${config.bg} text-white px-6 py-4 rounded-3xl shadow-2xl mb-4 flex items-center gap-4 animate-bounce-in relative z-50 border border-white/10`;
        bar.innerHTML = `<span class=\"text-[10px] font-black bg-white/20 px-2 py-0.5 rounded-lg uppercase\">${config.label}</span><span class=\"text-sm font-bold\">${message}</span>`;
        container.appendChild(bar);
        setTimeout(() => { bar.classList.add('opacity-0', '-translate-y-4'); setTimeout(() => bar.remove(), 500); }, 3000);
    }

    function syncHeaderToggle(el) {
        showNotification(`Mode Ramadan ${el.checked ? 'diaktifkan' : 'dinonaktifkan'}. Klik simpan untuk menerapkan.`, el.checked ? 'success' : 'danger');
    }
</script>

<style>
    @keyframes bounce-in { 0% { transform: translateY(-10px); opacity: 0; } 100% { transform: translateY(0); opacity: 1; } }
    @keyframes shine { 100% { transform: translateX(150%) skewX(-30deg); } }
    .animate-bounce-in { animation: bounce-in 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) forwards; }
    .animate-shine { animation: shine 1.5s ease-in-out infinite; }
    .animate-fade-in { animation: fadeIn 0.3s ease-out; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    .day-off-toggle:checked + div { box-shadow: 0 0 10px rgba(244, 63, 94, 0.3); }
</style>

<?php include '../layouts/footer.php'; ?>
