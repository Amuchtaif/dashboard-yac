<?php
// views/permissions/index.php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$page_title = "Manajemen Hak Akses";

$db = new Database();
$conn = $db->getConnection();

// Auto-migration: Ensure can_approve_permits column exists
try {
    $checkColumn = $conn->query("SHOW COLUMNS FROM positions LIKE 'can_approve_permits'");
    if ($checkColumn->rowCount() === 0) {
        $conn->exec("ALTER TABLE `positions` ADD COLUMN `can_approve_permits` TINYINT(1) NOT NULL DEFAULT 0 AFTER `can_create_meeting`");
    }
} catch (Exception $e) {
    // Column might already exist or error - continue anyway
}

// Auto-migration: Ensure can_access_tahfidz column exists
try {
    $checkColumn = $conn->query("SHOW COLUMNS FROM positions LIKE 'can_access_tahfidz'");
    if ($checkColumn->rowCount() === 0) {
        $conn->exec("ALTER TABLE `positions` ADD COLUMN `can_access_tahfidz` TINYINT(1) NOT NULL DEFAULT 0 AFTER `can_approve_permits`");
    }
} catch (Exception $e) {
    // Column might already exist or error - continue anyway
}

// Auto-migration: Ensure can_access_education column exists
try {
    $checkColumn = $conn->query("SHOW COLUMNS FROM positions LIKE 'can_access_education'");
    if ($checkColumn->rowCount() === 0) {
        $conn->exec("ALTER TABLE `positions` ADD COLUMN `can_access_education` TINYINT(1) NOT NULL DEFAULT 0 AFTER `can_access_tahfidz`");
    }
} catch (Exception $e) {
    // Column might already exist or error - continue anyway
}

// --- Pagination Logic ---
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
if (!in_array($limit, [10, 20, 50, 100]))
    $limit = 10;

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $limit;

// Total Positions
$total_rows = $conn->query("SELECT COUNT(*) FROM positions")->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Fetch Positions with permissions
// We fetch only id, name, and can_create_meeting for now
// In the future this can be expanded to join with a 'permissions' or 'role_permissions' table
$query = "
    SELECT 
        p.id, 
        p.name, 
        p.level,
        p.can_create_meeting,
        p.can_approve_permits,
        p.can_access_tahfidz,
        p.can_access_education
    FROM positions p
    ORDER BY p.level ASC, p.name ASC
    LIMIT :limit OFFSET :offset
