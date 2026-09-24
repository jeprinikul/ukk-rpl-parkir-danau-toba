# Website E-Commerce Parkir Danau Toba

Sistem booking & pembayaran parkir untuk kawasan wisata Danau Toba, dibangun dengan PHP native (PDO) + MySQL, tanpa framework, sehingga mudah dijalankan di XAMPP/Laragon/hosting biasa.

## 🗂️ Struktur Folder
```
parkir-danau-toba/
├── admin/                  # Panel admin (dashboard, kelola lokasi & booking)
├── assets/css/style.css    # Styling
├── assets/img/             # Gambar lokasi parkir (upload manual)
├── config/database.php     # Koneksi database (PDO)
├── database/parkir_danau_toba.sql   # File SQL untuk import
├── includes/               # Header, footer, functions.php
├── uploads/bukti_bayar/    # Upload bukti transfer pengguna
├── index.php               # Beranda (daftar lokasi parkir)
├── detail_lokasi.php       # Detail lokasi + tombol pesan
├── register.php / login.php / logout.php
├── booking.php / proses_booking.php
├── pembayaran.php
└── riwayat.php
```

## ⚙️ Cara Instalasi

1. **Copy folder** `parkir-danau-toba` ke folder web server Anda (misal `htdocs` di XAMPP).
2. **Buat database**: buka phpMyAdmin, import file `database/parkir_danau_toba.sql`.
   Ini akan membuat database `parkir_danau_toba` beserta tabel dan data contoh.
3. **Atur koneksi database** di `config/database.php` jika perlu (default: host `localhost`, user `root`, password kosong).
4. **Jalankan** via browser: `http://localhost/parkir-danau-toba/`.

## 🔑 Role & Akun Default

Sistem memiliki **4 peran (role)**:

| Role | Deskripsi | Akses |
|---|---|---|
| **Owner** | Pemilik usaha, akses tertinggi | Semua fitur Admin + kelola akun Admin/Petugas + lihat total pendapatan |
| **Admin** | Pengelola operasional | Kelola semua lokasi parkir, kelola semua booking (semua lokasi) |
| **Petugas** | Staf lapangan per lokasi | Hanya lihat & proses booking (dibayar → selesai) di **satu lokasi** yang ditugaskan padanya. Tidak bisa mengubah data lokasi/harga, tidak bisa membatalkan booking |
| **User** | Pengunjung/pelanggan | Booking parkir, bayar, lihat riwayat sendiri |

Akun contoh yang sudah tersedia di `database/parkir_danau_toba.sql` (semua password: **`password123`**):

| Role | Email |
|---|---|
| Owner | `owner@parkirdanautoba.com` |
| Admin | `admin@parkirdanautoba.com` |
| Petugas (Ajibata) | `petugas.ajibata@parkirdanautoba.com` |
| Petugas (Tomok) | `petugas.tomok@parkirdanautoba.com` |
| Petugas (Parapat) | `petugas.parapat@parkirdanautoba.com` |

> ⚠️ **Segera ganti semua password default** setelah instalasi (lewat menu **Kelola Akun Staf** sebagai Owner).

### Upgrade dari versi lama (hanya punya role user & admin)
Jika Anda sebelumnya sudah meng-install versi lama database (tanpa Owner/Petugas), jangan import ulang `parkir_danau_toba.sql` (nanti data lama hilang). Cukup jalankan file **`database/migrasi_role_staff.sql`** di phpMyAdmin, lalu sesuaikan datanya.

### Upgrade dari tarif per jam ke tarif flat (sekali bayar)
Database lama yang masih memakai kolom `harga_per_jam_*` / `tarif_per_jam` / `durasi_jam` perlu menjalankan **`database/migrasi_tarif_flat.sql`** satu kali di phpMyAdmin. Angka harga lama tidak berubah, hanya nama kolomnya; kolom `durasi_jam` dihapus. Setelah itu atur ulang harga flat lewat menu Kelola Lokasi & Kelola Tarif Parkir.

### Upgrade untuk mengaktifkan fitur Parkir Manual (walk-in)
Jika database Anda sudah ada tetapi belum punya tabel `tb_tarif`, `tb_area_parkir`, `tb_kendaraan`, `tb_transaksi`, dan `tb_log_aktivitas`, jalankan file **`database/migrasi_parkir_manual.sql`** di phpMyAdmin. Instalasi baru tidak perlu menjalankan file ini karena sudah termasuk dalam `parkir_danau_toba.sql`.

## 🚀 Fitur

**Untuk Pengunjung/User:**
- Melihat & mencari daftar lokasi parkir di kawasan Danau Toba
- Melihat detail lokasi (tarif flat per jenis kendaraan, slot tersedia)
- Registrasi & login
- Booking parkir (pilih tanggal, perkiraan jam datang, jenis kendaraan) dengan total bayar otomatis — **tarif flat sekali bayar, parkir bebas tanpa batas jam**
- Pembayaran (transfer bank / e-wallet / QRIS) dengan upload bukti transfer
- Riwayat booking pribadi beserta status

**Untuk Petugas (staf lapangan per lokasi):**
- Dashboard ringkas: slot tersedia, booking hari ini, antrean booking lokasinya
- Memproses booking online: ubah status dari "Dibayar" → "Selesai" (check-in/check-out) khusus di lokasi tugasnya, lewat menu **Kelola Booking**
- **Parkir Manual (walk-in)**: mencatat pengunjung yang datang langsung tanpa booking online — input plat nomor & jenis kendaraan, pilih area parkir di lokasi tugasnya, lalu saat kendaraan keluar sistem mencatat pembayaran (tarif flat sekali bayar, tanpa hitungan per jam) tunai/QRIS, dan mencetak struk. Dikerjakan lewat **Dashboard Petugas** (`petugas/dashboard.php`)
- Tidak dapat mengubah data lokasi/harga maupun membatalkan booking

**Untuk Admin:**
- Dashboard statistik (total lokasi, booking, pengunjung, booking pending)
- Kelola lokasi parkir (tambah, edit, hapus, atur harga & kapasitas) — semua lokasi
- Kelola booking (lihat semua booking di semua lokasi, ubah status apapun termasuk pembatalan)

**Untuk Owner:**
- Semua fitur Admin, ditambah:
- Melihat **total pendapatan** di dashboard
- **Kelola Akun Staf**: tambah/edit/nonaktifkan/hapus akun Admin & Petugas, serta menugaskan Petugas ke lokasi tertentu

## 🗃️ Struktur Database

- `users` — data pengguna & admin
- `lokasi_parkir` — data lokasi parkir, harga per jenis kendaraan, kapasitas & slot
- `booking` — data pemesanan parkir
- `pembayaran` — data pembayaran per booking

## 📝 Catatan Pengembangan Lanjutan

- Tambahkan gambar asli ke folder `assets/img/` sesuai nama file di database (misal `parapat.jpg`).
- Untuk produksi, integrasikan payment gateway sungguhan (Midtrans/Xendit) menggantikan alur konfirmasi manual admin.
- Tambahkan validasi CSRF token untuk keamanan form tambahan.
