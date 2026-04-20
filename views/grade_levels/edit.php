<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

if (!isset($_GET['id'])) {
    redirect('views/grade_levels/index.php?error=Permintaan tidak valid');
}

$id = $_GET['id'];
$db = new Database();
$conn = $db->getConnection();

// Fetch Grade Level Data
$stmt = $conn->prepare("SELECT * FROM grade_levels WHERE id = :id");
$stmt->bindParam(':id', $id);
$stmt->execute();
$level = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$level) {
    redirect('views/grade_levels/index.php?error=Kelas tidak ditemukan');
}

// Fetch Education Units
$units = $conn->query("SELECT id, name FROM education_units ORDER BY FIELD(name, 'Playgroup', 'TKIT', 'SDIT', 'MTs', 'Idad Lughoh', 'MA', 'Mahad Aly') ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Employees (Teachers)
$teachers = $conn->query("SELECT id, full_name as name FROM employees WHERE status = 'active' ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Edit Class";

include '../layouts/header.php';
?>

<div class="w-full pb-10">
    <!-- Header -->
    <div class="mb-8">
        <nav class="flex mb-4" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3 text-xs text-slate-500">
                <li class="inline-flex items-center">
                    <a href="<?php url('views/dashboard/index.php'); ?>" class="hover:text-slate-800">Dashboard</a>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="w-3 h-3 text-gray-400 mx-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                            fill="none" viewBox="0 0 6 10">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="m1 9 4-4-4-4" />
                        </svg>
                        <a href="<?php url('views/grade_levels/index.php'); ?>" class="hover:text-slate-800">Manajemen
                            Kelas</a>
                    </div>
                </li>
                <li aria-current="page">
                    <div class="flex items-center">
                        <svg class="w-3 h-3 text-gray-400 mx-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                            fill="none" viewBox="0 0 6 10">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="m1 9 4-4-4-4" />
                        </svg>
                        <span class="text-slate-800 font-medium">Edit Kelas</span>
                    </div>
                </li>
            </ol>
        </nav>
        <h2 class="text-2xl font-bold text-slate-900">Edit Data Kelas</h2>
        <p class="text-slate-500 text-sm mt-1">Perbarui informasi kelas.</p>
    </div>

    <form action="<?php url('logic/grade_levels/update.php'); ?>" method="POST" class="space-y-6">
        <input type="hidden" name="id" value="<?php echo $level['id']; ?>">

        <!-- Main Card -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8">

            <div class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Nama Kelas -->
                    <div>
                        <label for="name" class="block text-sm font-semibold text-slate-900 mb-2">Nama Kelas</label>
                        <input type="text" name="name" id="name" required
                            value="<?php echo htmlspecialchars($level['name']); ?>"
                            class="w-full px-4 py-2.5 rounded-lg border border-slate-200 bg-slate-50 text-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all placeholder:text-slate-400"
                            placeholder="Misal: 1-A, 10-IPA-1">
                    </div>

                    <!-- Kapasitas -->
                    <div>
                        <label for="capacity" class="block text-sm font-semibold text-slate-900 mb-2">Kapasitas</label>
                        <input type="number" name="capacity" id="capacity" required min="1"
                            value="<?php echo htmlspecialchars($level['capacity'] ?? 36); ?>"
                            class="w-full px-4 py-2.5 rounded-lg border border-slate-200 bg-slate-50 text-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all placeholder:text-slate-400"
                            placeholder="36">
                    </div>
                </div>

                <!-- Unit Pendidikan (Custom Searchable Select) -->
                <div>
                    <label class="block text-sm font-semibold text-slate-900 mb-2">Unit Pendidikan</label>
                    <div class="relative custom-select" id="unit-select">
                        <input type="hidden" name="education_unit_id" value="<?php echo $level['education_unit_id']; ?>"
                            required>

                        <button type="button"
                            class="select-toggle w-full px-4 py-2.5 rounded-lg border border-slate-200 bg-slate-50 text-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all flex justify-between items-center text-left">
                            <span class="selected-text">
                                <?php
                                $currentUnitName = 'Pilih Unit';
                                foreach ($units as $u) {
                                    if ($u['id'] == $level['education_unit_id']) {
                                        $currentUnitName = $u['name'];
                                        break;
                                    }
                                }
                                echo htmlspecialchars($currentUnitName);
                                ?>
                            </span>
                            <svg class="w-4 h-4 text-slate-500 transition-transform duration-200" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>

                        <div
                            class="select-options hidden absolute z-10 w-full mt-1 bg-white border border-slate-200 rounded-lg shadow-lg overflow-hidden">
                            <div class="p-2 border-b border-slate-100 bg-slate-50 sticky top-0">
                                <input type="text"
                                    class="search-input w-full px-3 py-1.5 text-xs text-slate-700 bg-white border border-slate-200 rounded-md focus:outline-none focus:border-cyan-500 placeholder:text-slate-400"
                                    placeholder="Cari unit...">
                            </div>
                            <ul class="max-h-60 overflow-y-auto py-1">
                                <?php foreach ($units as $u): ?>
                                    <li class="option cursor-pointer px-4 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors <?php echo ($level['education_unit_id'] == $u['id']) ? 'bg-cyan-50 text-cyan-700 font-medium' : ''; ?>"
                                        data-value="<?php echo $u['id']; ?>"
                                        data-label="<?php echo htmlspecialchars($u['name']); ?>">
                                        <?php echo htmlspecialchars($u['name']); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Wali Kelas (Custom Searchable Select) -->
                <div>
                    <label class="block text-sm font-semibold text-slate-900 mb-2">Wali Kelas</label>
                    <div class="relative custom-select" id="teacher-select">
                        <input type="hidden" name="teacher_id" value="<?php echo $level['teacher_id']; ?>">

                        <button type="button"
                            class="select-toggle w-full px-4 py-2.5 rounded-lg border border-slate-200 bg-slate-50 text-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all flex justify-between items-center text-left">
                            <span class="selected-text">
                                <?php
                                $currentTeacherName = 'Pilih Wali Kelas';
                                if ($level['teacher_id']) {
                                    foreach ($teachers as $t) {
                                        if ($t['id'] == $level['teacher_id']) {
                                            $currentTeacherName = $t['name']; // using alias name from earlier query
                                            break;
                                        }
                                    }
                                }
                                echo htmlspecialchars($currentTeacherName);
                                ?>
                            </span>
                            <svg class="w-4 h-4 text-slate-500 transition-transform duration-200" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>

                        <div
                            class="select-options hidden absolute z-10 w-full mt-1 bg-white border border-slate-200 rounded-lg shadow-lg overflow-hidden">
                            <div class="p-2 border-b border-slate-100 bg-slate-50 sticky top-0">
                                <input type="text"
                                    class="search-input w-full px-3 py-1.5 text-xs text-slate-700 bg-white border border-slate-200 rounded-md focus:outline-none focus:border-cyan-500 placeholder:text-slate-400"
                                    placeholder="Cari wali kelas...">
                            </div>
                            <ul class="max-h-60 overflow-y-auto py-1">
                                <li class="option cursor-pointer px-4 py-2 text-sm text-slate-500 hover:bg-slate-50 italic"
                                    data-value="" data-label="Pilih Wali Kelas">
                                    -- Tidak Ada --
                                </li>
                                <?php foreach ($teachers as $t): ?>
                                    <li class="option cursor-pointer px-4 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors <?php echo ($level['teacher_id'] == $t['id']) ? 'bg-cyan-50 text-cyan-700 font-medium' : ''; ?>"
                                        data-value="<?php echo $t['id']; ?>"
                                        data-label="<?php echo htmlspecialchars($t['name']); ?>">
                                        <?php echo htmlspecialchars($t['name']); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Footer Action -->
            <div class="flex items-center justify-end gap-3 mt-8 pt-6 border-t border-slate-100">
                <a href="<?php url('views/grade_levels/index.php'); ?>"
                    class="px-6 py-2.5 rounded-lg border border-slate-200 text-slate-700 text-sm font-medium hover:bg-slate-50 transition-colors">
                    Batal
                </a>
                <button type="submit"
                    class="px-6 py-2.5 rounded-lg bg-cyan-600 text-white text-sm font-semibold hover:bg-cyan-700 transition-colors flex items-center gap-2 shadow-sm shadow-cyan-200">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                        <path fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-11.25a.75.75 0 00-1.5 0v2.5h-2.5a.75.75 0 000 1.5h2.5v2.5a.75.75 0 001.5 0v-2.5h2.5a.75.75 0 000-1.5h-2.5v-2.5z"
                            clip-rule="evenodd" />
                    </svg>
                    Simpan Perubahan
                </button>
            </div>

        </div>
    </form>

    <script>
        document.querySelectorAll('.custom-select').forEach(selectContainer => {
            const toggle = selectContainer.querySelector('.select-toggle');
            const optionsContainer = selectContainer.querySelector('.select-options');
            const searchInput = selectContainer.querySelector('.search-input');
            const hiddenInput = selectContainer.querySelector('input[type="hidden"]');
            const selectedText = selectContainer.querySelector('.selected-text');
            const arrow = toggle.querySelector('svg');
            const options = selectContainer.querySelectorAll('.option');

            // Toggle Dropdown
            toggle.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation(); // Stop event from bubbling to document click listener
                const isOpen = !optionsContainer.classList.contains('hidden');

                // Close all other dropdowns
                document.querySelectorAll('.select-options').forEach(el => {
                    if (el !== optionsContainer) {
                        el.classList.add('hidden');
                        el.parentElement.querySelector('svg').classList.remove('rotate-180');
                    }
                });

                if (isOpen) {
                    closeDropdown();
                } else {
                    openDropdown();
                }
            });

            function openDropdown() {
                optionsContainer.classList.remove('hidden');
                arrow.classList.add('rotate-180');
                searchInput.focus();
                searchInput.value = ''; // Reset search
                filterOptions('');
            }

            function closeDropdown() {
                optionsContainer.classList.add('hidden');
                arrow.classList.remove('rotate-180');
            }

            // Search Functionality
            searchInput.addEventListener('input', (e) => {
                filterOptions(e.target.value.toLowerCase());
            });

            // Prevent closing when clicking inside search input or options container
            optionsContainer.addEventListener('click', (e) => {
                e.stopPropagation();
            });

            function filterOptions(query) {
                let hasResults = false;
                options.forEach(option => {
                    const label = option.getAttribute('data-label').toLowerCase();
                    if (label.includes(query)) {
                        option.classList.remove('hidden');
                        hasResults = true;
                    } else {
                        option.classList.add('hidden');
                    }
                });
            }

            // Option Selection
            options.forEach(option => {
                option.addEventListener('click', () => {
                    const value = option.getAttribute('data-value');
                    const label = option.getAttribute('data-label');

                    hiddenInput.value = value;
                    selectedText.textContent = label;
                    selectedText.classList.remove('text-slate-400');
                    selectedText.classList.add('text-slate-900');

                    // Update styling for selected option
                    options.forEach(opt => {
                        opt.classList.remove('bg-cyan-50', 'text-cyan-700', 'font-medium');
                        if (opt === option && value !== '') {
                            opt.classList.add('bg-cyan-50', 'text-cyan-700', 'font-medium');
                        }
                    });

                    closeDropdown();
                });
            });

            // Close when clicking outside
            document.addEventListener('click', (e) => {
                if (!selectContainer.contains(e.target)) {
                    closeDropdown();
                }
            });
        });
    </script>