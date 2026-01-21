# Dashboard YAC

High-performance Human Resource & School Management System for Yayasan Al-Azhar Center. This project consists of a Native PHP Web Admin Panel and a companion Mobile API backend.

## 🚀 Features

### 📱 Mobile API (Employee App)
*   **Geo-fenced Attendance:** Secure check-in/out restricted by office location radius (Haversine formula).
*   **Smart Attendance Logic:** Automatically handles Late arrivals, Early departures, and "Forgot to Clock Out" scenarios.
*   **Permit Management:**
    *   Digital leave/permit submission with attachment support.
    *   **Automated Hierarchy Routing:** Requests are automatically sent to the correct manager (Unit Head -> Division Head -> Mudir) based on the organization tree.
*   **Push Notifications:** Integrated with Firebase Cloud Messaging (FCM HTTP v1) for real-time approval alerts.

### 💻 Web Admin Dashboard
*   **Interactive Dashboard:** Real-time analytics on attendance rates, active employees, and daily activity feeds.
*   **Organization Chart:** Visual, dynamic organization tree powered by Google Charts. Supports filtering by division.
*   **Employee & Student Management:** Complete CRUD systems for managing staff and student bodies.
*   **Reports:** (In Development) Exportable attendance and performance reports.

## 🛠️ Tech Stack
*   **Backend:** PHP 8.x (Native/Vanilla), PDO Database Driver.
*   **Frontend:** HTML5, Tailwind CSS, JavaScript (Google Charts API).
*   **Database:** MySQL / MariaDB.
*   **Authentication:** Custom JSON-based Auth (Encrypted Passwords).
*   **Third-Party Services:** Google Firebase (FCM), Google Charts.

## 📂 Project Structure
```
/dashboard-yac
├── /api                 # JSON Endpoints for Mobile App
│   ├── attendance.php   # Check-in/out Logic
│   ├── submit_permit.php# Leave Requests
│   └── ...
├── /config              # Database & App Configuration
├── /logic               # Backend Business Logic for Web Forms
├── /views               # Admin Panel UI (Tailwind CSS)
│   ├── /dashboard       # Main Stats View
│   ├── /organization    # Org Chart Visualization
│   ├── /students        # Student Management
│   └── ...
└── /uploads             # Stored Attachments (Permits, Profiles)
```

## ⚙️ Installation & Setup

1.  **Clone the Repository**
    Clone this folder into your web server's root (e.g., `C:\xampp\htdocs\dashboard-yac`).

2.  **Database Setup**
    *   Create a new MySQL database named `dashboard_yac` (or similar).
    *   Import `schema.sql` to create the table structures.
    *   Import `organization_migration.sql` to populate initial organization data (if available).

3.  **Configuration**
    *   Open `config/database.php` and update your DB credentials:
        ```php
        $this->host = "localhost";
        $this->db_name = "dashboard_yac";
        $this->username = "root";
        $this->password = "";
        ```

4.  **Firebase Setup (For Notifications)**
    *   Place your Firebase Service Account JSON file in `config/service-account.json`.
    *   Ensure the PHP server has `write` permissions to the `config` directory if tokens are cached.

## 🧪 Testing the API
You can test the API endpoints using Postman or Insomnia.

*   **Login:** `POST /api/login.php`
    *   Body: `{"email": "user@example.com", "password": "password"}`
*   **Attendance:** `POST /api/attendance.php`
    *   Body: `{"user_id": 1, "type": "IN", "latitude": -6.2, "longitude": 106.8}`
