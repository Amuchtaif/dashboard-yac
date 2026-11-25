<?php
// logic/settings/upload_apk.php
require_once '../../config/app.php';
require_once '../../config/database.php';

header('Content-Type: application/json; charset=UTF-8');

// Matikan batas waktu eksekusi untuk transfer file besar
@set_time_limit(300);
@ini_set('memory_limit', '512M');

// Cek autentikasi & permission
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Sesi login Anda telah berakhir. Silakan muat ulang (refresh) halaman lalu masuk kembali.'
    ]);
    exit;
}

if (!function_exists('hasPermission')) {
    require_once '../../config/permission.php';
}

if (!hasPermission($_SESSION['user_id'], 'Administrator')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Akses ditolak: Anda tidak memiliki hak akses Administrator.'
    ]);
    exit;
}

// Deteksi jika request POST kosong karena payload melebihi post_max_size hosting
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
    http_response_code(413);
    echo json_encode([
        'success' => false,
        'error' => 'Ukuran potongan file melebihi batas post_max_size server hosting (' . ini_get('post_max_size') . ').'
    ]);
    exit;
}

$action = $_POST['action'] ?? '';

// 1. AJAX: Chunked APK Upload Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'chunk_upload_apk') {
    $uploadId = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['upload_id'] ?? '');
    $chunkIndex = (int)($_POST['chunk_index'] ?? 0);
    $totalChunks = (int)($_POST['total_chunks'] ?? 1);
    $fileName = preg_replace('/[^a-zA-Z0-9_.-]/', '', basename($_POST['file_name'] ?? 'app.apk'));
    $fileSize = (int)($_POST['file_size'] ?? 0);
    $buildNumber = (int)($_POST['build_number'] ?? 1);

    if (empty($uploadId)) {
        echo json_encode(['success' => false, 'error' => 'Upload ID tidak valid.']);
        exit;
    }

    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if ($ext !== 'apk') {
        echo json_encode(['success' => false, 'error' => 'Format file tidak didukung. Harap unggah file berekstensi .apk']);
        exit;
    }

    if (!isset($_FILES['file_chunk']) || $_FILES['file_chunk']['error'] !== UPLOAD_ERR_OK) {
        $uploadErrCode = $_FILES['file_chunk']['error'] ?? -1;
        $errMsg = 'Gagal menerima potongan file (Error Code: ' . $uploadErrCode . ').';
        if ($uploadErrCode === UPLOAD_ERR_INI_SIZE || $uploadErrCode === UPLOAD_ERR_FORM_SIZE) {
            $errMsg = 'Ukuran chunk melebihi batas upload_max_filesize server hosting (' . ini_get('upload_max_filesize') . ').';
        }
        echo json_encode(['success' => false, 'error' => $errMsg]);
        exit;
    }

    $targetDir = '../../uploads/app/';
    $tempDir = $targetDir . 'temp/';

    if (!is_dir($targetDir)) {
        if (!@mkdir($targetDir, 0755, true)) {
            echo json_encode(['success' => false, 'error' => 'Folder uploads/app/ gagal dibuat di server. Periksa hak akses direktori hosting.']);
            exit;
        }
    }
    if (!is_dir($tempDir)) {
        if (!@mkdir($tempDir, 0755, true)) {
            echo json_encode(['success' => false, 'error' => 'Folder uploads/app/temp/ gagal dibuat di server. Periksa hak akses direktori hosting.']);
            exit;
        }
    }

    $tempFilePath = $tempDir . 'upload_' . $uploadId . '.part';

    // Tulis/sambungkan potongan file (append mode)
    $in = @fopen($_FILES['file_chunk']['tmp_name'], 'rb');
    $out = @fopen($tempFilePath, ($chunkIndex === 0) ? 'wb' : 'ab');

    if (!$in || !$out) {
        if ($in) fclose($in);
        if ($out) fclose($out);
        echo json_encode(['success' => false, 'error' => 'Gagal menulis potongan file ke server. Periksa permission folder uploads/app/temp.']);
        exit;
    }

    while ($buff = fread($in, 8192)) {
        fwrite($out, $buff);
    }
    fclose($in);
    fclose($out);

    // Cek apakah seluruh potongan sudah selesai diunggah
    if (($chunkIndex + 1) >= $totalChunks) {
        $finalSize = file_exists($tempFilePath) ? filesize($tempFilePath) : 0;
        
        $finalFileName = 'app-release-b' . $buildNumber . '-' . time() . '.apk';
        $finalTargetPath = $targetDir . $finalFileName;

        if (!@rename($tempFilePath, $finalTargetPath)) {
            if (!@copy($tempFilePath, $finalTargetPath)) {
                echo json_encode(['success' => false, 'error' => 'Gagal memindahkan file APK selesai ke folder uploads/app/.']);
                exit;
            }
            @unlink($tempFilePath);
        }

        $relUrl = 'uploads/app/' . $finalFileName;

        // Resolve absolute URL
        $isHttps = (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' && !empty($_SERVER['HTTPS']))
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
            || (isset($_SERVER['HTTP_HOST']) && stripos($_SERVER['HTTP_HOST'], 'assunnahcirebon.com') !== false);
        $proto = $isHttps ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'dashboard.assunnahcirebon.com';
        $fullUrl = rtrim($proto . "://" . $host, '/') . '/' . ltrim($relUrl, '/');

        // Format ukuran file
        $sizeMb = round($finalSize / (1024 * 1024), 2);
        $sizeFormatted = $sizeMb > 0 ? ($sizeMb . ' MB') : (round($finalSize / 1024, 1) . ' KB');

        // Bersihkan file part usang yang lebih lama dari 12 jam
        try {
            $oldParts = glob($tempDir . '*.part');
            $now = time();
            if ($oldParts) {
                foreach ($oldParts as $part) {
                    if (is_file($part) && ($now - filemtime($part) > 43200)) {
                        @unlink($part);
                    }
                }
            }
        } catch (\Throwable $t) {}

        echo json_encode([
            'success' => true,
            'completed' => true,
            'file_url' => $relUrl,
            'full_url' => $fullUrl,
            'file_name' => $finalFileName,
            'original_name' => $fileName,
            'file_size' => $finalSize,
            'file_size_formatted' => $sizeFormatted,
            'message' => 'File APK berhasil diunggah dan siap dirilis.'
        ]);
        exit;
    } else {
        echo json_encode([
            'success' => true,
            'completed' => false,
            'chunk_index' => $chunkIndex,
            'total_chunks' => $totalChunks,
            'percent' => round((($chunkIndex + 1) / $totalChunks) * 100)
        ]);
        exit;
    }
}

// 2. AJAX: Batalkan Upload & Bersihkan File Temp
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'cancel_upload_apk') {
    $uploadId = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['upload_id'] ?? '');
    if (!empty($uploadId)) {
        $tempPart = '../../uploads/app/temp/upload_' . $uploadId . '.part';
        if (file_exists($tempPart)) {
            @unlink($tempPart);
        }
    }
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Aksi tidak valid atau request kosong.']);
exit;
