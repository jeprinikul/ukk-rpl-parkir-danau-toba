<?php
/**
 * admin/kendaraan.php — CRUD Kendaraan (khusus role: admin)
 */
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');

$db = getDB();
$pesan = '';
$pesanTipe = 'ok'; // ok | error

$pilihanJenis = ['motor' => 'Motor', 'mobil' => 'Mobil', 'lainnya' => 'Lainnya'];

/* ---------------- Tambah / Edit Kendaraan ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'simpan') {
    $idKendaraan = (int)($_POST['id_kendaraan'] ?? 0);
    $plat        = strtoupper(trim($_POST['plat_nomor'] ?? ''));
    $jenis       = trim($_POST['jenis_kendaraan'] ?? '');
    $warna       = trim($_POST['warna'] ?? '');
    $pemilik     = trim($_POST['pemilik'] ?? '');
    $idUser      = (int)($_POST['id_user'] ?? 0);

    if ($plat === '' || !in_array($jenis, array_keys($pilihanJenis), true) || $pemilik === '' || $idUser <= 0) {
        $pesan = 'Plat nomor, jenis kendaraan, pemilik, dan akun pemilik wajib diisi dengan benar.';
        $pesanTipe = 'error';
    } else {
        try {
            if ($idKendaraan > 0) {
                $stmt = $db->prepare("UPDATE tb_kendaraan SET plat_nomor = ?, jenis_kendaraan = ?, warna = ?, pemilik = ?, id_user = ? WHERE id_kendaraan = ?");
                $stmt->execute([$plat, $jenis, $warna, $pemilik, $idUser, $idKendaraan]);
                catatLog($db, $_SESSION['id_user'], "Mengubah data kendaraan #$idKendaraan ($plat)");
                $pesan = 'Data kendaraan berhasil diperbarui.';
            } else {
                $stmt = $db->prepare("INSERT INTO tb_kendaraan (plat_nomor, jenis_kendaraan, warna, pemilik, id_user) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$plat, $jenis, $warna, $pemilik, $idUser]);
                catatLog($db, $_SESSION['id_user'], "Menambahkan kendaraan baru: $plat ($jenis)");
                $pesan = 'Kendaraan baru berhasil ditambahkan.';
            }
        } catch (PDOException $e) {
            $pesan = (str_contains($e->getMessage(), 'Duplicate'))
                ? 'Plat nomor tersebut sudah terdaftar.'
                : 'Gagal menyimpan data kendaraan.';
            $pesanTipe = 'error';
        }
    }
}

/* ---------------- Hapus Kendaraan ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    $idKendaraan = (int)($_POST['id_kendaraan'] ?? 0);
    try {
        $db->prepare("DELETE FROM tb_kendaraan WHERE id_kendaraan = ?")->execute([$idKendaraan]);
        catatLog($db, $_SESSION['id_user'], "Menghapus kendaraan #$idKendaraan");
        $pesan = 'Kendaraan berhasil dihapus.';
    } catch (PDOException $e) {
        $pesan = 'Kendaraan ini tidak bisa dihapus karena masih memiliki data transaksi terkait.';
        $pesanTipe = 'error';
    }
}

/* ---------------- Data untuk form edit ---------------- */
$kendaraanDiedit = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM tb_kendaraan WHERE id_kendaraan = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $kendaraanDiedit = $stmt->fetch();
}

$daftarUser = $db->query("SELECT id_user, nama_lengkap FROM tb_user WHERE status_aktif = 1 ORDER BY nama_lengkap ASC")->fetchAll();

