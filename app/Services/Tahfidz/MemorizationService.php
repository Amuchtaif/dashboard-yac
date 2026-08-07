<?php
// app/Services/Tahfidz/MemorizationService.php

class MemorizationService {
    private $mysqli;
    private $quran_data = null;

    public function __construct($mysqli = null) {
        if ($mysqli) {
            $this->mysqli = $mysqli;
        } else {
            global $mysqli;
            require_once __DIR__ . '/../../../config/db_mysqli.php';
            $this->mysqli = $mysqli;
        }
        $this->loadQuranData();
    }

    private function loadQuranData() {
        $json_path = __DIR__ . '/../../../api/quran/surat.json';
        if (!file_exists($json_path)) {
            $json_path = __DIR__ . '/../../api/quran/surat.json';
        }
        if (file_exists($json_path)) {
            $data = json_decode(file_get_contents($json_path), true);
            if (isset($data['data'])) {
                $this->quran_data = [];
                foreach ($data['data'] as $s) {
                    $this->quran_data[(int)$s['nomor']] = $s;
                    $this->quran_data[strtolower(trim($s['namaLatin']))] = $s;
                    $cleanName = strtolower(preg_replace('/[^a-z0-9]/i', '', $s['namaLatin']));
                    if (!empty($cleanName)) {
                        $this->quran_data[$cleanName] = $s;
                    }
                }
            }
        }
    }

    public function resolveSurahInfo($val) {
        if ($val === null || $val === '') return null;
        if (is_numeric($val) && isset($this->quran_data[(int)$val])) {
            return $this->quran_data[(int)$val];
        }
        $str = strtolower(trim((string)$val));
        if (isset($this->quran_data[$str])) {
            return $this->quran_data[$str];
        }
        $cleanStr = preg_replace('/[^a-z0-9]/i', '', $str);
        if (!empty($cleanStr) && isset($this->quran_data[$cleanStr])) {
            return $this->quran_data[$cleanStr];
        }
        return null;
    }

    public function normalizeData(&$data) {
        // Normalize entry_type
        if (isset($data['entry_type']) && !empty($data['entry_type'])) {
            $data['entry_type'] = strtoupper($data['entry_type']);
        } elseif (isset($data['jenis_setoran']) && !empty($data['jenis_setoran'])) {
            $data['entry_type'] = strtoupper($data['jenis_setoran']);
        } else {
            $data['entry_type'] = 'HAFALAN_BARU';
        }

        // Normalize start surah
        $start_val = !empty($data['start_surah_id']) ? $data['start_surah_id'] : (!empty($data['surah_start']) ? $data['surah_start'] : (!empty($data['surah_id']) ? $data['surah_id'] : null));
        $start_info = $this->resolveSurahInfo($start_val);
        if ($start_info) {
            $data['start_surah_id'] = (int)$start_info['nomor'];
            $data['surah_id'] = (int)$start_info['nomor'];
            $data['surah_start'] = $start_info['namaLatin'];
        } else if (is_numeric($start_val) && (int)$start_val > 0) {
            $data['start_surah_id'] = (int)$start_val;
            $data['surah_id'] = (int)$start_val;
            $data['surah_start'] = "Surah " . (int)$start_val;
        } else if (!empty($start_val)) {
            $data['surah_start'] = (string)$start_val;
        }

        // Normalize end surah
        $end_val = !empty($data['end_surah_id']) ? $data['end_surah_id'] : (!empty($data['surah_end']) ? $data['surah_end'] : $start_val);
        $end_info = $this->resolveSurahInfo($end_val);
        if ($end_info) {
            $data['end_surah_id'] = (int)$end_info['nomor'];
            $data['surah_end'] = $end_info['namaLatin'];
        } else if (is_numeric($end_val) && (int)$end_val > 0) {
            $data['end_surah_id'] = (int)$end_val;
            $data['surah_end'] = "Surah " . (int)$end_val;
        } else if (!empty($end_val)) {
            $data['surah_end'] = (string)$end_val;
        }

        // Normalize ayahs
        if (!isset($data['start_ayah']) && isset($data['ayat_start'])) {
            $data['start_ayah'] = (int)$data['ayat_start'];
        }
        if (!isset($data['end_ayah']) && isset($data['ayat_end'])) {
            $data['end_ayah'] = (int)$data['ayat_end'];
        }

        // Normalize lines
        if (!isset($data['line_count']) && isset($data['total_baris'])) {
            $data['line_count'] = (int)$data['total_baris'];
        }
    }

