<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_employees');

$db = new Database();
$conn = $db->getConnection();

// --- Reuse Filter Logic (Same as Index/Export) ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$division_id = isset($_GET['division_id']) ? $_GET['division_id'] : '';
$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

$where_clauses = ["e.id != 1"];
$params = [];

if ($search) {
    $where_clauses[] = "(e.full_name LIKE :search OR e.email LIKE :search OR e.phone_number LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($division_id) {
    $where_clauses[] = "e.division_id = :division_id";
    $params[':division_id'] = $division_id;
}
if ($unit_id) {
    $where_clauses[] = "e.unit_id = :unit_id";
    $params[':unit_id'] = $unit_id;
}
if ($status) {
    if ($status === 'active') {
        $where_clauses[] = "(e.status = 'active' OR e.status IS NULL)";
    } elseif ($status === 'inactive') {
        $where_clauses[] = "e.status = 'inactive'";
    }
}

$where_sql = implode(" AND ", $where_clauses);

// Fetch All Matching Data (No Limit)
$query = "
    SELECT 
        e.id, 
        e.full_name, 
        e.email, 
        e.phone_number, 
        e.address, 
        d.name as division_name, 
        u.name as unit_name,
        p.name as position_name,
        e.status
    FROM employees e 
    LEFT JOIN divisions d ON e.division_id = d.id 
    LEFT JOIN units u ON e.unit_id = u.id
    LEFT JOIN positions p ON e.position_id = p.id
    WHERE $where_sql
    ORDER BY e.full_name ASC
";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees List - Export</title>
    <!-- Use Tailwind for print styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body {
                -webkit-print-color-adjust: exact;
            }
        }

        table {
            page-break-inside: auto;
        }

        tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        thead {
            display: table-header-group;
        }

        tfoot {
            display: table-footer-group;
        }
    </style>
</head>

<body class="p-8 bg-white text-slate-900" onload="window.print()">

    <!-- Header -->
    <div class="mb-8 border-b border-slate-200 pb-4 flex justify-between items-end">
        <div>
            <h1 class="text-2xl font-bold uppercase tracking-wide text-slate-800">Employee List</h1>
            <p class="text-sm text-slate-500 mt-1">Generated on
                <?php echo date('F j, Y, g:i a'); ?>
            </p>
        </div>
        <div class="text-right">
            <h2 class="font-bold text-lg text-cyan-700">Dashboard YAC</h2>
            <p class="text-xs text-slate-400">Confidential Report</p>
        </div>
    </div>

    <!-- Filters Applied Summary -->
    <div class="mb-6 text-xs text-slate-500 flex gap-4">
        <?php if ($division_id): ?>
            <span class="bg-slate-100 px-2 py-1 rounded">Div ID:
                <?php echo htmlspecialchars($division_id); ?>
            </span>
        <?php endif; ?>
        <?php if ($status): ?>
            <span class="bg-slate-100 px-2 py-1 rounded">Status:
                <?php echo htmlspecialchars(ucfirst($status)); ?>
            </span>
        <?php endif; ?>
        <span class="bg-slate-100 px-2 py-1 rounded">Total Records:
            <?php echo count($employees); ?>
        </span>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full text-left text-xs whitespace-nowrap">
            <thead>
                <tr class="border-b border-slate-300">
                    <th class="py-2 pr-4 font-bold uppercase text-slate-500">ID</th>
                    <th class="py-2 pr-4 font-bold uppercase text-slate-500">Full Name</th>
                    <th class="py-2 pr-4 font-bold uppercase text-slate-500">Position</th>
                    <th class="py-2 pr-4 font-bold uppercase text-slate-500">Division / Unit</th>
                    <th class="py-2 pr-4 font-bold uppercase text-slate-500">Contact</th>
                    <th class="py-2 font-bold uppercase text-slate-500">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($employees as $emp): ?>
                    <tr class="align-top">
                        <td class="py-3 pr-4 text-slate-400 font-mono">
                            <?php echo $emp['id']; ?>
                        </td>
                        <td class="py-3 pr-4 font-bold text-slate-800">
                            <?php echo htmlspecialchars($emp['full_name']); ?>
                        </td>
                        <td class="py-3 pr-4 text-slate-600">
                            <?php echo htmlspecialchars($emp['position_name'] ?? '-'); ?>
                        </td>
                        <td class="py-3 pr-4 text-slate-600">
                            <?php echo htmlspecialchars($emp['division_name']); ?>
                            <?php if ($emp['unit_name'])
                                echo " / " . htmlspecialchars($emp['unit_name']); ?>
                        </td>
                        <td class="py-3 pr-4 text-slate-600">
                            <div>
                                <?php echo htmlspecialchars($emp['email']); ?>
                            </div>
                            <div class="text-slate-400 text-[10px]">
                                <?php echo htmlspecialchars($emp['phone_number']); ?>
                            </div>
                        </td>
                        <td class="py-3">
                            <span
                                class="px-2 py-0.5 rounded-full text-[10px] font-bold <?php echo ($emp['status'] === 'inactive') ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600'; ?>">
                                <?php echo ($emp['status'] === 'inactive') ? 'INACTIVE' : 'ACTIVE'; ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</body>

</html>