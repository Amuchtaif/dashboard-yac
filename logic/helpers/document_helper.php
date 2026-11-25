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

if (!function_exists('formatIndonesianDate')) {
    /**
     * Format standard date string (e.g. 2026-11-16) to Indonesian format (16 November 2026).
     */
    function formatIndonesianDate($date_str) {
        if (empty($date_str)) return date('d F Y');
        $time = strtotime($date_str);
        if (!$time) return $date_str;

        $day = date('d', $time);
        $month = (int)date('m', $time);
        $year = date('Y', $time);

        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        $month_name = $months[$month] ?? date('F', $time);
        return ltrim($day, '0') . ' ' . $month_name . ' ' . $year;
    }
}

if (!function_exists('getReservedTemplateTags')) {
    /**
     * System reserved tags that must not be prompted as manual input fields.
     */
    function getReservedTemplateTags() {
        return [
            'nomor_surat',
            'perihal',
            'tujuan',
            'tanggal_surat',
            'nama_penandatangan',
            'jabatan_penandatangan',
            'ruang_tanda_tangan',
            'kepala_unit',
            'kepala_bidang',
            'pemimpin',
            'logo_url'
        ];
    }
}

if (!function_exists('renderDocumentKopHtml')) {
    /**
     * Render the official Kop Surat letterhead HTML.
     */
    function renderDocumentKopHtml($template) {
        if (empty($template)) return '';

        if (!empty($template['header_image'])) {
            $banner_url = BASE_URL . '/' . htmlspecialchars($template['header_image']);
            return '<div class="kop-surat-banner" style="margin-bottom: 20px; text-align: center;"><img src="' . $banner_url . '" style="width: 100%; max-height: 140px; object-fit: contain;" alt="Kop Surat"></div>';
        }

        $logo_url = !empty($template['header_logo']) ? (BASE_URL . '/' . htmlspecialchars($template['header_logo'])) : (BASE_URL . '/uploads/kop_logos/logo_yac.png');
        $line1 = !empty($template['header_line_1']) ? htmlspecialchars($template['header_line_1']) : 'YAYASAN AS SUNNAH CIREBON';
        $line2 = !empty($template['header_line_2']) ? htmlspecialchars($template['header_line_2']) : 'BIDANG PENDIDIKAN';
        $address = !empty($template['header_address']) ? htmlspecialchars($template['header_address']) : 'Jl. Kalitanjung No.52B Kel. Karyamulya Kec. Kesambi Kota Cirebon 45135';

        return '<div class="kop-surat-header" style="display: flex; align-items: center; border-bottom: 3px double #1e293b; padding-bottom: 12px; margin-bottom: 24px; font-family: \'Times New Roman\', Times, serif;">' .
               '<div style="flex-shrink: 0; width: 90px; text-align: center; margin-right: 18px;">' .
               '<img src="' . $logo_url . '" style="max-height: 80px; max-width: 85px; object-fit: contain;" alt="Logo Yayasan">' .
               '</div>' .
               '<div style="flex-grow: 1; text-align: center;">' .
               '<h2 style="margin: 0; font-size: 15pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: #0f172a; line-height: 1.2;">' . $line1 . '</h2>' .
               '<h3 style="margin: 3px 0 0 0; font-size: 12.5pt; font-weight: bold; text-transform: uppercase; color: #1e293b; line-height: 1.2;">' . $line2 . '</h3>' .
               '<p style="margin: 5px 0 0 0; font-size: 9.5pt; color: #475569; line-height: 1.3;">' . $address . '</p>' .
               '</div>' .
               '</div>';
    }
}

