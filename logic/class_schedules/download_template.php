<?php
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=template_jadwal.csv');

$output = fopen('php://output', 'w');

// Header
fputcsv($output, [
    'Hari',
    'Unit',
    'Kelas',
    'Mapel',
    'Guru',
    'Jam Ke Mulai',
    'Jam Ke Selesai',
    'Tahun Akademik'
]);

// Contoh Data
fputcsv($output, [
    'Monday',
    'SDIT',
    'Kelas 1A',
    'Matematika',
    'Ahmad Fauzi',
    '1',
    '2',
    '2023/2024'
]);

fputcsv($output, [
    'Tuesday',
    'MTs',
    '7A',
    'Bahasa Inggris',
    'Siti Aminah',
    '3',
    '3',
    '2023/2024'
]);

fclose($output);
exit;
