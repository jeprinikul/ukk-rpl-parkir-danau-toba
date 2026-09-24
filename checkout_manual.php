<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin(); // TODO: ganti ke requireAdminLogin() kalau khusus petugas

$db = getDB();
$lokasi_id = (int)($_GET['lokasi_id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM lokasi_parkir WHERE id = ?");
$stmt->execute([$lokasi_id]);
$lokasi = $stmt->fetch();

if (!$lokasi) {
    redirectWithMessage('index.php', 'Lokasi parkir tidak ditemukan.', 'danger');
}

// Semua kendaraan manual yang masih parkir (belum checkout) di lokasi ini
$stmt = $db->prepare("SELECT * FROM booking
    WHERE lokasi_id = ? AND jenis_transaksi = 'manual' AND status = 'masuk'
    ORDER BY jam_masuk_aktual ASC");
$stmt->execute([$lokasi_id]);
$daftar = $stmt->fetchAll();

$pageTitle = "Checkout Kendaraan Manual - " . $lokasi['nama_lokasi'];
require_once __DIR__ . '/includes/header.php';
?>

<section class="form-container">
    <h1>Checkout Kendaraan (Manual)</h1>
    <h3><?= clean($lokasi['nama_lokasi']) ?></h3>

    <?php if (empty($daftar)): ?>
        <p class="catatan">Belum ada kendaraan manual yang sedang parkir di lokasi ini.</p>
    <?php else: ?>
        <table class="tabel-checkout">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Plat Nomor</th>
                    <th>Jenis</th>
                    <th>Jam Masuk</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($daftar as $b): ?>
                <tr>
                    <td><?= clean($b['kode_booking']) ?></td>
                    <td><?= clean($b['plat_nomor']) ?></td>
                    <td><?= clean(ucfirst($b['jenis_kendaraan'])) ?></td>
                    <td><?= clean($b['jam_masuk_aktual']) ?></td>
                    <td>
                        <form method="POST" action="proses_checkout_manual.php" style="display:inline;">
                            <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                            <button type="submit" class="btn-primary">Checkout</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <p style="margin-top:1rem;">
        <a href="input_manual.php?lokasi_id=<?= (int)$lokasi_id ?>" class="btn-secondary">+ Catat Kendaraan Baru Masuk</a>
    </p>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>