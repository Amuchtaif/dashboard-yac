# STANDAR DESAIN FORMULIR DASHBOARD YAC
**Master Form Design System & Guidelines**  
*Patokan Desain Resmi untuk Seluruh Formulir Sistem Dashboard Yayasan Assunnah Cirebon (YAC)*  
*Referensi Utama: `views/employees/form.php`*

---

## 1. Filosofi Desain (Anti "AI Slop")

Prinsip dasar perancangan antarmuka formulir di seluruh Dashboard YAC adalah **Enterprise Ergonomics, Kerapian Visual, dan Konsistensi Fungsional**.

### Prinsip Utama:
1. **Bebas AI Slop**:
   - ❌ **Dilarang**: Gradien pelangi sembrono, glow efek berlebih, kartu transparan melayang (glassmorphism berlebihan), ikon animasi memantul (`bounce`/`ping` yang tidak relevan), atau input field yang dijejali hiasan tak berguna.
   - ❌ **Dilarang**: Heading berteriak huruf kapital penuh (`UPPERCASE`) pada judul section.
   - ❌ **Dilarang**: Box unggah file raksasa dengan ikon download untuk upload.
   - ✅ **Gunakan**: Tata letak yang tenang, terstruktur, kontras tegas, padding proporsional, dan penataan kolom yang rapi serta mudah dipahami oleh staf/admin/guru.
2. **Palet Warna Terkurasi**:
   - **Netral**: Slate family (`slate-50`, `slate-100`, `slate-200`, `slate-300`, `slate-500`, `slate-700`, `slate-800`, `slate-900`).
   - **Brand Accent Utama**: Cyan (`cyan-600` untuk tombol & fokus, `cyan-700` untuk hover).
   - **Status & Umpan Balik**:
     - Error: Red (`red-50`, `red-200`, `red-500`, `red-700`).
     - Sukses: Emerald (`emerald-50`, `emerald-200`, `emerald-600`).
     - Peringatan: Amber (`amber-50`, `amber-200`, `amber-700`).
3. **Ergonomi Data Entry**:
   - Indikator wajib isi (`*`) berwarna merah `text-red-500` yang konsisten.
   - Fokus keyboard jelas (`focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600`).
   - Dropdown konsisten menggunakan standardisasi `hybrid-select`.

---

## 2. Struktur Anatomi Halaman Form

Setiap halaman formulir harus mengikuti hierarki pembungkus berikut:

```
[ Layout Header (views/layouts/header.php) ]
└── Container Utama (w-full pb-12)
    ├── Header Halaman (mb-6)
    │   ├── Navigasi Breadcrumb (text-xs text-slate-500 mb-3)
    │   ├── Judul Halaman H1 (text-2xl font-bold text-slate-900 tracking-tight)
    │   └── Deskripsi / Subtitle (text-slate-500 text-sm mt-1)
    ├── Alert / Notifikasi Error Server (bg-red-50 border-red-200 mb-6)
    └── Form Tag (space-y-6)
        └── Kartu Induk / Master Card (bg-white rounded-xl shadow-sm border border-slate-200 divide-y divide-slate-200)
            ├── Section 1: Informasi Dasar / Pribadi (p-6 sm:p-8)
            │   ├── Section Header (h2 text-base font-semibold text-slate-900 + p text-xs text-slate-500)
            │   ├── [Opsional] Foto / Avatar Banner (p-4 rounded-xl bg-slate-50 border border-slate-200/80 mb-6)
            │   └── Grid Kolom Input (grid grid-cols-1 md:grid-cols-2 gap-5 sm:gap-6)
            ├── Section 2: Rincian Penempatan / Spesifik (p-6 sm:p-8)
            │   ├── Section Header
            │   └── Grid Kolom Input
            └── Footer Aksi Kartu (px-6 sm:px-8 py-4 bg-slate-50 flex items-center justify-end gap-3 rounded-b-xl)
                ├── Tombol Batal / Sekunder (bg-white border-slate-300 text-slate-700)
                └── Tombol Simpan / Primer (bg-cyan-600 hover:bg-cyan-700 text-white)
[ Layout Footer (views/layouts/footer.php) ]
```

---

## 3. Komponen Spesifik & Kode Standar

### 3.1. Pembungkus & Header Halaman

