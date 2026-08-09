<?php
// api/calendar/template.php
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="template_import_kalender_akademik.csv"');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fputs($output, $chr = "\xEF\xBB\xBF");

// Headers
fputcsv($output, [
    'Nama Kegiatan',
    'Tanggal Mulai',
    'Tanggal Selesai',
    'Jam Mulai',
    'Jam Selesai',
    'Kategori',
    'Sumber Agenda',
    'Unit',
    'Lokasi',
    'Deskripsi'
]);

// Sample Rows
fputcsv($output, [
    'Rapat Kurikulum Semester Ganjil',
    '2026-08-15',
    '2026-08-15',
    '08:00',
    '11:30',
    'Rapat',
    'bidang_pendidikan',
    '',
    'Ruang Rapat Bidang',
    'Rapat koordinasi penyusunan kurikulum'
]);

fputcsv($output, [
    'Class Meeting MTs Assunnah',
    '2026-08-20',
    '2026-08-22',
    '07:30',
    '14:00',
    'Kegiatan Unit',
    'unit',
    'MTs Assunnah',
    'Lapangan Sekolah',
    'Kegiatan lomba antarkelas'
]);

fclose($output);
exit;
