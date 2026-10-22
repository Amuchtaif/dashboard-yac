<?php
// logic/helpers/document_helper.php
require_once __DIR__ . '/../../config/database.php';

if (!function_exists('getDocumentSignersForEmployee')) {
    /**
     * Resolve the signers (Kepala Unit, Kepala Bidang, Pemimpin) for a specific employee.
     * 
     * @param PDO $conn
     * @param int $employee_id
     * @return array
     */
    function getDocumentSignersForEmployee($conn, $employee_id) {
        $stmt = $conn->prepare("
            SELECT e.id, e.full_name, e.unit_id, e.division_id, e.position_id,
                   u.name as unit_name, d.name as division_name, p.name as position_name
            FROM employees e
            LEFT JOIN units u ON e.unit_id = u.id
            LEFT JOIN divisions d ON e.division_id = d.id
            LEFT JOIN positions p ON e.position_id = p.id
            WHERE e.id = ?
        ");
        $stmt->execute([$employee_id]);
        $emp = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $signers = [
            'employee' => $emp ?: [
                'id' => $employee_id,
                'full_name' => 'Pegawai',
                'unit_id' => null,
                'division_id' => null,
                'unit_name' => 'Semua Unit',
                'division_name' => 'Yayasan',
                'position_name' => 'Staf'
            ],
            'unit_head' => null,
            'division_head' => null,
            'leader' => null
        ];

        // 1. Kepala Unit (Unit Head)
        if (!empty($emp['unit_id'])) {
            $stmtUnitHead = $conn->prepare("
                SELECT e.id, e.full_name, e.phone_number, p.name as position_name, p.level, e.signature_path, u.name as unit_name
                FROM employees e
                JOIN positions p ON e.position_id = p.id
                JOIN units u ON e.unit_id = u.id
                WHERE e.unit_id = ? 
                  AND (e.status = 'active' OR e.status IS NULL)
                  AND (p.name LIKE '%Kepala%' OR p.name LIKE '%Ketua%' OR p.name LIKE '%Koordinator%' OR p.level = 3)
                ORDER BY (CASE WHEN p.name LIKE '%Kepala Unit%' THEN 1 WHEN p.name LIKE '%Kepala%' THEN 2 ELSE 3 END), p.level ASC
                LIMIT 1
            ");
            $stmtUnitHead->execute([$emp['unit_id']]);
            $unitHead = $stmtUnitHead->fetch(PDO::FETCH_ASSOC);
            if ($unitHead) {
                $signers['unit_head'] = $unitHead;
            }
        }

        // 2. Kepala Bidang (Division Head)
        if (!empty($emp['division_id'])) {
            // A. Check employee in same division with formal position 'Kepala Bidang' or level 2
            $stmtDivPos = $conn->prepare("
                SELECT e.id, e.full_name, e.phone_number, p.name as position_name, p.level, e.signature_path, d.name as division_name
                FROM employees e
                JOIN positions p ON e.position_id = p.id
                JOIN divisions d ON e.division_id = d.id
                WHERE e.division_id = ? AND (e.status = 'active' OR e.status IS NULL)
                  AND (p.name LIKE '%Kepala Bidang%' OR p.level = 2)
                ORDER BY (CASE WHEN p.name LIKE '%Kepala Bidang%' THEN 1 ELSE 2 END), p.level ASC
                LIMIT 1
            ");
            $stmtDivPos->execute([$emp['division_id']]);
            $divHead = $stmtDivPos->fetch(PDO::FETCH_ASSOC);

            // B. Fallback: divisions.manager_id
            if (!$divHead) {
                $stmtDivManager = $conn->prepare("
                    SELECT e.id, e.full_name, e.phone_number, p.name as position_name, p.level, e.signature_path, d.name as division_name
                    FROM divisions d
                    JOIN employees e ON d.manager_id = e.id
                    LEFT JOIN positions p ON e.position_id = p.id
                    WHERE d.id = ? AND (e.status = 'active' OR e.status IS NULL)
                    LIMIT 1
                ");
                $stmtDivManager->execute([$emp['division_id']]);
                $divHead = $stmtDivManager->fetch(PDO::FETCH_ASSOC);
            }
            if ($divHead) {
                $signers['division_head'] = $divHead;
            }
        }

        // 3. Pemimpin / Mudir Yayasan (Top Leader)
        $stmtLeader = $conn->prepare("
            SELECT e.id, e.full_name, e.phone_number, p.name as position_name, p.level, e.signature_path
            FROM employees e
            JOIN positions p ON e.position_id = p.id
            WHERE (e.status = 'active' OR e.status IS NULL)
              AND (p.name LIKE '%Mudir%' OR p.name LIKE '%Pimpinan%' OR (p.level = 1 AND p.name != 'Administrator'))
            ORDER BY p.level ASC, e.id ASC
            LIMIT 1
        ");
        $stmtLeader->execute();
        $leader = $stmtLeader->fetch(PDO::FETCH_ASSOC);
        if ($leader) {
            $signers['leader'] = $leader;
        }

        return $signers;
    }
}

if (!function_exists('getResolvedSignerInfo')) {
    /**
     * Get specific signer info by type ('unit_head', 'division_head', 'leader', 'workflow')
     */
    function getResolvedSignerInfo($conn, $employee_id, $signer_type = 'unit_head') {
        $signers = getDocumentSignersForEmployee($conn, $employee_id);
        
        switch ($signer_type) {
            case 'division_head':
                if (!empty($signers['division_head'])) {
                    $divName = $signers['division_head']['division_name'] ?? 'Yayasan';
                    return [
                        'id' => $signers['division_head']['id'],
                        'name' => $signers['division_head']['full_name'],
                        'position' => 'Kepala Bidang ' . $divName,
                        'phone' => $signers['division_head']['phone_number'] ?? '',
                        'signature_path' => $signers['division_head']['signature_path'] ?? '',
                        'type' => 'division_head',
                        'type_label' => 'Kepala Bidang'
                    ];
                }
                break;

            case 'leader':
                if (!empty($signers['leader'])) {
                    return [
                        'id' => $signers['leader']['id'],
                        'name' => $signers['leader']['full_name'],
                        'position' => 'Mudir Yayasan Assunnah Cirebon',
                        'phone' => $signers['leader']['phone_number'] ?? '',
                        'signature_path' => $signers['leader']['signature_path'] ?? '',
                        'type' => 'leader',
                        'type_label' => 'Pemimpin / Mudir'
                    ];
                }
                break;

            case 'unit_head':
            default:
                if (!empty($signers['unit_head'])) {
                    $unitName = $signers['unit_head']['unit_name'] ?? 'Unit';
                    return [
                        'id' => $signers['unit_head']['id'],
                        'name' => $signers['unit_head']['full_name'],
                        'position' => 'Kepala ' . $unitName,
                        'phone' => $signers['unit_head']['phone_number'] ?? '',
                        'signature_path' => $signers['unit_head']['signature_path'] ?? '',
                        'type' => 'unit_head',
                        'type_label' => 'Kepala Unit'
                    ];
                }
                // Fallback to division head or leader if unit head is not found
                if (!empty($signers['division_head'])) {
                    return getResolvedSignerInfo($conn, $employee_id, 'division_head');
                } elseif (!empty($signers['leader'])) {
                    return getResolvedSignerInfo($conn, $employee_id, 'leader');
                }
                break;
        }

        // Ultimate fallback: Leader
        if (!empty($signers['leader'])) {
            return [
                'id' => $signers['leader']['id'],
                'name' => $signers['leader']['full_name'],
                'position' => 'Mudir Yayasan Assunnah Cirebon',
                'phone' => $signers['leader']['phone_number'] ?? '',
                'signature_path' => $signers['leader']['signature_path'] ?? '',
                'type' => 'leader',
                'type_label' => 'Pemimpin / Mudir'
            ];
        }

        return [
            'id' => 1,
            'name' => 'Pimpinan Yayasan',
            'position' => 'Pimpinan Yayasan Assunnah Cirebon',
            'phone' => '',
            'signature_path' => '',
            'type' => 'leader',
            'type_label' => 'Pemimpin'
        ];
    }
}
