<?php
$pageTitle = "Form Tarif Parkir";
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin(); // hanya admin; dicek sebelum header tampil agar redirect tidak gagal
require_once __DIR__ . '/admin_header.php';

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$tarif = ['jenis_kendaraan' => 'motor', 'tarif_flat' => 0];

if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM tb_tarif WHERE id_tarif = ?");
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if ($found) $tarif = $found;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jenis_kendaraan = clean($_POST['jenis_kendaraan'] ?? '');
    $tarif_flat   = (float)($_POST['tarif_flat'] ?? 0);

    if ($jenis_kendaraan === '' || $tarif_flat <= 0) {
        $errors[] = "Jenis kendaraan dan tarif flat wajib diisi dengan benar.";
    }

    if (empty($errors)) {
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE tb_tarif SET jenis_kendaraan=?, tarif_flat=? WHERE id_tarif=?");
            $stmt->execute([$jenis_kendaraan, $tarif_flat, $id]);
            redirectWithMessage('kelola_tarif.php', 'Tarif berhasil diperbarui.', 'success');
        } else {
            $stmt = $db->prepare("INSERT INTO tb_tarif (jenis_kendaraan, tarif_flat) VALUES (?, ?)");
            $stmt->execute([$jenis_kendaraan, $tarif_flat]);
            redirectWithMessage('kelola_tarif.php', 'Tarif berhasil ditambahkan.', 'success');
        }
    }
}
?>

<h1><?= $id > 0 ? 'Edit' : 'Tambah' ?> Tarif Parkir</h1>

<?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><?= clean($e) ?></div>
<?php endforeach; ?>

<form method="POST" class="form-box">
    <label>Jenis Kendaraan</label>
    <select name="jenis_kendaraan" required>
        <option value="motor" <?= $tarif['jenis_kendaraan'] === 'motor' ? 'selected' : '' ?>>Motor</option>
        <option value="mobil" <?= $tarif['jenis_kendaraan'] === 'mobil' ? 'selected' : '' ?>>Mobil</option>
        <option value="lainnya" <?= $tarif['jenis_kendaraan'] === 'lainnya' ? 'selected' : '' ?>>Lainnya</option>
    </select>

    <label>Tarif Flat / Sekali Bayar (Rp)</label>
    <input type="number" name="tarif_flat" value="<?= (float)$tarif['tarif_flat'] ?>" step="500" min="0" required>

    <button type="submit" class="btn-primary"><?= $id > 0 ? 'Simpan Perubahan' : 'Tambah Tarif' ?></button>
    <a href="kelola_tarif.php" class="btn-secondary">Batal</a>
</form>

<?php require_once __DIR__ . '/admin_footer.php'; ?>