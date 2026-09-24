<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$db = getDB();

$lokasi_id       = (int)($_POST['lokasi_id'] ?? 0);
$plat_nomor      = clean($_POST['plat_nomor'] ?? '');
$jenis_kendaraan = clean($_POST['jenis_kendaraan'] ?? 'motor');
$tanggal_booking = clean($_POST['tanggal_booking'] ?? '');
$jam_masuk       = clean($_POST['jam_masuk'] ?? '');

if ($plat_nomor === '' || $tanggal_booking === '' || $jam_masuk === '') {
    $_SESSION['notif_sound'] = 'error';   // suara: data booking belum lengkap
    redirectWithMessage("booking.php?lokasi_id=$lokasi_id", 'Mohon lengkapi semua data booking.', 'danger');
}

// Ambil data lokasi terbaru untuk validasi harga & slot
$stmt = $db->prepare("SELECT * FROM lokasi_parkir WHERE id = ? AND status = 'aktif' FOR UPDATE");
$db->beginTransaction();
$stmt->execute([$lokasi_id]);
$lokasi = $stmt->fetch();

if (!$lokasi || $lokasi['slot_tersedia'] <= 0) {
    $db->rollBack();
    $_SESSION['notif_sound'] = 'error';   // suara: slot penuh / lokasi tidak tersedia
    redirectWithMessage('index.php', 'Maaf, lokasi tidak tersedia atau slot penuh.', 'danger');
}

$hargaMap = [
    'motor' => $lokasi['harga_motor'],
    'mobil' => $lokasi['harga_mobil'],
    'bus'   => $lokasi['harga_bus'],
];
// Tarif FLAT: sekali bayar, parkir bebas tanpa batas jam (tidak dikali durasi)
$totalHarga  = $hargaMap[$jenis_kendaraan] ?? $lokasi['harga_motor'];
$kodeBooking = generateKodeBooking();

$stmt = $db->prepare("INSERT INTO booking
    (kode_booking, user_id, lokasi_id, plat_nomor, jenis_kendaraan, tanggal_booking, jam_masuk, total_harga, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
$stmt->execute([
    $kodeBooking,
    $_SESSION['id_user'],
    $lokasi_id,
    $plat_nomor,
    $jenis_kendaraan,
    $tanggal_booking,
    $jam_masuk,
    $totalHarga,
]);
$bookingId = $db->lastInsertId();

// Kurangi slot tersedia
$stmt = $db->prepare("UPDATE lokasi_parkir SET slot_tersedia = slot_tersedia - 1 WHERE id = ?");
$stmt->execute([$lokasi_id]);

$db->commit();

$_SESSION['notif_sound'] = 'booking';     // suara: booking berhasil dibuat
redirectWithMessage("pembayaran.php?booking_id=$bookingId", "Booking berhasil dibuat dengan kode $kodeBooking. Silakan lanjutkan pembayaran.", 'success');