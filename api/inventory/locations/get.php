<?php
// api/inventory/locations/get.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // 1. Get counts of items per location to show in preview
    $countStmt = $conn->query("SELECT location_id, COUNT(*) as total FROM inventory_items GROUP BY location_id");
    $itemCounts = [];
    while ($c = $countStmt->fetch(PDO::FETCH_ASSOC)) {
        $itemCounts[$c['location_id']] = (int)$c['total'];
    }

    // 2. Filter Logic
    $parentIdFilter = isset($_GET['parent_id']) ? $_GET['parent_id'] : null;
    $hasFilter = isset($_GET['parent_id']); // specifically check if key exists

    if ($hasFilter) {
        // Return FLAT list of direct children for mobile navigation
        $sql = "SELECT id, name, location_code, location_label, parent_id FROM inventory_locations WHERE ";
        $sql .= ($parentIdFilter === 'null' || $parentIdFilter === '') ? "parent_id IS NULL" : "parent_id = ?";
        $sql .= " ORDER BY name ASC";
        
        $stmt = $conn->prepare($sql);
        if ($parentIdFilter !== 'null' && $parentIdFilter !== '') {
            $stmt->execute([$parentIdFilter]);
        } else {
            $stmt->execute();
        }
        $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($locations as &$loc) {
            $loc['item_count'] = $itemCounts[$loc['id']] ?? 0;
            // Also check if they HAVE sub-locations
            $subStmt = $conn->prepare("SELECT COUNT(*) FROM inventory_locations WHERE parent_id = ?");
            $subStmt->execute([$loc['id']]);
            $loc['sub_count'] = (int)$subStmt->fetchColumn();
        }

        echo json_encode(["success" => true, "data" => $locations]);
        exit;
    }

    // --- LEGACY TREE VIEW (for Web) ---
    $stmt = $conn->query("SELECT id, name, location_code, location_label, parent_id FROM inventory_locations ORDER BY name ASC");
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $tree = [];
    $references = [];

    foreach ($locations as &$loc) {
        $loc['children'] = [];
        $loc['item_count'] = $itemCounts[$loc['id']] ?? 0;
        $references[$loc['id']] = &$loc; 
    }

    foreach ($locations as &$loc) {
        if ($loc['parent_id'] === null) {
            $tree[] = &$loc; 
        } else {
            if (isset($references[$loc['parent_id']])) {
                $references[$loc['parent_id']]['children'][] = &$loc;
            }
        }
    }

    echo json_encode(["success" => true, "data" => $tree]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
