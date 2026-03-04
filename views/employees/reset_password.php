<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_employees');

$page_title = "Reset Password Karyawan";

$db = new Database();
$conn = $db->getConnection();

// --- Pagination ---
$limit = isset($_GET['limit']) && in_array((int) $_GET['limit'], [10, 20, 50, 100]) ? (int) $_GET['limit'] : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// --- Search ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where_clauses = ["e.id != 1"];
$params = [];

if ($search) {
    $where_clauses[] = "(e.full_name LIKE :search OR e.email LIKE :search)";
    $params[':search'] = "%$search%";
}

$where_sql = implode(" AND ", $where_clauses);

// Total Count
$count_stmt = $conn->prepare("SELECT COUNT(*) FROM employees e WHERE $where_sql");
$count_stmt->execute($params);
$total_rows = $count_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Fetch Data
$query = "
    SELECT e.id, e.full_name, e.email, e.phone_number,
           d.name as division_name, u.name as unit_name, p.name as position_name
    FROM employees e 
    LEFT JOIN divisions d ON e.division_id = d.id 
    LEFT JOIN units u ON e.unit_id = u.id
    LEFT JOIN positions p ON e.position_id = p.id
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

// Handle Reset Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_id'])) {
    $reset_id = (int) $_POST['reset_id'];
    $new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
    
    if (empty($new_password) || strlen($new_password) < 6) {
        header("Location: reset_password.php?error=Password minimal 6 karakter.&search=" . urlencode($search));
        exit;
    }
    
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    try {
        $update = $conn->prepare("UPDATE employees SET password = :pass WHERE id = :id");
        $update->execute([':pass' => $hashed, ':id' => $reset_id]);
        header("Location: reset_password.php?success=Password berhasil direset.&search=" . urlencode($search));
        exit;
    } catch (PDOException $e) {
        header("Location: reset_password.php?error=Gagal mereset password.&search=" . urlencode($search));
        exit;
    }
}

include '../layouts/header.php';
?>

