<?php
$pageTitle = "Log Aktifitas";
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin(); // hanya admin; dicek sebelum header tampil agar redirect tidak gagal
require_once __DIR__ . '/admin_header.php';

$db = getDB();

// Filter opsional berdasarkan tanggal
$tanggal = $_GET['tanggal'] ?? '';

if ($tanggal !== '') {
    $stmt = $db->prepare("SELECT l.*, u.name AS nama_user, u.role
                           FROM tb_log_aktivitas l
                           LEFT JOIN users u ON l.id_user = u.id
                           WHERE DATE(l.waktu_aktivitas) = ?
                           ORDER BY l.waktu_aktivitas DESC");
    $stmt->execute([$tanggal]);
    $logList = $stmt->fetchAll();
} else {
    $logList = $db->query("SELECT l.*, u.name AS nama_user, u.role
                            FROM tb_log_aktivitas l
                            LEFT JOIN users u ON l.id_user = u.id
                            ORDER BY l.waktu_aktivitas DESC
                            LIMIT 200")->fetchAll();
}
?>

<div class="page-header-row">
    <h1>Log Aktifitas</h1>
</div>

<form method="GET" class="form-box" style="max-width:300px;">
    <label>Filter Tanggal</label>
    <input type="date" name="tanggal" value="<?= clean($tanggal) ?>">
    <button type="submit" class="btn-primary">Filter</button>
    <?php if ($tanggal !== ''): ?>
        <a href="log_aktivitas.php" class="btn-secondary">Reset</a>
    <?php endif; ?>
</form>

<table class="tabel-riwayat">
    <thead>
        <tr>
            <th>Waktu</th><th>User</th><th>Role</th><th>Aktivitas</th>
        </tr>
    </thead>
    <tbody>
        <?php if (count($logList) === 0): ?>
            <tr><td colspan="4">Tidak ada log aktivitas<?= $tanggal !== '' ? ' pada tanggal ini' : '' ?>.</td></tr>
        <?php else: foreach ($logList as $l): ?>
            <tr>
                <td><?= date("d/m/Y H:i:s", strtotime($l['waktu_aktivitas'])) ?></td>
                <td><?= clean($l['nama_user'] ?? 'Tidak diketahui') ?></td>
                <td><?= clean(ucfirst($l['role'] ?? '-')) ?></td>
                <td><?= clean($l['aktivitas']) ?></td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/admin_footer.php'; ?>