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
$id_parkir = $_POST['id_parkir'] ?? '';

// Metode bayar dikirim dari halaman konfirmasi_keluar.php (Tunai/QRIS).
// Validasi ketat: hanya 'tunai' atau 'qris' yang diterima, default 'tunai'.
$metodeBayar = $_POST['metode_bayar'] ?? 'tunai';
if (!in_array($metodeBayar, ['tunai', 'qris'], true)) {
    $metodeBayar = 'tunai';
}

if ($id_parkir === '' || !$lokasiPetugas) {
    $_SESSION['pesan'] = ['tipe' => 'gagal', 'teks' => 'Data transaksi tidak valid.'];
    header("Location: dashboard.php");
    exit;
}

try {
    $pdo->beginTransaction();

    // Ambil data transaksi + tarif + area, pastikan transaksi ini ada di lokasi tugas petugas
    $stmt = $pdo->prepare("SELECT t.*, tr.tarif_flat, k.plat_nomor, a.lokasi_id
                            FROM tb_transaksi t
                            JOIN tb_tarif tr ON t.id_tarif = tr.id_tarif
                            JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
                            JOIN tb_area_parkir a ON t.id_area = a.id_area
                            WHERE t.id_parkir = ? AND t.status = 'masuk'
                            FOR UPDATE");
    $stmt->execute([$id_parkir]);
    $transaksi = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaksi) {
        throw new Exception("Transaksi tidak ditemukan atau sudah selesai diproses.");
    }
    if ((int)$transaksi['lokasi_id'] !== $lokasiPetugas) {
        throw new Exception("Transaksi ini bukan milik lokasi tugas Anda.");
    }

    // Tarif FLAT: sekali bayar, tidak dihitung per jam
    $biayaTotal = $transaksi['tarif_flat'];

    // Update transaksi jadi keluar
    $stmtUpdate = $pdo->prepare("UPDATE tb_transaksi 
        SET waktu_keluar = NOW(), biaya_total = ?, status = 'keluar', metode_bayar = ?
        WHERE id_parkir = ?");
    $stmtUpdate->execute([$biayaTotal, $metodeBayar, $id_parkir]);

    // Kurangi jumlah terisi di area parkir
    $stmtArea = $pdo->prepare("UPDATE tb_area_parkir SET terisi = terisi - 1 WHERE id_area = ?");
    $stmtArea->execute([$transaksi['id_area']]);

    // Log aktivitas
    $stmtLog = $pdo->prepare("INSERT INTO tb_log_aktivitas (id_user, aktivitas, waktu_aktivitas)
                               VALUES (?, ?, NOW())");
    $stmtLog->execute([$id_user, "Memproses kendaraan keluar (parkir manual): " . $transaksi['plat_nomor'] . " - Rp" . number_format($biayaTotal, 0, ',', '.') . " - Bayar: " . strtoupper($metodeBayar)]);

    $pdo->commit();

    // Langsung arahkan ke halaman cetak struk
    header("Location: struk.php?id=" . $id_parkir);
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['pesan'] = ['tipe' => 'gagal', 'teks' => 'Gagal memproses keluar: ' . $e->getMessage()];
    header("Location: dashboard.php");
    exit;
}
