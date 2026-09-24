<?php
$pageTitle = "Form Kendaraan";
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin(); // hanya admin; dicek sebelum header tampil agar redirect tidak gagal
require_once __DIR__ . '/admin_header.php';

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$kendaraan = ['plat_nomor' => '', 'jenis_kendaraan' => 'motor', 'warna' => '', 'pemilik' => ''];

if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM tb_kendaraan WHERE id_kendaraan = ?");
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if ($found) $kendaraan = $found;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plat_nomor      = clean($_POST['plat_nomor'] ?? '');
    $jenis_kendaraan = clean($_POST['jenis_kendaraan'] ?? '');
    $warna           = clean($_POST['warna'] ?? '');
    $pemilik         = clean($_POST['pemilik'] ?? '');

    if ($plat_nomor === '' || $jenis_kendaraan === '') {
        $errors[] = "Plat nomor dan jenis kendaraan wajib diisi.";
    }

    if (empty($errors)) {
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE tb_kendaraan SET plat_nomor=?, jenis_kendaraan=?, warna=?, pemilik=? WHERE id_kendaraan=?");
            $stmt->execute([$plat_nomor, $jenis_kendaraan, $warna, $pemilik, $id]);
            redirectWithMessage('kelola_kendaraan.php', 'Data kendaraan berhasil diperbarui.', 'success');
        } else {
            $stmt = $db->prepare("INSERT INTO tb_kendaraan (plat_nomor, jenis_kendaraan, warna, pemilik, id_user)
                                   VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$plat_nomor, $jenis_kendaraan, $warna, $pemilik, $_SESSION['id_user']]);
            redirectWithMessage('kelola_kendaraan.php', 'Data kendaraan berhasil ditambahkan.', 'success');
        }
    }
}
?>

<h1><?= $id > 0 ? 'Edit' : 'Tambah' ?> Kendaraan</h1>

<?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><?= clean($e) ?></div>
<?php endforeach; ?>

<form method="POST" class="form-box">
    <label>Plat Nomor</label>
    <input type="text" name="plat_nomor" value="<?= clean($kendaraan['plat_nomor']) ?>" required>

    <label>Jenis Kendaraan</label>
    <select name="jenis_kendaraan" required>
        <option value="motor" <?= $kendaraan['jenis_kendaraan'] === 'motor' ? 'selected' : '' ?>>Motor</option>
        <option value="mobil" <?= $kendaraan['jenis_kendaraan'] === 'mobil' ? 'selected' : '' ?>>Mobil</option>
        <option value="lainnya" <?= $kendaraan['jenis_kendaraan'] === 'lainnya' ? 'selected' : '' ?>>Lainnya</option>
    </select>

    <label>Warna</label>
    <input type="text" name="warna" value="<?= clean($kendaraan['warna']) ?>">

    <label>Nama Pemilik</label>
    <input type="text" name="pemilik" value="<?= clean($kendaraan['pemilik']) ?>">

    <button type="submit" class="btn-primary"><?= $id > 0 ? 'Simpan Perubahan' : 'Tambah Kendaraan' ?></button>
    <a href="kelola_kendaraan.php" class="btn-secondary">Batal</a>
</form>

<?php require_once __DIR__ . '/admin_footer.php'; ?>