<?php
// portal_wali/init.php
// Bootstrapper khusus Portal Wali Murid Yayasan Assunnah Cirebon

if (session_status() === PHP_SESSION_NONE) {
    // Dedicated session path
    $sessionDir = __DIR__ . '/../storage/sessions';
    if (!is_dir($sessionDir)) {
        @mkdir($sessionDir, 0777, true);
    }
    if (is_dir($sessionDir) && is_writable($sessionDir)) {
        @session_save_path($sessionDir);
    }

    $isHttps = (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' && !empty($_SERVER['HTTPS']))
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (isset($_SERVER['HTTP_HOST']) && stripos($_SERVER['HTTP_HOST'], 'assunnahcirebon.com') !== false);

    if (!headers_sent()) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_samesite', 'Lax');
        if ($isHttps) {
            ini_set('session.cookie_secure', '1');
        }
    }
    session_start();
}

date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/csrf.php';

// Base URL helper untuk portal wali
if (!function_exists('portal_url')) {
    function portal_url($path = '') {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Cek apakah diakses lewat subdomain wali.assunnahcirebon.com atau subfolder
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        if (strpos($scriptName, '/portal_wali/') !== false) {
            $baseDir = substr($scriptName, 0, strpos($scriptName, '/portal_wali/') + strlen('/portal_wali'));
        } else {
            $baseDir = '';
        }
        
        return rtrim($protocol . "://" . $host . $baseDir, '/') . '/' . ltrim($path, '/');
    }
}

// Cek apakah wali murid sudah login
if (!function_exists('check_guardian_auth')) {
    function check_guardian_auth() {
        if (empty($_SESSION['guardian_id'])) {
            header("Location: " . portal_url('login.php'));
            exit;
        }
    }
}

