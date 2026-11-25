KelonTongReva — Sistem Informasi Penjualan
Aplikasi toko online dan kasir (POS) berbasis PHP Native, MySQL/MariaDB, HTML, CSS, dan JavaScript. Project dapat dijalankan secara lokal menggunakan XAMPP.
1. Pengguna dan akun demo
Database contoh (database.sql) menyediakan akun berikut. Akun demo hanya untuk pengujian lokal; ganti password sebelum aplikasi dipakai sungguhan.
Admin
Nama
Email login
Password
Halaman login
Admin Tokoku
admin@tokoku.test
admin123
http://localhost/KelonTongReva/admin/login.php


Hak akses Admin meliputi dashboard, produk, kategori, pengguna, pesanan, pembayaran, voucher, pengaturan toko, dan laporan. Admin juga dapat mendaftarkan UID RFID/NFC pada akun pengguna.
Kasir
Akun
Nama pengguna
Email login
Password
Halaman login
Kasir 1
Rina Kasir
kasir@tokoku.test
kasir123
http://localhost/KelonTongReva/kasir/login.php
Kasir 2
Kasir 2
kasir2@tokoku.test
kasir123
http://localhost/KelonTongReva/kasir/login.php


Kedua akun memiliki role kasir dan masuk melalui halaman login kasir yang sama. Aktivitas transaksi dicatat berdasarkan akun kasir yang sedang login sehingga laporan dapat menampilkan rekap masing-masing kasir maupun gabungan.
Pembeli
Nama pembeli contoh yang tersedia setelah database.sql diimpor:

Budi Santoso

Bagian ini sengaja hanya menampilkan nama pembeli, tanpa email, nomor telepon, atau password. Pembeli lain dapat membuat akun melalui menu Daftar Member pada halaman toko.
2. Fitur aplikasi
Admin
Dashboard ringkasan penjualan dan stok.
Mengelola produk, kategori, pengguna, dan voucher.
Mengelola pesanan, status pengiriman, dan pembatalan/void transaksi.
Memeriksa bukti pembayaran dan memverifikasi pembayaran.
Mengatur identitas toko, pajak, pembulatan, rekening, dan footer struk.
Melihat laporan penjualan berdasarkan rentang tanggal.
Mengunduh laporan PDF A4 dan mengekspor CSV.
Melihat rekap penjualan per kasir dan laporan gabungan Kasir 1 + Kasir 2.
Kasir 1 dan Kasir 2
Menggunakan halaman POS yang sama dengan akun masing-masing.
Mencari produk melalui nama atau barcode.
Mengelola keranjang, diskon, voucher, dan pembayaran.
Mencatat transaksi tunai, debit, dan QRIS.
Membuka dan menutup shift kasir.
Menahan transaksi dan melanjutkannya kembali.
Melihat riwayat transaksi dan mencetak struk thermal 80 mm.
Transaksi terhubung dengan identitas kasir yang sedang login.
Pembeli
Melihat katalog, kategori, pencarian produk, dan ulasan.
Mengelola keranjang dan wishlist.
Menggunakan voucher dan checkout.
Memilih metode pembayaran yang tersedia: transfer, QRIS, atau COD.
Mengunggah bukti pembayaran jika diminta.
Melihat status dan riwayat pesanan serta invoice.
3. Laporan penjualan PDF gabungan dua kasir
Login sebagai Admin.
Buka menu Laporan.
Tentukan tanggal awal dan tanggal akhir.
Pilih tampilan seluruh kasir/gabungan jika filter kasir tersedia.
Klik Tampilkan, lalu pilih Unduh Laporan PDF atau tombol PDF gabungan.
Periksa total omzet, jumlah transaksi, rincian transaksi, metode pembayaran, rekap per kasir, produk terlaris, dan selisih kas shift.

Laporan gabungan menyatukan transaksi Kasir 1 dan Kasir 2 dalam satu dokumen. Identitas kasir tetap ditampilkan pada rincian transaksi agar transaksi masing-masing tetap dapat dibedakan. Data laporan mengikuti rentang tanggal dan status transaksi yang diproses oleh halaman laporan.
4. QRIS pada halaman toko
File gambar QRIS bawaan project berada di uploads/QRIS.jpg. Halaman toko dapat menampilkan gambar QRIS saat pembeli memilih metode pembayaran QRIS. Pastikan file tersebut tidak dihapus atau dipindahkan.

