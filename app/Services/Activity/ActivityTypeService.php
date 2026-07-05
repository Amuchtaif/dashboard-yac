<?php
// app/Services/Activity/ActivityTypeService.php

class ActivityTypeService {
    private $mysqli;

    public function __construct($mysqli = null) {
        if ($mysqli) {
            $this->mysqli = $mysqli;
        } else {
            global $mysqli;
            require_once __DIR__ . '/../../../config/db_mysqli.php';
            $this->mysqli = $mysqli;
        }
    }

    private function generateSlug($name) {
        return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
    }

    public function createType($data) {
        $name = isset($data['name']) ? trim($data['name']) : '';
        $type = isset($data['type']) ? trim($data['type']) : 'personal';
        $description = isset($data['description']) ? trim($data['description']) : null;
        $icon = isset($data['icon']) ? trim($data['icon']) : null;
        $color = isset($data['color']) ? trim($data['color']) : null;
        $point = isset($data['point']) ? (int)$data['point'] : 0;
        $sort_order = isset($data['sort_order']) ? (int)$data['sort_order'] : 0;
        $is_active = isset($data['is_active']) ? (int)$data['is_active'] : 1;

        if (empty($name)) {
            throw new Exception("Nama aktivitas wajib diisi.");
        }
        if (!in_array($type, ['personal', 'event'])) {
            throw new Exception("Tipe aktivitas harus 'personal' atau 'event'.");
        }

        $slug = $this->generateSlug($name);

        // Check unique slug
        $stmt = $this->mysqli->prepare("SELECT id FROM activity_types WHERE slug = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            throw new Exception("Nama jenis aktivitas sudah terdaftar.");
        }
        $stmt->close();

        $stmt = $this->mysqli->prepare("INSERT INTO activity_types (name, slug, type, description, icon, color, point, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssiii", $name, $slug, $type, $description, $icon, $color, $point, $sort_order, $is_active);
        
        if (!$stmt->execute()) {
            throw new Exception("Gagal menyimpan jenis aktivitas: " . $stmt->error);
        }
        $id = $stmt->insert_id;
        $stmt->close();

        return $id;
    }

    public function updateType($id, $data) {
        $id = (int)$id;
        $name = isset($data['name']) ? trim($data['name']) : '';
        $type = isset($data['type']) ? trim($data['type']) : '';
        $description = isset($data['description']) ? trim($data['description']) : null;
        $icon = isset($data['icon']) ? trim($data['icon']) : null;
        $color = isset($data['color']) ? trim($data['color']) : null;
        $point = isset($data['point']) ? (int)$data['point'] : null;
        $sort_order = isset($data['sort_order']) ? (int)$data['sort_order'] : null;
        $is_active = isset($data['is_active']) ? (int)$data['is_active'] : null;

        // Fetch existing
        $stmt = $this->mysqli->prepare("SELECT * FROM activity_types WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$existing) {
            throw new Exception("Jenis aktivitas tidak ditemukan.");
        }

        $update_fields = [];
        $params = [];
        $types = "";

        if (!empty($name)) {
            $slug = $this->generateSlug($name);
            // Check unique slug excluding current id
            $stmt = $this->mysqli->prepare("SELECT id FROM activity_types WHERE slug = ? AND id != ? AND deleted_at IS NULL LIMIT 1");
            $stmt->bind_param("si", $slug, $id);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) {
                throw new Exception("Nama jenis aktivitas sudah terdaftar.");
            }
            $stmt->close();

            $update_fields[] = "name = ?, slug = ?";
            $params[] = $name;
            $params[] = $slug;
            $types .= "ss";
        }

        if (!empty($type)) {
            if (!in_array($type, ['personal', 'event'])) {
                throw new Exception("Tipe aktivitas harus 'personal' atau 'event'.");
            }
            $update_fields[] = "type = ?";
            $params[] = $type;
            $types .= "s";
        }

