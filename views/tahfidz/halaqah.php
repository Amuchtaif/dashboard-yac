<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$page_title = "Data Halaqah";

$db = new Database();
$conn = $db->getConnection();

// --- Fetch Data ---
// 1. Fetch Halaqah Groups with Teachers
$groups_query = "
    SELECT hg.*, e.full_name as teacher_name,
    (SELECT COUNT(*) FROM halaqah_members WHERE group_id = hg.id) as member_count
    FROM halaqah_groups hg
    JOIN employees e ON hg.teacher_id = e.id
    ORDER BY hg.group_name ASC
";
$groups = $conn->query($groups_query)->fetchAll(PDO::FETCH_ASSOC);

// 2. Fetch All Teachers (for group creation)
$teachers_query = "SELECT id, full_name FROM employees ORDER BY full_name ASC";
$teachers = $conn->query($teachers_query)->fetchAll(PDO::FETCH_ASSOC);

// 3. Fetch All Students (for member management)
$students_query = "SELECT id, nama_siswa as full_name FROM students ORDER BY nama_siswa ASC";
$all_students = $conn->query($students_query)->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="space-y-6 pb-12">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Manajemen Data Halaqah</h1>
            <p class="text-slate-500 mt-1">Kelola kelompok halaqah dan penempatan santri.</p>
        </div>
        <div class="mt-4 sm:mt-0">
            <button onclick="openModal('modal-add-group')" 
                class="inline-flex items-center justify-center rounded-lg bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all">
                <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Tambah Kelompok
            </button>
        </div>
    </div>

    <!-- Groups Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (count($groups) > 0): ?>
            <?php foreach ($groups as $group): ?>
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden hover:shadow-md transition-all group">
                    <div class="p-6">
                        <div class="flex justify-between items-start">
                            <div class="flex items-center gap-3">
                                <div class="h-12 w-12 rounded-xl bg-cyan-50 flex items-center justify-center text-cyan-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-bold text-slate-800 text-lg"><?php echo htmlspecialchars($group['group_name']); ?></h3>
                                    <p class="text-xs text-slate-400 font-medium uppercase tracking-wider">Kelompok Halaqah</p>
                                </div>
                            </div>
                            <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button onclick="deleteGroup(<?php echo $group['id']; ?>)" class="p-2 text-slate-400 hover:text-red-600 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="mt-6 space-y-4">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-500">Pengampu</span>
                                <span class="font-semibold text-slate-700"><?php echo htmlspecialchars($group['teacher_name']); ?></span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-500">Jumlah Santri</span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-cyan-50 text-cyan-700">
                                    <?php echo $group['member_count']; ?> Santri
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="bg-slate-50 px-6 py-4 border-t border-slate-100 flex justify-between items-center">
                        <a href="halaqah_members.php?group_id=<?php echo $group['id']; ?>" 
                           class="text-sm font-bold text-cyan-600 hover:text-cyan-700 transition-colors inline-flex items-center">
                            Kelola Santri
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-span-full py-20 bg-white rounded-2xl border border-dashed border-slate-300 flex flex-col items-center justify-center text-slate-400 text-center px-6">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mb-4 text-slate-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <p class="text-lg font-bold text-slate-600">Belum ada kelompok halaqah</p>
                <p class="text-sm mt-1 max-w-xs">Mulai dengan menambahkan kelompok halaqah baru dan tugaskan pengampu.</p>
                <button onclick="openModal('modal-add-group')" class="mt-6 text-cyan-600 font-bold hover:underline">Tambah Kelompok Sekarang</button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Add Group -->
