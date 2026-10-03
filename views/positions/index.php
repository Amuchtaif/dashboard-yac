<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_employees');

$page_title = "Data Jabatan";

$db = new Database();
$conn = $db->getConnection();

// --- Pagination Logic ---
// --- Pagination Logic ---
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
if (!in_array($limit, [10, 20, 50, 100]))
    $limit = 10;

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $limit;

// Total Positions
$total_rows = $conn->query("SELECT COUNT(*) FROM positions")->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Fetch Positions with Employee Count
// Assuming 'position_id' exists in employees table based on earlier check
$query = "
    SELECT 
        p.id, 
        p.name, 
        p.level,
        (SELECT COUNT(*) FROM employees e WHERE e.position_id = p.id) as member_count
    FROM positions p
    ORDER BY p.level ASC, p.name ASC
    LIMIT :limit OFFSET :offset
";
$stmt = $conn->prepare($query);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$positions = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="pb-10">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-xl font-bold text-slate-900">Data Jabatan</h1>
            <p class="mt-2 text-sm text-slate-500">Kelola jabatan karyawan dan tingkat hierarki organisasi.</p>
        </div>
        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none flex justify-end">
            <a href="<?php url('views/positions/form.php'); ?>"
                class="inline-flex items-center justify-center rounded-lg border border-transparent bg-cyan-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 sm:w-auto transition-colors">
                <i class="fa-solid fa-plus -ml-1 mr-2 h-4 w-4"></i>
                Tambah Jabatan
            </a>
        </div>
    </div>

    <!-- Table -->
    <div class="mt-8 flex flex-col">
        <div class="overflow-x-auto shadow ring-1 ring-black ring-opacity-5 md:rounded-xl bg-white">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                        <th scope="col" class="py-3.5 pl-6 pr-3 text-left w-16">No.</th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[200px]">Nama Jabatan</th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[120px]">Level</th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[150px]">Jumlah Pegawai</th>
                        <th scope="col" class="relative py-3.5 pl-3 pr-6 text-right w-32 border-none">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    <?php if (count($positions) > 0): ?>
                        <?php foreach ($positions as $index => $pos): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors group">
                                <td class="whitespace-nowrap py-4 pl-6 pr-3 text-sm text-slate-500 font-medium">
                                    <?php echo $offset + $index + 1; ?>.
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm font-bold text-slate-900">
                                    <?php echo htmlspecialchars($pos['name']); ?>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm">
                                    <span
                                        class="inline-flex items-center rounded-lg bg-slate-50 px-2.5 py-0.5 text-[11px] font-bold text-slate-600 ring-1 ring-inset ring-slate-500/10 uppercase">
                                        Tingkat <?php echo $pos['level']; ?>
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm">
                                    <button type="button"
                                        onclick="openEmployeesModal(<?php echo $pos['id']; ?>, '<?php echo htmlspecialchars(addslashes($pos['name']), ENT_QUOTES); ?>', '<?php echo $pos['level']; ?>', <?php echo (int)$pos['member_count']; ?>)"
                                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-[11px] font-bold transition-all duration-150 cursor-pointer active:scale-95 <?php echo $pos['member_count'] > 0 ? 'bg-blue-50 text-blue-700 hover:bg-blue-100 hover:text-blue-800 ring-1 ring-inset ring-blue-600/20 shadow-xs' : 'bg-slate-50 text-slate-400 hover:bg-slate-100 ring-1 ring-inset ring-slate-400/20'; ?>"
                                        title="Klik untuk melihat daftar pegawai">
                                        <i class="fa-solid fa-users text-[10px]"></i>
                                        <span><?php echo $pos['member_count']; ?> Orang</span>
                                        <i class="fa-solid fa-chevron-right text-[8px] opacity-70 ml-0.5"></i>
                                    </button>
                                </td>
                                <td class="relative whitespace-nowrap py-4 pl-3 pr-6 text-right text-sm font-medium">
                                    <div class="flex items-center justify-end gap-2 transition-opacity">
                                        <a href="<?php url('views/positions/form.php?id=' . $pos['id']); ?>"
                                            class="p-2 text-slate-400 hover:text-cyan-500 hover:bg-cyan-50 rounded-lg transition-all" title="Ubah">
                                            <i class="fa-solid fa-pen-to-square w-4 h-4"></i>
                                        </a>
                                        <button
                                            onclick="openDeleteModal('<?php url('logic/positions/delete.php?id=' . $pos['id']); ?>')"
                                            class="p-2 text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-all" title="Hapus">
                                            <i class="fa-solid fa-trash w-4 h-4"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="py-12 px-6 text-center">
                                <div class="flex flex-col items-center">
                                    <i class="fa-solid fa-folder-open h-10 w-10 text-slate-200 mb-3"></i>
                                    <p class="text-sm text-slate-500 font-medium tracking-tight">Data jabatan tidak ditemukan.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

                    <!-- Pagination -->
                    <div class="flex flex-col sm:flex-row items-center justify-between border-t border-slate-200 bg-white px-4 py-4 md:py-3 sm:px-6 gap-4">
                        <!-- Mobile Pagination Info -->
                        <div class="flex sm:hidden flex-col items-center gap-2">
                            <p class="text-xs text-slate-500">
                                Menampilkan <span class="font-bold text-slate-900"><?php echo ($total_rows > 0) ? $offset + 1 : 0; ?></span> - <span class="font-bold text-slate-900"><?php echo min($offset + $limit, $total_rows); ?></span> dari <span class="font-bold text-slate-900"><?php echo $total_rows; ?></span>
                            </p>
                            <div class="flex gap-2">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50">Sebelumnya</a>
                                <?php endif; ?>
                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50">Selanjutnya</a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                            <div class="flex items-center gap-4">
                                <select onchange="window.location.href='?page=1&limit='+this.value"
                                    class="block rounded-lg border-slate-300 py-1.5 pl-3 pr-8 text-slate-900 ring-1 ring-inset ring-slate-100 focus:ring-2 focus:ring-cyan-600 sm:text-xs">
                                    <?php foreach ([10, 20, 50, 100] as $val): ?>
                                        <option value="<?php echo $val; ?>" <?php echo $limit == $val ? 'selected' : ''; ?>>
                                            <?php echo $val; ?> per hal
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <nav class="isolate inline-flex -space-x-px rounded-xl shadow-sm border border-slate-200 overflow-hidden" aria-label="Pagination">
                                    <!-- Prev -->
                                    <?php if ($page > 1): ?>
                                        <a href="?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>"
                                            class="relative inline-flex items-center px-3 py-2 text-slate-400 hover:bg-slate-50 transition-colors">
                                            <i class="fa-solid fa-chevron-left h-5 w-5"></i>
                                        </a>
                                    <?php endif; ?>

                                    <?php
                                    $range = 1;
                                    for ($i = 1; $i <= $total_pages; $i++): 
                                        if ($i == 1 || $i == $total_pages || ($i >= $page - $range && $i <= $page + $range)): ?>
                                            <a href="?page=<?php echo $i; ?>&limit=<?php echo $limit; ?>"
                                                class="relative inline-flex items-center px-4 py-2 text-sm font-semibold <?php echo ($i == $page) ? 'bg-cyan-600 text-white' : 'text-slate-900 hover:bg-slate-50'; ?> border-x border-slate-100 transition-colors">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php elseif ($i == 2 || $i == $total_pages - 1): ?>
                                            <span class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-slate-400">...</span>
                                        <?php endif;
                                    endfor; ?>

                                    <!-- Next -->
                                    <?php if ($page < $total_pages): ?>
                                        <a href="?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>"
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
        </div>
    </div>
