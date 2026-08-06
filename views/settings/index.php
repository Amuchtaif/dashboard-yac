<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$page_title = "Global Geofencing";

$db = new Database();
$conn = $db->getConnection();

// Fetch current settings
$settings = [];
try {
    $stmt = $conn->query("SELECT * FROM office_settings WHERE id = 1 LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table might not exist or empty
}

// Defaults
$lat = $settings['latitude'] ?? '';
$long = $settings['longitude'] ?? '';
$radius = $settings['radius_meters'] ?? '100';

include '../layouts/header.php';
?>

<div class="pb-10">

    <!-- Breadcrumbs -->
    <nav class="flex mb-4" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3 text-xs text-slate-500">
            <li class="inline-flex items-center">
                <a href="<?php url('views/dashboard/index.php'); ?>"
                    class="hover:text-slate-800 flex items-center gap-1">
                    <i class="fa-solid fa-house w-3 h-3"></i>
                    Home
                </a>
            </li>
            <li>
                <div class="flex items-center">
                    <i class="fa-solid fa-chevron-right w-3 h-3 text-gray-400 mx-1"></i>
                    <span class="ml-1 text-slate-500 hover:text-slate-800">Settings</span>
                </div>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <i class="fa-solid fa-chevron-right w-3 h-3 text-gray-400 mx-1"></i>
                    <span class="ml-1 font-medium text-slate-800">Geofencing</span>
                </div>
            </li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-slate-900 sm:truncate sm:text-3xl sm:tracking-tight">Global
                Geofencing Settings</h2>
            <p class="mt-1 text-sm text-slate-500">Configure the primary coordinate boundaries for employee check-ins.
                Attendance logs outside this radius will be flagged.</p>
        </div>
        <div class="mt-4 flex md:ml-4 md:mt-0">
            <button type="button"
                class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 transition-colors">
                <i class="fa-solid fa-clock -ml-1 mr-2 h-4 w-4 text-slate-500"></i>
                View Audit Log
            </button>
        </div>
    </div>


    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- Left Column: Configuration Form -->
        <div class="lg:col-span-2">
            <form action="<?php url('logic/settings/update.php'); ?>" method="POST"
                class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-6 border-b border-slate-100 flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-shield-halved w-6 h-6 text-cyan-600"></i>
                        <h3 class="text-lg font-bold text-slate-800">Office Coordinates</h3>
                    </div>
                    <span
                        class="inline-flex items-center rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
                        Active
                    </span>
                </div>

                <div class="p-8 space-y-8">
                    <!-- Coordinates Row -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Latitude -->
                        <div>
                            <label for="office_lat"
                                class="block text-sm font-semibold text-slate-700 mb-2">Latitude</label>
                            <div class="relative rounded-md shadow-sm">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <span class="text-gray-400 sm:text-sm font-bold">LAT</span>
                                </div>
                                <input type="text" name="office_lat" id="office_lat"
                                    value="<?php echo htmlspecialchars($lat); ?>"
                                    class="block w-full rounded-lg border-slate-200 pl-12 pr-10 py-3 text-slate-700 focus:border-cyan-500 focus:ring-cyan-500 sm:text-sm shadow-sm"
                                    placeholder="e.g. 34.0522" required>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                                    <i class="fa-solid fa-magnifying-glass h-5 w-5 text-green-500"></i>
                                </div>
                            </div>
                            <p class="mt-2 text-xs text-slate-400">Decimal degrees (e.g., 34.0522)</p>
                        </div>

                        <!-- Longitude -->
                        <div>
                            <label for="office_long"
                                class="block text-sm font-semibold text-slate-700 mb-2">Longitude</label>
                            <div class="relative rounded-md shadow-sm">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <span class="text-gray-400 sm:text-sm font-bold">LNG</span>
                                </div>
                                <input type="text" name="office_long" id="office_long"
                                    value="<?php echo htmlspecialchars($long); ?>"
                                    class="block w-full rounded-lg border-slate-200 pl-12 pr-10 py-3 text-slate-700 focus:border-cyan-500 focus:ring-cyan-500 sm:text-sm shadow-sm"
                                    placeholder="e.g. -118.2437" required>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                                    <i class="fa-solid fa-magnifying-glass h-5 w-5 text-green-500"></i>
                                </div>
                            </div>
                            <p class="mt-2 text-xs text-slate-400">Decimal degrees (e.g., -118.2437)</p>
                        </div>
                    </div>

                    <!-- Radius Slider -->
                    <div>
                        <div class="flex justify-between items-center mb-4">
                            <label for="geofence_radius" class="block text-sm font-semibold text-slate-700">Allowed
                                Geofence Radius</label>
                            <span
                                class="inline-flex items-center rounded-md bg-cyan-50 px-2 py-1 text-xs font-bold text-cyan-700 ring-1 ring-inset ring-cyan-700/10"
                                id="radius-display">
                                <?php echo htmlspecialchars($radius); ?> meters
                            </span>
                        </div>
                        <input type="range" name="geofence_radius" id="geofence_radius" min="50" max="1000" step="10"
                            value="<?php echo htmlspecialchars($radius); ?>"
                            class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-cyan-600">
                        <div class="flex justify-between text-xs text-slate-400 mt-2">
                            <span>50m</span>
                            <span>1000m</span>
                        </div>

                        <div class="rounded-md bg-yellow-50 p-4 mt-6 border border-yellow-100">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fa-solid fa-triangle-exclamation h-5 w-5 text-yellow-400"></i>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-yellow-800">Attention needed</h3>
                                    <div class="mt-2 text-sm text-yellow-700">
                                        <p>Setting a radius smaller than 50 meters may cause check-in issues due to GPS
                                            drift on mobile devices.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div
                    class="bg-slate-50 px-6 py-4 flex flex-col sm:flex-row items-center justify-between gap-4 border-t border-slate-100">
                    <button type="button" onclick="getCurrentLocation()"
                        class="text-sm font-medium text-cyan-600 hover:text-cyan-700 flex items-center gap-2">
                        <i class="fa-solid fa-arrow-pointer w-5 h-5"></i>
                        Use My Current Location
                    </button>

                    <div class="flex items-center gap-3 w-full sm:w-auto">
                        <button type="reset"
                            class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800 transition-colors">Reset</button>
                        <button type="submit"
                            class="px-6 py-2 bg-cyan-600 hover:bg-cyan-700 text-white text-sm font-bold rounded-lg shadow-sm hover:shadow-md transition-all focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 w-full sm:w-auto">
                            Save Configuration
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Right Column: Map Preview -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden h-full flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h3 class="text-base font-bold text-slate-800">Location Preview</h3>
                </div>
                <div
                    class="relative flex-1 bg-slate-50 min-h-[300px] flex items-center justify-center overflow-hidden group">
                    <!-- Google Map Embed -->
                    <iframe id="map-preview" width="100%" height="100%" frameborder="0" style="border:0"
                        src="https://maps.google.com/maps?q=<?php echo ($long && $lat) ? "$lat,$long" : "34.0522,-118.2437"; ?>&z=15&output=embed"
                        allowfullscreen>
                    </iframe>

                    <!-- Overlay to prevent interaction if needed (optional, removed for better UX) -->
                </div>
                <div class="p-4 bg-slate-50 border-t border-slate-100">
                    <div class="flex gap-3">
                        <i class="fa-solid fa-circle-info w-5 h-5 text-slate-400 flex-shrink-0"></i>
                        <p class="text-xs text-slate-500 leading-relaxed">
                            Verify the location on the map. Dragging is not supported in preview mode; update
                            coordinates to move the pin.
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
        display.textContent = this.value + ' meters';
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
            alert("Geolocation is not supported by this browser.");
        }
    }

    function showPosition(position) {
        document.getElementById('office_lat').value = position.coords.latitude;
        document.getElementById('office_long').value = position.coords.longitude;
        updateMap();
    }

    function showError(error) {
        switch (error.code) {
            case error.PERMISSION_DENIED:
                alert("User denied the request for Geolocation.");
                break;
            case error.POSITION_UNAVAILABLE:
                alert("Location information is unavailable.");
                break;
            case error.TIMEOUT:
                alert("The request to get user location timed out.");
                break;
            case error.UNKNOWN_ERROR:
                alert("An unknown error occurred.");
                break;
        }
    }
</script>

<?php include '../layouts/footer.php'; ?>