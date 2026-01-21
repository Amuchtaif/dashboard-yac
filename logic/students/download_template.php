<?php
// Set headers to force download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=template_import_siswa.csv');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Output the column headings
fputcsv($output, array('Nama Siswa', 'NISN', 'Kelas'));

// Output sample data
fputcsv($output, array('Ahmad Santoso', '1234567890', '10-A'));
fputcsv($output, array('Budi Hartono', '0987654321', '11-IPA-1'));
fputcsv($output, array('Siti Aminah', '1122334455', 'TK-A'));

fclose($output);
exit;
