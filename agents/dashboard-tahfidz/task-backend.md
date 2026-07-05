# Task Backend - API Dashboard Tahfidz Bertingkat

## Tujuan

Membangun REST API Dashboard Tahfidz yang berfungsi sebagai pusat agregasi data monitoring tahfidz untuk seluruh level pimpinan (Kepala Pondok, Mudir, Kamad, dan Kanit Tahfidz).

Backend bertanggung jawab menghitung seluruh statistik, melakukan agregasi data, menerapkan hak akses berdasarkan role, serta menyediakan endpoint yang digunakan oleh aplikasi Flutter dan Admin Panel.

---

# Ruang Lingkup

Backend hanya menyediakan API.

Tidak membuat tampilan dashboard.

Seluruh data dashboard harus berasal dari data operasional yang telah diinput oleh Pengampu pada modul:

- Absensi Tahfidz
- Setoran Hafalan
- Murajaah
- Evaluasi Hafalan

---

# Dashboard Service

Buat service khusus sebagai pusat agregasi data dashboard.

Contoh:

- DashboardTahfidzService

Service ini bertanggung jawab menghitung seluruh statistik dashboard sehingga controller hanya mengambil data dari service.

---

# Hak Akses

## Kepala Pondok

Dapat melihat seluruh data:

- MTs
- MA
- Seluruh Halaqoh
- Seluruh Pengampu
- Seluruh Santri

---

## Mudir

Dapat melihat seluruh Unit Pendidikan:

- MTs
- MA

---

## Kamad

Hanya dapat melihat data unit yang dipimpin.

Contoh:

- Kamad MTs hanya melihat MTs.
- Kamad MA hanya melihat MA.

---

## Kanit Tahfidz

Hanya melihat seluruh halaqoh dalam unitnya.

---

# API Executive Summary

Menyediakan data:

- Total Santri
- Total Pengampu
- Total Halaqoh
- Kehadiran Santri Hari Ini
- Kehadiran Pengampu Hari Ini
- Total Setoran Hari Ini
- Total Murajaah Hari Ini
- Hafalan Baru Hari Ini
- Santri Belum Setor
- Halaqoh Belum Mengisi Aktivitas

---

# API Dashboard Kehadiran

Mengembalikan:

## Santri

- Hadir
- Izin
- Sakit
- Alfa

## Pengampu

- Hadir
- Izin
- Tidak Hadir
- Belum Absen

---

# API Live Activity

Mengembalikan aktivitas terbaru.

Jenis aktivitas:

- Input Absensi
- Setoran
- Murajaah
- Evaluasi
- Perubahan Data

Urut berdasarkan waktu terbaru.

---

# API Progress Hafalan

Mengembalikan:

- Total Hafalan
- Target Semester
- Target Tahunan
- Persentase Target

---

# API Distribusi Hafalan

Mengembalikan jumlah santri berdasarkan kategori:

- Belum 1 Juz
- 1–5 Juz
- 6–10 Juz
- 11–20 Juz
- 21–29 Juz
- 30 Juz

---

# API Monitoring Halaqoh

Mengembalikan:

- Nama Halaqoh
- Pengampu
- Jumlah Santri
- Kehadiran
- Setoran
- Murajaah
- Progress Hafalan

Mendukung pagination.

---

# API Detail Halaqoh

Mengembalikan:

- Informasi Halaqoh
- Daftar Santri
- Riwayat Aktivitas
- Kehadiran
- Progress
- Grafik Hafalan

---

# API Monitoring Pengampu

Mengembalikan:

- Nama
- Halaqoh
- Kehadiran
- Jumlah Setoran
- Jumlah Murajaah
- Evaluasi

---

# API Detail Pengampu

Mengembalikan:

- Profil Pengampu
- Halaqoh
- Riwayat Aktivitas
- Statistik Pengampu

---

# API Monitoring Santri

Mengembalikan:

- Nama
- Kelas
- Halaqoh
- Pengampu
- Hafalan Terakhir
- Hari Terakhir Setor
- Progress Hafalan
- Kehadiran

Mendukung pencarian dan pagination.

---

# API Detail Santri

Mengembalikan:

- Profil
- Hafalan Terakhir
- Riwayat Setoran
- Riwayat Murajaah
- Grafik Progress
- Kehadiran

---

# API Santri Perlu Perhatian

Mengembalikan daftar santri yang memenuhi kondisi:

- Tidak setor > 3 hari
- Kehadiran rendah
- Alfa berturut-turut
- Progress stagnan
- Murajaah rendah
- Target semester belum tercapai

---

# API Statistik Historis

Mendukung filter:

- Hari
- Minggu
- Bulan
- Semester
- Tahun Ajaran

Mengembalikan data grafik.

---

# API Executive Insight

Menghasilkan ringkasan otomatis berdasarkan data dashboard.

Contoh informasi:

- Persentase Kehadiran
- Total Setoran
- Total Murajaah
- Unit Terbaik
- Halaqoh Terbaik
- Halaqoh Memerlukan Perhatian
- Santri Belum Setor

---

# API Perbandingan Unit

Mengembalikan statistik:

- MTs
- MA

Perbandingan:

- Santri
- Pengampu
- Kehadiran
- Progress Hafalan

---

# API Ranking

Menyediakan endpoint:

## Ranking Unit

## Ranking Halaqoh

## Ranking Pengampu

Berdasarkan parameter:

- Kehadiran
- Progress Hafalan
- Setoran
- Murajaah

---

# API Health Score

Menghitung skor setiap unit berdasarkan:

- Kehadiran
- Progress Hafalan
- Kelengkapan Aktivitas
- Setoran
- Murajaah

---

# API Drill Down

Mendukung navigasi bertingkat:

Kepala Pondok / Mudir

↓

Unit

↓

Kelas

↓

Halaqoh

↓

Pengampu

↓

Santri

↓

Riwayat Hafalan

Seluruh data harus mengikuti hak akses role.

---

# Filter Global

Seluruh endpoint harus mendukung filter:

- Unit
- Jenjang
- Kelas
- Halaqoh
- Pengampu
- Semester
- Tahun Ajaran
- Rentang Tanggal

---

# Optimasi Backend

- Gunakan DashboardTahfidzService sebagai pusat agregasi data.
- Hindari query berulang (N+1 Query).
- Optimalkan query menggunakan eager loading dan agregasi.
- Gunakan caching untuk data yang tidak memerlukan pembaruan setiap saat.
- Terapkan pagination pada endpoint yang menampilkan daftar data.
- Pastikan seluruh endpoint memiliki response time yang optimal untuk dashboard.

---

# Acceptance Criteria

- Seluruh endpoint dashboard tersedia dan terdokumentasi.
- Hak akses diterapkan sesuai role (Kepala Pondok, Mudir, Kamad, Kanit Tahfidz).
- Seluruh statistik dihitung dari data operasional aktual.
- Seluruh endpoint mendukung filter global.
- Endpoint drill-down berfungsi hingga level santri.
- API dapat digunakan oleh Flutter dan Admin Panel tanpa perubahan logika bisnis.
- Struktur backend mudah dikembangkan untuk penambahan KPI atau widget dashboard di masa mendatang.