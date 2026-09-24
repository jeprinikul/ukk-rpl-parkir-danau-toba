<?php
session_start();
require_once "../config/database.php";
$pdo = getDB();

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'petugas') {
    header("Location: login.php");
    exit;
}
$id_user = $_SESSION['id_user'];
$lokasiPetugas = (int)($_SESSION['lokasi_id'] ?? 0);

if (!$lokasiPetugas) {
    $_SESSION['pesan'] = ['tipe' => 'gagal', 'teks' => 'Akun Anda belum ditugaskan ke lokasi parkir manapun.'];
    header("Location: dashboard.php");
    exit;
}

$plat_nomor = trim($_POST['plat_nomor'] ?? '');
$id_tarif   = $_POST['id_tarif'] ?? '';
$warna      = trim($_POST['warna'] ?? '');
$pemilik    = trim($_POST['pemilik'] ?? '');
$id_area    = $_POST['id_area'] ?? '';

if ($plat_nomor === '' || $id_tarif === '' || $id_area === '') {
    $_SESSION['pesan'] = ['tipe' => 'gagal', 'teks' => 'Plat nomor, jenis kendaraan, dan area wajib diisi.'];
    header("Location: dashboard.php");
    exit;
}

try {
    $pdo->beginTransaction();

    // Ambil jenis_kendaraan dari tb_tarif yang dipilih
    $stmt = $pdo->prepare("SELECT jenis_kendaraan FROM tb_tarif WHERE id_tarif = ?");
    $stmt->execute([$id_tarif]);
    $tarif = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tarif) {
        throw new Exception("Jenis kendaraan / tarif tidak ditemukan.");
    }
    $jenis_kendaraan = $tarif['jenis_kendaraan'];

    // Cek area dipilih benar-benar milik lokasi tugas petugas ini (cegah manipulasi id_area lewat form)
    // dan kapasitasnya masih tersedia
    $stmtArea = $pdo->prepare("SELECT kapasitas, terisi, lokasi_id FROM tb_area_parkir WHERE id_area = ? FOR UPDATE");
    $stmtArea->execute([$id_area]);
    $area = $stmtArea->fetch(PDO::FETCH_ASSOC);

    if (!$area || (int)$area['lokasi_id'] !== $lokasiPetugas) {
        throw new Exception("Area parkir tidak valid untuk lokasi tugas Anda.");
    }
    if ($area['terisi'] >= $area['kapasitas']) {
        throw new Exception("Area parkir sudah penuh, pilih area lain.");
    }

    // Simpan data kendaraan baru
    $stmtKendaraan = $pdo->prepare("INSERT INTO tb_kendaraan (plat_nomor, jenis_kendaraan, warna, pemilik, id_user)
                                     VALUES (?, ?, ?, ?, ?)");
    $stmtKendaraan->execute([$plat_nomor, $jenis_kendaraan, $warna, $pemilik, $id_user]);
    $id_kendaraan = $pdo->lastInsertId();

    // Simpan transaksi masuk
    $stmtTransaksi = $pdo->prepare("INSERT INTO tb_transaksi
        (id_kendaraan, waktu_masuk, id_tarif, status, id_user, id_area)
        VALUES (?, NOW(), ?, 'masuk', ?, ?)");
    $stmtTransaksi->execute([$id_kendaraan, $id_tarif, $id_user, $id_area]);
    $id_parkir_baru = $pdo->lastInsertId();

    // Update jumlah terisi area (+1)
    $stmtUpdateArea = $pdo->prepare("UPDATE tb_area_parkir SET terisi = terisi + 1 WHERE id_area = ?");
    $stmtUpdateArea->execute([$id_area]);

    // Catat log aktivitas
    $stmtLog = $pdo->prepare("INSERT INTO tb_log_aktivitas (id_user, aktivitas, waktu_aktivitas)
                               VALUES (?, ?, NOW())");
    $stmtLog->execute([$id_user, "Mencatat kendaraan masuk (parkir manual): $plat_nomor"]);

    $pdo->commit();
    $_SESSION['pesan'] = ['tipe' => 'sukses', 'teks' => "Kendaraan $plat_nomor berhasil dicatat masuk."];

    // Langsung arahkan ke halaman cetak struk masuk (berisi barcode No. Karcis)
    header("Location: struk_masuk.php?id=" . $id_parkir_baru);
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['pesan'] = ['tipe' => 'gagal', 'teks' => 'Gagal menyimpan: ' . $e->getMessage()];
    header("Location: dashboard.php");
    exit;
}