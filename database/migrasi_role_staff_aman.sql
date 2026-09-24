-- =========================================================
-- MIGRASI (VERSI AMAN): Menambahkan role Owner & Petugas
-- Bisa dijalankan berulang kali tanpa error, karena setiap
-- perubahan dicek dulu apakah sudah ada sebelum dieksekusi.
-- =========================================================

USE parkir_danau_toba;

-- 1. Ubah enum role menjadi 4 pilihan (aman dijalankan berulang)
ALTER TABLE users
  MODIFY COLUMN role ENUM('user','petugas','admin','owner') NOT NULL DEFAULT 'user';

-- 2. Tambah kolom lokasi_id HANYA jika belum ada
SET @kolom_ada := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'lokasi_id'
);
SET @sql := IF(@kolom_ada = 0,
  'ALTER TABLE users ADD COLUMN lokasi_id INT DEFAULT NULL COMMENT ''Lokasi parkir yang ditugaskan, khusus role petugas''',
  'SELECT ''Kolom lokasi_id sudah ada, dilewati.'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Tambah kolom status_akun HANYA jika belum ada
SET @kolom_ada := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'status_akun'
);
SET @sql := IF(@kolom_ada = 0,
  'ALTER TABLE users ADD COLUMN status_akun ENUM(''aktif'',''nonaktif'') NOT NULL DEFAULT ''aktif''',
  'SELECT ''Kolom status_akun sudah ada, dilewati.'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Tambah foreign key HANYA jika belum ada
SET @fk_ada := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND CONSTRAINT_NAME = 'fk_users_lokasi'
);
SET @sql := IF(@fk_ada = 0,
  'ALTER TABLE users ADD CONSTRAINT fk_users_lokasi FOREIGN KEY (lokasi_id) REFERENCES lokasi_parkir(id) ON DELETE SET NULL',
  'SELECT ''Foreign key fk_users_lokasi sudah ada, dilewati.'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. (Opsional) Jadikan salah satu akun admin lama sebagai Owner.
--    Ganti email di bawah sesuai akun admin lama Anda.
UPDATE users SET role = 'owner' WHERE email = 'admin@parkirdanautoba.com' AND role = 'admin';

-- Cek hasil akhir struktur tabel users
DESCRIBE users;
