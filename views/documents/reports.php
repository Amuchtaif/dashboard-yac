<?php
// views/documents/reports.php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_documents');

$page_title = "Laporan Persuratan";

$db = new Database();
$conn = $db->getConnection();
$user_id = $_SESSION['user_id'];
$is_admin = isset($_SESSION['position_name']) && $_SESSION['position_name'] === 'Administrator';

// Filter inputs
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';

$params = [];
if (!$is_admin) {
    $stmtEmpInfo = $conn->prepare("SELECT division_id, department_id, unit_id FROM employees WHERE id = ?");
    $stmtEmpInfo->execute([$user_id]);
    $emp_info = $stmtEmpInfo->fetch(PDO::FETCH_ASSOC);
    $user_div_id = $emp_info['division_id'] ?: ($emp_info['department_id'] ?: 0);
    $user_unit_id = $emp_info['unit_id'] ?? 0;

    $where_clause = "d.status = 'completed' AND (
        d.creator_id = :user_id 
        OR (d.receiver_division_id = :user_div_id AND (d.receiver_unit_id IS NULL OR d.receiver_unit_id = :user_unit_id OR :user_unit_id = 0))
        OR (d.receiver_unit_id = :user_unit_id AND :user_unit_id != 0)
        OR d.id IN (SELECT document_id FROM document_dispositions WHERE to_user_id = :user_id)
    )";
    $params[':user_id'] = $user_id;
    $params[':user_div_id'] = $user_div_id;
    $params[':user_unit_id'] = $user_unit_id;
} else {
    $where_clause = "d.status = 'completed'";
}

if (!empty($start_date)) {
    $where_clause .= " AND DATE(d.created_at) >= :start_date";
    $params[':start_date'] = $start_date;
}

if (!empty($end_date)) {
    $where_clause .= " AND DATE(d.created_at) <= :end_date";
    $params[':end_date'] = $end_date;
}

if (!empty($type_filter)) {
    $where_clause .= " AND d.type = :type";
    $params[':type'] = $type_filter;
}

