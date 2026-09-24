<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireOwner(); // hanya owner yang boleh kelola akun staf

$db = getDB();

// Nonaktifkan / aktifkan akun
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    if ($id !== (int)$_SESSION['id_user']) { // tidak bisa menonaktifkan diri sendiri
        $stmt = $db->prepare("SELECT status_akun FROM users WHERE id = ? AND role IN ('admin','petugas')");
        $stmt->execute([$id]);
        $u = $stmt->fetch();
        if ($u) {
            $baru = $u['status_akun'] === 'aktif' ? 'nonaktif' : 'aktif';
            $stmt = $db->prepare("UPDATE users SET status_akun = ? WHERE id = ?");
            $stmt->execute([$baru, $id]);
        }
    }
    redirectWithMessage('kelola_user.php', 'Status akun berhasil diperbarui.', 'success');
}

// Hapus akun staf
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    if ($id !== (int)$_SESSION['id_user']) {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role IN ('admin','petugas')");
        $stmt->execute([$id]);
        redirectWithMessage('kelola_user.php', 'Akun staf berhasil dihapus.', 'success');
    }
    redirectWithMessage('kelola_user.php', 'Anda tidak dapat menghapus akun sendiri.', 'danger');
}

$staffList = $db->query("SELECT u.*, l.nama_lokasi FROM users u
    LEFT JOIN lokasi_parkir l ON l.id = u.lokasi_id
    WHERE u.role IN ('admin','petugas','owner')
    ORDER BY FIELD(u.role,'owner','admin','petugas'), u.name ASC")->fetchAll();

// Baru sekarang include header (mulai cetak HTML)
$pageTitle = "Kelola Akun Staf";
require_once __DIR__ . '/admin_header.php';
?>

<div class="page-header-row">
    <h1>Kelola Akun Staf</h1>
    <a href="form_user.php" class="btn-primary">+ Tambah Akun Staf</a>
</div>

<table class="tabel-riwayat">
    <thead>
        <tr>
            <th>Nama</th><th>Email</th><th>Role</th><th>Lokasi Tugas</th><th>Status</th><th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($staffList as $s): ?>
            <tr>
                <td><?= clean($s['name']) ?></td>
                <td><?= clean($s['email']) ?></td>
                <td><span class="badge badge-<?= $s['role']==='owner' ? 'selesai' : ($s['role']==='admin' ? 'dibayar' : 'pending') ?>"><?= clean(labelRole($s['role'])) ?></span></td>
                <td><?= $s['nama_lokasi'] ? clean($s['nama_lokasi']) : '-' ?></td>
                <td><span class="badge badge-<?= $s['status_akun']==='aktif' ? 'selesai' : 'dibatalkan' ?>"><?= clean(ucfirst($s['status_akun'])) ?></span></td>
                <td>
                    <?php if ($s['role'] !== 'owner'): ?>
                        <a href="form_user.php?id=<?= (int)$s['id'] ?>" class="btn-small">Edit</a>
                        <a href="kelola_user.php?toggle=<?= (int)$s['id'] ?>" class="btn-small">
                            <?= $s['status_akun']==='aktif' ? 'Nonaktifkan' : 'Aktifkan' ?>
                        </a>
                        <a href="kelola_user.php?hapus=<?= (int)$s['id'] ?>" class="btn-small btn-danger" onclick="return confirm('Yakin ingin menghapus akun ini?')">Hapus</a>
                    <?php else: ?>
                        <span class="hint">Akun Owner</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/admin_footer.php'; ?>