===============================================================================
 SHIPPING EXTENSION INSTALLATION GUIDE / PANDUAN INSTALASI EKSTENSI PENGIRIMAN
===============================================================================

 Language / Bahasa:
 [1] Bahasa Indonesia (Lihat Bagian I)
 [2] English (See Section II)

-------------------------------------------------------------------------------
 TABLE OF CONTENTS / DAFTAR ISI
-------------------------------------------------------------------------------
 BAGIAN I: BAHASA INDONESIA
 1. Struktur File & Folder yang Di-upload
 2. Metode Upload Ekstensi
    - Metode A: Upload via Admin Installer (.ocmod.zip)
    - Metode B: Upload Manual via FTP / File Manager cPanel
 3. Login ke Admin OpenCart
 4. Menambahkan User Permission (Akses & Modifikasi)
 5. Aktivasi & Instalasi Ekstensi Shipping
 6. Troubleshooting & Verifikasi

 SECTION II: ENGLISH VERSION
 1. Files & Folder Structure to Upload
 2. Extension Upload Methods
    - Method A: Upload via Admin Installer (.ocmod.zip)
    - Method B: Manual Upload via FTP / cPanel File Manager
 3. Logging into OpenCart Admin
 4. Setting Up User Permissions (Access & Modify)
 5. Activating & Installing Shipping Extension
 6. Troubleshooting & Verification


===============================================================================
 BAGIAN I: BAHASA INDONESIA
===============================================================================

-------------------------------------------------------------------------------
 1. STRUKTUR FILE & FOLDER YANG DI-UPLOAD
-------------------------------------------------------------------------------

Sebelum melakukan instalasi, pastikan Anda memahami file dan folder apa saja
yang perlu di-upload ke server OpenCart Anda.

Ekstensi pengiriman (shipping extension) OpenCart terdiri dari file backend
admin (untuk pengaturan) dan catalog (untuk kalkulasi ongkir di checkout).

Contoh Struktur Folder Ekstensi (misalnya: Advanced Shipping):

  advancedshipping/
  ├── admin/
  │   ├── controller/
  │   │   └── shipping/
  │   │       └── advancedshipping.php
  │   ├── language/
  │   │   └── en-gb/
  │   │       └── shipping/
  │   │           └── advancedshipping.php
  │   ├── model/
  │   │   └── shipping/
  │   │       └── advancedshipping.php
  │   └── view/
  │       └── template/
  │           └── shipping/
  │               └── advancedshipping.twig
  ├── catalog/
  │   ├── language/
  │   │   └── en-gb/
  │   │       └── shipping/
  │   │           └── advancedshipping.php
  │   └── model/
  │       └── shipping/
  │           └── advancedshipping.php
  └── install.json

Penjelasan Folder & File Penting:
- folder 'admin/'   : Berisi controller, language, model, dan tampilan (view/twig)
                      untuk menu konfigurasi di dashboard admin.
- folder 'catalog/' : Berisi model kalkulasi harga shipping dan bahasa yang
                      digunakan saat pelanggan checkout di toko frontend.
- file 'install.json': File metadata resmi OpenCart yang berisi nama ekstensi,
                      versi, pembuat, dan instruksi instalasi otomatis.

-------------------------------------------------------------------------------
 2. METODE UPLOAD EKSTENSI
-------------------------------------------------------------------------------

Pilih salah satu metode upload di bawah ini:

-------------------------------------------------------------------------------
 [METODE A] Upload via Admin Installer (.ocmod.zip) — DIREKOMENDASIKAN
-------------------------------------------------------------------------------

1. Buat File ZIP Installer (.ocmod.zip):
   - Buka folder tempat file ekstensi berada (folder `upload/extension/advancedshipping/`).
   - Pilih file `install.json`, folder `admin`, dan folder `catalog`.
   - Compress/ZIP ketiga item tersebut menjadi satu file bernama:
     `advancedshipping.ocmod.zip`
   
   catatan:
   File `install.json`, folder `admin/`, dan folder `catalog/` HARUS berada di
   root utama ZIP (jangan dibungkus lagi dalam folder lain di dalam zip).

2. Lanjut ke langkah Login Admin (Bagian 3) dan ikuti instruksi Installer.


-------------------------------------------------------------------------------
 [METODE B] Upload Manual via FTP / File Manager cPanel
-------------------------------------------------------------------------------

