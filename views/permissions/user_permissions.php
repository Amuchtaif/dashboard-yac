<?php
// views/permissions/user_permissions.php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_employees');

$page_title = "Hak Akses Spesifik Karyawan";

$db = new Database();
$conn = $db->getConnection();

// Auto-migration: Ensure user_permissions table exists
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS `user_permissions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `employee_id` INT NOT NULL,
        `permission_name` VARCHAR(100) NOT NULL,
        `is_allowed` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_employee_permission` (`employee_id`, `permission_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // Table might already exist
}

// --- Pagination Logic ---
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
if (!in_array($limit, [10, 20, 50, 100]))
    $limit = 10;

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $limit;

// --- Search Logic ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clauses = ["1=1"];
$params = [];

if ($search) {
    $where_clauses[] = "(e.full_name LIKE :search OR e.email LIKE :search)";
    $params[':search'] = "%$search%";
}

$where_sql = implode(" AND ", $where_clauses);

// Total Rows
$count_query = "SELECT COUNT(*) FROM employees e WHERE $where_sql";
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute($params);
$total_rows = $count_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Fetch Employees with their position-based permissions AND user-specific overrides
$query = "
    SELECT 
        e.id, 
        e.full_name, 
        e.email, 
        p.name as position_name,
        -- Position-based (role) permissions
        COALESCE(p.can_create_meeting, 0) as role_meeting,
        COALESCE(p.can_approve_permits, 0) as role_permits,
        COALESCE(p.can_access_tahfidz, 0) as role_tahfidz,
        COALESCE(p.can_access_education, 0) as role_education,
        COALESCE(p.can_manage_news, 0) as role_news,
        COALESCE(p.can_manage_assignments, 0) as role_assignments,
        -- User-specific overrides (NULL = no override)
        up_meet.is_allowed as override_meeting,
        up_permits.is_allowed as override_permits,
        up_tahfidz.is_allowed as override_tahfidz,
        up_attend.is_allowed as override_attendance,
        up_edu.is_allowed as override_education,
        up_news.is_allowed as override_news,
        up_asn.is_allowed as override_assignments
    FROM employees e
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN user_permissions up_meet 
        ON e.id = up_meet.employee_id AND up_meet.permission_name = 'access_meeting'
    LEFT JOIN user_permissions up_permits 
        ON e.id = up_permits.employee_id AND up_permits.permission_name = 'approve_permits'
    LEFT JOIN user_permissions up_tahfidz 
        ON e.id = up_tahfidz.employee_id AND up_tahfidz.permission_name = 'access_tahfidz'
    LEFT JOIN user_permissions up_attend 
        ON e.id = up_attend.employee_id AND up_attend.permission_name = 'access_attendance'
    LEFT JOIN user_permissions up_edu 
        ON e.id = up_edu.employee_id AND up_edu.permission_name = 'access_education'
    LEFT JOIN user_permissions up_news 
        ON e.id = up_news.employee_id AND up_news.permission_name = 'manage_news'
    LEFT JOIN user_permissions up_asn 
        ON e.id = up_asn.employee_id AND up_asn.permission_name = 'manage_assignments'
    WHERE $where_sql
    ORDER BY e.full_name ASC
    LIMIT :limit OFFSET :offset
";

// Bind params
$stmt = $conn->prepare($query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<style>
    .perm-cell {
        position: relative;
    }
    .perm-source {
        font-size: 10px;
        line-height: 1;
        margin-top: 4px;
        display: block;
        text-align: center;
    }
    .perm-source.from-role {
        color: #64748b;
    }
    .perm-source.from-override {
        color: #8b5cf6;
        font-weight: 600;
    }
    .perm-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 8px;
        border-radius: 9999px;
        font-size: 10px;
        font-weight: 600;
        letter-spacing: 0.025em;
    }
    .perm-badge.active {
        background-color: #ecfdf5;
        color: #047857;
        ring: 1px solid #a7f3d0;
    }
    .perm-badge.inactive {
        background-color: #fef2f2;
        color: #b91c1c;
    }
</style>

<div class="pb-10">
    <!-- Header -->
    <div class="sm:flex sm:items-center justify-between">
        <div class="sm:flex-auto">
            <h1 class="text-xl font-bold text-slate-900">Hak Akses Spesifik</h1>
            <p class="mt-2 text-sm text-slate-500">Kelola izin fitur khusus untuk setiap karyawan (Override Jabatan).</p>
        </div>
        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none flex gap-3">
            <a href="user_web_permissions.php" class="inline-flex items-center justify-center rounded-lg border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:w-auto transition-colors">
                Akses Spesifik Web &rarr;
            </a>
            <a href="index.php" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 sm:w-auto transition-colors">
                &larr; Kembali ke Jabatan
            </a>
        </div>
    </div>

    <!-- Legend -->
    <div class="mt-6 bg-gradient-to-r from-slate-50 to-violet-50 border border-slate-200 rounded-xl p-4">
        <h3 class="text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Keterangan Prioritas:</h3>
        <div class="flex flex-wrap gap-4 text-xs text-slate-600">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 text-[10px] font-semibold">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    Jabatan
                </span>
                <span>= Izin bawaan dari jabatan</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-violet-100 text-violet-700 text-[10px] font-semibold">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Override
                </span>
                <span>= Izin khusus per karyawan (prioritas tertinggi)</span>
            </div>
        </div>
    </div>

    <!-- Actions Bar -->
    <div class="mt-4 bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-4 justify-between items-center">
        <!-- Search -->
        <form class="relative w-full sm:w-96" method="GET">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <svg class="h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
            </div>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                class="block w-full rounded-lg border-slate-200 pl-10 pt-2 pb-2 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border placeholder:text-slate-400 text-slate-600"
                placeholder="Cari nama karyawan..." onchange="this.form.submit()">
        </form>
    </div>

    <!-- Table -->
    <div class="mt-6 flex flex-col">
        <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
                <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:pl-6 w-12">No.</th>
                                <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:pl-6">Nama Karyawan</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Jabatan</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 w-36">Akses Buat Rapat</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 w-36">Akses Persetujuan Izin</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 w-36">Akses Menu Tahfidz</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 w-36">Akses Menu Pendidikan</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 w-36">Manajemen Berita</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 w-36">Akses Penugasan</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 w-36">Akses Presensi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <?php if (count($employees) > 0): ?>
                                <?php foreach ($employees as $index => $emp): 
                                    // Calculate effective permissions (override > role)
                                    // Source is only 'override' when the override value DIFFERS from role value
                                    $eff_meeting = $emp['override_meeting'] !== null ? (int)$emp['override_meeting'] : (int)$emp['role_meeting'];
                                    $src_meeting = ($emp['override_meeting'] !== null && (int)$emp['override_meeting'] !== (int)$emp['role_meeting']) ? 'override' : 'role';

                                    $eff_permits = $emp['override_permits'] !== null ? (int)$emp['override_permits'] : (int)$emp['role_permits'];
                                    $src_permits = ($emp['override_permits'] !== null && (int)$emp['override_permits'] !== (int)$emp['role_permits']) ? 'override' : 'role';

                                    $eff_tahfidz = $emp['override_tahfidz'] !== null ? (int)$emp['override_tahfidz'] : (int)$emp['role_tahfidz'];
                                    $src_tahfidz = ($emp['override_tahfidz'] !== null && (int)$emp['override_tahfidz'] !== (int)$emp['role_tahfidz']) ? 'override' : 'role';

                                    $eff_education = $emp['override_education'] !== null ? (int)$emp['override_education'] : (int)$emp['role_education'];
                                    $src_education = ($emp['override_education'] !== null && (int)$emp['override_education'] !== (int)$emp['role_education']) ? 'override' : 'role';
                                    
                                    $eff_news = $emp['override_news'] !== null ? (int)$emp['override_news'] : (int)$emp['role_news'];
                                    $src_news = ($emp['override_news'] !== null && (int)$emp['override_news'] !== (int)$emp['role_news']) ? 'override' : 'role';

                                    $eff_assignments = $emp['override_assignments'] !== null ? (int)$emp['override_assignments'] : (int)$emp['role_assignments'];
                                    $src_assignments = ($emp['override_assignments'] !== null && (int)$emp['override_assignments'] !== (int)$emp['role_assignments']) ? 'override' : 'role';

                                    $eff_attendance = $emp['override_attendance'] !== null ? (int)$emp['override_attendance'] : 0;
                                    $src_attendance = ($emp['override_attendance'] !== null && (int)$emp['override_attendance'] !== 0) ? 'override' : 'role';
                                ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-500 sm:pl-6">
                                            <?php echo $offset + $index + 1; ?>.
                                        </td>
                                        <td class="whitespace-nowrap py-4 pl-4 pr-3 sm:pl-6">
                                            <div class="flex items-center">
                                                <div class="h-9 w-9 flex-shrink-0">
                                                    <img class="h-9 w-9 rounded-full border border-gray-100"
                                                        src="https://ui-avatars.com/api/?name=<?php echo urlencode($emp['full_name']); ?>&background=random" alt="">
                                                </div>
                                                <div class="ml-4">
                                                    <div class="font-medium text-gray-900"><?php echo htmlspecialchars($emp['full_name']); ?></div>
                                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($emp['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                            <?php echo htmlspecialchars($emp['position_name'] ?? '-'); ?>
                                        </td>
                                        
                                        <!-- Access Meeting -->
                                        <td class="whitespace-nowrap px-3 py-4 text-center perm-cell">
                                            <?php renderToggle($emp['id'], 'access_meeting', $eff_meeting, $src_meeting, $emp['role_meeting']); ?>
                                        </td>
                                        
                                        <!-- Access Permits -->
                                        <td class="whitespace-nowrap px-3 py-4 text-center perm-cell">
                                            <?php renderToggle($emp['id'], 'approve_permits', $eff_permits, $src_permits, $emp['role_permits']); ?>
                                        </td>
                                        
                                        <!-- Access Tahfidz -->
                                        <td class="whitespace-nowrap px-3 py-4 text-center perm-cell">
                                            <?php renderToggle($emp['id'], 'access_tahfidz', $eff_tahfidz, $src_tahfidz, $emp['role_tahfidz']); ?>
                                        </td>

                                        <!-- Access Education -->
                                        <td class="whitespace-nowrap px-3 py-4 text-center perm-cell">
                                            <?php renderToggle($emp['id'], 'access_education', $eff_education, $src_education, $emp['role_education']); ?>
                                        </td>

                                        <!-- Manajemen Berita -->
                                        <td class="whitespace-nowrap px-3 py-4 text-center perm-cell">
                                            <?php renderToggle($emp['id'], 'manage_news', $eff_news, $src_news, $emp['role_news']); ?>
                                        </td>

                                        <!-- Akses Penugasan -->
                                        <td class="whitespace-nowrap px-3 py-4 text-center perm-cell">
                                            <?php renderToggle($emp['id'], 'manage_assignments', $eff_assignments, $src_assignments, $emp['role_assignments']); ?>
                                        </td>
                                        
                                        <!-- Access Attendance -->
                                        <td class="whitespace-nowrap px-3 py-4 text-center perm-cell">
                                            <?php renderToggle($emp['id'], 'access_attendance', $eff_attendance, $src_attendance, 0); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="py-10 text-center text-sm text-gray-500">Data karyawan tidak ditemukan.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="mt-4 flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 rounded-lg shadow-sm">
                    <div class="flex flex-1 justify-between sm:hidden">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</a>
                        <?php endif; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</a>
                        <?php endif; ?>
                    </div>
                    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Menampilkan <span class="font-medium"><?php echo ($total_rows > 0) ? $offset + 1 : 0; ?></span> sampai <span class="font-medium"><?php echo min($offset + $limit, $total_rows); ?></span> dari <span class="font-medium"><?php echo $total_rows; ?></span> hasil
                            </p>
                        </div>
                        <div>
                            <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                                        <span class="sr-only">Previous</span>
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" /></svg>
                                    </a>
                                <?php endif; ?>
                                <span class="relative z-10 inline-flex items-center bg-cyan-600 px-4 py-2 text-sm font-semibold text-white"><?php echo $page; ?></span>
                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                                        <span class="sr-only">Next</span>
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>
                                    </a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
function renderToggle($empId, $permName, $effectiveValue, $source, $roleValue = 0) {
    $isChecked = ($effectiveValue == 1);
    $isOverride = ($source === 'override');
    
    $sourceLabel = $isOverride ? 'Override' : 'Jabatan';
    $sourceClass = $isOverride ? 'from-override' : 'from-role';
    $sourceIcon = $isOverride 
        ? '<svg class="w-3 h-3 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>'
        : '<svg class="w-3 h-3 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>';
    
    $toggleColor = $isOverride ? 'peer-checked:bg-violet-600 peer-focus:ring-violet-300' : 'peer-checked:bg-cyan-600 peer-focus:ring-cyan-300';
    ?>
    <div class="flex flex-col items-center gap-1">
        <label class="relative inline-flex items-center cursor-pointer justify-center">
            <input type="checkbox" class="sr-only peer" 
                <?php echo $isChecked ? 'checked' : ''; ?>
                onchange="updateUserPermission(<?php echo $empId; ?>, '<?php echo $permName; ?>', this.checked, this)"
                data-role-value="<?php echo $roleValue; ?>"
                data-source="<?php echo $source; ?>"
                data-emp-id="<?php echo $empId; ?>"
                data-perm-name="<?php echo $permName; ?>">
            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 <?php echo $toggleColor; ?> rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
        </label>
        <span class="perm-source <?php echo $sourceClass; ?>" id="src-<?php echo $empId; ?>-<?php echo $permName; ?>">
            <?php echo $sourceIcon; ?> <?php echo $sourceLabel; ?>
        </span>
    </div>
    <?php
}
?>

<script>
function updateUserPermission(empId, permName, isChecked, checkboxEl) {
    const apiUrl = '../../logic/permissions/update_employee_permission.php';
    const sourceEl = document.getElementById(`src-${empId}-${permName}`);
    
    // Show saving state
    if (sourceEl) {
        sourceEl.innerHTML = '<span class="text-amber-500 animate-pulse">Menyimpan...</span>';
    }

    const roleValue = parseInt(checkboxEl.getAttribute('data-role-value') || '0');
    const isMatchingRole = (isChecked ? 1 : 0) === roleValue;

    fetch(apiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            employee_id: empId,
            permission_name: permName,
            is_allowed: isChecked ? 1 : 0,
            revert_to_role: isMatchingRole // Added this flag
        }),
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', 'Hak akses berhasil disimpan');
            // Update source indicator based on whether it matches role
            if (sourceEl) {
                if (isMatchingRole) {
                    sourceEl.className = 'perm-source from-role';
                    sourceEl.innerHTML = '<svg class="w-3 h-3 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg> Jabatan';
                } else {
                    sourceEl.className = 'perm-source from-override';
                    sourceEl.innerHTML = '<svg class="w-3 h-3 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg> Override';
                }
            }
            // Update toggle color
            const toggleDiv = checkboxEl.nextElementSibling;
            if (toggleDiv) {
                if (isMatchingRole) {
                    toggleDiv.className = toggleDiv.className
                        .replace('peer-checked:bg-violet-600', 'peer-checked:bg-cyan-600')
                        .replace('peer-focus:ring-violet-300', 'peer-focus:ring-cyan-300');
                } else {
                    toggleDiv.className = toggleDiv.className
                        .replace('peer-checked:bg-cyan-600', 'peer-checked:bg-violet-600')
                        .replace('peer-focus:ring-cyan-300', 'peer-focus:ring-violet-300');
                }
            }
        } else {
            showNotification('error', 'Gagal: ' + (data.message || 'Error permission'));
            // Revert checkbox
            checkboxEl.checked = !isChecked;
        }
    })
    .catch((error) => {
        console.error('Error:', error);
        showNotification('error', 'Terjadi kesalahan koneksi');
        checkboxEl.checked = !isChecked;
        if (sourceEl) {
            sourceEl.innerHTML = 'Error';
        }
    });
}

// Notification Helper
function showNotification(type, message) {
    const existingNotif = document.getElementById('user-perm-notification');
    if (existingNotif) existingNotif.remove();
    
    const notif = document.createElement('div');
    notif.id = 'user-perm-notification';
    notif.style.cssText = `
        position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 9999;
        padding: 12px 24px; border-radius: 8px; font-family: 'Outfit', sans-serif; font-size: 14px;
        font-weight: 500; display: flex; align-items: center; gap: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        animation: slideDown 0.3s ease-out;
    `;
    
    if (type === 'success') {
        notif.style.backgroundColor = '#10B981';
        notif.style.color = '#fff';
        notif.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
            </svg>
            <span>${message}</span>
        `;
    } else {
        notif.style.backgroundColor = '#EF4444';
        notif.style.color = '#fff';
        notif.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
            <span>${message}</span>
        `;
    }
    
    // Add animation
    if (!document.getElementById('notif-animation-style')) {
        const style = document.createElement('style');
        style.id = 'notif-animation-style';
        style.textContent = `
            @keyframes slideDown {
                from { opacity: 0; transform: translateX(-50%) translateY(-20px); }
                to { opacity: 1; transform: translateX(-50%) translateY(0); }
            }
            @keyframes slideUp {
                from { opacity: 1; transform: translateX(-50%) translateY(0); }
                to { opacity: 0; transform: translateX(-50%) translateY(-20px); }
            }
        `;
        document.head.appendChild(style);
    }
    
    document.body.appendChild(notif);
    
    setTimeout(() => {
        notif.style.animation = 'slideUp 0.3s ease-out forwards';
        setTimeout(() => notif.remove(), 300);
    }, 3000);
}
</script>

<?php include '../layouts/footer.php'; ?>
