# Task Admin Panel - Dashboard Tahfidz Bertingkat

## Tujuan

Membangun Dashboard Tahfidz pada Admin Panel sebagai pusat monitoring berbasis web untuk pimpinan dan administrator, menggunakan API Dashboard Tahfidz yang telah disediakan oleh backend.

Dashboard hanya bersifat monitoring dan analisis, tanpa fitur input data tahfidz.

---

# Ruang Lingkup

Admin Panel menggunakan API Dashboard Tahfidz yang sama dengan aplikasi Flutter.

Tidak diperbolehkan membuat query langsung ke database untuk kebutuhan dashboard.

---

# Dashboard

## Executive Summary

Menampilkan KPI:

- Total Santri
- Total Pengampu
- Total Halaqoh
- Kehadiran Santri
- Kehadiran Pengampu
- Total Setoran Hari Ini
- Total Murajaah Hari Ini
- Hafalan Baru Hari Ini
- Santri Belum Setor
- Halaqoh Belum Mengisi Aktivitas

---

## Dashboard Kehadiran

Menampilkan statistik kehadiran:

### Santri

- Hadir
- Izin
- Sakit
- Alfa

### Pengampu

- Hadir
- Izin
- Tidak Hadir
- Belum Absen

---

## Live Activity

Menampilkan aktivitas terbaru:

- Input Absensi
- Setoran
- Murajaah
- Evaluasi
- Perubahan Data

Data diperbarui otomatis.

---

## Progress Hafalan

Menampilkan:

- Total Hafalan
- Target Semester
- Target Tahunan
- Persentase

---

## Distribusi Hafalan

Visualisasi chart kategori:

- Belum 1 Juz
- 1–5 Juz
- 6–10 Juz
- 11–20 Juz
- 21–29 Juz
- 30 Juz

---

## Monitoring Halaqoh

Tabel:

- Nama Halaqoh
- Pengampu
- Jumlah Santri
- Kehadiran
- Setoran
- Murajaah
- Progress

Klik membuka detail.

---

## Monitoring Pengampu

Menampilkan performa seluruh pengampu.

---

## Monitoring Santri

Menampilkan:

- Nama
- Kelas
- Halaqoh
- Pengampu
- Progress Hafalan
- Kehadiran

Dilengkapi pencarian dan pagination.

---

## Santri Perlu Perhatian

Menampilkan daftar otomatis:

- Tidak setor > 3 hari
- Kehadiran rendah
- Progress stagnan
- Murajaah rendah

---

## Statistik Historis

Grafik berdasarkan:

- Hari
- Minggu
- Bulan
- Semester
- Tahun Ajaran

---

## Executive Insight

Menampilkan ringkasan otomatis dari backend.

---

## Ranking

Menampilkan:

- Ranking Unit
- Ranking Halaqoh
- Ranking Pengampu

---

## Health Score

Menampilkan skor setiap unit.

---

# Drill Down

Dashboard harus mendukung navigasi:

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

---

# Filter Global

Seluruh widget mengikuti filter:

- Unit
- Jenjang
- Kelas
- Halaqoh
- Pengampu
- Semester
- Tahun Ajaran
- Rentang Tanggal

---

# Hak Akses

Tampilan dashboard mengikuti role login:

- Kepala Pondok
- Mudir
- Kamad
- Kanit Tahfidz

Seluruh pembatasan data mengikuti response API backend.

---

# Ketentuan Teknis

- Menggunakan API Dashboard Tahfidz.
- Tidak membuat query database langsung.
- Mendukung auto refresh/live update.
- Tidak menggunakan push notification.
- Menampilkan loading, empty state, dan error state pada setiap widget.
- Seluruh KPI dan tabel mendukung drill-down ke halaman detail.

---

# Acceptance Criteria

- Dashboard menampilkan seluruh widget sesuai desain.
- Seluruh data berasal dari API Dashboard Tahfidz.
- Hak akses mengikuti role pengguna.
- Filter global diterapkan pada seluruh widget.
- Drill-down berfungsi hingga detail santri.
- Dashboard responsif dan nyaman digunakan pada desktop.
