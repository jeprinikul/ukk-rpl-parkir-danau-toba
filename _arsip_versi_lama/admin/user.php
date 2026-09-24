<?php
/**
 * admin/user.php — CRUD User (khusus role: admin)
 */
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');

$db = getDB();
$pesan = '';
$pesanTipe = 'ok'; // ok | error

/* ---------------- Tambah / Edit User ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'simpan') {
    $idUser  = (int)($_POST['id_user'] ?? 0);
    $nama    = trim($_POST['nama_lengkap'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'petugas';

    if ($nama === '' || $username === '' || !in_array($role, ['admin', 'petugas', 'owner'], true)) {
        $pesan = 'Nama, username, dan role wajib diisi dengan benar.';
        $pesanTipe = 'error';
    } elseif ($idUser === 0 && $password === '') {
        $pesan = 'Password wajib diisi untuk user baru.';
        $pesanTipe = 'error';
    } else {
        try {
            if ($idUser > 0) {
                // Update
                if ($password !== '') {
                    $stmt = $db->prepare("UPDATE tb_user SET nama_lengkap = ?, username = ?, password = ?, role = ? WHERE id_user = ?");
                    $stmt->execute([$nama, $username, password_hash($password, PASSWORD_DEFAULT), $role, $idUser]);
                } else {
                    $stmt = $db->prepare("UPDATE tb_user SET nama_lengkap = ?, username = ?, role = ? WHERE id_user = ?");
                    $stmt->execute([$nama, $username, $role, $idUser]);
                }
                catatLog($db, $_SESSION['id_user'], "Mengubah data user #$idUser ($username)");
                $pesan = 'Data user berhasil diperbarui.';
            } else {
                // Insert
                $stmt = $db->prepare("INSERT INTO tb_user (nama_lengkap, username, password, role, status_aktif) VALUES (?, ?, ?, ?, 1)");
                $stmt->execute([$nama, $username, password_hash($password, PASSWORD_DEFAULT), $role]);
                catatLog($db, $_SESSION['id_user'], "Menambahkan user baru: $username ($role)");
                $pesan = 'User baru berhasil ditambahkan.';
            }
        } catch (PDOException $e) {
            $pesan = (str_contains($e->getMessage(), 'Duplicate'))
                ? 'Username tersebut sudah dipakai user lain.'
                : 'Gagal menyimpan data user.';
            $pesanTipe = 'error';
        }
    }
}

/* ---------------- Aktifkan / Nonaktifkan ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'toggle_status') {
    $idUser = (int)($_POST['id_user'] ?? 0);
    if ($idUser === (int)$_SESSION['id_user']) {
        $pesan = 'Kamu tidak bisa menonaktifkan akunmu sendiri.';
        $pesanTipe = 'error';
    } else {
        $db->prepare("UPDATE tb_user SET status_aktif = 1 - status_aktif WHERE id_user = ?")->execute([$idUser]);
        catatLog($db, $_SESSION['id_user'], "Mengubah status aktif user #$idUser");
        $pesan = 'Status user berhasil diubah.';
    }
}

/* ---------------- Hapus User ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    $idUser = (int)($_POST['id_user'] ?? 0);
    if ($idUser === (int)$_SESSION['id_user']) {
        $pesan = 'Kamu tidak bisa menghapus akunmu sendiri.';
        $pesanTipe = 'error';
    } else {
        try {
            $db->prepare("DELETE FROM tb_user WHERE id_user = ?")->execute([$idUser]);
            catatLog($db, $_SESSION['id_user'], "Menghapus user #$idUser");
            $pesan = 'User berhasil dihapus.';
        } catch (PDOException $e) {
            $pesan = 'User ini tidak bisa dihapus karena masih memiliki data transaksi/kendaraan terkait. Nonaktifkan saja sebagai gantinya.';
            $pesanTipe = 'error';
        }
    }
}

/* ---------------- Data untuk form edit ---------------- */
$userDiedit = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM tb_user WHERE id_user = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $userDiedit = $stmt->fetch();
}

