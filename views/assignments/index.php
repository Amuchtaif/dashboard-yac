<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_tahfidz');

$page_title = "Manajemen Koordinator Tahfidz";

$db = new Database();
$conn = $db->getConnection();

// Fetch current assignments
$sql = "
    SELECT 
        ea.id,
        e.full_name,
        e.email,
        p.name as primary_position,
        ea.unit_id,
        u.name as unit_name,
        ea.created_at
    FROM employee_assignments ea
    JOIN employees e ON ea.employee_id = e.id
    JOIN positions p ON e.position_id = p.id
    LEFT JOIN units u ON ea.unit_id = u.id
    WHERE ea.position_id = 12 AND ea.is_active = 1
    ORDER BY u.name ASC, e.full_name ASC
";
$stmt = $conn->query($sql);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all active employees for selection
$employees_sql = "
    SELECT e.id, e.full_name, p.name as position_name, u.name as unit_name, d.name as division_name
    FROM employees e
    JOIN positions p ON e.position_id = p.id
    LEFT JOIN divisions d ON e.division_id = d.id
    LEFT JOIN units u ON e.unit_id = u.id
    WHERE e.status = 'active'
    ORDER BY d.name, u.name, e.full_name ASC
";
$stmt_emp = $conn->query($employees_sql);
$all_employees = $stmt_emp->fetchAll(PDO::FETCH_ASSOC);

// Fetch all units for dropdown
$units = $conn->query("SELECT id, name FROM units ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<!-- Tom Select CSS & JS -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/tom-select/2.2.2/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/tom-select/2.2.2/js/tom-select.complete.min.js"></script>

<div class="w-full pb-10">
    <div class="sm:flex sm:items-center justify-between mb-8">
        <div class="sm:flex-auto">
            <h1 class="text-2xl font-bold text-slate-900">Manajemen Koordinator Tahfidz</h1>
            <p class="mt-2 text-sm text-slate-500">Kelola penugasan Koordinator Tahfidz (Jabatan Tambahan) lintas unit.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Assign New Coordinator Form -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50">
                    <h3 class="text-base font-bold text-slate-800">Assign Koordinator Baru</h3>
                </div>
                <form action="../../logic/assignments/store.php" method="POST" class="p-6 space-y-6">
                    <div>
                        <label for="employee_id" class="block text-sm font-semibold text-slate-700 mb-2">Pilih Pegawai</label>
                        <select name="employee_id" id="employee_id" class="tom-select" required>
                            <option value="">Cari nama pegawai...</option>
                            <?php foreach ($all_employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>">
                                    <?php echo htmlspecialchars($emp['full_name']); ?> 
                                    (<?php echo htmlspecialchars($emp['position_name']); ?> - <?php echo htmlspecialchars($emp['unit_name'] ?: ($emp['division_name'] ?: 'Umum')); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="unit_id" class="block text-sm font-semibold text-slate-700 mb-2">Unit Penugasan</label>
                        <select name="unit_id" id="unit_id" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all placeholder:text-slate-400 shadow-sm">
                            <option value="">Semua Unit (Umum)</option>
                            <?php foreach ($units as $unit): ?>
                                <option value="<?php echo $unit['id']; ?>"><?php echo htmlspecialchars($unit['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-[10px] text-slate-400 mt-1 italic">*Pegawai akan mendapatkan akses fitur Tahfidz untuk unit terpilih.</p>
                    </div>

                    <button type="submit" class="w-full px-6 py-3 bg-cyan-600 hover:bg-cyan-700 text-white text-sm font-bold rounded-lg shadow-sm hover:shadow-md transition-all">
                        Assign Koordinator
                    </button>
                </form>
            </div>
        </div>

        <!-- Current Assignments List -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                    <h3 class="text-base font-bold text-slate-800">Daftar Koordinator Tahfidz Aktif</h3>
                    <span class="inline-flex items-center rounded-md bg-cyan-50 px-2 py-1 text-xs font-bold text-cyan-700 ring-1 ring-inset ring-cyan-700/10">
                        <?php echo count($assignments); ?> Koordinator
                    </span>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Pegawai</th>
                                <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jabatan Utama</th>
                                <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Unit Koordinator</th>
                                <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            <?php if (empty($assignments)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-10 text-center text-sm text-slate-500">Belum ada penugasan Koordinator Tahfidz.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($assignments as $asn): ?>
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="h-10 w-10 flex-shrink-0">
                                                <img class="h-10 w-10 rounded-full border border-slate-200" src="https://ui-avatars.com/api/?name=<?php echo urlencode($asn['full_name']); ?>&background=random" alt="">
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($asn['full_name']); ?></div>
                                                <div class="text-xs text-slate-500"><?php echo htmlspecialchars($asn['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                                            <?php echo htmlspecialchars($asn['primary_position']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-slate-700 font-medium"><?php echo htmlspecialchars($asn['unit_name'] ?: 'Semua Unit'); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                        <button onclick="confirmDelete(<?php echo $asn['id']; ?>)" class="text-red-600 hover:text-red-900 font-bold transition-colors">
                                            Revoke (Hapus)
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        new TomSelect('#employee_id', {
            create: false,
            sortField: {
                field: 'text',
                direction: 'asc'
            }
        });
    });

    function confirmDelete(id) {
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: "Jabatan tambahan Koordinator Tahfidz akan dicabut dari pegawai ini.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0891b2',
            cancelButtonColor: '#ef4444',
            confirmButtonText: 'Ya, Cabut!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '../../logic/assignments/delete.php?id=' + id;
            }
        });
    }
</script>

<?php include '../layouts/footer.php'; ?>
