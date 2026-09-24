<?php
$pageTitle = "Form Lokasi Parkir";
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin(); // hanya admin; dicek sebelum header tampil agar redirect tidak gagal
require_once __DIR__ . '/admin_header.php';

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$lokasi = [
    'nama_lokasi' => '', 'alamat' => '', 'deskripsi' => '',
    'harga_motor' => 3000, 'harga_mobil' => 5000, 'harga_bus' => 15000,
    'kapasitas' => 50, 'slot_tersedia' => 50, 'gambar' => 'default.jpg', 'status' => 'aktif'
];

if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM lokasi_parkir WHERE id = ?");
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if ($found) $lokasi = $found;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lokasi  = clean($_POST['nama_lokasi'] ?? '');
    $alamat       = clean($_POST['alamat'] ?? '');
    $deskripsi    = clean($_POST['deskripsi'] ?? '');
    $harga_motor  = (float)($_POST['harga_motor'] ?? 0);
    $harga_mobil  = (float)($_POST['harga_mobil'] ?? 0);
    $harga_bus    = (float)($_POST['harga_bus'] ?? 0);
    $kapasitas    = (int)($_POST['kapasitas'] ?? 0);
    $slot         = (int)($_POST['slot_tersedia'] ?? 0);
    $gambar       = clean($_POST['gambar'] ?? 'default.jpg');
    $status       = clean($_POST['status'] ?? 'aktif');

    if ($nama_lokasi === '' || $alamat === '') {
        $errors[] = "Nama lokasi dan alamat wajib diisi.";
    }

    if (empty($errors)) {
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE lokasi_parkir SET
                nama_lokasi=?, alamat=?, deskripsi=?, harga_motor=?, harga_mobil=?, harga_bus=?,
                kapasitas=?, slot_tersedia=?, gambar=?, status=? WHERE id=?");
            $stmt->execute([$nama_lokasi, $alamat, $deskripsi, $harga_motor, $harga_mobil, $harga_bus, $kapasitas, $slot, $gambar, $status, $id]);
            redirectWithMessage('kelola_lokasi.php', 'Lokasi parkir berhasil diperbarui.', 'success');
        } else {
            $stmt = $db->prepare("INSERT INTO lokasi_parkir
                (nama_lokasi, alamat, deskripsi, harga_motor, harga_mobil, harga_bus, kapasitas, slot_tersedia, gambar, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nama_lokasi, $alamat, $deskripsi, $harga_motor, $harga_mobil, $harga_bus, $kapasitas, $slot, $gambar, $status]);
            redirectWithMessage('kelola_lokasi.php', 'Lokasi parkir berhasil ditambahkan.', 'success');
        }
    }
}
?>

<h1><?= $id > 0 ? 'Edit' : 'Tambah' ?> Lokasi Parkir</h1>

<?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><?= clean($e) ?></div>
<?php endforeach; ?>

<form method="POST" class="form-box">
    <label>Nama Lokasi</label>
    <input type="text" name="nama_lokasi" value="<?= clean($lokasi['nama_lokasi']) ?>" required>

    <label>Alamat</label>
    <input type="text" name="alamat" value="<?= clean($lokasi['alamat']) ?>" required>

    <label>Deskripsi</label>
    <textarea name="deskripsi" rows="3"><?= clean($lokasi['deskripsi']) ?></textarea>

    <label>Tarif Flat Motor (Rp, sekali bayar)</label>
    <input type="number" name="harga_motor" value="<?= (float)$lokasi['harga_motor'] ?>" step="500" required>

    <label>Tarif Flat Mobil (Rp, sekali bayar)</label>
    <input type="number" name="harga_mobil" value="<?= (float)$lokasi['harga_mobil'] ?>" step="500" required>

    <label>Tarif Flat Bus (Rp, sekali bayar)</label>
    <input type="number" name="harga_bus" value="<?= (float)$lokasi['harga_bus'] ?>" step="500" required>

    <label>Kapasitas Total</label>
    <input type="number" name="kapasitas" value="<?= (int)$lokasi['kapasitas'] ?>" min="1" required>

    <label>Slot Tersedia</label>
    <input type="number" name="slot_tersedia" value="<?= (int)$lokasi['slot_tersedia'] ?>" min="0" required>

    <label>Nama File Gambar (di folder assets/img/)</label>
    <input type="text" name="gambar" value="<?= clean($lokasi['gambar']) ?>">

    <label>Status</label>
    <select name="status">
        <option value="aktif" <?= $lokasi['status'] === 'aktif' ? 'selected' : '' ?>>Aktif</option>
        <option value="nonaktif" <?= $lokasi['status'] === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
    </select>

    <button type="submit" class="btn-primary"><?= $id > 0 ? 'Simpan Perubahan' : 'Tambah Lokasi' ?></button>
    <a href="kelola_lokasi.php" class="btn-secondary">Batal</a>
</form>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
