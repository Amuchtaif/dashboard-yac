<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$page_title = "Manajemen Lokasi";

$db = new Database();
$conn = $db->getConnection();

// --- Fetch Locations ---
$query = "SELECT * FROM locations ORDER BY id ASC";
$stmt = $conn->prepare($query);
$stmt->execute();
$locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="pb-10">

    <!-- Page Header -->
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-xl font-bold text-slate-900">Manajemen Lokasi</h1>
            <p class="mt-2 text-sm text-slate-500">Daftar lokasi yang diizinkan untuk absensi check-in dan check-out.</p>
        </div>
        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
            <a href="location_form.php"
                class="inline-flex items-center justify-center rounded-lg border border-transparent bg-cyan-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 sm:w-auto transition-colors">
                <i class="fa-solid fa-plus -ml-1 mr-2 h-4 w-4"></i>
                Tambah Lokasi
            </a>
        </div>
    </div>

    <!-- Table -->
    <div class="mt-8 flex flex-col">
        <div class="overflow-x-auto shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:pl-6 w-12">No.</th>
                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:pl-6">Nama Lokasi</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Koordinat</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Radius (m)</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                        <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <?php if (empty($locations)): ?>
                        <tr>
                            <td colspan="6" class="py-10 text-center text-sm text-slate-500 italic">Belum ada data lokasi.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($locations as $index => $loc): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-500 sm:pl-6"><?php echo $index + 1; ?>.</td>
                                <td class="whitespace-nowrap py-4 pl-4 pr-3 sm:pl-6">
                                    <div class="font-medium text-gray-900"><?php echo htmlspecialchars($loc['name']); ?></div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 font-mono">
                                    <?php echo $loc['latitude']; ?>, <?php echo $loc['longitude']; ?>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                    <?php echo $loc['radius_meter']; ?> meter
                                </td>
                                <td class="whitespace-nowrap px-3 py-4">
                                    <span class="inline-flex items-center rounded-md bg-<?php echo ($loc['is_active'] ?? 1) ? 'green' : 'red'; ?>-50 px-2 py-1 text-xs font-medium text-<?php echo ($loc['is_active'] ?? 1) ? 'green' : 'red'; ?>-700 ring-1 ring-inset ring-<?php echo ($loc['is_active'] ?? 1) ? 'green' : 'red'; ?>-600/20 uppercase tracking-wider">
                                        <?php echo ($loc['is_active'] ?? 1) ? 'Aktif' : 'Nonaktif'; ?>
                                    </span>
                                </td>
                                <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                    <div class="flex items-center justify-end gap-3 text-gray-400">

                                        <a href="location_form.php?id=<?php echo $loc['id']; ?>" class="hover:text-cyan-600 transition-colors" title="Ubah">
                                            <i class="fa-solid fa-pen-to-square w-5 h-5"></i>
                                        </a>

                                        <button onclick="openDeleteModal('<?php url('logic/locations/delete.php?id=' . $loc['id']); ?>')" class="hover:text-red-600 transition-colors" title="Hapus">
                                            <i class="fa-solid fa-trash w-5 h-5"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