Jika Anda memilih upload manual tanpa file ZIP:

1. Buka FTP Client (FileZilla) atau File Manager di cPanel hosting Anda.
2. Cari direktori utama (root directory) instalasi OpenCart Anda
   (biasanya di `/public_html/` atau `/var/www/html/`).
3. Upload folder ekstensi ke jalur berikut:

   Target Folder di Server:
   <root-opencart>/extension/advancedshipping/

4. Hasil struktur file setelah di-upload di server:

   public_html/ (atau root OpenCart Anda)
   └── extension/
       └── advancedshipping/
           ├── admin/
           ├── catalog/
           └── install.json

-------------------------------------------------------------------------------
 3. CARA LOGIN KE DASHBOARD ADMIN OPENCART
-------------------------------------------------------------------------------

1. Buka browser web (Chrome, Firefox, Edge, Safari, dll).
2. Ketikkan URL Admin toko OpenCart Anda.
   Contoh:
   - http://namadomainanda.com/admin
   - https://tokoanda.id/admin
3. Masukkan 'Username' dan 'Password' akun Administrator Anda.
4. Klik tombol "Login".
5. Anda akan masuk ke halaman Dashboard Utama Admin OpenCart.

-------------------------------------------------------------------------------
 4. MENAMBAHKAN USER PERMISSION (HAK AKSES & MODIFIKASI)
-------------------------------------------------------------------------------

Agar menu ekstensi dapat dibuka dan disimpan tanpa muncul error "Access Denied"
atau "Permission Denied", Anda WAJIB memberikan Hak Akses (Access Permission)
dan Hak Modifikasi (Modify Permission) kepada grup pengguna Anda.

Langkah-langkah Pengaturan Hak Akses:

Langkah 4.1: Buka Menu User Groups
- Pada menu navigasi sebelah kiri, klik:
  System (Sistem) -> Users (Pengguna) -> User Groups (Grup Pengguna)

Langkah 4.2: Edit Grup Administrator
- Cari baris "Administrator" (atau grup pengguna yang sedang Anda gunakan).
- Klik tombol "Edit" (ikon pensil berwarna biru) di sebelah kanan baris tersebut.

Langkah 4.3: Berikan Hak Akses (Access Permission)
- Pada bagian "Access Permission" (Hak Akses):
  a. Gulir (scroll) daftar permission ke bawah dan cari path ekstensi shipping.
     Contoh path:
     - extension/advancedshipping/shipping/advancedshipping
     - extension/shipping/advancedshipping
  b. Beri centang (check) pada kotak di samping nama path ekstensi tersebut.
  c. Atau klik tombol "Select All" (Pilih Semua) di bawah kotak Access Permission.

Langkah 4.4: Berikan Hak Modifikasi (Modify Permission)
- Pada bagian "Modify Permission" (Hak Modifikasi):
  a. Cari path ekstensi yang sama seperti di atas.
  b. Beri centang (check) pada kotak di samping nama path ekstensi tersebut.
  c. Atau klik tombol "Select All" (Pilih Semua) di bawah kotak Modify Permission.

Langkah 4.5: Simpan Pengaturan
- Klik tombol "Save" (ikon disket berwarna biru) di kanan atas layar.
- Akan muncul pesan sukses: "Success: You have modified user groups!".

-------------------------------------------------------------------------------
 5. AKTIVASI & INSTALASI EKSTENSI SHIPPING
-------------------------------------------------------------------------------

Langkah 5.1: Mengunggah Zip via Admin Installer (Jika Menggunakan Metode A)
- Dari menu kiri dashboard, navigasi ke:
  Extensions (Ekstensi) -> Installer (Penginstal)
- Klik tombol "Upload" (ikon unggah warna biru) di pojok kanan atas.
- Pilih file `advancedshipping.ocmod.zip` dari komputer Anda.
- Tunggu hingga proses upload selesai dan muncul keterangan sukses.

Langkah 5.2: Mengaktifkan Ekstensi di Menu Shipping
- Dari menu navigasi sebelah kiri, pilih:
  Extensions (Ekstensi) -> Extensions (Ekstensi)
- Pada menu dropdown "Choose the extension type" (Pilih tipe ekstensi),
  pilih opsi: "Shipping" (Pengiriman).
- Cari nama ekstensi Anda di daftar (misalnya: "Advanced Shipping").
- Jika ekstensi belum terpasang:
  Klik tombol "Install" (ikon tanda plus `+` berwarna hijau).
