<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$db = new Database();
$conn = $db->getConnection();

$id = isset($_GET['id']) ? $_GET['id'] : null;
$subject = null;
$page_title = $id ? "Edit Mata Pelajaran" : "Tambah Mata Pelajaran";

if ($id) {
    $stmt = $conn->prepare("SELECT * FROM subjects WHERE id = ?");
    $stmt->execute([$id]);
    $subject = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$subject) {
        header("Location: index.php?error=Mata pelajaran tidak ditemukan");
        exit;
    }
}

include '../layouts/header.php';
?>

<div class="w-full pb-10">
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <h2 class="text-2xl font-bold leading-7 text-slate-900 sm:text-3xl sm:truncate">
                <?php echo $page_title; ?>
            </h2>
            <p class="mt-1 text-sm text-slate-500">
                Silakan isi data mata pelajaran di bawah ini secara lengkap.
            </p>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4">
            <a href="index.php"
                class="inline-flex items-center px-4 py-2 border border-slate-300 rounded-lg shadow-sm text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-500 transition-colors">
                Kembali
            </a>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <form action="../../logic/subjects/<?php echo $id ? 'update.php' : 'store.php'; ?>" method="POST"
            class="p-6 sm:p-8 space-y-6">
            <?php if ($id): ?>
                <input type="hidden" name="id" value="<?php echo $id; ?>">
            <?php endif; ?>

            <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2">
                <div class="sm:col-span-1">
                    <label for="code" class="block text-sm font-semibold text-slate-700">Kode Mapel <span
                            class="text-red-500">*</span></label>
                    <div class="mt-1">
                        <input type="text" name="code" id="code"
                            value="<?php echo htmlspecialchars($subject['code'] ?? ''); ?>" required
                            class="block w-full rounded-lg border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border placeholder:text-slate-400 text-slate-600 p-2.5"
                            placeholder="Contoh: MTK, ENG, BIND">
                    </div>
                </div>

                <div class="sm:col-span-1">
                    <label for="name" class="block text-sm font-semibold text-slate-700">Nama Mata Pelajaran <span
                            class="text-red-500">*</span></label>
                    <div class="mt-1">
                        <input type="text" name="name" id="name"
                            value="<?php echo htmlspecialchars($subject['name'] ?? ''); ?>" required
                            class="block w-full rounded-lg border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border placeholder:text-slate-400 text-slate-600 p-2.5"
                            placeholder="Contoh: Matematika">
                    </div>
                </div>

                <!-- Category (Add New) -->
                <div class="sm:col-span-1">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Kategori <span
                            class="text-red-500">*</span></label>
                    <div class="relative group" id="category-container">
                        <input type="hidden" name="category" id="category-input"
                            value="<?php echo $subject['category'] ?? 'Umum'; ?>" required>
                        <button type="button" onclick="toggleDropdown('category')"
                            class="inline-flex items-center justify-between w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-white focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors">
                            <span id="category-text"><?php echo $subject['category'] ?? 'Umum'; ?></span>
                            <svg class="h-5 w-5 text-slate-400 transition-transform duration-200" id="category-arrow"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div id="category-menu"
                            class="hidden absolute top-full left-0 mt-1 w-full origin-top-left rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50">
                            <ul class="py-1">
                                <li onclick="selectOption('category', 'Umum', 'Umum')"
                                    class="cursor-pointer px-4 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                    Umum</li>
                                <li onclick="selectOption('category', 'Diniyah', 'Diniyah')"
                                    class="cursor-pointer px-4 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                    Diniyah</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="sm:col-span-2">
                    <label for="description" class="block text-sm font-semibold text-slate-700">Deskripsi</label>
                    <div class="mt-1">
                        <textarea name="description" id="description" rows="3"
                            class="block w-full rounded-lg border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border placeholder:text-slate-400 text-slate-600 p-2.5"
                            placeholder="Opsional..."><?php echo htmlspecialchars($subject['description'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="pt-6 flex items-center justify-end gap-3 border-t border-slate-100">
                <button type="submit"
                    class="inline-flex items-center px-6 py-2.5 border border-transparent rounded-lg shadow-sm text-sm font-bold text-white bg-cyan-600 hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-500 transition-all">
                    <svg class="-ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>