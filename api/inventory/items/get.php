<?php
// api/inventory/items/get.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // To prevent N+1 queries for breadcrumbs, let's load all locations ONCE
    // into a Hash Map (O(n) fast access).
    $locStmt = $conn->query("SELECT id, name, parent_id FROM inventory_locations");
    $locs = $locStmt->fetchAll(PDO::FETCH_ASSOC);
    $locMap = [];
    foreach ($locs as $l) {
        $locMap[$l['id']] = $l;
    }

    // Function to traverse parent up to root and build breadcrumb string
    function buildBreadcrumb($map, $locId) {
        if (!isset($map[$locId])) return "Unknown Location";
        
        $path = [];
        $current = $locId;
        while ($current != null) {
            $path[] = $map[$current]['name'];
            $current = $map[$current]['parent_id'];
        }
        
        // Reverse because path starts from leaf up to root
        return implode(" > ", array_reverse($path));
    }

    // --- Filter Logic ---
    $filterLocationId = $_GET['location_id'] ?? null;
    $filterCondition = $_GET['condition'] ?? null;
    $targetLocationIds = [];

    if ($filterLocationId) {
        $targetLocationIds[] = (int)$filterLocationId;
        
        // Find all descendants recursively
        $getDescendants = function($parentId) use (&$getDescendants, $locs) {
            $kids = [];
            foreach ($locs as $l) {
                if ($l['parent_id'] == $parentId) {
                    $kids[] = (int)$l['id'];
                    $kids = array_merge($kids, $getDescendants($l['id']));
                }
            }
            return $kids;
        };

        $targetLocationIds = array_unique(array_merge($targetLocationIds, $getDescendants($filterLocationId)));
    }

    // Get Items
    $sql = "
        SELECT 
            i.id, 
            i.item_code, i.item_code AS kode_barang,
            i.name, 
            i.location_id, 
            i.qty, i.qty AS jumlah_barang,
            i.item_unit, i.item_unit AS satuan_barang,
            i.description, 
            i.item_condition, i.item_condition AS kondisi_barang,
            i.item_photo, i.item_photo AS foto_barang,
            l.name as location_leaf_name
        FROM inventory_items i
        LEFT JOIN inventory_locations l ON i.location_id = l.id
    ";

    $whereClauses = [];
    $params = [];

    if (!empty($targetLocationIds)) {
        $placeholders = implode(',', array_fill(0, count($targetLocationIds), '?'));
        $whereClauses[] = "i.location_id IN ($placeholders)";
        $params = array_merge($params, $targetLocationIds);
    }

    if ($filterCondition) {
        $whereClauses[] = "i.item_condition = ?";
        $params[] = $filterCondition;
    }

    if (!empty($whereClauses)) {
        $sql .= " WHERE " . implode(" AND ", $whereClauses);
    }

    $sql .= " ORDER BY i.name ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    $items = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Build the breadcrumb string instantly using the loaded hash map
        $row['location_breadcrumb'] = buildBreadcrumb($locMap, $row['location_id']);
        $items[] = $row;
    }

    echo json_encode(["success" => true, "data" => $items]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
