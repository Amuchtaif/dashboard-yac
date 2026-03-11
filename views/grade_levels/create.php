<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$page_title = "Tambah Kelas Baru";

$db = new Database();
$conn = $db->getConnection();

// Fetch Education Units
$units = $conn->query("SELECT id, name FROM education_units ORDER BY FIELD(name, 'Playgroup', 'TKIT', 'SDIT', 'MTs', 'Idad Lughoh', 'MA', 'Mahad Aly') ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Employees (Teachers)
// Assuming all employees can be teachers for now, or you can filter by position/division if needed.
$teachers = $conn->query("SELECT id, full_name as name FROM employees WHERE status = 'active' ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="w-full pb-10">
    <!-- Header -->
    <div class="mb-8">
        <nav class="flex mb-4" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3 text-xs text-slate-500">
                <li class="inline-flex items-center">
                    <a href="<?php url('views/dashboard/index.php'); ?>" class="hover:text-slate-800">Beranda</a>
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
                        <span class="text-slate-800 font-medium">Tambah Kelas Baru</span>
                    </div>
                </li>
            </ol>
        </nav>
        <h2 class="text-2xl font-bold text-slate-900">Tambah Kelas Baru</h2>
        <p class="text-slate-500 text-sm mt-1">Silakan lengkapi formulir di bawah ini untuk menambahkan data kelas ke
            dalam unit pendidikan.</p>
    </div>

    <form action="<?php url('logic/grade_levels/store.php'); ?>" method="POST" class="space-y-6">

        <!-- Main Card -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8">

            <div class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Nama Kelas -->
                    <div>
                        <label for="name" class="block text-sm font-semibold text-slate-900 mb-2">Nama Kelas</label>
                        <input type="text" name="name" id="name" required
                            class="w-full px-4 py-2.5 rounded-lg border border-slate-200 bg-slate-50 text-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all placeholder:text-slate-400">
                        <p class="text-[11px] text-slate-500 mt-1.5">Gunakan format penamaan standar unit (Angka -
                            Huruf/Nama).</p>
                    </div>

                    <!-- Kapasitas -->
                    <div>
                        <label for="capacity" class="block text-sm font-semibold text-slate-900 mb-2">Kapasitas</label>
                        <input type="number" name="capacity" id="capacity" required min="1" value="36"
                            class="w-full px-4 py-2.5 rounded-lg border border-slate-200 bg-slate-50 text-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all placeholder:text-slate-400"
                            placeholder="36">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Unit Pendidikan -->
                    <div>
                        <label for="education_unit_id" class="block text-sm font-semibold text-slate-900 mb-2">Unit
                            Pendidikan</label>
                        <div class="relative">
                            <select name="education_unit_id" id="education_unit_id" required
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-200 bg-slate-50 text-slate-900 text-sm appearance-none focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all">
                                <option value="">Pilih Unit</option>
                                <?php foreach ($units as $u): ?>
                                    <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div
                                class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-500">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Tingkat Removed -->
                </div>

                <!-- Wali Kelas (Searchable) -->
                <div class="relative">
                    <label for="teacher_search" class="block text-sm font-semibold text-slate-900 mb-2">Wali
                        Kelas</label>

                    <!-- Hidden Input for Form Submission -->
                    <input type="hidden" name="teacher_id" id="teacher_id_input">

                    <!-- Search Input -->
                    <input type="text" id="teacher_search" autocomplete="off"
                        class="w-full px-4 py-2.5 rounded-lg border border-slate-200 bg-slate-50 text-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all placeholder:text-slate-400"
                        placeholder="Cari wali kelas...">

                    <!-- Dropdown List (Hidden by default) -->
                    <ul id="teacher_list"
                        class="hidden absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                        <?php foreach ($teachers as $t): ?>
                            <li class="teacher-option relative cursor-pointer select-none py-2 pl-3 pr-9 hover:bg-cyan-50 text-slate-900"
                                data-id="<?php echo $t['id']; ?>" data-name="<?php echo htmlspecialchars($t['name']); ?>">
                                <span class="block truncate"><?php echo htmlspecialchars($t['name']); ?></span>
                            </li>
                        <?php endforeach; ?>
                        <!-- No results item -->
                        <li id="no_results"
                            class="hidden relative cursor-default select-none py-2 pl-3 pr-9 text-slate-500 italic">
                            Tidak ditemukan.
                        </li>
                    </ul>
                </div>

                <!-- Simple JS for Searchable Select -->
                <script>
                    const searchInput = document.getElementById('teacher_search');
                    const hiddenInput = document.getElementById('teacher_id_input');
                    const list = document.getElementById('teacher_list');
                    const options = document.querySelectorAll('.teacher-option');
                    const noResults = document.getElementById('no_results');
                    let activeOptionIndex = -1;

                    // Open/Close list
                    searchInput.addEventListener('focus', () => {
                        list.classList.remove('hidden');
                    });

                    // Filter logic
                    searchInput.addEventListener('input', function () {
                        const filter = this.value.toLowerCase();
                        let hasResults = false;

                        // If cleared, reset ID
                        if (filter === '') {
                            hiddenInput.value = '';
                        }

                        options.forEach(option => {
                            const text = option.getAttribute('data-name').toLowerCase();
                            if (text.includes(filter)) {
                                option.classList.remove('hidden');
                                hasResults = true;
                            } else {
                                option.classList.add('hidden');
                            }
                        });

                        if (!hasResults) {
                            noResults.classList.remove('hidden');
                        } else {
                            noResults.classList.add('hidden');
                        }
                        list.classList.remove('hidden');
                    });

                    // Select logic
                    options.forEach(option => {
                        option.addEventListener('click', function () {
                            const id = this.getAttribute('data-id');
                            const name = this.getAttribute('data-name');

                            hiddenInput.value = id;
                            searchInput.value = name;
                            list.classList.add('hidden');
                        });
                    });

                    // Hide when clicking outside
                    document.addEventListener('click', function (e) {
                        if (!searchInput.contains(e.target) && !list.contains(e.target)) {
                            list.classList.add('hidden');
                        }
                    });
                </script>

            </div>

            <!-- Footer Action -->
            <div class="flex items-center justify-end gap-3 mt-8 pt-6 border-t border-slate-100">
                <a href="<?php url('views/grade_levels/index.php'); ?>"
                    class="px-6 py-2.5 rounded-lg border border-slate-200 text-slate-700 text-sm font-medium hover:bg-slate-50 transition-colors">
                    Batal
                </a>
                <button type="submit" name="save" value="save"
                    class="px-6 py-2.5 rounded-lg bg-cyan-600 text-white text-sm font-semibold hover:bg-cyan-700 transition-colors flex items-center gap-2 shadow-sm shadow-cyan-200">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                        <path fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-11.25a.75.75 0 00-1.5 0v2.5h-2.5a.75.75 0 000 1.5h2.5v2.5a.75.75 0 001.5 0v-2.5h2.5a.75.75 0 000-1.5h-2.5v-2.5z"
                            clip-rule="evenodd" />
                    </svg>
                    Simpan Kelas
                </button>
            </div>

        </div>
    </form>

    <!-- Info Box -->
    <div class="mt-6 bg-blue-50 border border-blue-100 rounded-xl p-4 flex gap-3">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
            class="w-5 h-5 text-blue-500 shrink-0 mt-0.5">
            <path fill-rule="evenodd"
                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z"
                clip-rule="evenodd" />
        </svg>
        <div>
            <h4 class="text-sm font-semibold text-blue-700 mb-1">Catatan:</h4>
            <ul class="list-disc list-inside text-xs text-blue-600 space-y-1 ml-1">
                <li>Pastikan Unit Pendidikan sesuai dengan struktur organisasi.</li>
                <li>Wali kelas yang ditampilkan hanya pegawai yang aktif dan belum memiliki kelas.</li>
                <li>Siswa dapat ditambahkan ke kelas ini setelah data kelas disimpan.</li>
            </ul>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>