<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

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
        header("Location: " . BASE_URL . "/views/positions/index.php?error=" . urlencode("Position not found"));
        exit;
    }
}
include '../layouts/header.php';
?>

<div class="max-w-3xl mx-auto pb-10">
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
                    <svg class="w-3 h-3 text-gray-400 mx-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 6 10">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="m1 9 4-4-4-4" />
                    </svg>
                    <a href="<?php url('views/positions/index.php'); ?>"
                        class="ml-1 text-slate-500 hover:text-slate-700">Jabatan</a>
                </div>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 6 10">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="m1 9 4-4-4-4" />
                    </svg>
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
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                <path fill-rule="evenodd"
                    d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z"
                    clip-rule="evenodd" />
            </svg>
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
                                placeholder="e.g. Senior Manager">
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
                                placeholder="e.g. 1">
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