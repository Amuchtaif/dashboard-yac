<?php
$gaji_bulan = "- THR Ramadhan 1447 ";
$is_thr = false;
$bulan = '00';
$tahun = '0000';
if (preg_match('/THR\s+(?:.*?\s+)?(\d{4})/i', $gaji_bulan, $matches)) {
    $is_thr = true;
    $bulan = 'THR';
    $tahun = $matches[1];
}
echo "IS_THR: " . ($is_thr ? 'YES' : 'NO') . "\n";
echo "BULAN: " . $bulan . "\n";
echo "TAHUN: " . $tahun . "\n";
