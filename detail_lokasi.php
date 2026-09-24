<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM lokasi_parkir WHERE id = ? AND status = 'aktif'");
$stmt->execute([$id]);
$lokasi = $stmt->fetch();

if (!$lokasi) {
    redirectWithMessage('index.php', 'Lokasi parkir tidak ditemukan.', 'danger');
}

$pageTitle = $lokasi['nama_lokasi'];
require_once __DIR__ . '/includes/header.php';
?>

<section class="detail-lokasi">
    <div class="detail-img" style="background-image:url('assets/img/<?= clean($lokasi['gambar']) ?>')"></div>
    <div class="detail-info">
        <h1><?= clean($lokasi['nama_lokasi']) ?></h1>
        <p class="alamat">📍 <?= clean($lokasi['alamat']) ?></p>
        <p><?= nl2br(clean($lokasi['deskripsi'])) ?></p>

        <table class="tabel-harga">
            <tr><th>Jenis Kendaraan</th><th>Tarif (sekali bayar)</th></tr>
            <tr><td>Motor</td><td><?= formatRupiah($lokasi['harga_motor']) ?></td></tr>
            <tr><td>Mobil</td><td><?= formatRupiah($lokasi['harga_mobil']) ?></td></tr>
            <tr><td>Bus</td><td><?= formatRupiah($lokasi['harga_bus']) ?></td></tr>
        </table>

        <p class="slot">Slot tersedia: <strong><?= (int)$lokasi['slot_tersedia'] ?></strong> dari <?= (int)$lokasi['kapasitas'] ?> total kapasitas.</p>

        <?php if ($lokasi['slot_tersedia'] > 0): ?>
            <?php if (isLoggedIn()): ?>
                <a href="booking.php?lokasi_id=<?= (int)$lokasi['id'] ?>" class="btn-primary">Pesan Sekarang</a>
            <?php else: ?>
                <a href="login.php" class="btn-primary">Masuk untuk Memesan</a>
            <?php endif; ?>
        <?php else: ?>
            <p class="alert alert-danger">Slot parkir penuh, silakan coba lokasi lain.</p>
        <?php endif; ?>

        <a href="index.php" class="btn-secondary">&larr; Kembali ke Daftar Lokasi</a>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
