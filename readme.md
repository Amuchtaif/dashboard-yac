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

## 🧪 Testing the API
You can test the API endpoints using Postman or Insomnia.

*   **Login:** `POST /api/login.php`
    *   Body: `{"email": "user@example.com", "password": "password"}`
*   **Attendance:** `POST /api/attendance.php`
    *   Body: `{"user_id": 1, "type": "IN", "latitude": -6.2, "longitude": 106.8}`
