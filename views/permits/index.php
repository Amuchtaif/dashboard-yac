<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$page_title = "Permit Management";

$db = new Database();
$conn = $db->getConnection();

// --- Stats Logic ---
$stats = [
    'pending' => 0,
    'leave_today' => 0,
    'approval_rate' => 0
];

// 1. Pending Requests
$stats['pending'] = $conn->query("SELECT COUNT(*) FROM permits WHERE status = 'Pending'")->fetchColumn();

// 2. Staff on Leave Today
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT COUNT(*) FROM permits WHERE status = 'Approved' AND start_date <= :today AND end_date >= :today");
$stmt->execute([':today' => $today]);
$stats['leave_today'] = $stmt->fetchColumn();

// 3. Monthly Approval Rate
$currentMonth = date('Y-m');
$stmt = $conn->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved
    FROM permits 
    WHERE DATE_FORMAT(created_at, '%Y-%m') = :currentMonth");
$stmt->execute([':currentMonth' => $currentMonth]);
$rateData = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['approval_rate'] = ($rateData['total'] > 0) ? round(($rateData['approved'] / $rateData['total']) * 100, 1) : 0;


// --- Filter & Pagination Logic ---
$limit = 10;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $limit;

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'all'; // all, Pending, Approved, Rejected
$permit_type = isset($_GET['type']) ? $_GET['type'] : '';

$where_clauses = ["1=1"];
$params = [];

if ($tab !== 'all') {
    $where_clauses[] = "p.status = :status";
    $params[':status'] = $tab;
}
if ($permit_type) {
    $where_clauses[] = "p.permit_type = :type";
    $params[':type'] = $permit_type;
}

$where_sql = implode(" AND ", $where_clauses);

// Fetch Data with Employee Position (Join Positions not explicitly present in previous schema but assuming employees table structure or relationships, sticking to direct employees columns if no positions table linked yet. Wait, sidebar has Positions link so table likely exists. Let's LEFT JOIN positions if employee has position_id)
// Checking schema assumption: employees table likely has position_id. 
// If unsure, I will fetch pure employee name for now, or assume 'position' column exists or relationship.
// To be safe and fast, I will just display Employee Name and maybe a hardcoded/placeholder "Software Engineer" if I can't find the column, OR better: check if I can join `positions`.
// Previous conversations showed `positions` table exists.
// Let's try to join positions.

$query = "
    SELECT p.*, e.full_name, e.position_id, pos.name as position_name, DATEDIFF(p.end_date, p.start_date) + 1 as duration
    FROM permits p
    JOIN employees e ON p.employee_id = e.id
    LEFT JOIN positions pos ON e.position_id = pos.id
    WHERE $where_sql
    ORDER BY p.created_at DESC
    LIMIT :limit OFFSET :offset
";

// Count for pagination
$count_query = "SELECT COUNT(*) FROM permits p JOIN employees e ON p.employee_id = e.id WHERE $where_sql";

$total_stmt = $conn->prepare($count_query);
foreach ($params as $key => $val) {
    $total_stmt->bindValue($key, $val);
}
$total_stmt->execute();
$total_rows = $total_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

