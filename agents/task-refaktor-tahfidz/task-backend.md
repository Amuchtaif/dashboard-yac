# TASK_BACKEND.md

# Refactor Backend Modul Tahfidz Dashboard YAC

## Priority

**High**

## Type

Major Refactor (Backward Compatible API)

---

# Background

Modul Tahfidz saat ini telah memiliki fitur:

- Target Hafalan
- Input Setoran Harian
- Report Semester

Namun setelah evaluasi implementasi di lapangan ditemukan beberapa keterbatasan yang menyebabkan proses akademik kurang akurat.

Permasalahan utama:

- Tidak terdapat konsep **Baseline Hafalan Awal Tahun**.
- Setoran hanya mendukung satu surat dalam satu transaksi.
- Belum ada kategori setoran.
- Report Semester belum memperhitungkan hafalan awal santri.
- Belum terdapat Snapshot Semester sehingga laporan dapat berubah ketika transaksi lama diedit.

Refactor ini harus tetap mempertahankan kompatibilitas dengan sistem yang sudah berjalan serta meminimalkan perubahan pada modul lain.

---

# Objectives

Backend harus mampu:

- Menyimpan baseline hafalan awal setiap Tahun Ajaran.
- Mendukung setoran lintas surat.
- Memisahkan kategori setoran.
- Menghasilkan progress semester secara otomatis.
- Menghasilkan laporan semester yang akurat.
- Menyimpan Snapshot Semester.
- Menyediakan REST API yang siap digunakan Flutter.

---

# Business Flow

```text
Academic Year
        │
        ▼
Baseline Hafalan
        │
        ▼
Target Hafalan
(Unit + Kelas + Semester)
        │
        ▼
Input Setoran
        │
        ▼
Progress Semester
        │
        ▼
Snapshot Semester
        │
        ▼
Report Semester
```

---

# Database Changes

## 1. New Table

## memorization_baselines

Digunakan untuk menyimpan hafalan awal santri pada awal Tahun Ajaran.

Field minimum:

| Field            | Type          |
| ---------------- | ------------- |
| id               | bigint        |
| academic_year_id | FK            |
| student_id       | FK            |
| baseline_juz     | decimal(5,2)  |
| notes            | text nullable |
| created_at       | datetime      |
| updated_at       | datetime      |

Rules:

- unique(academic_year_id, student_id)

---

## 2. New Table

## semester_snapshots

Digunakan untuk menyimpan hasil akhir semester.

Field minimum

| Field               |
| ------------------- |
| id                  |
| academic_year_id    |
| semester            |
| student_id          |
| baseline_juz        |
| target_juz          |
| memorized_juz       |
| total_juz           |
| murojaah_total      |
| tasmi_score         |
| progress_percentage |
| notes               |
| generated_at        |

Snapshot bersifat read-only.

---

## 3. Refactor Existing Table

Refactor tabel:

```text
memorization_entries
```

Tambahkan kolom berikut.

| Field          |
| -------------- |
| entry_type     |
| start_surah_id |
| start_ayah     |
| end_surah_id   |
| end_ayah       |
| line_count     |
| score          |
| notes          |

Kolom lama:

```text
surah_id
start_ayah
end_ayah
```

tetap dipertahankan sementara selama proses migrasi.

Setelah migrasi selesai, dapat dilakukan deprecate sesuai roadmap.

---

# Entry Type

Gunakan Enum atau Master Table.

Nilai minimum:

```text
HAFALAN_BARU
MUROJAAH
TASMI
UJIAN
```

Business Rule:

## Hafalan Baru

- Menambah progres hafalan.
- Masuk laporan target semester.

---

## Murojaah

- Tidak menambah progres hafalan.
- Masuk laporan murojaah.

---

## Tasmi'

- Tidak menambah progres hafalan.
- Digunakan untuk nilai tasmi'.

---

## Ujian

- Digunakan pada ujian tahfidz.
- Tidak mempengaruhi target semester.

---

# Baseline Rules

- Satu santri hanya boleh memiliki satu baseline pada satu Tahun Ajaran.
- Baseline hanya boleh dibuat pada Tahun Ajaran aktif.
- Baseline tidak dihitung sebagai transaksi setoran.
- Baseline menjadi dasar seluruh perhitungan progress.

---

# Progress Calculation

Gunakan rumus berikut.

```text
Total Hafalan Saat Ini

=

Baseline

+

Akumulasi Hafalan Baru
```

Contoh

Baseline

```text
4 Juz
```

Setoran Baru

```text
0.80 Juz
```

Total

```text
4.80 Juz
```

---

# Target Semester

Target Hafalan existing **tidak diubah**.

Progress dihitung:

```text
Progress Semester

=

Hafalan Baru Semester

/

Target Semester
```

Bukan:

```text
Total Hafalan

/

Target
```

---

# Report Semester

Report Semester harus menghasilkan data berikut.

| Data              |
| ----------------- |
| Baseline Awal     |
| Target Semester   |
| Hafalan Baru      |
| Total Hafalan     |
| Persentase Target |
| Total Murojaah    |
| Total Setoran     |
| Nilai Tasmi       |
| Catatan           |

Report tidak mengambil data Snapshot.

Snapshot hanya digunakan setelah semester ditutup.

---

# Semester Closing

Tambahkan service baru:

```text
Semester Closing
```

Ketika dijalankan sistem harus:

- Mengambil seluruh transaksi semester.
- Menghitung progress.
- Menghitung total hafalan.
- Menghasilkan Snapshot.
- Mengunci Snapshot.

Snapshot tidak boleh berubah walaupun transaksi lama diperbaiki.

---

# API

## Baseline

```
GET     /api/tahfidz/baselines

GET     /api/tahfidz/baselines/{id}

POST    /api/tahfidz/baselines

PUT     /api/tahfidz/baselines/{id}

DELETE  /api/tahfidz/baselines/{id}
```

---

## Setoran

```
GET     /api/tahfidz/entries

GET     /api/tahfidz/entries/{id}

POST    /api/tahfidz/entries

PUT     /api/tahfidz/entries/{id}

DELETE  /api/tahfidz/entries/{id}
```

---

## Dashboard

```
GET /api/tahfidz/dashboard
```

Response minimal:

- Total Hafalan Baru
- Total Murojaah
- Total Tasmi'
- Total Ujian
- Target Semester
- Progress Semester

---

## Report Semester

```
GET /api/tahfidz/report-semester
```

Support filter:

- Tahun Ajaran
- Semester
- Unit
- Kelas
- Halaqah
- Santri

---

## Semester Closing

```
POST /api/tahfidz/semester/close
```

---

## Snapshot

```
GET /api/tahfidz/semester/{id}/snapshot
```

---

# Validation Rules

## Baseline

- Tidak boleh duplikat.
- Academic Year wajib aktif.
- Student wajib aktif.

---

## Setoran

- Surah Awal wajib.
- Ayat Awal wajib.
- Surah Akhir wajib.
- Ayat Akhir wajib.
- Entry Type wajib.
- Line Count minimal 1.

Validasi tambahan:

- Surah Awal tidak boleh berada setelah Surah Akhir.
- Jika Surah Awal = Surah Akhir maka Ayat Awal tidak boleh lebih besar dari Ayat Akhir.
- Rentang hafalan harus valid berdasarkan master data Al-Qur'an.

---

# Service Layer

Tambahkan service baru.

```
BaselineService

MemorizationService

ProgressService

SemesterReportService

SemesterClosingService

SnapshotService
```

Business Logic tidak boleh berada di Controller.

---

# Performance

Optimalkan query menggunakan:

- eager loading
- pagination
- aggregate query

Hindari perhitungan progress menggunakan loop yang berulang untuk setiap santri.

Gunakan query agregasi atau cache bila diperlukan.

---

# Logging

Catat aktivitas berikut:

- Baseline dibuat.
- Baseline diubah.
- Setoran dibuat.
- Setoran diubah.
- Semester Closing dijalankan.
- Snapshot dibuat.

---

# Security

- Seluruh endpoint harus menggunakan autentikasi.
- Gunakan Role Permission yang sudah ada.
- Semester Closing hanya dapat dilakukan oleh Admin Tahfidz atau Kepala Tahfidz.
- Snapshot tidak dapat diubah melalui endpoint mana pun.

---

# Acceptance Criteria

## Database

- Tabel Baseline berhasil dibuat.
- Tabel Snapshot berhasil dibuat.
- Tabel Setoran berhasil direfactor tanpa menghilangkan kompatibilitas data lama.

---

## Business

- Baseline menjadi dasar seluruh progres.
- Hafalan Baru menambah progres.
- Murojaah tidak menambah progres.
- Tasmi' tidak menambah progres.
- Ujian tidak menambah progres.

---

## API

- Seluruh endpoint berjalan sesuai spesifikasi.
- Response menggunakan format JSON yang konsisten.
- Mendukung pagination, filtering, dan sorting.

---

## Semester

- Semester Closing menghasilkan Snapshot.
- Snapshot tidak berubah setelah dibuat.
- Report Semester sesuai dengan hasil perhitungan business rule.

---

## Compatibility

- Existing Target Hafalan tetap dapat digunakan tanpa perubahan struktur.
- Existing Report Semester tetap dapat berjalan dengan penyesuaian data dari Baseline.
- Seluruh endpoint tetap kompatibel dengan integrasi Flutter setelah dilakukan penyesuaian pada kontrak API.
