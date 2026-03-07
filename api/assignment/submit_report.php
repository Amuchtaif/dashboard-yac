<?php
// api/assignment/submit_report.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $task_id = $_POST['task_id'] ?? null;
    $report_notes = $_POST['report_notes'] ?? '';

    // Robust Log
    $logFile = __DIR__ . '/../fcm_debug.log';
    $logData = date('Y-m-d H:i:s') . " [REPORT_IN] - Task ID: " . ($task_id ?? 'NULL') . " | Notes: $report_notes\n";
    if (!empty($_FILES)) {
        foreach ($_FILES as $key => $file) {
            $logData .= "  FILE[$key]: " . $file['name'] . " | Size: " . $file['size'] . " | Error: " . $file['error'] . "\n";
        }
    } else {
        $logData .= "  NO FILES RECEIVED\n";
    }
    file_put_contents($logFile, $logData, FILE_APPEND);

    if (!$task_id) {
        echo json_encode(["status" => "error", "message" => "ID Tugas tidak disertakan."]);
        exit();
    }

    // Handle File Upload
    $attachmentName = null;
    if (isset($_FILES['attachment'])) {
        file_put_contents(__DIR__ . '/../fcm_debug.log', date('Y-m-d H:i:s') . " [REPORT] - File: " . $_FILES['attachment']['name'] . " Err: " . $_FILES['attachment']['error'] . "\n", FILE_APPEND);
    }

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $uploadFileDir = '../../uploads/assignments/';
        if (!is_dir($uploadFileDir)) {
            mkdir($uploadFileDir, 0755, true);
        }

        $fileExtension = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
        // Allowed extensions: pdf, zip, jpg, jpeg, png
        $allowed = ['pdf', 'zip', 'jpg', 'jpeg', 'png'];
        if (in_array($fileExtension, $allowed)) {
            $newFileName = time() . '_' . rand(1000, 9999) . '.' . $fileExtension;
            $dest_path = $uploadFileDir . $newFileName;

            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dest_path)) {
                $attachmentName = $newFileName;
            } else {
                echo json_encode(["status" => "error", "message" => "Gagal mengunggah berkas."]);
                exit();
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Format berkas tidak diizinkan. Gunakan PDF, ZIP, atau Gambar."]);
            exit();
        }
    }

    $sql = "UPDATE assignments SET 
            report_notes = :report_notes, 
            report_attachment = :report_attachment,
            status = 'Selesai',
            progress = 100,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = :id";

    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        ':report_notes' => $report_notes,
        ':report_attachment' => $attachmentName,
        ':id' => $task_id
    ]);

    if ($result) {
        $logData = date('Y-m-d H:i:s') . " [REPORT_SUCCESS] - Task ID: $task_id update ok\n";
        file_put_contents($logFile, $logData, FILE_APPEND);
        echo json_encode([
            "success" => true,
            "status" => "success",
            "message" => "Laporan tugas berhasil dikirim dan status diupdate menjadi Selesai."
        ]);
    } else {
        $err = $stmt->errorInfo();
        $logData = date('Y-m-d H:i:s') . " [REPORT_ERROR] - SQL Error: " . json_encode($err) . "\n";
        file_put_contents($logFile, $logData, FILE_APPEND);
        echo json_encode([
            "success" => false, 
            "status" => "error", 
            "message" => "Gagal memperbarui status tugas: " . $err[2]
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Internal Server Error: " . $e->getMessage()]);
}
