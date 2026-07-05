# Task: Modul Amaliyah / Aktivitas Santri (Backend & Admin Panel)

## Tujuan

Membangun modul **Amaliyah / Aktivitas Santri** sebagai media pencatatan aktivitas pembiasaan ibadah dan kegiatan non-akademik santri.

Modul ini berdiri sendiri dan **tidak terintegrasi dengan modul Tahfidz** maupun modul akademik lainnya.

Data aktivitas akan diinput melalui aplikasi Flutter oleh **Guru** dan **Musyrif/AH**, sedangkan Admin Panel berfungsi sebagai pengelola master data, monitoring, dan pelaporan.

---

# Scope Pekerjaan

## Backend API

- Master Jenis Aktivitas
- API Aktivitas Santri
- API Upload Dokumentasi
- API Mobile untuk Flutter
- Dashboard Statistik
- Role & Permission

## Admin Panel

- Master Jenis Aktivitas
- Monitoring Aktivitas Santri
- Dashboard Statistik

---

# 1. Database

## 1.1 activity_types

Membuat tabel master jenis aktivitas yang bersifat dinamis.

Field:

- id
- name
- slug
- type (enum: personal, event)
- description (nullable)
- icon (nullable)
- color (nullable)
- point (default 0)
- sort_order
- is_active
- created_at
- updated_at
- deleted_at (Soft Delete)

### Keterangan

Admin dapat menambah, mengubah, mengaktifkan, menonaktifkan, maupun menghapus (Soft Delete) jenis aktivitas.

Contoh data:

- Shalat Dhuha
- Puasa Sunnah
- Dzikir Pagi
- Dzikir Petang
- Kajian
- Kerja Bakti
- Bakti Sosial
- Menjadi Imam

Daftar ini sepenuhnya dinamis dan tidak di-hardcode.

---

## 1.2 student_activities

Menyimpan aktivitas yang dilakukan oleh santri.

Field:

- id
- activity_type_id
- student_id
- activity_date
- start_time (nullable)
- end_time (nullable)
- status (completed, not_completed, excused)
- note (nullable)
- created_by
- updated_by
- created_at
- updated_at
- deleted_at

---

## 1.3 activity_files

Menyimpan dokumentasi aktivitas.

Field:

- id
- activity_id
- file_path
- file_type
- caption
- uploaded_by
- created_at

Satu aktivitas dapat memiliki lebih dari satu lampiran.

---

# 2. Backend API

## Master Jenis Aktivitas

Endpoint:

- GET /api/activity-types
- POST /api/activity-types
- PUT /api/activity-types/{id}
- DELETE /api/activity-types/{id}
- PATCH /api/activity-types/{id}/status

Support:

- Pagination
- Search
- Filter tipe
- Filter status aktif
- Sorting

Validasi:

- Nama tidak boleh duplikat.
- Slug dibuat otomatis.
- Tidak boleh dihapus apabila masih digunakan oleh aktivitas santri.

---

## API Aktivitas Santri

Endpoint:

- GET /api/student-activities
- GET /api/student-activities/{id}
- PUT /api/student-activities/{id}
- DELETE /api/student-activities/{id}

Support filter:

- Santri
- Jenis aktivitas
- Status
- Rentang tanggal
- Dibuat oleh

Support:

- Pagination
- Search
- Sorting

Catatan:

Endpoint POST tidak diperlukan untuk Admin Panel karena aktivitas diinput melalui Flutter.

---

## API Upload Dokumentasi

Endpoint:

POST /api/student-activities/{id}/attachments

Support:

- Multiple Upload
- Image
- PDF

Endpoint:

DELETE /api/student-activities/{id}/attachments/{attachmentId}

---

## API Mobile (Flutter)

Endpoint yang digunakan oleh Guru dan Musyrif/AH.

### Jenis Aktivitas

GET /api/mobile/activity-types

Menampilkan seluruh jenis aktivitas yang aktif.

---

### Daftar Santri

GET /api/mobile/students

