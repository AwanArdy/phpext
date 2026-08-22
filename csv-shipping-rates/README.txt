CARA PAKAI 4 FILE CSV SHIPPING RATES (skenario: berbasis Quantity, geo zone Indonesia)
======================================================================================

IMPORT
------
Admin -> Extensions -> Advanced Shipping -> tab Rates -> tombol Import
-> pilih salah satu file CSV -> rate baru otomatis ditambahkan
(kolom rate_id sengaja dikosongkan, jadi semua masuk sebagai data BARU).

PENTING - GANTI GEO ZONE ID
--------------------------
Semua file memakai geo zone id "1" di kolom "shipping".
Cek ID geo zone Indonesia Anda dulu:

    SELECT geo_zone_id FROM oc_geo_zone WHERE name LIKE '%indonesia%';

Jika bukan 1, ganti angka kuncinya pada JSON kolom shipping,
contoh dari  "..."1"":{...}"  menjadi  "..."7"":{...}".
Alternatif tanpa edit file: biarkan alamat uji jatuh ke zona lain dan isi
biaya pada geo zone id 0 = "All Other Zones".

CATATAN BAHASA
--------------
Kolom "name" memuat dua bahasa (en-gb & id-id). Kode bahasa harus sama
dengan kolom "code" di tabel oc_language toko Anda.

RINGKASAN ISI
-------------
1-cart-quantity-bertingkat.csv
    Cart Quantity bertingkat (Final Cost = Single):
      qty 1-3   -> Rp10.000 flat
      qty 4-10  -> Rp15.000 flat
      qty >10   -> Rp20.000 flat

2-cart-quantity-kumulatif.csv
    Cart Quantity kumulatif (Final Cost = Cumulative):
      item pertama Rp8.000, tiap tambahan item +Rp2.000
      (rates: max=1 cost=8000; max=~ cost=2000 per=1)

3-product-quantity-bertingkat.csv
    Product Quantity bertingkat per baris produk:
      qty produk <=5  -> Rp8.000
      qty produk  >5  -> Rp12.000

4-cart-quantity-persen-minmax.csv
    Cart Quantity dengan biaya persen dari subtotal:
      cost 5% dari subtotal, dibatasi Min Rp15.000 / Max Rp100.000
      (kolom cost min/max/add pada rate editor)

PENGUJIAN SESUAI KRONOLOGI ANDA
-------------------------------
1. Pastikan Status ekstensi Enabled dan Test Mode OFF (tab Settings).
2. Import CSV -> klik Clear Cache.
3. Keranjang: produk dengan "Requires Shipping = Yes".
4. Checkout dengan alamat Surabaya, Jawa Timur, Indonesia.
5. Metode muncul sesuai nama di kolom "name"; nilai sesuai tier qty.
6. Jika tidak muncul: aktifkan Debug lalu cek
   system/storage/logs/advancedshipping.txt
