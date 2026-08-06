<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_employees');

$db = new Database();
$conn = $db->getConnection();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$is_edit = $id > 0;
$page_title = $is_edit ? "Edit Jabatan" : "Tambah Jabatan";

// Initial Data
$position = [
    'name' => '',
    'level' => ''
];

if ($is_edit) {
    $stmt = $conn->prepare("SELECT * FROM positions WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $fetch = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($fetch) {
        $position = $fetch;
    } else {
        header("Location: " . BASE_URL . "/views/positions/index.php?error=" . urlencode("Jabatan tidak ditemukan"));
        exit;
    }
}
include '../layouts/header.php';
?>

<div class="w-full pb-10">
    <!-- Breadcrumb -->
    <nav class="flex mb-4" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
            <li class="inline-flex items-center">
                <a href="<?php url('views/dashboard/index.php'); ?>"
                    class="inline-flex items-center text-slate-500 hover:text-slate-700">
                    Beranda
                </a>
            </li>
            <li>
                <div class="flex items-center">
                    <i class="fa-solid fa-chevron-right w-3 h-3 text-gray-400 mx-1"></i>
                    <a href="<?php url('views/positions/index.php'); ?>"
                        class="ml-1 text-slate-500 hover:text-slate-700">Jabatan</a>
                </div>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <i class="fa-solid fa-chevron-right w-3 h-3 text-gray-400 mx-1"></i>
                    <span class="ml-1 font-medium text-slate-800">
                        <?php echo $is_edit ? "Edit" : "Tambah"; ?>
                    </span>
                </div>
            </li>
        </ol>
    </nav>

    <div class="mb-8">
        <h1 class="text-2xl font-bold text-slate-900">
            <?php echo $page_title; ?>
        </h1>
        <p class="mt-2 text-sm text-slate-600">Tentukan peran dan hierarki karyawan.</p>
    </div>

    <!-- Error Alert -->
    <?php if (isset($_GET['error'])): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm flex items-center gap-2">
            <i class="fa-solid fa-magnifying-glass w-5 h-5"></i>
            <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
        <form action="<?php echo $is_edit ? url('logic/positions/update.php') : url('logic/positions/store.php'); ?>"
            method="POST">
            <?php if ($is_edit): ?>
                <input type="hidden" name="id" value="<?php echo $position['id']; ?>">
            <?php endif; ?>

            <div class="px-4 py-6 sm:p-8">
                <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">

                    <!-- Position Name -->
                    <div class="sm:col-span-4">
                        <label for="name" class="block text-sm font-medium leading-6 text-gray-900">Nama Jabatan</label>
                        <div class="mt-2">
                            <input type="text" name="name" id="name" required
                                value="<?php echo htmlspecialchars($position['name']); ?>"
                                class="block w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all placeholder:text-slate-400 shadow-sm"
                                placeholder="misal: Kepala Unit">
                        </div>
                    </div>

                    <!-- Level -->
                    <div class="sm:col-span-2">
                        <label for="level" class="block text-sm font-medium leading-6 text-gray-900">Level
                            (Hierarki)</label>
                        <div class="mt-2">
                            <input type="number" name="level" id="level" required min="1"
                                value="<?php echo htmlspecialchars($position['level']); ?>"
                                class="block w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all placeholder:text-slate-400 shadow-sm"
                                placeholder="misal: 1">
                            <p class="mt-1 text-xs text-gray-500">Angka lebih rendah = Tingkatan lebih tinggi (misal: 1
                                = Direktur, 10 = Magang)
                            </p>
                        </div>
                    </div>

                </div>
            </div>

            <div class="flex items-center justify-end gap-x-6 border-t border-gray-900/10 px-4 py-4 sm:px-8">
                <a href="<?php url('views/positions/index.php'); ?>"
                    class="text-sm font-semibold leading-6 text-gray-900">Batal</a>
                <button type="submit"
                    class="rounded-md bg-cyan-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-cyan-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 transition-colors">
                    <?php echo $is_edit ? "Simpan Perubahan" : "Simpan Jabatan"; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>