    private function validateEntry($data) {
        $student_id = isset($data['student_id']) ? (int)$data['student_id'] : 0;
        $date = isset($data['date']) ? $data['date'] : '';
        $entry_type = isset($data['entry_type']) ? $data['entry_type'] : '';
        $start_surah_id = isset($data['start_surah_id']) ? (int)$data['start_surah_id'] : 0;
        $start_ayah = isset($data['start_ayah']) ? (int)$data['start_ayah'] : 0;
        $end_surah_id = isset($data['end_surah_id']) ? (int)$data['end_surah_id'] : 0;
        $end_ayah = isset($data['end_ayah']) ? (int)$data['end_ayah'] : 0;
        $line_count = isset($data['line_count']) ? (int)$data['line_count'] : 0;
        
        if ($student_id <= 0) throw new Exception("Student ID is required.");
        if (empty($date)) throw new Exception("Date is required.");
        if (!in_array($entry_type, ['HAFALAN_BARU', 'MUROJAAH', 'TASMI', 'UJIAN'])) {
            throw new Exception("Entry Type is invalid. Must be: HAFALAN_BARU, MUROJAAH, TASMI, UJIAN.");
        }
        if ($start_surah_id <= 0 || $start_ayah <= 0 || $end_surah_id <= 0 || $end_ayah <= 0) {
            throw new Exception("Start Surah, Start Ayah, End Surah, and End Ayah are all required and must be greater than 0.");
        }
        if ($line_count < 1) {
            throw new Exception("Line count must be at least 1.");
        }

        // Surah range validation
        if ($start_surah_id > $end_surah_id) {
            throw new Exception("Start Surah cannot be placed after End Surah.");
        }

        if ($start_surah_id === $end_surah_id && $start_ayah > $end_ayah) {
            throw new Exception("Start Ayah cannot be greater than End Ayah in the same Surah.");
        }

        // Validate against Quran master data
        if ($this->quran_data) {
            if (!isset($this->quran_data[$start_surah_id])) {
                throw new Exception("Start Surah ID ($start_surah_id) is invalid.");
            }
            if (!isset($this->quran_data[$end_surah_id])) {
                throw new Exception("End Surah ID ($end_surah_id) is invalid.");
            }

            $max_start_ayah = $this->quran_data[$start_surah_id]['jumlahAyat'];
            $max_end_ayah = $this->quran_data[$end_surah_id]['jumlahAyat'];

            if ($start_ayah > $max_start_ayah) {
                throw new Exception("Start Ayah ($start_ayah) exceeds max verses of Surah {$start_surah_id} ($max_start_ayah).");
            }
            if ($end_ayah > $max_end_ayah) {
                throw new Exception("End Ayah ($end_ayah) exceeds max verses of Surah {$end_surah_id} ($max_end_ayah).");
            }
        }
    }

