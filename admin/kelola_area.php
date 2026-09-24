<?php
$pageTitle = "Kelola Area Parkir";
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin(); // hanya admin; dicek sebelum header tampil agar redirect tidak gagal
require_once __DIR__ . '/admin_header.php';

$db = getDB();

if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    try {
        $stmt = $db->prepare("DELETE FROM tb_area_parkir WHERE id_area = ?");
        $stmt->execute([$id]);
        redirectWithMessage('kelola_area.php', 'Area parkir berhasil dihapus.', 'success');
    } catch (PDOException $e) {
        redirectWithMessage('kelola_area.php', 'Tidak bisa menghapus: area ini masih dipakai di data transaksi.', 'danger');
    }
}

$areaList = $db->query("SELECT a.*, l.nama_lokasi
                         FROM tb_area_parkir a
                         JOIN lokasi_parkir l ON a.lokasi_id = l.id
                         ORDER BY l.nama_lokasi ASC, a.nama_area ASC")->fetchAll();
?>

<div class="page-header-row">
    <h1>Kelola Area Parkir</h1>
    <a href="form_area.php" class="btn-primary">+ Tambah Area</a>
</div>
<p class="hint">Area parkir adalah titik fisik di tiap lokasi wisata yang dipakai Petugas untuk mencatat parkir manual (walk-in).</p>

<table class="tabel-riwayat">
    <thead>
        <tr>
            <th>Nama Area</th><th>Lokasi</th><th>Kapasitas</th><th>Terisi</th><th>Slot Kosong</th><th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php if (count($areaList) === 0): ?>
            <tr><td colspan="6">Belum ada data area parkir.</td></tr>
        <?php else: foreach ($areaList as $a): ?>
            <tr>
                <td><?= clean($a['nama_area']) ?></td>
                <td><?= clean($a['nama_lokasi']) ?></td>
                <td><?= (int)$a['kapasitas'] ?></td>
                <td><?= (int)$a['terisi'] ?></td>
                <td>
                    <span class="badge badge-<?= ($a['kapasitas'] - $a['terisi']) > 0 ? 'dibayar' : 'dibatalkan' ?>">
                        <?= max(0, (int)$a['kapasitas'] - (int)$a['terisi']) ?> slot
                    </span>
                </td>
                <td>
                    <a href="form_area.php?id=<?= (int)$a['id_area'] ?>" class="btn-small">Edit</a>
                    <a href="kelola_area.php?hapus=<?= (int)$a['id_area'] ?>" class="btn-small btn-danger" onclick="return confirm('Yakin ingin menghapus area ini?')">Hapus</a>
                </td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
