PRD --- Agenda Pendidikan YAC dari Kalender Akademik + Public URL

Produk: Dashboard YAC / Admin PortalModul Utama: Kalender AkademikFitur: Agenda Pendidikan, Import Data, dan Public AgendaStatus: Draft untuk DevelopmentTanggal: 8 Agustus 2026Prioritas: High

1. Ringkasan

Dibuat halaman khusus Agenda Pendidikan yang terpisah dari DashboardUtama.

Halaman ini menampilkan agenda Bidang Pendidikan dan seluruh unitpendidikan dengan mengambil data langsung dari halaman/data KalenderAkademik yang sudah ada.

Tidak dibuat tabel baru untuk agenda.

Selain itu, halaman Kalender Akademik ditambahkan:

Fitur Import Data.

Pengaturan Public Agenda.

Link untuk membuka halaman Agenda Pendidikan tanpa login.

Public Agenda tidak menggunakan sistem keamanan token khusus. URLcukup menggunakan route publik yang jelas dan stabil.

2. Prinsip Utama

Single Source of Truth

Kalender Akademik adalah sumber seluruh data agenda.

                  KALENDER AKADEMIK
                  Existing Data
                       │
          ┌────────────┼────────────┐
          │            │            │
          ▼            ▼            ▼
       Kalender      Agenda       Agenda
       Bulanan       Bidang        Unit
          │        Pendidikan    Pendidikan
          │            │            │
          └────────────┼────────────┘
                       ▼
                  Coming Soon
                       │
                       ▼
                Public Agenda

Tidak membuat tabel:

agendas
agenda_units
public_agendas
public_agenda_tokens

Semua tampilan mengambil data dari Kalender Akademik existing.

3. Tujuan

Menjadikan Kalender Akademik sebagai pusat seluruh agendapendidikan.

Memudahkan Admin melakukan input dan import agenda.

Menampilkan agenda Bidang Pendidikan secara terpisah.

Menampilkan agenda setiap unit pendidikan.

Menampilkan kegiatan yang akan datang melalui Coming Soon.

Menyediakan halaman Agenda Pendidikan yang dapat dibuka tanpa login.

Menghindari duplikasi data agenda.

4. Halaman yang Terlibat

A. Kalender Akademik

Halaman existing yang menjadi pusat pengelolaan data.

Tambahkan:

[ Import Data ] [ Public Agenda ]

Admin tetap mengelola agenda dari halaman ini.

B. Agenda Pendidikan

Halaman baru untuk display/read-only.

Tidak ada CRUD agenda di halaman ini.

Data sepenuhnya berasal dari Kalender Akademik.

5. Navigasi

Menu Admin tetap:

MANAJEMEN AKADEMIK

Tahun Ajaran
Kalender Akademik
Data Guru
Data Siswa
Data Siswa (Non-Aktif)
Kenaikan Kelas
Unit Pendidikan
Data Kelas
Jam Pelajaran

Tidak perlu menambahkan menu Admin baru untuk Agenda Pendidikan jikahalaman tersebut dapat dibuka melalui tombol/link dari KalenderAkademik.

Public Agenda memiliki URL sendiri.

Contoh:

https://domain-yac.id/agenda-pendidikan

6. Perubahan Halaman Kalender Akademik

Pada bagian header/action bar tambahkan:

[ Import Data ] [ Lihat Agenda Publik ↗ ]

Contoh:

┌─────────────────────────────────────────────────────────────────────┐
│ Agustus 2026 │
│ │
│ [ Import Data ] [ Agenda Publik ↗ ] │
├─────────────────────────────────────────────────────────────────────┤
│ │
│ Kalender Akademik │
│ │
└─────────────────────────────────────────────────────────────────────┘

Tombol Agenda Publik membuka halaman:

/agenda-pendidikan

tanpa membutuhkan login.

7. Public URL

Public Agenda menggunakan route sederhana.

Rekomendasi:

/agenda-pendidikan

Contoh:

https://domain-yac.id/agenda-pendidikan

Tidak perlu:

bearer token,

token hash,

token database,

revoke token,

regenerate token,

expiration token,

tabel public token.

URL bersifat publik dan dapat dibagikan secara langsung.

8. Public Page

Halaman /agenda-pendidikan merupakan halaman read-only.

Tidak membutuhkan:

