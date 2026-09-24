-- =========================================================
-- MIGRASI: Modul Parkir Manual (Walk-in) untuk Petugas
-- Jalankan file ini HANYA jika database Anda BELUM punya
-- tabel tb_tarif, tb_area_parkir, tb_kendaraan, tb_transaksi,
-- tb_log_aktivitas (yaitu, jika Anda meng-install sebelum
-- fitur "Parkir Manual" ini ditambahkan).
--
-- Jika Anda baru install dari awal, TIDAK PERLU menjalankan
-- file ini — cukup import parkir_danau_toba.sql saja, karena
-- tabel-tabel ini sudah termasuk di dalamnya.
--
-- Fitur ini memungkinkan Petugas mencatat pengunjung yang
-- datang LANGSUNG ke lokasi (tanpa booking online lewat
-- website) dan langsung bayar tunai di tempat saat keluar.
-- =========================================================

USE parkir_danau_toba;

CREATE TABLE IF NOT EXISTS tb_tarif (
  id_tarif INT AUTO_INCREMENT PRIMARY KEY,
  jenis_kendaraan VARCHAR(30) NOT NULL,
  tarif_flat DECIMAL(10,2) NOT NULL DEFAULT 0,
  UNIQUE KEY uniq_jenis_kendaraan (jenis_kendaraan)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tb_area_parkir (
  id_area INT AUTO_INCREMENT PRIMARY KEY,
  lokasi_id INT NOT NULL,
  nama_area VARCHAR(100) NOT NULL,
  kapasitas INT NOT NULL DEFAULT 10,
  terisi INT NOT NULL DEFAULT 0,
  FOREIGN KEY (lokasi_id) REFERENCES lokasi_parkir(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tb_kendaraan (
  id_kendaraan INT AUTO_INCREMENT PRIMARY KEY,
  plat_nomor VARCHAR(20) NOT NULL,
  jenis_kendaraan VARCHAR(30) NOT NULL,
  warna VARCHAR(30) DEFAULT NULL,
  pemilik VARCHAR(100) DEFAULT NULL,
  id_user INT DEFAULT NULL COMMENT 'Petugas yang mencatat',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_user) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tb_transaksi (
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

CREATE TABLE IF NOT EXISTS tb_log_aktivitas (
  id_log INT AUTO_INCREMENT PRIMARY KEY,
  id_user INT DEFAULT NULL,
  aktivitas VARCHAR(255) NOT NULL,
  waktu_aktivitas DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_user) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Data awal tarif (sesuaikan lagi lewat menu Admin > Kelola Tarif Parkir)
INSERT INTO tb_tarif (jenis_kendaraan, tarif_flat)
SELECT * FROM (SELECT 'motor' AS jenis_kendaraan, 3000 AS tarif_flat) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM tb_tarif WHERE jenis_kendaraan = 'motor');

INSERT INTO tb_tarif (jenis_kendaraan, tarif_flat)
SELECT * FROM (SELECT 'mobil' AS jenis_kendaraan, 5000 AS tarif_flat) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM tb_tarif WHERE jenis_kendaraan = 'mobil');

INSERT INTO tb_tarif (jenis_kendaraan, tarif_flat)
SELECT * FROM (SELECT 'lainnya' AS jenis_kendaraan, 15000 AS tarif_flat) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM tb_tarif WHERE jenis_kendaraan = 'lainnya');

-- Data awal: satu area fisik untuk tiap lokasi wisata yang sudah ada di lokasi_parkir
INSERT INTO tb_area_parkir (lokasi_id, nama_area, kapasitas, terisi)
SELECT id, CONCAT('Area Parkir ', nama_lokasi), kapasitas, 0
FROM lokasi_parkir l
WHERE NOT EXISTS (SELECT 1 FROM tb_area_parkir a WHERE a.lokasi_id = l.id);

-- Setelah migrasi ini, tugaskan tiap Petugas ke lokasi yang benar lewat
-- menu Owner > Kelola Akun Staf (kolom lokasi_id di tabel users).
