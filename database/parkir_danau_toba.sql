-- =========================================================
-- DATABASE: parkir_danau_toba
-- Sistem Booking & Pembayaran Parkir Kawasan Wisata Danau Toba
-- =========================================================

CREATE DATABASE IF NOT EXISTS parkir_danau_toba
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE parkir_danau_toba;

-- ---------------------------------------------------------
-- Tabel: users
-- Role:
--   user    -> pengunjung/pelanggan yang memesan parkir
--   petugas -> staf lapangan, hanya kelola booking di lokasi yang ditugaskan
--   admin   -> kelola semua lokasi, booking, dan akun petugas
--   owner   -> akses penuh, termasuk kelola akun admin & petugas, laporan keuangan
-- ---------------------------------------------------------
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  no_hp VARCHAR(20) DEFAULT NULL,
  alamat VARCHAR(255) DEFAULT NULL,
  role ENUM('user','petugas','admin','owner') NOT NULL DEFAULT 'user',
  lokasi_id INT DEFAULT NULL COMMENT 'Lokasi parkir yang ditugaskan, khusus role petugas',
  status_akun ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Tabel: lokasi_parkir
-- ---------------------------------------------------------
CREATE TABLE lokasi_parkir (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama_lokasi VARCHAR(150) NOT NULL,
  alamat VARCHAR(255) NOT NULL,
  deskripsi TEXT,
  -- Harga FLAT sekali bayar per kendaraan (bukan per jam), parkir bebas tanpa batas waktu
  harga_motor DECIMAL(10,2) NOT NULL DEFAULT 3000,
  harga_mobil DECIMAL(10,2) NOT NULL DEFAULT 5000,
  harga_bus DECIMAL(10,2) NOT NULL DEFAULT 15000,
  kapasitas INT NOT NULL DEFAULT 50,
  slot_tersedia INT NOT NULL DEFAULT 50,
  gambar VARCHAR(255) DEFAULT 'default.jpg',
  status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tambahkan foreign key lokasi_id di users (petugas ditugaskan ke satu lokasi)
ALTER TABLE users
  ADD CONSTRAINT fk_users_lokasi FOREIGN KEY (lokasi_id) REFERENCES lokasi_parkir(id) ON DELETE SET NULL;

-- ---------------------------------------------------------
-- Tabel: booking
-- ---------------------------------------------------------
CREATE TABLE booking (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode_booking VARCHAR(20) NOT NULL UNIQUE,
  user_id INT NOT NULL,
  lokasi_id INT NOT NULL,
  plat_nomor VARCHAR(20) NOT NULL,
  jenis_kendaraan ENUM('motor','mobil','bus') NOT NULL,
  tanggal_booking DATE NOT NULL,
  jam_masuk TIME NOT NULL,
  total_harga DECIMAL(10,2) NOT NULL,
  status ENUM('pending','dibayar','selesai','dibatalkan') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (lokasi_id) REFERENCES lokasi_parkir(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Tabel: pembayaran
-- ---------------------------------------------------------
CREATE TABLE pembayaran (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL,
  metode_pembayaran ENUM('transfer_bank','e_wallet','qris') NOT NULL,
  jumlah_bayar DECIMAL(10,2) NOT NULL,
  bukti_transfer VARCHAR(255) DEFAULT NULL,
  status ENUM('menunggu','berhasil','gagal') NOT NULL DEFAULT 'menunggu',
  tanggal_bayar TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES booking(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- DATA AWAL
-- ---------------------------------------------------------

-- Lokasi parkir contoh di kawasan Danau Toba
INSERT INTO lokasi_parkir (nama_lokasi, alamat, deskripsi, harga_motor, harga_mobil, harga_bus, kapasitas, slot_tersedia, gambar) VALUES
('Parkir Pelabuhan Ajibata', 'Ajibata, Toba, Sumatera Utara', 'Area parkir dekat pelabuhan penyeberangan ke Pulau Samosir, dekat dengan kapal ferry.', 3000, 5000, 15000, 100, 100, 'ajibata.jpg'),
('Parkir Pelabuhan Tomok', 'Tomok, Pulau Samosir, Sumatera Utara', 'Area parkir dekat pelabuhan Tomok, akses ke pasar oleh-oleh dan Makam Raja Sidabutar.', 3000, 5000, 15000, 80, 80, 'tomok.jpg'),
('Parkir Tepi Danau Parapat', 'Parapat, Kabupaten Simalungun, Sumatera Utara', 'Area parkir di kawasan wisata tepi danau Parapat, dekat pantai pasir putih.', 4000, 6000, 20000, 120, 120, 'parapat.jpg'),
('Parkir Bukit Holbung', 'Desa Sionggang, Kabupaten Toba, Sumatera Utara', 'Area parkir menuju spot wisata bukit dengan pemandangan Danau Toba.', 3000, 5000, 15000, 60, 60, 'holbung.jpg'),
('Parkir Menara Pandang Tele', 'Tele, Kabupaten Samosir, Sumatera Utara', 'Area parkir dekat menara pandang dengan panorama Danau Toba dari ketinggian.', 3000, 5000, 15000, 50, 50, 'tele.jpg');

-- ---------------------------------------------------------
-- Akun staf default
-- Semua akun contoh di bawah menggunakan password: password123
-- NB: hash bcrypt di bawah adalah contoh untuk password "password123"
-- Segera ganti password ini setelah instalasi.
-- ---------------------------------------------------------

-- Owner: akses penuh, tidak terikat ke satu lokasi
INSERT INTO users (name, email, password, role, lokasi_id) VALUES
('Pemilik Usaha', 'owner@parkirdanautoba.com', '$2b$12$787qN1At96LzK1/8l3Suo.yWI/UBcUzDKgQ7G0ly/ROi3TEo7om86', 'owner', NULL);

-- Admin: kelola operasional semua lokasi
INSERT INTO users (name, email, password, role, lokasi_id) VALUES
('Administrator', 'admin@parkirdanautoba.com', '$2b$12$787qN1At96LzK1/8l3Suo.yWI/UBcUzDKgQ7G0ly/ROi3TEo7om86', 'admin', NULL);

-- Petugas: masing-masing ditugaskan ke satu lokasi (lokasi_id mengikuti id di atas: 1=Ajibata, 2=Tomok, 3=Parapat)
INSERT INTO users (name, email, password, role, lokasi_id) VALUES
('Petugas Ajibata', 'petugas.ajibata@parkirdanautoba.com', '$2b$12$787qN1At96LzK1/8l3Suo.yWI/UBcUzDKgQ7G0ly/ROi3TEo7om86', 'petugas', 1),
('Petugas Tomok', 'petugas.tomok@parkirdanautoba.com', '$2b$12$787qN1At96LzK1/8l3Suo.yWI/UBcUzDKgQ7G0ly/ROi3TEo7om86', 'petugas', 2),
('Petugas Parapat', 'petugas.parapat@parkirdanautoba.com', '$2b$12$787qN1At96LzK1/8l3Suo.yWI/UBcUzDKgQ7G0ly/ROi3TEo7om86', 'petugas', 3);

-- =========================================================
-- MODUL PARKIR MANUAL (WALK-IN)
-- Untuk pengunjung yang datang langsung tanpa booking online.
-- Petugas mencatat kendaraan masuk & keluar langsung di lokasi,
-- bayar tunai di tempat, lalu cetak struk.
-- =========================================================

-- Tabel tarif flat per jenis kendaraan (sekali bayar, parkir bebas tanpa batas jam)
CREATE TABLE tb_tarif (
  id_tarif INT AUTO_INCREMENT PRIMARY KEY,
  jenis_kendaraan VARCHAR(30) NOT NULL,
  tarif_flat DECIMAL(10,2) NOT NULL DEFAULT 0,
  UNIQUE KEY uniq_jenis_kendaraan (jenis_kendaraan)
) ENGINE=InnoDB;

-- Tabel area parkir fisik, masing-masing terikat ke satu lokasi_parkir
-- (dipakai petugas untuk mencatat slot yang benar-benar terisi di lapangan)
CREATE TABLE tb_area_parkir (
  id_area INT AUTO_INCREMENT PRIMARY KEY,
  lokasi_id INT NOT NULL,
  nama_area VARCHAR(100) NOT NULL,
  kapasitas INT NOT NULL DEFAULT 10,
  terisi INT NOT NULL DEFAULT 0,
  FOREIGN KEY (lokasi_id) REFERENCES lokasi_parkir(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabel data kendaraan yang pernah dicatat manual oleh petugas
CREATE TABLE tb_kendaraan (
  id_kendaraan INT AUTO_INCREMENT PRIMARY KEY,
  plat_nomor VARCHAR(20) NOT NULL,
  jenis_kendaraan VARCHAR(30) NOT NULL,
  warna VARCHAR(30) DEFAULT NULL,
  pemilik VARCHAR(100) DEFAULT NULL,
  id_user INT DEFAULT NULL COMMENT 'Petugas yang mencatat',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_user) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tabel transaksi parkir manual (check-in / check-out di lapangan)
CREATE TABLE tb_transaksi (
  id_parkir INT AUTO_INCREMENT PRIMARY KEY,
  id_kendaraan INT NOT NULL,
  id_tarif INT NOT NULL,
  id_area INT NOT NULL,
  id_user INT DEFAULT NULL COMMENT 'Petugas yang memproses',
  waktu_masuk DATETIME NOT NULL,
  waktu_keluar DATETIME DEFAULT NULL,
  biaya_total DECIMAL(10,2) DEFAULT NULL,
  status ENUM('masuk','keluar') NOT NULL DEFAULT 'masuk',
  FOREIGN KEY (id_kendaraan) REFERENCES tb_kendaraan(id_kendaraan) ON DELETE CASCADE,
  FOREIGN KEY (id_tarif) REFERENCES tb_tarif(id_tarif),
  FOREIGN KEY (id_area) REFERENCES tb_area_parkir(id_area),
  FOREIGN KEY (id_user) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tabel log aktivitas staf (audit trail sederhana)
CREATE TABLE tb_log_aktivitas (
  id_log INT AUTO_INCREMENT PRIMARY KEY,
  id_user INT DEFAULT NULL,
  aktivitas VARCHAR(255) NOT NULL,
  waktu_aktivitas DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_user) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Data awal: tarif flat (sekali bayar) per jenis kendaraan
INSERT INTO tb_tarif (jenis_kendaraan, tarif_flat) VALUES
('motor', 3000),
('mobil', 5000),
('lainnya', 15000);

-- Data awal: satu area fisik untuk tiap lokasi wisata yang sudah ada
-- (id lokasi mengikuti urutan INSERT lokasi_parkir di atas: 1=Ajibata, 2=Tomok, 3=Parapat, 4=Holbung, 5=Tele)
INSERT INTO tb_area_parkir (lokasi_id, nama_area, kapasitas, terisi) VALUES
(1, 'Area Parkir Ajibata', 100, 0),
(2, 'Area Parkir Tomok', 80, 0),
(3, 'Area Parkir Parapat', 120, 0),
(4, 'Area Parkir Bukit Holbung', 60, 0),
(5, 'Area Parkir Menara Tele', 50, 0);
