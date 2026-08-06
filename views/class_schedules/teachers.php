<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$page_title = "Data Guru";

$db = new Database();
$conn = $db->getConnection();

// --- Filter Logic ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : '';

$where_clauses = [];
$params = [];

if ($search) {
    $where_clauses[] = "(e.full_name LIKE :search OR s.name LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($unit_id) {
    $where_clauses[] = "eu.id = :unit_id";
    $params[':unit_id'] = $unit_id;
}

$where_sql = count($where_clauses) > 0 ? "AND " . implode(" AND ", $where_clauses) : "";

// Fetch Data
$query = "
    SELECT 
        e.id,
        e.full_name as teacher_name, 
        GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR '||') as subjects,
        GROUP_CONCAT(DISTINCT eu.name ORDER BY FIELD(eu.name, 'Playgroup', 'TKIT', 'SDIT', 'MTs', 'Idad Lughoh', 'MA', 'Mahad Aly') SEPARATOR '||') as units
    FROM class_schedules cs
    JOIN employees e ON cs.employee_id = e.id
    JOIN subjects s ON cs.subject_id = s.id
    JOIN grade_levels gl ON cs.grade_level_id = gl.id
    JOIN education_units eu ON gl.education_unit_id = eu.id
    WHERE 1=1 $where_sql
    GROUP BY e.id, e.full_name
    ORDER BY e.full_name ASC
";

