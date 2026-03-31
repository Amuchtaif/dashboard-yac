<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_tahfidz');

$page_title = "Manajemen Koordinator Tahfidz";

$db = new Database();
$conn = $db->getConnection();

// Fetch current assignments
$sql = "
    SELECT 
        ea.id,
        e.full_name,
        e.email,
        p.name as primary_position,
        ea.unit_id,
        u.name as unit_name,
        ea.created_at
    FROM employee_assignments ea
    JOIN employees e ON ea.employee_id = e.id
    JOIN positions p ON e.position_id = p.id
    LEFT JOIN units u ON ea.unit_id = u.id
    WHERE ea.position_id = 12 AND ea.is_active = 1
    ORDER BY u.name ASC, e.full_name ASC
";
$stmt = $conn->query($sql);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all active employees for selection
$employees_sql = "
    SELECT e.id, e.full_name, p.name as position_name, u.name as unit_name, d.name as division_name
    FROM employees e
    JOIN positions p ON e.position_id = p.id
    LEFT JOIN divisions d ON e.division_id = d.id
    LEFT JOIN units u ON e.unit_id = u.id
    WHERE e.status = 'active'
    ORDER BY d.name, u.name, e.full_name ASC
";
$stmt_emp = $conn->query($employees_sql);
$all_employees = $stmt_emp->fetchAll(PDO::FETCH_ASSOC);

// Fetch specific educational units in order (Playgroup, TKIT, SDIT, MTs, MA, Mahad Aly)
$units = $conn->query("SELECT id, name FROM units WHERE id IN (25, 11, 12, 13, 14, 15) ORDER BY FIELD(id, 25, 11, 12, 13, 14, 15)")->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<!-- Custom Style Dropdown CSS & JS Logic -->
<style>
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
</style>

