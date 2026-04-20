<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$page_title = "Struktur Organisasi";

$db = new Database();
$conn = $db->getConnection();

// --- 1. Fetch Basic Data for Filters ---
$divisions = $conn->query("SELECT id, name FROM divisions ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
// Add Pengurus Inti as a virtual division for filtering
$divisions[] = ['id' => 'pengurus_inti', 'name' => 'Pengurus Inti'];

// --- 2. Filter Logic ---
$filter_division_id = isset($_GET['division_id']) && $_GET['division_id'] !== '' ? $_GET['division_id'] : null;

// --- 3. Fetch All Employees with Position/Div/Unit Info ---
// --- 3. Fetch Data from Multiple Sources ---
// Primary Positions
$primary_sql = "
    SELECT 
        CONCAT('e-', e.id) as node_id,
        e.id as employee_id,
        e.full_name, 
        e.position_id, 
        p.name as position_name, 
        p.level as position_level,
        e.division_id, 
        d.name as division_name,
        e.unit_id, 
        u.name as unit_name,
        'primary' as source
    FROM employees e
    JOIN positions p ON e.position_id = p.id
    LEFT JOIN divisions d ON e.division_id = d.id
    LEFT JOIN units u ON e.unit_id = u.id
    WHERE (e.status = 'active' OR e.status IS NULL) AND e.id != 1
";

// Manual Assignments (Double Jobs)
$assignment_sql = "
    SELECT 
        CONCAT('a-', ea.id) as node_id,
        e.id as employee_id,
        e.full_name, 
        ea.position_id, 
        p.name as position_name, 
        p.level as position_level,
        u_owner.division_id, 
        d.name as division_name,
        ea.unit_id, 
        u.name as unit_name,
        'assignment' as source
    FROM employee_assignments ea
    JOIN employees e ON ea.employee_id = e.id
    JOIN positions p ON ea.position_id = p.id
    LEFT JOIN units u ON ea.unit_id = u.id
    LEFT JOIN units u_owner ON u_owner.id = ea.unit_id
    LEFT JOIN divisions d ON u_owner.division_id = d.id
    WHERE ea.is_active = 1 AND e.id != 1
";

// Teaching Assignments from Class Schedules
// Only include as a separate node if they are NOT already primarily in that unit as a Guru
$teacher_sql = "
    SELECT 
        DISTINCT CONCAT('t-', e.id, '-', u.id) as node_id,
        e.id as employee_id,
        e.full_name, 
        4 as position_id, 
        'Guru' as position_name, 
        4 as position_level,
        u.division_id, 
        d.name as division_name,
        u.id as unit_id, 
        u.name as unit_name,
        'schedule' as source
    FROM class_schedules cs
    JOIN employees e ON cs.employee_id = e.id
    JOIN grade_levels gl ON cs.grade_level_id = gl.id
    JOIN education_units eu ON gl.education_unit_id = eu.id
    JOIN units u ON eu.operational_unit_id = u.id
    LEFT JOIN divisions d ON u.division_id = d.id
    WHERE (e.status = 'active' OR e.status IS NULL) AND e.id != 1
    AND NOT EXISTS (
        SELECT 1 FROM employees WHERE id = e.id AND unit_id = u.id AND position_id = 4
    )
";

$sql = "($primary_sql) UNION ALL ($assignment_sql) UNION ALL ($teacher_sql)";

if ($filter_division_id) {
    if ($filter_division_id === 'pengurus_inti') {
        $sql = "SELECT * FROM ($sql) as combined WHERE position_level IN (1, 2)";
    } else {
        $sql = "SELECT * FROM ($sql) as combined WHERE division_id = :div_id";
    }
}

$stmt = $conn->prepare($sql);
if ($filter_division_id && $filter_division_id !== 'pengurus_inti') {
    $stmt->bindValue(':div_id', $filter_division_id);
}
$stmt->execute();
$all_nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- 4. Hierarchy Processing ---

// Buckets for Parent Lookup by level/division/unit
$root_node_id = '';
$division_heads = []; // [div_id => node_id]
$unit_heads = [];     // [unit_id => node_id]

// First pass: identify parents (Level 1, 2, 3)
foreach ($all_nodes as $node) {
    $lvl = (int) $node['position_level'];
    if ($lvl == 1) {
        $root_node_id = $node['node_id'];
    } elseif ($lvl == 2) {
        if (!isset($division_heads[$node['division_id']])) {
            $division_heads[$node['division_id']] = $node['node_id'];
        }
    } elseif ($lvl == 3) {
        if (!isset($unit_heads[$node['unit_id']])) {
            $unit_heads[$node['unit_id']] = $node['node_id'];
        }
    }
}

// Build Google Charts Rows
$chart_rows = [];

foreach ($all_nodes as $node) {
    $nodeId = $node['node_id'];
    $lvl = (int) $node['position_level'];
    $parentId = '';

    // -- Smart Parent Discovery --
    if ($lvl == 1) {
        $parentId = ''; // Top of everyone
    } elseif ($lvl == 2) {
        $parentId = $root_node_id;
    } elseif ($lvl == 3) {
        // Child of its Division Head
        $parentId = $division_heads[$node['division_id']] ?? $root_node_id;
    } elseif ($lvl >= 4) {
        // Child of its Unit Head, or Division Head, or Root
        if (!empty($node['unit_id']) && isset($unit_heads[$node['unit_id']])) {
            $parentId = $unit_heads[$node['unit_id']];
        } elseif (!empty($node['division_id']) && isset($division_heads[$node['division_id']])) {
            $parentId = $division_heads[$node['division_id']];
        } else {
            $parentId = $root_node_id;
        }
    }

    // HTML Content for Node
    $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($node['full_name']) . "&background=random";

    $nodeHtml = '
        <div class="org-node flex flex-col items-center p-2 min-w-[160px]">
            <img src="' . $avatarUrl . '" class="w-12 h-12 rounded-full border-2 border-white shadow-sm mb-2">
            <div class="text-sm font-bold text-slate-800 leading-tight">' . addslashes($node['full_name']) . '</div>
            <div class="text-xs text-cyan-600 font-medium mt-1 flex items-center">' . $node['position_name'] . '</div>
            <div class="text-[10px] text-slate-500 mt-0.5">' . ($node['unit_name'] ?: ($node['division_name'] ?: '-')) . '</div>
        </div>
    ';

    $chart_rows[] = [
        ['v' => $nodeId, 'f' => $nodeHtml],
        $parentId,
        $node['position_name']
    ];
}

// remove orphans (just in case)
$valid_ids = array_column($all_nodes, 'node_id');
$cleaned_rows = [];
foreach ($chart_rows as $row) {
    if ($row[1] === '' || in_array($row[1], $valid_ids)) {
        $cleaned_rows[] = $row;
    } else {
        $row[1] = ''; // make it root if parent not found
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
            <p class="mt-1 text-sm text-slate-500">Hierarki visual organisasi yayasan.</p>
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
                    <p class="mt-1 text-sm text-slate-500">Coba pilih bidang yang berbeda.</p>
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