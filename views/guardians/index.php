<?php
// views/guardians/index.php
// Halaman Manajemen Wali Murid Terhubung - Dashboard YAC
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$page_title = "Manajemen Wali Murid";

$db = new Database();
$conn = $db->getConnection();

// --- Filter & Pagination Parameters ---
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$rel_filter = trim($_GET['relationship'] ?? '');
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 15;
if (!in_array($limit, [10, 15, 25, 50, 100])) {
    $limit = 15;
}
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// --- KPI Statistics ---
$kpi_query = "
    SELECT 
        COUNT(DISTINCT g.id) as total_guardians,
        COALESCE(SUM(CASE WHEN g.status = 'active' THEN 1 ELSE 0 END), 0) as active_guardians,
        COALESCE(SUM(CASE WHEN g.status = 'inactive' THEN 1 ELSE 0 END), 0) as inactive_guardians,
        COUNT(DISTINCT gs.student_id) as total_linked_students
    FROM guardians g
    LEFT JOIN guardian_students gs ON g.id = gs.guardian_id
";
$raw_kpi = $conn->query($kpi_query)->fetch(PDO::FETCH_ASSOC) ?: [];
$kpi_stats = [
    'total_guardians' => (int)($raw_kpi['total_guardians'] ?? 0),
    'active_guardians' => (int)($raw_kpi['active_guardians'] ?? 0),
    'inactive_guardians' => (int)($raw_kpi['inactive_guardians'] ?? 0),
    'total_linked_students' => (int)($raw_kpi['total_linked_students'] ?? 0)
];

// --- Query Builder for Guardians List ---
$where_clauses = ["1=1"];
$params = [];

if ($status_filter !== '') {
    $where_clauses[] = "g.status = :status";
    $params[':status'] = $status_filter;
}

if ($rel_filter !== '') {
    $where_clauses[] = "g.relationship = :rel";
    $params[':rel'] = $rel_filter;
}

if ($search !== '') {
    $where_clauses[] = "(
        g.full_name LIKE :search_name 
        OR g.phone_number LIKE :search_phone 
        OR EXISTS (
            SELECT 1 FROM guardian_students gs2 
            JOIN students s2 ON gs2.student_id = s2.id 
            WHERE gs2.guardian_id = g.id AND (s2.nama_siswa LIKE :search_sname OR s2.nomor_induk LIKE :search_nisn)
        )
    )";
    $params[':search_name'] = "%{$search}%";
    $params[':search_phone'] = "%{$search}%";
    $params[':search_sname'] = "%{$search}%";
    $params[':search_nisn'] = "%{$search}%";
}

$where_sql = implode(' AND ', $where_clauses);

// Count Total Rows
$count_stmt = $conn->prepare("SELECT COUNT(*) FROM guardians g WHERE $where_sql");
$count_stmt->execute($params);
$total_rows = (int)$count_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Fetch Guardians Page
$data_query = "
    SELECT 
        g.*,
        (SELECT COUNT(*) FROM guardian_students WHERE guardian_id = g.id) as children_count
    FROM guardians g
    WHERE $where_sql
    ORDER BY g.id DESC
    LIMIT :limit OFFSET :offset
";
$stmt = $conn->prepare($data_query);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->execute();
$guardians = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Connected Children for the current batch of guardians
$guardian_children_map = [];
if (!empty($guardians)) {
    $gids = array_column($guardians, 'id');
    $placeholders = implode(',', array_fill(0, count($gids), '?'));
    $child_sql = "
        SELECT 
            gs.guardian_id,
            gs.relationship as child_relationship,
            s.id as student_id,
            s.nama_siswa,
            s.nomor_induk,
            s.tingkat,
            s.kelas,
            gl.name as grade_name,
            eu.name as unit_name
        FROM guardian_students gs
        JOIN students s ON gs.student_id = s.id
        LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.status = 'ACTIVE'
        LEFT JOIN grade_levels gl ON sch.class_id = gl.id
        LEFT JOIN education_units eu ON gl.education_unit_id = eu.id
        WHERE gs.guardian_id IN ($placeholders)
        GROUP BY gs.guardian_id, s.id
        ORDER BY s.nama_siswa ASC
    ";
    $child_stmt = $conn->prepare($child_sql);
    $child_stmt->execute($gids);
    while ($crow = $child_stmt->fetch(PDO::FETCH_ASSOC)) {
        $guardian_children_map[$crow['guardian_id']][] = $crow;
    }
}

// Fetch Active Students & Guardians for Link Modal
$students_modal = $conn->query("
    SELECT 
        s.id, 
        s.nama_siswa, 
        s.nomor_induk, 
        s.status,
        COALESCE(gl.name, s.kelas, '-') as kelas,
        COALESCE(eu.name, s.tingkat, '-') as tingkat
    FROM students s
    LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.status = 'ACTIVE'
    LEFT JOIN grade_levels gl ON sch.class_id = gl.id
    LEFT JOIN education_units eu ON gl.education_unit_id = eu.id
    WHERE s.status = 'Aktif'
    GROUP BY s.id
    ORDER BY s.nama_siswa ASC
")->fetchAll(PDO::FETCH_ASSOC);

$guardians_modal = $conn->query("
    SELECT id, full_name, phone_number 
    FROM guardians 
    ORDER BY full_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<style>
    /* Premium Select Styling */
    select option {
        padding: 8px 12px;
        font-size: 12px;
        font-weight: 500;
        color: #1e293b;
        background-color: #ffffff;
    }
    #selectStudentId::-webkit-scrollbar {
        width: 6px;
    }
    #selectStudentId::-webkit-scrollbar-track {
        background: #f8fafc;
        border-radius: 8px;
    }
    #selectStudentId::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 8px;
    }
    #selectStudentId::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
</style>