$stmt = $conn->prepare($query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$permits = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="px-4 sm:px-6 lg:px-8 bg-gray-50 min-h-screen pb-10">

    <!-- Top Header Section -->
    <div class="flex justify-between items-start mb-8 pt-6">
        <div>
            <div class="flex items-center gap-2 text-sm text-slate-500 mb-1">
                <span>Dashboard</span>
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
                <span class="font-medium text-cyan-600">Permit Management</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900">Permit Management</h1>
            <p class="mt-1 text-slate-500">Review and manage leave requests from your organization.</p>
        </div>
        <a href="<?php url('views/permits/create.php'); ?>"
            class="bg-cyan-50 text-cyan-700 hover:bg-cyan-100 px-4 py-2 rounded-lg text-sm font-medium flex items-center transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Manual Request
        </a>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Card 1 -->
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm relative overflow-hidden">
            <div class="flex justify-between items-start">
                <div class="p-3 bg-orange-50 rounded-lg">
                    <svg class="w-6 h-6 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <!-- Mock Trend -->
                <span class="text-xs font-medium text-green-600 bg-green-50 px-2 py-1 rounded-full">+5% vs last
                    week</span>
            </div>
            <div class="mt-4">
                <p class="text-slate-500 text-sm font-medium">Pending Requests</p>
                <h3 class="text-3xl font-bold text-slate-800 mt-1"><?php echo $stats['pending']; ?></h3>
            </div>
        </div>

        <!-- Card 2 -->
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm relative overflow-hidden">
            <div class="flex justify-between items-start">
                <div class="p-3 bg-blue-50 rounded-lg">
                    <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                    </svg>
                </div>
                <span class="text-xs font-medium text-red-600 bg-red-50 px-2 py-1 rounded-full">-2% vs last week</span>
            </div>
            <div class="mt-4">
                <p class="text-slate-500 text-sm font-medium">Staff on Leave Today</p>
                <h3 class="text-3xl font-bold text-slate-800 mt-1"><?php echo $stats['leave_today']; ?></h3>
            </div>
        </div>

        <!-- Card 3 -->
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm relative overflow-hidden">
            <div class="flex justify-between items-start">
                <div class="p-3 bg-green-50 rounded-lg">
                    <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <span class="text-xs font-medium text-green-600 bg-green-50 px-2 py-1 rounded-full">+1% vs last
                    month</span>
            </div>
            <div class="mt-4">
                <p class="text-slate-500 text-sm font-medium">Monthly Approval Rate</p>
                <h3 class="text-3xl font-bold text-slate-800 mt-1"><?php echo $stats['approval_rate']; ?>%</h3>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm">

        <!-- Tabs -->
        <div class="border-b border-slate-200 px-6">
            <nav class="flex space-x-8" aria-label="Tabs">
                <?php
                $tabs = [
                    'all' => 'All Requests',
                    'Pending' => 'Pending',
                    'Approved' => 'Approved',
                    'Rejected' => 'Rejected'
                ];
                foreach ($tabs as $key => $label):
                    $isActive = ($tab === $key);
                    $badge = ($key === 'Pending' && $stats['pending'] > 0) ? '<span class="ml-2 bg-orange-100 text-orange-600 py-0.5 px-2 rounded-full text-xs font-medium">' . $stats['pending'] . '</span>' : '';
                    ?>
                    <a href="?tab=<?php echo $key; ?>"
                        class="<?php echo $isActive ? 'border-cyan-500 text-cyan-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
                        <?php echo $label; ?>
                        <?php echo $badge; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <!-- Toolbar (Filters) -->
        <div
            class="p-6 flex flex-col sm:flex-row justify-between items-center bg-white border-b border-slate-100 gap-4">
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <form action="" method="GET" class="flex gap-3">
                    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">

                    <div class="relative">
                        <select name="type" onchange="this.form.submit()"
                            class="appearance-none bg-white border border-slate-300 text-slate-700 py-2 pl-4 pr-10 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500">
                            <option value="">Permit Type: All</option>
                            <option value="Sick" <?php echo $permit_type == 'Sick' ? 'selected' : ''; ?>>Sick Leave
                            </option>
                            <option value="Leave" <?php echo $permit_type == 'Leave' ? 'selected' : ''; ?>>Annual Leave
                            </option>
                            <option value="Other" <?php echo $permit_type == 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                        <div
                            class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-500">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </div>

                    <!-- Placeholder for Department Filter - Requires dynamic loading if desired, sticking to Type mainly as requested -->
                </form>
            </div>

            <div class="text-sm text-slate-500">
                Showing <?php echo count($permits); ?> results
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm whitespace-nowrap">
                <thead
                    class="bg-gray-50 text-slate-500 border-b border-slate-100 uppercase tracking-wider text-xs font-semibold">
                    <tr>
                        <th scope="col" class="px-6 py-4">Employee</th>
                        <th scope="col" class="px-6 py-4">Type</th>
                        <th scope="col" class="px-6 py-4">Period</th>
                        <th scope="col" class="px-6 py-4">Reason Snippet</th>
                        <th scope="col" class="px-6 py-4">Status</th>
                        <th scope="col" class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <?php if (empty($permits)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-slate-500">
                                No permits found.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($permits as $permit): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <!-- Employee -->
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <img class="h-10 w-10 rounded-full object-cover"
                                        src="https://ui-avatars.com/api/?name=<?php echo urlencode($permit['full_name']); ?>&background=random"
                                        alt="">
                                    <div class="ml-4">
                                        <div class="font-medium text-slate-900">
                                            <?php echo htmlspecialchars($permit['full_name']); ?></div>
                                        <div class="text-slate-500 text-xs">
                                            <?php echo htmlspecialchars($permit['position_name'] ?? 'Employee'); ?></div>
                                    </div>
                                </div>
                            </td>

                            <!-- Type -->
                            <td class="px-6 py-4">
                                <?php
                                $typeClass = '';
                                $typeLabel = strtoupper($permit['permit_type']);
                                switch ($permit['permit_type']) {
                                    case 'Sick':
                                        $typeClass = 'bg-red-50 text-red-600 border border-red-100';
                                        $typeLabel .= ' LEAVE';
                                        break;
                                    case 'Leave':
                                        $typeClass = 'bg-purple-50 text-purple-600 border border-purple-100';
                                        break;
                                    case 'Other':
                                        $typeClass = 'bg-blue-50 text-blue-600 border border-blue-100';
                                        break;
                                    default:
                                        $typeClass = 'bg-gray-50 text-gray-600';
                                }
                                ?>
                                <span class="px-2.5 py-1 rounded-md text-xs font-bold <?php echo $typeClass; ?>">
                                    <?php echo $typeLabel; ?>
                                </span>
                            </td>

                            <!-- Period -->
                            <td class="px-6 py-4">
                                <div class="text-slate-900 font-medium">
                                    <?php echo date('M d', strtotime($permit['start_date'])); ?> -
                                    <?php echo date('M d', strtotime($permit['end_date'])); ?>
                                </div>
                                <div class="text-slate-500 text-xs">
                                    <?php echo $permit['duration']; ?> days
                                </div>
                            </td>

                            <!-- Reason -->
                            <td class="px-6 py-4">
                                <p class="text-slate-600 truncate w-48"
                                    title="<?php echo htmlspecialchars($permit['reason']); ?>">
                                    <?php echo htmlspecialchars($permit['reason']); ?>
                                </p>
                            </td>

                            <!-- Status -->
                            <td class="px-6 py-4">
                                <?php
                                $statusColor = 'slate';
                                if ($permit['status'] == 'Approved')
                                    $statusColor = 'green';
                                if ($permit['status'] == 'Pending')
                                    $statusColor = 'orange';
                                if ($permit['status'] == 'Rejected')
                                    $statusColor = 'red';
                                ?>
                                <div class="flex items-center text-<?php echo $statusColor; ?>-600 font-medium text-sm">
                                    <span class="h-2 w-2 rounded-full bg-<?php echo $statusColor; ?>-500 mr-2"></span>
                                    <?php echo $permit['status']; ?>
                                </div>
                            </td>

                            <!-- Actions -->
                            <td class="px-6 py-4 text-right">
                                <?php if ($permit['status'] === 'Pending'): ?>
                                    <div class="flex justify-end items-center gap-2">
                                        <a href="<?php url('logic/permits/quick_action.php?action=approve&id=' . $permit['id']); ?>"
                                            class="bg-cyan-600 text-white px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-cyan-700 transition">
                                            Approve
                                        </a>
                                        <a href="<?php url('logic/permits/quick_action.php?action=reject&id=' . $permit['id']); ?>"
                                            class="bg-white border border-slate-200 text-red-600 px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-red-50 transition">
                                            Reject
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <span
                                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-500">
                                        Processed
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between">
            <p class="text-sm text-slate-500">
                Page <?php echo $page; ?> of <?php echo max($total_pages, 1); ?>
            </p>
            <div class="flex gap-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&tab=<?php echo $tab; ?>&type=<?php echo $permit_type; ?>"
                        class="px-3 py-1.5 border border-slate-200 rounded-lg text-sm text-slate-600 hover:bg-slate-50">Previous</a>
                <?php endif; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&tab=<?php echo $tab; ?>&type=<?php echo $permit_type; ?>"
                        class="px-3 py-1.5 border border-slate-200 rounded-lg text-sm text-slate-600 hover:bg-slate-50">Next</a>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php include '../layouts/footer.php'; ?>