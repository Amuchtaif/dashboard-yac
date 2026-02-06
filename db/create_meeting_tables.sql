CREATE TABLE IF NOT EXISTS meetings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    meeting_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    type ENUM('online', 'offline') NOT NULL,
    location VARCHAR(255),
    created_by INT NOT NULL,
    division_id INT NOT NULL,
    qr_token VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES employees(id),
    FOREIGN KEY (division_id) REFERENCES divisions(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS meeting_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    employee_id INT NOT NULL,
    status ENUM('invited', 'present', 'absent') DEFAULT 'invited',
    attendance_time DATETIME NULL,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;
