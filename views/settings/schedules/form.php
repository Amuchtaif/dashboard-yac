<?php
require_once '../../../config/app.php';
require_once '../../../config/database.php';

check_login();
check_permission('manage_employees');

$db = new Database();
$conn = $db->getConnection();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$is_edit = $id > 0;
$page_title = $is_edit ? "Ubah Jadwal" : "Buat Jadwal";

// Initialize Schedule
$schedule = [
    'name' => ''
];

// Day mapping for UI
$day_map = [
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu',
    'Sunday' => 'Minggu'
];

// Initialize Days Defaults
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$schedule_details = [];
foreach ($days as $day) {
    // Default: 08:00 - 17:00, not off
    $schedule_details[$day] = [
        'start_time' => '08:00',
        'end_time' => '17:00',
        'is_day_off' => 0
    ];
}

// Fetch Logic (Get)
if ($is_edit) {
    $stmt = $conn->prepare("SELECT * FROM work_schedules WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$schedule) {
        header("Location: " . BASE_URL . "views/settings/schedules/index.php?error=" . urlencode("Jadwal tidak ditemukan"));
        exit;
    }

    // Fetch Details
    $stmtDetails = $conn->prepare("SELECT * FROM work_schedule_details WHERE schedule_id = :id");
    $stmtDetails->execute([':id' => $id]);
    $rows = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);

    // Map DB details to array
    foreach ($rows as $row) {
        $schedule_details[$row['day_name']] = [
            'start_time' => $row['start_time'] ? date('H:i', strtotime($row['start_time'])) : '',
            'end_time' => $row['end_time'] ? date('H:i', strtotime($row['end_time'])) : '',
            'is_day_off' => $row['is_day_off']
        ];
    }
}

// Handle Logic (Post)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $details = $_POST['details']; // Array of days

    if ($name && is_array($details)) {
        try {
            $conn->beginTransaction();

            if ($is_edit) {
                // Update Parent
                $stmt = $conn->prepare("UPDATE work_schedules SET name = :name WHERE id = :id");
                $stmt->execute([':name' => $name, ':id' => $id]);
                
                // Delete old details to re-insert
                $conn->prepare("DELETE FROM work_schedule_details WHERE schedule_id = :id")->execute([':id' => $id]);
                $current_id = $id;

            } else {
                // Insert Parent
                $stmt = $conn->prepare("INSERT INTO work_schedules (name) VALUES (:name)");
                $stmt->execute([':name' => $name]);
                $current_id = $conn->lastInsertId();
            }

            // Insert Details
            $stmtDetail = $conn->prepare("INSERT INTO work_schedule_details (schedule_id, day_name, start_time, end_time, is_day_off) VALUES (:sid, :day, :start, :end, :off)");

            foreach ($days as $day) {
                $dayData = $details[$day];
                $is_off = isset($dayData['is_day_off']) ? 1 : 0;
                $start = $is_off ? null : $dayData['start_time'];
                $end = $is_off ? null : $dayData['end_time'];

                $stmtDetail->execute([
                    ':sid' => $current_id,
                    ':day' => $day,
                    ':start' => $start,
                    ':end' => $end,
                    ':off' => $is_off
                ]);
            }

            $conn->commit();
            header("Location: " . BASE_URL . "/views/settings/schedules/index.php?success=" . urlencode("Jadwal berhasil disimpan"));
            exit;

        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Error simpan jadwal: " . $e->getMessage();
        }
    } else {
        $error = "Nama jadwal wajib diisi.";
    }
}

include '../../layouts/header.php';
?>

