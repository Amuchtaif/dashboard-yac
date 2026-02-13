<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$page_title = "Tambah Izin";

$db = new Database();
$conn = $db->getConnection();

// Fetch Employees
$employees = $conn->query("SELECT id, full_name FROM employees ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="w-full pb-10">
    <!-- Breadcrumb -->
    <nav class="flex mb-4" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
            <li class="inline-flex items-center">
                <a href="<?php url('views/dashboard/index.php'); ?>"
                    class="text-slate-500 hover:text-slate-700">Beranda</a>
            </li>
            <li>
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    <a href="<?php url('views/permits/index.php'); ?>"
                        class="ml-1 text-slate-500 hover:text-slate-700">Perizinan</a>
                </div>
            </li>
            <li>
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    <span class="ml-1 font-medium text-slate-800">Tambah Pengajuan</span>
                </div>
            </li>
        </ol>
    </nav>

    <div class="mb-8">
        <h1 class="text-2xl font-bold text-slate-900">Input Izin Manual</h1>
        <p class="mt-2 text-sm text-slate-600">Buat pengajuan izin baru untuk pegawai.</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl md:col-span-2">
        <form action="<?php url('logic/permits/store.php'); ?>" method="POST" enctype="multipart/form-data">
            <div class="px-4 py-6 sm:p-8">
                <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">

                    <!-- Employee (Advanced Searchable Dropdown) -->
                    <div class="sm:col-span-4">
                        <label for="employee_id"
                            class="block text-sm font-medium leading-6 text-gray-900">Pegawai</label>
                        <div class="mt-2 relative" id="dropdown-container-employee">
                            <input type="hidden" name="employee_id" id="input-employee"
                                value="<?php echo isset($employee_id) ? $employee_id : ''; ?>">
                            <button type="button" onclick="toggleFormDropdown('employee')" id="button-employee"
                                class="flex w-full items-center justify-between rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-200 transition-all">
                                <span id="text-employee" class="block truncate">Pilih Pegawai</span>
                                <svg class="h-4 w-4 text-slate-500 transition-transform duration-200"
                                    id="arrow-employee" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                                        clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div id="menu-employee"
                                class="absolute z-50 mt-1 hidden max-h-60 w-full overflow-auto rounded-lg bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                                <!-- Search Input -->
                                <div class="sticky top-0 z-10 bg-white px-3 py-2 border-b border-slate-100">
                                    <input type="text" id="search-employee" onkeyup="filterDropdown('employee')"
                                        class="block w-full rounded-md border-0 py-1.5 px-3 text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-cyan-600 sm:text-sm sm:leading-6"
                                        placeholder="Cari pegawai...">
                                </div>
                                <ul class="py-1" id="list-employee">
                                    <li onclick="selectFormOption('employee', '', 'Pilih Pegawai')"
                                        class="cursor-pointer px-4 py-2 text-sm text-slate-500 hover:bg-slate-50 hover:text-cyan-700 transition-colors">
                                        Pilih Pegawai
                                    </li>
                                    <?php foreach ($employees as $emp): ?>
                                        <li onclick="selectFormOption('employee', '<?php echo $emp['id']; ?>', '<?php echo htmlspecialchars($emp['full_name'], ENT_QUOTES); ?>')"
                                            class="cursor-pointer px-4 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors dropdown-item">
                                            <?php echo htmlspecialchars($emp['full_name']); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Permit Type (Custom Dropdown) -->
                    <div class="sm:col-span-4">
                        <label for="permit_type" class="block text-sm font-medium leading-6 text-gray-900">Jenis
                            Izin</label>
                        <div class="mt-2 relative" id="dropdown-container-type">
                            <input type="hidden" name="permit_type" id="input-type" value="">
                            <button type="button" onclick="toggleFormDropdown('type')" id="button-type"
                                class="flex w-full items-center justify-between rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-200 transition-all">
                                <span id="text-type" class="block truncate">Pilih Jenis</span>
                                <svg class="h-4 w-4 text-slate-500 transition-transform duration-200" id="arrow-type"
                                    xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                                        clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div id="menu-type"
                                class="absolute z-50 mt-1 hidden max-h-60 w-full overflow-auto rounded-lg bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                                <ul class="py-1">
                                    <li onclick="selectFormOption('type', 'Sick', 'Sakit')"
                                        class="cursor-pointer px-4 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                        Sakit
                                    </li>
                                    <li onclick="selectFormOption('type', 'Permission', 'Izin')"
                                        class="cursor-pointer px-4 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                        Izin
                                    </li>
                                    <li onclick="selectFormOption('type', 'Leave', 'Cuti')"
                                        class="cursor-pointer px-4 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                        Cuti
                                    </li>
                                    <li onclick="selectFormOption('type', 'Other', 'Lainnya')"
                                        class="cursor-pointer px-4 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                        Lainnya
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- JS for Dropdowns -->
                    <script>
                        let activeFormDropdownId = null;

                        function toggleFormDropdown(id) {
                            const menu = document.getElementById('menu-' + id);
                            const arrow = document.getElementById('arrow-' + id);

                            // Close others
                            if (activeFormDropdownId && activeFormDropdownId !== id) {
                                closeFormDropdown(activeFormDropdownId);
                            }

                            if (menu.classList.contains('hidden')) {
                                menu.classList.remove('hidden');
                                if (arrow) arrow.classList.add('rotate-180');
                                activeFormDropdownId = id;

                                // Focus search if exists
                                const searchInput = document.getElementById('search-' + id);
                                if (searchInput) setTimeout(() => searchInput.focus(), 100);
                            } else {
                                closeFormDropdown(id);
                            }
                        }

                        function closeFormDropdown(id) {
                            const menu = document.getElementById('menu-' + id);
                            const arrow = document.getElementById('arrow-' + id);
                            if (menu) menu.classList.add('hidden');
                            if (arrow) arrow.classList.remove('rotate-180');
                            activeFormDropdownId = null;
                        }

                        function selectFormOption(id, value, label) {
                            document.getElementById('input-' + id).value = value;
                            document.getElementById('text-' + id).textContent = label;
                            closeFormDropdown(id);
                        }

                        function filterDropdown(id) {
                            const input = document.getElementById('search-' + id);
                            const filter = input.value.toLowerCase();
                            const ul = document.getElementById('list-' + id);
                            const li = ul.getElementsByTagName('li');

                            for (let i = 0; i < li.length; i++) {
                                // Skip the first default 'Select' option
                                if (i === 0) continue;

                                const txtValue = li[i].textContent || li[i].innerText;
                                if (txtValue.toLowerCase().indexOf(filter) > -1) {
                                    li[i].style.display = "";
                                } else {
                                    li[i].style.display = "none";
                                }
                            }
                        }

                        document.addEventListener('click', (e) => {
                            if (activeFormDropdownId) {
                                const container = document.getElementById('dropdown-container-' + activeFormDropdownId);
                                if (container && !container.contains(e.target)) {
                                    closeFormDropdown(activeFormDropdownId);
                                }
                            }
                        });
                    </script>

                    <!-- Dates -->
                    <div class="sm:col-span-3">
                        <label for="start_date" class="block text-sm font-medium leading-6 text-gray-900">Tanggal
                            Mulai</label>
                        <div class="mt-2">
                            <input type="date" name="start_date" id="start_date" required
                                class="block w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all shadow-sm">
                        </div>
                    </div>

                    <div class="sm:col-span-3">
                        <label for="end_date" class="block text-sm font-medium leading-6 text-gray-900">Tanggal
                            Selesai</label>
                        <div class="mt-2">
                            <input type="date" name="end_date" id="end_date" required
                                class="block w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all shadow-sm">
                        </div>
                    </div>

                    <!-- File Upload -->
                    <div class="col-span-full">
                        <label for="attachment" class="block text-sm font-medium leading-6 text-gray-900">Lampiran
                            (Bukti)</label>
                        <div class="mt-2 text-sm text-slate-500">
                            Unggah gambar (jpg, png) atau dokumen PDF. Maks 2MB.
                        </div>
                        <div class="mt-2">
                            <input type="file" name="attachment" id="attachment" accept=".jpg,.jpeg,.png,.pdf" class="block w-full text-sm text-slate-500
                                  file:mr-4 file:py-2.5 file:px-4
                                  file:rounded-lg file:border-0
                                  file:text-sm file:font-semibold
                                  file:bg-cyan-50 file:text-cyan-700
                                  hover:file:bg-cyan-100 transition-all border border-slate-300 rounded-lg">
                        </div>
                    </div>

                    <!-- Reason -->
                    <div class="col-span-full">
                        <label for="reason" class="block text-sm font-medium leading-6 text-gray-900">Alasan</label>
                        <div class="mt-2">
                            <textarea id="reason" name="reason" rows="3" required
                                class="block w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all shadow-sm"></textarea>
                        </div>
                    </div>

                </div>
            </div>
            <div class="flex items-center justify-end gap-x-6 border-t border-gray-900/10 px-4 py-4 sm:px-8">
                <a href="<?php url('views/permits/index.php'); ?>"
                    class="text-sm font-semibold leading-6 text-gray-900">Batal</a>
                <button type="submit"
                    class="rounded-md bg-cyan-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-cyan-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 transition-colors">Kirim
                    Pengajuan</button>
            </div>
        </form>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>