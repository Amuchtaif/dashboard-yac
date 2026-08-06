<?php
require_once '../../../config/app.php';
require_once '../../../config/database.php';

check_permission('can_access_kesantrian');

$page_title = "Absensi Asrama";
$db = new Database();
$conn = $db->getConnection();

$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Fetch Active Academic Year
$active_year = $conn->query("SELECT id, name, semester FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);

// Fetch rooms with attendance status for the selected date
$rooms_query = "
    SELECT 
        br.id, 
        br.room_name, 
        (SELECT GROUP_CONCAT(e.full_name SEPARATOR ', ') FROM boarding_room_supervisors brs JOIN employees e ON brs.supervisor_id = e.id WHERE brs.room_id = br.id) as supervisor_name,
        COUNT(DISTINCT CASE WHEN s.status = 'Aktif' THEN brm.student_id END) as total_students,
        COUNT(DISTINCT ba.id) as total_attendance_count
    FROM boarding_rooms br
    LEFT JOIN boarding_room_members brm ON brm.room_id = br.id
    LEFT JOIN students s ON brm.student_id = s.id
    LEFT JOIN boarding_attendances ba ON ba.room_id = br.id AND ba.date = ?
    GROUP BY br.id, br.room_name
    ORDER BY CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(br.room_name, ' (', 1), ' ', -1) AS UNSIGNED) ASC, br.room_name ASC
";
$stmt = $conn->prepare($rooms_query);
$stmt->execute([$date]);
$rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../../layouts/header.php';
?>

<div class="space-y-6 pb-12">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-800">Absensi Asrama</h1>
                <?php if ($active_year): ?>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        TA: <?php echo htmlspecialchars($active_year['name'] . ' (' . $active_year['semester'] . ')'); ?>
                    </span>
                <?php endif; ?>
            </div>
            <p class="text-slate-500 mt-1">Pilih asrama untuk melihat data absensi santri.</p>
        </div>
        <div class="mt-4 sm:mt-0">
             <div class="flex items-center gap-3 bg-white p-2 rounded-xl border border-slate-200">
                <label class="text-xs font-bold text-slate-400 uppercase ml-2">Tanggal:</label>
                <input type="date" value="<?php echo $date; ?>" 
                    onchange="window.location.href='?date=' + this.value"
                    class="border-none bg-transparent text-sm font-bold text-slate-700 focus:ring-0">
            </div>
        </div>
    </div>

    <!-- Rooms Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (count($rooms) > 0): ?>
            <?php foreach ($rooms as $room): ?>
                <?php 
                    $is_fully_marked = $room['total_attendance_count'] >= $room['total_students'] && $room['total_students'] > 0;
                    $status_color = $is_fully_marked ? 'emerald' : 'orange';
                    if ($room['total_attendance_count'] == 0) $status_color = 'slate';
                ?>
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden hover:shadow-md transition-all group">
                    <div class="p-6">
                        <div class="flex justify-between items-start">
                            <div class="flex items-center gap-3">
                                <div class="h-12 w-12 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600">
                                    <i class="fa-solid fa-building h-6 w-6"></i>
                                </div>
                                <div>
                                    <a href="room_attendance.php?room_id=<?php echo $room['id']; ?>&date=<?php echo $date; ?>" class="hover:text-indigo-600 transition-colors">
                                        <h3 class="font-bold text-slate-800 text-lg"><?php echo htmlspecialchars($room['room_name']); ?></h3>
                                    </a>
                                    <p class="text-xs text-slate-400 font-medium uppercase tracking-wider">Kamar Asrama</p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 space-y-4">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-500">Musyrif</span>
                                <span class="font-semibold text-slate-700"><?php echo htmlspecialchars($room['supervisor_name']); ?></span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-500">Status Absensi</span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-<?php echo $status_color; ?>-50 text-<?php echo $status_color; ?>-700 border border-<?php echo $status_color; ?>-100">
                                    <?php if ($room['total_students'] == 0): ?>
                                        Belum Ada Santri
                                    <?php elseif ($room['total_attendance_count'] == 0): ?>
                                        Belum Absen
                                    <?php elseif ($room['total_attendance_count'] >= $room['total_students']): ?>
                                        Selesai Absensi
                                    <?php else: ?>
                                        Sebagian (<?php echo $room['total_attendance_count']; ?>/<?php echo $room['total_students']; ?>)
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="bg-slate-50 px-6 py-4 border-t border-slate-100 flex justify-between items-center">
                        <a href="room_attendance.php?room_id=<?php echo $room['id']; ?>&date=<?php echo $date; ?>" 
                           class="w-full text-center text-sm font-bold text-indigo-600 hover:text-indigo-700 transition-colors inline-block py-1">
                            Lihat Absensi
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-span-full py-20 bg-white rounded-2xl border border-dashed border-slate-300 flex flex-col items-center justify-center text-slate-400 text-center px-6">
                <p class="text-lg font-bold text-slate-600">Belum ada data asrama</p>
                <p class="text-sm mt-1">Silakan tambahkan data asrama terlebih dahulu di menu Data Asrama.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../layouts/footer.php'; ?>
