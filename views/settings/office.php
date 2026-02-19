<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$page_title = "Pengaturan Kantor";

$db = new Database();
$conn = $db->getConnection();

$message = "";
$msg_type = "";

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $office_name = $_POST['office_name'] ?? 'Kantor Pusat';
    $latitude = $_POST['office_lat'] ?? '';
    $longitude = $_POST['office_long'] ?? '';
    // Radius comes from slider 'geofence_radius'
    $radius_meters = $_POST['geofence_radius'] ?? 100;

    if ($latitude && $longitude && $radius_meters) {
        try {
            // Update logic (ID = 1)
            $sql = "UPDATE locations SET 
                    name = :name, 
                    latitude = :lat, 
                    longitude = :long, 
                    radius_meter = :rad 
                    WHERE id = 1";

            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([
                ':name' => $office_name,
                ':lat' => $latitude,
                ':long' => $longitude,
                ':rad' => $radius_meters
            ]);

            if ($result) {
                // Redirect to self with success for clean refresh
                header("Location: " . BASE_URL . "/views/settings/office.php?success=" . urlencode("Pengaturan berhasil diperbarui"));
                exit;
            } else {
                $message = "Gagal memperbarui pengaturan.";
                $msg_type = "error";
            }
        } catch (PDOException $e) {
            $message = "Kesalahan Database: " . $e->getMessage();
            $msg_type = "error";
        }
    } else {
        $message = "Semua bidang wajib diisi.";
        $msg_type = "error";
    }
}

