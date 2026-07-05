# Task Admin Panel: Modul Pengelompokan Karyawan (Employee Groups)

## Tujuan

Membangun halaman **Pengelompokan Karyawan (Employee Groups)** pada Admin Panel sebagai antarmuka untuk mengelola grup karyawan yang akan digunakan oleh berbagai modul seperti Rapat, Broadcast, Event, dan Absensi Kegiatan.

Task ini **hanya mencakup Admin Panel** dan tidak mencakup implementasi backend maupun aplikasi Flutter.

---

# Prasyarat

Backend Employee Groups telah selesai dan menyediakan:

- API CRUD Employee Group
- API CRUD Rule
- API CRUD Manual Member
- API Preview Dynamic Group

Seluruh data pada halaman ini menggunakan API, tanpa query langsung ke database.

---

# Menu Baru

Tambahkan menu baru pada Master Data:

```text
Master Data
├── Unit
├── Jabatan
├── Departemen
├── Lokasi Kerja
└── Pengelompokan Karyawan
```

---

# Halaman Daftar Pengelompokan

Tampilkan tabel dengan kolom:

- Nama Grup
- Jenis Grup
- Jumlah Anggota
- Status
- Deskripsi
- Terakhir Diperbarui
- Aksi

---

# Fitur

- Pencarian berdasarkan nama grup
- Filter berdasarkan:
  - Jenis Grup
  - Status

- Sorting
- Pagination

---

# Aksi

Setiap baris memiliki aksi:

- Detail
- Edit
- Hapus

Toolbar:

- Tambah Grup

---

# Form Tambah Grup

Field:

## Informasi Dasar

- Nama Grup \*
- Jenis Grup \*
  - Dynamic
  - Manual

- Status Aktif
- Deskripsi

---

# Dynamic Group

Jika jenis grup = Dynamic

Tampilkan halaman builder rule.

Contoh:

```text
Nama Grup

SDIT Ikhwan

------------------------

Rule 1

Field

[ Unit ▼ ]

Operator

[ = ]

Value

[ SDIT ▼ ]
```

Tambah rule

```text
+ Tambah Rule
```

Contoh kedua

```text
Field

Gender

=

Ikhwan
```

Rule ditampilkan dalam bentuk list yang mudah diedit.

Admin dapat:

- Tambah Rule
- Edit Rule
- Hapus Rule

---

# Preview Dynamic Group

Sebelum grup disimpan.

Tampilkan tombol:

```text
Preview Anggota
```

Saat diklik

Memanggil endpoint Preview.

Tampilkan modal berisi:

- Total Anggota
- Daftar Anggota

Kolom:

- Nama
- Unit
- Jabatan
- Gender

Preview bersifat read-only.

---

# Manual Group

Jika jenis grup = Manual

Tampilkan halaman pemilihan anggota.

Layout:

## Kiri

Pencarian Karyawan

Filter:

- Unit
- Jabatan
- Departemen
- Gender

Tabel:

- Nama
- Unit
- Jabatan

Checkbox multi-select.

---

## Kanan

Daftar Anggota Grup

Menampilkan seluruh anggota yang dipilih.

Fitur:

- Remove Member
- Remove All

Tampilkan total anggota.

---

# Halaman Detail Grup

Menampilkan informasi grup.

## Informasi

- Nama Grup
- Jenis
- Status
- Deskripsi

---

## Daftar Anggota

Jika Dynamic

Badge:

```text
Dynamic Group
```

Tampilkan daftar anggota hasil rule.

Tidak dapat diedit.

---

Jika Manual

Badge:

```text
Manual Group
```

Admin dapat:

- Tambah Anggota
- Hapus Anggota

---

# Edit Grup

Dynamic

Admin dapat:

- Mengubah informasi grup
- Mengubah rule
- Melakukan preview ulang

---

Manual

Admin dapat:

- Mengubah informasi grup
- Menambah anggota
- Menghapus anggota

---

# Hapus Grup

Saat menghapus grup.

Tampilkan dialog konfirmasi.

Jika grup sedang digunakan oleh modul lain.

Tampilkan pesan:

```text
Grup ini sedang digunakan oleh modul lain dan tidak dapat dihapus.
```

---

# UI/UX

Gunakan layout yang bersih dan mudah dipahami.

Builder Rule harus dibuat seperti query builder sederhana.

Contoh:

```text
Unit

=

SDIT

AND

Gender

=

Ikhwan

AND

Status

=

Aktif
```

---

# Empty State

Jika belum ada grup.

Tampilkan ilustrasi dan pesan:

```text
Belum ada pengelompokan karyawan.

Klik "Tambah Grup" untuk membuat grup pertama.
```

---

# Loading State

Seluruh proses API harus memiliki:

- Loading Spinner
- Skeleton Loader pada tabel
- Disabled Button saat proses submit

---

# Error Handling

Tampilkan pesan yang jelas apabila:

- Gagal memuat data
- Gagal menyimpan
- Gagal menghapus
- Gagal mengambil preview

---

# Validasi Form

Nama Grup

- Wajib diisi
- Harus unik

Jenis Grup

- Wajib dipilih

Dynamic Group

- Minimal memiliki satu rule

Manual Group

- Minimal memiliki satu anggota

---

# Hak Akses

Administrator

- Full CRUD

Operator

- View Only

User tanpa izin tidak dapat mengakses halaman ini.

---

# Responsif

Halaman harus tetap nyaman digunakan pada:

- Desktop
- Laptop
- Tablet

---

# Acceptance Criteria

- Menu Pengelompokan Karyawan tersedia pada Admin Panel.
- Halaman daftar grup berjalan dengan pagination, pencarian, dan filter.
- Admin dapat membuat Dynamic Group.
- Admin dapat membuat Manual Group.
- Builder Rule mudah digunakan.
- Preview anggota Dynamic Group berjalan melalui API.
- Pengelolaan anggota Manual Group berjalan dengan baik.
- Halaman detail grup menampilkan informasi dan anggota sesuai jenis grup.
- Seluruh validasi form berjalan.
- Seluruh proses menggunakan API backend tanpa query langsung ke database.
- UI konsisten dengan desain Admin Panel yang sudah ada.