Login.

Session admin.

Role admin.

Permission CRUD.

Public hanya dapat melihat data agenda yang memang ditampilkan untukpublik berdasarkan aturan existing Kalender Akademik.

9. Struktur Public Agenda

Header

Agenda Pendidikan

Pusat informasi kegiatan Bidang Pendidikan
dan seluruh unit pendidikan YAC

Filter:

[ Tahun Akademik ]
[ Semester ]
[ Sumber Agenda ]
[ Kategori ]

10. Layout Public Agenda

┌─────────────────────────────────────────────────────────────────────┐
│ AGENDA PENDIDIKAN │
│ Pusat informasi kegiatan Bidang Pendidikan dan seluruh unit YAC │
│ │
│ [2026/2027] [Ganjil] [Semua] [Semua] │
└─────────────────────────────────────────────────────────────────────┘

┌───────────────────────────────────┬─────────────────────────────────┐
│ │ │
│ Kalender Bulanan │ Agenda Bidang Pendidikan │
│ │ │
│ Agustus 2026 │ Agenda Terdekat │
│ │ │
│ Month View │ ● Rapat Kurikulum │
│ │ ● Pembinaan Guru │
│ ● ● ● indikator agenda │ ● Evaluasi Akademik │
│ │ │
└───────────────────────────────────┴─────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│ Kegiatan Unit Pendidikan │
│ │
│ [Semua] [MTs] [MA] [SD] [TK] [Lainnya] │
│ │
│ [ MTs ] [ MA ] [ SD ] [ TK ] │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│ Coming Soon │
│ │
│ 12 AGU ─── 17 AGU ─── 25 AGU ─── 02 SEP ─── 10 SEP │
│ │
│ Rapat HUT RI Maulid Workshop Evaluasi │
└─────────────────────────────────────────────────────────────────────┘

11. Kalender Bulanan

Gunakan tampilan Month View.

Fitur:

Bulan sebelumnya.

Bulan berikutnya.

Tombol Hari Ini.

Highlight hari ini.

Indikator agenda.

Multiple agenda dalam satu tanggal.

Klik tanggal untuk melihat agenda.

Agenda multi-hari.

Kategori warna tetap mengikuti kategori Kalender Akademik existing.

Contoh:

● Libur Nasional
● Libur Sekolah
● Cuti Bersama
● Rapat
● Kegiatan Yayasan
● Kegiatan Bidang Pendidikan
● Kegiatan Unit

12. Agenda Bidang Pendidikan

Section di sebelah kanan kalender.

Data diambil dari Kalender Akademik berdasarkan sumber:

Bidang Pendidikan

Menampilkan agenda pada bulan aktif.

Informasi:

Nama kegiatan.

Tanggal.

Waktu.

Lokasi.

Kategori.

Contoh:

09.00 12 AGU
Rapat Kurikulum
Ruang Bidang Pendidikan

08.00 15 AGU
Pembinaan Guru
Aula Yayasan

13.00 20 AGU
Evaluasi Akademik
Ruang Rapat

13. Kegiatan Unit Pendidikan

Section full width.

Filter:

[ Semua Unit ]
[ MTs ]
[ MA ]
[ SD ]
[ TK ]
[ Lainnya ]

Data berasal dari Kalender Akademik existing.

Contoh:

MTs Assunnah

● Rapat Wali Kelas 10 Agustus
● Class Meeting 18 Agustus
● Simulasi ANBK 26 Agustus

Lihat Semua →

Tidak ada input agenda di section ini.

14. Coming Soon

Coming Soon merupakan hasil filter data Kalender Akademik.

Tidak membuat data khusus.

Logika:

tanggal_kegiatan > tanggal_hari_ini
ORDER BY tanggal_kegiatan ASC

Tampilkan beberapa agenda terdekat.

Visual:

12 AGU ─── 17 AGU ─── 25 AGU ─── 02 SEP ─── 10 SEP
│ │ │ │ │
Rapat HUT RI Maulid Workshop Evaluasi

15. Import Data

Fitur Import Data berada di halaman Kalender Akademik.

Tombol:

[ Import Data ]

Tujuannya untuk memasukkan banyak data kegiatan sekaligus.

Data hasil import langsung masuk ke struktur data Kalender Akademikexisting.

16. Format Import

Format yang direkomendasikan:

