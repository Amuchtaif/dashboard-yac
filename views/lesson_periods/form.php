<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$db = new Database();
$conn = $db->getConnection();

$id = isset($_GET['id']) ? $_GET['id'] : null;
$period = null;
$page_title = $id ? "Edit Jam Pelajaran" : "Tambah Jam Pelajaran";

if ($id) {
    $stmt = $conn->prepare("SELECT * FROM lesson_periods WHERE id = ?");
    $stmt->execute([$id]);
    $period = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch Education Units
$units = $conn->query("SELECT id, name FROM education_units ORDER BY FIELD(name, 'Playgroup', 'TKIT', 'SDIT', 'MTs', 'MA', 'Ma''had Aly')")->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="w-full pb-10">
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <h2 class="text-2xl font-bold leading-7 text-slate-900 sm:text-3xl"><?php echo $page_title; ?></h2>
            <p class="mt-1 text-sm text-slate-500">Tentukan urutan jam dan rentang waktu per jenjang.</p>
        </div>
        <div class="mt-4 flex md:ml-4 md:mt-0">
            <a href="index.php" class="inline-flex items-center px-4 py-2 border border-slate-300 rounded-lg shadow-sm text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 transition-colors">Kembali</a>
        </div>
    </div>

    <div class="max-w-2xl bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <form action="../../logic/lesson_periods/<?php echo $id ? 'update.php' : 'store.php'; ?>" method="POST" class="p-6 sm:p-8 space-y-6">
            <?php if ($id): ?><input type="hidden" name="id" value="<?php echo $id; ?>"><?php endif; ?>

            <div class="space-y-6">
                <!-- Unit Pendidikan -->
                <div>
                    <label for="education_unit_id" class="block text-sm font-semibold text-slate-700 mb-1">Jenjang Pendidikan <span class="text-red-500">*</span></label>
                    <select name="education_unit_id" id="education_unit_id" required class="block w-full rounded-lg border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border p-2.5 text-slate-600">
                        <option value="">Pilih Jenjang...</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo ($period && $period['education_unit_id'] == $u['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($u['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Nomor Urut Jam -->
                <div>
                    <label for="period_number" class="block text-sm font-semibold text-slate-700 mb-1">Jam Ke- <span class="text-red-500">*</span></label>
                    <input type="number" name="period_number" id="period_number" value="<?php echo $period ? $period['period_number'] : ''; ?>" required min="1" max="20" placeholder="Contoh: 1" class="block w-full rounded-lg border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border p-2.5 text-slate-600">
                    <p class="mt-1 text-xs text-slate-400">Urutan jam dalam sehari (1, 2, 3...)</p>
                </div>

                <!-- Rentang Waktu -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="start_time" class="block text-sm font-semibold text-slate-700 mb-1">Waktu Mulai <span class="text-red-500">*</span></label>
                        <input type="time" name="start_time" id="start_time" value="<?php echo $period ? date('H:i', strtotime($period['start_time'])) : ''; ?>" required class="block w-full rounded-lg border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border p-2.5 text-slate-600">
                    </div>
                    <div>
                        <label for="end_time" class="block text-sm font-semibold text-slate-700 mb-1">Waktu Selesai <span class="text-red-500">*</span></label>
                        <input type="time" name="end_time" id="end_time" value="<?php echo $period ? date('H:i', strtotime($period['end_time'])) : ''; ?>" required class="block w-full rounded-lg border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border p-2.5 text-slate-600">
                    </div>
                </div>
            </div>

            <div class="pt-6 flex justify-end gap-3 border-t border-slate-100">
                <button type="submit" class="inline-flex items-center px-6 py-2.5 rounded-lg shadow-sm text-sm font-bold text-white bg-cyan-600 hover:bg-cyan-700 focus:ring-2 focus:ring-cyan-500 transition-all">
                    <?php echo $id ? 'Perbarui Data' : 'Simpan Jam'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
