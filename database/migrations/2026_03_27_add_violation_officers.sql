-- d:\xampp\htdocs\dashboard-yac\database\migrations\2026_03_27_add_violation_officers.sql

CREATE TABLE petugas_pelanggaran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE(employee_id)
);
