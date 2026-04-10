<?php
// api/inventory/locations/create.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
require_once __DIR__ . '/../../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $data = json_decode(file_get_contents("php://input"));
    $name = $data->name ?? $_POST['name'] ?? '';
    $parent_id = $data->parent_id ?? $_POST['parent_id'] ?? null;
    $label = $data->label ?? $name;

    if (empty($name)) {
        echo json_encode(["success" => false, "message" => "Name is required."]);
        exit;
    }

    if ($parent_id === "") $parent_id = null; // Normalize empty string to null

    // Auto-generate Code
    $words = explode(' ', trim($name));
    $code = "";

    function getDistinctConsonants($word, $limit = 2) {
        $consonants = preg_replace('/[aeiou\s]/i', '', $word);
        $result = "";
        $lastChar = "";
        for ($i = 0; $i < strlen($consonants); $i++) {
            if ($consonants[$i] !== $lastChar) {
                $result .= $consonants[$i];
                $lastChar = $consonants[$i];
                if (strlen($result) >= $limit) break;
            }
        }
        return strtoupper($result);
    }

    if (count($words) == 1) {
        $first = strtoupper(substr($words[0], 0, 1));
        $rest = getDistinctConsonants(substr($words[0], 1), 2);
        $code = $first . $rest;
        if (strlen($code) < 3) $code = strtoupper(substr($words[0], 0, 3));
    } else {
        $lastWord = end($words);
        if (is_numeric($lastWord)) {
            $firstChar = strtoupper(substr($words[0], 0, 1));
            $rest = getDistinctConsonants(substr($words[0], 1), 2);
            $code = $firstChar . $rest . $lastWord;
        } else {
            // "Gedung Yayasan" -> G-YYSN
            $firstChar = strtoupper(substr($words[0], 0, 1));
            $secondWord = $words[1];
            $secondConsonants = strtoupper(preg_replace('/[aeiou\s]/i', '', $secondWord));
            $code = $firstChar . "-" . $secondConsonants;
        }
    }

    $stmt = $conn->prepare("INSERT INTO inventory_locations (name, location_code, location_label, parent_id) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$name, $code, $label, $parent_id])) {
        echo json_encode(["success" => true, "message" => "Location created successfully.", "id" => $conn->lastInsertId(), "code" => $code]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to create location."]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