<div id="modal-add-group" class="fixed inset-0 z-50 hidden overflow-y-auto transition-opacity duration-300 opacity-0" aria-hidden="true">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm opacity-100"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-visible shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-slate-200">
            <form action="../../logic/tahfidz/manage_halaqah.php" method="POST">
                <input type="hidden" name="action" value="create_group">
                <div class="bg-white px-8 pt-8 pb-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-bold text-slate-800">Tambah Kelompok Baru</h3>
                        <button type="button" onclick="closeModal('modal-add-group')" class="text-slate-400 hover:text-slate-600">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    
                    <div class="space-y-5">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Nama Kelompok</label>
                            <input type="text" name="group_name" required placeholder="Contoh: Halaqah Abu Bakar"
                                class="block w-full rounded-xl border-slate-200 bg-slate-50 border px-4 py-3 text-sm focus:border-cyan-500 focus:ring-cyan-500 transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Pilih Pengampu (Ustadz)</label>
                            <input type="hidden" name="teacher_id" id="teacher-id-input" required>
                            <div class="relative" id="teacher-dropdown">
                                <!-- Trigger Button -->
                                <button type="button" id="teacher-dropdown-btn"
                                    onclick="toggleTeacherDropdown()"
                                    class="flex items-center justify-between w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-left focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500 focus:outline-none transition-all">
                                    <span id="teacher-dropdown-text" class="text-slate-400 truncate">Pilih Pengampu...</span>
                                    <svg class="h-4 w-4 text-slate-400 flex-shrink-0 ml-2 transition-transform duration-200" id="teacher-dropdown-arrow" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                <!-- Dropdown Panel -->
                                <div id="teacher-dropdown-menu"
                                    class="hidden absolute left-0 right-0 mt-2 bg-white rounded-xl border border-slate-200 shadow-lg z-[60] overflow-hidden transition-all duration-200 origin-top scale-y-0 opacity-0"
                                    style="max-height: 280px;">
                                    <!-- Search Input -->
                                    <div class="p-2.5 border-b border-slate-100 sticky top-0 bg-white z-10">
                                        <div class="relative">
                                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                                <svg class="h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                                                </svg>
                                            </div>
                                            <input type="text" id="teacher-search-input"
                                                placeholder="Ketik nama pengampu..."
                                                autocomplete="off"
                                                class="block w-full rounded-lg border-slate-200 bg-slate-50 border pl-9 pr-3 py-2 text-sm focus:border-cyan-500 focus:ring-cyan-500 transition-all placeholder:text-slate-400">
                                        </div>
                                    </div>
                                    <!-- Options List -->
                                    <ul id="teacher-options-list" class="overflow-y-auto" style="max-height: 210px;">
                                        <?php foreach ($teachers as $t): ?>
                                            <li class="teacher-option cursor-pointer flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors"
                                                data-value="<?php echo $t['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($t['full_name']); ?>"
                                                onclick="selectTeacher(this)">
                                                <span class="flex-shrink-0 h-7 w-7 rounded-full bg-cyan-100 flex items-center justify-center text-cyan-600 text-xs font-bold">
                                                    <?php echo strtoupper(substr($t['full_name'], 0, 1)); ?>
                                                </span>
                                                <span class="truncate"><?php echo htmlspecialchars($t['full_name']); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                        <li id="teacher-no-result" class="hidden px-4 py-6 text-center text-sm text-slate-400">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mx-auto mb-1 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            Pengampu tidak ditemukan
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-slate-50 px-8 py-6 flex flex-row-reverse gap-3">
                    <button type="submit" class="inline-flex justify-center rounded-xl bg-cyan-600 px-6 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-cyan-700 focus:outline-none transition-all">
                        Simpan Kelompok
                    </button>
                    <button type="button" onclick="closeModal('modal-add-group')" class="inline-flex justify-center rounded-xl bg-white border border-slate-200 px-6 py-2.5 text-sm font-bold text-slate-600 shadow-sm hover:bg-slate-50 focus:outline-none transition-all">
                        Batal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Delete Group -->
