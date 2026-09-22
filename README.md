Nama Peserta: Davin Khalinabag Nur Pasa
Kelas: XII RPL 1
Judul Project: Sistem Booking & Parkir Danau Toba
Studi Kasus: Kawasan wisata Danau Toba memiliki banyak titik parkir (Ajibata, Tomok, Parapat, dll) yang masih dikelola manual, sehingga pengunjung kesulitan mengetahui slot yang tersedia dan harus mengantre untuk membayar di lokasi. Aplikasi ini dibuat agar pengunjung bisa memesan (booking) slot parkir secara online dan membayar lebih dulu, sementara petugas di lapangan tetap bisa mencatat kendaraan yang datang langsung (walk-in) tanpa booking.

Teknologi:
- Frontend: HTML, CSS (assets/css/style.css), JavaScript (assets/js/sound.js)
- Backend: PHP native (tanpa framework), menggunakan PDO
- Database: MySQL

Fitur:
- Registrasi & login untuk pengunjung
- Melihat daftar dan detail lokasi parkir (tarif per jenis kendaraan, slot tersedia)
- Booking parkir (pilih tanggal, jam datang, jenis kendaraan) dengan tarif flat sekali bayar
- Pembayaran (transfer bank/e-wallet/QRIS) dengan upload bukti bayar
- Riwayat booking milik pengunjung
- Dashboard Petugas: memproses booking online (ubah status dibayar - selesai) khusus di lokasi tugasnya
- Parkir Manual (walk-in) oleh Petugas: input plat nomor & jenis kendaraan tanpa booking, cetak struk
- Dashboard Admin: kelola semua lokasi parkir dan semua booking di seluruh lokasi
- Dashboard Owner: semua fitur Admin, ditambah melihat total pendapatan dan mengelola akun Admin/Petugas
- Sistem 4 role (Owner, Admin, Petugas, User) dengan hak akses berbeda-beda

Cara Menjalankan:
1. Salin folder project ini ke folder web server (misalnya folder htdocs di XAMPP)
2. Buka phpMyAdmin, buat database baru, lalu import file database/parkir_danau_toba.sql
3. Cek pengaturan koneksi database di config/database.php (default: host localhost, user root, password kosong) dan sesuaikan jika perlu
4. Buka aplikasi lewat browser, contoh: http://localhost/parkir-danau-toba/

Database:
database/parkir_danau_toba.sql
(Tabel utama: users, lokasi_parkir, booking, pembayaran)

Dokumentasi:
docs/
(Catatan: folder docs/ berisi PDF analisis kebutuhan, perancangan, dokumentasi program, pengujian, debugging, dan evaluasi sesuai ketentuan UKK - lengkapi folder ini sebelum dikumpulkan)

Demo:
-

Akun Pengujian:
Owner
Email: owner@parkirdanautoba.com
Password: password123

Admin
Email: admin@parkirdanautoba.com
Password: password123

Petugas (Lokasi Ajibata)
Email: petugas.ajibata@parkirdanautoba.com
Password: password123

Petugas (Lokasi Tomok)
Email: petugas.tomok@parkirdanautoba.com
Password: password123

Petugas (Lokasi Parapat)
Email: petugas.parapat@parkirdanautoba.com
Password: password123

User/Pengunjung
Daftar akun baru sendiri lewat halaman Register, atau gunakan salah satu akun user contoh yang ada di database (jika tersedia)

Known Issues:
- Password akun contoh di atas masih default, sebaiknya diganti setelah instalasi
- Gambar asli lokasi parkir belum lengkap, folder assets/img/ perlu diisi sesuai nama file yang dirujuk di database
- Pembayaran masih berupa konfirmasi manual (upload bukti transfer), belum terhubung ke payment gateway asli
- Belum ada validasi CSRF token pada form# ukk-rpl-parkir-danau-toba
