# TASK_DATABASE_MIGRATION.md

# Database Migration - Refactor Modul Tahfidz Dashboard YAC

## Priority

**Critical**

## Type

Database Schema Migration (Backward Compatible)

---

# Background

Modul Tahfidz saat ini telah digunakan pada sistem production sehingga seluruh histori transaksi harus tetap dipertahankan.

Refactor Modul Tahfidz memperkenalkan beberapa konsep baru, antara lain:

- Baseline Hafalan
- Multi Surah Setoran
- Jenis Setoran
- Semester Snapshot

Migrasi **tidak bertujuan mengganti sistem lama**, melainkan memperluas struktur database agar mendukung fitur baru tanpa mengganggu operasional yang sedang berjalan.

Seluruh proses migrasi wajib menggunakan prinsip **Backward Compatibility**.

---

# Migration Objectives

- Menambahkan struktur database baru.
- Mempertahankan seluruh histori setoran.
- Menjamin API lama tetap dapat berjalan selama masa transisi.
- Menghindari data loss.
- Menyiapkan database untuk implementasi Flutter versi baru.

---

# Migration Strategy

Gunakan pendekatan bertahap.

```text
Backup Database
        │
        ▼
Schema Migration
        │
        ▼
Data Mapping
        │
        ▼
Backend Support Old + New Schema
        │
        ▼
Flutter Update
        │
        ▼
Production Validation
        │
        ▼
Cleanup (Future Release)
```

**Dilarang menghapus struktur lama pada proses migrasi pertama.**

---

# Phase 1 - Database Backup

## Wajib dilakukan sebelum migrasi

- Backup seluruh database production.
- Backup struktur tabel.
- Backup data transaksi Tahfidz.
- Pastikan file backup dapat direstore.

Checklist

- Backup berhasil dibuat.
- Backup telah diuji restore.
- Dokumentasikan lokasi backup.

---

# Phase 2 - Schema Migration

## Tambahkan tabel baru

### memorization_baselines

Digunakan untuk menyimpan hasil asesmen hafalan awal setiap Tahun Ajaran.

Minimal field:

- id
- academic_year_id
- student_id
- baseline_juz
- assessment_date
- assessor_id
- verification_status
- verified_by
- verified_at
- notes
- created_at
- updated_at

Constraint:

- UNIQUE (academic_year_id, student_id)

---

### semester_snapshots

Digunakan untuk menyimpan hasil Semester Closing.

Minimal field:

- id
- academic_year_id
- semester
- student_id
- baseline_juz
- target_juz
- memorized_juz
- total_juz
- murojaah_total
- tasmi_score
- progress_percentage
- generated_at
- created_by

Snapshot bersifat read-only.

---

# Phase 3 - Alter Existing Table

Refactor tabel:

```text
memorization_entries
```

Tambahkan kolom baru.

| Field          | Keterangan    |
| -------------- | ------------- |
| entry_type     | Jenis Setoran |
| start_surah_id | Surah Awal    |
| start_ayah     | Ayat Awal     |
| end_surah_id   | Surah Akhir   |
| end_ayah       | Ayat Akhir    |
| line_count     | Jumlah Baris  |
| score          | Nilai         |
| notes          | Catatan       |

---

## Existing Column

Kolom lama tetap dipertahankan.

Misalnya:

- surah_id
- ayat_awal
- ayat_akhir

**Jangan dihapus.**

Status:

```text
Deprecated
```

Kolom lama hanya digunakan selama masa transisi.

---

# Phase 4 - Existing Data Mapping

Seluruh data lama dipetakan ke struktur baru.

| Existing     | New            |
| ------------ | -------------- |
| surah_id     | start_surah_id |
| surah_id     | end_surah_id   |
| ayat_awal    | start_ayah     |
| ayat_akhir   | end_ayah       |
| jumlah_baris | line_count     |

Field baru:

```text
entry_type
```

Default:

```text
HAFALAN_BARU
```

karena sistem lama belum mengenal kategori setoran.

---

# Phase 5 - Business Rule Migration

## Baseline

Tidak dilakukan migrasi otomatis.

