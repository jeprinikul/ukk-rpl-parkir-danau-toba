<?php
$pageTitle = "Kelola Kendaraan";
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin(); // hanya admin; dicek sebelum header tampil agar redirect tidak gagal
require_once __DIR__ . '/admin_header.php';

$db = getDB();

if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    try {
        $stmt = $db->prepare("DELETE FROM tb_kendaraan WHERE id_kendaraan = ?");
        $stmt->execute([$id]);
        redirectWithMessage('kelola_kendaraan.php', 'Data kendaraan berhasil dihapus.', 'success');
    } catch (PDOException $e) {
        redirectWithMessage('kelola_kendaraan.php', 'Tidak bisa menghapus: kendaraan ini masih punya riwayat transaksi.', 'danger');
    }
}

$kendaraanList = $db->query("SELECT k.*, u.name AS dicatat_oleh
                              FROM tb_kendaraan k
                              LEFT JOIN users u ON k.id_user = u.id
                              ORDER BY k.id_kendaraan DESC")->fetchAll();
?>

<div class="page-header-row">
    <h1>Kelola Kendaraan</h1>
    <a href="form_kendaraan.php" class="btn-primary">+ Tambah Kendaraan</a>
</div>

<table class="tabel-riwayat">
    <thead>
        <tr>
            <th>Plat Nomor</th><th>Jenis</th><th>Warna</th><th>Pemilik</th><th>Dicatat Oleh</th><th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php if (count($kendaraanList) === 0): ?>
            <tr><td colspan="6">Belum ada data kendaraan.</td></tr>
        <?php else: foreach ($kendaraanList as $k): ?>
            <tr>
                <td><?= clean($k['plat_nomor']) ?></td>
                <td><?= clean(ucfirst($k['jenis_kendaraan'])) ?></td>
                <td><?= clean($k['warna'] ?: '-') ?></td>
                <td><?= clean($k['pemilik'] ?: '-') ?></td>
                <td><?= clean($k['dicatat_oleh'] ?? '-') ?></td>
                <td>
                    <a href="form_kendaraan.php?id=<?= (int)$k['id_kendaraan'] ?>" class="btn-small">Edit</a>
                    <a href="kelola_kendaraan.php?hapus=<?= (int)$k['id_kendaraan'] ?>" class="btn-small btn-danger" onclick="return confirm('Yakin ingin menghapus data kendaraan ini?')">Hapus</a>
                </td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/admin_footer.php'; ?>-