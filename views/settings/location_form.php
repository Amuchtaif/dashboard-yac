<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$db = new Database();
$conn = $db->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$location = null;

if ($id) {
    $stmt = $conn->prepare("SELECT * FROM locations WHERE id = ?");
    $stmt->execute([$id]);
    $location = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$location) {
        header("Location: locations.php?error=Data tidak ditemukan");
        exit;
    }
    $page_title = "Ubah Lokasi";
} else {
    $page_title = "Tambah Lokasi";
    $location = [
        'name' => '',
        'latitude' => '-6.175392', // Default Jakarta
        'longitude' => '106.827153',
        'radius_meter' => 100,
        'is_active' => 1
    ];
}

include '../layouts/header.php';
?>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

<style>
    #map {
        height: 350px;
        width: 100%;
        border-radius: 0.75rem;
        z-index: 10;
        border: 1px solid #e2e8f0;
    }
    .leaflet-container {
        font-family: inherit;
    }
</style>

<div class="pb-10">
    <!-- Breadcrumbs -->
    <nav class="flex mb-4" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3 text-xs text-slate-500">
            <li class="inline-flex items-center">
                <a href="<?php url('views/dashboard/index.php'); ?>" class="hover:text-slate-800 flex items-center gap-1">
                    <i class="fa-solid fa-house w-3 h-3"></i>
                    Beranda
                </a>
            </li>
            <li>
                <div class="flex items-center">
                    <i class="fa-solid fa-chevron-right w-3 h-3 text-gray-400 mx-1"></i>
                    <a href="locations.php" class="ml-1 text-slate-500 hover:text-slate-800">Manajemen Lokasi</a>
                </div>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <i class="fa-solid fa-chevron-right w-3 h-3 text-gray-400 mx-1"></i>
                    <span class="ml-1 font-medium text-slate-800"><?php echo $page_title; ?></span>
                </div>
            </li>
        </ol>
    </nav>

    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-slate-900 sm:truncate sm:text-3xl sm:tracking-tight"><?php echo $page_title; ?></h2>
            <p class="mt-1 text-sm text-slate-500">Konfigurasikan detail lokasi dan radius geofencing.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2">
            <form action="../../logic/locations/<?php echo $id ? 'edit.php' : 'add.php'; ?>" method="POST" class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <?php if ($id): ?>
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                <?php endif; ?>
                
                <div class="p-8 space-y-8">
                    <!-- Location Name -->
                    <div>
                        <label for="name" class="block text-sm font-semibold text-slate-700 mb-2">Nama Lokasi</label>
                        <input type="text" name="name" id="name" required value="<?php echo htmlspecialchars($location['name'] ?? ''); ?>" 
                               class="block w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all shadow-sm"
                               placeholder="Contoh: Kantor Cabang Bekasi">
                    </div>

                    <!-- Coordinates Row -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="latitude" class="block text-sm font-semibold text-slate-700 mb-2">Latitude</label>
                            <div class="relative rounded-md shadow-sm">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <span class="text-slate-400 text-xs font-bold font-mono">LAT</span>
                                </div>
                                <input type="text" name="latitude" id="latitude" required value="<?php echo htmlspecialchars($location['latitude'] ?? ''); ?>" 
                                       class="block w-full pl-12 pr-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all shadow-sm"
                                       placeholder="-6.123456">
                            </div>
                        </div>
                        <div>
                            <label for="longitude" class="block text-sm font-semibold text-slate-700 mb-2">Longitude</label>
                            <div class="relative rounded-md shadow-sm">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <span class="text-slate-400 text-xs font-bold font-mono">LNG</span>
                                </div>
                                <input type="text" name="longitude" id="longitude" required value="<?php echo htmlspecialchars($location['longitude'] ?? ''); ?>" 
                                       class="block w-full pl-12 pr-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all shadow-sm"
                                       placeholder="106.123456">
                            </div>
                        </div>
                    </div>

                    <!-- Radius Slider -->
                    <div>
                        <div class="flex justify-between items-center mb-4">
                            <div class="flex items-center gap-2">
                                <label for="radius_meter" class="block text-sm font-semibold text-slate-700">Radius Geofence</label>
                                <i class="fa-solid fa-circle-info w-4 h-4 text-slate-400 cursor-help"></i>
                            </div>
                            <span class="inline-flex items-center rounded-md bg-cyan-50 px-2 py-1 text-xs font-bold text-cyan-700 ring-1 ring-inset ring-cyan-700/10 shadow-sm" id="radius-display">
                                <?php echo htmlspecialchars($location['radius_meter'] ?? 100); ?> meter
                            </span>
                        </div>
                        <input type="range" name="radius_meter" id="radius_meter" min="50" max="1000" step="10" 
                               value="<?php echo htmlspecialchars($location['radius_meter'] ?? 100); ?>" 
                               class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-cyan-600">
                        <div class="flex justify-between text-[10px] text-slate-400 font-bold mt-2 uppercase tracking-tighter">
                             <span>50m</span>
                             <span>250m</span>
                             <span>500m</span>
                             <span>750m</span>
                             <span>1000m</span>
                        </div>
                    </div>

                    <div class="pt-6 border-t border-slate-100">
                        <div class="flex items-center gap-3">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="is_active" id="is_active" value="1" <?php echo (!isset($location['is_active']) || $location['is_active'] == 1) ? 'checked' : ''; ?> class="sr-only peer">
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-cyan-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-cyan-600 transition-colors"></div>
                                <span class="ms-3 text-sm font-medium text-slate-700">Status Aktif</span>
                            </label>
                            <p class="text-xs text-slate-400">Lokasi nonaktif tidak akan muncul di aplikasi pegawai.</p>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-50 px-6 py-4 flex flex-col sm:flex-row items-center justify-between gap-4 border-t border-slate-100">
                    <button type="button" onclick="getCurrentLocation()"
                        class="text-sm font-medium text-cyan-600 hover:text-cyan-700 flex items-center gap-2 group">
                        <i class="fa-solid fa-arrow-pointer w-5 h-5 group-hover:animate-pulse"></i>
                        Gunakan Lokasi Saya Saat Ini
                    </button>
                    <div class="flex items-center gap-3 w-full sm:w-auto">
                        <a href="locations.php" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800 transition-colors">Batal</a>
                        <button type="submit" class="px-8 py-2.5 bg-cyan-600 hover:bg-cyan-700 text-white text-sm font-bold rounded-lg shadow-sm hover:shadow-md transition-all focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 w-full sm:w-auto">
                            Simpan Perubahan
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Sidebar Helper -->
        <div class="lg:col-span-1 space-y-6">
             <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                 <div class="px-6 py-4 border-b border-slate-100 bg-slate-50">
                     <h3 class="font-bold text-slate-800 flex items-center gap-2 text-sm uppercase tracking-wider">
                         <i class="fa-solid fa-location-dot w-4 h-4 text-cyan-500"></i>
                         Pratinjau Lokasi
                     </h3>
                 </div>
                 <div class="p-4">
                     <div id="map"></div>
                     <div class="mt-4 p-4 rounded-lg bg-cyan-50 border border-cyan-100">
                         <div class="flex gap-3">
                             <i class="fa-solid fa-circle-info w-5 h-5 text-cyan-600 flex-shrink-0"></i>
                             <div class="space-y-1">
                                 <p class="text-xs text-cyan-800 font-bold leading-none">Petunjuk:</p>
                                 <p class="text-[11px] text-cyan-600 leading-relaxed font-medium">
                                     Klik pada peta untuk mengubah koordinat atau geser slider radius untuk melihat jangkauan absensi.
                                 </p>
                             </div>
                         </div>
                     </div>
                 </div>
             </div>
        </div>
    </div>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<script>
    const slider = document.getElementById('radius_meter');
    const display = document.getElementById('radius-display');
    const latInput = document.getElementById('latitude');
    const longInput = document.getElementById('longitude');

    // --- Initialize Map ---
    let lat = parseFloat(latInput.value) || -6.175392;
    let lng = parseFloat(longInput.value) || 106.827153;
    let radius = parseInt(slider.value) || 100;

    const map = L.map('map').setView([lat, lng], 16);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    // Marker
    const marker = L.marker([lat, lng], { draggable: true }).addTo(map);

    // Circle (Radius)
    const circle = L.circle([lat, lng], {
        color: '#0891b2',
        fillColor: '#0891b2',
        fillOpacity: 0.2,
        radius: radius
    }).addTo(map);

    // --- Event Listeners ---

    // Update Circle when slider moves
    slider.addEventListener('input', function() {
        const val = this.value;
        display.textContent = val + ' meter';
        circle.setRadius(val);
    });

    // Update lat/long inputs when marker is moved
    marker.on('dragend', function(e) {
        const position = marker.getLatLng();
        latInput.value = position.lat.toFixed(8);
        longInput.value = position.lng.toFixed(8);
        circle.setLatLng(position);
    });

    // Update map when inputs change
    const updateFromInputs = () => {
        const newLat = parseFloat(latInput.value);
        const newLng = parseFloat(longInput.value);
        if (!isNaN(newLat) && !isNaN(newLng)) {
            const newPos = [newLat, newLng];
            marker.setLatLng(newPos);
            circle.setLatLng(newPos);
            map.panTo(newPos);
        }
    };

    latInput.addEventListener('input', updateFromInputs);
    longInput.addEventListener('input', updateFromInputs);

    // Allow clicking map to set coordinates
    map.on('click', function(e) {
        const pos = e.latlng;
        marker.setLatLng(pos);
        circle.setLatLng(pos);
        latInput.value = pos.lat.toFixed(8);
        longInput.value = pos.lng.toFixed(8);
    });

    // Current Location Function
    window.getCurrentLocation = function() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                const myLat = position.coords.latitude;
                const myLng = position.coords.longitude;
                latInput.value = myLat.toFixed(8);
                longInput.value = myLng.toFixed(8);
                
                const newPos = [myLat, myLng];
                marker.setLatLng(newPos);
                circle.setLatLng(newPos);
                map.setView(newPos, 16);
            }, function(error) {
                alert("Gagal mengambil lokasi: " + error.message);
            });
        } else {
            alert("Browser Anda tidak mendukung geolokasi.");
        }
    };

    // Fix map loading issue in hidden/resized containers
    setTimeout(() => {
        map.invalidateSize();
    }, 500);
</script>

<?php include '../layouts/footer.php'; ?>
