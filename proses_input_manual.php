<?phpfile_put_contents(__DIR__ . '/debug_log.txt', date('Y-m-d H:i:s') . " - Proses input manual dipanggil\n", FILE_APPEND);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin(); // TODO: ganti ke requireAdminLogin() kalau khusus petugas

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectWithMessage('index.php', 'Akses tidak valid.', 'danger');
}

$lokasi_id = (int)($_POST['lokasi_id'] ?? 0);
$plat_nomor = clean($_POST['plat_nomor'] ?? '');
$jenis_kendaraan = clean($_POST['jenis_kendaraan'] ?? '');

if ($lokasi_id <= 0 || $plat_nomor === '' || !in_array($jenis_kendaraan, ['motor', 'mobil', 'bus'])) {
    redirectWithMessage('input_manual.php?lokasi_id=' . $lokasi_id, 'Data tidak lengkap atau tidak valid.', 'danger');
}

$stmt = $db->prepare("SELECT * FROM lokasi_parkir WHERE id = ? AND status = 'aktif'");
$stmt->execute([$lokasi_id]);
$lokasi = $stmt->fetch();

if (!$lokasi || $lokasi['slot_tersedia'] <= 0) {
    redirectWithMessage('index.php', 'Lokasi tidak tersedia atau slot penuh.', 'danger');
}

// Kode booking unik untuk transaksi manual, biar gampang dibedain di laporan
$kode_booking = 'MN-' . date('ymd') . '-' . strtoupper(substr(uniqid(), -5));

$stmt = $db->prepare("INSERT INTO booking
    (user_id, lokasi_id, kode_booking, jenis_transaksi, diinput_oleh, plat_nomor, jenis_kendaraan,
     tanggal_booking, jam_masuk, jam_masuk_aktual, durasi_jam, total_harga, status)
    VALUES (NULL, ?, ?, 'manual', ?, ?, ?, CURDATE(), CURTIME(), NOW(), NULL, NULL, 'masuk')");
$stmt->execute([
    $lokasi_id,
    $kode_booking,
    $_SESSION['id_user'],
    $plat_nomor,
    $jenis_kendaraan
]);

$booking_id = $db->lastInsertId();

// Kurangi slot tersedia karena kendaraan ini sekarang parkir
$stmt = $db->prepare("UPDATE lokasi_parkir SET slot_tersedia = slot_tersedia - 1 WHERE id = ?");
$stmt->execute([$lokasi_id]);

redirectWithMessage('checkout_manual.php?lokasi_id=' . $lokasi_id,
    'Kendaraan ' . $plat_nomor . ' berhasil dicatat masuk. Kode: ' . $kode_booking, 'success');