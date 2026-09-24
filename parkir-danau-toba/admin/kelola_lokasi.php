<?php
$pageTitle = "Kelola Lokasi Parkir";
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin(); // hanya admin; dicek sebelum header tampil agar redirect tidak gagal
require_once __DIR__ . '/admin_header.php';

$db = getDB();

// Hapus lokasi
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $stmt = $db->prepare("DELETE FROM lokasi_parkir WHERE id = ?");
    $stmt->execute([$id]);
    redirectWithMessage('kelola_lokasi.php', 'Lokasi parkir berhasil dihapus.', 'success');
}

$lokasiList = $db->query("SELECT * FROM lokasi_parkir ORDER BY id DESC")->fetchAll();
?>

<div class="page-header-row">
    <h1>Kelola Lokasi Parkir</h1>
    <a href="form_lokasi.php" class="btn-primary">+ Tambah Lokasi</a>
</div>

<table class="tabel-riwayat">
    <thead>
        <tr>
            <th>Nama Lokasi</th><th>Alamat</th><th>Tarif Flat (Mobil)</th><th>Kapasitas</th><th>Slot Tersedia</th><th>Status</th><th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($lokasiList as $l): ?>
            <tr>
                <td><?= clean($l['nama_lokasi']) ?></td>
                <td><?= clean($l['alamat']) ?></td>
                <td><?= formatRupiah($l['harga_mobil']) ?></td>
                <td><?= (int)$l['kapasitas'] ?></td>
                <td><?= (int)$l['slot_tersedia'] ?></td>
                <td><span class="badge badge-<?= $l['status'] === 'aktif' ? 'dibayar' : 'dibatalkan' ?>"><?= clean(ucfirst($l['status'])) ?></span></td>
                <td>
                    <a href="form_lokasi.php?id=<?= (int)$l['id'] ?>" class="btn-small">Edit</a>
                    <a href="kelola_lokasi.php?hapus=<?= (int)$l['id'] ?>" class="btn-small btn-danger" onclick="return confirm('Yakin ingin menghapus lokasi ini?')">Hapus</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