$stmtList = $conn->prepare("
    SELECT d.id, d.document_number, d.title, d.type, d.sender, d.created_at,
           e.full_name as creator_name, u.name as creator_unit
    FROM documents d
    LEFT JOIN employees e ON d.creator_id = e.id
    LEFT JOIN units u ON e.unit_id = u.id
    WHERE $where_clause
    ORDER BY d.created_at DESC
");
if (!empty($params)) {
    foreach ($params as $k => $v) {
        $stmtList->bindValue($k, $v);
    }
}
$stmtList->execute();
$records = $stmtList->fetchAll(PDO::FETCH_ASSOC) ?: [];

// General sums
$total_completed = count($records);
$outgoing_count = 0;
$incoming_count = 0;
foreach ($records as $r) {
    if ($r['type'] === 'outgoing') $outgoing_count++;
    else $incoming_count++;
}

include '../layouts/header.php';
?>
<!-- Font Awesome CDN -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="pb-10">
    <!-- Header -->
    <div class="border-b border-slate-200 pb-5">
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Laporan Statistik & Ekspor Persuratan</h1>
        <p class="mt-1 text-sm text-slate-500">Menganalisis volume surat dinas resmi yayasan serta mengunduhnya untuk keperluan administrasi.</p>
    </div>

    <!-- Stats summary grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Dokumen Selesai</p>
            <p class="text-2xl font-black text-indigo-600 mt-1"><?php echo $total_completed; ?></p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Surat Keluar Resmi</p>
            <p class="text-2xl font-black text-emerald-600 mt-1"><?php echo $outgoing_count; ?></p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Surat Masuk Eksternal</p>
            <p class="text-2xl font-black text-cyan-600 mt-1"><?php echo $incoming_count; ?></p>
        </div>
    </div>

    <!-- Filters Panel -->
    <div class="mt-8 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
        <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100 pb-2">Kriteria Laporan</h3>
        
        <form class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4" method="GET">
            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Dari Tanggal</label>
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>"
                       class="block w-full rounded-lg border-slate-200 py-1.5 text-sm focus:border-indigo-500 focus:ring-indigo-500 bg-slate-50 border text-slate-600">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Sampai Tanggal</label>
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>"
                       class="block w-full rounded-lg border-slate-200 py-1.5 text-sm focus:border-indigo-500 focus:ring-indigo-500 bg-slate-50 border text-slate-600">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Jenis Dokumen</label>
                <select name="type" class="select-custom w-full rounded-lg border-slate-200 text-sm focus:border-indigo-500 focus:ring-indigo-500 bg-slate-50 border py-2 pl-3 pr-8 text-slate-600">
                    <option value="">Semua Jenis</option>
                    <option value="outgoing" <?php echo $type_filter === 'outgoing' ? 'selected' : ''; ?>>Surat Keluar</option>
                    <option value="incoming" <?php echo $type_filter === 'incoming' ? 'selected' : ''; ?>>Surat Masuk</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-indigo-700 transition-colors">
                    Filter Laporan
                </button>
                <a href="<?php url('views/documents/reports.php'); ?>" class="inline-flex w-full items-center justify-center rounded-lg bg-white border border-slate-300 px-4 py-2.5 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Export Action bar -->
    <div class="mt-8 bg-slate-50 border border-slate-200/60 rounded-xl p-4 flex flex-col sm:flex-row justify-between items-center gap-4">
        <div>
            <h4 class="text-xs font-bold text-slate-700">Tindakan Ekspor</h4>
            <p class="text-[10px] text-slate-400">Unduh data hasil pencarian ke format spreadsheet pilihan Anda.</p>
        </div>
        <div class="flex gap-2">
            <button onclick="exportToCSV()" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 transition-colors">
                Ekspor ke CSV
            </button>
            <button onclick="exportToExcel()" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-indigo-700 transition-colors">
                Ekspor ke Excel
            </button>
        </div>
    </div>

    <!-- TABLE -->
    <div class="mt-4 overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-xl border border-slate-200 bg-white">
        <table id="report-table" class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                    <th scope="col" class="py-3.5 pl-4 pr-3 text-left sm:pl-6 w-12">No.</th>
                    <th scope="col" class="px-3 py-3.5 text-left">Nomor Surat</th>
                    <th scope="col" class="px-3 py-3.5 text-left">Perihal / Hal</th>
                    <th scope="col" class="px-3 py-3.5 text-left">Kategori</th>
                    <th scope="col" class="px-3 py-3.5 text-left">Asal / Instansi</th>
                    <th scope="col" class="px-3 py-3.5 text-left">Pembuat</th>
                    <th scope="col" class="px-3 py-3.5 text-left">Unit Kerja</th>
                    <th scope="col" class="px-3 py-3.5 text-center">Tanggal Registrasi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white text-slate-600 text-xs">
                <?php if (count($records) > 0): ?>
                    <?php foreach ($records as $index => $rec): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-xs text-slate-400 sm:pl-6">
                                <?php echo $index + 1; ?>.
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 font-bold text-slate-700">
                                <?php echo htmlspecialchars($rec['document_number']); ?>
                            </td>
                            <td class="px-3 py-4 font-medium text-slate-600">
                                <?php echo htmlspecialchars($rec['title']); ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 uppercase font-bold text-[9px]">
                                <?php echo $rec['type'] === 'outgoing' ? 'Keluar' : 'Masuk'; ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4">
                                <?php echo htmlspecialchars($rec['type'] === 'outgoing' ? 'Internal Yayasan' : ($rec['sender'] ?: '-')); ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4">
                                <?php echo htmlspecialchars($rec['creator_name']); ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4">
                                <?php echo htmlspecialchars($rec['creator_unit'] ?: '-'); ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-center text-slate-400">
                                <?php echo date('d/m/Y', strtotime($rec['created_at'])); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="py-10 text-center text-sm text-slate-500 italic">Data laporan tidak ditemukan.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function exportToCSV() {
    let csv = [];
    const rows = document.querySelectorAll("#report-table tr");
    
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll("td, th");
        
        for (let j = 0; j < cols.length; j++) {
            let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, "").replace(/(\s\s)/gm, " ");
            data = data.replace(/"/g, '""');
            row.push('"' + data + '"');
        }
        csv.push(row.join(","));
    }
    
    const csvContent = "data:text/csv;charset=utf-8," + csv.join("\n");
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "laporan_persuratan_" + new Date().toISOString().slice(0,10) + ".csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function exportToExcel() {
    const table = document.getElementById("report-table");
    const html = table.outerHTML;
    
    // Simple excel file builder via data uri
    const url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
    const link = document.createElement("a");
    link.download = "laporan_persuratan_" + new Date().toISOString().slice(0,10) + ".xls";
    link.href = url;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php include '../layouts/footer.php'; ?>
