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
- [Uji Coba Tanpa Docker (Langsung di OpenCart)](#-uji-coba-tanpa-docker-langsung-di-opencart)
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

1. **Zip isi folder ekstensi** (file `install.json`, `admin/`, dan `catalog/` harus berada di root ZIP — bukan di dalam subfolder `advancedshipping/`, karena installer OpenCart 4 hanya mencari `install.json` di root archive):
   ```bash
   cd upload/extension/advancedshipping
   zip -r ../advancedshipping.ocmod.zip admin catalog install.json
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
   - Zip isi folder (`install.json`, `admin/`, `catalog/` di root ZIP) lalu upload via **Extensions → Installer**, **atau**
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

## 🧪 Uji Coba Tanpa Docker (Langsung di OpenCart)

Panduan pengujian ini berlaku untuk **OpenCart 4 yang sudah terinstal** di server/hosting biasa (tanpa Docker). Semua pengujian dilakukan lewat browser: **Admin Panel** untuk setup & **toko** untuk simulasi checkout.

### Prasyarat

| Kebutuhan | Keterangan |
|-----------|------------|
| OpenCart | Versi 4.0.0.0 atau lebih baru, sudah terinstal & bisa diakses |
| Akses Admin | Login sebagai **Super Admin** (punya permission **Extensions → Installer**) |
| Produk uji | Minimal 1 produk dengan **berat**, **harga**, dan **dimensi** terisi |
| Checkout | Aktifkan **Guest Checkout** (System → Settings → Option) atau siapkan 1 akun pelanggan |
| Google Maps API Key | Hanya wajib untuk pengujian fitur **jarak** (opsional untuk lainnya) |

### Langkah 0: Instal Ekstensi

1. Buat file `advancedshipping.ocmod.zip` dari isi folder ekstensi (lihat [Instalasi Metode 1](#-instalasi)):
   ```bash
   cd upload/extension/advancedshipping
   zip -r ../advancedshipping.ocmod.zip admin catalog install.json
   ```
2. **Admin → Extensions → Installer → Upload** → pilih `advancedshipping.ocmod.zip`
3. **Extensions → Extensions** → pilih tipe **Shipping** pada dropdown
4. Cari **Advanced Shipping** → klik **Install** (ikon ➕) → klik **Edit**

> Setelah ini, aktifkan **Debug** (tab Settings) dan biarkan menyala selama pengujian agar setiap kegagalan bisa ditelusuri di **View Debug Log**.

### Langkah 1: Siapkan Produk Uji

Buat 3 produk di **Catalog → Products** dengan data berikut (sesuaikan Weight Class = kg, Length Class = cm, mata uang sesuai toko):

| Produk | Harga | Berat | Dimensi (P×L×T cm) | Volume |
|--------|-------|-------|--------------------|--------|
| Produk A | 100.000 | 0,5 kg | 10 × 10 × 10 | 1.000 cm³ |
| Produk B | 250.000 | 2 kg | 30 × 20 × 10 | 6.000 cm³ |
| Produk C | 500.000 | 5 kg | 50 × 40 × 30 | 60.000 cm³ |

Pastikan tiap produk masuk ke **category Default** (tab Links) agar muncul di toko.

### Langkah 2: Siapkan Geo Zone

Agar rate bisa dihitung, pastikan **Geo Zone** sesuai alamat pengiriman uji:

1. **System → Localisation → Geo Zones** → buat zone (misal: **Indonesia**, isi country + zone)
2. Saat membuat rate, pilih Geo Zone tersebut pada **Calculate Shipping**
3. Buat juga zone **Rest of the World** agar semua alamat lain tetap terhitung

---

### Checklist Pengujian

Berikut urutan pengujian lengkap. Setiap langkah = buat rate → checkout → cocokkan hasil dengan yang diharapkan.

#### Uji 1: Shipping Berbasis Berat (Cart Weight)

| Max (kg) | Cost | Per |
|----------|------|-----|
| 1 | 10.000 | - |
| 5 | 20.000 | - |
| ~ | 4.000 | 1 |

| Skenario | Keranjang | Hasil Diharapkan |
|----------|-----------|------------------|
| 0,5 kg | 1× Produk A | 10.000 |
| 2 kg | 1× Produk B | 20.000 |
| 5,5 kg | 1× Produk C + 1× Produk A | 24.000 (4.000 × 6) |

#### Uji 2: Jumlah Item (Cart Quantity)

| Max (item) | Cost |
|------------|------|
| 2 | 15.000 |
| 5 | 30.000 |
| ~ | 50.000 |

| Skenario | Keranjang | Hasil Diharapkan |
|----------|-----------|------------------|
| 1 item | 1× Produk A | 15.000 |
| 3 item | 3× Produk A | 30.000 |
| 6 item | 6× Produk A | 50.000 |

#### Uji 3: Total Keranjang (Cart Total)

| Max (Rp) | Cost |
|----------|------|
| 100.000 | 10.000 |
| 500.000 | 0 (gratis ongkir) |

| Skenario | Keranjang | Hasil Diharapkan |
|----------|-----------|------------------|
| Di bawah 100rb | 1× Produk A (100.000) → gunakan diskon kecil atau qty 1 | 10.000 |
| 200.000 | 2× Produk A | 0 |
| > 500.000 | 2× Produk C | 0 |

#### Uji 4: Volume & Dimensional Weight

| Max (cm³) | Cost |
|-----------|------|
| 1.000 | 10.000 |
| 10.000 | 25.000 |
| ~ | 50.000 |

| Skenario | Keranjang | Hasil Diharapkan |
|----------|-----------|------------------|
| 1.000 cm³ | 1× Produk A | 10.000 |
| 60.000 cm³ | 1× Produk C | 50.000 |

**Dimensional Weight** (jika diaktifkan): set **Shipping Factor** = 5000 di rate. Volume Produk C = 60.000 cm³ → dim weight = 12 kg. Bandingkan dengan berat asli 5 kg → yang dipakai nilai lebih besar (12 kg).

#### Uji 5: Tabel Rate — Single Row vs Kumulatif

Pakai **Rate Type = Cart Weight** dengan tabel:

| Max (kg) | Cost |
|----------|------|
| 2 | 10.000 |
| 5 | 15.000 |
| ~ | 20.000 |

| Mode Perhitungan | Keranjang 6 kg | Hasil Diharapkan |
|------------------|----------------|------------------|
| **Single Row** | 1× Produk C + 1× Produk A | 20.000 (baris ~ langsung menangkap) |
| **Kumulatif** | 1× Produk C + 1× Produk A | 45.000 (10.000 + 15.000 + 20.000) |

> Simbol `~` berarti "tanpa batas atas" dan menangkap semua nilai di atas baris sebelumnya.

#### Uji 6: Split Package

1. Buat rate **Cart Weight** dengan tabel rate maksimum **5 kg** dan aktifkan **Split Package**
2. Keranjang: 1× Produk C (5 kg) + 1× Produk B (2 kg) = **7 kg**

| Hasil Diharapkan |
|------------------|
| Paket dipecah jadi 2: 5 kg + 2 kg. Ongkir = biaya paket 5 kg + biaya paket 2 kg |

#### Uji 7: Jarak (Cart Distance)

> Wajib punya **Google Maps API Key** (lihat [Konfigurasi → Google Maps API Key](#2-google-maps-api-key)) dan **Origin Address** diisi pada rate.

| Max (km) | Cost | Per |
|----------|------|-----|
| 5 | 10.000 | - |
| 20 | 20.000 | - |
| ~ | 1.500 | 1 |

| Skenario (alamat tujuan) | Hasil Diharapkan |
|--------------------------|------------------|
| Jarak ≤ 5 km | 10.000 |
| Jarak 10 km | 20.000 |
| Jarak 30 km | 45.000 (1.500 × 30) |

#### Uji 8: Kombinasi Rate (SUM / AVG / MIN / MAX)

1. Buat rate **Grup A** (berbasis berat) dan rate **Grup B** (handling fee tetap 5.000)
2. Buka tab **Combine Shipping Rates** → tambah kombinasi:
   ```
   SUM({A},{B})    → ongkir = A + B
   AVG({A},{B})    → ongkir = rata-rata A dan B
   MAX({A},{B})    → ongkir = yang paling besar
   MIN({A},{B})    → ongkir = yang paling kecil
   ```
3. Checkout dengan keranjang yang memenuhi kedua grup

| Hasil Diharapkan |
|------------------|
| Muncul 1 opsi shipping hasil kombinasi dengan nilai sesuai formula |

#### Uji 9: Requirement Keranjang

1. Buat rate **Cart Weight** (misal cost 20.000)
2. Di tab **Requirements**, tambah syarat: **Cart Total ≥ 300.000** (operation `Greater Than Or Equals`)
3. Set **Match Mode = All**

| Skenario | Keranjang | Hasil Diharapkan |
|----------|-----------|------------------|
| Total < 300rb | 1× Produk B (250.000) | Rate tidak muncul |
| Total ≥ 300rb | 1× Produk C (500.000) | Rate muncul, cost 20.000 |

#### Uji 10: Requirement Produk

1. Tambahkan syarat **Product Category = Default** pada sebuah rate
2. Buat produk yang **tidak** masuk kategori Default sebagai pembanding

| Skenario | Hasil Diharapkan |
|----------|------------------|
| Keranjang hanya berisi produk kategori Default | Rate muncul |
| Keranjang berisi produk di luar kategori Default | Rate tidak muncul |

> Variasi lain yang bisa diuji: **Product Name**, **Model**, **SKU**, **Manufacturer**, **Option**, **Attribute** dengan operator `Equals` / `Contains`.

#### Uji 11: Requirement Pelanggan

1. Tambahkan syarat **Customer Group = Default**
2. Checkout sebagai **guest** (tanpa login) → group = Guest

| Skenario | Hasil Diharapkan |
|----------|------------------|
| Guest checkout | Rate tidak muncul (bila syarat hanya group Default) |
| Login akun group Default | Rate muncul |

> Variasi: **Customer City**, **Postcode**, **Email**, **Telephone**, dan **Custom Field**.

#### Uji 12: Requirement Lainnya (Hari & Mata Uang)

| Syarat | Setup | Hasil Diharapkan |
|--------|-------|------------------|
| **Day** | Pilih hari tertentu (misal Senin) | Rate hanya muncul di hari tersebut |
| **Currency** | Pilih mata uang toko (misal IDR) | Rate hanya muncul saat pelanggan memilih mata uang itu |

#### Uji 13: Multi-Currency

1. **System → Localisation → Currencies** → pastikan ada mata uang kedua (misal USD dengan nilai tukar)
2. Buat rate dengan **Currency = USD**, cost 5
3. Checkout dengan mata uang toko default (IDR)

| Hasil Diharapkan |
|------------------|
| Ongkir tampil dalam mata uang toko, otomatis dikonversi dari USD sesuai nilai tukar |

#### Uji 14: Multi-Bahasa (Nama Rate per Bahasa)

1. Tambahkan bahasa kedua (misal `id-id`) di **System → Localisation → Languages**
2. Pada rate, isi **Name** untuk tiap bahasa

| Hasil Diharapkan |
|------------------|
| Nama rate tampil sesuai bahasa toko yang aktif (EN untuk `en-gb`, ID untuk `id-id`) |

#### Uji 15: Import / Export CSV

1. **Export**: tab Shipping Rates → klik **Export** → file CSV terunduh, isi sesuai rate
2. Edit CSV (ubah satu cost)
3. **Import**: klik **Import** → pilih CSV → simpan

| Hasil Diharapkan |
|------------------|
| Muncul pesan sukses `X added, Y updated`, dan perubahan cost tampil di daftar rate |

#### Uji 16: Backup & Restore

1. Tab Settings → aktifkan **Auto Backup** (opsional)
2. Tab Shipping Rates → hapus salah satu rate
3. Tab **View Backups** → pilih backup → **Restore**

| Hasil Diharapkan |
|------------------|
| Rate yang dihapus kembali muncul setelah restore |

#### Uji 17: Debug Log

1. Tab Settings → **Debug = On**
2. Lakukan beberapa skenario checkout
3. Klik **View Debug Log** (atau buka `<opencart>/system/storage/logs/advancedshipping.txt`)

| Hasil Diharapkan |
|------------------|
| Log berisi detail tiap rate: Geo Zone, data keranjang/produk/pelanggan, hasil kalkulasi, dan alasan rate dilewati |

---

### Ringkasan Checklist

| # | Fitur | Berhasil? |
|---|-------|-----------|
| 1 | Cart Weight (rate bertingkat + per kg) | ☐ |
| 2 | Cart Quantity | ☐ |
| 3 | Cart Total (gratis ongkir) | ☐ |
| 4 | Volume & Dimensional Weight | ☐ |
| 5 | Single Row vs Kumulatif + simbol `~` | ☐ |
| 6 | Split Package | ☐ |
| 7 | Cart Distance (Google Maps) | ☐ |
| 8 | Kombinasi SUM / AVG / MIN / MAX | ☐ |
| 9 | Requirement Cart (minimal total) | ☐ |
| 10 | Requirement Product (kategori/SKU) | ☐ |
| 11 | Requirement Customer (group/city) | ☐ |
| 12 | Requirement Day / Currency | ☐ |
| 13 | Multi-currency | ☐ |
| 14 | Multi-bahasa | ☐ |
| 15 | Import / Export CSV | ☐ |
| 16 | Backup & Restore | ☐ |
| 17 | Debug Log | ☐ |

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
