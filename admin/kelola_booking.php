<?php
$pageTitle = "Kelola Booking";
require_once __DIR__ . '/admin_header.php';

$db = getDB();

// Petugas hanya boleh mengelola booking di lokasi yang ditugaskan padanya
$lokasiPetugas = isPetugas() ? (int)($_SESSION['lokasi_id'] ?? 0) : null;

if (isPetugas() && !$lokasiPetugas) {
    echo "<div class='alert alert-danger'>Akun Anda belum ditugaskan ke lokasi manapun. Hubungi Owner/Admin untuk penugasan lokasi.</div>";
    require_once __DIR__ . '/admin_footer.php';
    exit;
}

// Update status booking (misal: konfirmasi pembayaran -> selesai, atau batalkan)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'], $_POST['status_baru'])) {
    $bookingId = (int)$_POST['booking_id'];
    $statusBaru = clean($_POST['status_baru']);

    // Ambil data booking untuk validasi kepemilikan lokasi & transisi status
    $stmt = $db->prepare("SELECT lokasi_id, status FROM booking WHERE id = ?");
    $stmt->execute([$bookingId]);
    $old = $stmt->fetch();

    $bolehUpdate = false;
    if ($old) {
        if (isAdmin() || isOwner()) {
            // Admin & owner boleh set status apapun
            $bolehUpdate = in_array($statusBaru, ['pending', 'dibayar', 'selesai', 'dibatalkan']);
        } elseif (isPetugas()) {
            // Petugas hanya boleh mengubah booking di lokasi tugasnya,
            // dan hanya transisi maju: dibayar -> selesai (tidak boleh membatalkan / mundur)
            $bolehUpdate = ((int)$old['lokasi_id'] === $lokasiPetugas)
                && in_array($statusBaru, ['dibayar', 'selesai']);
        }
    }

    if ($bolehUpdate) {
        if ($statusBaru === 'dibatalkan' && $old['status'] !== 'dibatalkan') {
            $stmt = $db->prepare("UPDATE lokasi_parkir SET slot_tersedia = slot_tersedia + 1 WHERE id = ?");
            $stmt->execute([$old['lokasi_id']]);
        }

        $stmt = $db->prepare("UPDATE booking SET status = ? WHERE id = ?");
        $stmt->execute([$statusBaru, $bookingId]);

        if ($statusBaru === 'selesai') {
            $stmt = $db->prepare("UPDATE pembayaran SET status = 'berhasil' WHERE booking_id = ?");
            $stmt->execute([$bookingId]);
        }
        redirectWithMessage('kelola_booking.php', 'Status booking berhasil diperbarui.', 'success');
    } else {
        redirectWithMessage('kelola_booking.php', 'Anda tidak memiliki izin untuk mengubah booking ini.', 'danger');
    }
}

$filterStatus = clean($_GET['status'] ?? '');
$sql = "SELECT b.*, l.nama_lokasi, u.name as nama_user, u.email,
        p.metode_pembayaran, p.status as status_pembayaran, p.bukti_transfer
        FROM booking b
        JOIN lokasi_parkir l ON l.id = b.lokasi_id
        JOIN users u ON u.id = b.user_id
        LEFT JOIN pembayaran p ON p.booking_id = b.id
        WHERE 1=1 ";
$params = [];

// Petugas hanya melihat booking di lokasi tugasnya
if (isPetugas()) {
    $sql .= " AND b.lokasi_id = ? ";
    $params[] = $lokasiPetugas;
}

if ($filterStatus !== '') {
    $sql .= " AND b.status = ? ";
    $params[] = $filterStatus;
}
$sql .= " ORDER BY b.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$bookingList = $stmt->fetchAll();
?>

<h1>Kelola Booking</h1>
<?php if (isPetugas()): ?>
    <p class="hint">Anda hanya dapat melihat &amp; memproses booking di lokasi yang ditugaskan kepada Anda.</p>
<?php endif; ?>

<div class="filter-row">
    <a href="kelola_booking.php" class="btn-small <?= $filterStatus==='' ? 'active' : '' ?>">Semua</a>
    <a href="kelola_booking.php?status=pending" class="btn-small <?= $filterStatus==='pending' ? 'active' : '' ?>">Pending</a>
    <a href="kelola_booking.php?status=dibayar" class="btn-small <?= $filterStatus==='dibayar' ? 'active' : '' ?>">Dibayar</a>
    <a href="kelola_booking.php?status=selesai" class="btn-small <?= $filterStatus==='selesai' ? 'active' : '' ?>">Selesai</a>
    <a href="kelola_booking.php?status=dibatalkan" class="btn-small <?= $filterStatus==='dibatalkan' ? 'active' : '' ?>">Dibatalkan</a>
</div>

<table class="tabel-riwayat">
    <thead>
        <tr>
            <th>Kode</th><th>User</th><th>Lokasi</th><th>Kendaraan</th><th>Tanggal</th><th>Total</th>
            <th>Metode Bayar</th><th>Bukti</th><th>Status</th><th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($bookingList as $b): ?>
            <tr>
                <td><?= clean($b['kode_booking']) ?></td>
                <td><?= clean($b['nama_user']) ?><br><small><?= clean($b['email']) ?></small></td>
                <td><?= clean($b['nama_lokasi']) ?></td>
                <td><?= clean(ucfirst($b['jenis_kendaraan'])) ?> (<?= clean($b['plat_nomor']) ?>)</td>
                <td><?= clean($b['tanggal_booking']) ?> <?= clean(substr($b['jam_masuk'],0,5)) ?></td>
                <td><?= formatRupiah($b['total_harga']) ?></td>
                <td><?= $b['metode_pembayaran'] ? clean(str_replace('_',' ',ucfirst($b['metode_pembayaran']))) : '-' ?></td>
                <td>
                    <?php if (!empty($b['bukti_transfer'])): ?>
                        <a href="../<?= clean($b['bukti_transfer']) ?>" target="_blank">Lihat</a>
                    <?php else: ?>-<?php endif; ?>
                </td>
                <td><span class="badge badge-<?= clean($b['status']) ?>"><?= clean(ucfirst($b['status'])) ?></span></td>
                <td>
                    <form method="POST" class="inline-form">
                        <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                        <select name="status_baru" onchange="this.form.submit()">
                            <?php if (isAdmin() || isOwner()): ?>
                                <option value="pending" <?= $b['status']==='pending'?'selected':'' ?>>Pending</option>
                                <option value="dibayar" <?= $b['status']==='dibayar'?'selected':'' ?>>Dibayar</option>
                                <option value="selesai" <?= $b['status']==='selesai'?'selected':'' ?>>Selesai</option>
                                <option value="dibatalkan" <?= $b['status']==='dibatalkan'?'selected':'' ?>>Dibatalkan</option>
                            <?php else: // petugas: transisi terbatas ?>
                                <option value="dibayar" <?= $b['status']==='dibayar'?'selected':'' ?>>Dibayar</option>
                                <option value="selesai" <?= $b['status']==='selesai'?'selected':'' ?>>Selesai</option>
                            <?php endif; ?>
                        </select>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
