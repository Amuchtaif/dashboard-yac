<img width="1920" height="1080" alt="image" src="https://github.com/user-attachments/assets/311eabb6-5f6d-4c08-ade9-f5471c2bd407" />


# Dashboard YAC - Sistem Manajemen Terintegrasi

Dashboard YAC adalah aplikasi berbasis web yang dirancang untuk mengelola berbagai aspek operasional institusi pendidikan, mulai dari manajemen sumber daya manusia (SDM), akademik, hingga program tahfidz Qur'an.

## 🚀 Fitur Utama

### 1. Dashboard Utama
*   Statistik kehadiran pegawai dan santri.
*   Tren absensi bulanan.
*   Informasi aktivitas terbaru dan pemberitahuan sistem.

### 2. Manajemen Pegawai (HRIS)
*   **Data Pegawai**: Pengelolaan profil lengkap, foto, dan status keaktifan.
*   **Struktur Organisasi**: Pengaturan Bidang (Divisi), Unit Organisasi, dan Jabatan.
*   **Jadwal Kerja**: Pengaturan shift kerja dan jadwal rutin pegawai.
*   **Export/Import**: Dukungan ekspor data ke Excel/PDF dan impor dari CSV.

### 3. Absensi & Perizinan
*   **Absensi Mandiri**: Absensi menggunakan koordinat GPS (Geolokasi) dan verifikasi foto.
*   **Manajemen Perizinan**: Pengajuan dan persetujuan izin, sakit, atau cuti.
*   **Manajemen Rapat**: Dokumentasi kehadiran dan notulensi rapat.
*   **Laporan Kinerja**: Ringkasan performa kehadiran pegawai.

### 4. Manajemen Akademik
*   **Tahun Ajaran & Kalender**: Pengaturan periode akademik aktif dan agenda kegiatan.
*   **Data Siswa**: Manajemen database santri/siswa secara lengkap.
*   **Penempatan Kelas**: Fitur plotting siswa ke kelas masing-masing.
*   **Akademik Support**: Jadwal pelajaran, jurnal kelas, dan absensi harian siswa.

### 5. Manajemen Tahfidz
*   **Halaqah**: Pengaturan kelompok hafalan dan penugasan pengampu (Mudaris).
*   **Laporan Hafalan**: Pencatatan progres setoran hafalan santri secara periodik.
*   **Absensi Tahfidz**: Pencatatan kehadiran khusus untuk kegiatan halaqah.

### 6. Keamanan & Hak Akses (RBAC)
*   **Hak Akses Aplikasi**: Pengaturan izin fungsional (misal: buat rapat, setujui izin).
*   **Hak Akses Web**: Pengaturan izin akses ke modul-modul besar (Pegawai, Akademik, Tahfidz).
*   **Override Akses**: Kemampuan memberikan izin khusus kepada individu di luar izin jabatannya.

### 7. Pengaturan Sistem
*   **Manajemen Lokasi**: Pengaturan titik koordinat kantor/unit untuk validasi absensi.
*   **Konfigurasi Umum**: Pengaturan nama aplikasi, logo, dan identitas institusi.

### 8. Dukungan Mobile API
*   Tersedia endpoint API (JSON) untuk integrasi aplikasi mobile (Android/iOS).
*   Fitur: Login, Absensi, Pengajuan Izin, Data Tahfidz, dan Notifikasi FCM (Firebase Cloud Messaging).

## 📁 Struktur Proyek
*   `/api`: Endpoint untuk aplikasi mobile.
*   `/config`: Konfigurasi database dan aplikasi.
*   `/db`: File migrasi dan dump database SQL.
*   `/logic`: Logika backend/proses (PHP).
*   `/public`: Aset publik (css, js, images, uploads).
*   `/views`: Tampilan antarmuka web (Blade-like PHP templates).

## 🛠️ Teknologi yang Digunakan
*   **Bahasa Pemrograman**: PHP (Native)
*   **Database**: MySQL/MariaDB (PDO & MySQLi)
*   **Frontend**: Tailwind CSS, Vanilla JavaScript
*   **Charts**: Chart.js
*   **Icons**: Heroicons

## 📦 Instalasi
1. Clone repository ini.
2. Impor file database SQL dari folder `/db` ke MySQL Anda.
3. Sesuaikan konfigurasi database di `config/database.php`.
4. Sesuaikan `BASE_URL` di `config/app.php`.
5. Login default (jika ada) menggunakan akun Administrator.

---
Developed by **Amuchtaif**
