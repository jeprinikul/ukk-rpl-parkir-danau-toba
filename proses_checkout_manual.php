<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin(); // TODO: ganti ke requireAdminLogin() kalau khusus petugas

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectWithMessage('index.php', 'Akses tidak valid.', 'danger');
}

$booking_id = (int)($_POST['booking_id'] ?? 0);

$stmt = $db->prepare("SELECT b.*, l.harga_per_jam_motor, l.harga_per_jam_mobil, l.harga_per_jam_bus
    FROM booking b JOIN lokasi_parkir l ON l.id = b.lokasi_id
    WHERE b.id = ? AND b.jenis_transaksi = 'manual' AND b.status = 'masuk'");
$stmt->execute([$booking_id]);
$booking = $stmt->fetch();

if (!$booking) {
    redirectWithMessage('index.php', 'Data kendaraan tidak ditemukan atau sudah checkout.', 'danger');
}

// Hitung durasi aktual, dibulatkan KE ATAS ke jam berikutnya (standar parkir: lebih dari 0 menit = kena 1 jam)
$masuk = new DateTime($booking['jam_masuk_aktual']);
$keluar = new DateTime(); // sekarang
$selisihDetik = $keluar->getTimestamp() - $masuk->getTimestamp();
$durasi_jam = (int)ceil($selisihDetik / 3600);
if ($durasi_jam < 1) $durasi_jam = 1; // minimal dihitung 1 jam

$hargaPerJam = match ($booking['jenis_kendaraan']) {
    'motor' => $booking['harga_per_jam_motor'],
    'mobil' => $booking['harga_per_jam_mobil'],
    'bus'   => $booking['harga_per_jam_bus'],
    default => 0,
};

$total_harga = $durasi_jam * $hargaPerJam;

$db->beginTransaction();
try {
    // Update booking: tandai selesai, catat waktu keluar aktual, durasi & total final
    $stmt = $db->prepare("UPDATE booking
        SET status = 'selesai', jam_keluar_aktual = ?, durasi_jam = ?, total_harga = ?
        WHERE id = ?");
    $stmt->execute([$keluar->format('Y-m-d H:i:s'), $durasi_jam, $total_harga, $booking_id]);

    // Catat pembayaran tunai, langsung lunas karena dibayar di tempat
    // NOTE: sesuaikan value status ini ('lunas') dengan ENUM yang sebenarnya ada di tabel pembayaran
    $stmt = $db->prepare("INSERT INTO pembayaran (booking_id, metode_pembayaran, jumlah_bayar, bukti_transfer, status)
        VALUES (?, 'tunai', ?, NULL, 'lunas')");
    $stmt->execute([$booking_id, $total_harga]);

    // Kembalikan slot parkir karena kendaraan sudah keluar
    $stmt = $db->prepare("UPDATE lokasi_parkir SET slot_tersedia = slot_tersedia + 1 WHERE id = ?");
    $stmt->execute([$booking['lokasi_id']]);

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    redirectWithMessage('checkout_manual.php?lokasi_id=' . $booking['lokasi_id'], 'Gagal memproses checkout: ' . $e->getMessage(), 'danger');
}

redirectWithMessage(
    'checkout_manual.php?lokasi_id=' . $booking['lokasi_id'],
    'Checkout ' . $booking['plat_nomor'] . ' berhasil. Durasi: ' . $durasi_jam . ' jam, Total: ' . formatRupiah($total_harga),
    'success'
);