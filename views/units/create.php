<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$page_title = "Tambah Unit";

$db = new Database();
$conn = $db->getConnection();

// Fetch Divisions using PDO
$divisions = $conn->query("SELECT id, name FROM divisions ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Work Schedules
$schedules = $conn->query("SELECT id, name FROM work_schedules ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="w-full pb-10">
    <!-- Breadcrumb -->
    <nav class="flex mb-4" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
            <li class="inline-flex items-center">
                <a href="<?php url('views/dashboard/index.php'); ?>"
                    class="inline-flex items-center text-slate-500 hover:text-slate-700">
                    Beranda
                </a>
            </li>
            <li>
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 6 10">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="m1 9 4-4-4-4" />
                    </svg>
                    <a href="<?php url('views/units/index.php'); ?>"
                        class="ml-1 text-slate-500 hover:text-slate-700">Unit</a>
                </div>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 6 10">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="m1 9 4-4-4-4" />
                    </svg>
                    <span class="ml-1 font-medium text-slate-800">Tambah Baru</span>
                </div>
            </li>
        </ol>
    </nav>

    <div class="mb-8">
        <h1 class="text-2xl font-bold text-slate-900">Tambah Unit Baru</h1>
        <p class="mt-2 text-sm text-slate-600">Buat unit operasional atau tim baru.</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl md:col-span-2">
        <form action="<?php url('logic/units/store.php'); ?>" method="POST">
            <div class="px-4 py-6 sm:p-8">
                <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">

                    <div class="sm:col-span-4">
                        <label for="name" class="block text-sm font-medium leading-6 text-gray-900">Nama Unit</label>
                        <div class="mt-2">
                            <input type="text" name="name" id="name" required
                                class="block w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all placeholder:text-slate-400 shadow-sm"
                                placeholder="misal: Tim Payroll">
                        </div>
                    </div>

                    <div class="sm:col-span-4">
                        <label for="division_id"
                            class="block text-sm font-medium leading-6 text-gray-900">Divisi</label>
                        <div class="mt-2 relative" id="dropdown-container-division">
                            <input type="hidden" name="division_id" id="input-division" value="">
                            <button type="button" onclick="toggleFormDropdown('division')" id="button-division"
                                class="flex w-full items-center justify-between rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-200 transition-all">
                                <span id="text-division" class="block truncate">Pilih Divisi</span>
                                <svg class="h-4 w-4 text-slate-500 transition-transform duration-200"
                                    id="arrow-division" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                                        clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div id="menu-division"
                                class="absolute z-50 mt-1 hidden max-h-60 w-full overflow-auto rounded-lg bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                                <ul class="py-1">
                                    <li onclick="selectFormOption('division', '', 'Pilih Divisi')"
                                        class="cursor-pointer px-4 py-2 text-sm text-slate-500 hover:bg-slate-50 hover:text-cyan-700 transition-colors">
                                        Pilih Divisi
                                    </li>
                                    <?php foreach ($divisions as $div): ?>
                                        <li onclick="selectFormOption('division', '<?php echo $div['id']; ?>', '<?php echo htmlspecialchars($div['name'], ENT_QUOTES); ?>')"
                                            class="cursor-pointer px-4 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                            <?php echo htmlspecialchars($div['name']); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>

                        <script>
                            let activeFormDropdownId = null;

                            function toggleFormDropdown(id) {
                                const menu = document.getElementById('menu-' + id);
                                const arrow = document.getElementById('arrow-' + id);

                                if (activeFormDropdownId && activeFormDropdownId !== id) {
                                    closeFormDropdown(activeFormDropdownId);
                                }

                                if (menu.classList.contains('hidden')) {
                                    menu.classList.remove('hidden');
                                    if (arrow) arrow.classList.add('rotate-180');
                                    activeFormDropdownId = id;
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

                            document.addEventListener('click', (e) => {
                                if (activeFormDropdownId) {
                                    const container = document.getElementById('dropdown-container-' + activeFormDropdownId);
                                    if (container && !container.contains(e.target)) {
                                        closeFormDropdown(activeFormDropdownId);
                                    }
                                }
                            });
                        </script>
                    </div>

                    <div class="sm:col-span-4">
                        <label for="schedule_id" class="block text-sm font-medium leading-6 text-gray-900">Jadwal
                            Kerja</label>
                        <div class="mt-2 relative" id="dropdown-container-schedule">
                            <input type="hidden" name="schedule_id" id="input-schedule" value="">
                            <button type="button" onclick="toggleFormDropdown('schedule')" id="button-schedule"
                                class="flex w-full items-center justify-between rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-200 transition-all">
                                <span id="text-schedule" class="block truncate">-- Ikuti Jadwal Divisi (Default)
                                    --</span>
                                <svg class="h-4 w-4 text-slate-500 transition-transform duration-200"
                                    id="arrow-schedule" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                                        clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div id="menu-schedule"
                                class="absolute z-50 mt-1 hidden max-h-60 w-full overflow-auto rounded-lg bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                                <ul class="py-1">
                                    <li onclick="selectFormOption('schedule', '', '-- Ikuti Jadwal Divisi (Default) --')"
                                        class="cursor-pointer px-4 py-2 text-sm text-slate-500 hover:bg-slate-50 hover:text-cyan-700 transition-colors">
                                        -- Ikuti Jadwal Divisi (Default) --
                                    </li>
                                    <?php foreach ($schedules as $schedule): ?>
                                        <li onclick="selectFormOption('schedule', '<?php echo $schedule['id']; ?>', '<?php echo htmlspecialchars($schedule['name'], ENT_QUOTES); ?>')"
                                            class="cursor-pointer px-4 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                            <?php echo htmlspecialchars($schedule['name']); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">Kosongkan untuk menggunakan jadwal yang ditetapkan
                                pada Divisi.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-end gap-x-6 border-t border-gray-900/10 px-4 py-4 sm:px-8">
                <a href="<?php url('views/units/index.php'); ?>"
                    class="text-sm font-semibold leading-6 text-gray-900">Batal</a>
                <button type="submit"
                    class="rounded-md bg-cyan-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-cyan-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 transition-colors">
                    Simpan Unit
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>