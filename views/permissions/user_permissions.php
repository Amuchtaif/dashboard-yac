<?php
// views/permissions/user_permissions.php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$page_title = "Hak Akses Spesifik Karyawan";

$db = new Database();
$conn = $db->getConnection();

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
$where_clauses = ["e.status = 'active'"]; // Only active employees? Or all. Let's filter active by default or just show all. User expects "Search Employee".
// Let's safe filter active usually.
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

// Fetch Employees with Permissions
// Left Join with user_permissions for each type
$query = "
    SELECT 
        e.id, 
        e.full_name, 
        e.email, 
        p.name as position_name,
        up_meet.is_allowed as access_meeting,
        up_tahfidz.is_allowed as access_tahfidz,
        up_attend.is_allowed as access_attendance
    FROM employees e
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN user_permissions up_meet 
        ON e.id = up_meet.employee_id AND up_meet.permission_name = 'access_meeting'
    LEFT JOIN user_permissions up_tahfidz 
        ON e.id = up_tahfidz.employee_id AND up_tahfidz.permission_name = 'access_tahfidz'
    LEFT JOIN user_permissions up_attend 
        ON e.id = up_attend.employee_id AND up_attend.permission_name = 'access_attendance'
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

<div class="pb-10">
    <!-- Header -->
    <div class="sm:flex sm:items-center justify-between">
        <div class="sm:flex-auto">
            <h1 class="text-xl font-bold text-slate-900">Hak Akses Spesifik</h1>
            <p class="mt-2 text-sm text-slate-500">Kelola izin fitur khusus untuk setiap karyawan (Override Jabatan).</p>
        </div>
        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
            <a href="index.php" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 sm:w-auto transition-colors">
                &larr; Kembali ke Jabatan
            </a>
        </div>
    </div>

    <!-- Actions Bar -->
    <div class="mt-8 bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-4 justify-between items-center">
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
                                <th scope="col" class="px-3 py-3.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 w-32">Akses Rapat</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 w-32">Akses Tahfidz</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 w-32">Akses Presensi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <?php if (count($employees) > 0): ?>
                                <?php foreach ($employees as $index => $emp): ?>
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
                                        <td class="whitespace-nowrap px-3 py-4 text-center">
                                            <?php renderCheckbox($emp['id'], 'access_meeting', $emp['access_meeting']); ?>
                                        </td>
                                        
                                        <!-- Access Tahfidz -->
                                        <td class="whitespace-nowrap px-3 py-4 text-center">
                                            <?php renderCheckbox($emp['id'], 'access_tahfidz', $emp['access_tahfidz']); ?>
                                        </td>
                                        
                                        <!-- Access Attendance -->
                                        <td class="whitespace-nowrap px-3 py-4 text-center">
                                            <?php renderCheckbox($emp['id'], 'access_attendance', $emp['access_attendance']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="py-10 text-center text-sm text-gray-500">Data karyawan tidak ditemukan.</td>
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
function renderCheckbox($empId, $permName, $val) {
    // val is 1, 0, or NULL. NULL means no override (default 0).
    $isChecked = ($val == 1);
    ?>
    <label class="relative inline-flex items-center cursor-pointer justify-center">
        <input type="checkbox" class="sr-only peer" 
            <?php echo $isChecked ? 'checked' : ''; ?>
            onchange="updateUserPermission(<?php echo $empId; ?>, '<?php echo $permName; ?>', this.checked)">
        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-cyan-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-cyan-600"></div>
    </label>
    <?php
}
?>

<script>
function updateUserPermission(empId, permName, isChecked) {
    const apiUrl = '../../logic/permissions/update_employee_permission.php';

    fetch(apiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            employee_id: empId,
            permission_name: permName,
            is_allowed: isChecked ? 1 : 0
        }),
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', 'Hak akses berhasil disimpan');
        } else {
            showNotification('error', 'Gagal: ' + (data.message || 'Error permission'));
            // Revert checkbox if needed
        }
    })
    .catch((error) => {
        console.error('Error:', error);
        showNotification('error', 'Terjadi kesalahan koneksi');
    });
}

// Notification Helper (Duplicated from index.php or shared)
function showNotification(type, message) {
    const existingNotif = document.getElementById('user-perm-notification');
    if (existingNotif) existingNotif.remove();
    
    const notif = document.createElement('div');
    notif.id = 'user-perm-notification';
    notif.style.cssText = `
        position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 9999;
        padding: 12px 24px; border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 14px;
        font-weight: 500; display: flex; align-items: center; gap: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;
    
    if (type === 'success') {
        notif.style.backgroundColor = '#10B981';
        notif.style.color = '#fff';
    } else {
        notif.style.backgroundColor = '#EF4444';
        notif.style.color = '#fff';
    }
    
    notif.innerHTML = `<span>${message}</span>`;
    document.body.appendChild(notif);
    
    setTimeout(() => {
        notif.remove();
    }, 3000);
}
</script>

<?php include '../layouts/footer.php'; ?>
