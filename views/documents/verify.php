<?php
// views/documents/verify.php
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
    define('APP_NAME', 'Dashboard YAC');
}

$db = new Database();
$conn = $db->getConnection();

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$document = null;
$approver_name = '';
$approved_date = '';

if (!empty($token)) {
    // Query document details
    $stmtDoc = $conn->prepare("
        SELECT d.*, e.full_name as creator_name, u.name as creator_unit
        FROM documents d 
        LEFT JOIN employees e ON d.creator_id = e.id
        LEFT JOIN units u ON e.unit_id = u.id
        WHERE d.qr_token = ? AND d.status = 'completed'
        LIMIT 1
    ");
    $stmtDoc->execute([$token]);
    $document = $stmtDoc->fetch(PDO::FETCH_ASSOC);

    if ($document) {
        // Find the final stage approved details (signatory)
        $stmtSig = $conn->prepare("
            SELECT da.approved_at, e.full_name as approver_name
            FROM document_approvals da
            JOIN employees e ON da.approver_id = e.id
            WHERE da.document_id = ? AND da.status = 'approved'
            ORDER BY da.stage_order DESC
            LIMIT 1
        ");
        $stmtSig->execute([$document['id']]);
        $sig = $stmtSig->fetch(PDO::FETCH_ASSOC);
        if ($sig) {
            $approver_name = $sig['approver_name'];
            $approved_date = date('d F Y, H:i', strtotime($sig['approved_at']));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Dokumen Resmi - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f8fafc;
        }
    </style>
</head>
<body class="flex flex-col min-h-screen">
    <!-- Navbar / Header -->
    <header class="bg-white border-b border-slate-200 py-4 shadow-sm">
        <div class="max-w-4xl mx-auto px-4 flex items-center justify-between">
            <span class="text-sm font-black text-slate-800 tracking-wider uppercase"><?php echo APP_NAME; ?></span>
            <span class="text-xs font-bold text-indigo-600 bg-indigo-50 px-2.5 py-1 rounded-lg">Layanan Verifikasi QR</span>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-grow flex items-center justify-center py-12 px-4">
        <div class="w-full max-w-lg bg-white rounded-3xl border border-slate-200/80 shadow-xl p-8 relative overflow-hidden">
            <!-- Background design touches -->
            <div class="absolute -right-16 -top-16 w-36 h-36 rounded-full bg-slate-50"></div>
            
            <?php if ($document): ?>
                <!-- VALID DOCUMENT STATE -->
                <div class="text-center relative">
                    <!-- Verification Icon -->
                    <div class="mx-auto h-20 w-20 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center ring-8 ring-emerald-50 mb-6">
                        <i class="fa-solid fa-circle-check text-4xl"></i>
                    </div>

                    <h1 class="text-xl font-extrabold text-slate-800 tracking-tight">Dokumen Valid & Terverifikasi</h1>
                    <p class="text-xs text-slate-400 mt-1">Sistem Persuratan Digital Resmi Yayasan</p>

                    <!-- Document Metadata List -->
                    <div class="mt-8 border border-slate-100 rounded-2xl bg-slate-50/50 p-5 space-y-4 text-left text-xs">
                        <div class="border-b border-slate-100 pb-2.5">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Perihal / Judul Surat</span>
                            <p class="font-extrabold text-slate-700 mt-1"><?php echo htmlspecialchars($document['title']); ?></p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Nomor Surat</span>
                                <p class="font-bold text-slate-700 mt-0.5"><?php echo htmlspecialchars($document['document_number']); ?></p>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Unit Kerja</span>
                                <p class="font-bold text-slate-700 mt-0.5"><?php echo htmlspecialchars($document['creator_unit'] ?: 'Yayasan'); ?></p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Penandatangan</span>
                                <p class="font-bold text-slate-700 mt-0.5"><?php echo htmlspecialchars($approver_name ?: 'Ketua Yayasan'); ?></p>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Tanggal TTE</span>
                                <p class="font-bold text-slate-700 mt-0.5"><?php echo $approved_date; ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 text-center text-[10px] text-slate-400">
                        <p>Dokumen ini ditandatangani secara elektronik (TTE) menggunakan tanda tangan digital yayasan yang sah secara hukum internal yayasan.</p>
                    </div>
                </div>
            <?php else: ?>
                <!-- INVALID DOCUMENT STATE -->
                <div class="text-center relative">
                    <!-- Warning Icon -->
                    <div class="mx-auto h-20 w-20 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center ring-8 ring-rose-50 mb-6">
                        <i class="fa-solid fa-circle-xmark text-4xl"></i>
                    </div>

                    <h1 class="text-xl font-extrabold text-slate-800 tracking-tight">Dokumen Tidak Valid</h1>
                    <p class="text-xs text-slate-400 mt-1">Kode token digital atau QR tidak terdaftar dalam database kami.</p>

                    <div class="mt-6 bg-rose-50 border border-rose-100 rounded-2xl p-5 text-left text-xs text-rose-800 leading-relaxed font-medium">
                        <p class="font-bold text-rose-900 mb-1">Kemungkinan Penyebab:</p>
                        <ul class="list-disc pl-4 space-y-1 mt-2">
                            <li>QR Code dipalsukan atau direkayasa secara ilegal.</li>
                            <li>Dokumen merupakan draft yang ditolak atau dihapus.</li>
                            <li>Terdapat kesalahan pembacaan karakter token kode QR.</li>
                        </ul>
                    </div>

                    <div class="mt-8">
                        <a href="mailto:admin@yayasan.or.id" class="inline-flex items-center justify-center rounded-xl bg-slate-800 px-5 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-slate-700 transition-colors">
                            Hubungi Admin Yayasan
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-100 py-6 text-center text-xs text-slate-400">
        <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. Hak Cipta Dilindungi Undang-Undang.</p>
    </footer>
</body>
</html>
