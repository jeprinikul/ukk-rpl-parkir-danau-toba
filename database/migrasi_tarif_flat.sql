-- =========================================================
-- MIGRASI: Tarif per jam -> Tarif FLAT (sekali bayar)
-- Parkir bebas tanpa batas waktu, bukan per jam.
--
-- Jalankan SEKALI di phpMyAdmin pada database yang sudah ada.
-- (Instalasi baru dari parkir_danau_toba.sql TIDAK perlu file ini.)
--
-- Yang dilakukan:
--   1. lokasi_parkir : harga_per_jam_* -> harga_*  (nilai tetap)
--   2. tb_tarif      : tarif_per_jam   -> tarif_flat (nilai tetap)
--   3. booking       : hapus kolom durasi_jam
--   4. tb_transaksi  : hapus kolom durasi_jam
--
-- Angka harga lama TIDAK diubah. Setelah migrasi, sesuaikan harga flat
-- lewat Admin > Kelola Lokasi & Admin > Kelola Tarif Parkir.
-- PERHATIAN: kolom durasi_jam lama akan terhapus. Backup dulu jika perlu.
-- =========================================================

USE parkir_danau_toba;

ALTER TABLE lokasi_parkir
  CHANGE COLUMN harga_per_jam_motor harga_motor DECIMAL(10,2) NOT NULL DEFAULT 3000,
  CHANGE COLUMN harga_per_jam_mobil harga_mobil DECIMAL(10,2) NOT NULL DEFAULT 5000,
  CHANGE COLUMN harga_per_jam_bus   harga_bus   DECIMAL(10,2) NOT NULL DEFAULT 15000;

ALTER TABLE tb_tarif
  CHANGE COLUMN tarif_per_jam tarif_flat DECIMAL(10,2) NOT NULL DEFAULT 0;

ALTER TABLE booking      DROP COLUMN durasi_jam;
ALTER TABLE tb_transaksi DROP COLUMN durasi_jam;