<div class="w-full pb-10">
    <!-- Breadcrumb -->
    <nav class="flex mb-4" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
            <li class="inline-flex items-center">
                <a href="<?php url('views/settings/schedules/index.php'); ?>"
                    class="inline-flex items-center text-slate-500 hover:text-slate-700">
                    Jadwal
                </a>
            </li>
            <li>
                <div class="flex items-center">
                    <i class="fa-solid fa-chevron-right w-3 h-3 text-gray-400 mx-1"></i>
                    <span class="ml-1 font-medium text-slate-800">
                        <?php echo $is_edit ? 'Ubah' : 'Buat'; ?>
                    </span>
                </div>
            </li>
        </ol>
    </nav>

    <div class="mb-8">
        <h1 class="text-2xl font-bold text-slate-900">
            <?php echo $page_title; ?>
        </h1>
        <p class="mt-2 text-sm text-slate-600">Konfigurasi jam kerja mingguan dan shift.</p>
    </div>

    <?php if (isset($error)): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
        <form action="" method="POST">
            <div class="px-4 py-6 sm:p-8">
                <!-- Schedule Name -->
                <div class="mb-8 max-w-md">
                    <label for="name" class="block text-sm font-medium leading-6 text-gray-900">Nama Jadwal</label>
                    <div class="mt-2">
                        <input type="text" name="name" id="name" required
                            value="<?php echo htmlspecialchars($schedule['name']); ?>"
                            class="block w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all placeholder:text-slate-400 shadow-sm"
                            placeholder="Misal: Shift Reguler">
                    </div>
                </div>

                <!-- Daily Grid -->
                <div class="border rounded-lg overflow-hidden">
                    <div class="bg-gray-50 px-4 py-3 border-b grid grid-cols-12 gap-4 text-sm font-semibold text-gray-900">
                        <div class="col-span-3">Hari</div>
                        <div class="col-span-2 text-center">Libur</div>
                        <div class="col-span-7 grid grid-cols-2 gap-4">
                            <div>Jam Masuk</div>
                            <div>Jam Pulang</div>
                        </div>
                    </div>
                    
                    <div class="divide-y divide-gray-200">
                        <?php foreach ($days as $day): 
                            $detail = $schedule_details[$day];
                            $is_off = $detail['is_day_off'];
                        ?>
                        <div class="px-4 py-3 grid grid-cols-12 gap-4 items-center hover:bg-gray-50 transition-colors">
                            <div class="col-span-3 font-medium text-gray-900">
                                <?php echo $day_map[$day]; ?>
                            </div>
                            <div class="col-span-2 text-center">
                                <input type="checkbox" name="details[<?php echo $day; ?>][is_day_off]" 
                                    onchange="toggleDay('<?php echo $day; ?>')"
                                    id="off_<?php echo $day; ?>"
                                    value="1" 
                                    class="h-4 w-4 rounded border-gray-300 text-cyan-600 focus:ring-cyan-600"
                                    <?php echo $is_off ? 'checked' : ''; ?>>
                            </div>
                            <div class="col-span-7 grid grid-cols-2 gap-4">
                                <input type="time" name="details[<?php echo $day; ?>][start_time]" 
                                    id="start_<?php echo $day; ?>"
                                    value="<?php echo htmlspecialchars($detail['start_time']); ?>"
                                    class="block w-full px-3 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all shadow-sm disabled:bg-slate-100 disabled:text-slate-400"
                                    <?php echo $is_off ? 'disabled' : ''; ?>>
                                
                                <input type="time" name="details[<?php echo $day; ?>][end_time]" 
                                    id="end_<?php echo $day; ?>"
                                    value="<?php echo htmlspecialchars($detail['end_time']); ?>"
                                    class="block w-full px-3 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all shadow-sm disabled:bg-slate-100 disabled:text-slate-400"
                                    <?php echo $is_off ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-x-6 border-t border-gray-900/10 px-4 py-4 sm:px-8">
                <a href="<?php url('views/settings/schedules/index.php'); ?>"
                    class="text-sm font-semibold leading-6 text-gray-900">Batal</a>
                <button type="submit"
                    class="rounded-md bg-cyan-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-cyan-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 transition-colors">
                    <?php echo $is_edit ? 'Perbarui Jadwal' : 'Buat Jadwal'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleDay(day) {
    const isOff = document.getElementById('off_' + day).checked;
    const startInput = document.getElementById('start_' + day);
    const endInput = document.getElementById('end_' + day);

    if (isOff) {
        startInput.disabled = true;
        endInput.disabled = true;
        startInput.value = ''; 
        endInput.value = '';
    } else {
        startInput.disabled = false;
        endInput.disabled = false;
        if (!startInput.value) startInput.value = '08:00'; 
        if (!endInput.value) endInput.value = '17:00';
    }
}
</script>

<?php include '../../layouts/footer.php'; ?>