";
$stmt = $conn->prepare($query);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$positions = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="pb-10">
    <div class="sm:flex sm:items-center justify-between">
        <div class="sm:flex-auto">
            <h1 class="text-xl font-bold text-slate-900">Manajemen Hak Akses</h1>
            <p class="mt-2 text-sm text-slate-500">Atur hak akses fungsional untuk setiap jabatan.</p>
        </div>
        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
            <a href="user_permissions.php" class="inline-flex items-center justify-center rounded-lg border border-transparent bg-cyan-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 sm:w-auto transition-colors">
                Kelola Akses Spesifik Karyawan &rarr;
            </a>
        </div>
    </div>

    <!-- Table -->
    <div class="mt-8 flex flex-col">
        <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
                <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col"
                                    class="py-3.5 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:pl-6 w-12">
                                    No.</th>
                                <th scope="col"
                                    class="py-3.5 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:pl-6">
                                    Nama Jabatan</th>
                                <th scope="col"
                                    class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Level</th>
                                <th scope="col"
                                    class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Akses Buat Rapat</th>
                                <th scope="col"
                                    class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Akses Persetujuan Izin</th>
                                <th scope="col"
                                    class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Akses Menu Tahfidz</th>
                                <th scope="col"
                                    class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Akses Menu Pendidikan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <?php if (count($positions) > 0): ?>
                                <?php foreach ($positions as $index => $pos): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-500 sm:pl-6">
                                            <?php echo $offset + $index + 1; ?>.
                                        </td>
                                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-bold text-gray-900 sm:pl-6">
                                            <?php echo htmlspecialchars($pos['name']); ?>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                            <span
                                                class="inline-flex items-center rounded-md bg-slate-50 px-2 py-1 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-500/10">
                                                Level <?php echo $pos['level']; ?>
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input type="checkbox" class="sr-only peer" 
                                                    <?php echo $pos['can_create_meeting'] == 1 ? 'checked' : ''; ?>
                                                    onchange="updatePermission(<?php echo $pos['id']; ?>, 'can_create_meeting', this.checked)">
                                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-cyan-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-cyan-600"></div>
                                                <span class="ml-3 text-sm font-medium text-gray-900 status-text-meeting-<?php echo $pos['id']; ?>">
                                                    <?php echo $pos['can_create_meeting'] == 1 ? 'Ya' : 'Tidak'; ?>
                                                </span>
                                            </label>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input type="checkbox" class="sr-only peer" 
                                                    <?php echo isset($pos['can_approve_permits']) && $pos['can_approve_permits'] == 1 ? 'checked' : ''; ?>
                                                    onchange="updatePermission(<?php echo $pos['id']; ?>, 'can_approve_permits', this.checked)">
                                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-emerald-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                                                <span class="ml-3 text-sm font-medium text-gray-900 status-text-permit-<?php echo $pos['id']; ?>">
                                                    <?php echo isset($pos['can_approve_permits']) && $pos['can_approve_permits'] == 1 ? 'Ya' : 'Tidak'; ?>
                                                </span>
                                            </label>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input type="checkbox" class="sr-only peer" 
                                                    <?php echo isset($pos['can_access_tahfidz']) && $pos['can_access_tahfidz'] == 1 ? 'checked' : ''; ?>
                                                    onchange="updatePermission(<?php echo $pos['id']; ?>, 'can_access_tahfidz', this.checked)">
                                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                                <span class="ml-3 text-sm font-medium text-gray-900 status-text-tahfidz-<?php echo $pos['id']; ?>">
                                                    <?php echo isset($pos['can_access_tahfidz']) && $pos['can_access_tahfidz'] == 1 ? 'Ya' : 'Tidak'; ?>
                                                </span>
                                            </label>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input type="checkbox" class="sr-only peer" 
                                                    <?php echo isset($pos['can_access_education']) && $pos['can_access_education'] == 1 ? 'checked' : ''; ?>
                                                    onchange="updatePermission(<?php echo $pos['id']; ?>, 'can_access_education', this.checked)">
                                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-violet-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-violet-600"></div>
                                                <span class="ml-3 text-sm font-medium text-gray-900 status-text-education-<?php echo $pos['id']; ?>">
                                                    <?php echo isset($pos['can_access_education']) && $pos['can_access_education'] == 1 ? 'Ya' : 'Tidak'; ?>
                                                </span>
                                            </label>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="py-10 text-center text-sm text-gray-500">Data jabatan tidak ditemukan.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6">
                        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                            <div class="flex items-center gap-4">
                                <select onchange="window.location.href='?page=1&limit='+this.value"
                                    class="block rounded-md border-0 py-1.5 pl-3 pr-8 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-cyan-600 sm:text-xs sm:leading-6">
                                    <?php foreach ([10, 20, 50, 100] as $val): ?>
                                        <option value="<?php echo $val; ?>" <?php echo $limit == $val ? 'selected' : ''; ?>>
                                            Tampilkan <?php echo $val; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-sm text-gray-700">
                                    Menampilkan
                                    <span class="font-medium"><?php echo ($total_rows > 0) ? $offset + 1 : 0; ?></span>
                                    sampai
                                    <span class="font-medium"><?php echo min($offset + $limit, $total_rows); ?></span>
                                    dari
                                    <span class="font-medium"><?php echo $total_rows; ?></span>
                                    hasil
                                </p>
                            </div>
                            <div>
                                <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                                    <!-- Simple Pagination Logic -->
                                    <?php if ($page > 1): ?>
                                        <a href="?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                                            <span class="sr-only">Previous</span>
                                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                                            </svg>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="#" aria-current="page" class="relative z-10 inline-flex items-center bg-cyan-600 px-4 py-2 text-sm font-semibold text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600"><?php echo $page; ?></a>

                                    <?php if ($page < $total_pages): ?>
                                        <a href="?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
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
function updatePermission(id, permissionType, isChecked) {
    // Determine the correct status text selector based on permission type
    let statusTextSelector;
    if (permissionType === 'can_create_meeting') {
        statusTextSelector = `.status-text-meeting-${id}`;
    } else if (permissionType === 'can_approve_permits') {
        statusTextSelector = `.status-text-permit-${id}`;
    } else if (permissionType === 'can_access_tahfidz') {
        statusTextSelector = `.status-text-tahfidz-${id}`;
    } else if (permissionType === 'can_access_education') {
        statusTextSelector = `.status-text-education-${id}`;
    } else {
        statusTextSelector = `.status-text-${id}`;
    }
    
    const statusText = document.querySelector(statusTextSelector);
    const checkbox = event.target;
    const originalText = statusText ? statusText.innerText : '';
    
    if (statusText) {
        statusText.innerText = 'Menyimpan...';
    }

    // Detect base URL from current location
    const pathParts = window.location.pathname.split('/');
    let baseUrl = window.location.origin;
    for (let i = 0; i < pathParts.length; i++) {
        if (pathParts[i] === 'dashboard-yac') {
            baseUrl += '/' + pathParts.slice(1, i + 1).join('/') + '/';
            break;
        }
    }
    
    const apiUrl = baseUrl + 'logic/permissions/update_role_permission.php';
    console.log('Calling API:', apiUrl, 'Data:', { id, permissionType, value: isChecked ? 1 : 0 });
    
    fetch(apiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
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
            if (statusText) {
                statusText.innerText = isChecked ? 'Ya' : 'Tidak';
            }
            showNotification('success', 'Hak akses berhasil diperbarui');
        } else {
            if (statusText) {
                statusText.innerText = originalText;
            }
            if (checkbox) checkbox.checked = !isChecked;
            showNotification('error', 'Gagal memperbarui: ' + (data.message || 'Unknown error'));
        }
    })
    .catch((error) => {
        console.error('Fetch Error:', error);
        if (statusText) {
            statusText.innerText = originalText;
        }
        if (checkbox) checkbox.checked = !isChecked;
        showNotification('error', 'Error: ' + error.message);
    });
}

// Simple notification function with auto-hide
function showNotification(type, message) {
    // Remove existing notification if any
    const existingNotif = document.getElementById('permission-notification');
    if (existingNotif) {
        existingNotif.remove();
    }
    
    // Create notification element
    const notif = document.createElement('div');
    notif.id = 'permission-notification';
    notif.style.cssText = `
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 9999;
        padding: 12px 24px;
        border-radius: 8px;
        font-family: 'Poppins', sans-serif;
        font-size: 14px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        animation: slideDown 0.3s ease-out;
    `;
    
    if (type === 'success') {
        notif.style.backgroundColor = '#10B981';
        notif.style.color = '#ffffff';
        notif.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
            </svg>
            <span>${message}</span>
        `;
    } else {
        notif.style.backgroundColor = '#EF4444';
        notif.style.color = '#ffffff';
        notif.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
            <span>${message}</span>
        `;
    }
    
    // Add animation style if not exists
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
    
    // Auto hide after 3 seconds
    setTimeout(() => {
        notif.style.animation = 'slideUp 0.3s ease-out forwards';
        setTimeout(() => notif.remove(), 300);
    }, 3000);
}
</script>

<?php include '../layouts/footer.php'; ?>
