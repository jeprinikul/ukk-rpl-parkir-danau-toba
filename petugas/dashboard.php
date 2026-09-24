<?php
session_start();
require_once "../config/database.php";
$pdo = getDB();

// --- Proteksi login petugas ---
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'petugas') {
    header("Location: login.php");
    exit;
}
$id_user_login = $_SESSION['id_user'];
$lokasiPetugas = (int)($_SESSION['lokasi_id'] ?? 0);

$pesan = $_SESSION['pesan'] ?? null;
unset($_SESSION['pesan']);

// --- Petugas wajib sudah ditugaskan ke satu lokasi sebelum bisa mencatat parkir manual ---
if (!$lokasiPetugas) {
    $namaLokasiPetugas = null;
    $areaList = [];
    $tarifList = [];
    $aktifList = [];
    $totalMasukHariIni = $totalSelesaiHariIni = 0;
    $pendapatanHariIni = 0;
} else {
    // --- Nama lokasi yang ditugaskan, untuk ditampilkan di header ---
    $stmtLokasi = $pdo->prepare("SELECT nama_lokasi FROM lokasi_parkir WHERE id = ?");
    $stmtLokasi->execute([$lokasiPetugas]);
    $namaLokasiPetugas = $stmtLokasi->fetchColumn();

    // --- Ambil daftar area parkir di lokasi tugas petugas yang masih ada slot kosong ---
    $stmtArea = $pdo->prepare("SELECT id_area, nama_area, kapasitas, terisi
                              FROM tb_area_parkir
                              WHERE lokasi_id = ? AND terisi < kapasitas");
    $stmtArea->execute([$lokasiPetugas]);
    $areaList = $stmtArea->fetchAll(PDO::FETCH_ASSOC);

    // --- Ambil daftar tarif aktif (untuk dropdown jenis kendaraan) ---
    $stmtTarif = $pdo->query("SELECT id_tarif, jenis_kendaraan, tarif_flat FROM tb_tarif");
    $tarifList = $stmtTarif->fetchAll(PDO::FETCH_ASSOC);

    // --- Ambil daftar transaksi yang MASIH PARKIR (status = 'masuk') di lokasi tugas petugas ---
    $sqlAktif = "SELECT t.id_parkir, k.plat_nomor, k.jenis_kendaraan, k.pemilik,
                        t.waktu_masuk, a.nama_area
                 FROM tb_transaksi t
                 JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
                 JOIN tb_area_parkir a ON t.id_area = a.id_area
                 WHERE t.status = 'masuk' AND a.lokasi_id = ?
                 ORDER BY t.waktu_masuk DESC";
    $stmtAktif = $pdo->prepare($sqlAktif);
    $stmtAktif->execute([$lokasiPetugas]);
    $aktifList = $stmtAktif->fetchAll(PDO::FETCH_ASSOC);

    // --- Statistik ringkas hari ini, khusus lokasi tugas petugas ---
    $today = date("Y-m-d");

    $stmtStat1 = $pdo->prepare("SELECT COUNT(*) FROM tb_transaksi t
        JOIN tb_area_parkir a ON t.id_area = a.id_area
        WHERE a.lokasi_id = ? AND DATE(t.waktu_masuk) = ?");
    $stmtStat1->execute([$lokasiPetugas, $today]);
    $totalMasukHariIni = $stmtStat1->fetchColumn();

    $stmtStat2 = $pdo->prepare("SELECT COUNT(*) FROM tb_transaksi t
        JOIN tb_area_parkir a ON t.id_area = a.id_area
        WHERE a.lokasi_id = ? AND t.status='keluar' AND DATE(t.waktu_keluar) = ?");
    $stmtStat2->execute([$lokasiPetugas, $today]);
    $totalSelesaiHariIni = $stmtStat2->fetchColumn();

    $stmtStat3 = $pdo->prepare("SELECT COALESCE(SUM(t.biaya_total),0) FROM tb_transaksi t
        JOIN tb_area_parkir a ON t.id_area = a.id_area
        WHERE a.lokasi_id = ? AND t.status='keluar' AND DATE(t.waktu_keluar) = ?");
    $stmtStat3->execute([$lokasiPetugas, $today]);
    $pendapatanHariIni = $stmtStat3->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Petugas — Parkir Danau Toba</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Fraunces:wght@500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --lake-deep:#0E2A31; --lake-mid:#153B44; --lake-line:#1E4C56;
  --gold:#D9A441; --gold-soft:#F3E3BE; --gold-dim:#B78530;
  --ulos-red:#8C3B3B;
  --paper:#F7F4EC; --card:#FFFFFF; --ink:#16262A; --muted:#71807F; --line:rgba(15,44,51,0.10);
  --green:#2E7D4F; --red:#B33636;
  --font-display:"Fraunces",Georgia,serif; --font-body:"Inter",-apple-system,BlinkMacSystemFont,sans-serif;
  --radius:14px;
}
* { box-sizing:border-box; }
html, body { margin:0; padding:0; }
body {
  font-family:var(--font-body); background:var(--paper); color:var(--ink);
  display:flex; min-height:100vh;
}

/* ---------- Sidebar ---------- */
.sidebar {
  width:248px; flex-shrink:0; background:var(--lake-deep); color:#EFE9DC;
  display:flex; flex-direction:column; position:sticky; top:0; height:100vh;
}
.sidebar .brand {
  display:flex; align-items:center; gap:0.7rem; padding:1.6rem 1.5rem 1.3rem;
  border-bottom:1px solid var(--lake-line);
}
.sidebar .brand svg { flex-shrink:0; }
.sidebar .brand .teks { line-height:1.15; }
.sidebar .brand .teks strong { font-family:var(--font-display); font-size:1.05rem; font-weight:600; display:block; color:#fff; }
.sidebar .brand .teks span { font-size:0.72rem; color:#9CB2AE; letter-spacing:0.02em; }

.sidebar nav { padding:1.25rem 0.9rem; display:flex; flex-direction:column; gap:0.2rem; flex:1; }
.sidebar nav a {
  display:flex; align-items:center; gap:0.75rem; padding:0.7rem 0.85rem; border-radius:9px;
  color:#C7D6D3; text-decoration:none; font-size:0.91rem; font-weight:500;
  border-left:3px solid transparent; transition:background 0.15s ease;
}
.sidebar nav a svg { flex-shrink:0; opacity:0.85; }
.sidebar nav a:hover { background:var(--lake-mid); color:#fff; }
.sidebar nav a.aktif { background:var(--lake-mid); color:#fff; border-left-color:var(--gold); }
.sidebar nav a.aktif svg { opacity:1; }

.sidebar .kaki { padding:1.1rem 1.5rem 1.4rem; border-top:1px solid var(--lake-line); }
.sidebar .siapa { display:flex; align-items:center; gap:0.6rem; margin-bottom:0.9rem; }
.sidebar .avatar {
  width:34px; height:34px; border-radius:50%; background:var(--gold); color:var(--lake-deep);
  display:flex; align-items:center; justify-content:center; font-weight:700; font-family:var(--font-display); flex-shrink:0;
}
.sidebar .siapa .info strong { display:block; font-size:0.85rem; color:#fff; }
.sidebar .siapa .info span { font-size:0.72rem; color:#9CB2AE; }
.sidebar .kaki a {
  display:flex; align-items:center; gap:0.5rem; color:#C7D6D3; text-decoration:none; font-size:0.85rem; font-weight:600;
}
.sidebar .kaki a:hover { color:var(--gold); }

/* ---------- Main ---------- */
.main { flex:1; min-width:0; }
.topbar {
  background:var(--card); border-bottom:1px solid var(--line);
  padding:1.4rem 2.2rem; display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:0.8rem;
}
.topbar h1 { font-family:var(--font-display); font-size:1.55rem; margin:0; font-weight:600; color:var(--lake-deep); }
.topbar p { margin:0.25rem 0 0; color:var(--muted); font-size:0.9rem; }

.wrap { max-width:1320px; padding:2.2rem; margin:0 auto; }

/* ---------- Kartu ringkasan ---------- */
.kartu-ringkasan { display:grid; grid-template-columns:repeat(4, 1fr); gap:1.1rem; margin-bottom:1.75rem; }
.kartu {
  background:var(--card); border:1px solid var(--line); border-radius:var(--radius); padding:1.35rem 1.5rem;
  box-shadow:0 1px 2px rgba(15,44,51,0.04);
}
.kartu .label { font-size:0.8rem; color:var(--muted); margin-bottom:0.5rem; font-weight:500; }
.kartu .nilai { font-family:var(--font-display); font-size:1.65rem; color:var(--lake-deep); font-weight:600; }

/* ---------- Panel ---------- */
.panel {
  background:var(--card); border:1px solid var(--line); border-radius:var(--radius); padding:1.5rem;
  box-shadow:0 1px 2px rgba(15,44,51,0.04); margin-bottom:1.75rem;
}
.panel h2 { font-family:var(--font-display); font-size:1.05rem; color:var(--lake-deep); margin:0 0 1.1rem; font-weight:600; }

/* ---------- Form ---------- */
.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem 1.4rem; }
.form-grid .full { grid-column:1 / -1; }
label { display:block; font-size:0.8rem; font-weight:600; color:var(--muted); margin-bottom:0.4rem; }
input[type="text"], select {
  width:100%; padding:0.65rem 0.8rem; border:1px solid var(--line); border-radius:8px;
  font-family:var(--font-body); font-size:0.9rem; background:var(--paper); color:var(--ink);
}
button.btn-primary {
  margin-top:1.3rem; border:none; padding:0.7rem 1.4rem; border-radius:9px;
  background:var(--gold); color:var(--lake-deep); font-weight:700; font-size:0.9rem; cursor:pointer;
}
button.btn-primary:hover { background:var(--gold-dim); }

/* ---------- Tabel ---------- */
table { width:100%; border-collapse:collapse; font-size:0.87rem; }
th, td { text-align:left; padding:0.7rem 0.6rem; border-bottom:1px solid var(--line); }
th { color:var(--muted); font-weight:600; font-size:0.74rem; text-transform:uppercase; letter-spacing:0.03em; }
tbody tr:hover { background:var(--paper); }

.btn-proses {
  border:none; padding:0.45rem 0.9rem; border-radius:7px; font-size:0.82rem; font-weight:600;
  background:var(--ulos-red); color:#fff; cursor:pointer;
}
.btn-proses:hover { background:#722c2c; }

.pesan { padding:0.85rem 1.1rem; border-radius:10px; font-size:0.88rem; margin-bottom:1.25rem; }
.pesan.sukses { background:#D6E8D6; color:var(--green); }
.pesan.gagal { background:#F0D3D3; color:var(--red); }

@media (max-width:820px) {
  body { flex-direction:column; }
  .sidebar { width:100%; height:auto; position:relative; flex-direction:row; align-items:center; padding:0; }
  .sidebar .brand { border:none; padding:1rem 1.25rem; }
  .sidebar nav { flex-direction:row; overflow-x:auto; padding:0.5rem; }
  .sidebar .kaki { display:none; }
  .wrap { padding:1.25rem; }
  .kartu-ringkasan { grid-template-columns:1fr 1fr; }
  .form-grid { grid-template-columns:1fr; }
}
</style>
</head>
<body>

<aside class="sidebar">
    <div class="brand">
        <svg width="34" height="34" viewBox="0 0 34 34" fill="none">
            <circle cx="17" cy="17" r="17" fill="#D9A441"/>
            <path d="M6 20c2.5-2 5-2 7.5 0s5 2 7.5 0 5-2 7.5 0" stroke="#0E2A31" stroke-width="1.8" stroke-linecap="round" fill="none"/>
            <path d="M6 24c2.5-2 5-2 7.5 0s5 2 7.5 0 5-2 7.5 0" stroke="#0E2A31" stroke-width="1.8" stroke-linecap="round" fill="none" opacity="0.5"/>
            <path d="M17 6l3.5 8h-7z" fill="#0E2A31"/>
        </svg>
        <div class="teks">
            <strong>Danau Toba</strong>
            <span>Sistem Parkir</span>
        </div>
    </div>
    <nav>
        <a href="dashboard.php" class="aktif">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
            Dashboard
        </a>
    </nav>
    <div class="kaki">
        <div class="siapa">
            <div class="avatar"><?= strtoupper(substr($_SESSION['nama_lengkap'] ?? 'P', 0, 1)) ?></div>
            <div class="info">
                <strong><?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Petugas') ?></strong>
                <span>Petugas</span>
            </div>
        </div>
        <a href="../logout.php">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/></svg>
            Keluar
        </a>
    </div>
</aside>

<div class="main">
    <div class="topbar">
        <div>
            <h1>Dashboard Petugas</h1>
            <p>
                Catat kendaraan masuk, proses kendaraan keluar, dan cetak struk parkir manual (walk-in).
                <?php if ($namaLokasiPetugas): ?>
                    &mdash; Lokasi tugas: <strong><?= htmlspecialchars($namaLokasiPetugas) ?></strong>
                <?php endif; ?>
            </p>
        </div>
        <div>
            <a href="../admin/riwayat_manual.php" class="btn-primary" style="text-decoration:none;display:inline-block;margin-right:0.6rem;">Riwayat Parkir Manual</a>
            <a href="../admin/kelola_booking.php" class="btn-primary" style="text-decoration:none;display:inline-block;">Lihat Booking Online</a>
        </div>
    </div>

    <div class="wrap">

        <?php if ($pesan): ?>
            <div class="pesan <?= $pesan['tipe'] === 'sukses' ? 'sukses' : 'gagal' ?>"><?= htmlspecialchars($pesan['teks']) ?></div>
        <?php endif; ?>

        <?php if (!$lokasiPetugas): ?>
            <div class="pesan gagal">
                Akun Anda belum ditugaskan ke lokasi parkir manapun, sehingga fitur parkir manual belum bisa dipakai.
                Hubungi Owner/Admin untuk ditugaskan ke salah satu lokasi (menu Kelola Akun Staf).
            </div>
        <?php else: ?>

        <div class="kartu-ringkasan">
            <div class="kartu">
                <div class="label">Kendaraan masuk hari ini</div>
                <div class="nilai"><?= $totalMasukHariIni ?></div>
            </div>
            <div class="kartu">
                <div class="label">Sedang parkir sekarang</div>
                <div class="nilai"><?= count($aktifList) ?></div>
            </div>
            <div class="kartu">
                <div class="label">Transaksi selesai hari ini</div>
                <div class="nilai"><?= $totalSelesaiHariIni ?></div>
            </div>
            <div class="kartu">
                <div class="label">Pendapatan tercatat hari ini</div>
                <div class="nilai">Rp<?= number_format($pendapatanHariIni,0,',','.') ?></div>
            </div>
        </div>

        <!-- FORM CATAT KENDARAAN MASUK -->
        <div class="panel">
            <h2>Catat Kendaraan Masuk</h2>
            <form action="proses_masuk.php" method="POST">
                <div class="form-grid">
                    <div class="full">
                        <label>Plat Nomor</label>
                        <input type="text" name="plat_nomor" required placeholder="Contoh: BK 1234 AB">
                    </div>
                    <div>
                        <label>Jenis Kendaraan</label>
                        <select name="id_tarif" required>
                            <option value="">-- Pilih Jenis Kendaraan --</option>
                            <?php foreach ($tarifList as $t): ?>
                                <option value="<?= $t['id_tarif'] ?>">
                                    <?= htmlspecialchars($t['jenis_kendaraan']) ?> (Rp<?= number_format($t['tarif_flat'],0,',','.') ?> sekali bayar)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Area Parkir</label>
                        <select name="id_area" required>
                            <option value="">-- Pilih Area --</option>
                            <?php foreach ($areaList as $a): ?>
                                <option value="<?= $a['id_area'] ?>">
                                    <?= htmlspecialchars($a['nama_area']) ?> (Terisi <?= $a['terisi'] ?>/<?= $a['kapasitas'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Warna Kendaraan</label>
                        <input type="text" name="warna" placeholder="Opsional">
                    </div>
                    <div>
                        <label>Nama Pemilik</label>
                        <input type="text" name="pemilik" placeholder="Opsional">
                    </div>
                </div>
                <button type="submit" class="btn-primary">Simpan Kendaraan Masuk</button>
            </form>
        </div>

        <!-- SCAN BARCODE UNTUK VERIFIKASI KENDARAAN KELUAR -->
        <div class="panel">
            <h2>Scan Karcis (Kendaraan Keluar)</h2>
            <form action="konfirmasi_keluar.php" method="GET" id="form-scan" style="display:flex; gap:0.7rem; align-items:flex-end;">
                <div style="flex:1;">
                    <label>Arahkan alat scan barcode ke kolom ini, lalu tekan Enter</label>
                    <input type="text" name="id" id="input-scan" autocomplete="off" placeholder="Scan barcode struk masuk di sini...">
                </div>
                <button type="submit" class="btn-primary" style="margin-top:0;">Verifikasi &amp; Proses Keluar</button>
            </form>
        </div>

        <!-- DAFTAR KENDARAAN SEDANG PARKIR -->
        <div class="panel">
            <h2>Kendaraan Sedang Parkir</h2>
            <table>
                <thead>
                <tr>
                    <th>Plat Nomor</th>
                    <th>Jenis</th>
                    <th>Area</th>
                    <th>Waktu Masuk</th>
                    <th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php if (count($aktifList) === 0): ?>
                    <tr><td colspan="5">Tidak ada kendaraan yang sedang parkir saat ini.</td></tr>
                <?php else: foreach ($aktifList as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['plat_nomor']) ?></td>
                        <td><?= htmlspecialchars($row['jenis_kendaraan']) ?></td>
                        <td><?= htmlspecialchars($row['nama_area']) ?></td>
                        <td><?= date("d/m/Y H:i", strtotime($row['waktu_masuk'])) ?></td>
                        <td>
                            <a href="konfirmasi_keluar.php?id=<?= $row['id_parkir'] ?>" class="btn-proses" style="text-decoration:none;display:inline-block;">Proses Keluar</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php endif; ?>

    </div>
</div>


<script>
// Fokuskan kursor otomatis ke kolom scan barcode agar petugas tinggal scan tanpa klik dulu
var inputScan = document.getElementById('input-scan');
if (inputScan) { inputScan.focus(); }
</script>
</body>
</html>