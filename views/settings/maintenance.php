<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('Administrator'); // Only admins can change maintenance mode

$page_title = "Manajemen Maintenance";

$db = new Database();
$conn = $db->getConnection();


// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_maintenance = isset($_POST['is_maintenance']) ? '1' : '0';
    $maintenance_msg = $_POST['maintenance_message'] ?? 'Aplikasi sedang dalam pemeliharaan. Silakan coba lagi nanti.';

    try {
        // Use REPLACE INTO for easy upsert on app_settings
        $stmt1 = $conn->prepare("REPLACE INTO app_settings (setting_key, setting_value) VALUES ('is_maintenance', :val)");
        $stmt1->execute([':val' => $is_maintenance]);

        $stmt2 = $conn->prepare("REPLACE INTO app_settings (setting_key, setting_value) VALUES ('maintenance_message', :val)");
        $stmt2->execute([':val' => $maintenance_msg]);

        // Send FCM Notification to 'maintenance' topic
        try {
            require_once '../../config/fcm_helper.php';
            $fcm = new FcmHelper();
            $fcm->sendTopicData('maintenance', [
                'type' => 'maintenance',
                'status' => ($is_maintenance == '1' ? 'true' : 'false'),
                'message' => $maintenance_msg
            ]);
        } catch (Exception $e) {
            // Log FCM error but don't stop the success message
            error_log("FCM Maintenance Error: " . $e->getMessage());
        }

        $_SESSION['success'] = "Status maintenance berhasil diperbarui.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Kesalahan Database: " . $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Fetch Current Settings
$is_maintenance = '0';
$maintenance_msg = 'Aplikasi sedang dalam pemeliharaan. Silakan coba lagi nanti.';

try {
    $stmt = $conn->query("SELECT * FROM app_settings WHERE setting_key IN ('is_maintenance', 'maintenance_message')");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    if (isset($settings['is_maintenance']))
        $is_maintenance = $settings['is_maintenance'];
    if (isset($settings['maintenance_message']))
        $maintenance_msg = $settings['maintenance_message'];
} catch (PDOException $e) {
    // Ignore, use defaults
}

include '../layouts/header.php';
?>

<div class="pb-10">
    <!-- Breadcrumbs -->
    <nav class="flex mb-4" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3 text-xs text-slate-500">
            <li class="inline-flex items-center">
                <a href="<?php url('views/dashboard/index.php'); ?>"
                    class="hover:text-slate-800 flex items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3">
                        <path fill-rule="evenodd"
                            d="M9.293 2.293a1 1 0 011.414 0l7 7A1 1 0 0117 11h-1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-3a1 1 0 00-1-1H9a1 1 0 00-1 1v3a1 1 0 01-1 1H5a1 1 0 01-1-1v-6H3a1 1 0 01-.707-1.707l7-7z"
                            clip-rule="evenodd" />
                    </svg>
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
                    <span class="ml-1 text-slate-500 hover:text-slate-800">Pengaturan</span>
                </div>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 6 10">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="m1 9 4-4-4-4" />
                    </svg>
                    <span class="ml-1 font-medium text-slate-800">Status Maintenance</span>
                </div>
            </li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-slate-900 sm:truncate sm:text-3xl sm:tracking-tight">Status
                Maintenance Aplikasi</h2>
            <p class="mt-1 text-sm text-slate-500">Aktifkan mode maintenance untuk membatasi akses aplikasi Flutter saat
                melakukan pembaruan sistem.</p>
        </div>
    </div>

    <!-- Alerts -->
    <!-- Global notifications handled by header.php -->


    <div class="max-w-4xl">
        <form action="" method="POST"
            class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden transition-all duration-300">
            <div
                class="px-8 py-6 border-b border-slate-100 flex items-center justify-between <?php echo $is_maintenance == '1' ? 'bg-amber-50/50' : 'bg-emerald-50/50'; ?>">
                <div class="flex items-center gap-4">
                    <div
                        class="p-3 rounded-xl <?php echo $is_maintenance == '1' ? 'bg-amber-100 text-amber-600' : 'bg-emerald-100 text-emerald-600'; ?>">
                        <?php if ($is_maintenance == '1'): ?>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                        <?php else: ?>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">Status Saat Ini:
                            <?php echo $is_maintenance == '1' ? 'Maintenance' : 'Aktif'; ?></h3>
                        <p class="text-xs text-slate-500">Terakhir diperbarui: <?php echo date('d M Y, H:i'); ?></p>
                    </div>
                </div>

                <!-- Toggle Switch -->
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="is_maintenance" class="sr-only peer" <?php echo $is_maintenance == '1' ? 'checked' : ''; ?>>
                    <div
                        class="w-14 h-7 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-cyan-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-cyan-600">
                    </div>
                </label>
            </div>

            <div class="p-8 space-y-6">
                <div>
                    <label for="maintenance_message" class="block text-sm font-semibold text-slate-700 mb-2">Pesan
                        Maintenance</label>
                    <textarea name="maintenance_message" id="maintenance_message" rows="4"
                        class="block w-full px-4 py-3 rounded-xl border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all placeholder:text-slate-400 shadow-sm"
                        placeholder="Tuliskan alasan maintenance yang akan dilihat pengguna di aplikasi Flutter..."><?php echo htmlspecialchars($maintenance_msg); ?></textarea>
                    <p class="mt-2 text-xs text-slate-400 italic">Pesan ini akan muncul saat aplikasi dibuka di
                        perangkat Android/iOS.</p>
                </div>

            </div>

            <div class="bg-slate-50 px-8 py-4 flex justify-end gap-3 border-t border-slate-100">
                <button type="reset"
                    class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800 transition-colors">Batal</button>
                <button type="submit"
                    class="px-8 py-2 bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold rounded-lg shadow-sm hover:shadow-md transition-all focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">
                    Simpan Konfigurasi
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>