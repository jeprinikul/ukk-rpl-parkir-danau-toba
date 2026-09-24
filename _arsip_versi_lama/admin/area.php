<?php
/**
 * admin/area.php — CRUD Area Parkir (khusus role: admin)
 */
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');

$db = getDB();
$pesan = '';
$pesanTipe = 'ok'; // ok | error

/* ---------------- Tambah / Edit Area ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'simpan') {
    $idArea    = (int)($_POST['id_area'] ?? 0);
    $namaArea  = trim($_POST['nama_area'] ?? '');
    $kapasitas = (int)($_POST['kapasitas'] ?? 0);
    $terisi    = (int)($_POST['terisi'] ?? 0);

    if ($namaArea === '' || $kapasitas <= 0) {
        $pesan = 'Nama area dan kapasitas wajib diisi dengan benar.';
        $pesanTipe = 'error';
    } elseif ($terisi > $kapasitas) {
        $pesan = 'Jumlah terisi tidak boleh lebih besar dari kapasitas.';
        $pesanTipe = 'error';
    } else {
        try {
            if ($idArea > 0) {
                $stmt = $db->prepare("UPDATE tb_area_parkir SET nama_area = ?, kapasitas = ?, terisi = ? WHERE id_area = ?");
                $stmt->execute([$namaArea, $kapasitas, $terisi, $idArea]);
                catatLog($db, $_SESSION['id_user'], "Mengubah area parkir #$idArea ($namaArea)");
                $pesan = 'Area parkir berhasil diperbarui.';
            } else {
                $stmt = $db->prepare("INSERT INTO tb_area_parkir (nama_area, kapasitas, terisi) VALUES (?, ?, 0)");
                $stmt->execute([$namaArea, $kapasitas]);
                catatLog($db, $_SESSION['id_user'], "Menambahkan area parkir baru: $namaArea (kapasitas $kapasitas)");
                $pesan = 'Area parkir baru berhasil ditambahkan.';
            }
        } catch (PDOException $e) {
            $pesan = (str_contains($e->getMessage(), 'Duplicate'))
                ? 'Nama area tersebut sudah dipakai.'
                : 'Gagal menyimpan data area parkir.';
            $pesanTipe = 'error';
        }
    }
}

/* ---------------- Hapus Area ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    $idArea = (int)($_POST['id_area'] ?? 0);
    try {
        $db->prepare("DELETE FROM tb_area_parkir WHERE id_area = ?")->execute([$idArea]);
        catatLog($db, $_SESSION['id_user'], "Menghapus area parkir #$idArea");
        $pesan = 'Area parkir berhasil dihapus.';
    } catch (PDOException $e) {
        $pesan = 'Area ini tidak bisa dihapus karena masih memiliki data transaksi/kendaraan terkait.';
        $pesanTipe = 'error';
    }
}

/* ---------------- Data untuk form edit ---------------- */
$areaDiedit = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM tb_area_parkir WHERE id_area = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $areaDiedit = $stmt->fetch();
}

$daftarArea = $db->query("SELECT * FROM tb_area_parkir ORDER BY nama_area ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Area Parkir — Dashboard Admin</title>
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
.progress-bar { background:#EEE; border-radius:99px; height:8px; width:100px; overflow:hidden; display:inline-block; vertical-align:middle; margin-right:0.5rem; }
.progress-bar span { display:block; height:100%; background:var(--gold); }
form.inline { display:inline; }
.aksi-cell { display:flex; gap:0.4rem; flex-wrap:wrap; }
</style>
</head>
<body>

<div class="admin-header">
    <div>
        <h1>Kelola Area Parkir</h1>
        <p class="subjudul">Tambah area baru dan atur kapasitas tiap area</p>
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
            <h2><?= $areaDiedit ? 'Edit Area Parkir' : 'Tambah Area Baru' ?></h2>
            <form method="POST">
                <input type="hidden" name="aksi" value="simpan">
                <input type="hidden" name="id_area" value="<?= $areaDiedit ? (int)$areaDiedit['id_area'] : 0 ?>">

                <label>Nama Area</label>
                <input type="text" name="nama_area" required value="<?= htmlspecialchars($areaDiedit['nama_area'] ?? '') ?>">

                <label>Kapasitas</label>
                <input type="number" name="kapasitas" min="1" required value="<?= htmlspecialchars($areaDiedit['kapasitas'] ?? '') ?>">

                <?php if ($areaDiedit): ?>
                    <label>Jumlah Terisi Saat Ini</label>
                    <input type="number" name="terisi" min="0" value="<?= htmlspecialchars($areaDiedit['terisi'] ?? 0) ?>">
                    <p class="hint">Biasanya angka ini otomatis mengikuti transaksi. Ubah manual hanya untuk koreksi data.</p>
                <?php else: ?>
                    <input type="hidden" name="terisi" value="0">
                <?php endif; ?>

                <button type="submit" class="btn"><?= $areaDiedit ? 'Simpan Perubahan' : 'Tambah Area' ?></button>
            </form>
            <?php if ($areaDiedit): ?>
                <a href="area.php" style="display:block; margin-top:0.75rem;"><button type="button" class="btn secondary">Batal Edit</button></a>
            <?php endif; ?>
        </div>

        <div class="panel">
            <h2>Daftar Area Parkir (<?= count($daftarArea) ?>)</h2>
            <table>
                <thead>
                    <tr><th>Nama Area</th><th>Kapasitas</th><th>Okupansi</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                <?php foreach ($daftarArea as $a):
                    $persen = $a['kapasitas'] > 0 ? round(($a['terisi'] / $a['kapasitas']) * 100) : 0;
                ?>
                    <tr>
                        <td><?= htmlspecialchars($a['nama_area']) ?></td>
                        <td><?= (int)$a['kapasitas'] ?></td>
                        <td>
                            <span class="progress-bar"><span style="width:<?= $persen ?>%;"></span></span>
                            <?= (int)$a['terisi'] ?>/<?= (int)$a['kapasitas'] ?> (<?= $persen ?>%)
                        </td>
                        <td class="aksi-cell">
                            <a href="area.php?edit=<?= (int)$a['id_area'] ?>"><button type="button" class="btn secondary kecil">Edit</button></a>
                            <form method="POST" class="inline" onsubmit="return confirm('Yakin hapus area ini? Tindakan tidak bisa dibatalkan.');">
                                <input type="hidden" name="aksi" value="hapus">
                                <input type="hidden" name="id_area" value="<?= (int)$a['id_area'] ?>">
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