<div class="pb-10">
    <!-- Header -->
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-xl font-bold text-slate-900">Reset Password Karyawan</h1>
            <p class="mt-1 text-sm text-slate-500">Atur ulang password login akun karyawan yang lupa kata sandinya.</p>
        </div>
    </div>

    <!-- Alerts -->
    <?php if (isset($_GET['success'])): ?>
        <div class="mt-4 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg text-sm flex items-center gap-2 alert-banner transition-opacity duration-500">
            <svg class="h-5 w-5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
            <?php echo htmlspecialchars($_GET['success']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="mt-4 bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg text-sm flex items-center gap-2 alert-banner transition-opacity duration-500">
            <svg class="h-5 w-5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Search -->
    <div class="mt-6">
        <form method="GET" class="flex gap-3 items-center">
            <div class="relative flex-1 max-w-sm">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Cari nama atau email..."
                       class="w-full pl-10 pr-4 py-2 rounded-lg border border-slate-200 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all placeholder:text-slate-400">
            </div>
            <button type="submit" class="px-4 py-2 bg-cyan-600 text-white text-sm font-semibold rounded-lg hover:bg-cyan-700 transition-colors shadow-sm">
                Cari
            </button>
            <?php if ($search): ?>
                <a href="reset_password.php" class="px-4 py-2 border border-slate-200 text-slate-600 text-sm font-medium rounded-lg hover:bg-slate-50 transition-colors">
                    Reset Filter
                </a>
            <?php endif; ?>
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
                                <th class="py-3 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:pl-6 w-12">No.</th>
                                <th class="py-3 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:pl-6">Karyawan</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Jabatan</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Divisi / Unit</th>
                                <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 w-48">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <?php if (count($employees) > 0): ?>
                                <?php foreach ($employees as $index => $emp): ?>
                                    <tr class="hover:bg-gray-50 transition-colors" id="row-<?php echo $emp['id']; ?>">
                                        <td class="whitespace-nowrap py-3 pl-4 pr-3 text-sm text-gray-500 sm:pl-6">
                                            <?php echo $offset + $index + 1; ?>.
                                        </td>
                                        <td class="whitespace-nowrap py-3 pl-4 pr-3 sm:pl-6">
                                            <div class="flex items-center gap-3">
                                                <div class="h-9 w-9 rounded-full bg-slate-100 flex items-center justify-center overflow-hidden flex-shrink-0">
                                                    <span class="text-xs font-bold text-slate-500">
                                                        <?php echo strtoupper(substr($emp['full_name'], 0, 2)); ?>
                                                    </span>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($emp['full_name']); ?></p>
                                                    <p class="text-xs text-slate-400"><?php echo htmlspecialchars($emp['email']); ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3 text-sm text-slate-600">
                                            <?php echo htmlspecialchars($emp['position_name'] ?? '-'); ?>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3 text-sm text-slate-500">
                                            <span class="text-slate-700"><?php echo htmlspecialchars($emp['division_name'] ?? '-'); ?></span>
                                            <?php if ($emp['unit_name']): ?>
                                                <span class="text-slate-300 mx-1">/</span>
                                                <span class="text-slate-500"><?php echo htmlspecialchars($emp['unit_name']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3 text-center">
                                            <button type="button" onclick="openResetModal(<?php echo $emp['id']; ?>, '<?php echo htmlspecialchars(addslashes($emp['full_name']), ENT_QUOTES); ?>')"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-50 text-amber-700 text-xs font-semibold rounded-lg border border-amber-200 hover:bg-amber-100 transition-all">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                                </svg>
                                                Reset Password
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="py-10 text-center">
                                        <svg class="mx-auto h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                        </svg>
                                        <p class="mt-2 text-sm text-slate-400">Tidak ada data karyawan ditemukan.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6">
                        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                            <div class="flex items-center gap-4">
                                <select onchange="window.location.href='?page=1&limit='+this.value+'&search=<?php echo urlencode($search); ?>'"
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
                                    <?php if ($page > 1): ?>
                                        <a href="?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" /></svg>
                                        </a>
                                    <?php endif; ?>
                                    <a href="#" aria-current="page" class="relative z-10 inline-flex items-center bg-cyan-600 px-4 py-2 text-sm font-semibold text-white"><?php echo $page; ?></a>
                                    <?php if ($page < $total_pages): ?>
                                        <a href="?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>
                                        </a>
                                    <?php endif; ?>
                                </nav>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="resetModal" class="fixed inset-0 z-[60] hidden overflow-y-auto" aria-modal="true">
    <div id="resetModalBackdrop" class="fixed inset-0 bg-slate-900/50 transition-opacity duration-300 opacity-0 backdrop-blur-sm"></div>
    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <div id="resetModalPanel" class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-2xl transition-all duration-300 opacity-0 scale-95 sm:my-8 sm:w-full sm:max-w-md">
            <form method="POST" action="reset_password.php?search=<?php echo urlencode($search); ?>">
                <input type="hidden" name="reset_id" id="resetUserId">
                <div class="bg-white px-5 pt-5 pb-4">
                    <div class="flex items-start gap-4">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-amber-100">
                            <svg class="h-5 w-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                            </svg>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-base font-bold text-slate-900">Reset Password</h3>
                            <p class="mt-1 text-sm text-slate-500">
                                Atur ulang password untuk <span id="resetUserName" class="font-semibold text-slate-700"></span>
                            </p>
                            <div class="mt-4 space-y-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Password Baru</label>
                                    <div class="relative">
                                        <input type="password" name="new_password" id="newPasswordInput" required minlength="6"
                                               class="w-full px-3.5 py-2.5 rounded-lg border border-slate-200 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all placeholder:text-slate-300"
                                               placeholder="Minimal 6 karakter...">
                                        <button type="button" onclick="togglePassword()" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                            <svg id="eyeIcon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="p-2.5 bg-amber-50 border border-amber-100 rounded-lg">
                                    <p class="text-[10px] text-amber-700 font-medium flex items-start gap-1.5">
                                        <svg class="w-3.5 h-3.5 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                        </svg>
                                        Setelah direset, informasikan password baru kepada karyawan terkait secara langsung.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-slate-50 px-5 py-3 sm:flex sm:flex-row-reverse gap-2">
                    <button type="submit" class="inline-flex w-full justify-center rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-500 sm:w-auto transition-all active:scale-95">
                        Reset Password
                    </button>
                    <button type="button" onclick="closeResetModal()" class="mt-3 inline-flex w-full justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto transition-all">
                        Batal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openResetModal(id, name) {
    document.getElementById('resetUserId').value = id;
    document.getElementById('resetUserName').textContent = name;
    document.getElementById('newPasswordInput').value = '';

    const modal = document.getElementById('resetModal');
    const backdrop = document.getElementById('resetModalBackdrop');
    const panel = document.getElementById('resetModalPanel');

    modal.classList.remove('hidden');
    setTimeout(() => {
        backdrop.classList.remove('opacity-0');
        panel.classList.remove('opacity-0', 'scale-95');
    }, 10);
}

function closeResetModal() {
    const modal = document.getElementById('resetModal');
    const backdrop = document.getElementById('resetModalBackdrop');
    const panel = document.getElementById('resetModalPanel');

    backdrop.classList.add('opacity-0');
    panel.classList.add('opacity-0', 'scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

function togglePassword() {
    const input = document.getElementById('newPasswordInput');
    const icon = document.getElementById('eyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878l4.242 4.242M21 21l-3.122-3.122" />';
    } else {
        input.type = 'password';
        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />';
    }
}

// Close modal on backdrop click
document.getElementById('resetModalBackdrop').addEventListener('click', closeResetModal);
</script>

<?php include '../layouts/footer.php'; ?>
