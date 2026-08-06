# TASK: Pengembangan Modul "Surat & Dokumen Digital"

## Latar Belakang

Tambahkan modul baru bernama **Surat & Dokumen Digital** ke dalam Dashboard YAC.

Modul ini BUKAN aplikasi terpisah, melainkan menjadi bagian dari Dashboard YAC dan menggunakan seluruh infrastruktur yang sudah ada, seperti:

- Authentication
- User Management
- Website Access (RBAC)
- Activity Log
- Notification
- File Storage
- Master Unit
- Master Jabatan
- Master Pegawai

Jangan membuat sistem permission baru. Seluruh hak akses wajib menggunakan modul Website Access (RBAC) yang sudah tersedia.

---

## Tujuan

Membangun sistem persuratan digital yang terintegrasi dengan seluruh modul Dashboard YAC sehingga seluruh surat resmi yayasan dapat dibuat, diajukan, disetujui, ditandatangani, diverifikasi, dan diarsipkan secara digital.

---

# Integrasi RBAC

Seluruh menu dan aksi wajib menggunakan Website Access (RBAC).

Tambahkan permission berikut:

document.view
document.create
document.edit
document.delete
document.submit
document.approve
document.reject
document.sign
document.download
document.print
document.verify
document.archive
document.restore
document.disposition
document.template.manage
document.report.view

Seluruh menu harus otomatis muncul atau disembunyikan sesuai permission user.

Jangan menggunakan pengecekan role secara hardcode.

---

# Struktur Menu

Surat & Dokumen Digital

├── Dashboard
├── Surat Keluar
├── Surat Masuk
├── Approval
├── Disposisi
├── Template Surat
├── Arsip Digital
├── Verifikasi Dokumen
├── Laporan
└── Pengaturan Template

---

# Dashboard

Tampilkan statistik

- Surat dibuat hari ini
- Surat menunggu approval
- Surat selesai
- Surat ditolak
- Surat keluar bulan ini
- Surat masuk bulan ini

Grafik

- Surat per bulan
- Surat per unit
- Surat berdasarkan jenis

Quick Action

- Buat Surat Baru
- Surat Masuk
- Approval
- Arsip

---

# Template Surat

Buat master template.

Contoh:

- Surat Tugas
- Surat Keterangan
- Surat Keputusan
- Surat Pengantar
- Surat Pemberitahuan
- Surat Undangan
- Surat Izin
- Surat Aktif Belajar
- Surat Kelulusan
- Surat Mutasi
- Surat Rekomendasi
- Berita Acara
- Memo Internal

Template menggunakan placeholder.

Contoh

{{nama}}

{{jabatan}}

{{unit}}

{{tanggal}}

{{nomor_surat}}

{{alamat}}

dan field dinamis lainnya.

---

# Nomor Surat Otomatis

Support format dinamis.

Contoh

001/BIDIK/VIII/2026

Format dapat diatur dari pengaturan.

Support

{nomor}

{unit}

{kode}

{bulan_romawi}

{bulan}

{tahun}

Counter dapat direset per bulan atau per tahun.

---

# Surat Keluar

Fitur

- Draft
- Submit
- Approval
- Generate PDF
- Download
- Print
- QR Verification
- Arsip

Status

Draft

Menunggu Approval

Disetujui

Ditolak

Selesai

Diarsipkan

---

# Surat Masuk

Input surat masuk

Upload file

Nomor surat

Pengirim

Tanggal

Perihal

Lampiran

Tujuan Unit

Status

Disposisi

---

# Workflow Approval

Workflow harus fleksibel.

Contoh

Guru

↓

Kepala Sekolah

↓

Bidang Pendidikan

↓

Ketua Yayasan

↓

Selesai

Workflow dapat berbeda berdasarkan jenis surat.

---

# Disposisi

Support

Forward

Catatan

Deadline

Status

Riwayat disposisi

---

# Digital Signature

Tahap pertama

Upload tanda tangan PNG.

Tahap berikutnya

Support TTE tersertifikasi.

---

# QR Verification

Setiap dokumen memiliki QR.

Ketika QR dipindai tampilkan

Nomor Surat

Tanggal

Status

Penandatangan

Hash Dokumen

Valid / Tidak Valid

---

# Arsip Digital

Filter

Jenis Surat

Unit

Tanggal

Pembuat

Penandatangan

Status

Nomor Surat

Keyword

Preview PDF langsung.

---

# Laporan

Laporan

Surat per bulan

Surat per unit

Surat per jenis

Approval tercepat

Approval terlambat

Jumlah surat per user

Export

Excel

PDF

CSV

---

# Integrasi Activity Log

Seluruh aktivitas wajib masuk ke Activity Log yang sudah ada.

Contoh

Membuat surat

Mengubah surat

Menghapus draft

Mengirim approval

Approve

Reject

Disposisi

Download PDF

Print

Generate QR

Verifikasi QR

Archive

Restore

Jangan membuat sistem log baru.

---

# Integrasi Modul Dashboard

Modul Surat & Dokumen Digital harus dapat digunakan oleh seluruh modul.

Contoh

PPDB

Generate Surat Penerimaan

Generate Surat Penolakan

Generate Surat Daftar Ulang

Kepegawaian

Generate SK

Generate Surat Tugas

Generate Surat Cuti

Generate Surat Peringatan

Akademik

Generate Surat Aktif Belajar

Generate Surat Kelulusan

Generate Surat Mutasi

Tahfidz

Generate Sertifikat

Generate Surat Kelulusan Tahfidz

Inventori

Generate Berita Acara

Generate Surat Peminjaman

Keuangan

Generate Invoice

Generate Kuitansi

Generate Surat Permohonan Anggaran

---

# Notification

Gunakan sistem notifikasi yang sudah ada.

Approval baru

Approval ditolak

Approval selesai

Disposisi baru

Surat selesai dibuat

---

# UI/UX

Gunakan style modern minimalis mengikuti Dashboard YAC.

Gunakan DataTable.

Support dark mode apabila dashboard sudah mendukung.

Responsive desktop dan mobile.

---

# Target Arsitektur

Modul ini menjadi bagian dari Dashboard YAC.

Tidak membuat login baru.

Tidak membuat sistem user baru.

Tidak membuat sistem RBAC baru.

Tidak membuat Activity Log baru.

Seluruh data harus terintegrasi dengan sistem yang sudah ada sehingga Surat & Dokumen Digital menjadi layanan persuratan terpusat bagi seluruh modul Dashboard YAC.

---

# Catatan Pengembangan

- Gunakan arsitektur modular agar mudah dikembangkan.
- Seluruh fitur mengikuti prinsip reusable service.
- Hindari hardcode role, unit, maupun workflow.
- Semua konfigurasi (template, workflow, penomoran, dan permission) harus dapat dikelola melalui panel admin.
- Pastikan setiap dokumen memiliki jejak audit (audit trail) melalui Activity Log yang sudah tersedia.