- Setelah terpasang, status awal biasanya "Disabled".

Langkah 5.3: Konfigurasi & Mengaktifkan Status
- Klik tombol "Edit" (ikon pensil berwarna biru) pada baris ekstensi.
- Di dalam halaman konfigurasi ekstensi:
  a. Ubah "Status" menjadi "Enabled" (Aktif).
  b. Atur biaya, zona geografis (Geo Zone), dan urutan tampilan (Sort Order).
- Klik tombol "Save" (ikon disket biru) di bagian atas untuk menyimpan.

-------------------------------------------------------------------------------
 6. TROUBLESHOOTING & VERIFIKASI
-------------------------------------------------------------------------------

1. Ekstensi Tidak Muncul di List Shipping?
   - Buka Extensions -> Installer, pastikan file telah ter-upload dengan benar.
   - Buka System -> Maintenance -> Modifiers (atau Dashboard), klik tombol
     Refresh Cache (ikon roda gigi / sapu) untuk memperbarui cache OpenCart.

2. Pesan Error "Permission Denied"?
   - Ulangi Langkah 4 (Menambahkan User Permission). Pastikan centang pada
     Access Permission dan Modify Permission sudah tersimpan untuk grup Admin.

3. Metode Pengiriman Tidak Tampil di Checkout?
   - Pastikan Status Ekstensi sudah "Enabled".
   - Pastikan berat/lokasi/zona alamat checkout cocok dengan aturan Geo Zone.
   - Pastikan produk di keranjang memiliki opsi "Requires Shipping = Yes".


===============================================================================
 SECTION II: ENGLISH VERSION
===============================================================================

-------------------------------------------------------------------------------
 1. FILES & FOLDER STRUCTURE TO UPLOAD
-------------------------------------------------------------------------------

Before installation, make sure you understand which files and folders need
to be uploaded to your OpenCart server.

An OpenCart shipping extension typically consists of backend admin files
(for management) and catalog files (for shipping rate calculations at checkout).

Example Extension Folder Structure (e.g., Advanced Shipping):

  advancedshipping/
  ├── admin/
  │   ├── controller/
  │   │   └── shipping/
  │   │       └── advancedshipping.php
  │   ├── language/
  │   │   └── en-gb/
  │   │       └── shipping/
  │   │           └── advancedshipping.php
  │   ├── model/
  │   │   └── shipping/
  │   │       └── advancedshipping.php
  │   └── view/
  │       └── template/
  │           └── shipping/
  │               └── advancedshipping.twig
  ├── catalog/
  │   ├── language/
  │   │   └── en-gb/
  │   │       └── shipping/
  │   │           └── advancedshipping.php
  │   └── model/
  │       └── shipping/
  │           └── advancedshipping.php
  └── install.json

Folder & Important File Breakdown:
- 'admin/' folder   : Contains controller, language, model, and views (twig)
                      for the configuration page in the admin dashboard.
- 'catalog/' folder : Contains calculation models and frontend store language
                      used during checkout.
- 'install.json'    : Official OpenCart metadata file containing extension name,
                      version, author, and automated installation scripts.

-------------------------------------------------------------------------------
 2. EXTENSION UPLOAD METHODS
-------------------------------------------------------------------------------

Choose one of the upload methods below:

-------------------------------------------------------------------------------
 [METHOD A] Upload via Admin Installer (.ocmod.zip) — RECOMMENDED
-------------------------------------------------------------------------------

1. Create the ZIP Installer File (.ocmod.zip):
   - Open your local extension folder (`upload/extension/advancedshipping/`).
   - Select the `install.json` file, `admin` folder, and `catalog` folder.
   - Compress/ZIP these items directly into a single zip file named:
     `advancedshipping.ocmod.zip`
   
   Note:
   `install.json`, `admin/`, and `catalog/` MUST be placed at the root level
   of the ZIP archive (do not enclose them in another parent folder inside zip).

2. Proceed to the Logging into Admin step (Section 3) and follow the Installer.


-------------------------------------------------------------------------------
 [METHOD B] Manual Upload via FTP / cPanel File Manager
-------------------------------------------------------------------------------

If you prefer manual file transfer without creating a ZIP file:

1. Open your FTP Client (FileZilla) or cPanel File Manager.
2. Locate the root directory of your OpenCart installation
   (usually `/public_html/` or `/var/www/html/`).
