<?php
$host = "localhost";
$username = "root";
$password = "";

try {
    $conn = new PDO("mysql:host=$host", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create Database
    $conn->exec("CREATE DATABASE IF NOT EXISTS assunnah_payroll");
    $conn->exec("USE assunnah_payroll");

    // Create Table
    $sql = "CREATE TABLE IF NOT EXISTS payrolls (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_payroll VARCHAR(20) UNIQUE NOT NULL,
        user_id INT NOT NULL,
        nik_snapshot VARCHAR(20),
        name_snapshot VARCHAR(100),
        position_snapshot VARCHAR(100),
        periode_bulan CHAR(2) NOT NULL,
        periode_tahun CHAR(4) NOT NULL,
        gaji_pokok DECIMAL(15,2) DEFAULT 0,
        tunjangan_jabatan DECIMAL(15,2) DEFAULT 0,
        bonus_performa DECIMAL(15,2) DEFAULT 0,
        lembur DECIMAL(15,2) DEFAULT 0,
        gaji_bruto DECIMAL(15,2) DEFAULT 0,
        pajak_pph21 DECIMAL(15,2) DEFAULT 0,
        bpjs_kesehatan DECIMAL(15,2) DEFAULT 0,
        bpjs_ketenagakerjaan DECIMAL(15,2) DEFAULT 0,
        potongan_kehadiran DECIMAL(15,2) DEFAULT 0,
        jumlah_potongan DECIMAL(15,2) DEFAULT 0,
        gaji_netto DECIMAL(15,2) DEFAULT 0,
        status VARCHAR(20) DEFAULT 'TERBAYAR',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->exec($sql);
    echo "Database and table created successfully";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