CSV.

XLSX.

Tersedia:

[ Download Template ]
[ Upload File ]

Contoh struktur:

Nama Kegiatan
Tanggal Mulai
Tanggal Selesai
Kategori
Sumber Agenda
Unit
Keterangan
Lokasi

Kolom final harus mengikuti field existing pada Kalender Akademik.

Developer wajib melakukan mapping terlebih dahulu.

Jangan mengubah struktur database hanya untuk menyesuaikan templateimport.

17. Flow Import

Klik Import Data
↓
Upload CSV/XLSX
↓
Validasi File
↓
Preview Data
↓
Validasi Setiap Baris
↓
Tampilkan Error
↓
Admin Konfirmasi
↓
Insert ke Kalender Akademik Existing
↓
Import Selesai

18. Preview Import

Contoh:

Preview Import

┌────────────┬────────────┬────────────┬────────────┬──────────┐
│ Kegiatan │ Mulai │ Selesai │ Kategori │ Status │
├────────────┼────────────┼────────────┼────────────┼──────────┤
│ Rapat Guru │ 10/08/26 │ 10/08/26 │ Rapat │ ✓ Valid │
│ Workshop │ 15/08/26 │ 16/08/26 │ Workshop │ ✓ Valid │
│ ... │ ... │ ... │ ... │ ⚠ Error │
└────────────┴────────────┴────────────┴────────────┴──────────┘

[ Batal ] [ Import Data ]

19. Validasi Import

Minimal:

Format file benar.

Kolom wajib tersedia.

Tanggal valid.

Tanggal selesai tidak sebelum tanggal mulai.

Kategori valid.

Sumber/unit valid.

Data duplikat terdeteksi.

File tidak melebihi batas ukuran.

Data kosong ditolak.

Jika ada error:

25 data valid
3 data error

Tampilkan nomor baris dan alasan error.

20. Duplikasi Data

Karena data masuk ke Kalender Akademik existing, import perlu mendeteksiduplikat.

Kriteria mengikuti struktur data existing.

Contoh kandidat:

Nama Kegiatan
Tanggal Mulai
Tanggal Selesai
Sumber/Unit

Default:

Data yang terdeteksi sebagai duplikat dilewati.

Tidak boleh membuat data duplikat secara diam-diam.

21. RBAC

Gunakan RBAC existing.

Super Admin

Kelola semua Kalender Akademik.

Import data.

Melihat Public Agenda.

Admin Bidang Pendidikan

Kelola agenda sesuai permission.

Import jika diberi permission.

Melihat seluruh agenda.

Admin Unit

Kelola agenda unit sendiri.

Melihat agenda Bidang Pendidikan sesuai permission.

Public

Read-only.

Tanpa login.

22. Permission

Jika permission existing membutuhkan penambahan:

academic_calendar.view
academic_calendar.create
academic_calendar.update
academic_calendar.delete
academic_calendar.import
academic_calendar.public

Tidak perlu membuat role baru.

23. Log Aktivitas

Gunakan Log Aktivitas existing.

Aktivitas yang dicatat:

AGENDA_IMPORTED
AGENDA_CREATED
AGENDA_UPDATED
AGENDA_DELETED
PUBLIC_AGENDA_ACCESSED

Tidak membuat tabel log baru.

24. Cache

Jika sistem existing menggunakan cache, public agenda dapat menggunakancache dari query Kalender Akademik.

Cache harus diperbarui ketika:

Data agenda ditambah.

Data agenda diubah.

Data agenda dihapus.

Status publikasi berubah.

Tidak perlu tabel cache baru.

25. Keamanan Public Page

Tidak ada mekanisme keamanan token khusus.

Namun endpoint public tetap harus:

Menggunakan HTTPS.

Read-only.

Tidak memiliki endpoint CRUD.

Tidak menampilkan data internal yang tidak ditujukan untuk publik.

Tidak membuka akses Admin Portal.

Menggunakan query data yang memang ditetapkan untuk public.

Menggunakan noindex jika halaman hanya dimaksudkan untuk aksesmelalui link.

Contoh:

<meta name="robots" content="noindex, nofollow, noarchive">

Catatan: karena URL bersifat publik tanpa token, siapa pun yangmengetahui URL dapat membuka halaman tersebut. Ini memang konsekuensidesain yang dipilih dan harus dianggap sebagai bagian dari requirement.

