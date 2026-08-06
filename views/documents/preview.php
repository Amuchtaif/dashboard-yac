<?php
// views/documents/preview.php
require_once '../../config/database.php';

// Auto-detect project configuration for BASE_URL
if (!defined('BASE_URL')) {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? "https" : "http";
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
    $projectRoot = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
    $relativePath = '';
    if (stripos($projectRoot, $docRoot) === 0) {
        $relativePath = substr($projectRoot, strlen($docRoot));
    }
    $relativePath = '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
    $relativePath = rtrim($relativePath, '/');
    define('BASE_URL', $protocol . "://" . $host . $relativePath);
    define('BASE_PATH', $projectRoot);
    define('APP_NAME', 'Dashboard YAC');
}

$db = new Database();
$conn = $db->getConnection();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$document = null;
$approver_name = '';
$approver_pos = '';
$signature_path = '';

if ($id > 0) {
    // Fetch document details
    $stmtDoc = $conn->prepare("
        SELECT d.*, e.full_name as creator_name
        FROM documents d 
        LEFT JOIN employees e ON d.creator_id = e.id
        WHERE d.id = ?
        LIMIT 1
    ");
    $stmtDoc->execute([$id]);
    $document = $stmtDoc->fetch(PDO::FETCH_ASSOC);

    if ($document) {
        // Fetch template details if available
        $template = null;
        if (!empty($document['template_id'])) {
            $stmtTpl = $conn->prepare("SELECT * FROM document_templates WHERE id = ?");
            $stmtTpl->execute([$document['template_id']]);
            $template = $stmtTpl->fetch(PDO::FETCH_ASSOC);
        }

        if ($document['status'] === 'completed') {
            // Fetch the signatory details (final approver)
            $stmtSig = $conn->prepare("
                SELECT da.approved_at, e.full_name as approver_name, e.signature_path, p.name as approver_pos
                FROM document_approvals da
                JOIN employees e ON da.approver_id = e.id
                LEFT JOIN positions p ON e.position_id = p.id
                WHERE da.document_id = ? AND da.status = 'approved'
                ORDER BY da.stage_order DESC
                LIMIT 1
            ");
            $stmtSig->execute([$id]);
            $sig = $stmtSig->fetch(PDO::FETCH_ASSOC);
            if ($sig) {
                $approver_name = $sig['approver_name'];
                $approver_pos = $sig['approver_pos'] ?: 'Pimpinan';
                $signature_path = $sig['signature_path'];
            }
        }
    }
}

if (!$document) {
    echo "Dokumen tidak ditemukan atau belum selesai disetujui.";
    exit;
}

// Replace placeholders in document content
$logo_url = BASE_URL . '/' . ($template['header_logo'] ?? 'uploads/kop_logos/logo_yac.png');
$doc_content = $document['content'];

// Dynamic Kop Surat Injection if not already embedded
if (!empty($template) && stripos($doc_content, 'kop-surat-header') === false) {
    if (!empty($template['header_image'])) {
        $header_img_url = BASE_URL . '/' . $template['header_image'];
        $kop_html = '<div class="kop-surat-header" style="margin-bottom: 20px; text-align: center;"><img src="' . $header_img_url . '" style="max-width: 100%; height: auto;" alt="Kop Banner"></div>';
    } else {
        $h_line1 = !empty($template['header_line_1']) ? htmlspecialchars($template['header_line_1']) : 'YAYASAN AS SUNNAH CIREBON';
        $h_line2 = !empty($template['header_line_2']) ? htmlspecialchars($template['header_line_2']) : 'BIDANG PENDIDIKAN';
        $h_addr = !empty($template['header_address']) ? htmlspecialchars($template['header_address']) : 'Jl. Kalitanjung No.52B Kel. Karyamulya Kec. Kesambi Kota Cirebon 45135';
        $kop_html = '<div class="kop-surat-header" style="display: flex; align-items: center; border-bottom: 3px double #333; padding-bottom: 8px; margin-bottom: 20px;">' .
                    '<div style="flex-shrink: 0; width: 90px; text-align: center; margin-right: 15px;"><img src="' . $logo_url . '" style="max-height: 80px; max-width: 85px;" alt="Logo Yayasan"></div>' .
                    '<div style="flex-grow: 1; text-align: center; font-family: \'Times New Roman\', serif;">' .
                    '<h2 style="margin: 0; font-size: 17px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: #1e293b;">' . $h_line1 . '</h2>' .
                    '<h3 style="margin: 2px 0 0 0; font-size: 14px; font-weight: bold; text-transform: uppercase; color: #334155;">' . $h_line2 . '</h3>' .
                    '<p style="margin: 4px 0 0 0; font-size: 10.5px; color: #475569;">' . $h_addr . '</p>' .
                    '</div></div>';
    }
    $doc_content = $kop_html . $doc_content;
}

$doc_content = str_replace('{{logo_url}}', $logo_url, $doc_content);
$doc_content = str_replace('{{nomor_surat}}', htmlspecialchars($document['document_number']), $doc_content);
$doc_content = str_replace('{{perihal}}', htmlspecialchars($document['title']), $doc_content);
$doc_content = str_replace('{{tanggal_surat}}', date('d F Y', strtotime($document['created_at'])), $doc_content);
$doc_content = str_replace('{{nama_penandatangan}}', htmlspecialchars($approver_name ?: $document['creator_name']), $doc_content);
$doc_content = str_replace('{{jabatan_penandatangan}}', htmlspecialchars($approver_pos ?: 'Ketua Bidang Pendidikan Yayasan Assunnah Cirebon'), $doc_content);

$verification_url = BASE_URL . "/views/documents/verify?token=" . urlencode($document['qr_token']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Dokumen - <?php echo htmlspecialchars($document['title']); ?></title>
    <style>
        body {
            background-color: #fff;
            color: #000;
            font-family: "Times New Roman", Times, serif;
            font-size: 12pt;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }

        /* Printable Area Styling */
        .paper {
            width: 210mm;
            min-height: 297mm; /* A4 size */
            margin: 20mm auto;
            padding: 25mm 20mm 20mm 25mm; /* Margins */
            box-sizing: border-box;
            background-color: #fff;
        }

        /* Signatory box layout at the bottom of the letter */
        .signature-section {
            margin-top: 50px;
            width: 100%;
            page-break-inside: avoid;
        }

        .signature-table {
            width: 100%;
            border: none;
            margin-top: 30px;
        }

        .signature-table td {
            border: none;
            vertical-align: bottom;
            padding: 10px;
        }

        .signature-wrapper {
            position: relative;
            height: 100px;
            width: 100%;
        }

        .sig-image {
            position: absolute;
            bottom: 0;
            left: 0;
            max-height: 90px;
            max-width: 200px;
            z-index: 10;
        }

        .qr-image {
            max-height: 85px;
            max-width: 85px;
        }

        /* Print Specific CSS */
        @media print {
            body {
                background: none;
                margin: 0;
                padding: 0;
            }
            .paper {
                margin: 0;
                padding: 15mm;
                width: 100%;
                min-height: auto;
                box-shadow: none;
                border: none;
            }
            .no-print {
                display: none;
            }
        }

        /* Floating print control bar */
        .print-control {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: rgba(255,255,255,0.9);
            border: 1px solid #cbd5e1;
            padding: 10px 15px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            z-index: 999;
            font-family: Arial, sans-serif;
            display: flex;
            gap: 10px;
        }

        .print-btn {
            background-color: #4F46E5;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            font-size: 12px;
        }

        .print-btn:hover {
            background-color: #4338CA;
        }

        .close-btn {
            background-color: #fff;
            color: #475569;
            border: 1px solid #cbd5e1;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            font-size: 12px;
        }

        .close-btn:hover {
            background-color: #f8fafc;
        }
    </style>
</head>
<body>
    <!-- Floating Toolbar -->
    <div class="print-control no-print">
        <button class="close-btn" onclick="window.close()">Tutup</button>
        <button class="print-btn" onclick="window.print()">Cetak Dokumen</button>
    </div>

    <!-- Letter Paper -->
    <div class="paper">
        <!-- Rendered HTML Letter Content -->
        <div class="letter-content">
            <?php echo $doc_content; ?>
        </div>

        <!-- Render Signatory and QR Validation if Completed -->
        <?php if ($document['status'] === 'completed'): ?>
            <div class="signature-section">
                <table class="signature-table">
                    <tr>
                        <!-- Placeholder spacer -->
                        <td style="width: 50%;">
                            <!-- QR Validation info -->
                            <div style="font-size: 9pt; font-family: sans-serif; color: #475569; line-height: 1.4;">
                                <table style="border: none;">
                                    <tr>
                                        <td style="padding: 0; border: none; vertical-align: top;">
                                            <img class="qr-image" src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?php echo urlencode($verification_url); ?>" alt="QR Verification">
                                        </td>
                                        <td style="padding: 0 0 0 10px; border: none; vertical-align: middle;">
                                            <span style="font-weight: bold; color: #0f172a; display: block; margin-bottom: 2px;">DOKUMEN VALID</span>
                                            <span>Scan QR untuk memverifikasi keaslian surat ini melalui portal resmi yayasan.</span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                        
                        <!-- Signatory -->
                        <td style="width: 50%; text-align: left;">
                            <div style="font-family: sans-serif; font-size: 11pt; color: #000;">
                                <span><?php echo htmlspecialchars($approver_pos); ?>,</span>
                                
                                <div class="signature-wrapper" style="margin-top: 15px; margin-bottom: 15px;">
                                    <?php if (!empty($signature_path) && file_exists(BASE_PATH . '/' . $signature_path)): ?>
                                        <img class="sig-image" src="<?php echo BASE_URL . '/' . htmlspecialchars($signature_path); ?>" alt="Signature">
                                    <?php else: ?>
                                        <div style="height: 60px;"></div>
                                    <?php endif; ?>
                                </div>
                                
                                <span style="font-weight: bold; text-decoration: underline; display: block;"><?php echo htmlspecialchars($approver_name); ?></span>
                                <span style="font-size: 9pt; color: #475569;">NIP/NIK. [TTE-Sah]</span>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Auto-trigger Print Dialog -->
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            // Auto open print dialog but don't close window immediately
            setTimeout(() => {
                window.print();
            }, 500);
        });
    </script>
</body>
</html>
