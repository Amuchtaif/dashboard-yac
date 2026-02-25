<?php
// views/permissions/user_web_permissions.php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_employees');

$page_title = "Hak Akses Web Spesifik Karyawan";

$db = new Database();
$conn = $db->getConnection();

// --- Pagination Logic ---
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
if (!in_array($limit, [10, 20, 50, 100])) $limit = 10;

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) $page = 1;
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
        -- Position-based (role) permissions for Web
        COALESCE(p.can_manage_employees, 0) as role_manage_employees,
        COALESCE(p.can_manage_academic, 0) as role_manage_academic,
        COALESCE(p.can_manage_tahfidz, 0) as role_manage_tahfidz,
        -- User-specific overrides (NULL = no override)
        up_emp.is_allowed as override_manage_employees,
        up_aca.is_allowed as override_manage_academic,
        up_tah.is_allowed as override_manage_tahfidz
    FROM employees e
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN user_permissions up_emp 
        ON e.id = up_emp.employee_id AND up_emp.permission_name = 'manage_employees'
    LEFT JOIN user_permissions up_aca 
        ON e.id = up_aca.employee_id AND up_aca.permission_name = 'manage_academic'
    LEFT JOIN user_permissions up_tah 
        ON e.id = up_tah.employee_id AND up_tah.permission_name = 'manage_tahfidz'
    WHERE $where_sql
    ORDER BY e.full_name ASC
    LIMIT :limit OFFSET :offset
