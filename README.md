# Paymenku untuk WooCommerce

Plugin pembayaran Paymenku untuk WooCommerce 8+ (PHP 7.4+). Satu gateway
dengan pilihan kanal di checkout: QRIS, Virtual Account, e-wallet, dan gerai
ritel.

## Struktur

```
paymenku-woocommerce.php          Bootstrap, deklarasi HPOS
includes/
  class-paymenku-api.php          Klien API + envelope/galat
  class-paymenku-api-exception.php
  class-paymenku-transport.php    Interface + transport WP HTTP
  class-paymenku-webhook.php      Verifikasi HMAC
  class-wc-gateway-paymenku.php   Gateway WooCommerce
  class-paymenku-plugin.php       Hook, REST webhook, tampilan
assets/
  icon.png
  css/frontend.css
tests/
  bootstrap.php                   Fake transport
  run.php                         Uji tanpa WordPress
```

## Alur

1. `process_payment()` memanggil `POST /transaction/create` dengan
   `reference_id = WC-{order_id}` dan `Idempotency-Key`.
2. `pay_url` disimpan di meta dan pelanggan diarahkan ke halaman pembayaran.
3. `POST /wp-json/paymenku/v1/webhook` memverifikasi
   `X-PaymenKu-Signature = HMAC-SHA256(timestamp + "." + body, secret)`.
4. Status `paid` memanggil `payment_complete()`. Status `expired`, `failed`,
   dan `cancelled` menutup pesanan yang belum dibayar. `refunded` mengubah
   status pesanan menjadi refunded.
5. Refund manual memanggil `POST /transaction/refund`.

## Meta pesanan

| Meta | Isi |
|---|---|
| `_paymenku_trx_id` | ID transaksi |
| `_paymenku_reference_id` | `WC-{order_id}` |
| `_paymenku_channel` | Kode kanal |
| `_paymenku_pay_url` | URL halaman pembayaran |
| `_paymenku_payment_info` | Array QR/VA/kode bayar |
| `_paymenku_amount` | Nominal yang dikirim |
| `_paymenku_paid_at` | Waktu pembayaran diterima |
| `_paymenku_paid_amount` | Nominal dari webhook |
| `_paymenku_is_sandbox` | `yes`/`no` |

## Pengujian

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/run.php
```

Uji mencakup: header dan body permintaan, idempotency, parsing envelope,
galat 4xx, envelope `success:false` pada HTTP 200, respons non-JSON, escape
path, refund, daftar kanal, dan verifikasi tanda tangan webhook.

## Uji integrasi

Plugin diuji pada WordPress 7.1.3 + WooCommerce 11.2.1 (PHP 8.1) dengan
35 pemeriksaan: pendaftaran gateway, pembuatan transaksi (header, body,
idempotency), penyimpanan meta, penolakan tanda tangan salah, pembayaran via
webhook, idempotensi webhook, expired pada pesanan pending, refund, render
QR/VA, dan aksi Bayar di akun.

## Batasan 0.1.0

- Checkout klasik; checkout block belum didukung.
- Hanya IDR.
- Satu transaksi aktif per pesanan. Bila pelanggan membuka ulang checkout
  selama pesanan masih pending, plugin memakai `pay_url` yang sama.
- Refund mengikuti dukungan kanal; tidak semua kanal bisa refund.
- Tidak ada retry otomatis pada 429/5xx.

## Lisensi

MIT.
