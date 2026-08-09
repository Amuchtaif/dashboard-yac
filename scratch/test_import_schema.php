<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/Logger.php';

try {
    if (class_exists('Logger')) {
        Logger::log('info', 'activity', 'Kalender Akademik', 'Import Agenda', "Mengimport 5 agenda ke Kalender Akademik", [
            'user_id' => 1,
            'count' => 5
        ]);
        echo "LOG SUCCESS";
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage();
}