</div>

<!-- Modal Daftar Pegawai -->
<div id="employeesModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modalTitle" role="dialog" aria-modal="true">
    <!-- Backdrop Blur with Frosted Glass Effect -->
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-md transition-all duration-300" 
         style="backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); background-color: rgba(15, 23, 42, 0.6);" 
         onclick="closeEmployeesModal()"></div>

    <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0 relative z-10">
        <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-2xl border border-slate-200/80 flex flex-col max-h-[88vh]">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 bg-slate-50/70">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-xl bg-cyan-100 text-cyan-600 flex items-center justify-center shrink-0 shadow-xs">
                        <i class="fa-solid fa-users text-lg"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-bold text-slate-900 leading-tight" id="modalPositionName">Daftar Pegawai</h3>
                            <span id="modalPositionLevel" class="inline-flex items-center rounded-md bg-slate-200/70 px-2 py-0.5 text-[10px] font-bold text-slate-700 uppercase">
                                Tingkat -
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5" id="modalSubtitle">Daftar orang yang memiliki jabatan ini</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span id="modalEmployeeTotalBadge" class="hidden sm:inline-flex items-center rounded-full bg-cyan-50 px-2.5 py-0.5 text-xs font-semibold text-cyan-700 border border-cyan-200/60">
                        0 Orang
                    </span>
                    <button type="button" onclick="closeEmployeesModal()" class="rounded-lg p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors focus:outline-none">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>
            </div>

            <!-- Search Toolbar -->
            <div class="px-6 py-3 border-b border-slate-100 bg-white">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" id="employeeSearchInput" oninput="filterEmployees(this.value)"
                        placeholder="Cari nama, NIK, unit, atau divisi..."
                        class="w-full pl-9 pr-9 py-2 text-xs rounded-xl border border-slate-200 focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100 focus:outline-none placeholder:text-slate-400 text-slate-700 bg-slate-50/50 transition-all">
                    <button type="button" id="clearSearchBtn" onclick="clearEmployeeSearch()" class="hidden absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                        <i class="fa-solid fa-circle-xmark text-xs"></i>
                    </button>
                </div>
            </div>

            <!-- Modal Content / Employee List -->
            <div class="flex-1 overflow-y-auto px-6 py-4 space-y-2.5 min-h-[220px]" id="modalEmployeeList">
                <!-- Dynamic Content Loaded via JS -->
            </div>

            <!-- Modal Footer -->
            <div class="border-t border-slate-100 px-6 py-3.5 bg-slate-50/50 flex items-center justify-between">
                <span class="text-xs text-slate-500 font-medium" id="modalResultCounter">
                    Menampilkan 0 orang
                </span>
                <button type="button" onclick="closeEmployeesModal()"
                    class="rounded-lg bg-slate-200/80 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-300 transition-colors shadow-2xs">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    let currentEmployeesData = [];
    let currentPositionName = '';

    async function openEmployeesModal(positionId, positionName, positionLevel, memberCount) {
        const modal = document.getElementById('employeesModal');
        const titleEl = document.getElementById('modalPositionName');
        const levelEl = document.getElementById('modalPositionLevel');
        const totalBadge = document.getElementById('modalEmployeeTotalBadge');
        const listContainer = document.getElementById('modalEmployeeList');
        const searchInput = document.getElementById('employeeSearchInput');
        const counterEl = document.getElementById('modalResultCounter');

        currentPositionName = positionName;
        titleEl.textContent = positionName;
        levelEl.textContent = 'Tingkat ' + positionLevel;
        totalBadge.textContent = memberCount + ' Orang';
        searchInput.value = '';
        document.getElementById('clearSearchBtn').classList.add('hidden');
        counterEl.textContent = 'Memuat data...';

        // Show Modal
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');

        // Loading State
        listContainer.innerHTML = `
            <div class="py-12 flex flex-col items-center justify-center text-center">
                <div class="h-9 w-9 border-3 border-cyan-500 border-t-transparent rounded-full animate-spin mb-3"></div>
                <p class="text-sm font-medium text-slate-600">Memuat daftar pegawai...</p>
                <p class="text-xs text-slate-400 mt-1">Mengambil data untuk jabatan ${positionName}</p>
            </div>
        `;

        try {
            const response = await fetch(`<?php echo BASE_URL; ?>/api/positions/get_employees.php?position_id=${positionId}`);
            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message || 'Gagal memuat data');
            }

            currentEmployeesData = result.data || [];
            totalBadge.textContent = currentEmployeesData.length + ' Orang';
            renderEmployeeList(currentEmployeesData);
            searchInput.focus();

        } catch (error) {
            listContainer.innerHTML = `
                <div class="py-12 flex flex-col items-center justify-center text-center">
                    <div class="h-12 w-12 rounded-full bg-rose-50 text-rose-500 flex items-center justify-center mb-3">
                        <i class="fa-solid fa-triangle-exclamation text-xl"></i>
                    </div>
                    <p class="text-sm font-semibold text-slate-800">Gagal Mengambil Data</p>
                    <p class="text-xs text-slate-500 mt-1 max-w-sm">${error.message}</p>
                    <button type="button" onclick="openEmployeesModal(${positionId}, '${positionName}', '${positionLevel}', ${memberCount})"
                        class="mt-4 px-3 py-1.5 text-xs font-semibold text-cyan-600 bg-cyan-50 hover:bg-cyan-100 rounded-lg transition-colors">
                        <i class="fa-solid fa-arrow-rotate-right mr-1"></i> Coba Lagi
                    </button>
                </div>
            `;
            counterEl.textContent = 'Error';
        }
    }

    function renderEmployeeList(employees) {
        const listContainer = document.getElementById('modalEmployeeList');
        const counterEl = document.getElementById('modalResultCounter');

        counterEl.textContent = `Menampilkan ${employees.length} orang`;

        if (employees.length === 0) {
            const isSearching = document.getElementById('employeeSearchInput').value.trim() !== '';
            listContainer.innerHTML = `
                <div class="py-12 flex flex-col items-center justify-center text-center">
                    <div class="h-12 w-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mb-3">
                        <i class="fa-solid ${isSearching ? 'fa-magnifying-glass' : 'fa-user-slash'} text-xl"></i>
                    </div>
                    <p class="text-sm font-semibold text-slate-700">
                        ${isSearching ? 'Tidak Ada Pegawai Ditemukan' : 'Belum Ada Pegawai'}
                    </p>
                    <p class="text-xs text-slate-400 mt-1 max-w-xs">
                        ${isSearching ? 'Coba gunakan kata kunci pencarian yang lain.' : `Belum ada pegawai yang ditugaskan pada jabatan ${currentPositionName}.`}
                    </p>
                </div>
            `;
            return;
        }

        let html = '';
        employees.forEach((emp, index) => {
            const isActive = emp.status === 'active';
            const statusBadge = isActive 
                ? `<span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 ring-1 ring-inset ring-emerald-600/20 uppercase tracking-wider">
                     <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Aktif
                   </span>`
                : `<span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500 uppercase tracking-wider">
                     Nonaktif
                   </span>`;

            const unitInfo = [emp.unit_name, emp.department_name, emp.division_name].filter(Boolean).join(' • ');
            const nikBadge = emp.nik ? `<span class="font-mono text-[11px] text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded">NIK: ${escapeHtml(emp.nik)}</span>` : '';

            // Clean phone for whatsapp link
            let cleanPhone = (emp.phone_number || '').replace(/[^0-9]/g, '');
            if (cleanPhone.startsWith('0')) {
                cleanPhone = '62' + cleanPhone.substring(1);
            }
            const waButton = cleanPhone ? `
                <a href="https://wa.me/${cleanPhone}" target="_blank" rel="noopener noreferrer" 
                    class="h-8 w-8 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-100 flex items-center justify-center transition-colors shadow-2xs" 
                    title="Kirim Pesan WhatsApp (${escapeHtml(emp.phone_number)})">
                    <i class="fa-brands fa-whatsapp text-sm"></i>
                </a>
            ` : '';

            const emailButton = emp.email ? `
                <a href="mailto:${escapeHtml(emp.email)}" 
                    class="h-8 w-8 rounded-lg bg-slate-50 text-slate-600 hover:bg-slate-100 flex items-center justify-center transition-colors shadow-2xs" 
                    title="Kirim Email (${escapeHtml(emp.email)})">
                    <i class="fa-regular fa-envelope text-xs"></i>
                </a>
            ` : '';

            html += `
                <div class="flex items-center justify-between p-3 rounded-xl border border-slate-100 bg-white hover:bg-slate-50/80 hover:border-slate-200 transition-all duration-150 group">
                    <div class="flex items-center gap-3 min-w-0">
                        <img src="${emp.avatar_url}" 
                            alt="${escapeHtml(emp.full_name)}" 
                            onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(emp.full_name)}&background=0284c7&color=fff&bold=true';"
                            class="h-10 w-10 rounded-full object-cover ring-2 ring-slate-100 shrink-0 shadow-2xs">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h4 class="text-xs font-bold text-slate-900 group-hover:text-cyan-700 transition-colors truncate">
                                    ${escapeHtml(emp.full_name)}
                                </h4>
                                ${statusBadge}
                            </div>
                            <div class="flex items-center gap-2 text-[11px] text-slate-500 mt-0.5 flex-wrap">
                                ${nikBadge}
                                ${unitInfo ? `<span class="truncate text-slate-500 font-medium">${escapeHtml(unitInfo)}</span>` : '<span class="text-slate-400 italic">Tanpa Unit</span>'}
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0 ml-3">
                        ${waButton}
                        ${emailButton}
                        <a href="<?php url('views/employees/index.php'); ?>?search=${encodeURIComponent(emp.full_name)}" 
                            target="_blank"
                            class="h-8 w-8 rounded-lg bg-cyan-50 text-cyan-600 hover:bg-cyan-100 flex items-center justify-center transition-colors shadow-2xs"
                            title="Buka Data Pegawai">
                            <i class="fa-solid fa-arrow-up-right-from-square text-[11px]"></i>
                        </a>
                    </div>
                </div>
            `;
        });

        listContainer.innerHTML = html;
    }

    function filterEmployees(query) {
        const clearBtn = document.getElementById('clearSearchBtn');
        const q = query.trim().toLowerCase();

        if (q !== '') {
            clearBtn.classList.remove('hidden');
        } else {
            clearBtn.classList.add('hidden');
        }

        if (!currentEmployeesData || currentEmployeesData.length === 0) return;

        const filtered = currentEmployeesData.filter(emp => {
            const name = (emp.full_name || '').toLowerCase();
            const nik = (emp.nik || '').toLowerCase();
            const unit = (emp.unit_name || '').toLowerCase();
            const dept = (emp.department_name || '').toLowerCase();
            const div = (emp.division_name || '').toLowerCase();
            const email = (emp.email || '').toLowerCase();
            const phone = (emp.phone_number || '').toLowerCase();

            return name.includes(q) || nik.includes(q) || unit.includes(q) || dept.includes(q) || div.includes(q) || email.includes(q) || phone.includes(q);
        });

        renderEmployeeList(filtered);
    }

    function clearEmployeeSearch() {
        const input = document.getElementById('employeeSearchInput');
        input.value = '';
        document.getElementById('clearSearchBtn').classList.add('hidden');
        renderEmployeeList(currentEmployeesData);
        input.focus();
    }

    function closeEmployeesModal() {
        const modal = document.getElementById('employeesModal');
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    function escapeHtml(string) {
        if (!string) return '';
        const entityMap = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        };
        return String(string).replace(/[&<>"']/g, function (s) {
            return entityMap[s];
        });
    }

    // Close on Escape key press
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const modal = document.getElementById('employeesModal');
            if (modal && !modal.classList.contains('hidden')) {
                closeEmployeesModal();
            }
        }
    });
</script>

<?php include '../layouts/footer.php'; ?>