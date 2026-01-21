<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$db = new Database();
$conn = $db->getConnection();

// --- 1. Fetch Context Data ---

// Academic Years
$years = $conn->query("SELECT * FROM academic_years ORDER BY name DESC")->fetchAll();
$activeYear = null;
foreach ($years as $y) {
    if ($y['is_active']) {
        $activeYear = $y;
        break;
    }
}
$selectedYearId = isset($_GET['year_id']) ? $_GET['year_id'] : ($activeYear ? $activeYear['id'] : ($years[0]['id'] ?? 0));

// Target Class (if selected)
$targetClassId = isset($_GET['class_id']) ? $_GET['class_id'] : null;
$targetClass = null;
$targetClassStats = ['current' => 0];

if ($targetClassId) {
    // Fetch Class Info + Teacher Name + Unit Name
    // Note: Assuming 'employees' table for teachers and 'education_units' for units.
    $stmt = $conn->prepare("
        SELECT gl.*, e.full_name as teacher_name, u.name as unit_name 
        FROM grade_levels gl
        LEFT JOIN employees e ON gl.teacher_id = e.id
        LEFT JOIN education_units u ON gl.education_unit_id = u.id
        WHERE gl.id = ?
    ");
    $stmt->execute([$targetClassId]);
    $targetClass = $stmt->fetch();

    if ($targetClass) {
        // Count current students in this class for the selected year
        $stmtCount = $conn->prepare("SELECT COUNT(*) FROM student_class_history WHERE class_id = ? AND academic_year_id = ? AND status = 'ACTIVE'");
        $stmtCount->execute([$targetClassId, $selectedYearId]);
        $targetClassStats['current'] = $stmtCount->fetchColumn();
    }
}

// All Classes (for Dropdown)
$classes = $conn->query("SELECT * FROM grade_levels ORDER BY name ASC")->fetchAll();

// Fetch Source Students (All, filtering happens in UI mostly, but we query all active)
// We fetch students AND their current history for this year
$sql = "SELECT 
            s.id,
            s.nama_siswa,
            s.nomor_induk,
            s.status,
            s.foto,
            gl.name AS current_class_name,
            gl.id AS current_class_id
        FROM students s
        LEFT JOIN student_class_history h ON s.id = h.student_id AND h.academic_year_id = :year_id AND h.status = 'ACTIVE'
        LEFT JOIN grade_levels gl ON h.class_id = gl.id
        WHERE s.status = 'Aktiv'
        ORDER BY s.nama_siswa ASC";

$stmt = $conn->prepare($sql);
$stmt->execute([':year_id' => $selectedYearId]);
$allStudents = $stmt->fetchAll();

$page_title = "Class Placement";
include '../layouts/header.php';
?>

<div class="px-4 sm:px-6 lg:px-8 pb-10">
    <!-- Breadcrumb -->
    <nav class="flex mb-4" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3 text-xs text-slate-500">
            <li class="inline-flex items-center">
                <a href="<?php url('views/dashboard/index.php'); ?>"
                    class="hover:text-slate-800 flex items-center gap-1">Dashboard</a>
            </li>
            <li>/</li>
            <li>Manajemen Siswa</li>
            <li>/</li>
            <li aria-current="page" class="text-slate-900 font-medium">Penempatan Kelas</li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Penempatan Siswa ke Kelas</h1>
            <p class="text-slate-500 text-sm mt-1">Kelola distribusi siswa ke dalam ruang kelas yang tersedia secara
                kolektif.</p>
        </div>
        <div class="flex gap-2">
            <a href="<?php url('views/dashboard/index.php'); ?>"
                class="px-4 py-2 bg-white border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50">Batal</a>
            <button onclick="submitPlacement()"
                class="px-4 py-2 bg-cyan-600 rounded-lg text-sm font-medium text-white hover:bg-cyan-700 shadow-sm flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path
                        d="M7.707 10.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V6h5a2 2 0 012 2v7a2 2 0 01-2 2H4a2 2 0 01-2-2V8a2 2 0 012-2h5v5.586l-1.293-1.293zM9 4a1 1 0 012 0v2H9V4z" />
                </svg>
                Simpan Penempatan Siswa
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- LEFT COLUMN: Target Class Info & Filters (Span 3) -->
        <div class="lg:col-span-3 space-y-6">

            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <div class="flex items-center gap-2 mb-4 text-slate-800 font-bold text-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    Filter Pencarian
                </div>

                <!-- Year Filter -->
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Tahun
                        Ajaran</label>
                    <select
                        onchange="window.location.href='?class_id=<?php echo $targetClassId; ?>&year_id='+this.value"
                        class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-cyan-500 outline-none">
                        <?php foreach ($years as $y): ?>
                            <option value="<?php echo $y['id']; ?>" <?php echo $selectedYearId == $y['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($y['name']); ?>     <?php echo $y['is_active'] ? '(Active)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Name Search -->
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Cari Nama
                        Siswa</label>
                    <div class="relative">
                        <input type="text" id="search-input" placeholder="Ketik nama atau NISN..."
                            class="w-full pl-9 pr-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-cyan-500 outline-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 absolute left-3 top-2.5"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>

                <!-- Status Filter Buttons -->
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Status
                        Siswa</label>
                    <div class="flex flex-wrap gap-2 text-xs">
                        <button onclick="filterStatus('all')"
                            class="filter-btn active px-3 py-1.5 rounded-full bg-cyan-600 text-white font-medium transition-colors">Semua</button>
                        <button onclick="filterStatus('noclass')"
                            class="filter-btn px-3 py-1.5 rounded-full bg-slate-100 text-slate-600 hover:bg-slate-200 font-medium transition-colors">Belum
                            Ada Kelas</button>
                    </div>
                </div>
            </div>

            <!-- Class Info Card -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <div class="flex items-center gap-2 mb-4 text-cyan-700 font-semibold text-sm uppercase tracking-wide">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Informasi Kelas Tujuan
                </div>

                <!-- Target Class Selector -->
                <div class="mb-4">
                    <label class="block text-xs font-medium text-slate-500 mb-1">Pilih Kelas Tujuan</label>
                    <select
                        onchange="window.location.href='?year_id=<?php echo $selectedYearId; ?>&class_id='+this.value"
                        class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-cyan-500 outline-none">
                        <option value="" disabled <?php echo !$targetClassId ? 'selected' : ''; ?>>-- Pilih Kelas --
                        </option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $targetClassId == $c['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($targetClass): ?>
                    <div class="space-y-4 text-sm">
                        <div class="flex justify-between border-b border-slate-50 pb-2">
                            <span class="text-slate-500">Nama Kelas</span>
                            <span
                                class="font-semibold text-slate-900 text-right"><?php echo htmlspecialchars($targetClass['name']); ?></span>
                        </div>
                        <div class="flex justify-between border-b border-slate-50 pb-2">
                            <span class="text-slate-500">Unit Pendidikan</span>
                            <span
                                class="font-semibold text-slate-900 text-right"><?php echo htmlspecialchars($targetClass['unit_name'] ?? '-'); ?></span>
                        </div>
                        <div class="flex justify-between border-b border-slate-50 pb-2">
                            <span class="text-slate-500">Wali Kelas</span>
                            <span
                                class="font-semibold text-slate-900 text-right w-1/2 truncate"><?php echo htmlspecialchars($targetClass['teacher_name'] ?? '-'); ?></span>
                        </div>
                    </div>

                    <!-- Capacity Alert -->
                    <?php
                    $isFull = $targetClassStats['current'] >= $targetClass['capacity'];
                    $alertColor = $isFull ? 'bg-red-50 text-red-700 border-red-100' : 'bg-yellow-50 text-yellow-700 border-yellow-100';
                    ?>
                    <div class="mt-4 p-3 rounded-lg border <?php echo $alertColor; ?> flex gap-3 text-xs leading-relaxed">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                clip-rule="evenodd" />
                        </svg>
                        <div>
                            Kapasitas maksimal kelas adalah <span
                                class="font-bold"><?php echo $targetClass['capacity']; ?></span> siswa. Saat ini terisi
                            <span class="font-bold" id="current-fill"><?php echo $targetClassStats['current']; ?></span>
                            siswa.
                        </div>
                    </div>
                <?php else: ?>
                    <div class="mt-4 p-4 bg-slate-50 rounded-lg text-center text-slate-500 text-xs">
                        Silakan pilih kelas tujuan terlebih dahulu untuk melihat detail.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- CENTER COLUMN: Source List (Span 5) -->
        <div class="lg:col-span-5">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 h-[calc(100vh-140px)] flex flex-col">
                <!-- Header -->
                <div
                    class="p-4 border-b border-slate-100 flex items-center justify-between bg-white rounded-t-xl sticky top-0 z-10">
                    <div class="flex items-center gap-2">
                        <h3 class="font-bold text-slate-800">Daftar Siswa Tersedia</h3>
                        <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full text-xs font-bold"
                            id="source-count">0</span>
                    </div>
                    <button class="text-sm text-cyan-600 hover:text-cyan-700 font-medium flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Pilih Semua
                    </button>
                </div>

                <!-- List Info Header -->
                <div class="px-4 py-2 bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wide flex">
                    <div class="w-8"></div> <!-- checkbox placeholder -->
                    <div class="flex-1">Nama & NISN</div>
                    <div class="w-24 text-right pr-12">Status</div>
                </div>

                <!-- Scrollable List -->
                <div class="flex-1 overflow-y-auto p-2 space-y-1 custom-scrollbar" id="source-list">
                    <!-- JS will populate this -->
                </div>

                <div class="p-3 border-t border-slate-100 text-center">
                    <button class="text-sm text-cyan-600 hover:text-cyan-800 font-medium">Lihat Lebih Banyak</button>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: Selected/Target List (Span 4) -->
        <div class="lg:col-span-4">
            <div
                class="bg-white rounded-xl shadow-sm border border-slate-200 h-[calc(100vh-140px)] flex flex-col dashed-border">
                <!-- Header -->
                <div
                    class="p-4 border-b border-slate-100 flex items-center justify-between bg-white rounded-t-xl sticky top-0 z-10">
                    <div class="flex items-center gap-2">
                        <h3 class="font-bold text-slate-800">Siswa Terpilih</h3>
                        <span class="bg-cyan-100 text-cyan-800 px-2 py-0.5 rounded-full text-xs font-bold"
                            id="selected-counter">0</span>
                    </div>
                    <button onclick="clearSelection()" class="text-sm text-red-500 hover:text-red-700 font-medium">Hapus
                        Semua</button>
                </div>

                <!-- Scrollable Selected List -->
                <div class="flex-1 overflow-y-auto p-4 space-y-3 custom-scrollbar bg-slate-50/50" id="selected-list">
                    <!-- Empty State -->
                    <div id="empty-state" class="h-full flex flex-col items-center justify-center text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mb-3 opacity-20" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <p class="text-sm text-center px-6">Klik ikon tambah (+) atau seret siswa ke sini untuk
                            menambahkan.</p>
                    </div>
                </div>

                <!-- Footer Summary Action -->
                <div class="p-4 border-t border-slate-100 bg-white rounded-b-xl">
                    <div class="flex justify-between items-center mb-4 text-sm">
                        <span class="font-medium text-slate-600">Siswa Baru:</span>
                        <span class="font-bold text-cyan-600" id="new-count-summary">+0 Orang</span>
                    </div>
                    <button onclick="submitPlacement()" id="confirm-btn" disabled
                        class="w-full py-3 bg-cyan-600 hover:bg-cyan-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-xl font-bold text-sm shadow-md transition-all">
                        Konfirmasi Penempatan
                    </button>
                    <!-- Actual Form -->
                    <form id="placement-form" action="../../logic/placements/store.php" method="POST" class="hidden">
                        <input type="hidden" name="academic_year_id" value="<?php echo $selectedYearId; ?>">
                        <input type="hidden" name="class_id" value="<?php echo $targetClassId; ?>">
                        <!-- Inputs appended here -->
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Raw Data for JS -->
<script>
    const allStudents = <?php echo json_encode($allStudents); ?>;
    const targetClassId = "<?php echo $targetClassId; ?>";
    const targetCapacity = <?php echo $targetClass ? $targetClass['capacity'] : 0; ?>;
    const currentFill = <?php echo $targetClassStats['current']; ?>;
</script>

<script>
    // State
    let selectedIds = new Set();
    let searchTerm = '';
    let statusFilter = 'all';

    // DOM Elements
    const sourceListEl = document.getElementById('source-list');
    const selectedListEl = document.getElementById('selected-list');
    const sourceCountEl = document.getElementById('source-count');
    const selectedCounterEl = document.getElementById('selected-counter');
    const newCountSummaryEl = document.getElementById('new-count-summary');
    const confirmBtn = document.getElementById('confirm-btn');
    const emptyState = document.getElementById('empty-state');
    const searchInput = document.getElementById('search-input');

    // Init
    renderSourceList();

    // Event Listeners
    searchInput.addEventListener('input', (e) => {
        searchTerm = e.target.value.toLowerCase();
        renderSourceList();
    });

    function filterStatus(status) {
        statusFilter = status;
        // Update Buttons Styling
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.remove('bg-cyan-600', 'text-white', 'active');
            btn.classList.add('bg-slate-100', 'text-slate-600');
            if (btn.textContent.includes(status === 'all' ? 'Semua' : (status === 'noclass' ? 'Belum' : ''))) {
                btn.classList.remove('bg-slate-100', 'text-slate-600');
                btn.classList.add('bg-cyan-600', 'text-white', 'active');
            }
        });
        renderSourceList();
    }

    function renderSourceList() {
        sourceListEl.innerHTML = '';

        let visibleCount = 0;

        allStudents.forEach(student => {
            // 1. Check Selection
            if (selectedIds.has(String(student.id))) return; // Don't show if selected

            // 2. Check Search
            const nameMatch = student.nama_siswa.toLowerCase().includes(searchTerm);
            const nisMatch = student.nomor_induk ? student.nomor_induk.includes(searchTerm) : false;
            if (!nameMatch && !nisMatch) return;

            // 3. Check Status Filter
            if (statusFilter === 'noclass' && student.current_class_id) return;

            // Render Item
            visibleCount++;
            const item = document.createElement('div');
            item.className = 'group flex items-center p-3 rounded-lg hover:bg-slate-50 border border-transparent hover:border-slate-100 transition-all';

            // Avatar Initials or Image
            const avatarHtml = `<div class="h-10 w-10 rounded-full bg-cyan-100 text-cyan-600 flex items-center justify-center font-bold text-sm mr-3">
                ${student.nama_siswa.substring(0, 2).toUpperCase()}
            </div>`;

            // Status Badge
            let statusBadge = '';
            if (student.current_class_id) {
                statusBadge = `<span class="bg-cyan-50 text-cyan-700 px-2 py-0.5 rounded text-[10px] font-bold">Kelas ${student.current_class_name}</span>`;
            } else {
                statusBadge = `<span class="bg-slate-100 text-slate-500 px-2 py-0.5 rounded text-[10px] font-bold">Non-Kelas</span>`;
            }

            item.innerHTML = `
                <div class="mr-3">
                    <input type="checkbox" class="rounded border-slate-300 text-cyan-600 focus:ring-cyan-500 w-4 h-4 cursor-pointer opacity-30 group-hover:opacity-100">
                </div>
                ${avatarHtml}
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-slate-800 truncate">${student.nama_siswa}</p>
                    <p class="text-xs text-slate-400 font-mono">${student.nomor_induk || '-'}</p>
                </div>
                <div class="flex items-center gap-3">
                    ${statusBadge}
                    <button onclick="addToSelection('${student.id}')" class="h-8 w-8 rounded-full bg-cyan-50 text-cyan-600 flex items-center justify-center hover:bg-cyan-600 hover:text-white transition-colors">
                         <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
                    </button>
                </div>
            `;
            sourceListEl.appendChild(item);
        });

        sourceCountEl.textContent = visibleCount;
    }

    function renderSelectedList() {
        selectedListEl.innerHTML = '';

        if (selectedIds.size === 0) {
            selectedListEl.appendChild(emptyState);
            confirmBtn.disabled = true;
            newCountSummaryEl.textContent = '+0 Orang';
            return;
        }

        selectedIds.forEach(id => {
            const student = allStudents.find(s => String(s.id) === id);
            if (!student) return;

            const item = document.createElement('div');
            item.className = 'flex items-center p-3 bg-white rounded-lg shadow-sm border border-slate-100 relative';

            // Avatar (Blue Square like image)
            const avatarHtml = `<div class="h-10 w-10 rounded bg-cyan-700 text-white flex items-center justify-center font-bold text-xs mr-3">
                ${student.nama_siswa.substring(0, 2).toUpperCase()}
            </div>`;

            item.innerHTML = `
                ${avatarHtml}
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-slate-800 truncate">${student.nama_siswa}</p>
                    <p class="text-xs text-slate-400 font-mono">NISN: ${student.nomor_induk || '-'}</p>
                </div>
                 <button onclick="removeFromSelection('${student.id}')" class="text-slate-300 hover:text-red-500 transition-colors p-1">
                     <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                 </button>
            `;
            selectedListEl.appendChild(item);
        });

        const count = selectedIds.size;
        selectedCounterEl.textContent = count;
        newCountSummaryEl.textContent = `+${count} Orang`;
        confirmBtn.disabled = false;

        // Capacity Warning Logic (Optional visual cue)
        if (targetClassId && (currentFill + count > targetCapacity)) {
            newCountSummaryEl.classList.add('text-red-600');
            newCountSummaryEl.textContent += " (Over Capacity!)";
        } else {
            newCountSummaryEl.classList.remove('text-red-600');
        }
    }

    function addToSelection(id) {
        if (!targetClassId) {
            alert("Harap pilih Kelas Tujuan terlebih dahulu di sebelah kiri.");
            return;
        }
        selectedIds.add(id);
        renderSourceList();
        renderSelectedList();
    }

    function removeFromSelection(id) {
        selectedIds.delete(id);
        renderSourceList();
        renderSelectedList();
    }

    function clearSelection() {
        selectedIds.clear();
        renderSourceList();
        renderSelectedList();
    }

    function submitPlacement() {
        if (selectedIds.size === 0) return;
        if (!targetClassId) return alert("Pilih kelas tujuan.");

        const form = document.getElementById('placement-form');

        // Append IDs
        selectedIds.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'student_ids[]';
            input.value = id;
            form.appendChild(input);
        });

        form.submit();
    }

</script>

<style>
    .custom-scrollbar::-webkit-scrollbar {
        width: 4px;
    }

    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb {
        background-color: #e2e8f0;
        border-radius: 20px;
    }
</style>

<?php include '../layouts/footer.php'; ?>