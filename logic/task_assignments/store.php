<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_employees');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $conn = $db->getConnection();

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $special_instructions = trim($_POST['special_instructions'] ?? '');
    $priority = $_POST['priority'] ?? 'Rutin';
    $due_date = $_POST['due_date'] ?? null;
    $created_by = $_POST['created_by'] ?? $_SESSION['user_id'];
    $assigned_to = $_POST['assigned_to'] ?? null;

    if (empty($title) || empty($assigned_to)) {
        header("Location: ../../views/task_assignments/index.php?error=" . urlencode("Judul dan penerima tugas wajib diisi."));
        exit;
    }

    try {
        $sql = "INSERT INTO assignments (title, description, special_instructions, priority, due_date, created_by, assigned_to)
                VALUES (:title, :description, :special_instructions, :priority, :due_date, :created_by, :assigned_to)";
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([
            ':title' => $title,
            ':description' => $description,
            ':special_instructions' => $special_instructions,
            ':priority' => $priority,
            ':due_date' => $due_date ?: null,
            ':created_by' => $created_by,
            ':assigned_to' => $assigned_to,
        ]);

        if ($result) {
            $assignment_id = $conn->lastInsertId();

            // Save notification
            try {
                $conn->exec("CREATE TABLE IF NOT EXISTS `notifications` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `user_id` INT NOT NULL,
                    `title` VARCHAR(255) NOT NULL,
                    `body` TEXT,
                    `type` VARCHAR(50) DEFAULT 'general',
                    `reference_id` INT DEFAULT NULL,
                    `is_read` TINYINT(1) DEFAULT 0,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_user` (`user_id`),
                    INDEX `idx_type` (`type`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, title, body, type, reference_id) VALUES (:uid, :title, :body, 'assignment', :rid)");
                $notifStmt->execute([
                    ':uid' => $assigned_to,
                    ':title' => 'Tugas Baru',
                    ':body' => "Anda mendapat tugas: $title",
                    ':rid' => $assignment_id,
                ]);
            } catch (Exception $e) {
                // Silent fail
            }

            header("Location: ../../views/task_assignments/index.php?success=" . urlencode("Tugas berhasil dibuat dan didelegasikan."));
        } else {
            header("Location: ../../views/task_assignments/index.php?error=" . urlencode("Gagal menyimpan tugas."));
        }
    } catch (PDOException $e) {
        header("Location: ../../views/task_assignments/index.php?error=" . urlencode("Error: " . $e->getMessage()));
    }
} else {
        header("Location: ../../views/task_assignments/index.php?error=Operasi+gagal");
}