    public function createEntry($data) {
        $this->normalizeData($data);
        $this->validateEntry($data);

        $student_id = (int)$data['student_id'];
        $date = $data['date'];
        $entry_type = $data['entry_type'];
        $start_surah_id = (int)$data['start_surah_id'];
        $start_ayah = (int)$data['start_ayah'];
        $end_surah_id = (int)$data['end_surah_id'];
        $end_ayah = (int)$data['end_ayah'];
        $line_count = (int)$data['line_count'];
        $score = isset($data['score']) ? $data['score'] : null;
        if ($score !== null && !is_numeric($score) && in_array($score, ['Lancar', 'Kurang', 'Tidak', 'Kurang Lancar', 'Ulang', 'Ziyadah', 'Murajaah'])) {
            if (!isset($data['status']) || empty($data['status'])) {
                $data['status'] = $score;
            }
            $score = null;
        }
        $notes = isset($data['notes']) ? $data['notes'] : '';
        $teacher_id = isset($data['teacher_id']) ? (int)$data['teacher_id'] : null;

        // Fetch Surah Names for backward compatibility columns
        $surah_start_name = isset($data['surah_start']) ? $data['surah_start'] : (isset($this->quran_data[$start_surah_id]) ? $this->quran_data[$start_surah_id]['namaLatin'] : "");
        $surah_end_name = isset($data['surah_end']) ? $data['surah_end'] : (isset($this->quran_data[$end_surah_id]) ? $this->quran_data[$end_surah_id]['namaLatin'] : "");

        // Use provided status or fallback to category-based status
        $status = 'Lancar';
        if (isset($data['status']) && !empty($data['status'])) {
            $status = $data['status'];
        } else {
            if ($entry_type === 'MUROJAAH') {
                $status = 'Murajaah';
            } elseif ($entry_type === 'HAFALAN_BARU') {
                $status = 'Ziyadah';
            }
        }

        // Insert
        $stmt = $this->mysqli->prepare("INSERT INTO memorization_entries 
            (student_id, date, entry_type, start_surah_id, start_ayah, end_surah_id, end_ayah, line_count, score, notes, teacher_id, surah_id, surah_start, surah_end, total_baris, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("issiiiiissiissis",
            $student_id,
            $date,
            $entry_type,
            $start_surah_id,
            $start_ayah,
            $end_surah_id,
            $end_ayah,
            $line_count,
            $score,
            $notes,
            $teacher_id,
            $start_surah_id,
            $surah_start_name,
            $surah_end_name,
            $line_count,
            $status
        );

        if (!$stmt->execute()) {
            throw new Exception("Failed to save entry: " . $stmt->error);
        }
        $id = $stmt->insert_id;
        $stmt->close();

        $this->logActivity($student_id, "Entry created - ID: $id, Type: $entry_type, $surah_start_name:$start_ayah to $surah_end_name:$end_ayah");

        return $id;
    }

    public function updateEntry($id, $data) {
        $id = (int)$id;

        // Fetch existing
        $stmt = $this->mysqli->prepare("SELECT * FROM memorization_entries WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$existing) {
            throw new Exception("Entry record not found.");
        }

        // Merge with existing data for validation
        $merged = array_merge($existing, $data);
        $this->normalizeData($merged);
        $this->validateEntry($merged);

        $date = $merged['date'];
        $entry_type = $merged['entry_type'];
        $start_surah_id = (int)$merged['start_surah_id'];
        $start_ayah = (int)$merged['start_ayah'];
        $end_surah_id = (int)$merged['end_surah_id'];
        $end_ayah = (int)$merged['end_ayah'];
        $line_count = (int)$merged['line_count'];
        $score = isset($merged['score']) ? $merged['score'] : null;
        if ($score !== null && !is_numeric($score) && in_array($score, ['Lancar', 'Kurang', 'Tidak', 'Kurang Lancar', 'Ulang', 'Ziyadah', 'Murajaah'])) {
            if (!isset($data['status']) || empty($data['status'])) {
                $data['status'] = $score;
            }
            $score = null;
        }
        $notes = isset($merged['notes']) ? $merged['notes'] : '';
        $teacher_id = isset($merged['teacher_id']) ? (int)$merged['teacher_id'] : null;

        // Fetch Surah Names for backward compatibility columns
        $surah_start_name = isset($merged['surah_start']) ? $merged['surah_start'] : (isset($this->quran_data[$start_surah_id]) ? $this->quran_data[$start_surah_id]['namaLatin'] : "");
        $surah_end_name = isset($merged['surah_end']) ? $merged['surah_end'] : (isset($this->quran_data[$end_surah_id]) ? $this->quran_data[$end_surah_id]['namaLatin'] : "");

        // Use provided status or fallback to category-based status
        $status = 'Lancar';
        if (isset($data['status']) && !empty($data['status'])) {
            $status = $data['status'];
        } else {
            if ($entry_type === 'MUROJAAH') {
                $status = 'Murajaah';
            } elseif ($entry_type === 'HAFALAN_BARU') {
                $status = 'Ziyadah';
            }
        }

        $stmt = $this->mysqli->prepare("UPDATE memorization_entries SET
            date = ?, entry_type = ?, start_surah_id = ?, start_ayah = ?, end_surah_id = ?, end_ayah = ?, line_count = ?, score = ?, notes = ?, teacher_id = ?,
            surah_id = ?, surah_start = ?, surah_end = ?, total_baris = ?, status = ?
            WHERE id = ?");

        $stmt->bind_param("ssiiiiissiissisi",
            $date,
            $entry_type,
            $start_surah_id,
            $start_ayah,
            $end_surah_id,
            $end_ayah,
            $line_count,
            $score,
            $notes,
            $teacher_id,
            $start_surah_id,
            $surah_start_name,
            $surah_end_name,
            $line_count,
            $status,
            $id
        );

        if (!$stmt->execute()) {
            throw new Exception("Failed to update entry: " . $stmt->error);
        }
        $stmt->close();

        $this->logActivity($existing['student_id'], "Entry updated - ID: $id, Type: $entry_type, $surah_start_name:$start_ayah to $surah_end_name:$end_ayah");

        return true;
    }

    public function deleteEntry($id) {
        $id = (int)$id;

        $stmt = $this->mysqli->prepare("SELECT * FROM memorization_entries WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$existing) {
            throw new Exception("Entry record not found.");
        }

        $stmt = $this->mysqli->prepare("DELETE FROM memorization_entries WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        $this->logActivity($existing['student_id'], "Entry deleted - ID: $id");

        return true;
    }

    public function getEntry($id) {
        $id = (int)$id;
        $stmt = $this->mysqli->prepare("SELECT e.*, s.nama_siswa as student_name, t.full_name as teacher_name 
            FROM memorization_entries e
            JOIN students s ON e.student_id = s.id
            LEFT JOIN employees t ON e.teacher_id = t.id
            WHERE e.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $res;
    }

    public function listEntries($filters = [], $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        $where = "WHERE 1=1";
        $params = [];
        $types = "";

        if (isset($filters['student_id']) && $filters['student_id'] > 0) {
            $where .= " AND e.student_id = ?";
            $params[] = (int)$filters['student_id'];
            $types .= "i";
        }
        if (isset($filters['entry_type']) && !empty($filters['entry_type'])) {
            $where .= " AND e.entry_type = ?";
            $params[] = $filters['entry_type'];
            $types .= "s";
        }
        if (isset($filters['date']) && !empty($filters['date'])) {
            $where .= " AND e.date = ?";
            $params[] = $filters['date'];
            $types .= "s";
        }

        // Count total
        $count_query = "SELECT COUNT(*) FROM memorization_entries e $where";
        $stmt = $this->mysqli->prepare($count_query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $total = $stmt->get_result()->fetch_row()[0];
        $stmt->close();

        // Fetch data
        $query = "SELECT e.*, s.nama_siswa as student_name, t.full_name as teacher_name 
            FROM memorization_entries e
            JOIN students s ON e.student_id = s.id
            LEFT JOIN employees t ON e.teacher_id = t.id
            $where ORDER BY e.date DESC, e.created_at DESC LIMIT ? OFFSET ?";
        
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

    private function logActivity($student_id, $message) {
        $log_file = __DIR__ . '/../../../tmp/tahfidz_activity.log';
        if (!file_exists(dirname($log_file))) {
            mkdir(dirname($log_file), 0777, true);
        }
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[$timestamp] [Student ID: $student_id] [ENTRY] $message" . PHP_EOL;
        file_put_contents($log_file, $log_message, FILE_APPEND);
    }
}
