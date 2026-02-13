<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$db = new Database();
$conn = $db->getConnection();

$id = isset($_GET['id']) ? $_GET['id'] : null;
$is_edit = !empty($id);
$page_title = $is_edit ? "Edit Divisi" : "Tambah Divisi";

// Fetch Schedules
$schedules = $conn->query("SELECT * FROM work_schedules ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all employees for manager dropdown
$employees = $conn->query("SELECT id, full_name FROM employees ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

$department = [
    'name' => '',
    'schedule_id' => '',
    'manager_id' => ''
];

if ($is_edit) {
    $stmt = $conn->prepare("SELECT * FROM divisions WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $department = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$department) {
        header("Location: " . BASE_URL . "views/departments/index.php?error=Department not found");
        exit;
    }
}

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
                    <a href="<?php url('views/departments/index.php'); ?>"
                        class="ml-1 text-slate-500 hover:text-slate-700">Divisi</a>
                </div>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 6 10">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="m1 9 4-4-4-4" />
                    </svg>
                    <span class="ml-1 font-medium text-slate-800">
                        <?php echo $is_edit ? "Edit" : "Tambah Baru"; ?>
                    </span>
                </div>
            </li>
        </ol>
    </nav>

    <div class="mb-8">
        <h1 class="text-2xl font-bold text-slate-900">
            <?php echo $is_edit ? "Edit Divisi" : "Tambah Divisi Baru"; ?>
        </h1>
        <p class="mt-2 text-sm text-slate-600">
            <?php echo $is_edit ? "Perbarui rincian divisi dan jadwal standar." : "Buat divisi baru untuk mengatur tenaga kerja Anda."; ?>
        </p>
    </div>

    <!-- Alerts -->
    <?php if (isset($_GET['error'])): ?>
        <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-6 text-sm flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd"
                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                    clip-rule="evenodd" />
            </svg>
            <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl md:col-span-2">
        <form
            action="<?php echo $is_edit ? url('logic/departments/update.php') : url('logic/departments/create.php'); ?>"
            method="POST">
            <?php if ($is_edit): ?>
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
            <?php endif; ?>

            <div class="px-4 py-6 sm:p-8">
                <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">

                    <!-- Department Name -->
                    <div class="sm:col-span-4">
                        <label for="name" class="block text-sm font-medium leading-6 text-gray-900">Nama Divisi</label>
                        <div class="mt-2">
                            <input type="text" name="name" id="name" required
                                value="<?php echo htmlspecialchars($department['name']); ?>"
                                class="block w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all placeholder:text-slate-400 shadow-sm"
                                placeholder="misal: Keuangan">
                        </div>
                    </div>

                    <!-- Manager -->
                    <div class="sm:col-span-4">
                        <label for="manager_id" class="block text-sm font-medium leading-6 text-gray-900">Manajer
                            Divisi</label>
                        <p class="text-xs text-slate-500 mb-2">Pilih orang yang bertanggung jawab atas divisi ini.</p>
                        <div class="mt-2 relative">
                            <!-- Custom Searchable Dropdown for Manager -->
                            <div class="relative" id="manager-dropdown-container">
                                <input type="hidden" name="manager_id" id="manager_id_hidden"
                                    value="<?php echo htmlspecialchars($department['manager_id'] ?? ''); ?>">

                                <button type="button" id="manager-dropdown-btn"
                                    class="relative w-full cursor-default rounded-lg bg-white py-2.5 pl-4 pr-10 text-left text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 focus:outline-none focus:ring-2 focus:ring-cyan-500 sm:text-sm sm:leading-6">
                                    <span class="block truncate" id="manager-selected-text">
                                        <?php
                                        // Pre-fill selected name if editing
                                        $selected_name = 'Pilih Manajer';
                                        if (!empty($department['manager_id'])) {
                                            foreach ($employees as $emp) {
                                                if ($emp['id'] == $department['manager_id']) {
                                                    $selected_name = $emp['full_name'];
                                                    break;
                                                }
                                            }
                                        }
                                        echo htmlspecialchars($selected_name);
                                        ?>
                                    </span>
                                    <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                                        <svg class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor"
                                            aria-hidden="true">
                                            <path fill-rule="evenodd"
                                                d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                </button>

                                <!-- Dropdown Menu -->
                                <div id="manager-dropdown-menu"
                                    class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm hidden">

                                    <!-- Search Input -->
                                    <div class="sticky top-0 z-10 bg-white px-2 py-2 border-b border-slate-100">
                                        <input type="text" id="manager-search-input"
                                            class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-cyan-600 sm:text-sm sm:leading-6 px-3"
                                            placeholder="Cari...">
                                    </div>

                                    <!-- Options List -->
                                    <ul class="py-1" id="manager-options-list" role="listbox">
                                        <li class="relative cursor-default select-none py-2 pl-3 pr-9 text-gray-900 hover:bg-cyan-50 cursor-pointer"
                                            data-value="" data-text="Select Manager"
                                            onclick="selectManager('', 'Select Manager')">
                                            <span class="block truncate font-medium text-slate-500">- None -</span>
                                        </li>
                                        <?php foreach ($employees as $emp): ?>
                                            <li class="relative cursor-default select-none py-2 pl-3 pr-9 text-gray-900 hover:bg-cyan-50 cursor-pointer manager-option"
                                                data-value="<?php echo $emp['id']; ?>"
                                                data-text="<?php echo htmlspecialchars($emp['full_name']); ?>"
                                                onclick="selectManager('<?php echo $emp['id']; ?>', '<?php echo htmlspecialchars($emp['full_name'], ENT_QUOTES); ?>')">
                                                <div class="flex items-center">
                                                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($emp['full_name']); ?>&background=random&size=20"
                                                        alt="" class="h-6 w-6 flex-shrink-0 rounded-full mr-2">
                                                    <span class="block truncate font-normal">
                                                        <?php echo htmlspecialchars($emp['full_name']); ?>
                                                    </span>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>

                            <script>
                                const dropdownBtn = document.getElementById('manager-dropdown-btn');
                                const dropdownMenu = document.getElementById('manager-dropdown-menu');
                                const searchInput = document.getElementById('manager-search-input');
                                const optionsList = document.getElementById('manager-options-list');
                                const hiddenInput = document.getElementById('manager_id_hidden');
                                const selectedText = document.getElementById('manager-selected-text');
                                const options = document.querySelectorAll('.manager-option');

                                // Toggle Dropdown
                                dropdownBtn.addEventListener('click', () => {
                                    dropdownMenu.classList.toggle('hidden');
                                    if (!dropdownMenu.classList.contains('hidden')) {
                                        searchInput.focus();
                                    }
                                });

                                // Filter Options
                                searchInput.addEventListener('input', (e) => {
                                    const filter = e.target.value.toLowerCase();
                                    options.forEach(option => {
                                        const text = option.getAttribute('data-text').toLowerCase();
                                        if (text.includes(filter)) {
                                            option.style.display = '';
                                        } else {
                                            option.style.display = 'none';
                                        }
                                    });
                                });

                                // Select Manager Function
                                window.selectManager = function (value, text) {
                                    hiddenInput.value = value;
                                    selectedText.textContent = text;
                                    dropdownMenu.classList.add('hidden');
                                }

                                // Close dropdown when clicking outside
                                document.addEventListener('click', (e) => {
                                    if (!document.getElementById('manager-dropdown-container').contains(e.target)) {
                                        dropdownMenu.classList.add('hidden');
                                    }
                                });
                            </script>
                        </div>
                    </div>

                    <!-- Default Work Schedule -->
                    <div class="sm:col-span-4">
                        <label for="schedule_id" class="block text-sm font-medium leading-6 text-gray-900">Jadwal Kerja
                            Standar</label>
                        <p class="text-xs text-slate-500 mb-2">Pilih shift default untuk karyawan di divisi ini.</p>
                        <div class="mt-2 relative" id="dropdown-container-schedule">
                            <input type="hidden" name="schedule_id" id="input-schedule"
                                value="<?php echo $department['schedule_id']; ?>">
                            <button type="button" onclick="toggleFormDropdown('schedule')" id="button-schedule"
                                class="flex w-full items-center justify-between rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-200 transition-all">
                                <span id="text-schedule" class="block truncate">
                                    <?php
                                    $currentScheduleName = 'Pilih Jadwal...';
                                    foreach ($schedules as $schedule) {
                                        if ($department['schedule_id'] == $schedule['id']) {
                                            $currentScheduleName = $schedule['name'];
                                            break;
                                        }
                                    }
                                    echo htmlspecialchars($currentScheduleName);
                                    ?>
                                </span>
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
                                    <li onclick="selectFormOption('schedule', '', 'Pilih Jadwal...')"
                                        class="cursor-pointer px-4 py-2 text-sm text-slate-500 hover:bg-slate-50 hover:text-cyan-700 transition-colors">
                                        Pilih Jadwal...
                                    </li>
                                    <?php foreach ($schedules as $schedule): ?>
                                        <li onclick="selectFormOption('schedule', '<?php echo $schedule['id']; ?>', '<?php echo htmlspecialchars($schedule['name'], ENT_QUOTES); ?>')"
                                            class="cursor-pointer px-4 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                            <?php echo htmlspecialchars($schedule['name']); ?>
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

                </div>
            </div>
            <div class="flex items-center justify-end gap-x-6 border-t border-gray-900/10 px-4 py-4 sm:px-8">
                <a href="<?php url('views/departments/index.php'); ?>"
                    class="text-sm font-semibold leading-6 text-gray-900">Batal</a>
                <button type="submit"
                    class="rounded-md bg-cyan-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-cyan-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 transition-colors">
                    <?php echo $is_edit ? "Simpan Perubahan" : "Simpan Divisi"; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>