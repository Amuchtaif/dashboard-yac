<?php
require_once '../../config/app.php';
require_once '../../config/db_mysqli.php';

check_login();

$page_title = "Manajemen Rapat";

// Filter Logic
$division_id = isset($_GET['division_id']) ? $_GET['division_id'] : '';
$whereClause = "";
$params = [];
$types = "";

if (!empty($division_id)) {
    $whereClause = "WHERE m.division_id = ?";
    $params[] = $division_id;
    $types .= "i";
}

// 1. Fetch Meetings
$sql = "SELECT m.*, d.name as division_name 
        FROM meetings m 
        LEFT JOIN divisions d ON m.division_id = d.id 
        $whereClause 
        ORDER BY m.meeting_date DESC, m.start_time ASC";

$stmt = $mysqli->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$meetings = [];
while ($row = $result->fetch_assoc()) {
    $meetings[] = $row;
}

// 2. Fetch Divisions for Dropdown
$resDiv = $mysqli->query("SELECT * FROM divisions ORDER BY name ASC");
$divisions = [];
while ($row = $resDiv->fetch_assoc()) {
    $divisions[] = $row;
}

include '../layouts/header.php';
?>

<div class="min-h-screen pb-10">
    <!-- Top Header Section -->
    <div class="flex justify-between items-start mb-8 pt-6">
        <div>
            <div class="flex items-center gap-2 text-sm text-slate-500 mb-1">
                <span>Dashboard</span>
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
                <span class="font-medium text-indigo-600">Manajemen Rapat</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900">Manajemen Rapat</h1>
            <p class="mt-1 text-slate-500">Kelola jadwal rapat dan notulensi antar divisi.</p>
        </div>
        <div class="flex space-x-3">
            <!-- Filter Dropdown -->
            <form method="GET" class="relative">
                <select name="division_id" onchange="this.form.submit()"
                    class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-lg shadow-sm border bg-white">
                    <option value="">Semua Divisi</option>
                    <?php foreach ($divisions as $div): ?>
                        <option value="<?= $div['id'] ?>" <?= $div['id'] == $division_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($div['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <a href="create.php"
                class="bg-indigo-50 text-indigo-700 hover:bg-indigo-100 px-4 py-2 rounded-lg text-sm font-medium flex items-center transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Buat Rapat Baru
            </a>
        </div>
    </div>

    <!-- Stats / Cards View for Mobile, Table for Desktop -->
    <div class="bg-white shadow-sm rounded-xl overflow-hidden border border-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col"
                            class="px-6 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">
                            Title / Division</th>
                        <th scope="col"
                            class="px-6 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">
                            Date & Time</th>
                        <th scope="col"
                            class="px-6 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">
                            Type</th>
                        <th scope="col"
                            class="px-6 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">
                            Location</th>
                        <th scope="col"
                            class="px-6 py-4 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">
                            Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-slate-200">
                    <?php if (count($meetings) === 0): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-slate-400">
                                Tidak ada jadwal rapat ditemukan.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($meetings as $m): ?>
                        <tr class="hover:bg-slate-50 transition-colors duration-150">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex flex-col">
                                    <span class="text-sm font-semibold text-slate-900">
                                        <?= htmlspecialchars($m['title']) ?>
                                    </span>
                                    <span class="text-xs text-indigo-500 font-medium">
                                        <?= htmlspecialchars($m['division_name']) ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-slate-700 font-medium">
                                    <?= date('D, d M Y', strtotime($m['meeting_date'])) ?>
                                </div>
                                <div class="text-xs text-slate-500">
                                    <?= date('H:i', strtotime($m['start_time'])) ?> -
                                    <?= date('H:i', strtotime($m['end_time'])) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($m['type'] === 'online'): ?>
                                    <span
                                        class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-sky-100 text-sky-800 border border-sky-200">
                                        Online
                                    </span>
                                <?php else: ?>
                                    <span
                                        class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        Offline
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-slate-600 truncate max-w-xs"
                                    title="<?= htmlspecialchars($m['location']) ?>">
                                    <?= htmlspecialchars($m['location']) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium space-x-2">
                                <a href="details.php?id=<?= $m['id'] ?>"
                                    class="inline-flex items-center text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-md transition-colors">
                                    View
                                </a>
                                <a href="edit.php?id=<?= $m['id'] ?>"
                                    class="inline-flex items-center text-amber-600 hover:text-amber-900 bg-amber-50 hover:bg-amber-100 px-3 py-1.5 rounded-md transition-colors">
                                    Edit
                                </a>
                                <button onclick="confirmDelete(<?= $m['id'] ?>)"
                                    class="inline-flex items-center text-rose-600 hover:text-rose-900 bg-rose-50 hover:bg-rose-100 px-3 py-1.5 rounded-md transition-colors">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function confirmDelete(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#4f46e5', // Indigo 600
            cancelButtonColor: '#ef4444', // Red 500
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                // Perform delete via fetch
                fetch('../../logic/meetings/delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire(
                                'Deleted!',
                                'Meeting has been deleted.',
                                'success'
                            ).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', 'Failed to delete meeting.', 'error');
                        }
                    })
                    .catch(err => {
                        Swal.fire('Error', 'Something went wrong.', 'error');
                    });
            }
        });
    }
</script>

<?php include '../layouts/footer.php'; ?>