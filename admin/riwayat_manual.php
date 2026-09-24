<?php
$pageTitle = "Riwayat Parkir Manual";
require_once __DIR__ . '/admin_header.php';
// Admin, Owner, dan Petugas semua boleh membuka halaman ini (requireStaff sudah
// dipanggil di admin_header.php). Petugas hanya melihat transaksi di lokasi tugasnya.

$db = getDB();

$lokasiPetugas = isPetugas() ? (int)($_SESSION['lokasi_id'] ?? 0) : null;

if (isPetugas() && !$lokasiPetugas) {
    echo "<div class='alert alert-danger'>Akun Anda belum ditugaskan ke lokasi manapun. Hubungi Owner/Admin untuk penugasan lokasi.</div>";
    require_once __DIR__ . '/admin_footer.php';
    exit;
}

$filterStatus = clean($_GET['status'] ?? '');
$filterLokasi = isPetugas() ? $lokasiPetugas : (clean($_GET['lokasi'] ?? ''));
$filterTanggal = clean($_GET['tanggal'] ?? '');

$sql = "SELECT t.id_parkir, t.waktu_masuk, t.waktu_keluar, t.biaya_total, t.status, t.metode_bayar,
               k.plat_nomor, k.jenis_kendaraan, k.pemilik,
               a.nama_area, l.id AS lokasi_id, l.nama_lokasi,
               tr.tarif_flat,
               u.name AS nama_petugas
        FROM tb_transaksi t
        JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
        JOIN tb_area_parkir a ON t.id_area = a.id_area
        JOIN lokasi_parkir l ON a.lokasi_id = l.id
        JOIN tb_tarif tr ON t.id_tarif = tr.id_tarif
        LEFT JOIN users u ON t.id_user = u.id
        WHERE 1=1 ";
$params = [];

if ($filterLokasi !== '' && $filterLokasi !== null) {
    $sql .= " AND l.id = ? ";
    $params[] = $filterLokasi;
}
if ($filterStatus !== '') {
    $sql .= " AND t.status = ? ";
    $params[] = $filterStatus;
}
if ($filterTanggal !== '') {
    $sql .= " AND DATE(t.waktu_masuk) = ? ";
    $params[] = $filterTanggal;
}
$sql .= " ORDER BY t.waktu_masuk DESC LIMIT 300";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$transaksiList = $stmt->fetchAll();

// Ringkasan pendapatan dari hasil filter saat ini (hanya transaksi yang sudah selesai/keluar)
$totalPendapatanFilter = 0;
foreach ($transaksiList as $t) {
    if ($t['status'] === 'keluar') $totalPendapatanFilter += (float)$t['biaya_total'];
}

$daftarLokasi = isPetugas() ? [] : $db->query("SELECT id, nama_lokasi FROM lokasi_parkir ORDER BY nama_lokasi")->fetchAll();
?>

<h1>Riwayat Parkir Manual</h1>
<p class="hint">
    Daftar transaksi walk-in (pengunjung yang datang langsung tanpa booking online), dicatat oleh Petugas di lapangan.
    <?php if (isPetugas()): ?>Hanya menampilkan transaksi di lokasi tugas Anda.<?php endif; ?>
</p>

<form method="GET" class="filter-row" style="display:flex;gap:0.6rem;flex-wrap:wrap;align-items:center;margin-bottom:1.2rem;">
    <?php if (!isPetugas()): ?>
        <select name="lokasi" onchange="this.form.submit()">
            <option value="">Semua Lokasi</option>
            <?php foreach ($daftarLokasi as $l): ?>
                <option value="<?= (int)$l['id'] ?>" <?= (string)$filterLokasi === (string)$l['id'] ? 'selected' : '' ?>>
                    <?= clean($l['nama_lokasi']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>
    <select name="status" onchange="this.form.submit()">
        <option value="">Semua Status</option>
        <option value="masuk" <?= $filterStatus === 'masuk' ? 'selected' : '' ?>>Masih Parkir</option>
        <option value="keluar" <?= $filterStatus === 'keluar' ? 'selected' : '' ?>>Selesai</option>
    </select>
    <input type="date" name="tanggal" value="<?= clean($filterTanggal) ?>" onchange="this.form.submit()">
    <?php if ($filterStatus !== '' || $filterTanggal !== '' || ($filterLokasi !== '' && $filterLokasi !== null)): ?>
        <a href="riwayat_manual.php" class="btn-small">Reset Filter</a>
    <?php endif; ?>
    <span style="margin-left:auto;font-weight:600;color:var(--lake-deep);">
        Total pendapatan (hasil filter): <?= formatRupiah($totalPendapatanFilter) ?>
    </span>
</form>

<table class="tabel-riwayat">
    <thead>
        <tr>
            <th>No. Transaksi</th><th>Plat Nomor</th><th>Jenis</th><th>Lokasi</th><th>Area</th>
            <th>Masuk</th><th>Keluar</th><th>Biaya</th><th>Bayar</th><th>Petugas</th><th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php if (count($transaksiList) === 0): ?>
            <tr><td colspan="11">Belum ada transaksi parkir manual.</td></tr>
        <?php else: foreach ($transaksiList as $t): ?>
            <tr>
                <td>#<?= (int)$t['id_parkir'] ?></td>
                <td><?= clean($t['plat_nomor']) ?></td>
                <td><?= clean(ucfirst($t['jenis_kendaraan'])) ?></td>
                <td><?= clean($t['nama_lokasi']) ?></td>
                <td><?= clean($t['nama_area']) ?></td>
                <td><?= date("d/m/Y H:i", strtotime($t['waktu_masuk'])) ?></td>
                <td><?= $t['waktu_keluar'] ? date("d/m/Y H:i", strtotime($t['waktu_keluar'])) : '-' ?></td>
                <td><?= $t['biaya_total'] ? formatRupiah($t['biaya_total']) : '-' ?></td>
                <td><?= $t['status'] === 'keluar' ? (($t['metode_bayar'] ?? 'tunai') === 'qris' ? 'QRIS' : 'Tunai') : '-' ?></td>
                <td><?= clean($t['nama_petugas'] ?? '-') ?></td>
                <td>
                    <span class="badge badge-<?= $t['status'] === 'keluar' ? 'selesai' : 'pending' ?>">
                        <?= $t['status'] === 'keluar' ? 'Selesai' : 'Masih Parkir' ?>
                    </span>
                </td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
