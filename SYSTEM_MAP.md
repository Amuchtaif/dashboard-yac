# Project Summary
- **Tujuan**: Dashboard YAC adalah Sistem Informasi Manajemen (SIM) terintegrasi untuk pengelolaan yayasan pendidikan/pesantren. Meliputi manajemen pegawai, akademik (jadwal, rpp, nilai), kesantrian (tahfidz, boarding, perpulangan), inventaris, hingga presensi kehadiran.
- **Tech Stack Utama**: 
  - **Bahasa**: PHP Native (Vanilla/Prosedural).
  - **Database**: MySQL (PDO untuk dashboard utama, MySQLi di beberapa modul).
  - **Frontend**: HTML5, Vanilla CSS, Tailwind CSS (utility classes), AJAX/JavaScript native.
  - **Mobile Support**: RESTful API prodesural untuk integrasi dengan aplikasi mobile (Flutter/Android).
  - **Integrasi**: Firebase Cloud Messaging (FCM) untuk notifikasi push.
- **Pola Arsitektur**: 
  - **Modular Monolith (Simplified MVC)**:
    - `views/`: UI & Logika tampilan (GET data langsung dari DB).
    - `logic/`: Handler proses (POST/PUT/DELETE) yang memproses data dan melakukan redirect.
    - `api/`: Endpoint JSON untuk kebutuhan eksternal/mobile.

# Core Logic Flow (Function-Level Flowchart)
### 1. Autentikasi (Web)
Route (views/auth/login) -> Handler (logic/auth/login) -> check_password -> Set Session -> Redirect Dashboard.

### 2. Operasi CRUD (Contoh: Pegawai)
- **Read**: `views/employees/index.php` -> PDO Query (Filter, Search, Pagination) -> HTML Table.
- **Create**: `views/employees/form.php` -> `logic/employees/store.php` (Validasi, Upload Foto) -> PDO INSERT -> Redirect Index.
- **Update**: `views/employees/form.php?id=x` -> `logic/employees/update.php` -> PDO UPDATE -> Redirect Index.
- **Delete**: Button -> `logic/employees/delete.php` -> PDO DELETE -> Redirect Index.

### 3. API Login (Mobile)
`api/login.php` -> POST JSON -> Query User -> Validation -> Return JSON (User Data + Permissions).

### 4. Sistem Izin (Permissions)
`config/app.php` -> `check_permission()` -> calls `config/permission.php:hasPermission()` -> 
  1. Administrator Override (bypass all).
  2. Manual Override (Tabel `user_permissions`).
  3. Role-based (Tabel `positions`).
  4. Legacy/Hardcoded logic based on position name/level.

# Clean Tree
```text
.
├── api/                # Procedural endpoints (JSON output)
├── app/                # Core logic per module (e.g., Payroll)
├── assets/             # Statis files (CSS, JS, Images, Icons)
├── config/             # Konfigurasi utama (DB, App, Permissions, FCM)
├── database/           # Migrasi dan skema database
├── db/                 # SQL Dumps / Script database tambahan
├── logic/              # Request handlers (POST processes)
├── uploads/            # Runtime artifacts (Photos, Documents)
├── views/              # Frontend pages / UI (PHP based)
├── index.php           # Entry Point (Redirect based on Auth)
└── .htaccess           # Routing & Clean URL config
```

# Module Map (The Chapters)
### Core & Config
- `config/app.php`: Global constants, URL helpers, session management.
- `config/database.php`: PDO Connection class (default: `attendance_db`).
- `config/permission.php`: `hasPermission()` function, logic pemetaan akses user.

### Modules (Shared structure between `views/` and `logic/`)
- `employees/`: Manajemen data pegawai, NIK, dan akun.
- `students/`: Manajemen data santri/siswa.
- `attendance/`: Monitoring kehadiran pegawai (Check-in/out).
- `tahfidz/`: Setoran hafalan Al-Quran.
- `boarding/`: Manajemen asrama dan perizinan santri.
- `class_attendance/`: Monitoring kehadiran berdasarkan jadwal pelajaran dan jurnal kelas.
- `student_attendance/`: Rekapitulasi kehadiran individu santri/siswa.
- `academic/`: Jadwal pelajaran, jurnal kelas, dan penilaian.
- `inventory/`: Manajemen aset dan barang (barcode/item code).
- `news/`: Pengumuman dan berita internal yayasan.

# Data & Config
- **Konfigurasi Utama**: `config/app.php` & `config/database.php`.
- **Database Utama**: `attendance_db` (MySQL).
- **Entitas Inti**:
  - `employees`: Data induk user.
  - `positions`: Role/Jabatan dan hak akses bawaan.
  - `students`: Data santri.
  - `attendance`: Log kehadiran harian.
  - `divisions`/`units`: Pengelompokan organisasi.
- **Migration**: Skrip tersedia di `database/migrations/`.
- **Uploads**: Foto profil di `uploads/profile_photos/`, dokumen di `uploads/`.

# External Integrations
- **Firebase (FCM)**: Menggunakan `config/fcm_helper.php` dan `api/update_fcm_token.php` untuk notifikasi ke mobile app.
- **UI Avatars**: Digunakan untuk placeholder foto profil di `views/employees/index.php`.
- **Google Meet**: Integrasi link meeting di modul `meetings/`.

# Risks / Blind Spots
1. **Procedural logic redundancy**: Banyak file di `views/` melakukan query database langsung tanpa layer abstraksi, menyulitkan perubahan skema database secara masif.
2. **Hidden Legacy Logic**: Terdapat fallback izin (permissions) yang di-hardcode berdasarkan nama jabatan (`Administrator`, `Koordinator Tahfidz`, dll) di `config/permission.php`.
3. **Double Database**: Adanya `PayrollDatabase.php` menunjukkan aplikasi mungkin berkomunikasi dengan database kedua untuk urusan penggajian.
4. **Clean URLs**: Penggunaan `.htaccess` untuk menyembunyikan ekstensi `.php` mengharuskan konfigurasi server yang tepat (mod_rewrite aktif).