Baseline **bukan** berasal dari histori transaksi.

Baseline hanya dapat dibuat melalui asesmen pengampu pada awal Tahun Ajaran.

Dengan demikian:

- Tidak ada script generate baseline.
- Tidak ada konversi dari report semester.
- Tidak ada asumsi berdasarkan total hafalan sebelumnya.

---

## Semester Snapshot

Semester yang telah selesai sebelum refactor:

- Tidak dibuat Snapshot.

Snapshot mulai digunakan sejak fitur Semester Closing tersedia.

---

# Phase 6 - Compatibility Layer

Backend harus mendukung dua skema selama masa transisi.

## Existing API

Tetap dapat membaca data lama.

## New API

Menggunakan struktur baru.

Backend bertanggung jawab melakukan mapping apabila masih ditemukan data lama.

Flutter tidak perlu mengetahui perbedaan struktur database.

---

# Phase 7 - Validation

Lakukan validasi berikut.

## Data Count

Jumlah transaksi sebelum migrasi

=

Jumlah transaksi setelah migrasi.

---

## Data Integrity

Pastikan:

- Tidak ada student_id berubah.
- Tidak ada transaksi hilang.
- Tidak ada foreign key rusak.
- Tidak ada duplicate record.

---

## Sampling

Ambil minimal:

- 20 Santri
- 20 Setoran
- 5 Halaqah

Pastikan:

- Surat sesuai.
- Ayat sesuai.
- Jumlah Baris sesuai.
- Data tampil normal.

---

# Phase 8 - Regression Testing

Pastikan fitur berikut tetap berjalan.

## Existing

- Target Hafalan
- Dashboard Tahfidz
- Input Setoran
- Report Semester
- Detail Setoran

## New

- Baseline
- Multi Surah
- Jenis Setoran
- Progress Semester
- Snapshot

---

# Phase 9 - Production Verification

Setelah deployment lakukan pengecekan.

Checklist:

- API berjalan.
- Dashboard normal.
- Data lama tampil.
- Data baru dapat ditambahkan.
- Tidak ada error SQL.
- Tidak ada data hilang.

---

# Phase 10 - Future Cleanup

**Tahap ini tidak dilakukan pada release pertama.**

Cleanup hanya dilakukan setelah:

- Backend menggunakan field baru sepenuhnya.
- Flutter production menggunakan API baru.
- Seluruh endpoint lama dinyatakan deprecated.

Baru setelah itu:

- Hapus kolom lama.
- Hapus mapping compatibility.
- Optimalkan query.

Tahap ini menjadi task terpisah pada release berikutnya.

---

# Rollback Plan

Jika terjadi kegagalan:

1. Aktifkan Maintenance Mode.
2. Restore database dari backup.
3. Deploy source code versi sebelumnya.
4. Verifikasi seluruh modul Tahfidz.
5. Matikan Maintenance Mode.

Rollback harus dapat dilakukan tanpa kehilangan data transaksi.

---

# Out of Scope

Task ini **tidak mencakup**:

- Perubahan UI Flutter.
- Perubahan desain Admin Panel.
- Pengisian Baseline oleh pengampu.
- Semester Closing.
- Perhitungan Progress Semester.
- Analitik Tahfidz.

Task ini hanya berfokus pada migrasi database dan kompatibilitas struktur data.

---

# Acceptance Criteria

## Database

- Seluruh tabel baru berhasil dibuat.
- Seluruh kolom baru berhasil ditambahkan.
- Tidak ada tabel lama yang dihapus.

---

## Data

- Seluruh histori setoran tetap tersedia.
- Mapping data lama berhasil dilakukan.
- Tidak ada data hilang.

---

## Compatibility

- Existing API tetap berjalan.
- Backend mendukung struktur lama dan baru selama masa transisi.
- Flutter lama tetap dapat digunakan sebelum proses upgrade.

---

## Production

- Migrasi dapat dilakukan tanpa downtime berkepanjangan.
- Rollback berhasil diuji.
- Sistem siap digunakan untuk implementasi fitur Tahfidz generasi baru.
