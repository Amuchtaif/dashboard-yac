<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
$page_title = "Manajemen Akses Tahfidz";

$db = new Database();
$conn = $db->getConnection();

// --- Ensure Column Exists (Safety check on page load) ---
// This prevents errors on initial select if column missing
try {
    $checkCol = $conn->query("SHOW COLUMNS FROM positions LIKE 'can_access_tahfidz'");
    if ($checkCol->rowCount() == 0) {
        $conn->exec("ALTER TABLE positions ADD COLUMN can_access_tahfidz TINYINT(1) NOT NULL DEFAULT 0");
    }
} catch (Exception $e) {
    // Silent fail or log
}

// Fetch Positions
$query = "SELECT * FROM positions ORDER BY name ASC";
$positions = $conn->query($query)->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Akses Pengampu Tahfidz</h1>
            <p class="text-slate-500 mt-1">Atur jabatan mana yang memiliki akses ke menu Tahfidz.</p>
        </div>
    </div>

    <!-- Access Control Table -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="px-6 py-4 w-16 text-center">No</th>
                        <th class="px-6 py-4">Nama Jabatan</th>
                        <th class="px-6 py-4 w-48 text-center">Status Akses</th>
                        <th class="px-6 py-4 w-32 text-center">Perbarui</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php $no = 1; foreach ($positions as $pos): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4 text-center text-slate-400"><?php echo $no++; ?></td>
                        <td class="px-6 py-4 font-medium text-slate-800">
                            <?php echo htmlspecialchars($pos['name']); ?>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <!-- Toggle Switch -->
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" value="" class="sr-only peer access-toggle" 
                                       data-id="<?php echo $pos['id']; ?>" 
                                       <?php echo ($pos['can_access_tahfidz'] == 1) ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                <span class="ml-3 text-sm font-medium text-gray-900 status-text-<?php echo $pos['id']; ?>">
                                    <?php echo ($pos['can_access_tahfidz'] == 1) ? 'Aktif' : 'Nonaktif'; ?>
                                </span>
                            </label>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="text-xs text-slate-400" id="msg-<?php echo $pos['id']; ?>"></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.access-toggle').forEach(toggle => {
    toggle.addEventListener('change', function() {
        const positionId = this.dataset.id;
        const isChecked = this.checked;
        const statusText = document.querySelector('.status-text-' + positionId);
        const msgSpan = document.getElementById('msg-' + positionId);

        // Optimistic UI Update
        statusText.textContent = isChecked ? 'Aktif' : 'Nonaktif';
        msgSpan.textContent = 'Menyimpan...';

        fetch('../../logic/tahfidz/update_role_access.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                position_id: positionId,
                status: isChecked ? 1 : 0
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                msgSpan.textContent = 'Tersimpan';
                msgSpan.className = 'text-xs text-green-600 font-medium';
                setTimeout(() => { msgSpan.textContent = ''; }, 2000);
            } else {
                throw new Error(data.message || 'Gagal');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // Revert state
            this.checked = !isChecked;
            statusText.textContent = !isChecked ? 'Aktif' : 'Nonaktif';
            msgSpan.textContent = 'Gagal';
            msgSpan.className = 'text-xs text-red-600 font-medium';
        });
    });
});
</script>

<?php include '../layouts/footer.php'; ?>
