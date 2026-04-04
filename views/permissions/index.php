<?php
// views/permissions/index.php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_employees');

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

// Auto-migration: Ensure can_manage_news column exists
try {
    $checkColumn = $conn->query("SHOW COLUMNS FROM positions LIKE 'can_manage_news'");
    if ($checkColumn->rowCount() === 0) {
        $conn->exec("ALTER TABLE `positions` ADD COLUMN `can_manage_news` TINYINT(1) NOT NULL DEFAULT 0 AFTER `can_access_education`");
    }
} catch (Exception $e) { }

// Auto-migration: Ensure can_access_kabid column exists
try {
    $checkColumn = $conn->query("SHOW COLUMNS FROM positions LIKE 'can_access_kabid'");
    if ($checkColumn->rowCount() === 0) {
        $conn->exec("ALTER TABLE `positions` ADD COLUMN `can_access_kabid` TINYINT(1) NOT NULL DEFAULT 0 AFTER `can_manage_assignments`");
    }
} catch (Exception $e) { }

// Auto-migration: Ensure can_access_kesantrian column exists
try {
    $checkColumn = $conn->query("SHOW COLUMNS FROM positions LIKE 'can_access_kesantrian'");
    if ($checkColumn->rowCount() === 0) {
        $conn->exec("ALTER TABLE `positions` ADD COLUMN `can_access_kesantrian` TINYINT(1) NOT NULL DEFAULT 0 AFTER `can_access_kabid`");
    }
} catch (Exception $e) { }

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
$query = "
    SELECT 
        p.id, 
        p.name, 
        p.level,
        p.can_create_meeting,
        p.can_approve_permits,
        p.can_access_tahfidz,
        p.can_access_education,
        p.can_manage_news,
        p.can_manage_assignments,
        p.can_access_kabid,
        p.can_access_kesantrian
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
        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none flex gap-3">
            <a href="web_permissions.php" class="inline-flex items-center justify-center rounded-lg border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:w-auto transition-colors">
                Hak Akses Web &rarr;
            </a>
            <a href="user_permissions.php" class="inline-flex items-center justify-center rounded-lg border border-transparent bg-cyan-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 sm:w-auto transition-colors">
                Kelola Akses Spesifik Karyawan &rarr;
            </a>
        </div>
    </div>

    <!-- Custom Style for Modern Table -->
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
                                <th colspan="4" class="group-header border-l border-slate-200">Akses Utama Portal</th>
                                <th colspan="2" class="group-header border-l border-slate-200 bg-cyan-50/30 text-cyan-700">Fitur Operasional</th>
                                <th colspan="2" class="group-header border-l border-slate-200 bg-indigo-50/30 text-indigo-700">Manajemen Konten</th>
                            </tr>
                            <tr class="bg-slate-50/80 backdrop-blur-sm text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                                <th scope="col" class="sticky-col py-3.5 pl-4 pr-3 text-left sm:pl-6 w-12 border-b border-slate-200">No.</th>
                                <th scope="col" class="sticky-col py-3.5 pl-4 pr-3 text-left sm:pl-6 min-w-[180px] border-b border-slate-200 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.05)]">Jabatan</th>
                                <th scope="col" class="px-3 py-3.5 text-left border-b border-slate-200">Level</th>
                                
                                <!-- Akses Utama -->
                                <th scope="col" class="px-3 py-3.5 text-center border-b border-slate-200 border-l border-slate-100 min-w-[100px]">Kepala Bidang</th>
                                <th scope="col" class="px-3 py-3.5 text-center border-b border-slate-200 min-w-[100px]">Kesantrian</th>
                                <th scope="col" class="px-3 py-3.5 text-center border-b border-slate-200 min-w-[100px]">Tahfidz</th>
                                <th scope="col" class="px-3 py-3.5 text-center border-b border-slate-200 min-w-[100px]">Pendidikan</th>
                                
                                <!-- Operasional -->
                                <th scope="col" class="px-3 py-3.5 text-center border-b border-slate-200 border-l border-slate-100 min-w-[100px]">Buat Rapat</th>
                                <th scope="col" class="px-3 py-3.5 text-center border-b border-slate-200 min-w-[100px]">Izin Pegawai</th>
                                
                                <!-- Manajemen -->
                                <th scope="col" class="px-3 py-3.5 text-center border-b border-slate-200 border-l border-slate-100 min-w-[100px]">Berita</th>
                                <th scope="col" class="px-3 py-3.5 text-center border-b border-slate-200 min-w-[100px] border-none">Penugasan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <?php if (count($positions) > 0): ?>
                                <?php foreach ($positions as $index => $pos): ?>
                                    <tr class="hover:bg-slate-50 transition-colors group">
                                        <td class="sticky-col whitespace-nowrap py-4 pl-4 pr-3 text-xs text-slate-400 sm:pl-6 border-slate-100"><?php echo $offset + $index + 1; ?>.</td>
                                        <td class="sticky-col whitespace-nowrap py-4 pl-4 pr-3 sm:pl-6 border-slate-100 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.05)]">
                                            <span class="text-sm font-bold text-slate-700 group-hover:text-cyan-700 transition-colors"><?php echo htmlspecialchars($pos['name']); ?></span>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-xs text-slate-500">
                                            <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 border border-slate-200">L<?php echo $pos['level']; ?></span>
                                        </td>
                                        
                                        <!-- Column Toggles (Compact) -->
                                        <?php 
                                            $perms = [
                                                ['type' => 'can_access_kabid',      'color' => 'orange', 'val' => $pos['can_access_kabid'] ?? 0],
                                                ['type' => 'can_access_kesantrian', 'color' => 'pink',   'val' => $pos['can_access_kesantrian'] ?? 0],
                                                ['type' => 'can_access_tahfidz',    'color' => 'blue',   'val' => $pos['can_access_tahfidz'] ?? 0],
                                                ['type' => 'can_access_education',  'color' => 'violet', 'val' => $pos['can_access_education'] ?? 0],
                                                ['type' => 'can_create_meeting',   'color' => 'cyan',   'val' => $pos['can_create_meeting'] ?? 0],
                                                ['type' => 'can_approve_permits',   'color' => 'emerald','val' => $pos['can_approve_permits'] ?? 0],
                                                ['type' => 'can_manage_news',       'color' => 'rose',   'val' => $pos['can_manage_news'] ?? 0],
                                                ['type' => 'can_manage_assignments','color' => 'indigo', 'val' => $pos['can_manage_assignments'] ?? 0]
                                            ];
                                            
                                            foreach($perms as $p):
                                        ?>
                                        <td class="whitespace-nowrap px-3 py-4 text-center">
                                            <div class="flex justify-center">
                                                <label class="relative inline-flex items-center cursor-pointer">
                                                    <input type="checkbox" class="sr-only peer" 
                                                        <?php echo $p['val'] == 1 ? 'checked' : ''; ?>
                                                        onchange="updatePermission(<?php echo $pos['id']; ?>, '<?php echo $p['type']; ?>', this.checked)">
                                                    <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-<?php echo $p['color']; ?>-600"></div>
                                                </label>
                                            </div>
                                        </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="11" class="py-10 text-center text-sm text-slate-500 italic">Data jabatan tidak ditemukan.</td>
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
    } else if (permissionType === 'can_manage_news') {
        statusTextSelector = `.status-text-news-${id}`;
    } else if (permissionType === 'can_manage_assignments') {
        statusTextSelector = `.status-text-assignments-${id}`;
    } else if (permissionType === 'can_access_kabid') {
        statusTextSelector = `.status-text-kabid-${id}`;
    } else if (permissionType === 'can_access_kesantrian') {
        statusTextSelector = `.status-text-kesantrian-${id}`;
    } else {
        statusTextSelector = `.status-text-${id}`;
    }
    
    const statusText = document.querySelector(statusTextSelector);
    const checkbox = event.target;
    const originalText = statusText ? statusText.innerText : '';
    
    if (statusText) {
        statusText.innerText = 'Menyimpan...';
    }

    const apiUrl = '../../logic/permissions/update_role_permission.php';
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