```html
<div class="w-full pb-12">
    <!-- Header Block -->
    <div class="mb-6">
        <!-- Breadcrumb -->
        <nav class="flex mb-3" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-2 text-xs text-slate-500">
                <li>
                    <a href="<?php url('views/dashboard/index.php'); ?>" class="hover:text-slate-800 transition-colors">Beranda</a>
                </li>
                <li>
                    <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 mx-1"></i>
                    <a href="<?php url('views/modul/index.php'); ?>" class="hover:text-slate-800 transition-colors">Nama Modul</a>
                </li>
                <li aria-current="page">
                    <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 mx-1"></i>
                    <span class="text-slate-800 font-medium"><?php echo $is_edit ? "Edit Data" : "Tambah Baru"; ?></span>
                </li>
            </ol>
        </nav>

        <!-- Judul & Deskripsi Halaman -->
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">
            <?php echo $is_edit ? "Edit Data Modul" : "Tambah Data Baru"; ?>
        </h1>
        <p class="text-slate-500 text-sm mt-1">
            <?php echo $is_edit ? "Perbarui informasi dan rincian data." : "Lengkapi formulir di bawah ini untuk mendaftarkan data baru ke dalam sistem."; ?>
        </p>
    </div>

    <!-- Alert Notifikasi Error -->
    <?php if (isset($_GET['error'])): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 text-sm flex items-center gap-2.5">
            <i class="fa-solid fa-circle-exclamation text-red-500 flex-shrink-0"></i>
            <span><?php echo htmlspecialchars($_GET['error']); ?></span>
        </div>
    <?php endif; ?>
```

---

### 3.2. Kartu Induk (Master Card Container)

Gunakan utility `divide-y divide-slate-200` pada kartu induk agar setiap section dan footer terpisahkan garis pembatas halus secara otomatis tanpa perlu tag `<hr>` manual.

```html
    <form action="<?php url('logic/...'); ?>" method="POST" enctype="multipart/form-data" class="space-y-6">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 divide-y divide-slate-200">
            <!-- Section-section form di sini -->
        </div>
    </form>
</div>
```

---

### 3.3. Section Header (Sub-Judul Bagian)

Gunakan Title Case yang elegan dengan font semibold dan deskripsi satu baris yang informatif:

```html
<div class="p-6 sm:p-8">
    <div class="mb-6">
        <h2 class="text-base font-semibold text-slate-900">Informasi Pribadi</h2>
        <p class="text-xs text-slate-500 mt-0.5">Identitas dasar dan kontak lengkap.</p>
    </div>

    <!-- Form Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 sm:gap-6">
        <!-- Field items -->
    </div>
</div>
```

---

### 3.4. Uploader Foto Profil / Avatar

Komponen uploader foto mengadopsi banner baris horizontal dengan thumbnail lingkaran (`rounded-full`), tombol upload file tersembunyi, dan tombol hapus kondisional:

```html
<div class="flex flex-col sm:flex-row sm:items-center gap-5 p-4 rounded-xl bg-slate-50 border border-slate-200/80 mb-6">
    <!-- Preview Avatar Frame -->
    <div class="relative w-20 h-20 rounded-full border border-slate-200 shadow-sm overflow-hidden bg-slate-100 flex-shrink-0 flex items-center justify-center">
        <?php $has_photo = !empty($data['photo']); ?>
        <img id="profile_preview"
            class="w-full h-full object-cover <?php echo $has_photo ? '' : 'hidden'; ?>"
            src="<?php echo $has_photo ? htmlspecialchars($data['photo_url']) : ''; ?>" alt="Foto Profil">

        <div id="photo_placeholder"
            class="<?php echo $has_photo ? 'hidden' : 'flex'; ?> items-center justify-center w-full h-full text-slate-400">
            <i class="fa-solid fa-user text-2xl"></i>
        </div>
    </div>

    <!-- Tombol Aksi & Petunjuk -->
    <div class="flex-1">
        <div class="text-sm font-semibold text-slate-800 mb-0.5">Foto Profil</div>
        <p class="text-xs text-slate-500 mb-3">Format JPG, JPEG, PNG, atau WebP. Maksimal 2MB.</p>
        <div class="flex items-center gap-2.5">
            <input type="file" name="profile_photo" id="profile_photo_input" class="hidden"
                accept="image/*" onchange="previewImage(this)">
            <input type="hidden" name="remove_photo" id="remove_photo_input" value="0">

            <button type="button" onclick="document.getElementById('profile_photo_input').click()"
                class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-sm transition-colors cursor-pointer">
                <i class="fa-solid fa-arrow-up-from-bracket text-xs text-slate-500"></i>
                <span>Pilih Foto</span>
            </button>
            <button type="button" id="remove_photo_btn" onclick="removeImage()"
                class="<?php echo $has_photo ? '' : 'hidden'; ?> inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-red-200 bg-red-50 hover:bg-red-100 text-red-600 text-xs font-semibold transition-colors cursor-pointer">
                <i class="fa-solid fa-trash-can text-xs"></i>
                <span>Hapus</span>
            </button>
        </div>
    </div>
</div>
```