$daftarKendaraan = $db->query("
    SELECT k.*, u.nama_lengkap AS nama_akun
    FROM tb_kendaraan k
    LEFT JOIN tb_user u ON u.id_user = k.id_user
    ORDER BY k.plat_nomor ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Kendaraan — Dashboard Admin</title>
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
.badge { display:inline-block; padding:0.15rem 0.6rem; border-radius:99px; font-size:0.78rem; font-weight:600; background:#CFE3DE; color:#1B4451; }
form.inline { display:inline; }
.aksi-cell { display:flex; gap:0.4rem; flex-wrap:wrap; }
.kosong { color:var(--muted); font-size:0.9rem; }
</style>
</head>
<body>

<div class="admin-header">
    <div>
        <h1>Kelola Kendaraan</h1>
        <p class="subjudul">Data kendaraan yang terdaftar di sistem</p>
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
            <h2><?= $kendaraanDiedit ? 'Edit Kendaraan' : 'Tambah Kendaraan Baru' ?></h2>

            <?php if (empty($daftarUser)): ?>
                <p class="kosong">Belum ada akun user aktif. Tambahkan user terlebih dahulu di menu Kelola User sebelum menambahkan kendaraan.</p>
            <?php else: ?>
            <form method="POST">
                <input type="hidden" name="aksi" value="simpan">
                <input type="hidden" name="id_kendaraan" value="<?= $kendaraanDiedit ? (int)$kendaraanDiedit['id_kendaraan'] : 0 ?>">

                <label>Plat Nomor</label>
                <input type="text" name="plat_nomor" required value="<?= htmlspecialchars($kendaraanDiedit['plat_nomor'] ?? '') ?>">

                <label>Jenis Kendaraan</label>
                <select name="jenis_kendaraan" required>
                    <?php foreach ($pilihanJenis as $val => $lbl): ?>
                        <option value="<?= $val ?>" <?= ($kendaraanDiedit['jenis_kendaraan'] ?? '') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Warna</label>
                <input type="text" name="warna" value="<?= htmlspecialchars($kendaraanDiedit['warna'] ?? '') ?>">

                <label>Nama Pemilik</label>
                <input type="text" name="pemilik" required value="<?= htmlspecialchars($kendaraanDiedit['pemilik'] ?? '') ?>">

                <label>Akun Terhubung</label>
                <select name="id_user" required>
                    <option value="">-- Pilih akun user --</option>
                    <?php foreach ($daftarUser as $u): ?>
                        <option value="<?= (int)$u['id_user'] ?>" <?= (int)($kendaraanDiedit['id_user'] ?? 0) === (int)$u['id_user'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['nama_lengkap']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn"><?= $kendaraanDiedit ? 'Simpan Perubahan' : 'Tambah Kendaraan' ?></button>
            </form>
            <?php if ($kendaraanDiedit): ?>
                <a href="kendaraan.php" style="display:block; margin-top:0.75rem;"><button type="button" class="btn secondary">Batal Edit</button></a>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="panel">
            <h2>Daftar Kendaraan (<?= count($daftarKendaraan) ?>)</h2>
            <?php if (empty($daftarKendaraan)): ?>
                <p class="kosong">Belum ada kendaraan terdaftar.</p>
            <?php else: ?>
            <table>
                <thead>
                    <tr><th>Plat Nomor</th><th>Jenis</th><th>Warna</th><th>Pemilik</th><th>Akun</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                <?php foreach ($daftarKendaraan as $k): ?>
                    <tr>
                        <td><?= htmlspecialchars($k['plat_nomor']) ?></td>
                        <td><span class="badge"><?= htmlspecialchars($pilihanJenis[$k['jenis_kendaraan']] ?? $k['jenis_kendaraan']) ?></span></td>
                        <td><?= htmlspecialchars($k['warna'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($k['pemilik']) ?></td>
                        <td><?= htmlspecialchars($k['nama_akun'] ?? '-') ?></td>
                        <td class="aksi-cell">
                            <a href="kendaraan.php?edit=<?= (int)$k['id_kendaraan'] ?>"><button type="button" class="btn secondary kecil">Edit</button></a>
                            <form method="POST" class="inline" onsubmit="return confirm('Yakin hapus kendaraan ini? Tindakan tidak bisa dibatalkan.');">
                                <input type="hidden" name="aksi" value="hapus">
                                <input type="hidden" name="id_kendaraan" value="<?= (int)$k['id_kendaraan'] ?>">
                                <button type="submit" class="btn merah kecil">Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>