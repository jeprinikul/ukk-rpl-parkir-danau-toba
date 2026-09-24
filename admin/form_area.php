<?php
$pageTitle = "Form Area Parkir";
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin(); // hanya admin; dicek sebelum header tampil agar redirect tidak gagal
require_once __DIR__ . '/admin_header.php';

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$area = ['nama_area' => '', 'kapasitas' => 10, 'terisi' => 0, 'lokasi_id' => ''];

if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM tb_area_parkir WHERE id_area = ?");
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if ($found) $area = $found;
}

$lokasiList = $db->query("SELECT id, nama_lokasi FROM lokasi_parkir ORDER BY nama_lokasi ASC")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_area = clean($_POST['nama_area'] ?? '');
    $lokasi_id = (int)($_POST['lokasi_id'] ?? 0);
    $kapasitas = (int)($_POST['kapasitas'] ?? 0);
    $terisi    = (int)($_POST['terisi'] ?? 0);

    if ($nama_area === '' || $lokasi_id <= 0 || $kapasitas <= 0) {
        $errors[] = "Nama area, lokasi, dan kapasitas wajib diisi dengan benar.";
    } elseif ($terisi > $kapasitas) {
        $errors[] = "Jumlah terisi tidak boleh lebih besar dari kapasitas.";
    }

    if (empty($errors)) {
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE tb_area_parkir SET nama_area=?, lokasi_id=?, kapasitas=?, terisi=? WHERE id_area=?");
            $stmt->execute([$nama_area, $lokasi_id, $kapasitas, $terisi, $id]);
            redirectWithMessage('kelola_area.php', 'Area parkir berhasil diperbarui.', 'success');
        } else {
            $stmt = $db->prepare("INSERT INTO tb_area_parkir (nama_area, lokasi_id, kapasitas, terisi) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nama_area, $lokasi_id, $kapasitas, $terisi]);
            redirectWithMessage('kelola_area.php', 'Area parkir berhasil ditambahkan.', 'success');
        }
    }
}
?>

<h1><?= $id > 0 ? 'Edit' : 'Tambah' ?> Area Parkir</h1>

<?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><?= clean($e) ?></div>
<?php endforeach; ?>

<form method="POST" class="form-box">
    <label>Nama Area</label>
    <input type="text" name="nama_area" value="<?= clean($area['nama_area']) ?>" placeholder="Contoh: Area Depan Pelabuhan" required>

    <label>Lokasi Wisata</label>
    <select name="lokasi_id" required>
        <option value="">-- Pilih Lokasi --</option>
        <?php foreach ($lokasiList as $l): ?>
            <option value="<?= (int)$l['id'] ?>" <?= (int)($area['lokasi_id'] ?? 0) === (int)$l['id'] ? 'selected' : '' ?>>
                <?= clean($l['nama_lokasi']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <p class="hint">Petugas yang ditugaskan ke lokasi ini akan melihat area tersebut di dashboard parkir manualnya.</p>

    <label>Kapasitas Total</label>
    <input type="number" name="kapasitas" value="<?= (int)$area['kapasitas'] ?>" min="1" required>

    <label>Terisi Saat Ini</label>
    <input type="number" name="terisi" value="<?= (int)$area['terisi'] ?>" min="0">
    <p class="hint">Biasanya angka ini terisi otomatis dari transaksi Petugas. Ubah manual hanya kalau perlu koreksi data.</p>

    <button type="submit" class="btn-primary"><?= $id > 0 ? 'Simpan Perubahan' : 'Tambah Area' ?></button>
    <a href="kelola_area.php" class="btn-secondary">Batal</a>
</form>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