$daftarUser = $db->query("SELECT * FROM tb_user ORDER BY role, nama_lengkap ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola User — Dashboard Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Fraunces:wght@500;600&display=swap" rel="stylesheet">
<style>
:root {
  --lake-deep:#0F2C33; --lake-mid:#1B4451; --gold:#D9A441; --gold-dim:#B78530;
  --ulos-red:#8C3B3B; --paper:#F5F2EA; --ink:#16262A; --muted:#6C7C7C; --line:rgba(15,44,51,0.12);
  --ok-bg:#E6F0EC; --ok-ink:#1B4451; --err-bg:#F5E4E4; --err-ink:#8C3B3B;
  --font-display:"Fraunces",Georgia,serif; --font-body:"Inter",-apple-system,BlinkMacSystemFont,sans-serif;
}
* { box-sizing: border-box; }
body { margin:0; font-family:var(--font-body); background:var(--paper); color:var(--ink); }
.admin-header {
  background: var(--lake-deep); color: var(--paper); padding: 1.5rem 2rem;
  display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem;
}
.admin-header h1 { font-family:var(--font-display); font-size:1.5rem; margin:0; font-weight:600; }
.admin-header .subjudul { color:#C9D6D4; font-size:0.9rem; margin:0.2rem 0 0; }
.admin-header .kanan { display:flex; align-items:center; gap:1.25rem; }
.admin-header .siapa { font-size:0.85rem; color:#C9D6D4; }
.admin-header a { color: var(--gold); text-decoration:none; font-size:0.9rem; font-weight:600; }

.wrap { max-width: 1280px; margin: 0 auto; padding: 2rem; }
.breadcrumb { font-size:0.85rem; color:var(--muted); margin-bottom:1.25rem; }
.breadcrumb a { color: var(--lake-mid); text-decoration:none; font-weight:600; }

.pesan { padding:0.75rem 1rem; border-radius:8px; margin-bottom:1.25rem; font-size:0.9rem; }
.pesan.ok { background:var(--ok-bg); color:var(--ok-ink); }
.pesan.error { background:var(--err-bg); color:var(--err-ink); }

.grid-2 { display:grid; grid-template-columns: 340px 1fr; gap:1.5rem; align-items:start; }
@media (max-width: 900px) { .grid-2 { grid-template-columns: 1fr; } }

.panel { background:#fff; border:1px solid var(--line); border-radius:10px; padding:1.5rem; margin-bottom:1.5rem; }
.panel h2 { font-family:var(--font-display); font-size:1.1rem; color:var(--lake-deep); margin:0 0 1rem; font-weight:600; }

label { display:block; font-size:0.85rem; font-weight:600; margin-bottom:0.35rem; }
input, select {
  width:100%; padding:0.65rem 0.8rem; border:1px solid var(--line); border-radius:8px;
  font-family:var(--font-body); font-size:0.92rem; margin-bottom:1rem;
}
.hint { font-size:0.78rem; color:var(--muted); margin:-0.7rem 0 1rem; }
.btn { background: var(--gold); color: var(--lake-deep); border:none; padding:0.7rem 1.2rem; border-radius:8px; font-weight:700; cursor:pointer; font-size:0.9rem; width:100%; }
.btn:hover { background: var(--gold-dim); }
.btn.secondary { background:#fff; color:var(--lake-mid); border:1px solid var(--line); }
.btn.kecil { width:auto; padding:0.4rem 0.75rem; font-size:0.78rem; }
.btn.merah { background:var(--ulos-red); color:#fff; }
.btn.merah:hover { background:#742f2f; }

table { width:100%; border-collapse:collapse; font-size:0.88rem; }
th, td { text-align:left; padding:0.6rem 0.5rem; border-bottom:1px solid var(--line); vertical-align:middle; }
th { color:var(--muted); font-weight:600; font-size:0.78rem; text-transform:uppercase; }
.badge { display:inline-block; padding:0.15rem 0.6rem; border-radius:99px; font-size:0.78rem; font-weight:600; }
.badge.admin { background:#E4D2B4; color:#8C5A16; }
.badge.petugas { background:#CFE3DE; color:#1B4451; }
.badge.owner { background:#E6D3D3; color:#8C3B3B; }
.badge.aktif { background:#DCEFE2; color:#1F5138; }
.badge.nonaktif { background:#EEE; color:#888; }
form.inline { display:inline; }
.aksi-cell { display:flex; gap:0.4rem; flex-wrap:wrap; }
</style>
</head>
<body>

<div class="admin-header">
    <div>
        <h1>Kelola User</h1>
        <p class="subjudul">Tambah, ubah, aktifkan/nonaktifkan akun admin, petugas, dan owner</p>
    </div>
    <div class="kanan">
        <span class="siapa">👤 <?= htmlspecialchars($_SESSION['nama_lengkap']) ?> (Admin)</span>
        <a href="../logout.php">Keluar</a>
    </div>
</div>

<div class="wrap">
    <p class="breadcrumb"><a href="dashboard.php">← Kembali ke Dashboard</a></p>

    <?php if ($pesan): ?>
        <div class="pesan <?= $pesanTipe ?>"><?= htmlspecialchars($pesan) ?></div>
    <?php endif; ?>

    <div class="grid-2">
        <div class="panel">
            <h2><?= $userDiedit ? 'Edit User' : 'Tambah User Baru' ?></h2>
            <form method="POST">
                <input type="hidden" name="aksi" value="simpan">
                <input type="hidden" name="id_user" value="<?= $userDiedit ? (int)$userDiedit['id_user'] : 0 ?>">

                <label>Nama Lengkap</label>
                <input type="text" name="nama_lengkap" required value="<?= htmlspecialchars($userDiedit['nama_lengkap'] ?? '') ?>">

                <label>Username</label>
                <input type="text" name="username" required value="<?= htmlspecialchars($userDiedit['username'] ?? '') ?>">

                <label>Password <?= $userDiedit ? '(kosongkan jika tidak diubah)' : '' ?></label>
                <input type="password" name="password" <?= $userDiedit ? '' : 'required' ?>>
                <?php if (!$userDiedit): ?><p class="hint">Minimal 6 karakter, disarankan.</p><?php endif; ?>

                <label>Role</label>
                <select name="role" required>
                    <?php foreach (['admin' => 'Admin', 'petugas' => 'Petugas', 'owner' => 'Owner'] as $val => $lbl): ?>
                        <option value="<?= $val ?>" <?= ($userDiedit['role'] ?? '') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn"><?= $userDiedit ? 'Simpan Perubahan' : 'Tambah User' ?></button>
            </form>
            <?php if ($userDiedit): ?>
                <a href="user.php" style="display:block; margin-top:0.75rem;"><button type="button" class="btn secondary">Batal Edit</button></a>
            <?php endif; ?>
        </div>

        <div class="panel">
            <h2>Daftar User (<?= count($daftarUser) ?>)</h2>
            <table>
                <thead>
                    <tr><th>Nama</th><th>Username</th><th>Role</th><th>Status</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                <?php foreach ($daftarUser as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['nama_lengkap']) ?></td>
                        <td><?= htmlspecialchars($u['username']) ?></td>
                        <td><span class="badge <?= htmlspecialchars($u['role']) ?>"><?= htmlspecialchars($u['role']) ?></span></td>
                        <td><span class="badge <?= $u['status_aktif'] ? 'aktif' : 'nonaktif' ?>"><?= $u['status_aktif'] ? 'Aktif' : 'Nonaktif' ?></span></td>
                        <td class="aksi-cell">
                            <a href="user.php?edit=<?= (int)$u['id_user'] ?>"><button type="button" class="btn secondary kecil">Edit</button></a>

                            <form method="POST" class="inline">
                                <input type="hidden" name="aksi" value="toggle_status">
                                <input type="hidden" name="id_user" value="<?= (int)$u['id_user'] ?>">
                                <button type="submit" class="btn secondary kecil"><?= $u['status_aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                            </form>

                            <form method="POST" class="inline" onsubmit="return confirm('Yakin hapus user ini? Tindakan tidak bisa dibatalkan.');">
                                <input type="hidden" name="aksi" value="hapus">
                                <input type="hidden" name="id_user" value="<?= (int)$u['id_user'] ?>">
                                <button type="submit" class="btn merah kecil">Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>