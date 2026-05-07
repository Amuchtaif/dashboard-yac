<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$page_title = "Monitoring Lokasi Inventaris";
require_once __DIR__ . '/../layouts/header.php';

$db = new Database();
$conn = $db->getConnection();

// Fetch All Locations once for breadcrumbs
$locStmt = $conn->query("SELECT id, name, parent_id, location_code FROM inventory_locations ORDER BY name ASC");
$allLocs = $locStmt->fetchAll(PDO::FETCH_ASSOC);
$locMap = [];
foreach ($allLocs as $l) {
    $locMap[$l['id']] = $l;
}

function getBreadcrumbPath($map, $locId) {
    if (!isset($map[$locId])) return "-";
    $path = [];
    $curr = $locId;
    while ($curr != null) {
        $path[] = $map[$curr]['name'];
        $curr = $map[$curr]['parent_id'];
    }
    return implode(" > ", array_reverse($path));
}

// Fetch Item Counts per Location
$countStmt = $conn->query("SELECT location_id, COUNT(*) as total FROM inventory_items GROUP BY location_id");
$itemCounts = [];
while ($c = $countStmt->fetch(PDO::FETCH_ASSOC)) {
    $itemCounts[$c['location_id']] = (int)$c['total'];
}

// Prepare data for the table
$monitoringData = [];
foreach ($allLocs as $loc) {
    $count = $itemCounts[$loc['id']] ?? 0;
    $monitoringData[] = [
        'id' => $loc['id'],
        'name' => $loc['name'],
        'code' => $loc['location_code'],
        'breadcrumb' => getBreadcrumbPath($locMap, $loc['id']),
        'item_count' => $count
    ];
}

// Sort by breadcrumb for better readability
usort($monitoringData, function($a, $b) {
    return strcmp($a['breadcrumb'], $b['breadcrumb']);
});

// Stats
$totalLocations = count($monitoringData);
$emptyLocations = count(array_filter($monitoringData, fn($d) => $d['item_count'] === 0));
$filledLocations = $totalLocations - $emptyLocations;
$totalItems = array_sum($itemCounts);
?>

<div class="p-6">
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-slate-900">Monitoring Lokasi</h2>
        <p class="text-sm text-slate-500 mt-1">Pantau sebaran barang inventaris di seluruh lokasi.</p>
    </div>

    <!-- Stats Overview -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-cyan-100 text-cyan-600 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
            </div>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Total Lokasi</p>
                <p class="text-2xl font-black text-slate-800"><?php echo $totalLocations; ?></p>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Lokasi Terisi</p>
                <p class="text-2xl font-black text-slate-800"><?php echo $filledLocations; ?></p>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
            </div>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Lokasi Kosong</p>
                <p class="text-2xl font-black text-slate-800"><?php echo $emptyLocations; ?></p>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
            </div>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Total Barang</p>
                <p class="text-2xl font-black text-slate-800"><?php echo $totalItems; ?></p>
            </div>
        </div>
    </div>

    <!-- Table Section -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-slate-800 text-white flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" /></svg>
                </div>
                <h3 class="font-bold text-slate-700">Daftar Status Lokasi</h3>
            </div>
            <div class="flex items-center gap-2">
                <input type="text" id="locSearch" onkeyup="filterTable()" placeholder="Cari lokasi..." class="px-4 py-2 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none transition w-64">
                <select id="statusFilter" onchange="filterTable()" class="px-4 py-2 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none transition cursor-pointer">
                    <option value="all">Semua Status</option>
                    <option value="filled">Terisi</option>
                    <option value="empty">Kosong</option>
                </select>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse" id="monitoringTable">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-6 py-4 text-xs font-black text-slate-400 uppercase tracking-widest text-center w-16">No</th>
                        <th class="px-6 py-4 text-xs font-black text-slate-400 uppercase tracking-widest">Nama Lokasi & Jalur</th>
                        <th class="px-6 py-4 text-xs font-black text-slate-400 uppercase tracking-widest text-center">Kode</th>
                        <th class="px-6 py-4 text-xs font-black text-slate-400 uppercase tracking-widest text-center">Total Barang</th>
                        <th class="px-6 py-4 text-xs font-black text-slate-400 uppercase tracking-widest text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($monitoringData as $index => $row): ?>
                    <tr class="hover:bg-slate-50/80 transition-colors" data-status="<?php echo $row['item_count'] > 0 ? 'filled' : 'empty'; ?>">
                        <td class="px-6 py-4 text-sm text-slate-500 text-center font-medium"><?php echo $index + 1; ?>.</td>
                        <td class="px-6 py-4">
                            <div class="font-bold text-slate-800 text-sm"><?php echo $row['name']; ?></div>
                            <div class="text-[10px] text-slate-400 font-medium uppercase tracking-tight"><?php echo $row['breadcrumb']; ?></div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-2 py-1 bg-slate-100 text-slate-600 rounded-md text-[10px] font-bold font-mono"><?php echo $row['code'] ?: '-'; ?></span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="text-sm font-black <?php echo $row['item_count'] > 0 ? 'text-cyan-600' : 'text-slate-300'; ?>">
                                <?php echo $row['item_count']; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <?php if ($row['item_count'] > 0): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>
                                    TERISI
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500">
                                    KOSONG
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function filterTable() {
        const searchInput = document.getElementById('locSearch').value.toLowerCase();
        const statusFilter = document.getElementById('statusFilter').value;
        const rows = document.querySelectorAll('#monitoringTable tbody tr');

        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            const status = row.getAttribute('data-status');
            
            const matchesSearch = text.includes(searchInput);
            const matchesStatus = (statusFilter === 'all' || status === statusFilter);

            if (matchesSearch && matchesStatus) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
