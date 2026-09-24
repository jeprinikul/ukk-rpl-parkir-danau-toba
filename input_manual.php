<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin(); // TODO: ganti ke requireAdminLogin() kalau halaman ini khusus petugas/admin

$db = getDB();
$lokasi_id = (int)($_GET['lokasi_id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM lokasi_parkir WHERE id = ? AND status = 'aktif'");
$stmt->execute([$lokasi_id]);
$lokasi = $stmt->fetch();

if (!$lokasi) {
    redirectWithMessage('index.php', 'Lokasi parkir tidak ditemukan.', 'danger');
}

if ($lokasi['slot_tersedia'] <= 0) {
    redirectWithMessage('detail_lokasi.php?id=' . $lokasi_id, 'Maaf, slot parkir di lokasi ini sudah penuh.', 'danger');
}

$pageTitle = "Input Kendaraan Manual - " . $lokasi['nama_lokasi'];
require_once __DIR__ . '/includes/header.php';
?>

<section class="form-container">
    <h1>Catat Kendaraan Masuk (Manual)</h1>
    <h3><?= clean($lokasi['nama_lokasi']) ?></h3>
    <p class="catatan">Dipakai untuk kendaraan yang langsung datang tanpa booking online. Tarif akan dihitung otomatis saat kendaraan checkout / keluar.</p>

    <form method="POST" action="proses_input_manual.php" class="form-box">
        <input type="hidden" name="lokasi_id" value="<?= (int)$lokasi['id'] ?>">

        <label>Plat Nomor Kendaraan</label>
        <input type="text" name="plat_nomor" placeholder="Contoh: BK 1234 AB" required>

        <label>Jenis Kendaraan</label>
        <select name="jenis_kendaraan" required>
            <option value="motor">Motor - <?= formatRupiah($lokasi['harga_per_jam_motor']) ?>/jam</option>
            <option value="mobil">Mobil - <?= formatRupiah($lokasi['harga_per_jam_mobil']) ?>/jam</option>
            <option value="bus">Bus - <?= formatRupiah($lokasi['harga_per_jam_bus']) ?>/jam</option>
        </select>

        <button type="submit" class="btn-primary">Catat Kendaraan Masuk</button>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>