Menampilkan daftar santri sesuai hak akses Guru atau Musyrif/AH.

---

### Riwayat Aktivitas

GET /api/mobile/student-activities

Support filter:

- Santri
- Jenis Aktivitas
- Tanggal

---

### Input Aktivitas

POST /api/mobile/student-activities

Validasi:

- Jenis aktivitas aktif.
- Santri berada dalam cakupan pengguna.
- Tanggal wajib diisi.

---

### Edit Aktivitas

PUT /api/mobile/student-activities/{id}

---

### Hapus Aktivitas

DELETE /api/mobile/student-activities/{id}

---

### Upload Dokumentasi

POST /api/mobile/student-activities/{id}/attachments

Support:

- Multiple Upload
- Image
- PDF

---

# 3. Admin Panel

## Menu Baru

### Amaliyah Santri

Submenu:

- Master Jenis Aktivitas
- Monitoring Aktivitas
- Dashboard Statistik

---

## 3.1 Master Jenis Aktivitas

CRUD penuh.

Kolom:

- Nama Aktivitas
- Tipe
- Icon
- Warna
- Poin
- Urutan
- Status
- Total Digunakan

Fitur:

- Tambah
- Edit
- Soft Delete
- Aktif / Nonaktif
- Search
- Filter
- Sorting

---

## 3.2 Monitoring Aktivitas

Halaman DataTable untuk memantau seluruh aktivitas yang telah diinput melalui Flutter.

Kolom:

- Tanggal
- Nama Santri
- Jenis Aktivitas
- Tipe
- Status
- Guru/Musyrif Penginput
- Dokumentasi

Filter:

- Unit
- Kelas
- Santri
- Jenis Aktivitas
- Status
- Rentang Tanggal

Action:

- Detail
- Edit
- Hapus
- Lihat Dokumentasi

Admin tidak melakukan input aktivitas melalui halaman ini.

---

## 3.3 Dashboard Statistik

Widget:

- Total Aktivitas Hari Ini
- Total Aktivitas Bulan Ini
- Aktivitas Personal
- Aktivitas Event
- Aktivitas Terbanyak
- Grafik Aktivitas Bulanan

---

# 4. Role & Permission

## Admin

Hak akses:

- Kelola Master Jenis Aktivitas
- Monitoring seluruh aktivitas
- Melihat statistik
- Mengedit aktivitas apabila diperlukan
- Menghapus aktivitas apabila diperlukan

---

## Guru

Hak akses melalui Flutter:

- Melihat daftar santri yang menjadi tanggung jawabnya
- Menginput aktivitas
- Mengedit aktivitas miliknya
- Menghapus aktivitas miliknya
- Upload dokumentasi
- Melihat riwayat aktivitas

---

## Musyrif / AH

Hak akses melalui Flutter:

- Melihat daftar santri yang menjadi tanggung jawabnya
- Menginput aktivitas
- Mengedit aktivitas miliknya
- Menghapus aktivitas miliknya
- Upload dokumentasi
- Melihat riwayat aktivitas

---

# Acceptance Criteria

- Modul Amaliyah berdiri sendiri dan tidak terhubung dengan modul Tahfidz.
- Master Jenis Aktivitas bersifat dinamis dan dikelola melalui Admin Panel.
- Jenis aktivitas mendukung tipe Personal dan Event.
- Seluruh aktivitas diinput melalui aplikasi Flutter oleh Guru dan Musyrif/AH.
- Admin Panel hanya digunakan untuk pengelolaan master data, monitoring, statistik, dan koreksi data.
- Tidak terdapat proses approval maupun verifikasi.
- Flutter hanya dapat mengakses jenis aktivitas yang berstatus aktif.
- Daftar santri pada Flutter dibatasi berdasarkan hak akses Guru atau Musyrif/AH.
- Seluruh endpoint mendukung pagination, filtering, searching, dan sorting.
- Seluruh tabel utama menggunakan Soft Delete.
- Seluruh API menggunakan validasi dan response JSON yang konsisten.
