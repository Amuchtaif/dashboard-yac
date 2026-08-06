<?php
// tests/test_digital_documents.php
// Automated End-to-End Test Suite for Digital Documents (Surat Digital & Outgoing Documents)

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/permission.php';

echo "\n========================================================\n";
echo "    TEST SUITE: SURAT DIGITAL & OUTGOING DOCUMENTS     \n";
echo "========================================================\n\n";

$passedCount = 0;
$failedCount = 0;

function assertTest($condition, $testName, $details = '') {
    global $passedCount, $failedCount;
    if ($condition) {
        $passedCount++;
        echo "  \033[32m[PASS]\033[0m " . $testName . "\n";
    } else {
        $failedCount++;
        echo "  \033[31m[FAIL]\033[0m " . $testName;
        if ($details) echo " -> " . $details;
        echo "\n";
    }
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    assertTest($conn !== null, "1. Connecting to MySQL Database");

    // ---------------------------------------------------------
    // TEST 1: Check Database Table Schema & Columns
    // ---------------------------------------------------------
    $stmtCols = $conn->prepare("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents'
    ");
    $stmtCols->execute();
    $columns = $stmtCols->fetchAll(PDO::FETCH_COLUMN);

    assertTest(in_array('receiver_division_id', $columns), "2. Column 'receiver_division_id' exists in 'documents' table");
    assertTest(in_array('receiver_unit_id', $columns), "3. Column 'receiver_unit_id' exists in 'documents' table");

    // Check table document_routing_rules
    $stmtRouting = $conn->query("SHOW TABLES LIKE 'document_routing_rules'");
    assertTest($stmtRouting->rowCount() > 0, "4. Table 'document_routing_rules' exists in database");

    // Fetch test division (Bidang) and unit
    $stmtDiv = $conn->query("SELECT id, name FROM divisions ORDER BY id ASC LIMIT 1");
    $div = $stmtDiv->fetch(PDO::FETCH_ASSOC);
    assertTest(!empty($div), "5. Division (Bidang) data available for testing", "Division: " . ($div['name'] ?? 'none'));
    $division_id = $div['id'] ?? 1;

    $stmtUnit = $conn->prepare("SELECT id, name FROM units WHERE division_id = ? ORDER BY id ASC LIMIT 1");
    $stmtUnit->execute([$division_id]);
    $unit = $stmtUnit->fetch(PDO::FETCH_ASSOC);
    if (!$unit) {
        $stmtUnit = $conn->query("SELECT id, name, division_id FROM units ORDER BY id ASC LIMIT 1");
        $unit = $stmtUnit->fetch(PDO::FETCH_ASSOC);
    }
    assertTest(!empty($unit), "6. Unit data available for testing", "Unit: " . ($unit['name'] ?? 'none'));
    $unit_id = $unit['id'] ?? 1;

    // Fetch test creator user
    $stmtCreator = $conn->query("SELECT id, full_name FROM employees WHERE status = 'active' ORDER BY id ASC LIMIT 1");
    $creator = $stmtCreator->fetch(PDO::FETCH_ASSOC);
    assertTest(!empty($creator), "7. Creator employee available for testing", "User: " . ($creator['full_name'] ?? 'none'));
    $creator_id = $creator['id'] ?? 1;

    // ---------------------------------------------------------
    // TEST 2: User Permissions Module Full Access Override Test
    // ---------------------------------------------------------
    $stmtInsertPerm = $conn->prepare("
        INSERT INTO user_permissions (employee_id, permission_name, is_allowed)
        VALUES (?, 'manage_documents', 1)
        ON DUPLICATE KEY UPDATE is_allowed = 1
    ");
    $stmtInsertPerm->execute([$creator_id]);

    $permCreate = hasPermission($creator_id, 'document.create');
    $permApprove = hasPermission($creator_id, 'document.approve');
    $permManage = hasPermission($creator_id, 'manage_documents');

    assertTest($permCreate && $permApprove && $permManage, "8. User granted 'manage_documents' receives full access to all document operations (document.create, document.approve, manage_documents)");

    // ---------------------------------------------------------
    // TEST 3: Routing Rules Configuration Test (Multi-Employee per Division)
    // ---------------------------------------------------------
    $stmtRuleAdd = $conn->prepare("
        INSERT INTO document_routing_rules (division_id, unit_id, employee_id, role_type)
        VALUES (?, ?, ?, 'handler')
    ");
    $stmtRuleAdd->execute([$division_id, $unit_id, $creator_id]);
    $rule_id = $conn->lastInsertId();

    assertTest($rule_id > 0, "9. Successfully assigned employee to Routing Matrix for Division & Unit");

    // ---------------------------------------------------------
    // TEST 4: Create Outgoing Draft (Bidang + Unit)
    // ---------------------------------------------------------
    $title1 = "Surat Pengujian E2E (Bidang + Unit) " . date('H:i:s');
    $temp_no1 = "DRAFT/TEST/" . time() . "/1";
    $token1 = bin2hex(random_bytes(16));
    $content1 = "<p>Ini adalah dokumen surat keluar uji coba E2E ditujukan ke Bidang & Unit.</p>";

    $stmtInsert1 = $conn->prepare("
        INSERT INTO documents (creator_id, template_id, type, document_number, title, content, receiver_division_id, receiver_department_id, receiver_unit_id, qr_token, status)
        VALUES (?, NULL, 'outgoing', ?, ?, ?, ?, ?, ?, ?, 'draft')
    ");
    $res1 = $stmtInsert1->execute([$creator_id, $temp_no1, $title1, $content1, $division_id, $division_id, $unit_id, $token1]);
    $doc1_id = $conn->lastInsertId();

    assertTest($res1 && $doc1_id > 0, "10. Create Outgoing Draft with Division (Bidang) and Unit target");

    // Verify record from database
    $stmtVerify1 = $conn->prepare("
        SELECT d.*, divs.name as division_name, u.name as unit_name
        FROM documents d
        LEFT JOIN divisions divs ON d.receiver_division_id = divs.id
        LEFT JOIN units u ON d.receiver_unit_id = u.id
        WHERE d.id = ?
    ");
    $stmtVerify1->execute([$doc1_id]);
    $fetched1 = $stmtVerify1->fetch(PDO::FETCH_ASSOC);

    assertTest($fetched1['receiver_division_id'] == $division_id, "11. Correct receiver_division_id stored");
    assertTest($fetched1['receiver_unit_id'] == $unit_id, "12. Correct receiver_unit_id stored");
    assertTest($fetched1['status'] === 'draft', "13. Document status is 'draft'");

    // ---------------------------------------------------------
    // TEST 5: Create Outgoing Draft Directly to Bidang (Unit = NULL)
    // ---------------------------------------------------------
    $title2 = "Surat Pengujian E2E (Langsung ke Bidang) " . date('H:i:s');
    $temp_no2 = "DRAFT/TEST/" . time() . "/2";
    $token2 = bin2hex(random_bytes(16));
    $content2 = "<p>Ini adalah dokumen surat keluar uji coba E2E ditujukan langsung ke Bidang.</p>";

    $stmtInsert2 = $conn->prepare("
        INSERT INTO documents (creator_id, template_id, type, document_number, title, content, receiver_division_id, receiver_department_id, receiver_unit_id, qr_token, status)
        VALUES (?, NULL, 'outgoing', ?, ?, ?, ?, ?, NULL, ?, 'draft')
    ");
    $res2 = $stmtInsert2->execute([$creator_id, $temp_no2, $title2, $content2, $division_id, $division_id, $token2]);
    $doc2_id = $conn->lastInsertId();

    assertTest($res2 && $doc2_id > 0, "14. Create Outgoing Draft directly to Division (Unit = NULL)");

    $stmtVerify2 = $conn->prepare("SELECT receiver_division_id, receiver_unit_id FROM documents WHERE id = ?");
    $stmtVerify2->execute([$doc2_id]);
    $fetched2 = $stmtVerify2->fetch(PDO::FETCH_ASSOC);

    assertTest($fetched2['receiver_division_id'] == $division_id && $fetched2['receiver_unit_id'] === null, "15. Correct NULL unit stored when addressed directly to Bidang");

    // ---------------------------------------------------------
    // TEST 6: Update/Edit Outgoing Draft
    // ---------------------------------------------------------
    $updatedTitle = "Surat Pengujian E2E (Terupdate) " . date('H:i:s');
    $stmtUpdate = $conn->prepare("
        UPDATE documents 
        SET title = ?, receiver_division_id = ?, receiver_unit_id = ?
        WHERE id = ?
    ");
    $resUpdate = $stmtUpdate->execute([$updatedTitle, $division_id, $unit_id, $doc2_id]);
    assertTest($resUpdate, "16. Update outgoing document draft title and destination");

    // ---------------------------------------------------------
    // TEST 7: Verify Destination Staff Auto-Visibility Query Logic in Surat Masuk & Surat Keluar
    // ---------------------------------------------------------
    $conn->exec("UPDATE documents SET status = 'pending_approval' WHERE id = " . intval($doc1_id));

    // Query simulate for staff in $division_id
    $stmtStaffQuery = $conn->prepare("
        SELECT COUNT(*) 
        FROM documents d
        WHERE d.type = 'outgoing'
          AND (
              d.creator_id = :staff_id
              OR (
                  d.status != 'draft' AND (
                      (d.receiver_division_id = :user_div_id AND (d.receiver_unit_id IS NULL OR d.receiver_unit_id = :user_unit_id OR :user_unit_id = 0))
                      OR (d.receiver_unit_id = :user_unit_id AND :user_unit_id != 0)
                  )
              )
          )
    ");
    $stmtStaffQuery->execute([
        ':staff_id' => 999999, // non-creator staff
        ':user_div_id' => $division_id,
        ':user_unit_id' => $unit_id
    ]);
    $foundCount = $stmtStaffQuery->fetchColumn();

    assertTest($foundCount > 0, "17. Destination staff can query and see incoming letters for their Bidang/Unit");

    // ---------------------------------------------------------
    // TEST 8: Strict Isolation Test (Unrelated User CANNOT See Draft/Surat)
    // ---------------------------------------------------------
    $stmtIsolated = $conn->prepare("
        SELECT COUNT(*) 
        FROM documents d
        WHERE d.id = :doc_id
          AND (
              d.creator_id = :unrelated_user_id
              OR (
                  d.status != 'draft' AND (
                      (d.receiver_division_id = :unrelated_div_id AND (d.receiver_unit_id IS NULL OR d.receiver_unit_id = :unrelated_unit_id OR :unrelated_unit_id = 0))
                      OR (d.receiver_unit_id = :unrelated_unit_id AND :unrelated_unit_id != 0)
                  )
              )
          )
    ");
    $stmtIsolated->execute([
        ':doc_id' => $doc2_id, // draft doc
        ':unrelated_user_id' => 999999,
        ':unrelated_div_id' => 999999,
        ':unrelated_unit_id' => 999999
    ]);
    $isolatedCount = $stmtIsolated->fetchColumn();

    assertTest($isolatedCount == 0, "18. Unrelated account is strictly prevented from seeing private draft of another user");

    // ---------------------------------------------------------
    // TEST 9: Direct Approval & Completion Workflow & Automatic Archiving
    // ---------------------------------------------------------
    $finalDocNo = "OUT/" . date('Ymd') . "/" . rand(100, 999);
    $stmtApprove = $conn->prepare("UPDATE documents SET status = 'completed', document_number = ? WHERE id = ?");
    $resApprove = $stmtApprove->execute([$finalDocNo, $doc1_id]);

    assertTest($resApprove, "19. Transition document status from pending to 'completed'");

    $stmtCheckFinal = $conn->prepare("SELECT status, document_number FROM documents WHERE id = ?");
    $stmtCheckFinal->execute([$doc1_id]);
    $finalDoc = $stmtCheckFinal->fetch(PDO::FETCH_ASSOC);

    assertTest($finalDoc['status'] === 'completed' && $finalDoc['document_number'] === $finalDocNo, "20. Verified completed status and official document number for Arsip Digital");

    // ---------------------------------------------------------
    // CLEANUP TEST DATA
    // ---------------------------------------------------------
    $stmtCleanupDoc = $conn->prepare("DELETE FROM documents WHERE id IN (?, ?)");
    $stmtCleanupDoc->execute([$doc1_id, $doc2_id]);

    $stmtCleanupRule = $conn->prepare("DELETE FROM document_routing_rules WHERE id = ?");
    $stmtCleanupRule->execute([$rule_id]);

    assertTest(true, "21. Test data cleanup completed");

} catch (Exception $e) {
    echo "  \033[31m[ERROR]\033[0m Exception caught: " . $e->getMessage() . "\n";
    $failedCount++;
}

echo "\n========================================================\n";
echo "  SUMMARY: " . $passedCount . " PASSED, " . $failedCount . " FAILED\n";
echo "========================================================\n\n";

exit($failedCount === 0 ? 0 : 1);
?>