$stmt = $conn->prepare($query);
foreach ($params as $key => $val) {
    if ($key === ':search') {
        $stmt->bindValue($key, $val);
    } else {
        $stmt->bindValue($key, $val);
    }
}
$stmt->execute();
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Units for Filter
$units = $conn->query("SELECT id, name FROM education_units ORDER BY FIELD(name, 'Playgroup', 'TKIT', 'SDIT', 'MTs', 'Idad Lughoh', 'MA', 'Mahad Aly') ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="pb-10">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-xl font-bold text-slate-900">Data Guru</h1>
            <p class="mt-2 text-sm text-slate-500">Daftar karyawan yang memiliki jadwal mengajar beserta mata pelajaran dan unit yang diampu.</p>
        </div>
    </div>

    <!-- Filter Bar -->
    <form id="filterForm" class="mt-8 bg-white p-6 rounded-xl border border-slate-200 shadow-sm" method="GET">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <!-- Search -->
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <i class="fa-solid fa-magnifying-glass h-4 w-4 text-slate-400"></i>
                </div>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                    class="block w-full rounded-lg border-slate-200 pl-10 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border placeholder:text-slate-400 text-slate-600 py-2.5"
                    placeholder="Cari nama guru, mapel...">
            </div>

            <!-- Unit Filter -->
            <div class="relative" id="container-unit_id">
                <input type="hidden" name="unit_id" id="input-unit_id" value="<?php echo $unit_id; ?>">
                <button type="button" onclick="toggleFormDropdown('unit_id')"
                    class="flex w-full items-center justify-between rounded-lg border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all">
                    <span id="text-unit_id" class="block truncate">
                        <?php 
                        $unitTitle = "Semua Unit";
                        foreach($units as $u) if((string)$u['id'] === (string)$unit_id) $unitTitle = $u['name'];
                        echo htmlspecialchars($unitTitle);
                        ?>
                    </span>
                    <i id="arrow-unit_id" class="fa-solid fa-chevron-down h-4 w-4 text-slate-400 transition-transform"></i>
                </button>
                <div id="menu-unit_id" class="hidden absolute z-30 mt-1 max-h-60 w-full overflow-auto rounded-xl bg-white py-1 text-base shadow-2xl ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                    <div class="sticky top-0 z-10 bg-white px-2 py-1.5">
                        <input type="text" id="search-unit_id" onkeyup="filterDropdownSearch('unit_id')" placeholder="Cari unit..." class="block w-full rounded-md border-slate-200 py-1.5 pl-3 text-sm focus:border-cyan-500 focus:ring-cyan-500">
                    </div>
                    <ul id="list-unit_id">
                        <li onclick="selectFilterOption('unit_id', '', 'Semua Unit')" class="relative cursor-pointer select-none py-2 pl-3 pr-9 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">Semua Unit</li>
                        <?php foreach ($units as $u): ?>
                            <li onclick="selectFilterOption('unit_id', '<?php echo $u['id']; ?>', '<?php echo htmlspecialchars($u['name'], ENT_QUOTES); ?>')" class="relative cursor-pointer select-none py-2 pl-3 pr-9 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                <?php echo htmlspecialchars($u['name']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Reset Button -->
            <div>
                <a href="teachers.php" 
                    class="flex items-center justify-center w-full px-4 py-2.5 rounded-lg bg-slate-800 text-white text-sm font-semibold hover:bg-slate-900 shadow-sm transition-all active:scale-95">
                    <i class="fa-solid fa-xmark w-4 h-4 mr-2"></i>
                    Hapus Filter
                </a>
            </div>
        </div>
        <button type="submit" class="hidden">Filter</button>
    </form>

    <script>
    function toggleFormDropdown(id) {
        const menu = document.getElementById('menu-' + id);
        const arrow = document.getElementById('arrow-' + id);
        const allMenus = document.querySelectorAll('[id^="menu-"]');
        const allArrows = document.querySelectorAll('[id^="arrow-"]');
        
        allMenus.forEach(m => { if(m !== menu) m.classList.add('hidden'); });
        allArrows.forEach(a => { if(a !== arrow) a.classList.remove('rotate-180'); });

        menu.classList.toggle('hidden');
        arrow.classList.toggle('rotate-180');
        
        if (!menu.classList.contains('hidden') && document.getElementById('search-' + id)) {
            document.getElementById('search-' + id).focus();
        }
    }

    function selectFilterOption(id, value, text) {
        document.getElementById('input-' + id).value = value;
        document.getElementById('text-' + id).innerText = text;
        document.getElementById('menu-' + id).classList.add('hidden');
        document.getElementById('arrow-' + id).classList.remove('rotate-180');

        document.getElementById('filterForm').submit();
    }

    function filterDropdownSearch(id) {
        const input = document.getElementById('search-' + id);
        const filter = input.value.toLowerCase();
        const list = document.getElementById('list-' + id);
        const li = list.getElementsByTagName('li');

        for (let i = 0; i < li.length; i++) {
            const txtValue = li[i].textContent || li[i].innerText;
            const matchesSearch = txtValue.toLowerCase().indexOf(filter) > -1;
            li[i].style.display = matchesSearch ? "" : "none";
        }
    }

    // Close on click outside
    window.addEventListener('click', function (e) {
        document.querySelectorAll('[id^="container-"]').forEach(container => {
            if (!container.contains(e.target)) {
                const id = container.id.replace('container-', '');
                const menu = document.getElementById('menu-' + id);
                if(menu) menu.classList.add('hidden');
                const arrow = document.getElementById('arrow-' + id);
                if(arrow) arrow.classList.remove('rotate-180');
            }
        });
    });
    </script>

    <!-- Table -->
    <div class="mt-8 flex flex-col">
        <div class="overflow-x-auto shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 w-12 sm:pl-6">
                            No.</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Nama Guru</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Mata Pelajaran yang Diajar</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Unit yang Diajar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <?php if (empty($teachers)): ?>
                        <tr>
                            <td colspan="4" class="py-10 text-center text-sm text-slate-500">Belum ada data guru pengampu.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($teachers as $index => $t): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 sm:pl-6">
                                <?php echo $index + 1; ?>.
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm font-semibold text-slate-900">
                                <?php echo htmlspecialchars($t['teacher_name']); ?>
                            </td>
                            <td class="px-3 py-4 text-sm">
                                <div class="flex flex-wrap gap-1">
                                    <?php 
                                    $mapels = $t['subjects'] ? explode('||', $t['subjects']) : [];
                                    foreach ($mapels as $m): ?>
                                        <span class="inline-flex items-center rounded-md bg-cyan-50 px-2.5 py-1 text-xs font-medium text-cyan-700 ring-1 ring-inset ring-cyan-700/10">
                                            <?php echo htmlspecialchars($m); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td class="px-3 py-4 text-sm">
                                <div class="flex flex-wrap gap-1">
                                    <?php 
                                    $u_list = $t['units'] ? explode('||', $t['units']) : [];
                                    foreach ($u_list as $u): ?>
                                        <span class="inline-flex items-center rounded-md bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-500/10">
                                            <?php echo htmlspecialchars($u); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