// Ambil daftar anak yang terhubung dengan akun wali
if (!function_exists('get_guardian_children')) {
    function get_guardian_children($guardian_id) {
        $db = new Database();
        $conn = $db->getConnection();
        
        $sql = "
            SELECT 
                s.id as student_id,
                s.nama_siswa,
                s.nomor_induk,
                s.tingkat,
                s.kelas,
                s.foto,
                s.status as status_siswa,
                gs.relationship,
                gs.is_primary,
                gl.name as grade_name,
                eu.name as unit_name
            FROM guardian_students gs
            JOIN students s ON gs.student_id = s.id
            LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.status = 'ACTIVE'
            LEFT JOIN grade_levels gl ON sch.class_id = gl.id
            LEFT JOIN education_units eu ON gl.education_unit_id = eu.id
            WHERE gs.guardian_id = :guardian_id AND gs.is_verified = 1
            GROUP BY s.id, gs.relationship, gs.is_primary, gl.name, eu.name
            ORDER BY s.id ASC
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([':guardian_id' => $guardian_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Dapatkan data santri yang sedang aktif dipilih di sesi
if (!function_exists('get_active_child')) {
    function get_active_child() {
        if (empty($_SESSION['guardian_id'])) {
            return null;
        }
        
        $children = get_guardian_children($_SESSION['guardian_id']);
        if (empty($children)) {
            return null;
        }
        
        // Jika belum ada pilihan atau anak yang dipilih tidak ada di daftar, default ke anak pertama
        $active_id = $_SESSION['active_student_id'] ?? null;
        if ($active_id) {
            foreach ($children as $c) {
                if ((int)$c['student_id'] === (int)$active_id) {
                    return $c;
                }
            }
        }
        
        // Fallback ke anak pertama
        $_SESSION['active_student_id'] = (int)$children[0]['student_id'];
        return $children[0];
    }
}

// Helper untuk mendeteksi Juz dari Nama/Nomor Surah dan Ayat Al-Qur'an
if (!function_exists('get_quran_juz')) {
    function get_quran_juz($surah_input, $ayah = 1) {
        if (empty($surah_input)) {
            return null;
        }

        static $surah_map = [
            'al-fatihah' => 1, 'fatihah' => 1,
            'al-baqarah' => 2, 'baqarah' => 2,
            'ali \'imran' => 3, 'ali imran' => 3, 'al-imran' => 3,
            'an-nisa\'' => 4, 'an-nisa' => 4, 'nisa' => 4,
            'al-ma\'idah' => 5, 'al-maidah' => 5, 'maidah' => 5,
            'al-an\'am' => 6, 'al-anam' => 6, 'anam' => 6,
            'al-a\'raf' => 7, 'al-araf' => 7, 'araf' => 7,
            'al-anfal' => 8, 'anfal' => 8,
            'at-tawbah' => 9, 'at-taubah' => 9, 'tawbah' => 9, 'taubah' => 9,
            'yunus' => 10,
            'hud' => 11,
            'yusuf' => 12,
            'ar-ra\'d' => 13, 'ar-rad' => 13, 'rad' => 13,
            'ibrahim' => 14,
            'al-hijr' => 15, 'hijr' => 15,
            'an-nahl' => 16, 'nahl' => 16,
            'al-isra\'' => 17, 'al-isra' => 17, 'isra' => 17,
            'al-kahf' => 18, 'kahf' => 18,
            'maryam' => 19,
            'taha' => 20,
            'al-anbiya\'' => 21, 'al-anbiya' => 21, 'anbiya' => 21,
            'al-hajj' => 22, 'hajj' => 22,
            'al-mu\'minun' => 23, 'al-muminun' => 23, 'muminun' => 23,
            'an-nur' => 24, 'nur' => 24,
            'al-furqan' => 25, 'furqan' => 25,
            'ash-shu\'ara\'' => 26, 'ash-shuara' => 26, 'shuara' => 26,
            'an-naml' => 27, 'naml' => 27,
            'al-qasas' => 28, 'qasas' => 28,
            'al-\'ankabut' => 29, 'al-ankabut' => 29, 'ankabut' => 29,
            'ar-rum' => 30, 'rum' => 30,
            'luqman' => 31,
            'as-sajdah' => 32, 'sajdah' => 32,
            'al-ahzab' => 33, 'ahzab' => 33,
            'saba\'' => 34, 'saba' => 34,
            'fatir' => 35,
            'ya-sin' => 36, 'yasin' => 36,
            'as-saffat' => 37, 'saffat' => 37,
            'sad' => 38,
            'az-zumar' => 39, 'zumar' => 39,
            'ghafir' => 40, 'al-mu\'min' => 40,
            'fussilat' => 41,
            'ash-shura' => 42, 'shura' => 42,
            'az-zukhruf' => 43, 'zukhruf' => 43,
            'ad-dukhan' => 44, 'dukhan' => 44,
            'al-jathiyah' => 45, 'al-jatsiyah' => 45, 'jatsiyah' => 45, 'jathiyah' => 45,
            'al-ahqaf' => 46, 'ahqaf' => 46,
            'muhammad' => 47,
            'al-fath' => 48, 'fath' => 48,
            'al-hujurat' => 49, 'hujurat' => 49,
            'qaf' => 50,
            'adh-dhariyat' => 51, 'adz-dzariyat' => 51, 'dhariyat' => 51, 'dzariyat' => 51,
            'at-tur' => 52, 'tur' => 52,
            'an-najm' => 53, 'najm' => 53,
            'al-qamar' => 54, 'qamar' => 54,
            'ar-rahman' => 55, 'rahman' => 55,
            'al-waqi\'ah' => 56, 'al-waqiah' => 56, 'waqiah' => 56,
            'al-hadid' => 57, 'hadid' => 57,
            'al-mujadila' => 58, 'al-mujadilah' => 58, 'mujadilah' => 58,
            'al-hashr' => 59, 'al-hasyr' => 59, 'hashr' => 59, 'hasyr' => 59,
            'al-mumtahanah' => 60, 'mumtahanah' => 60,
            'as-saff' => 61, 'saff' => 61,
            'al-jumu\'ah' => 62, 'al-jumuah' => 62, 'jumuah' => 62,
            'al-munafiqun' => 63, 'munafiqun' => 63,
            'at-taghabun' => 64, 'taghabun' => 64,
            'at-talaq' => 65, 'talaq' => 65,
            'at-tahrim' => 66, 'tahrim' => 66,
            'al-mulk' => 67, 'mulk' => 67,
            'al-qalam' => 68, 'qalam' => 68,
            'al-haqqah' => 69, 'haqqah' => 69,
            'al-ma\'arij' => 70, 'al-maarij' => 70, 'maarij' => 70,
            'nuh' => 71,
            'al-jinn' => 72, 'jinn' => 72,
            'al-muzzammil' => 73, 'muzzammil' => 73,
            'al-muddaththir' => 74, 'al-muddatsir' => 74, 'muddatsir' => 74,
            'al-qiyamah' => 75, 'qiyamah' => 75,
            'al-insan' => 76, 'insan' => 76,
            'al-mursalat' => 77, 'mursalat' => 77,
            'an-naba\'' => 78, 'an-naba' => 78, 'naba' => 78,
            'an-nazi\'at' => 79, 'an-naziat' => 79, 'naziat' => 79,
            '\'abasa' => 80, 'abasa' => 80,
            'at-takwir' => 81, 'takwir' => 81,
            'al-infitar' => 82, 'infitar' => 82,
            'al-mutaffifin' => 83, 'mutaffifin' => 83,
            'al-inshiqaq' => 84, 'al-insyiqaq' => 84, 'inshiqaq' => 84,
            'al-buruj' => 85, 'buruj' => 85,
            'at-tariq' => 86, 'tariq' => 86,
            'al-a\'la' => 87, 'al-ala' => 87, 'ala' => 87,
            'al-ghashiyah' => 88, 'al-ghasyiyah' => 88, 'ghasyiyah' => 88,
            'al-fajr' => 89, 'fajr' => 89,
            'al-balad' => 90, 'balad' => 90,
            'ash-shams' => 91, 'asy-syams' => 91, 'shams' => 91, 'syams' => 91,
            'al-layl' => 92, 'layl' => 92,
            'ad-duha' => 93, 'duha' => 93,
            'ash-sharh' => 94, 'al-insyirah' => 94, 'insyirah' => 94, 'sharh' => 94,
            'at-tin' => 95, 'tin' => 95,
            'al-\'alaq' => 96, 'al-alaq' => 96, 'alaq' => 96,
            'al-qadr' => 97, 'qadr' => 97,
            'al-bayyinah' => 98, 'bayyinah' => 98,
            'az-zalzalah' => 99, 'zalzalah' => 99,
            'al-\'adiyat' => 100, 'al-adiyat' => 100, 'adiyat' => 100,
            'al-qari\'ah' => 101, 'al-qariah' => 101, 'qariah' => 101,
            'at-takathur' => 102, 'at-takatsur' => 102, 'takatsur' => 102,
            'al-\'asr' => 103, 'al-asr' => 103, 'asr' => 103,
            'al-humazah' => 104, 'humazah' => 104,
            'al-fil' => 105, 'fil' => 105,
            'quraysh' => 106, 'quraisy' => 106,
            'al-ma\'un' => 107, 'al-maun' => 107, 'maun' => 107,
            'al-kawthar' => 108, 'al-kautsar' => 108, 'kautsar' => 108,
            'al-kafirun' => 109, 'kafirun' => 109,
            'an-nasr' => 110, 'nasr' => 110,
            'al-masad' => 111, 'al-lahab' => 111, 'masad' => 111, 'lahab' => 111,
            'al-ikhlas' => 112, 'ikhlas' => 112,
            'al-falaq' => 113, 'falaq' => 113,
            'an-nas' => 114, 'nas' => 114
        ];

        $s_num = null;
        if (is_numeric($surah_input)) {
            $s_num = (int)$surah_input;
        } else {
            $clean = strtolower(trim((string)$surah_input));
            $clean = str_replace(['‘', '’', '`'], "'", $clean);
            if (isset($surah_map[$clean])) {
                $s_num = $surah_map[$clean];
            } else {
                $simplified = preg_replace('/[^a-z0-9]/', '', $clean);
                foreach ($surah_map as $k => $v) {
                    if (preg_replace('/[^a-z0-9]/', '', $k) === $simplified) {
                        $s_num = $v;
                        break;
                    }
                }
            }
        }

        if (!$s_num || $s_num < 1 || $s_num > 114) {
            return null;
        }

        $ayah = (int)$ayah;
        if ($ayah < 1) $ayah = 1;

        // Titik awal setiap Juz (Surah, Ayat)
        static $juz_starts = [
            1 => [1, 1],
            2 => [2, 142],
            3 => [2, 253],
            4 => [3, 93],
            5 => [4, 24],
            6 => [4, 148],
            7 => [5, 82],
            8 => [6, 111],
            9 => [7, 88],
            10 => [8, 41],
            11 => [9, 93],
            12 => [11, 6],
            13 => [12, 53],
            14 => [15, 1],
            15 => [17, 1],
            16 => [18, 75],
            17 => [21, 1],
            18 => [23, 1],
            19 => [25, 21],
            20 => [27, 56],
            21 => [29, 46],
            22 => [33, 31],
            23 => [36, 28],
            24 => [39, 32],
            25 => [41, 47],
            26 => [46, 1],
            27 => [51, 31],
            28 => [58, 1],
            29 => [67, 1],
            30 => [78, 1],
        ];

        for ($j = 30; $j >= 1; $j--) {
            list($start_s, $start_a) = $juz_starts[$j];
            if ($s_num > $start_s || ($s_num === $start_s && $ayah >= $start_a)) {
                return $j;
            }
        }

        return 1;
    }
}

