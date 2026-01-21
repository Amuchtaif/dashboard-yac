<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$page_title = "Attendance Log";

$db = new Database();
$conn = $db->getConnection();

// --- Pagination Logic ---
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
if (!in_array($limit, [10, 20, 50]))
    $limit = 10;

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;

$offset = ($page - 1) * $limit;

// Total Count
$total_stmt = $conn->query("SELECT COUNT(*) FROM attendance");
$total_rows = $total_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Fetch Data
$query = "
    SELECT a.*, e.full_name, e.email 
    FROM attendance a
    JOIN employees e ON a.user_id = e.id
    ORDER BY a.date DESC, a.time_in DESC
    LIMIT :limit OFFSET :offset
";
$stmt = $conn->prepare($query);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="px-4 sm:px-6 lg:px-8">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-xl font-semibold text-gray-900">Attendance History</h1>
            <p class="mt-2 text-sm text-gray-700">A complete log of employee check-ins and check-outs.</p>
        </div>
    </div>

    <!-- Table Container -->
    <div class="mt-8 flex flex-col">
        <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
                <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col"
                                    class="py-3.5 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:pl-6 w-12">
                                    #</th>
                                <th scope="col"
                                    class="py-3.5 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Employee</th>
                                <th scope="col"
                                    class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Date</th>
                                <th scope="col"
                                    class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Check In</th>
                                <th scope="col"
                                    class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Check Out</th>
                                <th scope="col"
                                    class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Location In</th>
                                <th scope="col"
                                    class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Location Out</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <?php if (count($logs) > 0): ?>
                                <?php foreach ($logs as $index => $log): ?>
                                    <tr>
                                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-500 sm:pl-6">
                                            <?php echo $offset + $index + 1; ?>
                                        </td>
                                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($log['full_name']); ?>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                            <?php echo date('l, d M Y', strtotime($log['date'])); ?>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm">
                                            <div class="font-medium text-gray-900">
                                                <?php echo date('H:i', strtotime($log['time_in'])); ?>
                                            </div>
                                            <span
                                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium <?php echo ($log['status'] === 'Hadir') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                <?php echo htmlspecialchars($log['status']); ?>
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm">
                                            <?php if ($log['time_out']): ?>
                                                <div class="font-medium text-gray-900">
                                                    <?php echo date('H:i', strtotime($log['time_out'])); ?>
                                                </div>
                                                <span
                                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium <?php echo ($log['status_out'] === 'Pulang') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                    <?php echo htmlspecialchars($log['status_out'] ?? 'Pulang'); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-gray-400 italic">Active</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-xs text-gray-500">
                                            Lat: <?php echo number_format($log['lat_in'], 5); ?><br>
                                            Long: <?php echo number_format($log['long_in'], 5); ?>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-xs text-gray-500">
                                            <?php if ($log['lat_out']): ?>
                                                Lat: <?php echo number_format($log['lat_out'], 5); ?><br>
                                                Long: <?php echo number_format($log['long_out'], 5); ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="px-3 py-4 text-sm text-center text-gray-500">No attendance
                                        records found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Pagination (Moved Inside) -->
                    <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6">
                        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                            <div class="flex items-center gap-4">
                                <select onchange="window.location.href='?page=1&limit='+this.value"
                                    class="block rounded-md border-0 py-1.5 pl-3 pr-8 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-cyan-600 sm:text-xs sm:leading-6">
                                    <?php foreach ([10, 20, 50, 100] as $val): ?>
                                        <option value="<?php echo $val; ?>" <?php echo $limit == $val ? 'selected' : ''; ?>>
                                            Show <?php echo $val; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-sm text-gray-700">
                                    Showing
                                    <span class="font-medium"><?php echo ($total_rows > 0) ? $offset + 1 : 0; ?></span>
                                    to
                                    <span class="font-medium"><?php echo min($offset + $limit, $total_rows); ?></span>
                                    of
                                    <span class="font-medium"><?php echo $total_rows; ?></span>
                                    results
                                </p>
                            </div>
                            <div>
                                <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm"
                                    aria-label="Pagination">
                                    <!-- Prev -->
                                    <?php if ($page > 1): ?>
                                        <a href="?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>"
                                            class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                            <span class="sr-only">Previous</span>
                                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd"
                                                    d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </a>
                                    <?php endif; ?>

                                    <?php
                                    $range = 2; // Number of pages around current page
                                    $initial_num = $page - $range;
                                    $condition_limit_num = ($page + $range) + 1;

                                    for ($i = 1; $i <= $total_pages; $i++) {
                                        if ($i == 1 || $i == $total_pages || ($i >= $initial_num && $i < $condition_limit_num)) {
                                            ?>
                                            <a href="?page=<?php echo $i; ?>&limit=<?php echo $limit; ?>"
                                                class="relative inline-flex items-center px-4 py-2 text-sm font-semibold <?php echo ($i == $page) ? 'bg-cyan-600 text-white focus-visible:outline-cyan-600' : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50'; ?> focus:z-20 focus:outline-offset-0">
                                                <?php echo $i; ?>
                                            </a>
                                            <?php
                                        } elseif ($i == $initial_num - 1 || $i == $condition_limit_num) {
                                            ?>
                                            <span
                                                class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 focus:outline-offset-0">...</span>
                                            <?php
                                        }
                                    }
                                    ?>

                                    <!-- Next -->
                                    <?php if ($page < $total_pages): ?>
                                        <a href="?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>"
                                            class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                            <span class="sr-only">Next</span>
                                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd"
                                                    d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </a>
                                    <?php endif; ?>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../layouts/footer.php'; ?>