3. Upload the extension directory to the following path:

   Target Folder on Server:
   <root-opencart>/extension/advancedshipping/

4. Final structure on your server after upload:

   public_html/ (or your OpenCart root)
   └── extension/
       └── advancedshipping/
           ├── admin/
           ├── catalog/
           └── install.json

-------------------------------------------------------------------------------
 3. LOGGING INTO OPENCART ADMIN
-------------------------------------------------------------------------------

1. Open your web browser (Chrome, Firefox, Edge, Safari, etc.).
2. Type your OpenCart Store Admin URL into the address bar.
   Examples:
   - http://yourdomain.com/admin
   - https://yourstore.com/admin
3. Enter your Administrator Username and Password.
4. Click the "Login" button.
5. You will land on the OpenCart Admin Dashboard.

-------------------------------------------------------------------------------
 4. SETTING UP USER PERMISSIONS (ACCESS & MODIFY)
-------------------------------------------------------------------------------

To prevent "Access Denied" or "Permission Denied" errors when opening or saving
the extension settings, you MUST grant both Access Permission and Modify Permission
to your Administrator user group.

Step-by-step User Permission Instructions:

Step 4.1: Open User Groups Menu
- From the left sidebar menu, navigate to:
  System -> Users -> User Groups

Step 4.2: Edit Administrator Group
- Locate the "Administrator" row (or the group your account belongs to).
- Click the "Edit" button (blue pencil icon) on the right side of the row.

Step 4.3: Grant Access Permission
- Under the "Access Permission" list:
  a. Scroll down the path checklist and find the shipping extension path.
     Example paths:
     - extension/advancedshipping/shipping/advancedshipping
     - extension/shipping/advancedshipping
  b. Check the checkbox next to the extension path.
  c. Alternatively, click the "Select All" button below the Access Permission box.

Step 4.4: Grant Modify Permission
- Under the "Modify Permission" list:
  a. Locate the exact same extension path as above.
  b. Check the checkbox next to the extension path.
  c. Alternatively, click the "Select All" button below the Modify Permission box.

Step 4.5: Save Changes
- Click the "Save" button (blue disk icon) at the top right of the screen.
- A success message will appear: "Success: You have modified user groups!".

-------------------------------------------------------------------------------
 5. ACTIVATING & INSTALLING SHIPPING EXTENSION
-------------------------------------------------------------------------------

Step 5.1: Upload ZIP via Admin Installer (If using Method A)
- From the left navigation menu, go to:
  Extensions -> Installer
- Click the "Upload" button (blue upload icon) in the top right corner.
- Select `advancedshipping.ocmod.zip` from your computer.
- Wait for the upload progress bar to finish until a success message appears.

Step 5.2: Installing Extension under Shipping Extensions
- From the left navigation menu, select:
  Extensions -> Extensions
- In the "Choose the extension type" dropdown menu, select:
  "Shipping"
- Find your shipping extension from the list (e.g., "Advanced Shipping").
- If the extension is not yet installed:
  Click the "Install" button (green plus `+` icon).
- Once installed, the initial status will usually show as "Disabled".

Step 5.3: Configuring & Enabling Extension
- Click the "Edit" button (blue pencil icon) on the extension row.
- Inside the extension configuration panel:
  a. Change "Status" to "Enabled".
  b. Configure your shipping rates, Geo Zones, and Sort Order.
- Click the "Save" button (blue disk icon) at the top right to apply settings.

-------------------------------------------------------------------------------
 6. TROUBLESHOOTING & VERIFICATION
-------------------------------------------------------------------------------

1. Extension Not Showing in Shipping List?
   - Navigate to Extensions -> Installer to verify the file was uploaded properly.
   - Go to System -> Maintenance -> Modifiers (or Dashboard), click the
     Refresh Cache button (gear / broom icon) to update OpenCart cache.

2. "Permission Denied" Error Message?
   - Repeat Step 4 (Setting Up User Permissions). Ensure both Access Permission
     and Modify Permission are checked and saved for your Administrator group.

3. Shipping Method Not Appearing at Checkout?
   - Ensure Extension Status is set to "Enabled".
   - Ensure the checkout address matches the configured Geo Zone rules.
   - Confirm products in the cart have "Requires Shipping = Yes" set in product data.

===============================================================================
