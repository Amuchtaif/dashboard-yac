<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->user_id)) {
    try {
        // Collect update parts
        $update_parts = [];
        $params = [':id' => $data->user_id];

        if (isset($data->full_name)) {
            $update_parts[] = "full_name = :full_name";
            $params[':full_name'] = $data->full_name;
        }

        if (isset($data->phone_number)) {
            $update_parts[] = "phone_number = :phone_number";
            $params[':phone_number'] = $data->phone_number;
        }

        if (isset($data->address)) {
            $update_parts[] = "address = :address";
            $params[':address'] = $data->address;
        }

        if (empty($update_parts)) {
            echo json_encode(["success" => false, "message" => "No data provided for update."]);
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
                            e.address as alamat,
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
            $stmtFetch->bindParam(':id', $data->user_id);
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
