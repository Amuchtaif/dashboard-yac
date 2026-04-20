<?php
// views/permissions/web_permissions.php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_employees');

$page_title = "Manajemen Hak Akses Web";

$db = new Database();
$conn = $db->getConnection();

// --- Auto-migration: Ensure web permission columns exist ---
$web_permissions = [
    'can_manage_employees' => 'can_access_education', // after this column
    'can_manage_academic' => 'can_manage_employees',
    'can_manage_tahfidz' => 'can_manage_academic',
    'can_manage_boarding' => 'can_manage_tahfidz',
    'can_manage_inventory' => 'can_manage_boarding'
];

foreach ($web_permissions as $column => $after) {
    try {
        $checkColumn = $conn->query("SHOW COLUMNS FROM positions LIKE '$column'");
        if ($checkColumn->rowCount() === 0) {
            $conn->exec("ALTER TABLE `positions` ADD COLUMN `$column` TINYINT(1) NOT NULL DEFAULT 0 AFTER `$after`");
        }
    } catch (Exception $e) {
        // Continue if column exists or error
    }
}

// --- Pagination Logic ---
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
if (!in_array($limit, [10, 20, 50, 100])) $limit = 10;

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$total_rows = $conn->query("SELECT COUNT(*) FROM positions")->fetchColumn();
$total_pages = ceil($total_rows / $limit);

$query = "
    SELECT 
        id, name, level,
        can_manage_employees,
        can_manage_academic,
        can_manage_tahfidz,
        can_manage_boarding,
        can_manage_inventory
    FROM positions
    ORDER BY level ASC, name ASC
    LIMIT :limit OFFSET :offset
