<?php
// Skrip pemeriksa sementara. Hapus file ini setelah masalah selesai.
header('Content-Type: text/html; charset=utf-8');
$root = __DIR__;

function baca($p) { return is_file($p) ? file_get_contents($p) : false; }
function status($ok, $teksOk, $teksGagal) {
    return ($ok ? "[OK]     " : "[MASALAH] ") . ($ok ? $teksOk : $teksGagal) . "\n";
}

echo "<pre style='font-size:15px;line-height:1.6'>";
echo "HASIL PEMERIKSAAN\n=================\n\n";
echo "Folder yang dipakai server:\n  " . $root . "\n\n";

// 1. admin_header.php sudah punya ob_start?
$h = baca($root . '/admin/admin_header.php');
if ($h === false) {
    echo "[MASALAH] admin/admin_header.php tidak ditemukan\n";
} else {
    echo status(strpos($h, 'ob_start') !== false,
        "admin_header.php sudah versi baru (ada ob_start)",
        "admin_header.php MASIH VERSI LAMA (belum ada ob_start)");
}

// 2. kelola_lokasi.php: cek akses sebelum header?
$k = baca($root . '/admin/kelola_lokasi.php');
if ($k === false) {
    echo "[MASALAH] admin/kelola_lokasi.php tidak ditemukan\n";
} else {
    $posGuard  = strpos($k, 'requireAdmin()');
    $posHeader = strpos($k, 'admin_header.php');
    echo status($posGuard !== false && $posHeader !== false && $posGuard < $posHeader,
        "kelola_lokasi.php sudah versi baru (cek akses sebelum header)",
        "kelola_lokasi.php MASIH VERSI LAMA (cek akses masih di bawah header)");
}

// 3. Folder bersarang akibat salah ekstrak?
echo status(!is_dir($root . '/admin/parkir-danau-toba'),
    "tidak ada folder bersarang di dalam admin",
    "ADA folder 'parkir-danau-toba' di dalam admin/ (salah ekstrak, file perbaikan tidak menimpa file asli)");

// 4. Kode tarif flat?
$t = baca($root . '/admin/kelola_tarif.php');
echo status($t !== false && strpos($t, 'tarif_flat') !== false,
    "kode sudah versi tarif flat",
    "kode masih versi tarif per jam");

// 5. Database sudah dimigrasi?
try {
    require_once $root . '/config/database.php';
    $db = getDB();
    $c1 = $db->query("SHOW COLUMNS FROM tb_tarif LIKE 'tarif_flat'")->fetch();
    $c2 = $db->query("SHOW COLUMNS FROM lokasi_parkir LIKE 'harga_motor'")->fetch();
    $c3 = $db->query("SHOW COLUMNS FROM tb_transaksi LIKE 'metode_bayar'")->fetch();
    echo status((bool)$c1, "database: tb_tarif.tarif_flat ada", "database: tb_tarif.tarif_flat TIDAK ADA (migrasi belum dijalankan)");
    echo status((bool)$c2, "database: lokasi_parkir.harga_motor ada", "database: lokasi_parkir.harga_motor TIDAK ADA (migrasi belum dijalankan)");
    echo status((bool)$c3, "database: tb_transaksi.metode_bayar ada", "database: tb_transaksi.metode_bayar TIDAK ADA (jalankan migrasi_qris_petugas.sql)");
} catch (Throwable $e) {
    echo "[MASALAH] tidak bisa cek database: " . htmlspecialchars($e->getMessage()) . "\n";
}

// 6. Info PHP
echo "\nInfo PHP: versi " . PHP_VERSION . ", output_buffering=" . ini_get('output_buffering') . "\n";
echo "</pre>";