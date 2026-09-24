-- =========================================================
-- MIGRASI: Pembayaran QRIS untuk Parkir Manual (Petugas)
-- Bisa dijalankan berulang kali tanpa error, karena kolom
-- hanya ditambahkan jika belum ada.
--
-- Menambahkan kolom metode_bayar ('tunai' / 'qris') ke tabel
-- tb_transaksi, supaya saat Petugas memproses kendaraan keluar,
-- transaksi bisa dicatat dibayar Tunai atau lewat QRIS.
-- =========================================================

USE parkir_danau_toba;

SET @kolom_ada := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tb_transaksi' AND COLUMN_NAME = 'metode_bayar'
);
SET @sql := IF(@kolom_ada = 0,
  'ALTER TABLE tb_transaksi ADD COLUMN metode_bayar ENUM(''tunai'',''qris'') NOT NULL DEFAULT ''tunai'' AFTER biaya_total',
  'SELECT ''Kolom metode_bayar sudah ada, dilewati.'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Cek hasil akhir struktur tabel tb_transaksi
DESCRIBE tb_transaksi;
