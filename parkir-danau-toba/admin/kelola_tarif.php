<?php
$pageTitle = "Kelola Tarif Parkir";
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin(); // hanya admin; dicek sebelum header tampil agar redirect tidak gagal
require_once __DIR__ . '/admin_header.php';

$db = getDB();

// Hapus tarif
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    try {
        $stmt = $db->prepare("DELETE FROM tb_tarif WHERE id_tarif = ?");
        $stmt->execute([$id]);
        redirectWithMessage('kelola_tarif.php', 'Tarif berhasil dihapus.', 'success');
    } catch (PDOException $e) {
        redirectWithMessage('kelola_tarif.php', 'Tidak bisa menghapus: tarif ini masih dipakai di data transaksi.', 'danger');
    }
}

$tarifList = $db->query("SELECT * FROM tb_tarif ORDER BY jenis_kendaraan ASC")->fetchAll();
?>

<div class="page-header-row">
    <h1>Kelola Tarif Parkir</h1>
    <a href="form_tarif.php" class="btn-primary">+ Tambah Tarif</a>
</div>

<table class="tabel-riwayat">
    <thead>
        <tr>
            <th>Jenis Kendaraan</th><th>Tarif Flat (Sekali Bayar)</th><th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php if (count($tarifList) === 0): ?>
            <tr><td colspan="3">Belum ada data tarif.</td></tr>
        <?php else: foreach ($tarifList as $t): ?>
            <tr>
                <td><?= clean(ucfirst($t['jenis_kendaraan'])) ?></td>
                <td><?= formatRupiah($t['tarif_flat']) ?> / sekali bayar</td>
                <td>
                    <a href="form_tarif.php?id=<?= (int)$t['id_tarif'] ?>" class="btn-small">Edit</a>
                    <a href="kelola_tarif.php?hapus=<?= (int)$t['id_tarif'] ?>" class="btn-small btn-danger" onclick="return confirm('Yakin ingin menghapus tarif ini?')">Hapus</a>
                </td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/admin_footer.php'; ?>