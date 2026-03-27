-- d:\xampp\htdocs\dashboard-yac\database\migrations\2026_03_26_create_student_violations.sql

CREATE TABLE kategori_pelanggaran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_kategori VARCHAR(100) NOT NULL, -- ringan, sedang, berat
    poin INT DEFAULT 0
);

CREATE TABLE pelanggaran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    santri_id INT NOT NULL,
    kategori_id INT NOT NULL,
    deskripsi TEXT NOT NULL,
    tanggal_pelanggaran DATE NOT NULL,
    lokasi VARCHAR(255),
    pelapor INT NOT NULL, -- user_id (employee_id)
    status ENUM('draft', 'dilaporkan', 'diproses', 'selesai') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (santri_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (kategori_id) REFERENCES kategori_pelanggaran(id) ON DELETE CASCADE,
    FOREIGN KEY (pelapor) REFERENCES employees(id) ON DELETE CASCADE
);

CREATE TABLE tindak_lanjut (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pelanggaran_id INT NOT NULL,
    tindakan TEXT NOT NULL,
    catatan TEXT,
    tanggal_tindakan DATE NOT NULL,
    penindak INT NOT NULL, -- user_id (employee_id)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (pelanggaran_id) REFERENCES pelanggaran(id) ON DELETE CASCADE,
    FOREIGN KEY (penindak) REFERENCES employees(id) ON DELETE CASCADE
);

-- Seed Initial Categories
INSERT INTO kategori_pelanggaran (nama_kategori, poin) VALUES 
('Ringan', 5),
('Sedang', 15),
('Berat', 50);
