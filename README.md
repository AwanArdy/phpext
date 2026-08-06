# Advanced Shipping for OpenCart 4

<p align="center">
  <strong>ADVANCED</strong> SHIPPING v2.0.0
</p>

> Ekstensi pengiriman (shipping) yang fleksibel dan powerful untuk **OpenCart 4.x** — kalkulasi biaya pengiriman berdasarkan berat, jumlah item, jarak, dimensi, dan lainnya dengan aturan rate yang fleksibel serta kondisi requirement yang lengkap.

---

## 📋 Daftar Isi

- [Fitur](#-fitur)
- [Persyaratan Sistem](#-persyaratan-sistem)
- [Instalasi](#-instalasi)
- [Uji Coba dengan Docker (Step by Step)](#-uji-coba-dengan-docker-step-by-step)
- [Konfigurasi](#-konfigurasi)
  - [Pengaturan Umum](#1-pengaturan-umum)
  - [Google Maps API Key](#2-google-maps-api-key)
  - [Membuat Shipping Rate](#3-membuat-shipping-rate)
  - [Kombinasi Rate](#4-kombinasi-rate)
  - [Requirement / Syarat](#5-requirement--syarat)
- [Tipe Perhitungan](#-tipe-perhitungan)
- [Contoh Penggunaan](#-contoh-penggunaan)
- [Backup & Debug](#-backup--debug)
- [Struktur File](#-struktur-file)
- [FAQ](#-faq)
- [Lisensi](#-lisensi)

---

## ✨ Fitur

- **Multi-parameter shipping** — Hitung ongkir berdasarkan berat, jumlah item, volume, dimensi, jarak, atau metode shipping lain
- **Perhitungan jarak** — Integrasi Google Maps API untuk kalkulasi jarak otomatis (Directions API + Geocoding API + Haversine fallback)
- **Rate bertingkat** — Buat tabel rate dengan harga bertingkat (single row atau kumulatif)
- **Kombinasi rate** — Gabungkan beberapa shipping rate menggunakan formula (`SUM`, `AVG`, `MIN`, `MAX`)
- **Kondisi fleksibel** — Aktifkan rate berdasarkan syarat: produk, keranjang, pelanggan, waktu, hari, zona geografis
- **Split package** — Bagi paket otomatis berdasarkan batas maksimum
- **Multi-currency** — Tentukan harga dalam mata uang berbeda, konversi otomatis
- **Multi-bahasa** — Nama shipping rate bisa berbeda per bahasa
- **Per product shipping** — Integrasi dengan Per Product Shipping extension
- **Testing mode** — Mode uji coba tanpa mempengaruhi pelanggan asli
- **Auto backup** — Backup otomatis harian untuk data rate
- **Debug log** — Log detail untuk troubleshooting kalkulasi
- **Import / Export** — Import dan export rate via CSV
- **Multi Geo Zone** — Konfigurasi rate berbeda per zona geografis

---

## 💻 Persyaratan Sistem

| Komponen         | Minimum                |
|------------------|------------------------|
| OpenCart          | 4.0.0.0 atau lebih baru |
| PHP              | 8.0 atau lebih baru    |
| MySQL / MariaDB  | 5.7+ / 10.3+          |
| PHP Extensions   | `json`, `simplexml`, `mbstring` |

> **Catatan:** Untuk fitur perhitungan jarak, diperlukan **Google Maps API Key** dengan akses ke Directions API dan Geocoding API.

---

## 📦 Instalasi

### Metode 1: Upload via Admin Panel (Direkomendasikan)

1. **Zip folder ekstensi:**
   ```bash
   cd upload/extension/
   zip -r advancedshipping.ocmod.zip advancedshipping/
   ```

2. **Login ke Admin Panel OpenCart 4**

3. Navigasi ke **Extensions → Installer**

4. Klik tombol **Upload** dan pilih file `advancedshipping.ocmod.zip`

5. Setelah upload berhasil, navigasi ke **Extensions → Extensions**

6. Pilih tipe **Shipping** dari dropdown

7. Cari **Advanced Shipping** dan klik tombol **Install** (ikon +)

8. Klik tombol **Edit** untuk mulai konfigurasi

### Metode 2: Upload Manual via FTP/SFTP

> ⚠️ **Penting (OpenCart 4):** Menyalin file saja **tidak cukup**.  
> Menu **Extensions → Shipping** hanya menampilkan ekstensi yang tercatat di tabel `extension_path` (hasil Installer), **bukan** hasil scan folder.  
> Setelah upload manual, Anda **tetap harus** mendaftarkan ekstensi lewat Installer (Metode 1) atau lewat Docker entrypoint di repo ini.

1. Upload seluruh isi folder `upload/extension/advancedshipping/` ke direktori OpenCart:
   ```
   <opencart-root>/extension/advancedshipping/
   ```

2. Pastikan struktur direktori seperti berikut:
   ```
   extension/
   └── advancedshipping/
       ├── install.json
       ├── admin/
       │   ├── controller/shipping/advancedshipping.php
       │   ├── model/shipping/advancedshipping.php
       │   ├── language/en-gb/shipping/advancedshipping.php
       │   └── view/
       │       ├── template/shipping/advancedshipping.twig
       │       ├── template/shipping/advancedshipping_rate.twig
       │       ├── stylesheet/shipping/advancedshipping.css
       │       └── javascript/shipping/advancedshipping/
       └── catalog/
           ├── model/shipping/advancedshipping.php
           └── language/en-gb/shipping/advancedshipping.php
   ```

3. **Daftarkan ke OpenCart** (pilih salah satu):
   - Zip folder lalu upload via **Extensions → Installer**, **atau**
   - Jika pakai Docker di repo ini: restart container (`docker compose up -d`) — entrypoint akan auto-register

4. Login ke Admin Panel, navigasi ke **Extensions → Extensions → Shipping**

5. Cari **Advanced Shipping** dan klik **Install** (ikon +), lalu **Edit**

### Metode 3: Docker dev environment (repo ini)

```bash
docker compose up -d --build
```

Ekstensi di-mount ke `extension/advancedshipping/` dan **otomatis didaftarkan** ke database saat container start.

> 📖 Lihat panduan lengkap di bagian [Uji Coba dengan Docker](#-uji-coba-dengan-docker-step-by-step).

---

## 🐳 Uji Coba dengan Docker (Step by Step)

Repo ini menyediakan environment Docker lengkap: **OpenCart 4 + MariaDB + phpMyAdmin**, dengan ekstensi Advanced Shipping yang sudah di-mount dan **otomatis terdaftar** ke database saat container pertama kali start.

> ⚠️ Pastikan file yang Anda edit ada di folder `upload/extension/advancedshipping/` — folder ini di-mount langsung ke container, sehingga **perubahan kode langsung terlihat** tanpa rebuild image.

### Langkah 0: Prasyarat

- Docker & Docker Compose terinstal (`docker --version`, `docker compose version`)
- Port berikut bebas: **8080** (toko), **8081** (phpMyAdmin), **3307** (MySQL)

### Langkah 1: Jalankan Container

```bash
docker compose up -d --build
```

Perintah ini akan:
1. Build image OpenCart 4.0.2.3 + PHP 8.2
2. Menunggu MariaDB siap (healthcheck otomatis)
3. Menginstal OpenCart via CLI (hanya saat pertama kali, ditandai file `.installed`)
4. **Mendaftarkan Advanced Shipping** ke tabel `extension_install` & `extension_path` (lewat `docker-entrypoint.sh`)

Pantau log sampai muncul `✅ Advanced Shipping terdaftar.`:

```bash
docker compose logs -f opencart
```

### Langkah 2: Akses Store & Admin

| Service    | URL                        |
|------------|----------------------------|
| Toko       | http://localhost:8080      |
| Admin      | http://localhost:8080/admin |
| phpMyAdmin | http://localhost:8081      |

**Kredensial default:**

| Akun    | Username | Password  |
|---------|----------|-----------|
| Admin   | `admin`  | `admin123`|
| MySQL   | `opencart` | `opencart123` |

### Langkah 3: Aktifkan Ekstensi di Admin

1. Login ke **http://localhost:8080/admin** (`admin` / `admin123`)
2. Buka **Extensions → Extensions**
3. Pilih tipe **Shipping** pada dropdown
4. Cari **Advanced Shipping** → klik tombol **Install** (ikon ➕)
5. Klik **Edit** untuk membuka halaman konfigurasi

> Jika ekstensi tidak muncul di daftar Shipping: restart container (`docker compose restart opencart`) — entrypoint akan mendaftarkan ulang semua path.

### Langkah 4: Siapkan Produk & Kredensial Pengiriman (opsional)

Untuk pengujian checkout dibutuhkan minimal 1 produk:

1. **Catalog → Products → Add New**
2. Isi nama produk & harga
3. **Data tab** → isi **Weight** (misal `1.0`) dan pilih **Weight Class**
4. **Links tab** → centang category **Default** agar muncul di toko

### Langkah 5: Buat Shipping Rate Pertama

1. Buka **Extensions → Extensions → Shipping → Advanced Shipping → Edit**
2. Pastikan **Status** di tab **Settings** = **On** (hijau)
3. Buka tab **Shipping Rates** → klik **Add Shipping Rate**
4. Isi konfigurasi minimal:
   - **Description**: `Tes Ongkir Berat`
   - **Status**: On
   - **Rate Type**: `Cart Weight`
   - **Shipping Costs**:
     | Max (kg) | Cost | Per |
     |----------|------|-----|
     | 1        | 10000 | -  |
     | 5        | 25000 | -  |
     | ~        | 5000  | 1  |
5. Klik **Save**

### Langkah 6: Uji Coba Checkout

1. Buka toko **http://localhost:8080**
2. Tambahkan produk ke keranjang → **Checkout**
3. Pilih **Register Account** atau **Guest Checkout**, isi alamat pengiriman
4. Di langkah shipping, pastikan **Advanced Shipping** muncul dengan ongkir sesuai tabel rate
5. Jika memakai **Testing Mode**: set nama pelanggan menjadi **Advanced Shipping** (mode ini menonaktifkan rate untuk semua nama lain)

> **Tips:** Fitur **Cart Distance** membutuhkan **Google Maps API Key** (lihat bagian [Konfigurasi](#-konfigurasi)). Tanpa API Key, gunakan rate berbasis berat/kuantitas/volume.

### Langkah 7: Cek Debug Log

Jika rate tidak muncul atau ongkir salah:

1. **Admin → Advanced Shipping → Edit → Settings** → aktifkan **Debug**
2. Ulangi langkah checkout
3. Klik **View Debug Log** untuk melihat detail kalkulasi tiap rate
4. Atau cek langsung dari terminal:
   ```bash
   docker compose exec opencart cat /var/www/html/system/storage/logs/advancedshipping.txt
   ```

### Langkah 8: Menghentikan & Mereset Environment

```bash
# Hentikan container (data tetap tersimpan di volume)
docker compose down

# Jalankan lagi (instalasi tidak diulang — ada file .installed)
docker compose up -d

# Reset penuh: hapus database & storage (semua data hilang)
docker compose down -v
docker compose up -d --build

# Buka shell di dalam container
docker compose exec opencart bash
```

### Troubleshooting

| Masalah                                        | Solusi                                                                 |
|------------------------------------------------|------------------------------------------------------------------------|
| Port 8080/8081 sudah terpakai                  | Ubah mapping port di `docker-compose.yml` lalu `docker compose up -d`   |
| Ekstensi tidak muncul di daftar Shipping       | `docker compose restart opencart` untuk mendaftarkan ulang              |
| Perubahan kode tidak kelihatan                 | Refresh browser (cache) — file di-mount langsung, tidak perlu rebuild   |
| Perubahan PHP tidak kelihatan di admin         | Hapus cache di **Settings → Cache → Clear Cache**, atau hapus file di `system/storage/cache/` |
| MySQL koneksi error saat instalasi             | Pastikan `depends_on` healthcheck selesai: `docker compose logs -f db`  |
| Ingin instalasi dari awal                     | `docker compose down -v` lalu `docker compose up -d --build`            |

---

## ⚙️ Konfigurasi

Setelah instalasi, buka halaman pengaturan Advanced Shipping melalui:  
**Extensions → Extensions → Shipping → Advanced Shipping → Edit**

Halaman utama memiliki 4 tab:

### 1. Pengaturan Umum

Tab **Settings** berisi konfigurasi global:

| Setting               | Keterangan                                                                 |
|-----------------------|----------------------------------------------------------------------------|
| **Status**            | Aktifkan/nonaktifkan ekstensi                                              |
| **Testing Mode**      | Aktifkan untuk uji coba — hanya berfungsi jika nama pelanggan = "Advanced Shipping" |
| **Shipping Title**    | Judul grup shipping yang tampil di checkout (per bahasa)                    |
| **Google Maps API Key** | API Key untuk fitur perhitungan jarak                                    |
| **Sort Order**        | Urutan tampilan di checkout dibanding shipping method lain                  |
| **Sort Quotes**       | Urutan tampilan antar rate: Sort Order, Biaya Terendah/Tertinggi           |
| **Debug**             | Aktifkan logging untuk troubleshooting                                     |
| **Cache**             | Aktifkan cache untuk mempercepat kalkulasi                                 |
| **Auto Backup**       | Backup otomatis harian                                                     |

### 2. Google Maps API Key

Untuk menggunakan fitur **perhitungan jarak** (Cart Distance):

1. Buka [Google Cloud Console](https://console.cloud.google.com/)
2. Buat project baru atau pilih project yang ada
3. Aktifkan API berikut:
   - **Directions API**
   - **Geocoding API**
4. Buat API Key di menu **Credentials**
5. (Opsional) Batasi API Key ke domain toko Anda untuk keamanan
6. Paste API Key ke field **Google Maps API Key** di tab Settings

### 3. Membuat Shipping Rate

Klik tombol **Add Shipping Rate** di tab **Shipping Rates**:

#### General Settings
| Field           | Keterangan                                                  |
|-----------------|-------------------------------------------------------------|
| **Description** | Deskripsi internal (hanya terlihat admin)                    |
| **Status**      | On/Off untuk rate ini                                        |
| **Group**       | Kode grup untuk kombinasi rate (contoh: `A`, `B`, `EXPRESS`) |
| **Tax Class**   | Kelas pajak yang diterapkan                                  |
| **Total Type**  | Basis kalkulasi: Sub-Total, Sub-Total + Tax, atau Total      |
| **Origin**      | Alamat asal untuk perhitungan jarak                          |

#### Display Settings
| Field          | Keterangan                                      |
|----------------|--------------------------------------------------|
| **Name**       | Nama yang tampil di checkout (per bahasa)         |
| **Sort Order** | Urutan tampilan rate ini                          |

#### Calculate Shipping (per Geo Zone)

Untuk setiap Geo Zone, konfigurasi:

| Field              | Keterangan                                                      |
|--------------------|-----------------------------------------------------------------|
| **Rate Type**      | Basis perhitungan (Weight, Quantity, Distance, dll.)              |
| **Shipping Factor**| Faktor untuk Dimensional Weight                                  |
| **Currency**       | Mata uang untuk harga rate                                       |
| **Shipping Costs** | Tabel rate: Max → Cost → Per Unit                                |
| **Calculation**    | Single Row atau Kumulatif                                        |
| **Split Package**  | Bagi paket otomatis                                              |
| **Cost Adj.**      | Min cost, Max cost, Additional cost                              |
| **Handling Fee**   | Biaya penanganan (tetap atau persentase)                         |

#### Tabel Shipping Costs

Isi tabel rate dengan kolom:

| Max | Cost  | Per |
|-----|-------|-----|
| 5   | 10.00 | -   |
| 10  | 8.00  | -   |
| ~   | 5.00  | 1   |

- **Max**: Nilai maksimum sebelum pindah ke baris berikutnya. Gunakan `~` untuk "tanpa batas"
- **Cost**: Biaya pengiriman (angka tetap atau persentase dengan `%`)
- **Per**: Biaya per unit (opsional — contoh: $5 per kg)

### 4. Kombinasi Rate

Tab **Combine Shipping Rates** memungkinkan penggabungan rate dari grup berbeda:

| Field              | Keterangan                                    |
|--------------------|------------------------------------------------|
| **Sort Order**     | Urutan kombinasi                               |
| **Title Display**  | Cara menampilkan nama (First, Last, Combined, Custom) |
| **Formula**        | Rumus kombinasi                                |
| **Group Requirement** | Semua grup harus ada nilai atau cukup salah satu |

#### Contoh Formula:
```
SUM({A},{B})        → Jumlah rate grup A + B
AVG({A},{B})        → Rata-rata rate grup A dan B  
MIN({A},{B})        → Rate terendah antara A dan B
MAX({A},{B})        → Rate tertinggi antara A dan B
SUM({A},MAX({B},{C})) → A + yang tertinggi antara B dan C
```

### 5. Requirement / Syarat

Setiap rate bisa memiliki syarat aktivasi:

#### Tipe Requirement:

| Kategori     | Syarat Tersedia                                                                  |
|--------------|----------------------------------------------------------------------------------|
| **Keranjang** | Quantity, Total, Berat, Volume, Dimensional Weight, Jarak, Panjang, Lebar, Tinggi |
| **Produk**    | Quantity, Total, Berat, Volume, Nama, Model, SKU, UPC, EAN, Kategori, Manufacturer, Opsi, Atribut |
| **Pelanggan** | Store, Customer Group, Nama, Email, Telepon, Perusahaan, Alamat, Kota, Kode Pos, Custom Field |
| **Lainnya**   | Mata Uang, Hari, Tanggal, Waktu                                                  |

#### Operator:
- `Equals` / `Does Not Equal`
- `Greater Than Or Equals` / `Less Than Or Equals`
- `Contains` / `Does Not Contain`
- `Add` / `Subtract` (untuk adjustment nilai)

#### Match Mode:
- **Any** — Salah satu syarat terpenuhi sudah cukup
- **All** — Semua syarat harus terpenuhi
- **None** — Tidak ada syarat yang boleh terpenuhi

---

## 📊 Tipe Perhitungan

### Cart Values
| Tipe                    | Keterangan                                    |
|-------------------------|-----------------------------------------------|
| Cart Quantity           | Jumlah total item di keranjang                |
| Cart Total              | Total harga keranjang                         |
| Cart Weight             | Berat total keranjang                         |
| Cart Dimensional Weight | Berat dimensional (volume / faktor)           |
| Cart Volume             | Volume total (P × L × T)                     |
| Cart Length/Width/Height | Dimensi total keranjang                      |
| Cart Distance           | Jarak dari alamat asal ke tujuan (km)         |

### Product Values
| Tipe                       | Keterangan                                 |
|----------------------------|---------------------------------------------|
| Product Quantity           | Jumlah per produk                           |
| Product Total              | Harga per produk                            |
| Product Weight             | Berat per produk                            |
| Product Dimensional Weight | Berat dimensional per produk                |
| Product Volume             | Volume per produk                           |
| Product Length/Width/Height| Dimensi per produk                          |

### Other Shipping Methods
Gunakan rate dari ekstensi shipping lain sebagai basis kalkulasi.

---

## 📝 Contoh Penggunaan

### Contoh 1: Ongkir Berdasarkan Berat

**Skenario:** Ongkir flat berdasarkan berat total keranjang

| Max (kg) | Cost (Rp) | Per |
|----------|-----------|-----|
| 1        | 10000     | -   |
| 5        | 25000     | -   |
| 10       | 40000     | -   |
| ~        | 5000      | 1   |

→ 0-1kg = Rp10.000, 1-5kg = Rp25.000, 5-10kg = Rp40.000, >10kg = Rp5.000/kg

### Contoh 2: Ongkir Berdasarkan Jarak

**Skenario:** Ongkir per km dari toko ke pelanggan

1. Isi **Origin Address** dengan alamat toko
2. Isi **Google Maps API Key** di Settings
3. Pilih **Rate Type** = Cart Distance

| Max (km) | Cost (Rp) | Per |
|----------|-----------|-----|
| 5        | 10000     | -   |
| 20       | 2000      | 1   |
| ~        | 1500      | 1   |

→ 0-5km = Rp10.000 flat, 5-20km = Rp2.000/km, >20km = Rp1.500/km

### Contoh 3: Gratis Ongkir di Atas Rp500.000

1. Buat rate dengan Cost = `0`
2. Tambahkan **Requirement**: Cart Total ≥ 500000
3. Buat rate lain untuk di bawah Rp500.000 dengan cost normal

### Contoh 4: Kombinasi Berat + Handling Fee

1. Buat rate grup **A** (berdasarkan berat)
2. Buat rate grup **B** (handling fee tetap, misal Rp5.000)
3. Di tab Combinations: `SUM({A},{B})`

---

## 🔧 Backup & Debug

### Debug Log

1. Aktifkan **Debug** di tab Settings
2. Lakukan test order
3. Klik **View Debug Log** untuk melihat detail kalkulasi
4. Log tersimpan di `<opencart>/system/storage/logs/advancedshipping.txt`

### Backup & Restore

- **Auto Backup**: Aktifkan di Settings untuk backup harian otomatis
- **Manual Restore**: Klik **View Backups** → pilih backup → **Restore**
- **Export/Import**: Gunakan tombol Export/Import di tab Shipping Rates untuk CSV

### Testing Mode

1. Aktifkan **Testing Mode** di Settings
2. Buat test order dengan nama pelanggan: **Advanced Shipping**
3. Hanya order dengan nama tersebut yang akan menampilkan rate
4. Nonaktifkan Testing Mode setelah selesai uji coba

---

## 📁 Struktur File

```
extension/advancedshipping/
├── install.json                                    # OC4 extension descriptor
├── admin/
│   ├── controller/shipping/
│   │   └── advancedshipping.php                    # Admin controller (PHP 8, strict types)
│   ├── model/shipping/
│   │   └── advancedshipping.php                    # Admin model — CRUD, install/uninstall
│   ├── language/en-gb/shipping/
│   │   └── advancedshipping.php                    # Admin language (English)
│   └── view/
│       ├── template/shipping/
│       │   ├── advancedshipping.twig               # Main admin template
│       │   └── advancedshipping_rate.twig           # Rate editor template
│       ├── stylesheet/shipping/
│       │   └── advancedshipping.css                # Admin styles
│       └── javascript/shipping/advancedshipping/
│           ├── jquery.datetimepicker.js             # DateTimePicker plugin
│           └── jquery.datetimepicker.css            # DateTimePicker styles
└── catalog/
    ├── model/shipping/
    │   └── advancedshipping.php                    # Shipping calculation engine (PHP 8)
    └── language/en-gb/shipping/
        └── advancedshipping.php                    # Catalog language (English)
```

---

## ❓ FAQ

### Q: Apakah ekstensi ini kompatibel dengan OpenCart 3?
**A:** Tidak. Versi 2.0.0 ini dibuat khusus untuk OpenCart 4.x dengan PHP 8. Untuk OpenCart 3, gunakan versi sebelumnya (Intuitive Shipping).

### Q: Apakah Google Maps API Key wajib?
**A:** Tidak wajib. API Key hanya diperlukan jika Anda menggunakan fitur **Cart Distance** (perhitungan jarak). Fitur lain seperti berat, jumlah item, dan volume tidak memerlukan API Key.

### Q: Berapa biaya Google Maps API?
**A:** Google memberikan **$200 credit gratis per bulan** (~40.000 request Directions API). Untuk kebanyakan toko, ini sudah cukup. Aktifkan **Cache** di Settings untuk mengurangi jumlah request API.

### Q: Bagaimana cara menambahkan bahasa Indonesia?
**A:** Buat file di `admin/language/id-id/shipping/advancedshipping.php` dan `catalog/language/id-id/shipping/advancedshipping.php` dengan terjemahan dari file `en-gb`.

### Q: Rate saya tidak muncul di checkout, kenapa?
**A:** Periksa hal berikut:
1. Status ekstensi harus **On**
2. Status rate individual harus **On**
3. Testing Mode harus **Off** (atau nama pelanggan = "Advanced Shipping")
4. Geo Zone harus sesuai dengan alamat pengiriman
5. Semua **Requirements** harus terpenuhi
6. Aktifkan **Debug** untuk melihat log detail

### Q: Apa arti simbol `~` di kolom Max?
**A:** Simbol `~` berarti "tanpa batas atas" (unlimited). Baris dengan `~` akan menangkap semua nilai yang melampaui baris sebelumnya.

### Q: Bagaimana cara uninstall?
**A:** Navigasi ke **Extensions → Extensions → Shipping**, cari Advanced Shipping, dan klik tombol **Uninstall**. Ini akan menghapus tabel database `advanced_shipping` beserta semua data rate.

---

## 📄 Lisensi

Copyright © 2011–2026 OpenCart Addons. All rights reserved.

---

## 🆘 Support

Jika membutuhkan bantuan, gunakan tab **Support** di halaman Advanced Shipping admin panel untuk mengirim pertanyaan langsung.
