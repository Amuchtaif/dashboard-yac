# PRD - Activity Log & System Logging

Version: 1.0
Status: Draft
Priority: High

---

# 1. Latar Belakang

Dashboard YAC merupakan aplikasi terintegrasi yang terdiri dari berbagai modul seperti:

- PPDB
- Surat Digital
- Tahfidz
- Inventori
- Kepegawaian
- Absensi
- Pengaturan
- dan modul lainnya.

Saat ini seluruh aktivitas pengguna belum memiliki sistem pencatatan (Audit Trail) yang terpusat. Hal ini menyulitkan ketika terjadi:

- perubahan data yang tidak diketahui pelakunya
- kehilangan data
- kesalahan update
- penyalahgunaan akun
- debugging ketika sistem mengalami error
- investigasi keamanan

Oleh karena itu diperlukan sebuah sistem logging yang mampu mencatat seluruh aktivitas aplikasi baik pada level database maupun file log.

---

# 2. Tujuan

Membangun sistem logging terpusat yang mampu:

- mencatat seluruh aktivitas pengguna
- mencatat aktivitas sistem
- menyimpan log ke database
- menyimpan log ke file
- mempermudah audit
- mempermudah troubleshooting
- tetap dapat diakses ketika panel admin atau database mengalami gangguan.

---

# 3. Scope

## In Scope

✔ Activity Log

✔ Authentication Log

✔ Error Log

✔ Security Log

✔ API Log

✔ Scheduler Log

✔ Backup Log

✔ File Log

✔ Dashboard Log Viewer

---

## Out of Scope

Monitoring server

Realtime monitoring CPU

Realtime monitoring RAM

Monitoring nginx/apache

SIEM

Centralized Logging (ELK)

Cloud Logging

---

# 4. Arsitektur

```
                    User

                      │

              Melakukan Aktivitas

                      │

               Logger Service

      ┌───────────────┴───────────────┐

      ▼                               ▼

Database Activity Log           File Logging

activity_logs                  storage/logs/

      │                               │

      ▼                               ▼

Dashboard Viewer              SSH / FTP / File Manager

```

Semua proses logging harus melalui satu service agar konsisten.

---

# 5. Modul Logging

## 5.1 Activity Log

Mencatat aktivitas pengguna.

Contoh

- tambah data
- edit data
- hapus data
- approve
- reject
- upload
- download
- print
- export
- import

---

## 5.2 Authentication Log

Mencatat aktivitas login.

Contoh

- login sukses

- login gagal

- logout

- reset password

- ganti password

- token expired

---

## 5.3 Error Log

Mencatat seluruh exception.

Contoh

- PHP Exception

- Database Error

- API Error

- Validation Error

- File Upload Error

---

## 5.4 Security Log

Mencatat aktivitas keamanan.

Contoh

- login gagal berulang

- SQL Injection

- XSS

- upload file berbahaya

- akses menu tanpa izin

- perubahan role

---

## 5.5 API Log

Mencatat request API.

Contoh

POST /api/login

POST /api/student

GET /api/dashboard

---

## 5.6 Scheduler Log

Mencatat aktivitas scheduler.

Contoh

Backup Database

Auto Notification

Reminder

Cron Job

Firebase Notification

---

# 6. Struktur Folder

```
storage/

└── logs/

    ├── activity/

    ├── auth/

    ├── error/

    ├── security/

    ├── api/

    ├── scheduler/

    ├── backup/

    └── system/
```

---

# 7. Format Penamaan File

```
activity-YYYY-MM-DD.log

auth-YYYY-MM-DD.log

error-YYYY-MM-DD.log

security-YYYY-MM-DD.log

api-YYYY-MM-DD.log

scheduler-YYYY-MM-DD.log
```

Contoh

```
activity-2026-07-19.log

error-2026-07-19.log
```

---

# 8. Format Log

Disimpan menggunakan JSON Lines.

Satu aktivitas = satu baris.

Contoh

```json
{
  "datetime": "2026-07-19 08:12:15",
  "level": "INFO",
  "module": "PPDB",
  "action": "UPDATE",
  "user_id": 5,
  "user": "Andi",
  "role": "Admin PPDB",
  "table": "students",
  "record_id": 25,
  "description": "Mengubah data siswa",
  "ip": "192.168.1.12",
  "browser": "Chrome",
  "url": "/students/update/25"
}
```

---

# 9. Database

## activity_logs

| Field       | Type     |
| ----------- | -------- |
| id          | bigint   |
| user_id     | bigint   |
| user_name   | varchar  |
| role        | varchar  |
| module      | varchar  |
| action      | varchar  |
| table_name  | varchar  |
| record_id   | bigint   |
| description | text     |
| old_data    | json     |
| new_data    | json     |
| ip_address  | varchar  |
| browser     | varchar  |
| url         | varchar  |
| level       | varchar  |
| created_at  | datetime |

