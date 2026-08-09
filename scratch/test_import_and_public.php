<?php
// scratch/test_import_and_public.php
$_GET['month'] = 8;
$_GET['year'] = 2026;

ob_start();
require __DIR__ . '/../api/agenda/public.php';
$output = ob_get_clean();

$data = json_decode($output, true);
echo "API Response Success: " . ($data['success'] ? 'YES' : 'NO') . "\n";
echo "Total Agendas (including API holidays): " . $data['total'] . "\n";

$apiHolidays = array_filter($data['data'], function($item) {
    return isset($item['is_api']) && $item['is_api'] === true;
});

echo "Public Holiday API Items Count in Aug 2026: " . count($apiHolidays) . "\n";
foreach ($apiHolidays as $h) {
    echo " - [{$h['start_date']}] {$h['title']} ({$h['category']})\n";
}

