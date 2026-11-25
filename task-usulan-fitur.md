# 📋 [Task Spec] Backend API & Database: Fitur "Usulan Fitur/Lapor Kendala"

Dokumen spesifikasi teknis dan rincian tugas pengerjaan backend (Database MySQL, REST API PHP, dan Web Dashboard Admin Panel) untuk fitur **Usulan Fitur & Lapor Kendala** pada aplikasi **Dashboard YAC (Yayasan As-Sunnah Cirebon)**.

---

## 1. 🗄️ Skema Database (MySQL / MariaDB DDL)

Buat tabel baru dengan nama `user_feedbacks`:

```sql
CREATE TABLE IF NOT EXISTS `user_feedbacks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL COMMENT 'ID pegawai pengusul (relasi ke tabel users/pegawai)',
  `type` ENUM('feature', 'improvement', 'bug', 'general') NOT NULL DEFAULT 'feature' COMMENT 'feature: Fitur Baru, improvement: Peningkatan, bug: Lapor Kendala, general: Saran Umum',
  `module` VARCHAR(100) NOT NULL DEFAULT 'Umum' COMMENT 'Tahfidz, Keasramaan, Presensi Makan, Presensi Pegawai, Payroll/Gaji, Homeroom/Wali Kelas, Akademik, Umum',
  `priority` ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium' COMMENT 'low: Rendah, medium: Sedang, high: Mendesak',
  `title` VARCHAR(255) NOT NULL COMMENT 'Judul singkat usulan / kendala',
  `description` TEXT NOT NULL COMMENT 'Penjelasan rinci',
  `attachment_path` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Path file foto/screenshot di server (contoh: uploads/feedbacks/fb_123.jpg)',
  `app_version` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Versi aplikasi saat pengiriman (contoh: 1.0.8+25)',
  `device_info` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Sistem operasi & perangkat (contoh: android / ios)',
  `status` ENUM('pending', 'reviewed', 'in_progress', 'completed', 'rejected') NOT NULL DEFAULT 'pending' COMMENT 'pending: Menunggu, reviewed: Ditinjau, in_progress: Dalam Antrean Rilis, completed: Selesai, rejected: Ditunda',
  `admin_notes` TEXT NULL DEFAULT NULL COMMENT 'Catatan tanggapan / balasan dari admin untuk user',
  `admin_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'ID user admin yang meninjau/membalas',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_user_feedbacks_user` (`user_id`),
  INDEX `idx_user_feedbacks_status` (`status`),
  INDEX `idx_user_feedbacks_module` (`module`),
  INDEX `idx_user_feedbacks_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 2. 🔌 Daftar Endpoint REST API (Untuk Mobile Client)

Lokasi folder API: `api/feedback/`

### A. Kirim Usulan / Lapor Kendala

- **URL:** `POST /api/feedback/submit.php`
- **Content-Type:** `multipart/form-data`
- **Request Fields:**

| Field         | Tipe Data     | Wajib | Keterangan                                                          |
| :------------ | :------------ | :---- | :------------------------------------------------------------------ |
| `user_id`     | Integer       | Ya    | ID Pengguna pengusul                                                |
| `type`        | String        | Ya    | `'feature'`, `'improvement'`, `'bug'`, atau `'general'`             |
| `module`      | String        | Ya    | Contoh: `'Keasramaan'`, `'Tahfidz'`, `'Umum'`                       |
| `priority`    | String        | Tidak | `'low'`, `'medium'`, atau `'high'` (Default: `'medium'`)            |
| `title`       | String        | Ya    | Judul usulan (maks. 255 karakter)                                   |
| `description` | String        | Ya    | Penjelasan detail usulan                                            |
| `app_version` | String        | Tidak | Versi aplikasi (contoh: `"1.0.8"`)                                  |
| `device_info` | String        | Tidak | Model HP / OS (contoh: `"android"`)                                 |
| `attachment`  | File (Binary) | Tidak | Foto/Screenshot bukti (`.jpg`, `.jpeg`, `.png`, `.webp`, maks. 5MB) |

- **Response Sukses (HTTP 200):**

```json
{
  "success": true,
  "message": "Usulan berhasil dikirim ke Tim IT & Manajemen",
  "data": {
    "id": 15,
    "user_id": 42,
    "title": "Filter tanggal di absensi asrama",
    "status": "pending",
    "created_at": "2026-11-25 14:30:00"
  }
}
```

- **Penanganan Upload File:**
  - Simpan di folder: `uploads/feedbacks/`
  - Nama file unik: `fb_{user_id}_{timestamp}_{random}.jpg`
  - Validasi ketat ekstensi dan MIME type untuk mencegah eksekusi skrip berbahaya.

---

### B. Ambil Riwayat Masukan User (Privat)

- **URL:** `GET /api/feedback/my_feedbacks.php?user_id={user_id}`
- **Response Sukses (HTTP 200):**

```json
{
  "success": true,
  "data": [
    {
      "id": 15,
      "user_id": 42,
      "user_name": "Ust. Ahmad",
      "user_division": "Keasramaan",
      "type": "feature",
      "module": "Keasramaan",
      "priority": "medium",
      "title": "Filter cepat santri per kamar",
      "description": "Mohon ditambahkan filter per kamar di absensi...",
      "attachment_url": "https://dashboard.assunnahcirebon.com/uploads/feedbacks/fb_42_17955.jpg",
      "app_version": "1.0.8",
      "device_info": "android",
      "status": "in_progress",
      "admin_notes": "Jazakallahu khair, usulan ini sudah masuk antrean rilis v2.4.",
      "admin_name": "Tim IT YAC",
      "created_at": "2026-11-25 14:30:00",
      "updated_at": "2026-11-25 15:00:00"
    }
  ]
}
```

---

## 3. 🖥️ Endpoint untuk Web Dashboard Panel (Admin / IT)

Lokasi folder API: `api/feedback/admin/`

### A. List Semua Usulan Pegawai

- **URL:** `GET /api/feedback/admin/list.php`
- **Query Params:**
  - `status`: `pending`, `reviewed`, `in_progress`, `completed`, `rejected` (Opsional)
  - `type`: `feature`, `improvement`, `bug`, `general` (Opsional)
  - `module`: Nama modul (Opsional)
  - `search`: Pencarian judul / nama pengusul (Opsional)
  - `page`: Halaman (Default: `1`)
  - `limit`: Jumlah per halaman (Default: `20`)
- **Response Sukses (HTTP 200):**

```json
{
  "success": true,
  "summary": {
    "total": 35,
    "pending": 8,
    "reviewed": 5,
    "in_progress": 4,
    "completed": 15,
    "rejected": 3
  },
  "pagination": {
    "page": 1,
    "limit": 20,
    "total_pages": 2
  },
  "data": [
    {
      "id": 15,
      "user_id": 42,
      "user_name": "Ust. Ahmad",
      "user_division": "Keasramaan",
      "user_phone": "08123456789",
      "type": "feature",
      "module": "Keasramaan",
      "priority": "high",
      "title": "Filter tanggal di absensi asrama",
      "description": "...",
      "attachment_url": "...",
      "status": "pending",
      "admin_notes": null,
      "created_at": "2026-11-25 14:30:00"
    }
  ]
}
```

---

### B. Update Status & Beri Balasan ke User

- **URL:** `POST /api/feedback/admin/update_status.php`
- **Content-Type:** `application/json`
- **Request Body:**

```json
{
  "feedback_id": 15,
  "admin_id": 1,
  "status": "in_progress",
  "admin_notes": "Usulan telah disetujui dalam rapat IT dan sedang dikerjakan untuk update berikutnya."
}
```

- **Response Sukses (HTTP 200):**

```json
{
  "success": true,
  "message": "Status dan catatan balasan berhasil diperbarui"
}
```

- **FCM Push Notification Trigger (Opsional):**
  - Saat `admin_notes` diisi atau status berubah ke `completed` / `in_progress`:
  - Kirimkan notifikasi FCM ke perangkat `user_id`:
    - Title: `Tanggapan Usulan Fitur`
    - Body: `Tim IT telah menanggapi usulan Anda: "[title]"`

---

## 4. 🗂️ Rincian File Backend yang Perlu Dibuat / Disesuaikan

| No  | File Path                              | Fungsi                                           |
| :-- | :------------------------------------- | :----------------------------------------------- |
| 1   | `api/feedback/submit.php`              | Menerima submit form mobile + upload gambar      |
| 2   | `api/feedback/my_feedbacks.php`        | Mengambil riwayat masukan per user               |
| 3   | `api/feedback/admin/list.php`          | Mengambil daftar tiket untuk web dashboard       |
| 4   | `api/feedback/admin/update_status.php` | Mengubah status & menyimpan balasan admin        |
| 5   | `uploads/feedbacks/`                   | Folder direktori penyimpanan gambar upload       |
| 6   | Menu di Web Admin Dashboard            | Halaman tabel/kanban pengelolaan masukan pegawai |