---

# 10. Level Log

```
INFO

NOTICE

WARNING

ERROR

CRITICAL

SECURITY
```

---

# 11. Jenis Aktivitas

## Authentication

LOGIN

LOGOUT

LOGIN_FAILED

RESET_PASSWORD

CHANGE_PASSWORD

---

## CRUD

CREATE

UPDATE

DELETE

RESTORE

ARCHIVE

---

## Approval

APPROVE

REJECT

VERIFY

UNVERIFY

---

## File

UPLOAD

DOWNLOAD

IMPORT

EXPORT

PRINT

---

## User

CREATE_USER

UPDATE_USER

DELETE_USER

CHANGE_ROLE

DISABLE_ACCOUNT

---

## System

BACKUP

RESTORE

CLEAR_CACHE

RUN_SCHEDULER

SEND_NOTIFICATION

---

# 12. Data yang Dicatat

Minimal

- User ID

- Nama User

- Role

- Modul

- Action

- Nama Tabel

- Record ID

- IP

- Browser

- URL

- Timestamp

- Deskripsi

---

Opsional

- Data Lama

- Data Baru

- Session ID

- Device

- Latitude

- Longitude

---

# 13. Dashboard Activity Log

Menu

```
System

└── Activity Log
```

Filter

- tanggal

- user

- role

- module

- level

- action

- keyword

---

Kolom

Tanggal

User

Role

Module

Action

Description

IP

Status

---

Detail

Menampilkan

- data sebelum

- data sesudah

- browser

- url

- device

- request id

---

# 14. Dashboard Log Viewer

Menu

```
System

└── System Logs

      Activity

      Error

      Security

      API

      Scheduler

      Backup
```

Viewer membaca langsung file log.

Tidak membaca database.

Harus tetap dapat digunakan ketika database bermasalah.

---

# 15. Rotasi Log

Rotasi otomatis.

Per Hari

atau

Per 10 MB

Retention

90 Hari

Log lama otomatis:

- dikompresi

- dipindahkan ke archive

---

# 16. Permission

Super Admin

✔ Semua

Administrator

✔ Activity

✔ Error

✔ Scheduler

Manager

✔ Activity

Developer

✔ Semua

Readonly

✔ Hanya lihat

---

# 17. Logger Service

Semua logging wajib menggunakan Logger Service.

Tidak diperbolehkan menulis file secara langsung dari controller.

Contoh

```
Logger::activity()

Logger::info()

Logger::warning()

Logger::error()

Logger::critical()

Logger::security()

Logger::api()

Logger::scheduler()
```

---

# 18. Notifikasi Critical (Future)

Jika level

CRITICAL

maka sistem dapat mengirim

- Telegram

- Discord

- Email

- WhatsApp Gateway

---

# 19. Integrasi Modul

Semua modul wajib menggunakan Logger Service.

Modul:

- PPDB

- Surat Digital

- Inventori

- Tahfidz

- Kepegawaian

- Absensi

- Penggajian

- Perizinan

- Dashboard

- Pengaturan

- API

- Scheduler

---

# 20. Non Functional Requirement

- Logging tidak boleh mengganggu performa aplikasi.

- Logging harus asynchronous jika memungkinkan.

- Error saat menulis log tidak boleh menyebabkan transaksi gagal.

- Logger wajib memiliki fallback.

- File log harus tetap dibuat walaupun database gagal.

- Database tetap disimpan apabila file log gagal dibuat.

- Semua log menggunakan timezone Asia/Jakarta.

- Log harus mudah diarsipkan.

- Tidak menyimpan password, token, OTP, atau data sensitif lainnya dalam bentuk plaintext.

---

# 21. Future Roadmap

## Phase 1

- Activity Log
- File Log
- Dashboard Viewer

## Phase 2

- Error Monitoring
- Security Monitoring
- API Monitoring

## Phase 3

- Realtime Log Streaming
- Live Dashboard
- Telegram Alert
- Discord Alert
- Email Alert

## Phase 4

- Elasticsearch Integration
- Kibana Dashboard
- Grafana Monitoring
- Log Analytics
- Audit Report Generator

---

# 22. Kesimpulan

Sistem Logging menjadi komponen inti Dashboard YAC sebagai mekanisme audit, keamanan, dan troubleshooting. Dengan menerapkan dual logging (Database + File Log), seluruh aktivitas pengguna dan sistem tetap terdokumentasi walaupun terjadi gangguan pada database maupun panel administrasi. Pendekatan ini meningkatkan transparansi, mempermudah investigasi, serta menjadi fondasi untuk pengembangan fitur monitoring dan analitik di masa mendatang.
