<?php
// logic/inventory/download_template.php
require_once '../../config/app.php';

check_login();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=template_import_inventaris.csv');

$output = fopen('php://output', 'w');

// Headers
fputcsv($output, [
    'No', 
    'Kode Barang (Kosongkan jika ingin auto-generate)', 
    'Nama Barang', 
    'Lokasi (Wajib Ada di Sistem)', 
    'Qty', 
    'Satuan', 
    'Kondisi (Baik/Rusak Ringan/Rusak Berat)', 
    'Deskripsi'
]);

// Sample Rows
fputcsv($output, ['1', '', 'Meja Kantor 120cm', 'Kantor Bidik', '5', 'Unit', 'Baik', 'Meja kayu jati minimalis']);
fputcsv($output, ['2', '', 'Kursi Lipat Chitose', 'Ma\'had Aly', '10', 'Pcs', 'Rusak Ringan', 'Warna hitam, butuh baut tambahan']);

fclose($output);
exit;
?>
