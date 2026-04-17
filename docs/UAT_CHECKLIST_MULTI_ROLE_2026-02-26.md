# UAT Checklist Multi-Role (Admin, Mitra, Consumer, Affiliate, Penjual)

Tanggal: 26 Februari 2026  
Tujuan: validasi end-to-end pasca perbaikan bug sistem dan memastikan tidak ada regresi lintas role.

## 1) Prasyarat UAT

- Seed data minimal tersedia: 1 admin, 1 mitra, 1 consumer, 1 affiliate aktif, 1 penjual (consumer mode farmer_seller).
- Minimal 1 produk admin aktif, 1 produk mitra aktif, 1 produk penjual P2P aktif.
- Wallet balance tersedia untuk skenario checkout dan withdraw.
- Queue/notification worker aktif saat pengujian notifikasi.
- Jam server dan timezone environment sudah benar.

## 2) Checklist Admin

- [x] Dashboard admin tampil tanpa error dan metrik utama terisi.
- [x] Menu Keuangan: ringkasan admin wallet sinkron dengan transaksi admin.
- [x] Menu Keuangan: transaksi consumer tidak tercampur ke ringkasan admin.
- [x] Withdraw: approve request berhasil mengubah status `pending -> approved`.
- [x] Withdraw: mark paid berhasil membuat ledger debit user (`withdrawal`) dan debit admin (`admin_payout`).
- [x] Withdraw: mark paid gagal jika saldo admin tidak cukup.
- [x] Procurement: order `paid` tidak bisa langsung `cancelled` (harus alur refund).
- [x] Konten & Promo: pengumuman aktif sesuai `starts_at/ends_at` real time.

## 3) Checklist Mitra

- [x] Aktivasi produk hasil pengadaan admin bisa ubah harga dan tersimpan.
- [x] Toggle affiliate pada aktivasi produk bekerja sesuai pilihan user.
- [x] Validasi komisi: jika affiliate nonaktif, field komisi tidak wajib.
- [x] Validasi komisi: jika affiliate aktif, field komisi wajib dan tersimpan benar.
- [x] Produk mitra terjual menyebabkan stok berkurang sesuai qty.
- [x] Halaman Pesanan Mitra sudah tanpa aksi/kolom `Siap Packing` lama.
- [x] Aksi `Kirim` order mengubah status dan mengirim notifikasi ke buyer.
- [x] API procurement mitra tetap JSON walau request tanpa header `Accept: application/json`.

## 4) Checklist Consumer

- [x] Register user baru wajib set lokasi (provinsi/kota, opsional kecamatan).
- [x] Profil consumer menampilkan akses `Set Lokasi` dengan jelas.
- [x] Checkout normal berjalan (transfer/saldo sesuai mode).
- [x] Klik `Pantau Pesanan` mengarah ke halaman `Pesanan Saya`.
- [x] Saat mitra kirim order, consumer menerima notifikasi status pengiriman.

## 5) Checklist Affiliate

- [x] Komisi hanya masuk untuk order dengan affiliate valid.
- [x] Order non-affiliate tidak memotong nominal penerimaan seller sebagai komisi affiliate.
- [x] Riwayat komisi dan saldo affiliate konsisten terhadap ledger.
- [x] Withdraw affiliate hanya bisa jika policy role/mode terpenuhi.

## 6) Checklist Penjual (P2P)

- [x] Flow penjual P2P tetap terpisah dari jalur mitra B2B.
- [x] Pengajuan mitra dari user yang sedang affiliate/penjual menampilkan warning perpindahan status.
- [x] Setelah approved jadi mitra, mode consumer lama (affiliate/penjual) sudah direset.

## 7) Rekonsiliasi Keuangan Lintas Role

- [x] Ambil 1 order store_online ber-affiliate dan 1 order store_online non-affiliate.
- [x] Cocokkan nilai `orders.total_amount` dengan split settlement di `order_settlements`.
- [x] Cocokkan wallet seller, affiliate, admin terhadap transaksi pada `wallet_transactions`.
- [x] Pastikan tidak ada double credit dan tidak ada potongan liar pada wallet buyer/admin.

## 8) Catatan Risiko yang Masih Perlu Dipantau

- Validasi visual UI responsif lintas device untuk halaman review dispute/refund masih perlu cek manual di browser.
- Validasi notifikasi real-time di environment dengan worker queue live masih perlu cek manual.
