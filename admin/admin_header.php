<?php
// Tahan output halaman sampai selesai. Tanpa ini, redirect setelah simpan/hapus data
// (redirectWithMessage) gagal dengan "Cannot modify header information" karena
// sidebar/CSS sudah terkirim ke browser lebih dulu.
ob_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireStaff();
$rootPath = '../';

// Deteksi file halaman yang sedang dibuka, untuk menyorot menu yang aktif di sidebar
$currentFile = basename($_SERVER['PHP_SELF']);
function menuAktif($file, $currentFile) {
    return $file === $currentFile ? ' aktif' : '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? clean($pageTitle) . ' - ' : '' ?>Admin Parkir Danau Toba</title>
<link rel="icon" href="data:,">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Fraunces:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
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
body { font-family:var(--font-body); background:var(--paper); color:var(--ink); }

.admin-layout { display:flex; min-height:100vh; }

/* ---------- Sidebar ---------- */
.admin-sidebar {
  width:248px; flex-shrink:0; background:var(--ulos-deep); color:#EFE4E0;
  display:flex; flex-direction:column; position:sticky; top:0; height:100vh;
}
.admin-sidebar h2 {
  font-family:var(--font-display); font-size:1.05rem; font-weight:600; color:#fff; margin:0;
  padding:1.6rem 1.5rem 0.2rem; display:flex; align-items:center; gap:0.6rem;
}
.admin-sidebar .sidebar-user {
  font-size:0.72rem; color:#CDA8A2; letter-spacing:0.02em; margin:0; padding:0 1.5rem 1.3rem;
  border-bottom:1px solid #603030;
}
.admin-sidebar nav { padding:1.25rem 0.9rem; display:flex; flex-direction:column; gap:0.2rem; flex:1; }
.admin-sidebar nav a {
  display:flex; align-items:center; padding:0.7rem 0.85rem; border-radius:9px;
  color:#DCC3BF; text-decoration:none; font-size:0.91rem; font-weight:500;
  border-left:3px solid transparent; transition:background 0.15s ease;
}
.admin-sidebar nav a:hover { background:var(--ulos-mid); color:#fff; }
.admin-sidebar nav a.aktif { background:var(--ulos-mid); color:#fff; border-left-color:var(--gold); }

/* ---------- Konten utama ---------- */
.admin-content { flex:1; min-width:0; padding:2.2rem; max-width:1320px; margin:0 auto; }

.page-header-row { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.8rem; margin-bottom:1.75rem; }
.page-header-row h1 { font-family:var(--font-display); font-size:1.55rem; margin:0; font-weight:600; color:var(--lake-deep); }

.btn-primary {
  background:var(--gold); color:var(--lake-deep); border:none; padding:0.65rem 1.3rem; border-radius:9px;
  font-weight:700; font-size:0.9rem; text-decoration:none; display:inline-flex; align-items:center; cursor:pointer;
}
.btn-primary:hover { background:var(--gold-dim); }

.tabel-riwayat, table { width:100%; border-collapse:collapse; font-size:0.88rem; background:var(--card); border-radius:var(--radius); overflow:hidden; }
.tabel-riwayat thead tr, table thead tr { background:var(--lake-deep); }
.tabel-riwayat th, table th { text-align:left; padding:0.85rem 0.9rem; color:#fff; font-weight:600; font-size:0.78rem; text-transform:none; }
.tabel-riwayat td, table td { text-align:left; padding:0.8rem 0.9rem; border-bottom:1px solid var(--line); }
.tabel-riwayat tbody tr:hover, table tbody tr:hover { background:var(--paper); }

.badge { display:inline-block; padding:0.2rem 0.7rem; border-radius:99px; font-size:0.76rem; font-weight:600; }
.badge-dibayar { background:#153B44; color:#fff; }
.badge-dibatalkan { background:var(--ulos-red); color:#fff; }
.badge-pending { background:#F1DDAE; color:var(--amber); }
.badge-selesai { background:#D6E8D6; color:var(--green); }

.btn-small {
  display:inline-block; padding:0.4rem 0.85rem; border-radius:7px; font-size:0.82rem; font-weight:600;
  text-decoration:none; background:var(--lake-deep); color:#fff; border:none; cursor:pointer;
}
.btn-small:hover { background:#123943; }
.btn-small.btn-danger { background:var(--ulos-red); }
.btn-small.btn-danger:hover { background:#722c2c; }

.alert { padding:0.85rem 1.1rem; border-radius:10px; font-size:0.88rem; margin-bottom:1.25rem; }
.alert-success { background:#D6E8D6; color:var(--green); }
.alert-danger { background:#F0D3D3; color:var(--red); }

@media (max-width:820px) {
  .admin-layout { flex-direction:column; }
  .admin-sidebar { width:100%; height:auto; position:relative; flex-direction:row; align-items:center; padding:0; }
  .admin-sidebar h2 { padding:1rem 1.25rem 0; border:none; }
  .admin-sidebar .sidebar-user { display:none; }
  .admin-sidebar nav { flex-direction:row; overflow-x:auto; padding:0.5rem; }
  .admin-content { padding:1.25rem; }
}
</style>
</head>
<body>
<div class="admin-layout">
    <aside class="admin-sidebar">
        <h2>
            <svg width="26" height="26" viewBox="0 0 34 34" fill="none">
                <circle cx="17" cy="17" r="17" fill="#D9A441"/>
                <path d="M6 20c2.5-2 5-2 7.5 0s5 2 7.5 0 5-2 7.5 0" stroke="#4A1F1F" stroke-width="1.8" stroke-linecap="round" fill="none"/>
                <path d="M6 24c2.5-2 5-2 7.5 0s5 2 7.5 0 5-2 7.5 0" stroke="#4A1F1F" stroke-width="1.8" stroke-linecap="round" fill="none" opacity="0.5"/>
                <path d="M17 6l3.5 8h-7z" fill="#4A1F1F"/>
            </svg>
            Panel <?= clean(labelRole(currentRole())) ?>
        </h2>
        <p class="sidebar-user"><?= clean($_SESSION['nama_lengkap']) ?></p>
        <nav>
            <a class="<?= trim(menuAktif('dashboard.php', $currentFile)) ?>" href="dashboard.php">Dashboard</a>
            <?php if (isAdmin()): ?>
                <a class="<?= trim(menuAktif('kelola_lokasi.php', $currentFile)) ?>" href="kelola_lokasi.php">Kelola Lokasi Parkir</a>
                <a class="<?= trim(menuAktif('kelola_tarif.php', $currentFile)) ?>" href="kelola_tarif.php">Kelola Tarif Parkir</a>
                <a class="<?= trim(menuAktif('kelola_area.php', $currentFile)) ?>" href="kelola_area.php">Kelola Area Parkir</a>
                <a class="<?= trim(menuAktif('kelola_kendaraan.php', $currentFile)) ?>" href="kelola_kendaraan.php">Kelola Kendaraan</a>
                <a class="<?= trim(menuAktif('log_aktivitas.php', $currentFile)) ?>" href="log_aktivitas.php">Log Aktifitas</a>
            <?php endif; ?>
            <?php if (isAdmin() || isPetugas()): ?>
                <a class="<?= trim(menuAktif('kelola_booking.php', $currentFile)) ?>" href="kelola_booking.php">Kelola Booking</a>
            <?php endif; ?>
            <?php if (isAdmin() || isPetugas() || isOwner()): ?>
                <a class="<?= trim(menuAktif('riwayat_manual.php', $currentFile)) ?>" href="riwayat_manual.php">Riwayat Parkir Manual</a>
            <?php endif; ?>
            <?php if (isAdmin()): ?>
                <a class="<?= trim(menuAktif('kelola_user.php', $currentFile)) ?>" href="kelola_user.php">Kelola Akun Staf</a>
            <?php endif; ?>
            <?php if (isOwner()): ?>
                <a href="../owner/dashboard.php">Rekap Transaksi</a>
            <?php endif; ?>
            <a href="../index.php">Lihat Situs</a>
            <a href="../logout.php">Keluar</a>
        </nav>
    </aside>
    <main class="admin-content">
        <?php showFlashMessage(); ?>