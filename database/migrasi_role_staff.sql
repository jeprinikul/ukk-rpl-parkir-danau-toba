-- =========================================================
-- MIGRASI: Menambahkan role Owner & Petugas
-- Jalankan file ini HANYA jika Anda sudah pernah meng-install
-- database versi sebelumnya (yang hanya punya role 'user' & 'admin').
-- Jika baru install dari awal, cukup import parkir_danau_toba.sql saja.
-- =========================================================

USE parkir_danau_toba;

-- 1. Ubah enum role menjadi 4 pilihan
ALTER TABLE users
  MODIFY COLUMN role ENUM('user','petugas','admin','owner') NOT NULL DEFAULT 'user';

-- 2. Tambah kolom lokasi_id (penugasan lokasi untuk petugas)
ALTER TABLE users
  ADD COLUMN lokasi_id INT DEFAULT NULL COMMENT 'Lokasi parkir yang ditugaskan, khusus role petugas' AFTER alamat;

-- 3. Tambah kolom status_akun (aktif/nonaktif)
ALTER TABLE users
  ADD COLUMN status_akun ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif' AFTER lokasi_id;

-- 4. Tambah foreign key ke lokasi_parkir
ALTER TABLE users
  ADD CONSTRAINT fk_users_lokasi FOREIGN KEY (lokasi_id) REFERENCES lokasi_parkir(id) ON DELETE SET NULL;

-- 5. (Opsional) Ubah salah satu akun admin lama menjadi Owner.
--    Ganti 'admin@parkirdanautoba.com' dengan email admin lama Anda jika berbeda.
UPDATE users SET role = 'owner' WHERE email = 'admin@parkirdanautoba.com';

-- 6. (Opsional) Tambahkan akun admin & petugas baru dengan password "password123"
--    Hash di bawah valid untuk password "password123".
--    Gunakan email BARU yang berbeda dari email admin lama (yang sudah jadi owner di langkah 5).
INSERT INTO users (name, email, password, role, lokasi_id) VALUES
('Administrator Baru', 'admin.baru@parkirdanautoba.com', '$2b$12$787qN1At96LzK1/8l3Suo.yWI/UBcUzDKgQ7G0ly/ROi3TEo7om86', 'admin', NULL),
('Petugas Contoh', 'petugas1@parkirdanautoba.com', '$2b$12$787qN1At96LzK1/8l3Suo.yWI/UBcUzDKgQ7G0ly/ROi3TEo7om86', 'petugas', 1);
