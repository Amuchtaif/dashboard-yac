# Task Backend: Modul Pengelompokan Karyawan (Employee Groups API)

## Tujuan

Membangun backend untuk sistem **Pengelompokan Karyawan (Employee Groups)** yang akan menjadi master data seluruh grup karyawan di dalam sistem.

Backend ini akan digunakan oleh:

- Admin Panel
- Aplikasi Flutter
- Modul Rapat
- Modul Broadcast
- Modul Absensi Kegiatan
- Modul Penugasan
- Modul lainnya

Task ini **hanya mencakup backend**, tidak termasuk implementasi UI Admin Panel maupun Flutter.

---

# Scope

Backend harus menyediakan:

- Database
- Migration
- Model
- Relasi
- Service / Business Logic
- REST API
- Authorization
- Validation

---

# Database

## employee_groups

Kolom:

- id
- group_name
- group_type (dynamic/manual)
- description
- is_active
- created_at
- updated_at

---

## employee_group_rules

Kolom:

- id
- group_id
- field_name
- operator
- field_value
- created_at
- updated_at

Digunakan hanya untuk Dynamic Group.

Contoh:

```text
Unit = SDIT
Gender = Ikhwan
Status = Aktif
```

Rule menggunakan operator **AND**.

---

## employee_group_members

Kolom:

- id
- group_id
- employee_id
- created_at

Digunakan hanya untuk Manual Group.

---

# Business Rules

## Dynamic Group

Backend harus dapat menghasilkan daftar anggota berdasarkan rule.

Contoh:

Rule

Unit = SDIT

Gender = Ikhwan

↓

Backend mengembalikan seluruh pegawai yang memenuhi rule tersebut.

---

## Manual Group

Backend mengambil anggota dari tabel employee_group_members.

---

# Supported Rule

Tahap pertama mendukung filter:

- Unit
- Gender
- Jabatan
- Departemen
- Status Karyawan

Arsitektur harus memungkinkan penambahan rule baru tanpa perubahan struktur database.

---

# REST API

## Employee Group

- GET /employee-groups
- GET /employee-groups/{id}
- POST /employee-groups
- PUT /employee-groups/{id}
- DELETE /employee-groups/{id}

---

## Dynamic Rule

- POST Rule
- PUT Rule
- DELETE Rule

---

## Manual Member

- GET Members
- POST Add Member
- DELETE Remove Member

---

## Preview Dynamic Group

Endpoint khusus untuk melakukan preview hasil rule sebelum grup disimpan.

Response berisi:

- Total anggota
- Daftar anggota
- Ringkasan filter

---

# Validation

Employee Group

- Nama grup wajib unik.
- group_type wajib dynamic/manual.
- Dynamic Group minimal memiliki satu rule.
- Manual Group minimal memiliki satu anggota.

Rule

- field_name harus valid.
- operator harus valid.
- field_value wajib diisi.

Member

- Tidak boleh ada anggota duplikat.
- Employee harus aktif.

---

# Authorization

Administrator

- Full CRUD

Operator

- Read Only

Endpoint lain hanya dapat menggunakan data grup tanpa mengubahnya.

---

# Performance

Dynamic Group harus menggunakan query yang efisien.

Hindari proses filtering di sisi aplikasi.

Semua filtering dilakukan oleh database.

---

# Future Ready

Struktur backend harus dapat digunakan kembali oleh:

- Modul Rapat
- Broadcast Notification
- Pengumuman
- Event
- Voting
- Approval Workflow
- Absensi Kegiatan

Tanpa perubahan struktur database.

---

# Acceptance Criteria

- Migration berhasil dijalankan.
- Relasi database berjalan dengan baik.
- CRUD Employee Group tersedia.
- CRUD Rule tersedia.
- CRUD Manual Member tersedia.
- Endpoint Preview Dynamic Group berjalan.
- Dynamic Group menghasilkan anggota secara otomatis.
- Manual Group mengelola anggota secara manual.
- Validasi berjalan sesuai business rule.
- API siap digunakan oleh Admin Panel dan Flutter.