";

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
    .perm-source { font-size: 10px; line-height: 1; margin-top: 4px; display: block; text-align: center; }
    .perm-source.from-role { color: #64748b; }
    .perm-source.from-override { color: #8b5cf6; font-weight: 600; }
</style>

<div class="pb-10">
    <div class="sm:flex sm:items-center justify-between">
        <div class="sm:flex-auto">
            <h1 class="text-xl font-bold text-slate-900">Hak Akses Web Spesifik</h1>
            <p class="mt-2 text-sm text-slate-500">Kelola izin pengelolaan web khusus per karyawan (Override Jabatan).</p>
        </div>
        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none flex gap-3">
            <a href="user_permissions.php" class="inline-flex items-center justify-center rounded-lg border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:w-auto transition-colors">
                &larr; Akses Spesifik Aplikasi
            </a>
            <a href="web_permissions.php" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 sm:w-auto transition-colors">
                &larr; Kembali ke Jabatan Web
            </a>
        </div>
    </div>

    <!-- Legend -->
    <div class="mt-6 bg-gradient-to-r from-slate-50 to-violet-50 border border-slate-200 rounded-xl p-4">
        <div class="flex flex-wrap gap-4 text-xs text-slate-600">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 text-[10px] font-semibold">
                    Jabatan
                </span>
                <span>= Izin bawaan dari jabatan</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-violet-100 text-violet-700 text-[10px] font-semibold">
                    Override
                </span>
                <span>= Izin khusus per karyawan</span>
            </div>
        </div>
    </div>

    <!-- Actions Bar -->
    <div class="mt-4 bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-4 justify-between items-center">
        <form class="relative w-full sm:w-96" method="GET">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                class="block w-full rounded-lg border-slate-200 pl-4 pt-2 pb-2 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border placeholder:text-slate-400 text-slate-600"
                placeholder="Cari nama karyawan...">
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
                                <th scope="col" class="px-3 py-3.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 w-44">Manajemen Pegawai</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 w-44">Manajemen Akademik</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 w-44">Manajemen Tahfidz</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <?php if (count($employees) > 0): ?>
                                <?php foreach ($employees as $index => $emp): 
                                    $eff_emp = $emp['override_manage_employees'] !== null ? (int)$emp['override_manage_employees'] : (int)$emp['role_manage_employees'];
                                    $src_emp = $emp['override_manage_employees'] !== null ? 'override' : 'role';

                                    $eff_aca = $emp['override_manage_academic'] !== null ? (int)$emp['override_manage_academic'] : (int)$emp['role_manage_academic'];
                                    $src_aca = $emp['override_manage_academic'] !== null ? 'override' : 'role';

                                    $eff_tah = $emp['override_manage_tahfidz'] !== null ? (int)$emp['override_manage_tahfidz'] : (int)$emp['role_manage_tahfidz'];
                                    $src_tah = $emp['override_manage_tahfidz'] !== null ? 'override' : 'role';
                                ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-500 sm:pl-6">
                                            <?php echo $offset + $index + 1; ?>.
                                        </td>
                                        <td class="whitespace-nowrap py-4 pl-4 pr-3 sm:pl-6">
                                            <div class="font-medium text-gray-900"><?php echo htmlspecialchars($emp['full_name']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($emp['email']); ?></div>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                            <?php echo htmlspecialchars($emp['position_name'] ?? '-'); ?>
                                        </td>
                                        
                                        <td class="whitespace-nowrap px-3 py-4 text-center">
                                            <?php renderToggle($emp['id'], 'manage_employees', $eff_emp, $src_emp); ?>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-center">
                                            <?php renderToggle($emp['id'], 'manage_academic', $eff_aca, $src_aca); ?>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-center">
                                            <?php renderToggle($emp['id'], 'manage_tahfidz', $eff_tah, $src_tah); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="py-10 text-center text-sm text-gray-500">Data karyawan tidak ditemukan.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
function renderToggle($empId, $permName, $effectiveValue, $source) {
    $isChecked = ($effectiveValue == 1);
    $isOverride = ($source === 'override');
    $sourceLabel = $isOverride ? 'Override' : 'Jabatan';
    $sourceClass = $isOverride ? 'from-override' : 'from-role';
    $toggleColor = $isOverride ? 'peer-checked:bg-violet-600 peer-focus:ring-violet-300' : 'peer-checked:bg-cyan-600 peer-focus:ring-cyan-300';
?>
    <div class="flex flex-col items-center gap-1">
        <label class="relative inline-flex items-center cursor-pointer justify-center">
            <input type="checkbox" class="sr-only peer" <?php echo $isChecked ? 'checked' : ''; ?>
                onchange="updateUserWebPermission(<?php echo $empId; ?>, '<?php echo $permName; ?>', this.checked, this)">
            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 <?php echo $toggleColor; ?> rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
        </label>
        <span class="perm-source <?php echo $sourceClass; ?>" id="src-<?php echo $empId; ?>-<?php echo $permName; ?>">
            <?php echo $sourceLabel; ?>
        </span>
    </div>
<?php } ?>

<script>
function updateUserWebPermission(empId, permName, isChecked, checkboxEl) {
    const apiUrl = '../../logic/permissions/update_employee_permission.php';
    const sourceEl = document.getElementById(`src-${empId}-${permName}`);
    if (sourceEl) sourceEl.innerHTML = '<span class="text-amber-500 animate-pulse">Menyimpan...</span>';

    fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ employee_id: empId, permission_name: permName, is_allowed: isChecked ? 1 : 0 }),
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', 'Hak akses web berhasil disimpan');
            if (sourceEl) {
                sourceEl.className = 'perm-source from-override';
                sourceEl.innerHTML = 'Override';
            }
            const toggleDiv = checkboxEl.nextElementSibling;
            if (toggleDiv) {
                toggleDiv.className = toggleDiv.className.replace('peer-checked:bg-cyan-600', 'peer-checked:bg-violet-600').replace('peer-focus:ring-cyan-300', 'peer-focus:ring-violet-300');
            }
        } else {
            showNotification('error', 'Gagal: ' + data.message);
            checkboxEl.checked = !isChecked;
        }
    })
    .catch((error) => {
        showNotification('error', 'Terjadi kesalahan');
        checkboxEl.checked = !isChecked;
    });
}

function showNotification(type, message) {
    const existingNotif = document.getElementById('user-perm-notification');
    if (existingNotif) existingNotif.remove();
    const notif = document.createElement('div');
    notif.id = 'user-perm-notification';
    notif.style.cssText = `position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:9999;padding:12px 24px;border-radius:8px;font-family:sans-serif;font-size:14px;font-weight:500;display:flex;align-items:center;gap:10px;box-shadow:0 4px 12px rgba(0,0,0,0.15);`;
    notif.style.backgroundColor = type === 'success' ? '#10B981' : '#EF4444';
    notif.style.color = '#fff';
    notif.innerHTML = `<span>${message}</span>`;
    document.body.appendChild(notif);
    setTimeout(() => notif.remove(), 3000);
}
</script>

<?php include '../layouts/footer.php'; ?>
