<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Handle both JSON and FormData
$json_data = json_decode(file_get_contents("php://input"));
$user_id = isset($_POST['user_id']) ? $_POST['user_id'] : ($json_data->user_id ?? null);

if (!empty($user_id)) {
    try {
        // Collect update parts
        $update_parts = [];
        $params = [':id' => $user_id];

        // Data from POST
        $full_name = isset($_POST['full_name']) ? $_POST['full_name'] : ($json_data->full_name ?? null);
        $phone_number = isset($_POST['phone_number']) ? $_POST['phone_number'] : ($json_data->phone_number ?? null);
        $address = isset($_POST['address']) ? $_POST['address'] : ($json_data->address ?? null);

        if ($full_name !== null) {
            $update_parts[] = "full_name = :full_name";
            $params[':full_name'] = $full_name;
        }

        if ($phone_number !== null) {
            $update_parts[] = "phone_number = :phone_number";
            $params[':phone_number'] = $phone_number;
        }

        if ($address !== null) {
            $update_parts[] = "address = :address";
            $params[':address'] = $address;
        }

        // Handle File Upload
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['profile_photo']['tmp_name'];
            $file_name = $_FILES['profile_photo']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Allowed extensions
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($file_ext, $allowed_exts)) {
                $new_file_name = "profile_" . $user_id . "_" . time() . "." . $file_ext;
                $upload_dir = "../uploads/profile_photos/";
                
                // Remove old photo if exists
                $stmt_old = $db->prepare("SELECT profile_photo FROM employees WHERE id = :id");
                $stmt_old->execute([':id' => $user_id]);
                $old_photo = $stmt_old->fetchColumn();
                if ($old_photo && file_exists($upload_dir . $old_photo)) {
                    unlink($upload_dir . $old_photo);
                }

                if (move_uploaded_file($file_tmp, $upload_dir . $new_file_name)) {
                    $update_parts[] = "profile_photo = :profile_photo";
                    $params[':profile_photo'] = $new_file_name;
                }
            }
        }

        if (empty($update_parts)) {
            echo json_encode(["success" => false, "message" => "Tidak ada data yang diubah."]);
            exit();
        }

        $query = "UPDATE employees SET " . implode(", ", $update_parts) . " WHERE id = :id";
        $stmt = $db->prepare($query);

        if ($stmt->execute($params)) {
            // Fetch updated data to return
            $queryFetch = "SELECT 
                            e.id, 
                            e.full_name, 
                            e.email, 
                            e.phone_number,
                            e.address,
                            e.profile_photo,
                            u.name AS unit_name, 
                            d.name AS division_name,
                            p.level AS position_level,
                            p.name AS position_name
                          FROM employees e 
                          LEFT JOIN positions p ON e.position_id = p.id
                          LEFT JOIN units u ON e.unit_id = u.id 
                          LEFT JOIN divisions d ON e.division_id = d.id 
                          WHERE e.id = :id";
            $stmtFetch = $db->prepare($queryFetch);
            $stmtFetch->bindParam(':id', $user_id);
            $stmtFetch->execute();
            $updatedUser = $stmtFetch->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                "success" => true,
                "message" => "Profil berhasil diperbarui",
                "data" => $updatedUser
            ]);
        } else {
            echo json_encode(["success" => false, "message" => "Gagal memperbarui profil."]);
        }
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "User ID tidak valid."]);
}
?>
