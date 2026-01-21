<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$page_title = "Struktur Organisasi";

$db = new Database();
$conn = $db->getConnection();

// --- 1. Fetch Basic Data for Filters ---
$divisions = $conn->query("SELECT id, name FROM divisions ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// --- 2. Filter Logic ---
$filter_division_id = isset($_GET['division_id']) && $_GET['division_id'] !== '' ? $_GET['division_id'] : null;

// --- 3. Fetch All Employees with Position/Div/Unit Info ---
$sql = "
    SELECT 
        e.id, 
        e.full_name, 
        e.position_id, 
        p.name as position_name, 
        p.level as position_level,
        e.division_id, 
        d.name as division_name,
        e.unit_id, 
        u.name as unit_name
    FROM employees e
    JOIN positions p ON e.position_id = p.id
    LEFT JOIN divisions d ON e.division_id = d.id
    LEFT JOIN units u ON e.unit_id = u.id
    WHERE e.status = 'active' OR e.status IS NULL
";

if ($filter_division_id) {
    $sql .= " AND (e.division_id = :div_id)"; // Only fetch people in this division
}

// Append ORDER BY at the end
$sql .= " ORDER BY p.level ASC";

$stmt = $conn->prepare($sql);
if ($filter_division_id) {
    $stmt->bindValue(':div_id', $filter_division_id);
}
$stmt->execute();
$all_employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- 4. Hierarchy Processing ---

// Buckets for Parent Lookup
$level1_ids = []; // [empid, empid]
$level2_by_div = []; // [division_id => employee_data]
$level3_by_unit = []; // [unit_id => employee_data]

// Pre-process to fill buckets
foreach ($all_employees as $emp) {
    $lvl = (int) $emp['position_level'];
    if ($lvl == 1) {
        $level1_ids[] = $emp['id'];
    } elseif ($lvl == 2) {
        if (!isset($level2_by_div[$emp['division_id']])) {
            $level2_by_div[$emp['division_id']] = $emp;
        }
    } elseif ($lvl == 3) {
        if (!isset($level3_by_unit[$emp['unit_id']])) {
            $level3_by_unit[$emp['unit_id']] = $emp;
        }
    }
}

// Build Google Charts Rows
$chart_rows = [];

foreach ($all_employees as $emp) {
    $id = (string) $emp['id'];
    $lvl = (int) $emp['position_level'];
    $parentId = ''; // Top level by default

    // -- Parent Logic --
    if ($filter_division_id) {
        if ($lvl == 2) {
            $parentId = ''; // Root of this view
        } elseif ($lvl == 3) {
            if (isset($level2_by_div[$emp['division_id']])) {
                $parentId = (string) $level2_by_div[$emp['division_id']]['id'];
            }
        } elseif ($lvl >= 4) {
            if (isset($level3_by_unit[$emp['unit_id']])) {
                $parentId = (string) $level3_by_unit[$emp['unit_id']]['id'];
            } else {
                if (isset($level2_by_div[$emp['division_id']])) {
                    $parentId = (string) $level2_by_div[$emp['division_id']]['id'];
                }
            }
        }
    } else {
        // Full View
        if ($lvl == 1) {
            $parentId = '';
        } elseif ($lvl == 2) {
            if (!empty($level1_ids)) {
                $parentId = (string) $level1_ids[0];
            }
        } elseif ($lvl == 3) {
            if (isset($level2_by_div[$emp['division_id']])) {
                $parentId = (string) $level2_by_div[$emp['division_id']]['id'];
            } elseif (!empty($level1_ids)) {
                $parentId = (string) $level1_ids[0];
            }
        } elseif ($lvl >= 4) {
            if (isset($level3_by_unit[$emp['unit_id']])) {
                $parentId = (string) $level3_by_unit[$emp['unit_id']]['id'];
            } elseif (isset($level2_by_div[$emp['division_id']])) {
                $parentId = (string) $level2_by_div[$emp['division_id']]['id'];
            } elseif (!empty($level1_ids)) {
                $parentId = (string) $level1_ids[0];
            }
        }
    }

    // HTML Content for Node
    $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($emp['full_name']) . "&background=random";

    $nodeHtml = '
        <div class="org-node flex flex-col items-center p-2 min-w-[150px]">
            <img src="' . $avatarUrl . '" class="w-12 h-12 rounded-full border-2 border-white shadow-sm mb-2">
            <div class="text-sm font-bold text-slate-800 leading-tight">' . addslashes($emp['full_name']) . '</div>
            <div class="text-xs text-cyan-600 font-medium mt-1">' . $emp['position_name'] . '</div>
            <div class="text-[10px] text-slate-500 mt-0.5">' . ($emp['unit_name'] ?: $emp['division_name']) . '</div>
        </div>
    ';

    $chart_rows[] = [
        ['v' => $id, 'f' => $nodeHtml],
        $parentId,
        $emp['position_name']
    ];
}

// remove orphans
$all_ids = array_column($all_employees, 'id');
$cleaned_rows = [];
foreach ($chart_rows as $row) {
    $pid = $row[1];
    if ($pid === '' || in_array((int) $pid, $all_ids)) {
        $cleaned_rows[] = $row;
    } else {
        $row[1] = '';
        $cleaned_rows[] = $row;
    }
}

include '../layouts/header.php';
?>

<div class="h-screen flex flex-col pb-10">
    <!-- Header -->
    <div class="sm:flex sm:items-center justify-between py-6">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Struktur Organisasi</h1>
            <p class="mt-1 text-sm text-slate-500">Hierarki visual kepemimpinan dan laporan langsung.</p>
        </div>

        <!-- Filter Form -->
        <form method="GET" action="" class="mt-4 sm:mt-0 flex gap-2">
            <select name="division_id" onchange="this.form.submit()"
                class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-cyan-600 sm:text-sm sm:leading-6">
                <option value="">Semua Bidang</option>
                <?php foreach ($divisions as $div): ?>
                    <option value="<?php echo $div['id']; ?>" <?php echo $filter_division_id == $div['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($div['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <!-- Chart Container -->
    <div class="flex-1 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden relative">
        <?php if (empty($cleaned_rows)): ?>
            <div class="flex items-center justify-center h-full text-slate-400">
                <div class="text-center">
                    <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-slate-900">Data tidak ditemukan</h3>
                    <p class="mt-1 text-sm text-slate-500">Coba pilih divisi yang berbeda.</p>
                </div>
            </div>
        <?php else: ?>
            <div id="chart_div" class="w-full h-full overflow-auto"></div>
        <?php endif; ?>
    </div>
</div>

<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<script type="text/javascript">
    google.charts.load('current', { packages: ["orgchart"] });
    google.charts.setOnLoadCallback(drawChart);

    function drawChart() {
        var data = new google.visualization.DataTable();
        data.addColumn('string', 'Name');
        data.addColumn('string', 'Manager');
        data.addColumn('string', 'ToolTip');

        var rows = <?php echo json_encode($cleaned_rows); ?>;
        data.addRows(rows);

        var options = {
            allowHtml: true,
            allowCollapse: true,
            size: 'medium',
            nodeClass: 'acc-node',
            selectedNodeClass: 'acc-node-selected'
        };

        var chart = new google.visualization.OrgChart(document.getElementById('chart_div'));
        chart.draw(data, options);
    }
</script>

<style>
    .google-visualization-orgchart-table {
        border-collapse: separate !important;
        border-spacing: 0 20px !important;
    }

    .acc-node {
        border: 1px solid #e2e8f0 !important;
        border-radius: 12px !important;
        background: #ffffff !important;
        box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1) !important;
        padding: 0 !important;
        font-family: inherit !important;
        cursor: pointer;
        transition: all 0.2s;
    }

    .acc-node:hover {
        border-color: #0891b2 !important;
        box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1) !important;
    }

    .acc-node-selected {
        border: 2px solid #0891b2 !important;
        background: #ecfeff !important;
    }

    .google-visualization-orgchart-lineleft,
    .google-visualization-orgchart-lineright,
    .google-visualization-orgchart-linebottom {
        border-color: #cbd5e1 !important;
        border-width: 2px !important;
    }
</style>

<?php include '../layouts/footer.php'; ?>