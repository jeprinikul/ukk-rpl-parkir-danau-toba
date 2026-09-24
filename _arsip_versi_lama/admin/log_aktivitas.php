<?php
/**
 * admin/log_aktivitas.php — Lihat semua log aktivitas (khusus role: admin)
 */
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');

$db = getDB();

/* ---------------- Filter ---------------- */
$kataKunci  = trim($_GET['q'] ?? '');
$filterRole = $_GET['role'] ?? '';
$tanggal    = $_GET['tanggal'] ?? '';

$where = [];
$params = [];

if ($kataKunci !== '') {
    $where[] = "(l.aktivitas LIKE ? OR u.nama_lengkap LIKE ?)";
    $params[] = "%$kataKunci%";
    $params[] = "%$kataKunci%";
}
if (in_array($filterRole, ['admin', 'petugas', 'owner'], true)) {
    $where[] = "u.role = ?";
    $params[] = $filterRole;
}
if ($tanggal !== '') {
    $where[] = "DATE(l.waktu_aktivitas) = ?";
    $params[] = $tanggal;
}

$sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* ---------------- Pagination sederhana ---------------- */
$halaman = max(1, (int)($_GET['halaman'] ?? 1));
$perHalaman = 30;
$offset = ($halaman - 1) * $perHalaman;

$stmtTotal = $db->prepare("
    SELECT COUNT(*) FROM tb_log_aktivitas l
    JOIN tb_user u ON u.id_user = l.id_user
    $sqlWhere
");
$stmtTotal->execute($params);
$totalBaris = (int)$stmtTotal->fetchColumn();
$totalHalaman = max(1, (int)ceil($totalBaris / $perHalaman));

$sql = "
    SELECT l.id_log, l.waktu_aktivitas, l.aktivitas, u.nama_lengkap, u.role
    FROM tb_log_aktivitas l
    JOIN tb_user u ON u.id_user = l.id_user
    $sqlWhere
    ORDER BY l.waktu_aktivitas DESC
    LIMIT $perHalaman OFFSET $offset
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$daftarLog = $stmt->fetchAll();

function tautanFilter(array $override): string {
    $params = array_merge($_GET, $override);
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log Aktivitas — Dashboard Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Fraunces:wght@500;600&display=swap" rel="stylesheet">
<style>
:root {
  --lake-deep:#0F2C33; --lake-mid:#1B4451; --gold:#D9A441; --gold-dim:#B78530;
  --ulos-red:#8C3B3B; --paper:#F5F2EA; --ink:#16262A; --muted:#6C7C7C; --line:rgba(15,44,51,0.12);
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

.panel { background:#fff; border:1px solid var(--line); border-radius:10px; padding:1.5rem; margin-bottom:1.5rem; }
.panel h2 { font-family:var(--font-display); font-size:1.1rem; color:var(--lake-deep); margin:0 0 1rem; font-weight:600; }

.form-filter { display:flex; gap:0.75rem; flex-wrap:wrap; margin-bottom:1.5rem; }
.form-filter input, .form-filter select {
  padding:0.6rem 0.8rem; border:1px solid var(--line); border-radius:8px;
  font-family:var(--font-body); font-size:0.88rem;
}
.form-filter input[type="text"] { flex:1; min-width:200px; }
.btn { background: var(--gold); color: var(--lake-deep); border:none; padding:0.6rem 1.1rem; border-radius:8px; font-weight:700; cursor:pointer; font-size:0.88rem; }
.btn:hover { background: var(--gold-dim); }
.btn.secondary { background:#fff; color:var(--lake-mid); border:1px solid var(--line); text-decoration:none; display:inline-flex; align-items:center; }

table { width:100%; border-collapse:collapse; font-size:0.88rem; }
th, td { text-align:left; padding:0.6rem 0.5rem; border-bottom:1px solid var(--line); vertical-align:middle; }
th { color:var(--muted); font-weight:600; font-size:0.78rem; text-transform:uppercase; }
.badge { display:inline-block; padding:0.15rem 0.6rem; border-radius:99px; font-size:0.78rem; font-weight:600; }
.badge.admin { background:#E4D2B4; color:#8C5A16; }
.badge.petugas { background:#CFE3DE; color:#1B4451; }
.badge.owner { background:#E6D3D3; color:#8C3B3B; }

.paginasi { display:flex; gap:0.4rem; justify-content:center; margin-top:1.5rem; flex-wrap:wrap; }
.paginasi a, .paginasi span {
  padding:0.4rem 0.75rem; border-radius:6px; font-size:0.85rem; text-decoration:none; color:var(--lake-mid); border:1px solid var(--line);
}
.paginasi .aktif { background:var(--lake-deep); color:#fff; border-color:var(--lake-deep); }
.kosong { color:var(--muted); font-size:0.9rem; text-align:center; padding:2rem 0; }
</style>
</head>
<body>

<div class="admin-header">
    <div>
        <h1>Log Aktivitas</h1>
        <p class="subjudul">Riwayat semua aktivitas pengguna dalam sistem</p>
    </div>
    <div class="kanan">
        <span class="siapa">👤 <?= htmlspecialchars($_SESSION['nama_lengkap']) ?> (Admin)</span>
        <a href="../logout.php">Keluar</a>
    </div>
</div>

<div class="wrap">
    <p class="breadcrumb"><a href="dashboard.php">← Kembali ke Dashboard</a></p>

    <div class="panel">
        <form method="GET" class="form-filter">
            <input type="text" name="q" placeholder="Cari nama atau aktivitas..." value="<?= htmlspecialchars($kataKunci) ?>">
            <select name="role">
                <option value="">Semua role</option>
                <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="petugas" <?= $filterRole === 'petugas' ? 'selected' : '' ?>>Petugas</option>
                <option value="owner" <?= $filterRole === 'owner' ? 'selected' : '' ?>>Owner</option>
            </select>
            <input type="date" name="tanggal" value="<?= htmlspecialchars($tanggal) ?>">
            <button type="submit" class="btn">Terapkan Filter</button>
            <?php if ($kataKunci !== '' || $filterRole !== '' || $tanggal !== ''): ?>
                <a href="log_aktivitas.php" class="btn secondary">Reset</a>
            <?php endif; ?>
        </form>

        <h2>Riwayat Aktivitas (<?= $totalBaris ?> total)</h2>

        <?php if (empty($daftarLog)): ?>
            <p class="kosong">Tidak ada aktivitas yang cocok dengan filter ini.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>Waktu</th><th>Nama</th><th>Role</th><th>Aktivitas</th></tr>
            </thead>
            <tbody>
                <?php foreach ($daftarLog as $log): ?>
                <tr>
                    <td><?= date('d M Y, H:i', strtotime($log['waktu_aktivitas'])) ?></td>
                    <td><?= htmlspecialchars($log['nama_lengkap']) ?></td>
                    <td><span class="badge <?= htmlspecialchars($log['role']) ?>"><?= htmlspecialchars($log['role']) ?></span></td>
                    <td><?= htmlspecialchars($log['aktivitas']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalHalaman > 1): ?>
        <div class="paginasi">
            <?php for ($p = 1; $p <= $totalHalaman; $p++): ?>
                <?php if ($p === $halaman): ?>
                    <span class="aktif"><?= $p ?></span>
                <?php else: ?>
                    <a href="<?= tautanFilter(['halaman' => $p]) ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

</body>
</html>