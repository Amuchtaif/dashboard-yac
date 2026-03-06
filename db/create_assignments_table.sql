-- Database migration for Assignments (Penugasan)
-- Matches the UI mockup for Task List, Detail, and Create screens.

CREATE TABLE IF NOT EXISTS assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    special_instructions TEXT,
    priority ENUM('Tinggi', 'Rutin', 'Biasa') DEFAULT 'Rutin',
    status ENUM('Belum Dimulai', 'Sedang Dikerjakan', 'Selesai', 'Dibatalkan') DEFAULT 'Belum Dimulai',
    due_date DATE,
    created_by INT NOT NULL,
    assigned_to INT NOT NULL,
    attachment_path VARCHAR(255),
    report_notes TEXT,
    report_attachment VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES employees(id),
    FOREIGN KEY (assigned_to) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed initial data for testing
INSERT INTO assignments (title, description, priority, status, due_date, created_by, assigned_to, special_instructions) VALUES
('Finalisasi Laporan Kuartal Keuangan', 'Review detail pengeluaran departemen operasional serta verifikasi data akhir.', 'Tinggi', 'Belum Dimulai', '2023-10-25', 1, 1, 'Pastikan melakukan backup data sebelum menjalankan skrip migrasi atau optimasi index pada production.'),
('Audit Inventaris Gudang Utama', 'Pengecekan berkala stok barang masuk dan keluar untuk akurasi data stock opname.', 'Rutin', 'Belum Dimulai', '2023-10-28', 1, 1, 'Bawa catatan fisik untuk rekonsiliasi.'),
('Update Database Penjualan', 'Melakukan optimasi pada database penjualan untuk meningkatkan kecepatan query.', 'Tinggi', 'Sedang Dikerjakan', '2023-10-30', 1, 1, 'Lakukan di jam low traffic.');