<div class="w-full pb-14">

    <!-- Top Breadcrumbs & Page Header -->
    <div class="mb-6">
        <nav class="flex mb-3" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-2 text-xs text-slate-500">
                <li>
                    <a href="<?php url('views/dashboard/index.php'); ?>" class="hover:text-slate-800 transition-colors">
                        <i class="fa-solid fa-house mr-1"></i> Beranda
                    </a>
                </li>
                <li>
                    <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 mx-1"></i>
                    <span class="text-slate-500 font-medium">Manajemen Akademik</span>
                </li>
                <li aria-current="page">
                    <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 mx-1"></i>
                    <span class="text-[#2B3990] font-semibold">Wali Murid</span>
                </li>
            </ol>
        </nav>

        <div class="sm:flex sm:items-center sm:justify-between gap-4">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-xl bg-gradient-to-tr from-[#2B3990] to-indigo-600 flex items-center justify-center text-white shadow-md shadow-indigo-100 flex-shrink-0">
                        <i class="fa-solid fa-people-roof text-lg"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
                            Manajemen Wali Murid
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200/60">
                                Portal Wali
                            </span>
                        </h1>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Kelola akun wali murid terhubung, kontrol hak akses, hubungkan santri manual, dan atur ulang kata sandi.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Action Buttons: Hubungkan Manual & Buka Portal -->
            <div class="mt-4 sm:mt-0 flex items-center gap-2.5 flex-wrap">
                <button type="button" onclick="openLinkModal()"
                   class="inline-flex items-center justify-center rounded-lg bg-cyan-600 hover:bg-cyan-700 px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition-colors cursor-pointer">
                    <i class="fa-solid fa-link mr-2"></i>
                    Hubungkan Santri Manual
                </button>

                <a href="<?php url('portal_wali/index.php'); ?>" target="_blank"
                   class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors"
                   title="Buka aplikasi Portal Wali Santri">
                    <i class="fa-solid fa-up-right-from-square mr-2 text-slate-500"></i>
                    Buka Portal Wali
                </a>
            </div>
        </div>
    </div>

    <!-- Banner Notifikasi Password Baru + Tombol Kirim ke WhatsApp -->
    <?php if (isset($_GET['pwd_reset_plain']) && isset($_GET['pwd_reset_phone'])): 
        $rpName = $_GET['pwd_reset_name'] ?? 'Wali Santri';
        $rpPhone = $_GET['pwd_reset_phone'] ?? '';
        $rpPlain = $_GET['pwd_reset_plain'] ?? '';
        $waMessage = "Assalamu'alaikum Warahmatullahi Wabarakatuh,\n\nBapak/Ibu {$rpName},\nBerikut adalah informasi pembaruan kata sandi akun Portal Wali Santri Yayasan Assunnah Cirebon:\n\n- Nomor WhatsApp: {$rpPhone}\n- Kata Sandi Baru: {$rpPlain}\n\nSilakan masuk melalui tautan berikut:\nhttps://wali.assunnahcirebon.com\n\nJazakumullahu khairan.";
        $waUrl = "https://wa.me/{$rpPhone}?text=" . rawurlencode($waMessage);
    ?>
        <div class="bg-gradient-to-r from-emerald-600 to-teal-700 text-white p-4 rounded-2xl mb-6 shadow-md flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-lg shrink-0">
                    <i class="fa-solid fa-key"></i>
                </div>
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-100">Kata Sandi Baru Berhasil Diatur</h3>
                    <p class="text-sm mt-0.5">
                        Wali: <strong><?php echo htmlspecialchars($rpName); ?></strong> &bull; Kata Sandi: <code class="px-2 py-0.5 rounded bg-black/30 font-mono text-emerald-200 font-bold"><?php echo htmlspecialchars($rpPlain); ?></code>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a href="<?php echo $waUrl; ?>" target="_blank"
                   class="inline-flex items-center gap-2 px-3.5 py-2 bg-white text-emerald-800 hover:bg-emerald-50 rounded-xl text-xs font-bold transition-all shadow-xs">
                    <i class="fa-brands fa-whatsapp text-emerald-600 text-base"></i>
                    <span>Kirim Password ke WhatsApp</span>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Summary KPI Cards Grid (Tanpa Ikon Kecil, Watermark Besar Bersih) -->
    <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-4 gap-3.5 mb-6">
        <!-- 1. Total Wali -->
        <div class="group relative overflow-hidden bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col justify-between hover:border-slate-300 hover:shadow-lg hover:shadow-slate-200/60 hover:-translate-y-1 transition-all duration-300 ease-out">
            <div class="absolute -right-3 -bottom-3 text-7xl text-slate-800/[0.05] group-hover:text-slate-800/[0.12] transform group-hover:scale-115 group-hover:-rotate-6 transition-all duration-300 pointer-events-none select-none">
                <i class="fa-solid fa-people-roof"></i>
            </div>
            <div class="relative z-10">
                <span class="text-xs font-medium text-slate-500 group-hover:text-slate-800 transition-colors">Total Wali Terdaftar</span>
            </div>
            <div class="relative z-10 mt-3">
                <div class="text-2xl font-bold text-slate-900"><?php echo number_format((int)($kpi_stats['total_guardians'] ?? 0)); ?></div>
                <div class="text-[11px] text-slate-400 mt-0.5">Akun orang tua/wali</div>
            </div>
        </div>

        <!-- 2. Akses Aktif -->
        <div class="group relative overflow-hidden bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col justify-between hover:border-emerald-300 hover:shadow-lg hover:shadow-emerald-500/10 hover:-translate-y-1 transition-all duration-300 ease-out">
            <div class="absolute -right-3 -bottom-3 text-7xl text-emerald-600/[0.06] group-hover:text-emerald-600/[0.16] transform group-hover:scale-115 group-hover:-rotate-6 transition-all duration-300 pointer-events-none select-none">
                <i class="fa-solid fa-user-check"></i>
            </div>
            <div class="relative z-10">
                <span class="text-xs font-medium text-slate-500 group-hover:text-emerald-700 transition-colors">Akses Aktif</span>
            </div>
            <div class="relative z-10 mt-3">
                <div class="text-2xl font-bold text-emerald-600"><?php echo number_format((int)($kpi_stats['active_guardians'] ?? 0)); ?></div>
                <div class="text-[11px] text-slate-400 mt-0.5">Dapat login ke portal</div>
            </div>
        </div>

        <!-- 3. Akses Terputus -->
        <div class="group relative overflow-hidden bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col justify-between hover:border-rose-300 hover:shadow-lg hover:shadow-rose-500/10 hover:-translate-y-1 transition-all duration-300 ease-out">
            <div class="absolute -right-3 -bottom-3 text-7xl text-rose-600/[0.06] group-hover:text-rose-600/[0.16] transform group-hover:scale-115 group-hover:-rotate-6 transition-all duration-300 pointer-events-none select-none">
                <i class="fa-solid fa-user-xmark"></i>
            </div>
            <div class="relative z-10">
                <span class="text-xs font-medium text-slate-500 group-hover:text-rose-700 transition-colors">Akses Diputus</span>
            </div>
            <div class="relative z-10 mt-3">
                <div class="text-2xl font-bold text-rose-600"><?php echo number_format((int)($kpi_stats['inactive_guardians'] ?? 0)); ?></div>
                <div class="text-[11px] text-slate-400 mt-0.5">Dinonaktifkan admin</div>
            </div>
        </div>

        <!-- 4. Santri Terhubung -->
        <div class="group relative overflow-hidden bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col justify-between hover:border-teal-300 hover:shadow-lg hover:shadow-teal-500/10 hover:-translate-y-1 transition-all duration-300 ease-out">
            <div class="absolute -right-3 -bottom-3 text-7xl text-teal-600/[0.06] group-hover:text-teal-600/[0.16] transform group-hover:scale-115 group-hover:-rotate-6 transition-all duration-300 pointer-events-none select-none">
                <i class="fa-solid fa-user-graduate"></i>
            </div>
            <div class="relative z-10">
                <span class="text-xs font-medium text-slate-500 group-hover:text-teal-700 transition-colors">Santri Terhubung</span>
            </div>
            <div class="relative z-10 mt-3">
                <div class="text-2xl font-bold text-teal-700"><?php echo number_format((int)($kpi_stats['total_linked_students'] ?? 0)); ?></div>
                <div class="text-[11px] text-slate-400 mt-0.5">Santri terkoneksi wali</div>
            </div>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-6">
        <form method="GET" action="index.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
            <!-- Search -->
            <div class="lg:col-span-2">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Pencarian</label>
                <div class="relative">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                           placeholder="Cari wali / no. WA / nama santri / NISN..."
                           class="block w-full rounded-lg border border-slate-300 bg-white pl-8 pr-3 py-2 text-xs text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-cyan-600 focus:border-cyan-600 shadow-sm h-9">
                    <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                </div>
            </div>

            <!-- Status Akses -->
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Status Akses</label>
                <div class="relative group">
                    <select name="status" onchange="this.form.submit()"
                            class="block w-full appearance-none rounded-xl border border-slate-200 bg-slate-50/70 pl-3.5 pr-9 py-2 text-xs font-semibold text-slate-700 hover:bg-white hover:border-slate-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-cyan-500/10 focus:border-cyan-600 shadow-xs transition-all cursor-pointer h-9">
                        <option value="" <?php echo $status_filter === '' ? 'selected' : ''; ?>>Semua Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Aktif (Diberi Akses)</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Akses Diputus (Nonaktif)</option>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 group-hover:text-slate-600 transition-colors">
                        <i class="fa-solid fa-chevron-down text-[10px]"></i>
                    </div>
                </div>
            </div>

            <!-- Hubungan -->
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Hubungan</label>
                <div class="relative group">
                    <select name="relationship" onchange="this.form.submit()"
                            class="block w-full appearance-none rounded-xl border border-slate-200 bg-slate-50/70 pl-3.5 pr-9 py-2 text-xs font-semibold text-slate-700 hover:bg-white hover:border-slate-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-cyan-500/10 focus:border-cyan-600 shadow-xs transition-all cursor-pointer h-9">
                        <option value="" <?php echo $rel_filter === '' ? 'selected' : ''; ?>>Semua Hubungan</option>
                        <option value="Ayah" <?php echo $rel_filter === 'Ayah' ? 'selected' : ''; ?>>Ayah</option>
                        <option value="Ibu" <?php echo $rel_filter === 'Ibu' ? 'selected' : ''; ?>>Ibu</option>
                        <option value="Wali" <?php echo $rel_filter === 'Wali' ? 'selected' : ''; ?>>Wali</option>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 group-hover:text-slate-600 transition-colors">
                        <i class="fa-solid fa-chevron-down text-[10px]"></i>
                    </div>
                </div>
            </div>

            <!-- Buttons -->
            <div class="flex items-center gap-2">
                <button type="submit" 
                        class="flex-1 inline-flex items-center justify-center h-9 px-4 rounded-lg bg-cyan-600 hover:bg-cyan-700 text-white text-xs font-semibold shadow-sm transition-colors cursor-pointer">
                    <i class="fa-solid fa-filter mr-1.5"></i> Filter
                </button>
                <a href="index.php" title="Reset Filter"
                   class="inline-flex items-center justify-center h-9 w-9 rounded-lg border border-slate-300 bg-white text-slate-500 hover:text-slate-800 hover:bg-slate-50 transition-colors shadow-sm flex-shrink-0">
                    <i class="fa-solid fa-rotate-left text-xs"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Data Table Container -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                <thead class="bg-slate-50/80 text-slate-600 font-semibold uppercase tracking-wider text-[11px]">
                    <tr>
                        <th class="py-3 px-3.5 w-12 text-center">No</th>
                        <th class="py-3 px-4">Wali Murid</th>
                        <th class="py-3 px-4">Kontak WhatsApp</th>
                        <th class="py-3 px-4">Santri Terhubung</th>
                        <th class="py-3 px-3 text-center">Status Akses</th>
                        <th class="py-3 px-4">Terakhir Login</th>
                        <th class="py-3 px-4 text-center w-36">Aksi Kontrol</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    <?php if (empty($guardians)): ?>
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="h-12 w-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-2 text-xl">
                                        <i class="fa-solid fa-people-roof"></i>
                                    </div>
                                    <p class="font-semibold text-slate-600">Tidak ada data wali murid</p>
                                    <p class="text-[11px] text-slate-400 mt-0.5">Belum ada wali murid yang mendaftar atau sesuai kriteria filter.</p>
                                    <button type="button" onclick="openLinkModal()" class="mt-3 px-3.5 py-1.5 rounded-lg bg-cyan-600 hover:bg-cyan-700 text-white text-xs font-semibold cursor-pointer">
                                        <i class="fa-solid fa-plus mr-1"></i> Hubungkan Santri Manual
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $no = $offset + 1;
                        foreach ($guardians as $g): 
                            $gid = (int)$g['id'];
                            $gChildren = $guardian_children_map[$gid] ?? [];
                            $cleanPhone = preg_replace('/[^0-9]/', '', $g['phone_number']);
                            $waNumber = (strpos($cleanPhone, '0') === 0) ? '62' . substr($cleanPhone, 1) : $cleanPhone;
                        ?>
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <!-- No -->
                                <td class="py-3.5 px-3.5 text-center text-slate-400 font-mono font-medium">
                                    <?php echo $no++; ?>
                                </td>

                                <!-- Wali Murid -->
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-3">
                                        <div class="h-9 w-9 rounded-xl bg-indigo-50 text-[#2B3990] flex items-center justify-center font-bold text-xs shrink-0 border border-indigo-100 shadow-xs">
                                            <?php echo strtoupper(substr($g['full_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-slate-900"><?php echo htmlspecialchars($g['full_name']); ?></div>
                                            <div class="flex items-center gap-1.5 mt-0.5 text-[11px] text-slate-400">
                                                <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-semibold bg-slate-100 text-slate-600">
                                                    <?php echo htmlspecialchars($g['relationship']); ?>
                                                </span>
                                                &bull;
                                                <span>Daftar: <?php echo date('d/m/Y', strtotime($g['created_at'])); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Kontak WhatsApp -->
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-slate-800 font-medium"><?php echo htmlspecialchars($g['phone_number']); ?></span>
                                        <?php if (!empty($waNumber)): ?>
                                            <a href="https://wa.me/<?php echo $waNumber; ?>" target="_blank"
                                               class="p-1 rounded-md text-emerald-600 hover:bg-emerald-50 hover:text-emerald-700 transition-colors"
                                               title="Chat WhatsApp">
                                                <i class="fa-brands fa-whatsapp text-sm"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Santri Terhubung -->
                                <td class="py-3.5 px-4">
                                    <div class="space-y-1.5">
                                        <?php if (!empty($gChildren)): ?>
                                            <?php foreach ($gChildren as $c): ?>
                                                <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-lg bg-slate-50 border border-slate-200/80 text-[11px]">
                                                    <i class="fa-solid fa-user-graduate text-slate-400 text-[10px]"></i>
                                                    <span class="font-semibold text-slate-800"><?php echo htmlspecialchars($c['nama_siswa']); ?></span>
                                                    <span class="text-slate-400 text-[10px] font-mono">(NISN: <?php echo htmlspecialchars($c['nomor_induk'] ?? '-'); ?>)</span>
                                                    <span class="text-indigo-600 font-medium text-[10px]"><?php echo htmlspecialchars($c['unit_name'] ?? $c['tingkat'] ?? ''); ?></span>
                                                    
                                                    <!-- Tombol Putus Santri Ini -->
                                                    <button type="button" 
                                                            class="btn-unlink-action text-rose-400 hover:text-rose-600 ml-1 cursor-pointer" 
                                                            data-gid="<?php echo $gid; ?>"
                                                            data-sid="<?php echo $c['student_id']; ?>"
                                                            data-sname="<?php echo htmlspecialchars($c['nama_siswa'], ENT_QUOTES); ?>"
                                                            data-gname="<?php echo htmlspecialchars($g['full_name'], ENT_QUOTES); ?>"
                                                            title="Putus keterhubungan santri ini">
                                                        <i class="fa-solid fa-xmark text-xs pointer-events-none"></i>
                                                    </button>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-slate-400 italic text-[11px] block">Belum ada santri terhubung</span>
                                        <?php endif; ?>

                                        <!-- Tombol Cepat Tambah Santri ke Wali Ini -->
                                        <div>
                                            <button type="button" 
                                                    class="btn-quick-link inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-cyan-50 text-cyan-700 border border-cyan-200 hover:bg-cyan-100 transition-colors cursor-pointer"
                                                    data-gid="<?php echo $gid; ?>"
                                                    data-gname="<?php echo htmlspecialchars($g['full_name'], ENT_QUOTES); ?>"
                                                    title="Hubungkan santri ke wali ini">
                                                <i class="fa-solid fa-plus text-[9px] pointer-events-none"></i>
                                                <span>Tambah Santri</span>
                                            </button>
                                        </div>
                                    </div>
                                </td>

                                <!-- Status Akses -->
                                <td class="py-3.5 px-3 text-center">
                                    <?php if ($g['status'] === 'active'): ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Aktif
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                            Akses Diputus
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Terakhir Login -->
                                <td class="py-3.5 px-4 text-slate-500 text-[11px]">
                                    <?php if (!empty($g['last_login_at'])): ?>
                                        <div class="font-medium text-slate-700"><?php echo date('d M Y', strtotime($g['last_login_at'])); ?></div>
                                        <div class="text-[10px] text-slate-400 font-mono"><?php echo date('H:i', strtotime($g['last_login_at'])); ?> WIB</div>
                                    <?php else: ?>
                                        <span class="text-slate-400 italic">Belum login</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Aksi Kontrol -->
                                <td class="py-3.5 px-4 text-center">
                                    <div class="inline-flex items-center gap-1.5">
                                        <!-- Tombol Reset Password -->
                                        <button type="button" 
                                                class="btn-pwd-action inline-flex items-center justify-center h-8 w-8 rounded-lg border border-amber-200 bg-amber-50/70 text-amber-700 hover:bg-amber-100 hover:border-amber-300 transition-colors shadow-xs cursor-pointer"
                                                data-id="<?php echo $gid; ?>"
                                                data-name="<?php echo htmlspecialchars($g['full_name'], ENT_QUOTES); ?>"
                                                data-phone="<?php echo htmlspecialchars($g['phone_number'], ENT_QUOTES); ?>"
                                                title="Ubah kata sandi wali murid">
                                            <i class="fa-solid fa-key text-xs pointer-events-none"></i>
                                        </button>

                                        <!-- Tombol Edit Data Wali -->
                                        <button type="button"
                                                class="btn-edit-action inline-flex items-center justify-center h-8 w-8 rounded-lg border border-slate-300 bg-white text-slate-600 hover:text-cyan-700 hover:border-cyan-300 hover:bg-cyan-50/50 transition-colors shadow-xs cursor-pointer"
                                                data-id="<?php echo $gid; ?>"
                                                data-name="<?php echo htmlspecialchars($g['full_name'], ENT_QUOTES); ?>"
                                                data-phone="<?php echo htmlspecialchars($g['phone_number'], ENT_QUOTES); ?>"
                                                data-relationship="<?php echo htmlspecialchars($g['relationship'] ?? 'Ayah', ENT_QUOTES); ?>"
                                                data-status="<?php echo htmlspecialchars($g['status'], ENT_QUOTES); ?>"
                                                title="Edit data wali murid">
                                            <i class="fa-solid fa-pen-to-square text-xs pointer-events-none"></i>
                                        </button>

                                        <!-- Tombol Toggle Status (Putus/Aktifkan Akses) -->
                                        <?php if ($g['status'] === 'active'): ?>
                                            <button type="button" 
                                                    class="btn-toggle-action inline-flex items-center justify-center h-8 w-8 rounded-lg border border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 transition-colors shadow-xs cursor-pointer"
                                                    data-id="<?php echo $gid; ?>"
                                                    data-name="<?php echo htmlspecialchars($g['full_name'], ENT_QUOTES); ?>"
                                                    data-status="inactive"
                                                    title="Putus akses portal (Nonaktifkan)">
                                                <i class="fa-solid fa-ban text-xs pointer-events-none"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" 
                                                    class="btn-toggle-action inline-flex items-center justify-center h-8 w-8 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition-colors shadow-xs cursor-pointer"
                                                    data-id="<?php echo $gid; ?>"
                                                    data-name="<?php echo htmlspecialchars($g['full_name'], ENT_QUOTES); ?>"
                                                    data-status="active"
                                                    title="Aktifkan kembali akses portal">
                                                <i class="fa-solid fa-check text-xs pointer-events-none"></i>
                                            </button>
                                        <?php endif; ?>

                                        <!-- Tombol Hapus Wali Murid -->
                                        <button type="button" 
                                                class="btn-delete-action inline-flex items-center justify-center h-8 w-8 rounded-lg border border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100 transition-colors shadow-xs cursor-pointer"
                                                data-id="<?php echo $gid; ?>"
                                                data-name="<?php echo htmlspecialchars($g['full_name'], ENT_QUOTES); ?>"
                                                data-phone="<?php echo htmlspecialchars($g['phone_number'], ENT_QUOTES); ?>"
                                                title="Hapus akun wali murid">
                                            <i class="fa-solid fa-trash-can text-xs pointer-events-none"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Table Pagination Footer -->
        <?php if ($total_pages > 1): ?>
            <div class="px-4 py-3 border-t border-slate-200 bg-slate-50/50 sm:flex sm:items-center sm:justify-between text-xs text-slate-500">
                <div>
                    Menampilkan <strong><?php echo $offset + 1; ?></strong> - <strong><?php echo min($offset + $limit, (int)$total_rows); ?></strong> dari <strong><?php echo number_format((int)$total_rows); ?></strong> wali murid
                </div>
                <div class="mt-2 sm:mt-0 flex items-center space-x-1">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                           class="px-2.5 py-1 rounded-md text-xs font-semibold <?php echo $i === $page ? 'bg-[#2B3990] text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-100'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL 1: UBAH KATA SANDI WALI MURID                      -->
<!-- ======================================================== -->
<div id="passwordModal" onclick="if(event.target === this) closePasswordModal()" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-md flex items-center justify-center hidden p-4 transition-all">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-xl border border-slate-200 overflow-hidden transform transition-all">
        <form method="POST" action="../../logic/guardians/update_password.php" onsubmit="return validatePasswordForm()">
            <input type="hidden" name="guardian_id" id="pwdGuardianId" value="">

            <div class="p-6">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-5">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
                            <i class="fa-solid fa-key"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Ubah Kata Sandi Wali</h3>
                            <p class="text-[11px] text-slate-500">Atur ulang PIN / password login portal</p>
                        </div>
                    </div>
                    <button type="button" onclick="closePasswordModal()" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="space-y-4">
                    <!-- Read-only info -->
                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/80 text-xs space-y-1">
                        <div class="text-slate-500">Nama Wali: <strong class="text-slate-800" id="pwdGuardianName">-</strong></div>
                        <div class="text-slate-500">No. WhatsApp: <strong class="text-slate-800 font-mono" id="pwdGuardianPhone">-</strong></div>
                    </div>

                    <!-- Input Password Baru -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-semibold text-slate-700">
                                Kata Sandi Baru <span class="text-rose-500">*</span>
                            </label>
                            <button type="button" onclick="generateRandomPassword()" class="text-[11px] text-cyan-600 hover:text-cyan-700 font-semibold flex items-center gap-1 cursor-pointer">
                                <i class="fa-solid fa-shuffle"></i>
                                <span>Acak Sandi</span>
                            </button>
                        </div>
                        <div class="relative">
                            <input type="text" name="new_password" id="inputNewPassword" required minlength="6"
                                   placeholder="Minimal 6 karakter"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none">
                        </div>
                        <span class="text-[10px] text-slate-400 mt-1 block">Setelah disimpan, Anda dapat langsung mengirimkan kata sandi ini ke nomor WhatsApp wali murid.</span>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2.5">
                <button type="button" onclick="closePasswordModal()" class="px-4 py-2 rounded-lg border border-slate-300 bg-white text-slate-700 text-xs font-semibold hover:bg-slate-50 cursor-pointer">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-cyan-600 hover:bg-cyan-700 text-white text-xs font-semibold shadow-xs cursor-pointer">
                    Simpan Kata Sandi
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL 2: KONFIRMASI PUTUS / AKTIFKAN AKSES               -->
<!-- ======================================================== -->
<div id="toggleModal" onclick="if(event.target === this) closeToggleModal()" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-md flex items-center justify-center hidden p-4 transition-all">
    <div class="bg-white w-full max-w-sm rounded-2xl shadow-xl border border-slate-200 overflow-hidden transform transition-all p-6 text-center">
        <form method="POST" action="../../logic/guardians/toggle_status.php">
            <input type="hidden" name="guardian_id" id="toggleGuardianId" value="">
            <input type="hidden" name="status" id="toggleStatusValue" value="">

            <div class="w-12 h-12 rounded-full mx-auto mb-4 flex items-center justify-center text-xl" id="toggleIconContainer">
                <i id="toggleIcon"></i>
            </div>

            <h3 class="text-base font-bold text-slate-900 mb-1" id="toggleTitle">Konfirmasi</h3>
            <p class="text-xs text-slate-500 mb-5 leading-relaxed" id="toggleDescription">
                Apakah Anda yakin ingin memproses akun ini?
            </p>

            <div class="flex items-center justify-center gap-2.5">
                <button type="button" onclick="closeToggleModal()" class="px-4 py-2 rounded-lg border border-slate-300 bg-white text-slate-700 text-xs font-semibold hover:bg-slate-50 cursor-pointer">
                    Batal
                </button>
                <button type="submit" id="toggleSubmitBtn" class="px-4 py-2 rounded-lg text-white text-xs font-semibold shadow-xs cursor-pointer">
                    Ya, Lanjutkan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL 3: KONFIRMASI LEPAS SANTRI                         -->
<!-- ======================================================== -->
<div id="unlinkModal" onclick="if(event.target === this) closeUnlinkModal()" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-md flex items-center justify-center hidden p-4 transition-all">
    <div class="bg-white w-full max-w-sm rounded-2xl shadow-xl border border-slate-200 overflow-hidden transform transition-all p-6 text-center">
        <form method="POST" action="../../logic/guardians/unlink_student.php">
            <input type="hidden" name="guardian_id" id="unlinkGuardianId" value="">
            <input type="hidden" name="student_id" id="unlinkStudentId" value="">

            <div class="w-12 h-12 rounded-full bg-rose-50 text-rose-600 mx-auto mb-4 flex items-center justify-center text-xl">
                <i class="fa-solid fa-link-slash"></i>
            </div>

            <h3 class="text-base font-bold text-slate-900 mb-1">Putus Keterhubungan Santri?</h3>
            <p class="text-xs text-slate-500 mb-5 leading-relaxed">
                Santri <strong class="text-slate-800" id="unlinkStudentName">-</strong> akan dilepas dari akun wali <strong class="text-slate-800" id="unlinkGuardianName">-</strong>. Akun wali santri tidak akan dihapus.
            </p>

            <div class="flex items-center justify-center gap-2.5">
                <button type="button" onclick="closeUnlinkModal()" class="px-4 py-2 rounded-lg border border-slate-300 bg-white text-slate-700 text-xs font-semibold hover:bg-slate-50 cursor-pointer">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold shadow-xs cursor-pointer">
                    Ya, Lepas Hubungan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL 4: HUBUNGKAN SANTRI MANUAL (ADMIN)                 -->
<!-- ======================================================== -->
<div id="linkModal" onclick="if(event.target === this) closeLinkModal()" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-md flex items-center justify-center hidden p-4 transition-all">
    <div class="bg-white w-full max-w-lg rounded-2xl shadow-xl border border-slate-200 overflow-hidden transform transition-all">
        <form method="POST" action="../../logic/guardians/link_student.php" onsubmit="return validateLinkForm()">
            <input type="hidden" name="mode" id="linkModeInput" value="existing">

            <div class="p-6">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-cyan-50 text-cyan-600 flex items-center justify-center font-bold">
                            <i class="fa-solid fa-link"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Hubungkan Santri ke Wali Murid</h3>
                            <p class="text-[11px] text-slate-500">Penetapan relasi orang tua santri oleh administrator</p>
                        </div>
                    </div>
                    <button type="button" onclick="closeLinkModal()" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <!-- Mode Selector Tab -->
                <div class="flex items-center bg-slate-100 p-1 rounded-xl mb-4 text-xs font-semibold">
                    <button type="button" id="tabModeExisting" onclick="switchLinkMode('existing')" 
                            class="flex-1 py-1.5 rounded-lg bg-white text-cyan-800 shadow-xs transition-all cursor-pointer">
                        Wali Terdaftar
                    </button>
                    <button type="button" id="tabModeNew" onclick="switchLinkMode('new')" 
                            class="flex-1 py-1.5 rounded-lg text-slate-500 hover:text-slate-800 transition-all cursor-pointer">
                        + Buat Wali Baru
                    </button>
                </div>

                <div class="space-y-3.5">
                    <!-- 1. Opsi Wali Terdaftar -->
                    <div id="sectionExistingGuardian">
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-semibold text-slate-700">
                                Pilih Akun Wali Murid <span class="text-rose-500">*</span>
                            </label>
                            <span class="text-[10px] text-slate-400" id="guardianCountLabel">Total: <?php echo count($guardians_modal); ?> Wali</span>
                        </div>

                        <!-- Kolom Searchable untuk Wali Murid -->
                        <div class="relative mb-1.5">
                            <input type="text" id="guardianSearchFilter" onkeyup="filterGuardianOptions(this.value)"
                                   placeholder="Ketik nama atau no. WhatsApp wali..."
                                   class="w-full pl-7 pr-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 text-slate-700 text-xs focus:border-cyan-600 focus:bg-white focus:outline-none">
                            <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-[10px]"></i>
                        </div>

                        <div class="relative group">
                            <select name="guardian_id" id="selectGuardianId"
                                    class="w-full appearance-none px-3.5 py-2.5 pr-10 rounded-xl border border-slate-200 bg-slate-50/70 text-slate-800 text-xs font-semibold hover:bg-white hover:border-slate-300 focus:bg-white focus:border-cyan-600 focus:ring-4 focus:ring-cyan-500/10 focus:outline-none transition-all cursor-pointer shadow-xs">
                                <option value="">-- Pilih Wali Murid --</option>
                                <?php foreach ($guardians_modal as $gm): ?>
                                    <option value="<?php echo $gm['id']; ?>">
                                        <?php echo htmlspecialchars($gm['full_name']); ?> &bull; WA: <?php echo htmlspecialchars($gm['phone_number']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 group-hover:text-slate-600 transition-colors">
                                <i class="fa-solid fa-chevron-down text-xs"></i>
                            </div>
                        </div>
                        <span class="text-[10px] text-slate-400 mt-1 block">Ketik di kolom cari untuk menyaring daftar pilihan wali murid.</span>
                    </div>

                    <!-- 2. Opsi Buat Wali Baru -->
                    <div id="sectionNewGuardian" class="hidden space-y-3 p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                Nama Lengkap Wali Murid <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="guardian_name" id="inputNewGuardianName" placeholder="Contoh: H. Syamsuddin"
                                   class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white text-slate-800 text-xs focus:border-cyan-600 focus:outline-none">
                        </div>
                        <div class="grid grid-cols-2 gap-2.5">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    No. WhatsApp <span class="text-rose-500">*</span>
                                </label>
                                <input type="tel" name="guardian_phone" id="inputNewGuardianPhone" placeholder="08xxxxxxxxxx"
                                       inputmode="numeric" pattern="[0-9]*" maxlength="16"
                                       oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                       class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white text-slate-800 text-xs focus:border-cyan-600 focus:outline-none">
                                <span class="text-[10px] text-slate-400 mt-0.5 block">Hanya angka (contoh: 08xxxxxxxxxx).</span>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Password Awal
                                </label>
                                <input type="text" name="guardian_password" id="inputNewGuardianPassword" value="password"
                                       class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white text-slate-800 text-xs focus:border-cyan-600 focus:outline-none font-mono">
                            </div>
                        </div>
                    </div>

                    <!-- Hubungan -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            Hubungan dengan Santri <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative group">
                            <select name="relationship"
                                    class="w-full appearance-none px-3.5 py-2.5 pr-10 rounded-xl border border-slate-200 bg-slate-50/70 text-slate-800 text-xs font-semibold hover:bg-white hover:border-slate-300 focus:bg-white focus:border-cyan-600 focus:ring-4 focus:ring-cyan-500/10 focus:outline-none transition-all cursor-pointer shadow-xs">
                                <option value="Ayah">Ayah</option>
                                <option value="Ibu">Ibu</option>
                                <option value="Wali">Wali</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 group-hover:text-slate-600 transition-colors">
                                <i class="fa-solid fa-chevron-down text-xs"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Pilih Santri Aktif (Dengan Kotak Pencarian Cepat) -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-semibold text-slate-700">
                                Pilih Santri Aktif <span class="text-rose-500">*</span>
                            </label>
                            <span class="text-[10px] text-emerald-600 font-semibold bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200/60">
                                <i class="fa-solid fa-circle-check text-[9px] mr-1"></i><?php echo count($students_modal); ?> Santri Aktif
                            </span>
                        </div>

                        <!-- Search input for filter student options -->
                        <div class="relative mb-1.5">
                            <input type="text" id="studentSearchFilter" onkeyup="filterStudentOptions(this.value)"
                                   placeholder="Ketik nama atau NISN santri aktif..."
                                   class="w-full pl-7 pr-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 text-slate-700 text-xs focus:border-cyan-600 focus:bg-white focus:outline-none">
                            <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-[10px]"></i>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-1.5 focus-within:border-cyan-600 focus-within:ring-4 focus-within:ring-cyan-500/10 focus-within:bg-white transition-all shadow-xs">
                            <select name="student_id" id="selectStudentId" required size="5"
                                    class="w-full bg-transparent p-1 text-slate-800 text-xs font-medium focus:outline-none border-0 divide-y divide-slate-100 cursor-pointer">
                                <?php foreach ($students_modal as $sm): ?>
                                    <option value="<?php echo $sm['id']; ?>" class="py-2 px-2.5 my-0.5 rounded-lg cursor-pointer transition-colors hover:bg-cyan-50 checked:bg-cyan-600 checked:text-white font-medium">
                                        <?php echo htmlspecialchars($sm['nama_siswa']); ?> &bull; NISN: <?php echo htmlspecialchars($sm['nomor_induk'] ?? '-'); ?> (<?php echo htmlspecialchars($sm['tingkat'] ?? ''); ?> <?php echo htmlspecialchars($sm['kelas'] ?? ''); ?>) &bull; [Aktif]
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <span class="text-[10px] text-slate-400 mt-1 block">Hanya menampilkan santri dengan status aktif. Klik santri di atas untuk memilih.</span>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2.5">
                <button type="button" onclick="closeLinkModal()" class="px-4 py-2 rounded-lg border border-slate-300 bg-white text-slate-700 text-xs font-semibold hover:bg-slate-50 cursor-pointer">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-cyan-600 hover:bg-cyan-700 text-white text-xs font-semibold shadow-xs cursor-pointer">
                    <i class="fa-solid fa-link mr-1.5"></i>
                    Simpan & Hubungkan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL 5: EDIT DATA WALI MURID                            -->
<!-- ======================================================== -->
<div id="editModal" onclick="if(event.target === this) closeEditModal()" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-md flex items-center justify-center hidden p-4 transition-all">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-xl border border-slate-200 overflow-hidden transform transition-all">
        <form method="POST" action="../../logic/guardians/update.php" onsubmit="return validateEditForm()">
            <input type="hidden" name="guardian_id" id="editGuardianId" value="">

            <div class="p-6">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-5">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-cyan-50 text-cyan-600 flex items-center justify-center font-bold">
                            <i class="fa-solid fa-user-pen"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Edit Data Wali Murid</h3>
                            <p class="text-[11px] text-slate-500">Perbarui identitas profil dan status akses</p>
                        </div>
                    </div>
                    <button type="button" onclick="closeEditModal()" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="space-y-4">
                    <!-- Nama Lengkap -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            Nama Lengkap <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="full_name" id="editFullName" required
                               placeholder="Nama lengkap wali murid"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none">
                    </div>

                    <!-- Nomor WhatsApp -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            Nomor WhatsApp <span class="text-rose-500">*</span>
                        </label>
                        <input type="tel" name="phone_number" id="editPhoneNumber" required
                               inputmode="numeric" pattern="[0-9]*" maxlength="16"
                               oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                               placeholder="08xxxxxxxxxx"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none">
                        <span class="text-[10px] text-slate-400 mt-1 block">Hanya angka (contoh: 08xxxxxxxxxx). Nomor ini digunakan untuk login.</span>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <!-- Hubungan Default -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                Hubungan <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative group">
                                <select name="relationship" id="editRelationship"
                                        class="w-full appearance-none px-3.5 py-2.5 pr-10 rounded-xl border border-slate-200 bg-slate-50/70 text-slate-800 text-xs font-semibold hover:bg-white hover:border-slate-300 focus:bg-white focus:border-cyan-600 focus:ring-4 focus:ring-cyan-500/10 focus:outline-none transition-all cursor-pointer shadow-xs">
                                    <option value="Ayah">Ayah</option>
                                    <option value="Ibu">Ibu</option>
                                    <option value="Wali">Wali</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 group-hover:text-slate-600 transition-colors">
                                    <i class="fa-solid fa-chevron-down text-xs"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Status Akses -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                Status Akses <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative group">
                                <select name="status" id="editStatus"
                                        class="w-full appearance-none px-3.5 py-2.5 pr-10 rounded-xl border border-slate-200 bg-slate-50/70 text-slate-800 text-xs font-semibold hover:bg-white hover:border-slate-300 focus:bg-white focus:border-cyan-600 focus:ring-4 focus:ring-cyan-500/10 focus:outline-none transition-all cursor-pointer shadow-xs">
                                    <option value="active">Aktif (Dapat Login)</option>
                                    <option value="inactive">Nonaktif (Akses Diputus)</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 group-hover:text-slate-600 transition-colors">
                                    <i class="fa-solid fa-chevron-down text-xs"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2.5">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 rounded-lg border border-slate-300 bg-white text-slate-700 text-xs font-semibold hover:bg-slate-50 cursor-pointer">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-cyan-600 hover:bg-cyan-700 text-white text-xs font-semibold shadow-xs cursor-pointer">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL 6: KONFIRMASI HAPUS DATA WALI MURID                -->
<!-- ======================================================== -->
<div id="deleteModal" onclick="if(event.target === this) closeDeleteModal()" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-md flex items-center justify-center hidden p-4 transition-all">
    <div class="bg-white w-full max-w-sm rounded-2xl shadow-xl border border-slate-200 overflow-hidden transform transition-all p-6 text-center">
        <form method="POST" action="../../logic/guardians/delete.php">
            <input type="hidden" name="guardian_id" id="deleteGuardianId" value="">

            <div class="w-12 h-12 rounded-full bg-rose-50 text-rose-600 mx-auto mb-4 flex items-center justify-center text-xl">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>

            <h3 class="text-base font-bold text-slate-900 mb-1">Hapus Akun Wali Murid?</h3>
            <p class="text-xs text-slate-500 mb-5 leading-relaxed">
                Akun wali murid <strong class="text-slate-800" id="deleteGuardianName">-</strong> (<span class="font-mono text-slate-600" id="deleteGuardianPhone">-</span>) akan dihapus secara permanen. Semua data keterhubungan santri juga akan dilepas.
            </p>

            <div class="flex items-center justify-center gap-2.5">
                <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 rounded-lg border border-slate-300 bg-white text-slate-700 text-xs font-semibold hover:bg-slate-50 cursor-pointer">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold shadow-xs cursor-pointer">
                    Ya, Hapus Permanen
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ======================================================== -->
<!-- JAVASCRIPT LOGIC (CSP NONCE COMPLIANT)                   -->
<!-- ======================================================== -->
<script nonce="<?php echo csp_nonce(); ?>">
document.addEventListener('DOMContentLoaded', function() {
    // 1. Event Delegation for Action Buttons
    document.addEventListener('click', function(e) {
        // Button Ubah Password
        const pwdBtn = e.target.closest('.btn-pwd-action');
        if (pwdBtn) {
            const id = pwdBtn.getAttribute('data-id');
            const name = pwdBtn.getAttribute('data-name');
            const phone = pwdBtn.getAttribute('data-phone');
            openPasswordModal(id, name, phone);
            return;
        }

        // Button Edit Data Wali
        const editBtn = e.target.closest('.btn-edit-action');
        if (editBtn) {
            const id = editBtn.getAttribute('data-id');
            const name = editBtn.getAttribute('data-name');
            const phone = editBtn.getAttribute('data-phone');
            const relationship = editBtn.getAttribute('data-relationship');
            const status = editBtn.getAttribute('data-status');
            openEditModal(id, name, phone, relationship, status);
            return;
        }

        // Button Toggle Status (Putus / Aktifkan)
        const toggleBtn = e.target.closest('.btn-toggle-action');
        if (toggleBtn) {
            const id = toggleBtn.getAttribute('data-id');
            const name = toggleBtn.getAttribute('data-name');
            const status = toggleBtn.getAttribute('data-status');
            openToggleModal(id, name, status);
            return;
        }

        // Button Hapus Wali Murid
        const delBtn = e.target.closest('.btn-delete-action');
        if (delBtn) {
            const id = delBtn.getAttribute('data-id');
            const name = delBtn.getAttribute('data-name');
            const phone = delBtn.getAttribute('data-phone');
            openDeleteModal(id, name, phone);
            return;
        }

        // Button Unlink Santri
        const unlinkBtn = e.target.closest('.btn-unlink-action');
        if (unlinkBtn) {
            const gid = unlinkBtn.getAttribute('data-gid');
            const sid = unlinkBtn.getAttribute('data-sid');
            const sname = unlinkBtn.getAttribute('data-sname');
            const gname = unlinkBtn.getAttribute('data-gname');
            openUnlinkModal(gid, sid, sname, gname);
            return;
        }

        // Button Quick Link Santri to this Guardian
        const quickLinkBtn = e.target.closest('.btn-quick-link');
        if (quickLinkBtn) {
            const gid = quickLinkBtn.getAttribute('data-gid');
            openLinkModal(gid);
            return;
        }
    });
});

// Modal Password Functions
function openPasswordModal(id, name, phone) {
    document.getElementById('pwdGuardianId').value = id;
    document.getElementById('pwdGuardianName').innerText = name;
    document.getElementById('pwdGuardianPhone').innerText = phone;
    document.getElementById('inputNewPassword').value = '';
    document.getElementById('passwordModal').classList.remove('hidden');
}

function closePasswordModal() {
    document.getElementById('passwordModal').classList.add('hidden');
}

function generateRandomPassword() {
    const chars = '0123456789';
    let pin = '';
    for (let i = 0; i < 6; i++) {
        pin += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('inputNewPassword').value = pin;
}

function triggerToast(message, type = 'success') {
    if (typeof showToast === 'function') {
        showToast(message, type);
    } else {
        console.log(`[Toast] ${type}: ${message}`);
    }
}

function validatePasswordForm() {
    const val = document.getElementById('inputNewPassword').value.trim();
    if (val.length < 6) {
        triggerToast('Kata sandi baru minimal 6 karakter.', 'error');
        return false;
    }
    return true;
}

function validateLinkForm() {
    const mode = document.getElementById('linkModeInput').value;
    const studentId = document.getElementById('selectStudentId').value;

    if (mode === 'existing') {
        const guardianId = document.getElementById('selectGuardianId').value;
        if (!guardianId) {
            triggerToast('Silakan pilih akun wali murid terlebih dahulu.', 'error');
            return false;
        }
    } else {
        const name = document.getElementById('inputNewGuardianName').value.trim();
        const phone = document.getElementById('inputNewGuardianPhone').value.trim();
        if (!name) {
            triggerToast('Nama lengkap wali murid wajib diisi.', 'error');
            return false;
        }
        if (!phone) {
            triggerToast('Nomor WhatsApp wali murid wajib diisi.', 'error');
            return false;
        }
        if (!/^[0-9]+$/.test(phone)) {
            triggerToast('Nomor WhatsApp hanya boleh berisi angka.', 'error');
            return false;
        }
        if (phone.length < 9) {
            triggerToast('Nomor WhatsApp minimal 9 digit angka.', 'error');
            return false;
        }
    }

    if (!studentId) {
        triggerToast('Silakan pilih santri aktif yang akan dihubungkan.', 'error');
        return false;
    }

    return true;
}

// Modal Toggle Status Functions
function openToggleModal(id, name, targetStatus) {
    document.getElementById('toggleGuardianId').value = id;
    document.getElementById('toggleStatusValue').value = targetStatus;

    const iconCont = document.getElementById('toggleIconContainer');
    const icon = document.getElementById('toggleIcon');
    const title = document.getElementById('toggleTitle');
    const desc = document.getElementById('toggleDescription');
    const submitBtn = document.getElementById('toggleSubmitBtn');

    if (targetStatus === 'inactive') {
        iconCont.className = 'w-12 h-12 rounded-full mx-auto mb-4 flex items-center justify-center text-xl bg-rose-50 text-rose-600';
        icon.className = 'fa-solid fa-ban';
        title.innerText = 'Putus Akses Portal?';
        desc.innerHTML = `Akun wali murid <strong>${name}</strong> akan dinonaktifkan. Wali tidak akan dapat masuk ke aplikasi portal sampai diaktifkan kembali.`;
        submitBtn.className = 'px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold shadow-xs cursor-pointer';
        submitBtn.innerText = 'Ya, Putus Akses';
    } else {
        iconCont.className = 'w-12 h-12 rounded-full mx-auto mb-4 flex items-center justify-center text-xl bg-emerald-50 text-emerald-600';
        icon.className = 'fa-solid fa-check';
        title.innerText = 'Aktifkan Kembali Akses?';
        desc.innerHTML = `Akses portal untuk wali murid <strong>${name}</strong> akan dipulihkan sehingga dapat login kembali ke aplikasi.`;
        submitBtn.className = 'px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-xs cursor-pointer';
        submitBtn.innerText = 'Ya, Aktifkan';
    }

    document.getElementById('toggleModal').classList.remove('hidden');
}

function closeToggleModal() {
    document.getElementById('toggleModal').classList.add('hidden');
}

// Modal Unlink Functions
function openUnlinkModal(gid, sid, sname, gname) {
    document.getElementById('unlinkGuardianId').value = gid;
    document.getElementById('unlinkStudentId').value = sid;
    document.getElementById('unlinkStudentName').innerText = sname;
    document.getElementById('unlinkGuardianName').innerText = gname;
    document.getElementById('unlinkModal').classList.remove('hidden');
}

function closeUnlinkModal() {
    document.getElementById('unlinkModal').classList.add('hidden');
}

// Modal Link Manual Functions
function openLinkModal(preselectedGuardianId = null) {
    const modal = document.getElementById('linkModal');
    switchLinkMode('existing');
    
    // Reset filter & pilihan wali murid
    const guardianFilterInput = document.getElementById('guardianSearchFilter');
    if (guardianFilterInput) {
        guardianFilterInput.value = '';
    }
    filterGuardianOptions('');

    if (preselectedGuardianId) {
        const selG = document.getElementById('selectGuardianId');
        if (selG) {
            selG.value = preselectedGuardianId;
        }
    }
    
    // Reset filter santri
    const studentFilterInput = document.getElementById('studentSearchFilter');
    if (studentFilterInput) {
        studentFilterInput.value = '';
    }
    filterStudentOptions('');

    modal.classList.remove('hidden');
}

function closeLinkModal() {
    document.getElementById('linkModal').classList.add('hidden');
}

function switchLinkMode(mode) {
    const tabExisting = document.getElementById('tabModeExisting');
    const tabNew = document.getElementById('tabModeNew');
    const secExisting = document.getElementById('sectionExistingGuardian');
    const secNew = document.getElementById('sectionNewGuardian');
    const modeInput = document.getElementById('linkModeInput');

    if (mode === 'existing') {
        modeInput.value = 'existing';
        tabExisting.className = 'flex-1 py-1.5 rounded-lg bg-white text-cyan-800 shadow-xs transition-all cursor-pointer';
        tabNew.className = 'flex-1 py-1.5 rounded-lg text-slate-500 hover:text-slate-800 transition-all cursor-pointer';
        secExisting.classList.remove('hidden');
        secNew.classList.add('hidden');
    } else {
        modeInput.value = 'new_guardian';
        tabNew.className = 'flex-1 py-1.5 rounded-lg bg-white text-cyan-800 shadow-xs transition-all cursor-pointer';
        tabExisting.className = 'flex-1 py-1.5 rounded-lg text-slate-500 hover:text-slate-800 transition-all cursor-pointer';
        secNew.classList.remove('hidden');
        secExisting.classList.add('hidden');
    }
}

// Live Filter Search for Guardian Select Box
function filterGuardianOptions(keyword) {
    const filter = keyword.toLowerCase().trim();
    const select = document.getElementById('selectGuardianId');
    if (!select) return;
    const options = select.getElementsByTagName('option');
    let firstMatchedValue = null;
    let matchCount = 0;

    for (let i = 0; i < options.length; i++) {
        if (options[i].value === "") {
            options[i].hidden = false;
            options[i].style.display = "";
            continue;
        }
        const text = options[i].textContent || options[i].innerText;
        if (filter === '' || text.toLowerCase().indexOf(filter) > -1) {
            options[i].hidden = false;
            options[i].style.display = "";
            matchCount++;
            if (!firstMatchedValue) {
                firstMatchedValue = options[i].value;
            }
        } else {
            options[i].hidden = true;
            options[i].style.display = "none";
        }
    }

    const countLabel = document.getElementById('guardianCountLabel');
    if (countLabel) {
        if (filter === '') {
            countLabel.innerText = 'Total: ' + (options.length - 1) + ' Wali';
        } else {
            countLabel.innerText = matchCount + ' ditemukan';
        }
    }

    if (filter !== '' && firstMatchedValue) {
        select.value = firstMatchedValue;
    }
}

// Live Filter Search for Student Select Box (Santri Aktif)
function filterStudentOptions(keyword) {
    const filter = keyword.toLowerCase().trim();
    const select = document.getElementById('selectStudentId');
    if (!select) return;
    const options = select.getElementsByTagName('option');

    for (let i = 0; i < options.length; i++) {
        const text = options[i].textContent || options[i].innerText;
        if (filter === '' || text.toLowerCase().indexOf(filter) > -1) {
            options[i].hidden = false;
            options[i].style.display = "";
        } else {
            options[i].hidden = true;
            options[i].style.display = "none";
        }
    }
}
// Modal Edit Guardian Functions
function openEditModal(id, name, phone, relationship, status) {
    document.getElementById('editGuardianId').value = id;
    document.getElementById('editFullName').value = name;
    document.getElementById('editPhoneNumber').value = phone;
    document.getElementById('editRelationship').value = relationship || 'Ayah';
    document.getElementById('editStatus').value = status || 'active';
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

function validateEditForm() {
    const name = document.getElementById('editFullName').value.trim();
    const phone = document.getElementById('editPhoneNumber').value.trim();

    if (!name) {
        triggerToast('Nama lengkap wali murid wajib diisi.', 'error');
        return false;
    }
    if (!phone) {
        triggerToast('Nomor WhatsApp wali murid wajib diisi.', 'error');
        return false;
    }
    if (!/^[0-9]+$/.test(phone)) {
        triggerToast('Nomor WhatsApp hanya boleh berisi angka.', 'error');
        return false;
    }
    if (phone.length < 9) {
        triggerToast('Nomor WhatsApp minimal 9 digit angka.', 'error');
        return false;
    }
    return true;
}

// Modal Delete Guardian Functions
function openDeleteModal(id, name, phone) {
    document.getElementById('deleteGuardianId').value = id;
    document.getElementById('deleteGuardianName').innerText = name;
    document.getElementById('deleteGuardianPhone').innerText = phone;
    document.getElementById('deleteModal').classList.remove('hidden');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}
</script>

<?php include '../layouts/footer.php'; ?>
