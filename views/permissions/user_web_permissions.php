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
        COALESCE(p.can_manage_boarding, 0) as role_manage_boarding,
        COALESCE(p.can_manage_inventory, 0) as role_manage_inventory,
        -- User-specific overrides (NULL = no override)
        up_emp.is_allowed as override_manage_employees,
        up_aca.is_allowed as override_manage_academic,
        up_tah.is_allowed as override_manage_tahfidz,
        up_boa.is_allowed as override_manage_boarding,
        up_inv.is_allowed as override_manage_inventory
    FROM employees e
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN user_permissions up_emp 
        ON e.id = up_emp.employee_id AND up_emp.permission_name = 'manage_employees'
    LEFT JOIN user_permissions up_aca 
        ON e.id = up_aca.employee_id AND up_aca.permission_name = 'manage_academic'
    LEFT JOIN user_permissions up_tah 
        ON e.id = up_tah.employee_id AND up_tah.permission_name = 'manage_tahfidz'
    LEFT JOIN user_permissions up_boa 
        ON e.id = up_boa.employee_id AND up_boa.permission_name = 'manage_boarding'
    LEFT JOIN user_permissions up_inv 
        ON e.id = up_inv.employee_id AND up_inv.permission_name = 'manage_inventory'
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
    .perm-source {
        font-size: 9px;
        line-height: 1;
        margin-top: 4px;
        display: block;
        text-align: center;
        letter-spacing: 0.02em;
    }
    .perm-source.from-role {
        color: #94a3b8;
    }
    .perm-source.from-override {
        color: #8b5cf6;
        font-weight: 700;
        text-transform: uppercase;
    }
    .sticky-col {
        position: sticky;
        left: 0;
        background-color: white !important;
        z-index: 10;
    }
    .permissions-table-container {
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
    }
    .permissions-table-container::-webkit-scrollbar {
        height: 6px;
    }
    .permissions-table-container::-webkit-scrollbar-track {
        background: transparent;
    }
    .permissions-table-container::-webkit-scrollbar-thumb {
        background-color: #cbd5e1;
        border-radius: 20px;
    }
    .group-header {
        background-color: #f8fafc;
        border-bottom: 2px solid #e2e8f0;
        text-align: center;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 0.05em;
        color: #64748b;
        padding: 6px 0;
        text-transform: uppercase;
    }
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
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-200 text-slate-600 text-[10px] font-semibold">
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
        <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8 permissions-table-container">
            <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
                <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 border-separate border-spacing-0">
                        <thead>
                            <!-- Category Row -->
                            <tr>
                                <th colspan="3" class="bg-slate-50 border-b border-slate-200 sticky-col z-20"></th>
                                <th colspan="5" class="group-header border-l border-slate-200 bg-indigo-50/30 text-indigo-700">Manajemen Dashboard (Override)</th>
                            </tr>
                            <tr class="bg-slate-50/80 backdrop-blur-sm">
                                <th scope="col" class="sticky-col py-3.5 pl-4 pr-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500 sm:pl-6 border-b border-slate-200">No.</th>
                                <th scope="col" class="sticky-col py-3.5 pl-4 pr-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500 sm:pl-6 min-w-[200px] border-b border-slate-200 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.05)]">Nama Karyawan</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">Jabatan</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-[10px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200 border-l border-slate-100">Manajemen Pegawai</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-[10px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">Manajemen Akademik</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-[10px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">Manajemen Tahfidz</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-[10px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">Kepengasuhan</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-[10px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">Manajemen Inventaris</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <?php if (count($employees) > 0): ?>
                                <?php foreach ($employees as $index => $emp): 
                                    $p_list = [
                                        ['name' => 'manage_employees', 'eff' => ($emp['override_manage_employees'] !== null ? (int)$emp['override_manage_employees'] : (int)$emp['role_manage_employees']), 'src' => ($emp['override_manage_employees'] !== null && (int)$emp['override_manage_employees'] !== (int)$emp['role_manage_employees'] ? 'override' : 'role'), 'role' => (int)$emp['role_manage_employees']],
                                        ['name' => 'manage_academic',  'eff' => ($emp['override_manage_academic'] !== null ? (int)$emp['override_manage_academic'] : (int)$emp['role_manage_academic']), 'src' => ($emp['override_manage_academic'] !== null && (int)$emp['override_manage_academic'] !== (int)$emp['role_manage_academic'] ? 'override' : 'role'), 'role' => (int)$emp['role_manage_academic']],
                                        ['name' => 'manage_tahfidz',   'eff' => ($emp['override_manage_tahfidz'] !== null ? (int)$emp['override_manage_tahfidz'] : (int)$emp['role_manage_tahfidz']), 'src' => ($emp['override_manage_tahfidz'] !== null && (int)$emp['override_manage_tahfidz'] !== (int)$emp['role_manage_tahfidz'] ? 'override' : 'role'), 'role' => (int)$emp['role_manage_tahfidz']],
                                        ['name' => 'manage_boarding',  'eff' => ($emp['override_manage_boarding'] !== null ? (int)$emp['override_manage_boarding'] : (int)$emp['role_manage_boarding']), 'src' => ($emp['override_manage_boarding'] !== null && (int)$emp['override_manage_boarding'] !== (int)$emp['role_manage_boarding'] ? 'override' : 'role'), 'role' => (int)$emp['role_manage_boarding']],
                                        ['name' => 'manage_inventory', 'eff' => ($emp['override_manage_inventory'] !== null ? (int)$emp['override_manage_inventory'] : (int)$emp['role_manage_inventory']), 'src' => ($emp['override_manage_inventory'] !== null && (int)$emp['override_manage_inventory'] !== (int)$emp['role_manage_inventory'] ? 'override' : 'role'), 'role' => (int)$emp['role_manage_inventory']]
                                    ];
                                ?>
                                    <tr class="hover:bg-slate-50 transition-colors group">
                                        <td class="sticky-col whitespace-nowrap py-4 pl-4 pr-3 text-xs text-slate-400 sm:pl-6 border-slate-100">
                                            <?php echo $offset + $index + 1; ?>.
                                        </td>
                                        <td class="sticky-col whitespace-nowrap py-4 pl-4 pr-3 sm:pl-6 border-slate-100 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.05)]">
                                            <div class="flex items-center">
                                                <div class="h-8 w-8 flex-shrink-0">
                                                    <img class="h-8 w-8 rounded-full ring-1 ring-slate-200"
                                                        src="https://ui-avatars.com/api/?name=<?php echo urlencode($emp['full_name']); ?>&background=random" alt="">
                                                </div>
                                                <div class="ml-3">
                                                    <div class="text-[11px] font-bold text-slate-700 group-hover:text-violet-700 transition-colors"><?php echo htmlspecialchars($emp['full_name']); ?></div>
                                                    <div class="text-[9px] text-slate-400"><?php echo htmlspecialchars($emp['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-[10px] text-slate-500 text-xs font-medium">
                                            <?php echo htmlspecialchars($emp['position_name'] ?? '-'); ?>
                                        </td>
                                        
                                        <?php foreach($p_list as $p): ?>
                                        <td class="whitespace-nowrap px-3 py-4 text-center">
                                            <?php renderToggle($emp['id'], $p['name'], $p['eff'], $p['src'], $p['role']); ?>
                                        </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="py-10 text-center text-sm text-slate-500 italic">Data karyawan tidak ditemukan.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
    $toggleColor = $isOverride ? 'peer-checked:bg-violet-600 peer-focus:ring-violet-300' : 'peer-checked:bg-cyan-600 peer-focus:ring-cyan-300';
?>
    <div class="flex flex-col items-center gap-1">
        <label class="relative inline-flex items-center cursor-pointer justify-center">
            <input type="checkbox" class="sr-only peer" <?php echo $isChecked ? 'checked' : ''; ?>
                onchange="updateUserWebPermission(<?php echo $empId; ?>, '<?php echo $permName; ?>', this.checked, this)"
                data-role-value="<?php echo $roleValue; ?>">
            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none <?php echo $toggleColor; ?> rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
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

    const roleValue = parseInt(checkboxEl.getAttribute('data-role-value') || '0');
    const isMatchingRole = (isChecked ? 1 : 0) === roleValue;

    fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            employee_id: empId, 
            permission_name: permName, 
            is_allowed: isChecked ? 1 : 0,
            revert_to_role: isMatchingRole
        }),
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', 'Hak akses web berhasil disimpan');
            if (sourceEl) {
                if (isMatchingRole) {
                    sourceEl.className = 'perm-source from-role';
                    sourceEl.innerHTML = 'Jabatan';
                } else {
                    sourceEl.className = 'perm-source from-override';
                    sourceEl.innerHTML = 'Override';
                }
            }
            const toggleDiv = checkboxEl.nextElementSibling;
            if (toggleDiv) {
                if (isMatchingRole) {
                    toggleDiv.className = toggleDiv.className.replace('peer-checked:bg-violet-600', 'peer-checked:bg-cyan-600');
                } else {
                    toggleDiv.className = toggleDiv.className.replace('peer-checked:bg-cyan-600', 'peer-checked:bg-violet-600');
                }
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
    notif.style.cssText = `position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:9999;padding:12px 24px;border-radius:8px;font-family:sans-serif;font-size:14px;font-weight:500;display:flex;align-items:center;gap:10px;box-shadow: 0 4px 12px rgba(0,0,0,0.15);animation: slideDown 0.3s ease-out;`;
    notif.style.backgroundColor = type === 'success' ? '#10B981' : '#EF4444';
    notif.style.color = '#fff';
    notif.innerHTML = `<span>${message}</span>`;
    document.body.appendChild(notif);
    setTimeout(() => notif.remove(), 3000);
}
</script>

<style>
@keyframes slideDown { from { opacity: 0; transform: translateX(-50%) translateY(-20px); } to { opacity: 1; transform: translateX(-50%) translateY(0); } }
</style>

<?php include '../layouts/footer.php'; ?>