26. Data Public

Public page hanya mengambil data Kalender Akademik yang sesuai aturanpublikasi existing.

Jika Kalender Akademik sudah memiliki field/status:

public
internal

gunakan field tersebut.

Jika belum ada, gunakan mekanisme existing yang tersedia.

Tidak membuat tabel khusus public agenda.

27. Responsive

Desktop

Kalender Agenda Bidang
50% 50%

Tablet

Kalender
Agenda Bidang

Mobile

Filter
Kalender
Agenda Bidang
Kegiatan Unit
Coming Soon

28. Acceptance Criteria

Kalender Akademik

Tidak ada tabel baru untuk agenda.

Kalender Akademik tetap menjadi sumber data utama.

Import Data tersedia.

Template import tersedia.

CSV/XLSX dapat diproses.

Preview import tersedia.

Validasi baris berjalan.

Duplikat ditangani.

Data valid masuk ke Kalender Akademik existing.

Import tercatat pada Log Aktivitas existing.

Public Agenda

Public Agenda dapat dibuka dari halaman Kalender Akademik.

Public Agenda memiliki URL khusus.

Tidak membutuhkan login.

Tidak menggunakan token keamanan khusus.

Tidak ada CRUD pada public page.

Kalender bulanan tampil.

Agenda Bidang Pendidikan tampil.

Agenda Unit tampil.

Coming Soon tampil.

Filter Tahun Akademik berjalan.

Filter Semester berjalan.

Filter Sumber Agenda berjalan.

Filter Kategori berjalan.

Data berasal dari Kalender Akademik existing.

29. Definition of Done

Task dianggap selesai apabila:

Tidak ada tabel agenda baru.

Kalender Akademik menjadi Single Source of Truth.

Import data tersedia pada halaman Kalender Akademik.

Hasil import masuk ke data Kalender Akademik existing.

Public Agenda dapat dibuka dari tombol pada Kalender Akademik.

Public Agenda menggunakan URL sederhana, misalnya/agenda-pendidikan.

Public Agenda tidak membutuhkan login.

Tidak ada sistem token khusus.

Public Agenda menampilkan kalender, agenda Bidang Pendidikan, agendaunit, dan Coming Soon.

Semua data berasal dari Kalender Akademik existing.

Perubahan pada Kalender Akademik otomatis tercermin di PublicAgenda.

Tidak ada duplikasi data agenda.

RBAC existing tetap digunakan untuk pengelolaan data.

Log Aktivitas existing digunakan.

Tampilan responsive.

30. Catatan Implementasi Developer

Sebelum melakukan perubahan database:

Periksa tabel Kalender Akademik existing.

Periksa model/controller/service existing.

Periksa field kategori.

Periksa field Tahun Akademik.

Periksa field semester.

Periksa relasi Unit Pendidikan.

Periksa mekanisme status/public visibility.

Periksa RBAC existing.

Periksa Log Aktivitas existing.

Periksa mekanisme import yang sudah tersedia di sistem.

Prioritas utama adalah reuse existing system.

Jangan membuat migration baru jika kebutuhan dapat dipenuhi denganstruktur Kalender Akademik yang sudah ada.

Public Agenda hanya merupakan read-only presentation layer dari dataKalender Akademik.

31. Ringkasan Arsitektur Final

┌──────────────────────────────────────┐
│ KALENDER AKADEMIK │
│ EXISTING DATA │
│ │
│ CRUD + Import + Filter │
└──────────────────┬───────────────────┘
│
│
┌──────────┼──────────┐
│ │ │
▼ ▼ ▼
Calendar Agenda Agenda
Bulanan Bidang Unit
│ Pendidikan Pendidikan
│ │ │
└──────────┼──────────┘
▼
Coming Soon
│
▼
┌─────────────────────┐
│ PUBLIC AGENDA │
│ /agenda-pendidikan │
│ │
│ Read Only │
│ No Login │
│ No Token │
└─────────────────────┘

Intinya: Kalender Akademik tetap menjadi mesin datanya, AgendaPendidikan hanya menjadi tampilan baru, Import menjadi cara cepatmemasukkan data, dan /agenda-pendidikan menjadi pintu publiknya.Tidak perlu membangun kerajaan tabel baru hanya untuk memajang kalender.