";
$stmt = $conn->prepare($query);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$positions = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<style>
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
            <h1 class="text-xl font-bold text-slate-900">Manajemen Hak Akses Web</h1>
            <p class="mt-2 text-sm text-slate-500">Atur hak akses pengelolaan fitur web untuk setiap jabatan.</p>
        </div>
        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none flex gap-3">
            <a href="index.php" class="inline-flex items-center justify-center rounded-lg border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:w-auto transition-colors">
                &larr; Hak Akses Aplikasi
            </a>
            <a href="user_web_permissions.php" class="inline-flex items-center justify-center rounded-lg border border-transparent bg-cyan-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 sm:w-auto transition-colors">
                Kelola Akses Spesifik Karyawan &rarr;
            </a>
        </div>
    </div>

    <!-- Table -->
    <div class="mt-8 flex flex-col">
        <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8 permissions-table-container">
            <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
                <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 border-separate border-spacing-0">
                        <thead>
                            <!-- Category Row -->
                            <tr>
                                <th colspan="3" class="bg-slate-50 border-b border-slate-200 sticky-col z-20"></th>
                                <th colspan="5" class="group-header border-l border-slate-200 bg-indigo-50/30 text-indigo-700">Manajemen Dashboard (Web CMS)</th>
                            </tr>
                            <tr class="bg-slate-50/80 backdrop-blur-sm">
                                <th scope="col" class="sticky-col py-3.5 pl-4 pr-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500 sm:pl-6 w-12 border-b border-slate-200">No.</th>
                                <th scope="col" class="sticky-col py-3.5 pl-4 pr-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500 sm:pl-6 min-w-[200px] border-b border-slate-200 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.05)]">Jabatan</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">Level</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-[10px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200 border-l border-slate-100">Manajemen Pegawai</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-[10px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">Manajemen Akademik</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-[10px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">Manajemen Tahfidz</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-[10px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">Kepengasuhan</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-[10px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">Manajemen Inventaris</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <?php if (count($positions) > 0): ?>
                                <?php foreach ($positions as $index => $pos): ?>
                                    <tr class="hover:bg-slate-50 transition-colors group">
                                        <td class="sticky-col whitespace-nowrap py-4 pl-4 pr-3 text-xs text-slate-400 sm:pl-6 border-slate-100">
                                            <?php echo $offset + $index + 1; ?>.
                                        </td>
                                        <td class="sticky-col whitespace-nowrap py-4 pl-4 pr-3 sm:pl-6 border-slate-100 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.05)]">
                                            <span class="text-sm font-bold text-slate-700 group-hover:text-cyan-700 transition-colors"><?php echo htmlspecialchars($pos['name']); ?></span>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-xs text-slate-500">
                                            <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 border border-slate-200">L<?php echo $pos['level']; ?></span>
                                        </td>
                                        <!-- Management Toggles -->
                                        <td class="whitespace-nowrap px-3 py-4 text-center">
                                            <div class="flex justify-center">
                                                <label class="relative inline-flex items-center cursor-pointer">
                                                    <input type="checkbox" class="sr-only peer" 
                                                        <?php echo $pos['can_manage_employees'] == 1 ? 'checked' : ''; ?>
                                                        onchange="updateWebPermission(<?php echo $pos['id']; ?>, 'can_manage_employees', this.checked)">
                                                    <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-cyan-600"></div>
                                                </label>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-center">
                                            <div class="flex justify-center">
                                                <label class="relative inline-flex items-center cursor-pointer">
                                                    <input type="checkbox" class="sr-only peer" 
                                                        <?php echo $pos['can_manage_academic'] == 1 ? 'checked' : ''; ?>
                                                        onchange="updateWebPermission(<?php echo $pos['id']; ?>, 'can_manage_academic', this.checked)">
                                                    <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-600"></div>
                                                </label>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-center">
                                            <div class="flex justify-center">
                                                <label class="relative inline-flex items-center cursor-pointer">
                                                    <input type="checkbox" class="sr-only peer" 
                                                        <?php echo $pos['can_manage_tahfidz'] == 1 ? 'checked' : ''; ?>
                                                        onchange="updateWebPermission(<?php echo $pos['id']; ?>, 'can_manage_tahfidz', this.checked)">
                                                    <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                                                </label>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-center">
                                            <div class="flex justify-center">
                                                <label class="relative inline-flex items-center cursor-pointer">
                                                    <input type="checkbox" class="sr-only peer" 
                                                        <?php echo $pos['can_manage_boarding'] == 1 ? 'checked' : ''; ?>
                                                        onchange="updateWebPermission(<?php echo $pos['id']; ?>, 'can_manage_boarding', this.checked)">
                                                    <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-rose-600"></div>
                                                </label>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-center">
                                            <div class="flex justify-center">
                                                <label class="relative inline-flex items-center cursor-pointer">
                                                    <input type="checkbox" class="sr-only peer" 
                                                        <?php echo $pos['can_manage_inventory'] == 1 ? 'checked' : ''; ?>
                                                        onchange="updateWebPermission(<?php echo $pos['id']; ?>, 'can_manage_inventory', this.checked)">
                                                    <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-amber-600"></div>
                                                </label>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="py-10 text-center text-sm text-slate-500 italic">Data jabatan tidak ditemukan.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div class="mt-4 flex items-center justify-between border-t border-slate-200 bg-white px-4 py-3 sm:px-6 rounded-lg shadow-sm">
                        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                            <div class="flex items-center gap-4">
                                <select onchange="window.location.href='?page=1&limit='+this.value"
                                    class="block rounded-md border-0 py-1 pl-2 pr-8 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-cyan-600 text-[10px] leading-6">
                                    <?php foreach ([10, 20, 50, 100] as $val): ?>
                                        <option value="<?php echo $val; ?>" <?php echo $limit == $val ? 'selected' : ''; ?>>Tampilkan <?php echo $val; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-xs text-slate-600">
                                    Menampilkan <span class="font-medium text-slate-900"><?php echo ($total_rows > 0) ? $offset + 1 : 0; ?></span> -
                                    <span class="font-medium text-slate-900"><?php echo min($offset + $limit, $total_rows); ?></span> dari
                                    <span class="font-medium text-slate-900"><?php echo $total_rows; ?></span>
                                </p>
                            </div>
                            <div>
                                <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                                    <?php if ($page > 1): ?>
                                        <a href="?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50">
                                            <span class="sr-only">Previous</span>
                                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                                            </svg>
                                        </a>
                                    <?php endif; ?>
                                    <span class="relative z-10 inline-flex items-center bg-cyan-600 px-4 py-2 text-xs font-semibold text-white focus:z-20"><?php echo $page; ?></span>
                                    <?php if ($page < $total_pages): ?>
                                        <a href="?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50">
                                            <span class="sr-only">Next</span>
                                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                                            </svg>
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
</div>

<script>
function updateWebPermission(id, permissionType, isChecked) {
    const apiUrl = '../../logic/permissions/update_role_permission';
    const checkbox = event.target;
    
    fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id: id,
            permission_type: permissionType,
            value: isChecked ? 1 : 0
        }),
    })
    .then(response => {
        const clonedResponse = response.clone();
        return response.json().catch(() => {
            return clonedResponse.text().then(text => {
                throw new Error('Server response: ' + text.substring(0, 200));
            });
        });
    })
    .then(data => {
        if (data.success) {
            showNotification('success', 'Hak akses web berhasil diperbarui');
        } else {
            if (checkbox) checkbox.checked = !isChecked;
            showNotification('error', 'Gagal: ' + data.message);
        }
    })
    .catch((error) => {
        if (checkbox) checkbox.checked = !isChecked;
        showNotification('error', 'Error: ' + error.message);
    });
}

function showNotification(type, message) {
    const existingNotif = document.getElementById('permission-notification');
    if (existingNotif) existingNotif.remove();
    
    const notif = document.createElement('div');
    notif.id = 'permission-notification';
    notif.style.cssText = `position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:9999;padding:12px 24px;border-radius:8px;font-family:sans-serif;font-size:14px;font-weight:500;display:flex;align-items:center;gap:10px;box-shadow:0 4px 12px rgba(0,0,0,0.15);animation:slideDown 0.3s ease-out;`;
    
    if (type === 'success') {
        notif.style.backgroundColor = '#10B981';
        notif.style.color = '#ffffff';
        notif.innerHTML = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 13l4 4L19 7"></path></svg><span>${message}</span>`;
    } else {
        notif.style.backgroundColor = '#EF4444';
        notif.style.color = '#ffffff';
        notif.innerHTML = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 18L18 6M6 6l12 12"></path></svg><span>${message}</span>`;
    }
    
    document.body.appendChild(notif);
    setTimeout(() => {
        notif.style.animation = 'slideUp 0.3s ease-out forwards';
        setTimeout(() => notif.remove(), 300);
    }, 3000);
}
</script>

<style>
@keyframes slideDown { from { opacity: 0; transform: translateX(-50%) translateY(-20px); } to { opacity: 1; transform: translateX(-50%) translateY(0); } }
@keyframes slideUp { from { opacity: 1; transform: translateX(-50%) translateY(0); } to { opacity: 0; transform: translateX(-50%) translateY(-20px); } }
</style>

<?php include '../layouts/footer.php'; ?>