// Fetch Current Settings
$settings = [];
try {
    $stmt = $conn->query("SELECT * FROM locations WHERE id = 1 LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fallback if no row exists
    if (!$settings) {
        $settings = [
            'name' => 'Kantor Pusat',
            'latitude' => '',
            'longitude' => '',
            'radius_meter' => 100
        ];
    }
} catch (PDOException $e) {
    die("Error fetching settings: " . $e->getMessage());
}

$lat = $settings['latitude'];
$long = $settings['longitude'];
$radius = $settings['radius_meter'];
$office_name = $settings['name'];

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
                    <span class="ml-1 font-medium text-slate-800">Lokasi Kantor</span>
                </div>
            </li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-slate-900 sm:truncate sm:text-3xl sm:tracking-tight">Lokasi
                Kantor & Geofencing</h2>
            <p class="mt-1 text-sm text-slate-500">Konfigurasikan batas koordinat utama untuk absensi pegawai.</p>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($message): ?>
        <div
            class="mb-6 rounded-md p-4 <?php echo $msg_type == 'success' ? 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20' : 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/10'; ?>">
            <p class="text-sm font-medium"><?php echo htmlspecialchars($message); ?></p>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- Left Column: Configuration Form -->
        <div class="lg:col-span-2">
            <form action="" method="POST" class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-6 border-b border-slate-100 flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                            class="w-6 h-6 text-cyan-600">
                            <path fill-rule="evenodd"
                                d="M11.54 22.351l.07.04.028.016a.76.76 0 00.723 0l.028-.015.071-.041a16.975 16.975 0 001.144-.742 19.58 19.58 0 002.683-2.282c1.944-1.99 3.963-4.98 3.963-8.827a8.25 8.25 0 00-16.5 0c0 3.846 2.02 6.837 3.963 8.827a19.58 19.58 0 002.682 2.282 16.975 16.975 0 001.145.742zM12 13.5a3 3 0 100-6 3 3 0 000 6z"
                                clip-rule="evenodd" />
                        </svg>
                        <h3 class="text-lg font-bold text-slate-800">Koordinat Kantor</h3>
                    </div>
                </div>

                <div class="p-8 space-y-8">

                    <!-- Office Name (Added back) -->
                    <div>
                        <label for="office_name" class="block text-sm font-semibold text-slate-700 mb-2">Nama
                            Kantor</label>
                        <input type="text" name="office_name" id="office_name"
                            value="<?php echo htmlspecialchars($office_name); ?>"
                            class="block w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all placeholder:text-slate-400 shadow-sm"
                            placeholder="misal: Kantor Pusat">
                    </div>

                    <!-- Coordinates Row -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Latitude -->
                        <div>
                            <label for="office_lat" class="block text-sm font-semibold text-slate-700 mb-2">Garis
                                Lintang (Latitude)</label>
                            <div class="relative rounded-md shadow-sm">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <span class="text-gray-400 sm:text-sm font-bold">LAT</span>
                                </div>
                                <input type="text" name="office_lat" id="office_lat"
                                    value="<?php echo htmlspecialchars($lat); ?>"
                                    class="block w-full pl-12 pr-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all placeholder:text-slate-400 shadow-sm"
                                    placeholder="misal: 34.0522" required>
                            </div>
                        </div>

                        <!-- Longitude -->
                        <div>
                            <label for="office_long" class="block text-sm font-semibold text-slate-700 mb-2">Garis Bujur
                                (Longitude)</label>
                            <div class="relative rounded-md shadow-sm">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <span class="text-gray-400 sm:text-sm font-bold">LNG</span>
                                </div>
                                <input type="text" name="office_long" id="office_long"
                                    value="<?php echo htmlspecialchars($long); ?>"
                                    class="block w-full pl-12 pr-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all placeholder:text-slate-400 shadow-sm"
                                    placeholder="misal: -118.2437" required>
                            </div>
                        </div>
                    </div>

                    <!-- Radius Slider -->
                    <div>
                        <div class="flex justify-between items-center mb-4">
                            <label for="geofence_radius" class="block text-sm font-semibold text-slate-700">Radius
                                Geofence yang Diizinkan</label>
                            <span
                                class="inline-flex items-center rounded-md bg-cyan-50 px-2 py-1 text-xs font-bold text-cyan-700 ring-1 ring-inset ring-cyan-700/10"
                                id="radius-display">
                                <?php echo htmlspecialchars($radius); ?> meter
                            </span>
                        </div>
                        <input type="range" name="geofence_radius" id="geofence_radius" min="50" max="1000" step="10"
                            value="<?php echo htmlspecialchars($radius); ?>"
                            class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-cyan-600">
                        <div class="flex justify-between text-xs text-slate-400 mt-2">
                            <span>50m</span>
                            <span>1000m</span>
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div
                    class="bg-slate-50 px-6 py-4 flex flex-col sm:flex-row items-center justify-between gap-4 border-t border-slate-100">
                    <button type="button" onclick="getCurrentLocation()"
                        class="text-sm font-medium text-cyan-600 hover:text-cyan-700 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15.042 21.672L13.684 16.6m0 0l-2.51 2.225.569-9.47 5.227 7.917-3.286-.672zM12 2.25V4.5m5.834.166l-1.591 1.591M20.25 10.5H18M7.757 14.743l-1.59 1.59M6 10.5H3.75m4.007-4.243l-1.59-1.59" />
                        </svg>
                        Gunakan Lokasi Saya Saat Ini
                    </button>

                    <div class="flex items-center gap-3 w-full sm:w-auto">
                        <button type="reset"
                            class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800 transition-colors">Reset</button>
                        <button type="submit"
                            class="px-6 py-2 bg-cyan-600 hover:bg-cyan-700 text-white text-sm font-bold rounded-lg shadow-sm hover:shadow-md transition-all focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 w-full sm:w-auto">
                            Simpan Perubahan
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Right Column: Map Preview -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden h-full flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h3 class="text-base font-bold text-slate-800">Pratinjau Lokasi</h3>
                </div>
                <div
                    class="relative flex-1 bg-slate-50 min-h-[300px] flex items-center justify-center overflow-hidden group">
                    <!-- Google Map Embed -->
                    <iframe id="map-preview" width="100%" height="100%" frameborder="0" style="border:0"
                        src="https://maps.google.com/maps?q=<?php echo ($long && $lat) ? "$lat,$long" : "34.0522,-118.2437"; ?>&z=15&output=embed"
                        allowfullscreen>
                    </iframe>
                </div>
                <div class="p-4 bg-slate-50 border-t border-slate-100">
                    <div class="flex gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                            class="w-5 h-5 text-slate-400 flex-shrink-0">
                            <path fill-rule="evenodd"
                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z"
                                clip-rule="evenodd" />
                        </svg>
                        <p class="text-xs text-slate-500 leading-relaxed">
                            Verifikasi lokasi di peta. Perbarui koordinat untuk memindahkan pin.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const slider = document.getElementById('geofence_radius');
    const display = document.getElementById('radius-display');
    const latInput = document.getElementById('office_lat');
    const longInput = document.getElementById('office_long');
    const mapPreview = document.getElementById('map-preview');

    slider.addEventListener('input', function () {
        display.textContent = this.value + ' meter';
    });

    // Update Map on Input Change
    function updateMap() {
        const lat = latInput.value;
        const long = longInput.value;
        if (lat && long) {
            mapPreview.src = `https://maps.google.com/maps?q=${lat},${long}&z=15&output=embed`;
        }
    }

    latInput.addEventListener('change', updateMap);
    longInput.addEventListener('change', updateMap);

    function getCurrentLocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(showPosition, showError);
        } else {
            alert("Geolokasi tidak didukung oleh browser ini.");
        }
    }

    function showPosition(position) {
        document.getElementById('office_lat').value = position.coords.latitude;
        document.getElementById('office_long').value = position.coords.longitude;
        updateMap();
    }

    function showError(error) {
        alert("Tidak dapat mengambil lokasi (Kode error: " + error.code + ")");
    }
</script>

<?php include '../layouts/footer.php'; ?>