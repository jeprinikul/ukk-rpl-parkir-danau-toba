<?php
$pageTitle = "Form Akun Staf";
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireOwner(); // hanya owner; dicek sebelum header tampil agar redirect tidak gagal
require_once __DIR__ . '/admin_header.php';

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$staf = [
    'name' => '', 'email' => '', 'no_hp' => '', 'role' => 'petugas',
    'lokasi_id' => '', 'status_akun' => 'aktif'
];

if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role IN ('admin','petugas')");
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if ($found) $staf = $found;
}

$lokasiList = $db->query("SELECT id, nama_lokasi FROM lokasi_parkir ORDER BY nama_lokasi ASC")->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = clean($_POST['name'] ?? '');
    $email     = clean($_POST['email'] ?? '');
    $no_hp     = clean($_POST['no_hp'] ?? '');
    $role      = clean($_POST['role'] ?? 'petugas');
    $lokasi_id = $_POST['lokasi_id'] !== '' ? (int)$_POST['lokasi_id'] : null;
    $pass      = $_POST['password'] ?? '';
    $status_akun = clean($_POST['status_akun'] ?? 'aktif');

    if ($name === '' || $email === '') {
        $errors[] = "Nama dan email wajib diisi.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format email tidak valid.";
    }
    if (!in_array($role, ['admin', 'petugas'])) {
        $errors[] = "Role tidak valid.";
    }
    if ($role === 'petugas' && !$lokasi_id) {
        $errors[] = "Petugas wajib ditugaskan ke satu lokasi parkir.";
    }
    if ($id === 0 && strlen($pass) < 6) {
        $errors[] = "Password minimal 6 karakter untuk akun baru.";
    }
    if ($pass !== '' && strlen($pass) < 6) {
        $errors[] = "Password baru minimal 6 karakter.";
    }

    // Cek email duplikat
    if (empty($errors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $id]);
        if ($stmt->fetch()) {
            $errors[] = "Email sudah digunakan akun lain.";
        }
    }

    // Admin tidak perlu lokasi_id
    if ($role === 'admin') {
        $lokasi_id = null;
    }

    if (empty($errors)) {
        if ($id > 0) {
            if ($pass !== '') {
                $hash = password_hash($pass, PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE users SET name=?, email=?, no_hp=?, role=?, lokasi_id=?, status_akun=?, password=? WHERE id=?");
                $stmt->execute([$name, $email, $no_hp, $role, $lokasi_id, $status_akun, $hash, $id]);
            } else {
                $stmt = $db->prepare("UPDATE users SET name=?, email=?, no_hp=?, role=?, lokasi_id=?, status_akun=? WHERE id=?");
                $stmt->execute([$name, $email, $no_hp, $role, $lokasi_id, $status_akun, $id]);
            }
            redirectWithMessage('kelola_user.php', 'Akun staf berhasil diperbarui.', 'success');
        } else {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $stmt = $db->prepare("INSERT INTO users (name, email, password, no_hp, role, lokasi_id, status_akun) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $hash, $no_hp, $role, $lokasi_id, $status_akun]);
            redirectWithMessage('kelola_user.php', 'Akun staf berhasil ditambahkan.', 'success');
        }
    }
}
?>

<h1><?= $id > 0 ? 'Edit' : 'Tambah' ?> Akun Staf</h1>

<?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><?= clean($e) ?></div>
<?php endforeach; ?>

<form method="POST" class="form-box">
    <label>Nama Lengkap</label>
    <input type="text" name="name" value="<?= clean($staf['name']) ?>" required>

    <label>Email</label>
    <input type="email" name="email" value="<?= clean($staf['email']) ?>" required>

    <label>No. HP</label>
    <input type="text" name="no_hp" value="<?= clean($staf['no_hp'] ?? '') ?>">

    <label>Role</label>
    <select name="role" id="roleSelect" onchange="toggleLokasi()">
        <option value="petugas" <?= $staf['role']==='petugas' ? 'selected' : '' ?>>Petugas</option>
        <option value="admin" <?= $staf['role']==='admin' ? 'selected' : '' ?>>Admin</option>
    </select>

    <div id="lokasiWrapper">
        <label>Lokasi Tugas (khusus Petugas)</label>
        <select name="lokasi_id">
            <option value="">-- Pilih Lokasi --</option>
            <?php foreach ($lokasiList as $l): ?>
                <option value="<?= (int)$l['id'] ?>" <?= (int)($staf['lokasi_id'] ?? 0) === (int)$l['id'] ? 'selected' : '' ?>>
                    <?= clean($l['nama_lokasi']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <label>Status Akun</label>
    <select name="status_akun">
        <option value="aktif" <?= ($staf['status_akun'] ?? 'aktif') === 'aktif' ? 'selected' : '' ?>>Aktif</option>
        <option value="nonaktif" <?= ($staf['status_akun'] ?? '') === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
    </select>

    <label>Password <?= $id > 0 ? '(kosongkan jika tidak ingin mengubah)' : '' ?></label>
    <input type="password" name="password" <?= $id > 0 ? '' : 'required' ?>>

    <button type="submit" class="btn-primary"><?= $id > 0 ? 'Simpan Perubahan' : 'Tambah Akun' ?></button>
    <a href="kelola_user.php" class="btn-secondary">Batal</a>
</form>

<script>
function toggleLokasi() {
    const role = document.getElementById('roleSelect').value;
    document.getElementById('lokasiWrapper').style.display = role === 'petugas' ? 'block' : 'none';
}
toggleLokasi();
</script>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
