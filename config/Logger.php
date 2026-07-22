<?php
// config/Logger.php

class Logger {
    private static $log_dir = __DIR__ . '/../storage/logs';

    private static function initDirs() {
        $dirs = ['activity', 'auth', 'error', 'security', 'api', 'scheduler', 'backup', 'system'];
        foreach ($dirs as $dir) {
            $path = self::$log_dir . '/' . $dir;
            if (!file_exists($path)) {
                mkdir($path, 0755, true);
            }
        }
    }

    private static function getClientIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    private static function getBrowser() {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    }

    private static function getRequestURL() {
        return $_SERVER['REQUEST_URI'] ?? 'Unknown';
    }

    private static function sanitizeData($data) {
        if (!is_array($data)) return $data;
        $sensitive_keys = ['password', 'token', 'otp', 'pass', 'secret', 'key', 'auth_token', 'fcm_token'];
        $clean = [];
        foreach ($data as $k => $v) {
            if (in_array(strtolower($k), $sensitive_keys)) {
                $clean[$k] = '********';
            } elseif (is_array($v)) {
                $clean[$k] = self::sanitizeData($v);
            } else {
                $clean[$k] = $v;
            }
        }
        return $clean;
    }

    // Rotasi & Pembersihan Log Lama (Retention 90 Hari)
    public static function rotateLogs() {
        self::initDirs();
        $now = time();
        $retention_seconds = 90 * 24 * 60 * 60; // 90 days

        $dirs = ['activity', 'auth', 'error', 'security', 'api', 'scheduler', 'backup', 'system'];
        foreach ($dirs as $dir) {
            $path = self::$log_dir . '/' . $dir;
            if (!is_dir($path)) continue;
            
            $files = scandir($path);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                $file_path = $path . '/' . $file;
                
                // If it is archive directory
                if ($file === 'archive' && is_dir($file_path)) {
                    $archived_files = scandir($file_path);
                    foreach ($archived_files as $af) {
                        if ($af === '.' || $af === '..') continue;
                        $af_path = $file_path . '/' . $af;
                        if (is_file($af_path) && ($now - filemtime($af_path) > $retention_seconds)) {
                            unlink($af_path);
                        }
                    }
                    continue;
                }

                if (!is_file($file_path)) continue;

                // Check file age
                if ($now - filemtime($file_path) > $retention_seconds) {
                    unlink($file_path);
                }
            }
        }
    }

    private static function writeToFile($type, array $data) {
        self::initDirs();
        
        $date = date('Y-m-d');
        $file_path = self::$log_dir . '/' . $type . '/' . $type . '-' . $date . '.log';

        // Check if file size > 10MB to rotate
        if (file_exists($file_path) && filesize($file_path) >= 10 * 1024 * 1024) {
            $archive_dir = self::$log_dir . '/' . $type . '/archive';
            if (!file_exists($archive_dir)) {
                mkdir($archive_dir, 0755, true);
            }
            $archive_file = $archive_dir . '/' . $type . '-' . $date . '-' . time() . '.log.gz';
            
            // Write gzipped archive file if zlib extension is available
            if (extension_loaded('zlib')) {
                $gz = gzopen($archive_file, 'w9');
                gzwrite($gz, file_get_contents($file_path));
                gzclose($gz);
            } else {
                // fallback to plain file if gzip not supported
                copy($file_path, str_replace('.log.gz', '.log', $archive_file));
            }
            
            // Truncate current file
            file_put_contents($file_path, '');
        }

        $log_line = json_encode($data) . "\n";
        file_put_contents($file_path, $log_line, FILE_APPEND | LOCK_EX);

        // Run rotation occasionally (1% chance)
        if (rand(1, 100) === 1) {
            self::rotateLogs();
        }
    }

    // Core log function
    public static function log($level, $category, $module, $action, $description, $context = []) {
        $user_id = $_SESSION['user_id'] ?? null;
        $user_name = $_SESSION['user_name'] ?? 'Guest';
        $role = $_SESSION['position_name'] ?? 'Guest';

        $ip = self::getClientIP();
        $browser = self::getBrowser();
        $url = self::getRequestURL();
        $datetime = date('Y-m-d H:i:s');

        $old_data = isset($context['old_data']) ? self::sanitizeData($context['old_data']) : null;
        $new_data = isset($context['new_data']) ? self::sanitizeData($context['new_data']) : null;

        $log_data = [
            'datetime' => $datetime,
            'level' => $level,
            'module' => $module,
            'action' => $action,
            'user_id' => $user_id,
            'user' => $user_name,
            'role' => $role,
            'table' => $context['table'] ?? null,
            'record_id' => $context['record_id'] ?? null,
            'description' => $description,
            'old_data' => $old_data,
            'new_data' => $new_data,
            'ip' => $ip,
            'browser' => $browser,
            'url' => $url
        ];

        // 1. Write to File (Always, fallback path)
        try {
            self::writeToFile($category, $log_data);
        } catch (Exception $e) {
            error_log("Failed to write log file: " . $e->getMessage());
        }

        // 2. Write to Database (Dual Logging)
        if (class_exists('Database')) {
            try {
                $db = new Database();
                $conn = $db->getConnection();
                if ($conn) {
                    $stmt = $conn->prepare("
                        INSERT INTO activity_logs (
                            user_id, user_name, role, module, action, table_name, record_id, 
                            description, old_data, new_data, ip_address, browser, url, level, created_at
                        ) VALUES (
                            :user_id, :user_name, :role, :module, :action, :table_name, :record_id, 
                            :description, :old_data, :new_data, :ip_address, :browser, :url, :level, :created_at
                        )
                    ");

                    $stmt->execute([
                        ':user_id' => $user_id,
                        ':user_name' => $user_name,
                        ':role' => $role,
                        ':module' => $module,
                        ':action' => $action,
                        ':table_name' => $context['table'] ?? null,
                        ':record_id' => $context['record_id'] ?? null,
                        ':description' => $description,
                        ':old_data' => $old_data ? json_encode($old_data) : null,
                        ':new_data' => $new_data ? json_encode($new_data) : null,
                        ':ip_address' => $ip,
                        ':browser' => $browser,
                        ':url' => $url,
                        ':level' => $level,
                        ':created_at' => $datetime
                    ]);
                }
            } catch (Exception $e) {
                error_log("Database Logging Failed: " . $e->getMessage());
            }
        }
    }

    // Logger Helpers
    public static function activity($module, $action, $description, $context = []) {
        self::log('INFO', 'activity', $module, $action, $description, $context);
    }

    public static function info($module, $action, $description, $context = []) {
        self::log('INFO', 'system', $module, $action, $description, $context);
    }

    public static function warning($module, $action, $description, $context = []) {
        self::log('WARNING', 'system', $module, $action, $description, $context);
    }

    public static function error($module, $action, $description, $context = []) {
        self::log('ERROR', 'error', $module, $action, $description, $context);
    }

    public static function critical($module, $action, $description, $context = []) {
        self::log('CRITICAL', 'error', $module, $action, $description, $context);
    }

    public static function security($module, $action, $description, $context = []) {
        self::log('SECURITY', 'security', $module, $action, $description, $context);
    }

    public static function auth($action, $description, $context = []) {
        $level = ($action === 'LOGIN_FAILED') ? 'WARNING' : 'INFO';
        self::log($level, 'auth', 'Auth', $action, $description, $context);
    }

    public static function api($module, $action, $description, $context = []) {
        self::log('INFO', 'api', $module, $action, $description, $context);
    }

    public static function scheduler($action, $description, $context = []) {
        self::log('INFO', 'scheduler', 'Scheduler', $action, $description, $context);
    }

    public static function backup($action, $description, $context = []) {
        self::log('INFO', 'backup', 'Backup', $action, $description, $context);
    }
}
?>
