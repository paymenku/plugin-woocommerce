=== Paymenku untuk WooCommerce ===
Contributors: paymenku
Tags: qris, virtual account, ewallet, payment gateway, indonesia
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 8.0
WC tested up to: 11.2
Stable tag: 0.1.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Terima QRIS, Virtual Account, e-wallet, dan gerai ritel lewat Paymenku.

== Description ==

Plugin pembayaran Paymenku untuk WooCommerce. Satu gateway dengan pilihan kanal
di halaman checkout, verifikasi webhook bertanda tangan, dan refund dari
dashboard pesanan.

Fitur:

* QRIS dan QRIS Custom
* Virtual Account: BCA, BNI, BRI, Mandiri, Permata, CIMB, Danamon, BSI, OCBC, Maybank, Muamalat, BNC
* E-wallet: DANA, OVO, ShopeePay
* Gerai ritel: Indomaret, Alfamart
* Status pesanan otomatis dari webhook (bertanda tangan HMAC-SHA256)
* Halaman instruksi pembayaran: QR, nomor VA, kode bayar
* Refund dari halaman pesanan (tergantung dukungan kanal)
* Mode sandbox dan produksi dalam satu pengaturan
* Log debug opsional

Alur pembayaran:

1. Pelanggan memilih kanal di checkout.
2. Plugin membuat transaksi di Paymenku dan mengarahkan ke halaman pembayaran.
3. Paymenku mengirim webhook saat status berubah.
4. Pesanan diperbarui menjadi processing atau completed.

== Installation ==

1. Unggah folder `paymenku-woocommerce` ke `/wp-content/plugins/`, atau unggah berkas ZIP lewat Plugins > Add New > Upload Plugin.
2. Aktifkan plugin.
3. Buka WooCommerce > Settings > Payments > Paymenku.
4. Isi API key dari dashboard Paymenku (`sk_test_...` untuk sandbox).
5. Pilih kanal yang aktif di akun Anda.
6. Isi Webhook secret, lalu daftarkan URL webhook yang tampil di halaman pengaturan ke dashboard Paymenku.
7. Simpan, lalu uji satu pesanan.

== Frequently Asked Questions ==

= Mengapa webhook membalas 401? =

Webhook secret di plugin berbeda dengan yang ada di dashboard, atau body diubah
sebelum verifikasi. Pastikan keduanya sama persis.

= Mengapa status pesanan tidak berubah? =

Periksa URL webhook di dashboard Paymenku dan log debug plugin
(WooCommerce > Status > Logs, sumber `paymenku`). Pastikan pesanan memakai
metode pembayaran Paymenku.

= Mata uang apa yang didukung? =

Hanya IDR. Gateway disembunyikan bila mata uang toko bukan IDR.

= Apakah refund selalu berhasil? =

Tidak. Dukungan refund berbeda per kanal. Bila kanal tidak mendukung, API
mengembalikan galat dan pesanan tidak berubah.

= Apakah plugin mendukung checkout block? =

Versi 0.1.0 mendukung checkout klasik. Checkout block akan ditambahkan pada
rilis berikutnya.

== External services ==

Plugin ini terhubung ke API Paymenku (https://paymenku.com/api/v1) untuk
membuat transaksi, mengecek status, dan refund. Data yang dikirim: nomor
pesanan, nominal, nama dan email pelanggan, nomor telepon, serta rincian item.

* Syarat layanan: https://paymenku.com/syarat-ketentuan
* Kebijakan privasi: https://paymenku.com/kebijakan-privasi

== Changelog ==

= 0.1.0 =
* Rilis awal.
* Diuji pada WordPress 7.1.3 + WooCommerce 11.2.1: 35 pemeriksaan integrasi lulus.
* Gateway dengan pilihan kanal di checkout klasik.
* Webhook bertanda tangan dan pembaruan status otomatis.
* Refund dari dashboard pesanan.
* Halaman instruksi pembayaran QRIS/VA/gerai ritel.