**JavaScript Pendukung Uploader:**
```javascript
function previewImage(input) {
    const preview = document.getElementById('profile_preview');
    const placeholder = document.getElementById('photo_placeholder');
    const removeBtn = document.getElementById('remove_photo_btn');
    const removeInput = document.getElementById('remove_photo_input');

    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function (e) {
            preview.src = e.target.result;
            preview.classList.remove('hidden');
            placeholder.classList.add('hidden');
            removeBtn.classList.remove('hidden');
            removeInput.value = "0";
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function removeImage() {
    const input = document.getElementById('profile_photo_input');
    const preview = document.getElementById('profile_preview');
    const placeholder = document.getElementById('photo_placeholder');
    const removeBtn = document.getElementById('remove_photo_btn');
    const removeInput = document.getElementById('remove_photo_input');

    input.value = "";
    preview.src = "";
    preview.classList.add('hidden');
    placeholder.classList.remove('hidden');
    removeBtn.classList.add('hidden');
    removeInput.value = "1";
}
```

---

### 3.5. Form Label & Input Fields

#### A. Input Teks Standar
```html
<div>
    <label for="nama_lengkap" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
        Nama Lengkap <span class="text-red-500">*</span>
    </label>
    <input type="text" name="nama_lengkap" id="nama_lengkap" required
        value="<?php echo htmlspecialchars($data['nama_lengkap'] ?? ''); ?>"
        class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm placeholder:text-slate-400"
        placeholder="Masukkan nama lengkap">
</div>
```

#### B. Input Email & Nomor Telepon
```html
<div>
    <label for="email" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
        Alamat Email <span class="text-red-500">*</span>
    </label>
    <input type="email" name="email" id="email" required
        value="<?php echo htmlspecialchars($data['email'] ?? ''); ?>"
        class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm placeholder:text-slate-400"
        placeholder="nama@email.com">
</div>
```

#### C. Input Password dengan Hint Edit/Create Kondisional
```html
<div>
    <label for="password" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
        <?php echo $is_edit ? "Kata Sandi Baru" : "Kata Sandi Default"; ?>
        <span class="<?php echo $is_edit ? "text-slate-400 text-xs font-normal normal-case" : "text-red-500"; ?>">
            <?php echo $is_edit ? "(Kosongkan jika tidak ingin diubah)" : "*"; ?>
        </span>
    </label>
    <input type="password" name="password" id="password" <?php echo $is_edit ? '' : 'required'; ?>
        class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm placeholder:text-slate-400"
        placeholder="••••••••">
</div>
```

#### D. Input Nominal / Moneter (Prefix Rp)
```html
<div>
    <label for="nominal_spp" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
        Besar SPP Bulanan
    </label>
    <div class="flex">
        <span class="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 text-slate-500 text-sm font-medium">Rp</span>
        <input type="number" name="nominal_spp" id="nominal_spp" min="0" step="1000"
            value="<?php echo htmlspecialchars($data['nominal_spp'] ?? '0'); ?>"
            class="rounded-r-lg border border-slate-300 bg-white text-slate-800 focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none block w-full min-w-0 flex-1 text-sm px-3.5 py-2.5 shadow-sm placeholder:text-slate-400"
            placeholder="0">
    </div>
    <p class="text-[11px] text-slate-400 mt-1">Nominal iuran bulanan dalam Rupiah.</p>
</div>
```

#### E. Input Textarea
```html
<div class="md:col-span-2">
    <label for="alamat" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
        Alamat Lengkap <span class="text-red-500">*</span>
    </label>
    <textarea name="alamat" id="alamat" rows="3" required
        class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm placeholder:text-slate-400"
        placeholder="Alamat lengkap tempat tinggal..."><?php echo htmlspecialchars($data['alamat'] ?? ''); ?></textarea>
</div>
```

#### F. Dropdown Select (`hybrid-select`)
Tambahkan class `hybrid-select` agar secara otomatis dikonversi oleh `footer.php` menjadi select yang dapat dicari (*searchable*), ramah keyboard, dan memiliki transisi panah yang mulus.
- Jika tidak ingin fitur pencarian (misal opsi sedikit seperti Gender): tambahkan atribut `data-searchable="false"`.

