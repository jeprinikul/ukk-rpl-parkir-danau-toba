<?php
/**
 * admin/dashboard.php — khusus role: admin
 * Statistik ringkas + menu ke Kelola Lokasi, Kelola Booking, Kelola Akun Staf.
 * Desain: sidebar layout senada dengan dashboard Owner, aksen warna dibedakan
 * (merah ulos sebagai warna utama Admin, emas tetap jadi aksen sorotan).
 */
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireStaff();
// Halaman ini didesain untuk admin; petugas & owner punya dashboard sendiri,
// tapi tetap diizinkan mampir ke sini untuk lihat ringkasan operasional.
// Menu CRUD di bawah hanya ditampilkan ke role yang benar-benar berhak membukanya.

$db = getDB();

/* ---------------- Suara notifikasi ---------------- */
// Prioritas: suara login (diset login.php) > suara booking baru.
// Dashboard hanya memutar suara yang memang untuknya.
$suaraValid   = ['login', 'booking'];
$maxBookingId = (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM booking")->fetchColumn();
$suara = null;

if (!empty($_SESSION['notif_sound'])) {
    $suara = $_SESSION['notif_sound'];
    unset($_SESSION['notif_sound']); // sekali pakai
} elseif (isset($_SESSION['last_booking_id']) && $maxBookingId > (int)$_SESSION['last_booking_id']) {
    $suara = 'booking';
}
if (!in_array($suara, $suaraValid, true)) $suara = null;

$_SESSION['last_booking_id'] = $maxBookingId; // baseline untuk kunjungan berikutnya

/* ---------------- Ringkasan ---------------- */
$totalUser      = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$totalLokasi    = (int)$db->query("SELECT COUNT(*) FROM lokasi_parkir WHERE status = 'aktif'")->fetchColumn();
$totalKapasitas = (int)$db->query("SELECT COALESCE(SUM(kapasitas),0) FROM lokasi_parkir")->fetchColumn();
$totalTersedia  = (int)$db->query("SELECT COALESCE(SUM(slot_tersedia),0) FROM lokasi_parkir")->fetchColumn();
$totalTerisi    = max(0, $totalKapasitas - $totalTersedia);
$persenOkupansi = $totalKapasitas > 0 ? round(($totalTerisi / $totalKapasitas) * 100, 1) : 0;
$bookingPending = (int)$db->query("SELECT COUNT(*) FROM booking WHERE status = 'pending'")->fetchColumn();

/* ---------------- Grafik: okupansi per lokasi ---------------- */
$labelLokasi = [];
$dataOkupansiLokasi = [];
$stmt = $db->query("SELECT nama_lokasi, kapasitas, slot_tersedia FROM lokasi_parkir ORDER BY kapasitas DESC");
foreach ($stmt->fetchAll() as $row) {
    $labelLokasi[] = $row['nama_lokasi'];
    $kap = (int)$row['kapasitas'];
    $terisi = max(0, $kap - (int)$row['slot_tersedia']);
    $dataOkupansiLokasi[] = $kap > 0 ? round(($terisi / $kap) * 100, 1) : 0;
}

/* ---------------- Booking terbaru (10 terakhir) ---------------- */
$bookingTerbaru = $db->query("
    SELECT b.kode_booking, b.status, b.created_at, b.total_harga, l.nama_lokasi, u.name AS nama_user
    FROM booking b
    JOIN lokasi_parkir l ON l.id = b.lokasi_id
    JOIN users u ON u.id = b.user_id
    ORDER BY b.created_at DESC
    LIMIT 10
")->fetchAll();

function badgeKelas($status) {
    $map = ['pending' => 'pending', 'dibayar' => 'dibayar', 'selesai' => 'selesai', 'dibatalkan' => 'dibatalkan'];
    return $map[$status] ?? 'pending';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Admin — Parkir Danau Toba</title>
<link rel="icon" href="data:,">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Fraunces:wght@500;600;700&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<style>
:root {
  --lake-deep:#0E2A31; --ulos-deep:#4A1F1F; --ulos-mid:#5E2828;
  --gold:#D9A441; --gold-soft:#F3E3BE; --gold-dim:#B78530;
  --ulos-red:#8C3B3B;
  --paper:#F7F4EC; --card:#FFFFFF; --ink:#16262A; --muted:#71807F; --line:rgba(15,44,51,0.10);
  --green:#2E7D4F; --amber:#8C5A16; --red:#B33636;
  --font-display:"Fraunces",Georgia,serif; --font-body:"Inter",-apple-system,BlinkMacSystemFont,sans-serif;
  --radius:14px;
}
* { box-sizing:border-box; }
html, body { margin:0; padding:0; }
body { font-family:var(--font-body); background:var(--paper); color:var(--ink); display:flex; min-height:100vh; }

/* ---------- Sidebar ---------- */
.sidebar {
  width:248px; flex-shrink:0; background:var(--ulos-deep); color:#EFE4E0;
  display:flex; flex-direction:column; position:sticky; top:0; height:100vh;
}
.sidebar .brand { display:flex; align-items:center; gap:0.7rem; padding:1.6rem 1.5rem 1.3rem; border-bottom:1px solid #603030; }
.sidebar .brand .teks { line-height:1.15; }
.sidebar .brand .teks strong { font-family:var(--font-display); font-size:1.05rem; font-weight:600; display:block; color:#fff; }
.sidebar .brand .teks span { font-size:0.72rem; color:#CDA8A2; letter-spacing:0.02em; }

.sidebar nav { padding:1.25rem 0.9rem; display:flex; flex-direction:column; gap:0.2rem; flex:1; }
.sidebar nav a {
  display:flex; align-items:center; gap:0.75rem; padding:0.7rem 0.85rem; border-radius:9px;
  color:#DCC3BF; text-decoration:none; font-size:0.91rem; font-weight:500;
  border-left:3px solid transparent; transition:background 0.15s ease;
}
.sidebar nav a svg { flex-shrink:0; opacity:0.85; }
.sidebar nav a:hover { background:var(--ulos-mid); color:#fff; }
.sidebar nav a.aktif { background:var(--ulos-mid); color:#fff; border-left-color:var(--gold); }
.sidebar nav a.aktif svg { opacity:1; }

.sidebar .kaki { padding:1.1rem 1.5rem 1.4rem; border-top:1px solid #603030; }
.sidebar .siapa { display:flex; align-items:center; gap:0.6rem; margin-bottom:0.9rem; }
.sidebar .avatar {
  width:34px; height:34px; border-radius:50%; background:var(--gold); color:var(--ulos-deep);
  display:flex; align-items:center; justify-content:center; font-weight:700; font-family:var(--font-display); flex-shrink:0;
}
.sidebar .siapa .info strong { display:block; font-size:0.85rem; color:#fff; }
.sidebar .siapa .info span { font-size:0.72rem; color:#CDA8A2; }
.sidebar .kaki a { display:flex; align-items:center; gap:0.5rem; color:#DCC3BF; text-decoration:none; font-size:0.85rem; font-weight:600; }
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
.kartu { background:var(--card); border:1px solid var(--line); border-radius:var(--radius); padding:1.35rem 1.5rem; box-shadow:0 1px 2px rgba(15,44,51,0.04); }
.kartu.sorot { background:linear-gradient(135deg, var(--ulos-deep), #6B3232); color:#fff; border:none; position:relative; overflow:hidden; }
.kartu.sorot::after { content:''; position:absolute; right:-30px; bottom:-40px; width:140px; height:140px; border-radius:50%; background:rgba(217,164,65,0.14); }
.kartu .label { font-size:0.8rem; color:var(--muted); margin-bottom:0.5rem; font-weight:500; }
.kartu.sorot .label { color:#E3BEB8; }
.kartu .nilai { font-family:var(--font-display); font-size:1.65rem; color:var(--lake-deep); font-weight:600; }
.kartu.sorot .nilai { color:#fff; }
.kartu .nilai .satuan { font-size:1rem; color:var(--muted); font-family:var(--font-body); }
.kartu.sorot .nilai .satuan { color:#E3BEB8; }

/* ---------- Menu CRUD ---------- */
.menu-crud { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:1.1rem; margin-bottom:1.75rem; }
.menu-item {
  background:var(--card); border:1px solid var(--line); border-radius:var(--radius); padding:1.5rem;
  text-decoration:none; display:flex; flex-direction:column; gap:0.55rem; transition:border-color .15s ease, transform .15s ease;
  box-shadow:0 1px 2px rgba(15,44,51,0.04);
}
.menu-item:hover { border-color:var(--gold); transform:translateY(-2px); }
.menu-item .ikon {
  width:38px; height:38px; border-radius:10px; background:var(--gold-soft); color:var(--gold-dim);
  display:flex; align-items:center; justify-content:center;
}
.menu-item h3 { font-family:var(--font-display); font-size:1.05rem; color:var(--lake-deep); margin:0; font-weight:600; }
.menu-item p { font-size:0.85rem; color:var(--muted); margin:0; line-height:1.45; }

/* ---------- Panel ---------- */
.panel { background:var(--card); border:1px solid var(--line); border-radius:var(--radius); padding:1.5rem; margin-bottom:1.5rem; box-shadow:0 1px 2px rgba(15,44,51,0.04); }
.panel h2 { font-family:var(--font-display); font-size:1.05rem; color:var(--lake-deep); margin:0 0 1.1rem; font-weight:600; }
canvas { max-height:320px; }

table { width:100%; border-collapse:collapse; font-size:0.88rem; }
th, td { text-align:left; padding:0.6rem 0.5rem; border-bottom:1px solid var(--line); }
th { color:var(--muted); font-weight:600; font-size:0.74rem; text-transform:uppercase; letter-spacing:0.03em; }
tbody tr:hover { background:var(--paper); }

.badge { display:inline-block; padding:0.2rem 0.65rem; border-radius:99px; font-size:0.76rem; font-weight:600; }
.badge-pending { background:#F1DDAE; color:var(--amber); }
.badge-dibayar { background:#CFE3DE; color:#1B4451; }
.badge-selesai { background:#D6E8D6; color:var(--green); }
.badge-dibatalkan { background:#F0D3D3; color:var(--red); }

.tautan-lanjut { color:var(--ulos-red); font-weight:600; font-size:0.88rem; text-decoration:none; }
.tautan-lanjut:hover { text-decoration:underline; }

@media (max-width:1080px) { .kartu-ringkasan { grid-template-columns:repeat(2, 1fr); } }
@media (max-width:820px) {
  body { flex-direction:column; }
  .sidebar { width:100%; height:auto; position:relative; flex-direction:row; align-items:center; padding:0; }
  .sidebar .brand { border:none; padding:1rem 1.25rem; }
  .sidebar nav { flex-direction:row; overflow-x:auto; padding:0.5rem; }
  .sidebar .kaki { display:none; }
  .wrap { padding:1.25rem; }
  .kartu-ringkasan { grid-template-columns:1fr 1fr; }
}
</style>
</head>
<body>

<aside class="sidebar">
    <div class="brand">
        <svg width="34" height="34" viewBox="0 0 34 34" fill="none">
            <circle cx="17" cy="17" r="17" fill="#D9A441"/>
            <path d="M6 20c2.5-2 5-2 7.5 0s5 2 7.5 0 5-2 7.5 0" stroke="#4A1F1F" stroke-width="1.8" stroke-linecap="round" fill="none"/>
            <path d="M6 24c2.5-2 5-2 7.5 0s5 2 7.5 0 5-2 7.5 0" stroke="#4A1F1F" stroke-width="1.8" stroke-linecap="round" fill="none" opacity="0.5"/>
            <path d="M17 6l3.5 8h-7z" fill="#4A1F1F"/>
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
        <?php if (isAdmin()): ?>
        <a href="kelola_lokasi.php">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
            Kelola Lokasi Parkir
        </a>
        <a href="kelola_tarif.php">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v10M9.5 9.5c0-1.4 1.1-2.2 2.5-2.2s2.5.8 2.5 2c0 2.4-5 1.6-5 4 0 1.2 1.1 2.2 2.5 2.2s2.5-.8 2.5-2.2"/></svg>
            Kelola Tarif Parkir
        </a>
        <a href="kelola_area.php">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
            Kelola Area Parkir
        </a>
        <a href="kelola_kendaraan.php">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 13l1.5-5A2 2 0 0 1 6.4 6.5h11.2A2 2 0 0 1 19.5 8l1.5 5"/><rect x="2" y="13" width="20" height="6" rx="1.5"/><circle cx="7" cy="19.5" r="1.5"/><circle cx="17" cy="19.5" r="1.5"/></svg>
            Kelola Kendaraan
        </a>
        <a href="log_aktivitas.php">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4v16l4-2 4 2 4-2 4 2V4z"/><path d="M8 9h8M8 13h8"/></svg>
            Log Aktifitas
        </a>
        <?php endif; ?>
        <?php if (isAdmin() || isPetugas()): ?>
        <a href="kelola_booking.php">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/></svg>
            Kelola Booking
        </a>
        <?php endif; ?>
        <?php if (isAdmin()): ?>
        <a href="kelola_user.php">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="8" r="3.2"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><path d="M16.5 4.2a3.2 3.2 0 0 1 0 6.2M20 20c0-2.8-1.8-5-4.5-5.8"/></svg>
            Kelola Akun Staf
        </a>
        <?php endif; ?>
        <?php if (isOwner()): ?>
        <a href="../owner/dashboard.php">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 15l4-5 3 3 5-7"/></svg>
            Rekap Transaksi
        </a>
        <?php endif; ?>
    </nav>
    <div class="kaki">
        <div class="siapa">
            <div class="avatar"><?= strtoupper(substr($_SESSION['nama_lengkap'], 0, 1)) ?></div>
            <div class="info">
                <strong><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></strong>
                <span><?= clean(labelRole(currentRole())) ?></span>
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
            <h1>Dashboard Admin</h1>
            <p>Kelola lokasi parkir, booking &amp; akun staf</p>
        </div>
        <a href="../index.php" class="tautan-lanjut">Lihat Situs →</a>
    </div>

    <div class="wrap">

        <div class="kartu-ringkasan">
            <div class="kartu sorot">
                <div class="label">Okupansi keseluruhan</div>
                <div class="nilai"><?= $persenOkupansi ?><span class="satuan">%</span></div>
            </div>
            <div class="kartu">
                <div class="label">Total pengunjung terdaftar</div>
                <div class="nilai"><?= $totalUser ?></div>
            </div>
            <div class="kartu">
                <div class="label">Lokasi parkir aktif</div>
                <div class="nilai"><?= $totalLokasi ?></div>
            </div>
            <div class="kartu">
                <div class="label">Booking menunggu konfirmasi</div>
                <div class="nilai"><?= $bookingPending ?></div>
            </div>
        </div>

        <div class="menu-crud">
            <?php if (isAdmin()): ?>
            <a class="menu-item" href="kelola_lokasi.php">
                <span class="ikon">
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                </span>
                <h3>Kelola Lokasi Parkir</h3>
                <p>Tambah, ubah lokasi, harga per jenis kendaraan &amp; kapasitas.</p>
            </a>
            <?php endif; ?>
            <?php if (isAdmin() || isPetugas()): ?>
            <a class="menu-item" href="kelola_booking.php">
                <span class="ikon">
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/></svg>
                </span>
                <h3>Kelola Booking</h3>
                <p>Lihat semua booking, konfirmasi pembayaran, ubah status.</p>
            </a>
            <?php endif; ?>
            <?php if (isAdmin()): ?>
            <a class="menu-item" href="kelola_user.php">
                <span class="ikon">
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="8" r="3.2"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/></svg>
                </span>
                <h3>Kelola Akun Staf</h3>
                <p>Tambah, ubah, atau nonaktifkan akun admin &amp; petugas.</p>
            </a>
            <?php endif; ?>
        </div>

        <div class="panel">
            <h2>Okupansi per Lokasi Parkir</h2>
            <canvas id="grafikOkupansi"></canvas>
        </div>

        <div class="panel">
            <h2>Booking Terbaru</h2>
            <?php if (empty($bookingTerbaru)): ?>
                <p style="color:var(--muted);">Belum ada booking tercatat.</p>
            <?php else: ?>
            <table>
                <thead>
                    <tr><th>Kode</th><th>Pemesan</th><th>Lokasi</th><th>Total</th><th>Status</th><th>Waktu</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($bookingTerbaru as $b): ?>
                    <tr>
                        <td><?= htmlspecialchars($b['kode_booking']) ?></td>
                        <td><?= htmlspecialchars($b['nama_user']) ?></td>
                        <td><?= htmlspecialchars($b['nama_lokasi']) ?></td>
                        <td><?= 'Rp' . number_format($b['total_harga'], 0, ',', '.') ?></td>
                        <td><span class="badge badge-<?= badgeKelas($b['status']) ?>"><?= htmlspecialchars(ucfirst($b['status'])) ?></span></td>
                        <td><?= date('d M Y, H:i', strtotime($b['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            <?php if (isAdmin() || isPetugas()): ?>
            <p style="margin-top:1rem;"><a href="kelola_booking.php" class="tautan-lanjut">Lihat semua booking →</a></p>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
Chart.defaults.font.family = "Inter, sans-serif";
Chart.defaults.color = '#71807F';

new Chart(document.getElementById('grafikOkupansi'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($labelLokasi) ?>,
        datasets: [{
            label: 'Okupansi (%)',
            data: <?= json_encode($dataOkupansiLokasi) ?>,
            backgroundColor: '#8C3B3B',
            borderRadius: 6
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { beginAtZero: true, max: 100, grid: { color: 'rgba(15,44,51,0.06)' } },
            y: { grid: { display: false } }
        }
    }
});
</script>

<!-- Suara notifikasi: notif.js harus ada di assets/js/, file mp3 di assets/sounds/ -->
<script src="../assets/js/notif.js"></script>
<?php if ($suara): ?>
<script>notifSound(<?= json_encode($suara) ?>);</script>
<?php endif; ?>

</body>
</html>