Penting: tampilan QRIS bukan bukti bahwa pembayaran sudah diterima secara otomatis. Admin tetap harus memeriksa bukti atau mutasi pembayaran melalui kanal merchant sebelum memverifikasi pesanan.
5. Teknologi dan kebutuhan
Windows 10/11 atau sistem operasi lain yang mendukung XAMPP.
XAMPP dengan PHP 8.2 atau lebih baru.
MySQL/MariaDB.
Apache, ekstensi PDO MySQL dan Fileinfo aktif.
Browser modern.
Reader RFID/NFC USB mode keyboard-wedge jika ingin memakai login kartu.
6. Struktur folder
KelonTongReva/
├── admin/                  # Dashboard dan pengelolaan admin
├── akun/                   # Login, daftar, dan halaman pembeli
├── kasir/                  # Login dan halaman POS kasir
├── assets/                 # CSS dan aset tampilan
├── uploads/
│   ├── produk/              # Foto produk
│   ├── payments/            # Bukti pembayaran
│   └── QRIS.jpg             # Gambar QRIS toko
├── config.php               # Konfigurasi koneksi database
├── database.sql             # Database dan data contoh
├── database_upgrade_existing.sql
├── database_upgrade_rfid.sql
├── lib.php                  # Fungsi umum aplikasi
├── index.php                # Halaman toko utama
├── cari-barang.php
├── struk.php
└── README.md
7. Instalasi menggunakan XAMPP
Ekstrak folder KelonTongReva ke C:\xampp\htdocs\KelonTongReva.
Buka XAMPP Control Panel, lalu jalankan Apache dan MySQL.
Buka http://localhost/phpmyadmin.
Pilih menu Import, pilih file database.sql, lalu jalankan import.
File SQL membuat database tokoku, tabel, dan akun demo termasuk Admin, Kasir 1, Kasir 2, serta satu pembeli contoh. Gunakan database kosong untuk instalasi baru, karena skrip menghapus tabel dengan nama yang sama sebelum membuat ulang.
Buka config.php dan sesuaikan host, nama database, username, dan password MySQL jika berbeda dari konfigurasi default XAMPP.
Buka http://localhost/KelonTongReva/.
Pastikan folder uploads/produk/ dan uploads/payments/ dapat ditulisi oleh Apache.

Konfigurasi database default project: host 127.0.0.1, database tokoku, user root, password kosong.
8. Login aplikasi
Admin: http://localhost/KelonTongReva/admin/login.php
Kasir 1 dan Kasir 2: http://localhost/KelonTongReva/kasir/login.php
Pembeli: http://localhost/KelonTongReva/akun/login.php
Halaman toko: http://localhost/KelonTongReva/

Untuk pembeli, gunakan pendaftaran member untuk membuat akun baru. Nama pembeli contoh dari database adalah Budi Santoso.
9. Login RFID/NFC
Project mendukung reader USB RFID/NFC yang mengirim UID kartu sebagai ketikan keyboard (keyboard-wedge) dan mengakhiri input dengan Enter. Admin dapat mengisi UID unik untuk akun admin, kasir, atau pembeli dari menu Pengguna. Setelah UID tersimpan, pengguna dapat menempelkan kartu pada halaman login sesuai role. Login email/password tetap tersedia.

Untuk database lama, backup database terlebih dahulu lalu jalankan database_upgrade_rfid.sql satu kali jika kolom RFID belum tersedia. Instalasi baru yang memakai database.sql tidak perlu menjalankan migrasi RFID lagi.
10. Pembayaran dan verifikasi
Pembayaran online transfer/QRIS dapat menunggu bukti pembayaran dari pembeli.
Admin memeriksa bukti pembayaran sebelum mengubah status menjadi terverifikasi.
COD mengikuti alur pesanan tanpa verifikasi transfer.
Pada POS, kasir wajib memastikan pembayaran benar-benar diterima sebelum menyelesaikan transaksi.
Konfirmasi QRIS pada POS merupakan konfirmasi manual, bukan koneksi otomatis ke penyedia pembayaran.
11. Cetak struk
Struk dirancang untuk printer thermal 80 mm. Dari halaman transaksi, gunakan tombol cetak dan pilih printer serta ukuran kertas yang sesuai pada dialog browser. Hasil cetak dapat berbeda sesuai driver printer. Tombol kembali pada struk mengarah ke halaman kasir.
12. Database dan migrasi
Nama database: tokoku. Tabel utama meliputi users, categories, products, shifts, orders, order_items, vouchers, held_transactions, stock_movements, wishlists, reviews, dan settings.

Jangan mengimpor ulang database.sql ke database lama yang berisi data penting karena skrip instalasi menghapus dan membuat ulang tabel. Backup database terlebih dahulu. Gunakan file database_upgrade_existing.sql atau database_upgrade_rfid.sql hanya sesuai kebutuhan dan pastikan migrasi belum pernah dijalankan.
13. Keamanan
Password disimpan menggunakan password_hash().
Query database menggunakan PDO prepared statements.
Form POST menggunakan token CSRF.
Sesi menggunakan pengaturan cookie HttpOnly dan SameSite.
Ganti password akun demo sebelum aplikasi digunakan di jaringan publik.
Untuk penggunaan produksi, aktifkan HTTPS, gunakan password database yang kuat, batasi hak akses database, dan lakukan backup rutin.
Jangan membagikan bukti pembayaran atau data pribadi pembeli secara terbuka.



KelonTongReva — Belanja Mudah, Kasir Cepat, Stok Terkelola.