<div id="modal-delete-group" class="fixed inset-0 z-50 hidden overflow-y-auto transition-opacity duration-300 opacity-0" aria-hidden="true">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm opacity-100" onclick="closeModal('modal-delete-group')"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full border border-slate-200">
            <form id="delete-group-form" action="../../logic/tahfidz/manage_halaqah.php" method="POST">
                <input type="hidden" name="action" value="delete_group">
                <input type="hidden" name="group_id" id="delete_group_id">
                
                <div class="bg-white px-8 pt-8 pb-6">
                    <div class="flex flex-col items-center text-center">
                        <div class="h-14 w-14 rounded-full bg-red-50 flex items-center justify-center text-red-500 mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-slate-800 mb-2">Hapus Kelompok?</h3>
                        <p class="text-slate-500 text-sm">
                            Apakah Anda yakin ingin menghapus kelompok ini?
                            <br>
                            <span class="text-red-500 font-medium text-xs mt-1 block">Semua data anggota di dalamnya juga akan terhapus permanen.</span>
                        </p>
                    </div>
                </div>
                
                <div class="bg-slate-50 px-8 py-6 flex flex-row gap-3 justify-center">
                    <button type="button" onclick="closeModal('modal-delete-group')" 
                        class="w-full inline-flex justify-center rounded-xl bg-white border border-slate-200 px-6 py-2.5 text-sm font-bold text-slate-600 shadow-sm hover:bg-slate-50 focus:outline-none transition-all">
                        Batal
                    </button>
                    <button type="submit" 
                        class="w-full inline-flex justify-center rounded-xl bg-red-600 px-6 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-red-700 focus:outline-none transition-all">
                        Hapus
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openModal(id) {
        const modal = document.getElementById(id);
        modal.classList.remove('hidden');
        // Force reflow
        void modal.offsetWidth; 
        modal.classList.remove('opacity-0');
        modal.classList.add('opacity-100');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        modal.classList.remove('opacity-100');
        modal.classList.add('opacity-0');
        
        // Wait for transition to finish before hiding
        setTimeout(() => {
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }, 300); // Matches duration-300
    }

    function deleteGroup(id) {
        document.getElementById('delete_group_id').value = id;
        openModal('modal-delete-group');
    }

    // --- Custom Searchable Dropdown for Teacher ---
    let teacherDropdownOpen = false;

    function toggleTeacherDropdown() {
        teacherDropdownOpen ? closeTeacherDropdown() : openTeacherDropdown();
    }

    function openTeacherDropdown() {
        const menu = document.getElementById('teacher-dropdown-menu');
        const arrow = document.getElementById('teacher-dropdown-arrow');
        const searchInput = document.getElementById('teacher-search-input');
        
        menu.classList.remove('hidden');
        // Force reflow for animation
        void menu.offsetWidth;
        menu.classList.remove('scale-y-0', 'opacity-0');
        menu.classList.add('scale-y-100', 'opacity-100');
        arrow.classList.add('rotate-180');
        teacherDropdownOpen = true;

        // Focus search input after a tiny delay for smooth animation
        setTimeout(() => searchInput.focus(), 100);
    }

    function closeTeacherDropdown() {
        const menu = document.getElementById('teacher-dropdown-menu');
        const arrow = document.getElementById('teacher-dropdown-arrow');
        
        menu.classList.remove('scale-y-100', 'opacity-100');
        menu.classList.add('scale-y-0', 'opacity-0');
        arrow.classList.remove('rotate-180');
        teacherDropdownOpen = false;

        setTimeout(() => menu.classList.add('hidden'), 200);
    }

    function selectTeacher(el) {
        const value = el.getAttribute('data-value');
        const name = el.getAttribute('data-name');
        
        document.getElementById('teacher-id-input').value = value;
        
        const textEl = document.getElementById('teacher-dropdown-text');
        textEl.textContent = name;
        textEl.classList.remove('text-slate-400');
        textEl.classList.add('text-slate-800', 'font-medium');
        
        // Highlight selected
        document.querySelectorAll('.teacher-option').forEach(opt => {
            opt.classList.remove('bg-cyan-50', 'text-cyan-700', 'font-semibold');
        });
        el.classList.add('bg-cyan-50', 'text-cyan-700', 'font-semibold');
        
        closeTeacherDropdown();
    }

    // Search filter
    document.getElementById('teacher-search-input').addEventListener('input', function() {
        const keyword = this.value.toLowerCase().trim();
        const options = document.querySelectorAll('.teacher-option');
        const noResult = document.getElementById('teacher-no-result');
        let visibleCount = 0;

        options.forEach(opt => {
            const name = opt.getAttribute('data-name').toLowerCase();
            if (name.includes(keyword)) {
                opt.classList.remove('hidden');
                visibleCount++;
            } else {
                opt.classList.add('hidden');
            }
        });

        if (visibleCount === 0) {
            noResult.classList.remove('hidden');
        } else {
            noResult.classList.add('hidden');
        }
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('teacher-dropdown');
        if (dropdown && !dropdown.contains(e.target) && teacherDropdownOpen) {
            closeTeacherDropdown();
        }
    });
</script>

<?php include '../layouts/footer.php'; ?>