if (!function_exists('renderDocumentSignatureStampHtml')) {
    /**
     * Render the signature stamp badge or image without redundant outer wrappers.
     */
    function renderDocumentSignatureStampHtml($status, $qrToken = '', $signaturePath = '') {
        if ($status === 'completed') {
            if (!empty($signaturePath) && file_exists(__DIR__ . '/../../' . $signaturePath)) {
                $sigImgUrl = BASE_URL . '/' . htmlspecialchars($signaturePath);
                return '<img src="' . $sigImgUrl . '" style="max-height: 75px; max-width: 140px; object-fit: contain;" alt="Tanda Tangan">';
            } else {
                return '<div style="border: 1.5px solid #10b981; border-radius: 8px; padding: 6px 14px; background: #ecfdf5; text-align: center; display: inline-block;">' .
                       '<span style="display: block; font-size: 8pt; font-weight: bold; color: #065f46; letter-spacing: 0.5px;">✓ DITANDATANGANI SECARA DIGITAL</span>' .
                       '<span style="display: block; font-size: 7.5pt; color: #047857; margin-top: 2px;">Tervalidasi Sistem YAC</span>' .
                       '</div>';
            }
        } elseif ($status === 'pending_approval') {
            return '<div style="border: 1px dashed #f59e0b; border-radius: 6px; padding: 6px 14px; background: #fffbeb; text-align: center; display: inline-block;">' .
                   '<span style="font-size: 8.5pt; font-weight: bold; color: #b45309;">⏳ Menunggu Persetujuan Pejabat</span>' .
                   '</div>';
        } else {
            return '<div style="border: 1px dashed #cbd5e1; border-radius: 6px; padding: 6px 14px; background: #f8fafc; text-align: center; display: inline-block;">' .
                   '<span style="font-size: 8.5pt; color: #94a3b8; font-style: italic;">[ Ruang Tanda Tangan ]</span>' .
                   '</div>';
        }
    }
}

if (!function_exists('processDocumentSignatureHtml')) {
    /**
     * Replace any existing signature placeholder or pending approval badge in content with the current status stamp.
     */
    function processDocumentSignatureHtml($content, $status, $qrToken = '', $signaturePath = '') {
        $stamp = renderDocumentSignatureStampHtml($status, $qrToken, $signaturePath);

        // 1. Direct template tag replacement if present
        if (strpos($content, '{{ruang_tanda_tangan}}') !== false) {
            return str_replace('{{ruang_tanda_tangan}}', $stamp, $content);
        }

        // 2. Standard template signature container: <div style="height: ...">...</div> before signer name <p>
        $patternWrapper = '/<div style="height:\s*(?:80|75)px[^"]*"[^>]*>.*?(?=\s*<p[^>]*>)/is';
        if (preg_match($patternWrapper, $content)) {
            return preg_replace(
                $patternWrapper,
                "<div style=\"height: 80px; display: flex; align-items: center; justify-content: center; margin: 10px 0;\">\n        " . $stamp . "\n    </div>\n    ",
                $content
            );
        }

        // 3. Fallback: replace naked dashed or solid stamp
        $patternNaked = '/<div[^>]*border:\s*(?:1px\s*dashed|1\.5px\s*solid)[^>]*>.*?<\/div>/is';
        if (preg_match($patternNaked, $content)) {
            return preg_replace($patternNaked, $stamp, $content);
        }

        return $content;
    }
}

if (!function_exists('renderDocumentSignatureBoxHtml')) {
    /**
     * Render the official bottom signature block HTML.
     */
    function renderDocumentSignatureBoxHtml($signerName, $signerPosition, $status, $qrToken = '', $signaturePath = '', $cityDate = '') {
        $cityDateStr = !empty($cityDate) ? $cityDate : ('Cirebon, ' . formatIndonesianDate(date('Y-m-d')));
        $sigArea = renderDocumentSignatureStampHtml($status, $qrToken, $signaturePath);

        return '<div class="signature-box" style="float: right; width: 320px; text-align: center; font-family: \'Times New Roman\', serif; font-size: 12pt; margin-top: 25px;">' .
               '<p style="margin: 0;">' . htmlspecialchars($cityDateStr) . '</p>' .
               '<p style="margin: 5px 0 0 0; font-weight: bold;">' . htmlspecialchars($signerPosition) . '</p>' .
               '<div style="height: 80px; display: flex; align-items: center; justify-content: center; margin: 10px 0;">' .
               $sigArea .
               '</div>' .
               '<p style="margin: 0; font-weight: bold; text-decoration: underline;">' . htmlspecialchars($signerName) . '</p>' .
               '</div>' .
               '<div style="clear: both;"></div>';
    }
}
