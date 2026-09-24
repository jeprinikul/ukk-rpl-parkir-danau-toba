<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$db = getDB();
$stmt = $db->prepare("SELECT b.*, l.nama_lokasi FROM booking b
    JOIN lokasi_parkir l ON l.id = b.lokasi_id
    WHERE b.user_id = ?
    ORDER BY b.created_at DESC");
$stmt->execute([$_SESSION['id_user']]);
$riwayat = $stmt->fetchAll();

$pageTitle = "Riwayat Booking";
require_once __DIR__ . '/includes/header.php';
?>

<section class="riwayat-container">
    <h1>Riwayat Booking Saya</h1>

    <?php if (empty($riwayat)): ?>
        <p class="empty-state">Anda belum memiliki riwayat booking.</p>
    <?php else: ?>
        <table class="tabel-riwayat">
            <thead>
                <tr>
                    <th>Kode Booking</th>
                    <th>Lokasi</th>
                    <th>Kendaraan</th>
                    <th>Tanggal</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($riwayat as $r): ?>
                    <tr>
                        <td><?= clean($r['kode_booking']) ?></td>
                        <td><?= clean($r['nama_lokasi']) ?></td>
                        <td><?= clean(ucfirst($r['jenis_kendaraan'])) ?> (<?= clean($r['plat_nomor']) ?>)</td>
                        <td><?= clean($r['tanggal_booking']) ?> <?= clean(substr($r['jam_masuk'],0,5)) ?></td>
                        <td><?= formatRupiah($r['total_harga']) ?></td>
                        <td><span class="badge badge-<?= clean($r['status']) ?>"><?= clean(ucfirst($r['status'])) ?></span></td>
                        <td>
                            <?php if ($r['status'] === 'pending'): ?>
                                <a href="pembayaran.php?booking_id=<?= (int)$r['id'] ?>" class="btn-small">Bayar</a>
                            <?php elseif ($r['status'] === 'selesai'): ?>
                                <a href="pembayaran.php?booking_id=<?= (int)$r['id'] ?>" class="btn-small">Detail</a>
                                <a href="ulasan.php?booking_id=<?= (int)$r['id'] ?>" class="btn-small">Beri Ulasan</a>
                            <?php else: ?>
                                <a href="pembayaran.php?booking_id=<?= (int)$r['id'] ?>" class="btn-small">Detail</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>