<div class="w-full pb-10">
    <div class="sm:flex sm:items-center justify-between mb-8">
        <div class="sm:flex-auto">
            <h1 class="text-2xl font-bold text-slate-800">Manajemen Koordinator Tahfidz</h1>
            <p class="mt-2 text-sm text-slate-500 font-medium">Kelola penugasan Koordinator Tahfidz (Jabatan Tambahan) lintas unit.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Assign New Coordinator Form -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200">
                <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50 rounded-t-2xl">
                    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Assign Koordinator Baru</h3>
                </div>
                <form action="../../logic/assignments/store.php" method="POST" class="p-6 space-y-6">
                    <!-- Employee Selector (Custom Styles) -->
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest">Pilih Pegawai</label>
                        <div class="relative" id="dropdown-employee">
                            <input type="hidden" name="employee_id" id="input-employee_id" required>
                            <button type="button" onclick="toggleCustomDropdown('employee')"
                                class="flex h-[46px] w-full items-center justify-between rounded-xl border border-slate-300 bg-slate-50 px-4 py-2 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-4 focus:ring-cyan-500/5 transition-all">
                                <span id="text-employee" class="block truncate text-slate-400 italic">Cari nama pegawai...</span>
                                <svg id="arrow-employee" class="h-4 w-4 text-slate-400 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div id="menu-employee" class="hidden absolute z-50 mt-1 max-h-72 w-full overflow-hidden rounded-xl bg-white py-1 text-base shadow-2xl ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                                <div class="sticky top-0 z-10 bg-white px-2 py-1.5 border-b border-slate-100">
                                    <input type="text" id="search-employee" onkeyup="filterCustomDropdown('employee')" placeholder="Ketik nama pegawai..." class="block w-full rounded-lg border-slate-200 py-2 pl-3 text-sm focus:border-cyan-500 focus:ring-cyan-500 outline-none">
                                </div>
                                <ul id="list-employee" class="max-h-60 overflow-y-auto custom-scrollbar">
                                    <?php foreach ($all_employees as $emp): ?>
                                        <li onclick="selectCustomOption('employee', '<?php echo $emp['id']; ?>', '<?php echo htmlspecialchars($emp['full_name'], ENT_QUOTES); ?>')" 
                                            class="option-item relative cursor-pointer select-none py-3 px-3 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors border-b border-slate-50 last:border-0"
                                            data-search="<?php echo strtolower(htmlspecialchars($emp['full_name'])); ?> <?php echo strtolower(htmlspecialchars($emp['position_name'])); ?>">
                                            <div class="flex items-center gap-3">
                                                <div class="h-9 w-9 rounded-xl bg-cyan-100 flex items-center justify-center text-cyan-600 text-xs font-black">
                                                    <?php echo strtoupper(substr($emp['full_name'], 0, 1)); ?>
                                                </div>
                                                <div class="flex flex-col min-w-0">
                                                    <span class="font-bold text-slate-700 text-[13px] truncate"><?php echo htmlspecialchars($emp['full_name']); ?></span>
                                                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-tight"><?php echo htmlspecialchars($emp['position_name']); ?></span>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Unit Selector (Custom Styles) -->
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest">Unit Penugasan</label>
                        <div class="relative" id="dropdown-unit">
                            <input type="hidden" name="unit_id" id="input-unit_id">
                            <button type="button" onclick="toggleCustomDropdown('unit')"
                                class="flex h-[46px] w-full items-center justify-between rounded-xl border border-slate-300 bg-slate-50 px-4 py-2 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-4 focus:ring-cyan-500/5 transition-all">
                                <span id="text-unit" class="block truncate font-bold text-slate-700">Semua Unit (Umum)</span>
                                <svg id="arrow-unit" class="h-4 w-4 text-slate-400 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div id="menu-unit" class="hidden absolute z-50 mt-1 max-h-60 w-full overflow-hidden rounded-xl bg-white py-1 text-base shadow-2xl ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                                <div class="sticky top-0 z-10 bg-white px-2 py-1.5 border-b border-slate-100">
                                    <input type="text" id="search-unit" onkeyup="filterCustomDropdown('unit')" placeholder="Cari unit..." class="block w-full rounded-lg border-slate-200 py-1.5 pl-3 text-sm focus:border-cyan-500 focus:ring-cyan-500 outline-none">
                                </div>
                                <ul id="list-unit" class="max-h-48 overflow-y-auto custom-scrollbar">
                                    <li onclick="selectCustomOption('unit', '', 'Semua Unit (Umum)')" 
                                        class="option-item relative cursor-pointer select-none py-3 px-4 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors font-bold text-[13px]" 
                                        data-search="semua unit umum">Semua Unit (Umum)</li>
                                    <?php foreach ($units as $unit): ?>
                                        <li onclick="selectCustomOption('unit', '<?php echo $unit['id']; ?>', '<?php echo htmlspecialchars($unit['name'], ENT_QUOTES); ?>')" 
                                            class="option-item relative cursor-pointer select-none py-3 px-4 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors font-bold text-[13px] border-t border-slate-50" 
                                            data-search="<?php echo strtolower(htmlspecialchars($unit['name'])); ?>">
                                            <?php echo htmlspecialchars($unit['name']); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                        <p class="text-[10px] text-slate-400 font-medium italic">*Pegawai akan mendapatkan akses fitur Tahfidz untuk unit terpilih.</p>
                    </div>

                    <button type="submit" class="w-full px-6 py-3.5 bg-cyan-600 hover:bg-cyan-700 text-white text-sm font-black rounded-xl shadow-lg shadow-cyan-600/20 hover:shadow-cyan-600/40 active:scale-[0.98] transition-all">
                        Assign Koordinator
                    </button>
                </form>
            </div>
        </div>

        <!-- Current Assignments List -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Daftar Koordinator Tahfidz Aktif</h3>
                    <span class="inline-flex items-center rounded-lg bg-cyan-50 px-2.5 py-1 text-[10px] font-black uppercase text-cyan-600 border border-cyan-100">
                        <?php echo count($assignments); ?> Koordinator
                    </span>
                </div>
                
                <div class="overflow-x-auto shadow ring-1 ring-black ring-opacity-5 md:rounded-xl bg-white">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-6 py-4 text-left text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] min-w-[200px]">Pegawai</th>
                                <th scope="col" class="px-6 py-4 text-left text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] min-w-[150px]">Jabatan Utama</th>
                                <th scope="col" class="px-6 py-4 text-left text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] min-w-[150px]">Unit Koordinator</th>
                                <th scope="col" class="relative px-6 py-4 text-right text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <?php if (empty($assignments)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-20 text-center">
                                        <p class="text-sm font-medium text-slate-400">Belum ada penugasan Koordinator Tahfidz aktif.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($assignments as $asn): ?>
                                <tr class="hover:bg-slate-50/50 transition-colors group">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="h-10 w-10 flex-shrink-0">
                                                <img class="h-10 w-10 rounded-xl border border-slate-200 object-cover shadow-sm" src="https://ui-avatars.com/api/?name=<?php echo urlencode($asn['full_name']); ?>&background=random" alt="">
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-[13px] font-bold text-slate-800"><?php echo htmlspecialchars($asn['full_name']); ?></div>
                                                <div class="text-[10px] text-slate-400 font-medium"><?php echo htmlspecialchars($asn['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center rounded-lg bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-600 border border-indigo-100">
                                            <?php echo htmlspecialchars($asn['primary_position']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-[11px] text-cyan-600 font-bold bg-cyan-50 px-2 py-0.5 rounded-lg w-fit"><?php echo htmlspecialchars($asn['unit_name'] ?: 'Semua Unit'); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <button onclick="confirmDelete(<?php echo $asn['id']; ?>)" class="p-2 text-slate-300 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition-all" title="Hapus Koordinator">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // --- Custom Searchable Dropdown Logic ---
    function toggleCustomDropdown(id) {
        const menu = document.getElementById('menu-' + id);
        const arrow = document.getElementById('arrow-' + id);
        
        // Close other custom menus
        document.querySelectorAll('[id^="menu-"]').forEach(m => {
            if (m !== menu) m.classList.add('hidden');
        });
        document.querySelectorAll('[id^="arrow-"]').forEach(a => {
            if (a !== arrow) a.classList.remove('rotate-180');
        });

        menu.classList.toggle('hidden');
        arrow.classList.toggle('rotate-180');
        
        if (!menu.classList.contains('hidden')) {
            document.getElementById('search-' + id).value = '';
            filterCustomDropdown(id);
            document.getElementById('search-' + id).focus();
            
            // Add animation
            menu.style.opacity = '0';
            menu.style.transform = 'translateY(-10px)';
            requestAnimationFrame(() => {
                menu.style.transition = 'all 0.2s ease-out';
                menu.style.opacity = '1';
                menu.style.transform = 'translateY(0)';
            });
        }
    }

    function selectCustomOption(id, value, text) {
        document.getElementById('input-' + id).value = value;
        const textEl = document.getElementById('text-' + id);
        textEl.innerText = text;
        textEl.classList.remove('text-slate-400', 'italic');
        textEl.classList.add('font-bold', 'text-slate-800');
        
        document.getElementById('menu-' + id).classList.add('hidden');
        document.getElementById('arrow-' + id).classList.remove('rotate-180');

        // Highlighting logic
        const list = document.getElementById('list-' + id);
        const items = list.getElementsByTagName('li');
        for (let i = 0; i < items.length; i++) {
            items[i].classList.remove('bg-cyan-50', 'text-cyan-700', 'ring-1', 'ring-inset', 'ring-cyan-100');
            if (items[i].getAttribute('onclick').includes("'" + value + "'")) {
                items[i].classList.add('bg-cyan-50', 'text-cyan-700', 'ring-1', 'ring-inset', 'ring-cyan-100');
            }
        }
    }

    function filterCustomDropdown(id) {
        const input = document.getElementById('search-' + id);
        const filter = input.value.toLowerCase();
        const list = document.getElementById('list-' + id);
        const li = list.getElementsByClassName('option-item');

        for (let i = 0; i < li.length; i++) {
            const searchData = li[i].getAttribute('data-search');
            if (searchData.indexOf(filter) > -1) {
                li[i].style.display = "";
            } else {
                li[i].style.display = "none";
            }
        }
    }

    // Close dropdowns on outside click
    window.addEventListener('click', function (e) {
        document.querySelectorAll('[id^="dropdown-"]').forEach(dropdown => {
            if (!dropdown.contains(e.target)) {
                const id = dropdown.id.replace('dropdown-', '');
                const menu = document.getElementById('menu-' + id);
                if (menu && !menu.classList.contains('hidden')) {
                    menu.classList.add('hidden');
                    const arrow = document.getElementById('arrow-' + id);
                    if (arrow) arrow.classList.remove('rotate-180');
                }
            }
        });
    });

    function confirmDelete(id) {
        Swal.fire({
            title: 'Hapus Penugasan?',
            text: "Jabatan tambahan Koordinator Tahfidz akan dicabut dari pegawai ini.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0891b2',
            cancelButtonColor: '#ef4444',
            confirmButtonText: 'Ya, Cabut!',
            cancelButtonText: 'Batal',
            border: 'none',
            borderRadius: '20px'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '../../logic/assignments/delete.php?id=' + id;
            }
        });
    }
</script>

<?php include '../layouts/footer.php'; ?>