        if (isset($data['description'])) {
            $update_fields[] = "description = ?";
            $params[] = $description;
            $types .= "s";
        }

        if (isset($data['icon'])) {
            $update_fields[] = "icon = ?";
            $params[] = $icon;
            $types .= "s";
        }

        if (isset($data['color'])) {
            $update_fields[] = "color = ?";
            $params[] = $color;
            $types .= "s";
        }

        if ($point !== null) {
            $update_fields[] = "point = ?";
            $params[] = $point;
            $types .= "i";
        }

        if ($sort_order !== null) {
            $update_fields[] = "sort_order = ?";
            $params[] = $sort_order;
            $types .= "i";
        }

        if ($is_active !== null) {
            $update_fields[] = "is_active = ?";
            $params[] = $is_active;
            $types .= "i";
        }

        if (empty($update_fields)) {
            return true;
        }

        $params[] = $id;
        $types .= "i";

        $sql = "UPDATE activity_types SET " . implode(", ", $update_fields) . " WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            throw new Exception("Gagal memperbarui jenis aktivitas: " . $stmt->error);
        }
        $stmt->close();

        return true;
    }

    public function deleteType($id) {
        $id = (int)$id;

        // Check if used in student_activities
        $stmt = $this->mysqli->prepare("SELECT COUNT(*) FROM student_activities WHERE activity_type_id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_row()[0];
        $stmt->close();

        if ($count > 0) {
            throw new Exception("Tidak dapat menghapus jenis aktivitas karena masih digunakan oleh aktivitas santri.");
        }

        // Soft delete
        $stmt = $this->mysqli->prepare("UPDATE activity_types SET deleted_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            throw new Exception("Gagal menghapus jenis aktivitas: " . $stmt->error);
        }
        $stmt->close();

        return true;
    }

    public function toggleStatus($id) {
        $id = (int)$id;
        $stmt = $this->mysqli->prepare("UPDATE activity_types SET is_active = 1 - is_active WHERE id = ? AND deleted_at IS NULL");
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            throw new Exception("Gagal memperbarui status jenis aktivitas: " . $stmt->error);
        }
        $stmt->close();
        return true;
    }

    public function getType($id) {
        $id = (int)$id;
        $stmt = $this->mysqli->prepare("SELECT * FROM activity_types WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $res;
    }

    public function listTypes($filters = [], $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        $where = "WHERE deleted_at IS NULL";
        $params = [];
        $types = "";

        if (isset($filters['type']) && !empty($filters['type'])) {
            $where .= " AND type = ?";
            $params[] = $filters['type'];
            $types .= "s";
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $where .= " AND is_active = ?";
            $params[] = (int)$filters['is_active'];
            $types .= "i";
        }

        if (isset($filters['search']) && !empty($filters['search'])) {
            $where .= " AND (name LIKE ? OR description LIKE ?)";
            $search_param = "%" . $filters['search'] . "%";
            $params[] = $search_param;
            $params[] = $search_param;
            $types .= "ss";
        }

        // Count total
        $count_query = "SELECT COUNT(*) FROM activity_types $where";
        $stmt = $this->mysqli->prepare($count_query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $total = $stmt->get_result()->fetch_row()[0];
        $stmt->close();

        // Fetch data
        $query = "SELECT t.*, (
            SELECT COUNT(*) FROM student_activities a WHERE a.activity_type_id = t.id AND a.deleted_at IS NULL
        ) as total_used FROM activity_types t $where ORDER BY t.sort_order ASC, t.name ASC LIMIT ? OFFSET ?";
        
        $fetch_params = $params;
        $fetch_params[] = $limit;
        $fetch_params[] = $offset;
        $fetch_types = $types . "ii";

        $stmt = $this->mysqli->prepare($query);
        $stmt->bind_param($fetch_types, ...$fetch_params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();

        return [
            'total' => $total,
            'pages' => ceil($total / $limit),
            'page' => $page,
            'limit' => $limit,
            'data' => $data
        ];
    }
}
