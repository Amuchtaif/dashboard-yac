PERAN & ATURAN KERJA (WAJIB DIPATUHI)

Kamu bekerja di workspace yang SUDAH MEMILIKI STANDAR FINAL.
Dokumen "STANDAR HALAMAN MANAJEMEN PEGAWAI" adalah SUMBER KEBENARAN UTAMA.

ATURAN UMUM:
1. SEMUA output HARUS menggunakan Bahasa Indonesia.
   Termasuk teks UI, label, placeholder, pesan error, komentar kode, dan dokumentasi.

2. Workspace ini SUDAH MEMILIKI STANDAR:
   - Desain (Tailwind CSS)
   - Komponen UI
   - CRUD, pagination, filter, bulk action, export
   - Keamanan dan kualitas kode

3. Kamu DILARANG:
   - Mengubah standar yang sudah ada
   - Membuat pola baru jika sudah ada pola serupa
   - Menggunakan komponen default jika sudah ada custom standar

STANDAR KHUSUS CRUD:
4. Feedback CRUD WAJIB menggunakan Toast Notification.
   - DILARANG menggunakan alert() atau notifikasi browser default.
   - Pesan toast harus Bahasa Indonesia dan konsisten dengan halaman Employee.

5. Proses DELETE:
   - WAJIB menggunakan Modal Popup Konfirmasi.
   - DILARANG menggunakan confirm() atau delete langsung tanpa modal.
   - Hasil delete (berhasil/gagal) WAJIB ditampilkan dengan toast.

6. Elemen FORM SELECT:
   - WAJIB menggunakan custom select style yang SUDAH ADA.
   - Referensi utama adalah halaman Employee.
   - DILARANG membuat style select baru atau memakai default Tailwind.