```html
<div>
    <label for="gender" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
        Jenis Kelamin <span class="text-red-500">*</span>
    </label>
    <select name="gender" id="gender" required data-searchable="false"
        class="hybrid-select w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm">
        <option value="">Pilih Jenis Kelamin</option>
        <option value="Male" <?php echo ($val === 'Male') ? 'selected' : ''; ?>>Laki-laki</option>
        <option value="Female" <?php echo ($val === 'Female') ? 'selected' : ''; ?>>Perempuan</option>
    </select>
</div>
```

---

### 3.6. Card Footer & Tombol Aksi

Bagian footer terintegrasi langsung di dalam kartu induk pada baris terbawah dengan latar belakang `bg-slate-50` dan sudut bawah melengkung `rounded-b-xl`:

```html
            <!-- Card Footer / Actions -->
            <div class="px-6 sm:px-8 py-4 bg-slate-50 flex items-center justify-end gap-3 rounded-b-xl">
                <!-- Tombol Batal / Kembali -->
                <a href="<?php url('views/modul/index.php'); ?>"
                    class="px-5 py-2.5 border border-slate-300 rounded-lg text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50 transition-colors shadow-sm cursor-pointer">
                    Batal
                </a>

                <!-- Tombol Simpan / Submit -->
                <button type="submit"
                    class="px-6 py-2.5 bg-cyan-600 hover:bg-cyan-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-600 cursor-pointer">
                    <?php echo $is_edit ? "Simpan Perubahan" : "Simpan Data"; ?>
                </button>
            </div>
```

---

## 4. Tabel Rangkuman Utility Class (Cheat Sheet)

| Komponen | Utility Classes Tailwind |
| :--- | :--- |
| **Container Halaman** | `w-full pb-12` |
| **Breadcrumb Nav** | `flex mb-3` |
| **Breadcrumb List** | `inline-flex items-center space-x-2 text-xs text-slate-500` |
| **Page Title (H1)** | `text-2xl font-bold text-slate-900 tracking-tight` |
| **Page Subtitle (P)** | `text-slate-500 text-sm mt-1` |
| **Error Alert Box** | `bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 text-sm flex items-center gap-2.5` |
| **Master Card** | `bg-white rounded-xl shadow-sm border border-slate-200 divide-y divide-slate-200` |
| **Section Wrapper** | `p-6 sm:p-8` |
| **Section Title (H2)**| `text-base font-semibold text-slate-900` |
| **Section Desc (P)** | `text-xs text-slate-500 mt-0.5` |
| **Grid Form 2-Kolom**| `grid grid-cols-1 md:grid-cols-2 gap-5 sm:gap-6` |
| **Form Label** | `block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5` |
| **Required Asterisk**| `text-red-500` |
| **Input / Textarea** | `w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm placeholder:text-slate-400` |
| **Select Dropdown** | Tambahkan `class="hybrid-select ..."` (memanfaatkan global init di `views/layouts/footer.php`) |
| **Bottom Helper Note**| `text-[11px] text-slate-400 mt-1` |
| **Card Footer** | `px-6 sm:px-8 py-4 bg-slate-50 flex items-center justify-end gap-3 rounded-b-xl` |
| **Tombol Batal** | `px-5 py-2.5 border border-slate-300 rounded-lg text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50 transition-colors shadow-sm cursor-pointer` |
| **Tombol Simpan** | `px-6 py-2.5 bg-cyan-600 hover:bg-cyan-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-600 cursor-pointer` |

---

## 5. Checklist Verifikasi Formulir Baru (Do & Don't)

- [ ] **Pastikan `divide-y divide-slate-200` aktif** pada kartu pembungkus utama, bukan garis batas manual yang tidak rata.
- [ ] **Pastikan font input seragam**: gunakan `text-sm text-slate-800` dengan padding `px-3.5 py-2.5` dan border `border-slate-300`.
- [ ] **Jangan gunakan ikon warna-warni yang mengambang di dalam kotak input** kecuali untuk unit mata uang (`Rp`) atau ikon pencarian.
- [ ] **Hindari huruf kapital penuh (`UPPERCASE`) pada judul section (H2)**. Judul section harus tenang dan ramah keterbacaannya.
- [ ] **Semua elemen `<select>` wajib menyertakan class `hybrid-select`** agar mendapatkan styling terpadu dari sistem dashboard.
- [ ] **Validasi server selalu ditangkap**: sertakan blok pengecekan `isset($_GET['error'])` di atas form.
- [ ] **Tombol aksi diletakkan di footer kartu** dengan tombol Batal di sebelah kiri tombol Simpan.

---
*Dokumen ini merupakan pedoman resmi dan wajib dipatuhi untuk seluruh pengerjaan form baru maupun proses refaktorisasi antarmuka di Dashboard YAC.*
