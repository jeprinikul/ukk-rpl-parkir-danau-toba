<?php
/**
 * owner/dashboard.php — khusus role: owner
 * Rekap pendapatan & transaksi sesuai rentang waktu yang diminta.
 * Tampilan: Tailwind, selaras dengan beranda TobaPark.
 */
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
if (!isOwner()) {
    header('Location: ../kelola_lokasi.php');
    exit;
}

$db = getDB();

/* ---------------- Suara notifikasi ---------------- */
// Owner hanya mendengar suara login (diset login.php), sekali pakai.
// Nilai lain (mis. 'error' yang tertinggal) dibuang tanpa diputar.
$suara = null;
if (!empty($_SESSION['notif_sound'])) {
    if ($_SESSION['notif_sound'] === 'login') $suara = 'login';
    unset($_SESSION['notif_sound']);
}

/* ---------------- Filter ---------------- */
$dariTanggal   = $_GET['dari'] ?? date('Y-m-d', strtotime('-29 day'));
$sampaiTanggal = $_GET['sampai'] ?? date('Y-m-d');
$filterLokasi  = $_GET['lokasi'] ?? '';
$filterJenis   = $_GET['jenis'] ?? '';

$tglAwal  = new DateTime($dariTanggal);
$tglAkhir = new DateTime($sampaiTanggal);
$jumlahHari = (int)$tglAwal->diff($tglAkhir)->days + 1;

$sebelumSampai = (clone $tglAwal)->modify('-1 day')->format('Y-m-d');
$sebelumDari   = (clone $tglAwal)->modify('-' . $jumlahHari . ' day')->format('Y-m-d');

function bangunFilter($filterLokasi, $filterJenis, $aliasBooking = 'b') {
    $klausa = '';
    $params = [];
    if ($filterLokasi !== '') {
        $klausa .= " AND {$aliasBooking}.lokasi_id = ?";
        $params[] = $filterLokasi;
    }
    if ($filterJenis !== '') {
        $klausa .= " AND {$aliasBooking}.jenis_kendaraan = ?";
        $params[] = $filterJenis;
    }
    return [$klausa, $params];
}
[$klausaFilter, $paramFilter] = bangunFilter($filterLokasi, $filterJenis);

/* ---------------- Data untuk dropdown filter ---------------- */
$daftarLokasi = $db->query("SELECT id, nama_lokasi FROM lokasi_parkir ORDER BY nama_lokasi")->fetchAll();
$daftarJenis  = $db->query("SELECT DISTINCT jenis_kendaraan FROM booking ORDER BY jenis_kendaraan")->fetchAll(PDO::FETCH_COLUMN);

/* ---------------- Ringkasan periode berjalan & sebelumnya ---------------- */
$sqlRingkasan = "
    SELECT COUNT(*) AS jumlah_transaksi,
           COALESCE(SUM(total_harga),0) AS total_pendapatan
    FROM booking b
    WHERE status = 'selesai' AND tanggal_booking BETWEEN ? AND ?" . $klausaFilter;

$stmtRingkasan = $db->prepare($sqlRingkasan);
$stmtRingkasan->execute(array_merge([$dariTanggal, $sampaiTanggal], $paramFilter));
$ringkasan = $stmtRingkasan->fetch();

$rataRataPerTransaksi = $ringkasan['jumlah_transaksi'] > 0
    ? $ringkasan['total_pendapatan'] / $ringkasan['jumlah_transaksi']
    : 0;

$stmtRingkasanLalu = $db->prepare($sqlRingkasan);
$stmtRingkasanLalu->execute(array_merge([$sebelumDari, $sebelumSampai], $paramFilter));
$ringkasanLalu = $stmtRingkasanLalu->fetch();

function hitungPersenPerubahan($sekarang, $lalu) {
    if ($lalu == 0) return $sekarang > 0 ? 100.0 : 0.0;
    return (($sekarang - $lalu) / $lalu) * 100;
}
$perubahanPendapatan = hitungPersenPerubahan($ringkasan['total_pendapatan'], $ringkasanLalu['total_pendapatan']);
$perubahanTransaksi  = hitungPersenPerubahan($ringkasan['jumlah_transaksi'], $ringkasanLalu['jumlah_transaksi']);

/* ---------------- Grafik: pendapatan harian ---------------- */
$sqlHarian = "
    SELECT tanggal_booking AS tanggal, SUM(total_harga) AS total
    FROM booking b
    WHERE status = 'selesai' AND tanggal_booking BETWEEN ? AND ?" . $klausaFilter . "
    GROUP BY tanggal_booking
    ORDER BY tanggal ASC";
$stmtHarian = $db->prepare($sqlHarian);
$stmtHarian->execute(array_merge([$dariTanggal, $sampaiTanggal], $paramFilter));
$perTanggal = [];
foreach ($stmtHarian->fetchAll() as $row) { $perTanggal[$row['tanggal']] = (float)$row['total']; }

$labelHarian = [];
$dataHarian = [];
$periode = new DatePeriod(new DateTime($dariTanggal), new DateInterval('P1D'), (new DateTime($sampaiTanggal))->modify('+1 day'));
foreach ($periode as $tgl) {
    $key = $tgl->format('Y-m-d');
    $labelHarian[] = $tgl->format('d M');
    $dataHarian[] = $perTanggal[$key] ?? 0;
}

/* ---------------- Grafik: pendapatan per lokasi ---------------- */
$sqlLokasi = "
    SELECT l.nama_lokasi, COALESCE(SUM(b.total_harga),0) AS total, COUNT(b.id) AS jumlah
    FROM lokasi_parkir l
    LEFT JOIN booking b ON b.lokasi_id = l.id AND b.status = 'selesai'
        AND b.tanggal_booking BETWEEN ? AND ?" .
        ($filterJenis !== '' ? " AND b.jenis_kendaraan = ?" : "") . "
    " . ($filterLokasi !== '' ? "WHERE l.id = ?" : "") . "
    GROUP BY l.id, l.nama_lokasi
    ORDER BY total DESC";
$paramLokasi = [$dariTanggal, $sampaiTanggal];
if ($filterJenis !== '') $paramLokasi[] = $filterJenis;
if ($filterLokasi !== '') $paramLokasi[] = $filterLokasi;
$stmtLokasi = $db->prepare($sqlLokasi);
$stmtLokasi->execute($paramLokasi);
$dataLokasiRaw = $stmtLokasi->fetchAll();

$labelLokasi = []; $dataLokasi = [];
foreach ($dataLokasiRaw as $row) { $labelLokasi[] = $row['nama_lokasi']; $dataLokasi[] = (float)$row['total']; }
$topLokasi = array_slice($dataLokasiRaw, 0, 5);
$totalSemuaLokasi = array_sum($dataLokasi) ?: 1;

/* ---------------- Grafik: breakdown per jenis kendaraan ---------------- */
$sqlJenis = "
    SELECT jenis_kendaraan, COUNT(*) AS jumlah, COALESCE(SUM(total_harga),0) AS total
    FROM booking b
    WHERE status = 'selesai' AND tanggal_booking BETWEEN ? AND ?" . $klausaFilter . "
    GROUP BY jenis_kendaraan
    ORDER BY total DESC";
$stmtJenis = $db->prepare($sqlJenis);
$stmtJenis->execute(array_merge([$dariTanggal, $sampaiTanggal], $paramFilter));
$dataJenisRaw = $stmtJenis->fetchAll();
$labelJenis = []; $dataJenisTotal = [];
foreach ($dataJenisRaw as $row) { $labelJenis[] = ucfirst($row['jenis_kendaraan']); $dataJenisTotal[] = (float)$row['total']; }

/* ---------------- Detail transaksi ---------------- */
$sqlDetail = "
    SELECT b.kode_booking, b.plat_nomor, b.jenis_kendaraan, l.nama_lokasi,
           b.tanggal_booking, b.jam_masuk, b.total_harga, u.name AS nama_user
    FROM booking b
    JOIN lokasi_parkir l ON l.id = b.lokasi_id
    JOIN users u ON u.id = b.user_id
    WHERE b.status = 'selesai' AND b.tanggal_booking BETWEEN ? AND ?" . $klausaFilter . "
    ORDER BY b.tanggal_booking DESC, b.jam_masuk DESC
    LIMIT 500";
$stmtDetail = $db->prepare($sqlDetail);
$stmtDetail->execute(array_merge([$dariTanggal, $sampaiTanggal], $paramFilter));
$detailTransaksi = $stmtDetail->fetchAll();

function rp($angka) { return 'Rp' . number_format((float)$angka, 0, ',', '.'); }
function tandaPersen($nilai) { return ($nilai >= 0 ? '+' : '') . number_format($nilai, 1) . '%'; }

/* ---------------- Export CSV ---------------- */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=rekap_transaksi_' . $dariTanggal . '_sampai_' . $sampaiTanggal . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Kode Booking', 'Pemesan', 'Plat Nomor', 'Jenis Kendaraan', 'Lokasi', 'Tanggal', 'Jam Masuk', 'Total Harga']);
    foreach ($detailTransaksi as $t) {
        fputcsv($out, [$t['kode_booking'], $t['nama_user'], $t['plat_nomor'], $t['jenis_kendaraan'],
            $t['nama_lokasi'], $t['tanggal_booking'], substr($t['jam_masuk'], 0, 5), $t['total_harga']]);
    }
    fputcsv($out, []);
    fputcsv($out, ['Ringkasan Periode ' . $dariTanggal . ' s/d ' . $sampaiTanggal]);
    fputcsv($out, ['Jumlah Transaksi', $ringkasan['jumlah_transaksi']]);
    fputcsv($out, ['Total Pendapatan', $ringkasan['total_pendapatan']]);
    fputcsv($out, ['Rata-rata per Transaksi', round($rataRataPerTransaksi)]);
    fclose($out);
    exit;
}

$inisial = strtoupper(substr($_SESSION['nama_lengkap'], 0, 1));
$naikPend = $perubahanPendapatan >= 0;
$naikTrans = $perubahanTransaksi >= 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Owner — TobaPark</title>
<link rel="icon" href="data:,">
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif:ital,wght@0,400..700;1,400..700&family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config={darkMode:"class",theme:{extend:{"colors":{"on-tertiary-container":"#f27274","surface":"#f6faf7","on-error":"#ffffff","on-secondary-fixed-variant":"#673d00","primary-fixed":"#c1eaea","on-secondary-fixed":"#2b1700","on-primary-container":"#7aa2a1","tertiary-container":"#680816","error":"#ba1a1a","surface-dim":"#d7dbd8","on-primary-fixed":"#002020","inverse-on-surface":"#edf2ee","secondary-fixed":"#ffddba","inverse-primary":"#a5cecd","secondary-fixed-dim":"#ffb866","on-background":"#181d1b","on-primary":"#ffffff","on-tertiary-fixed-variant":"#852128","surface-variant":"#dfe4e0","on-secondary-container":"#724400","error-container":"#ffdad6","outline-variant":"#c0c8c7","tertiary-fixed":"#ffdad8","surface-tint":"#3e6565","on-surface-variant":"#414848","outline":"#717978","primary-container":"#0d3838","background":"#f6faf7","surface-container-low":"#f0f5f1","on-tertiary-fixed":"#410008","on-error-container":"#93000a","secondary-container":"#fdb257","inverse-surface":"#2c312f","surface-container-high":"#e5e9e6","surface-bright":"#f6faf7","surface-container-highest":"#dfe4e0","secondary":"#875200","on-surface":"#181d1b","surface-container-lowest":"#ffffff","surface-container":"#eaefeb","on-secondary":"#ffffff","on-tertiary":"#ffffff","tertiary-fixed-dim":"#ffb3b1","primary-fixed-dim":"#a5cecd","on-primary-fixed-variant":"#254d4d","primary":"#002222","tertiary":"#430008"},"borderRadius":{"DEFAULT":"0.25rem","lg":"0.5rem","xl":"0.75rem","full":"9999px"},"spacing":{"space-sm":"0.5rem","space-lg":"1.75rem","space-xl":"2.5rem","margin":"3rem","gutter":"1.5rem","gutter-mobile":"1rem","space-md":"1rem","margin-mobile":"1.25rem","space-xs":"0.25rem"},"fontFamily":{"display-lg-mobile":["Noto Serif"],"label-lg":["Plus Jakarta Sans"],"body-md":["Plus Jakarta Sans"],"label-sm":["Plus Jakarta Sans"],"headline-md":["Noto Serif"],"label-md":["Plus Jakarta Sans"],"body-sm":["Plus Jakarta Sans"],"headline-sm":["Noto Serif"],"headline-lg":["Noto Serif"],"body-lg":["Plus Jakarta Sans"],"headline-lg-mobile":["Noto Serif"],"display-lg":["Noto Serif"]},"fontSize":{"display-lg-mobile":["36px",{"lineHeight":"42px","letterSpacing":"-0.015em","fontWeight":"600"}],"label-lg":["14px",{"lineHeight":"18px","letterSpacing":"0.02em","fontWeight":"600"}],"body-md":["15px",{"lineHeight":"24px","fontWeight":"400"}],"label-sm":["11px",{"lineHeight":"14px","letterSpacing":"0.08em","fontWeight":"700"}],"headline-md":["26px",{"lineHeight":"34px","fontWeight":"600"}],"label-md":["12px",{"lineHeight":"16px","letterSpacing":"0.05em","fontWeight":"600"}],"body-sm":["13px",{"lineHeight":"20px","fontWeight":"400"}],"headline-sm":["20px",{"lineHeight":"28px","fontWeight":"500"}],"headline-lg":["38px",{"lineHeight":"46px","letterSpacing":"-0.01em","fontWeight":"600"}],"body-lg":["18px",{"lineHeight":"28px","fontWeight":"400"}],"headline-lg-mobile":["28px",{"lineHeight":"34px","letterSpacing":"-0.01em","fontWeight":"600"}],"display-lg":["52px",{"lineHeight":"60px","letterSpacing":"-0.02em","fontWeight":"600"}]}}}}
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<style>
  body { margin: 0; }
  .tabel-scroll { max-height: 440px; overflow: auto; }
  .tabel-scroll thead th { position: sticky; top: 0; z-index: 1; }
  @media print {
    body { background: #fff; }
    .no-print { display: none !important; }
    .panel, .kpi { break-inside: avoid; box-shadow: none !important; border: 1px solid #ccc; }
    .tabel-scroll { max-height: none; overflow: visible; }
  }
</style>
</head>
<body class="bg-surface font-body-md text-on-surface antialiased flex flex-col lg:flex-row min-h-screen selection:bg-secondary-fixed selection:text-on-secondary-fixed">

<!-- ================= SIDEBAR (desktop) ================= -->
<aside class="no-print hidden lg:flex flex-col w-64 shrink-0 bg-primary-container text-surface sticky top-0 h-screen">
  <div class="flex items-center gap-space-sm px-space-lg py-space-lg border-b border-surface/10">
    <div class="w-10 h-10 rounded-lg bg-surface/10 flex items-center justify-center">
      <span class="material-symbols-outlined text-secondary-container text-[24px]">anchor</span>
    </div>
    <div class="flex flex-col">
      <span class="font-headline-sm text-headline-sm text-surface tracking-tight leading-none">TobaPark</span>
      <span class="font-label-sm text-[10px] text-secondary-container tracking-widest uppercase mt-0.5">Panel Owner</span>
    </div>
  </div>

  <nav class="flex-1 px-space-md py-space-lg flex flex-col gap-space-xs">
    <a href="dashboard.php" class="flex items-center gap-space-sm px-space-md py-space-sm rounded-lg bg-surface/10 text-surface font-label-lg text-label-lg border-l-4 border-secondary-container">
      <span class="material-symbols-outlined text-[20px]">dashboard</span>Rekap &amp; Dashboard
    </a>
    <a href="../index.php" class="flex items-center gap-space-sm px-space-md py-space-sm rounded-lg text-surface-container-high hover:bg-surface/10 hover:text-surface font-label-lg text-label-lg border-l-4 border-transparent transition-colors">
      <span class="material-symbols-outlined text-[20px]">home</span>Lihat Beranda
    </a>
  </nav>

  <div class="px-space-lg py-space-lg border-t border-surface/10">
    <div class="flex items-center gap-space-sm mb-space-md">
      <div class="w-10 h-10 rounded-full bg-secondary-container text-on-secondary-container flex items-center justify-center font-headline-sm text-headline-sm font-bold"><?= $inisial ?></div>
      <div class="min-w-0">
        <div class="font-label-lg text-label-lg text-surface truncate"><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></div>
        <div class="font-label-sm text-label-sm text-on-primary-container uppercase">Owner</div>
      </div>
    </div>
    <a href="../logout.php" class="flex items-center gap-space-xs text-surface-container-high hover:text-secondary-container font-label-lg text-label-lg transition-colors">
      <span class="material-symbols-outlined text-[18px]">logout</span>Keluar
    </a>
  </div>
</aside>

<!-- ================= HEADER (mobile) ================= -->
<header class="no-print lg:hidden bg-primary-container text-surface px-gutter py-space-md flex items-center justify-between">
  <div class="flex items-center gap-space-sm">
    <span class="material-symbols-outlined text-secondary-container text-[26px]">anchor</span>
    <span class="font-headline-sm text-headline-sm">TobaPark <span class="font-label-sm text-label-sm text-secondary-container uppercase tracking-widest ml-1">Owner</span></span>
  </div>
  <a href="../logout.php" class="flex items-center gap-1 font-label-lg text-label-lg text-surface-container-high hover:text-secondary-container">
    <span class="material-symbols-outlined text-[18px]">logout</span>Keluar
  </a>
</header>

<!-- ================= KONTEN ================= -->
<div class="flex-1 min-w-0">

  <div class="bg-surface-container-lowest border-b border-outline-variant/40 px-gutter lg:px-margin py-space-lg">
    <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Ringkasan Bisnis</span>
    <h1 class="font-headline-lg-mobile md:font-headline-lg text-headline-lg-mobile md:text-headline-lg text-primary mt-space-xs">Rekap Transaksi</h1>
    <p class="font-body-md text-body-md text-on-surface-variant mt-space-xs">Pendapatan &amp; aktivitas parkir sesuai rentang waktu yang diminta.</p>
  </div>

  <div class="max-w-[1400px] mx-auto px-gutter lg:px-margin py-space-lg flex flex-col gap-space-lg">

    <!-- Filter -->
    <form method="GET" class="no-print bg-surface-container-lowest rounded-xl shadow-sm p-space-lg relative overflow-hidden">
      <div class="h-1 w-full bg-gradient-to-r from-tertiary via-secondary to-primary-container absolute top-0 left-0 right-0"></div>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-space-md pt-space-xs">
        <div>
          <label for="dari" class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider block mb-1.5">Dari tanggal</label>
          <input type="date" id="dari" name="dari" value="<?= htmlspecialchars($dariTanggal) ?>" class="w-full bg-surface-container-low text-on-surface font-label-lg text-label-lg px-space-md py-2.5 rounded-lg focus:outline-none focus:bg-surface focus:ring-2 focus:ring-secondary-container">
        </div>
        <div>
          <label for="sampai" class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider block mb-1.5">Sampai tanggal</label>
          <input type="date" id="sampai" name="sampai" value="<?= htmlspecialchars($sampaiTanggal) ?>" class="w-full bg-surface-container-low text-on-surface font-label-lg text-label-lg px-space-md py-2.5 rounded-lg focus:outline-none focus:bg-surface focus:ring-2 focus:ring-secondary-container">
        </div>
        <div>
          <label for="lokasi" class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider block mb-1.5">Lokasi</label>
          <select id="lokasi" name="lokasi" class="w-full bg-surface-container-low text-on-surface font-label-lg text-label-lg px-space-md py-2.5 rounded-lg focus:outline-none focus:bg-surface focus:ring-2 focus:ring-secondary-container cursor-pointer">
            <option value="">Semua Lokasi</option>
            <?php foreach ($daftarLokasi as $lok): ?>
              <option value="<?= $lok['id'] ?>" <?= $filterLokasi == $lok['id'] ? 'selected' : '' ?>><?= htmlspecialchars($lok['nama_lokasi']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="jenis" class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider block mb-1.5">Jenis Kendaraan</label>
          <select id="jenis" name="jenis" class="w-full bg-surface-container-low text-on-surface font-label-lg text-label-lg px-space-md py-2.5 rounded-lg focus:outline-none focus:bg-surface focus:ring-2 focus:ring-secondary-container cursor-pointer">
            <option value="">Semua Jenis</option>
            <?php foreach ($daftarJenis as $j): ?>
              <option value="<?= htmlspecialchars($j) ?>" <?= $filterJenis === $j ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($j)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-space-sm mt-space-md">
        <button type="submit" class="bg-secondary-container hover:bg-secondary text-on-secondary-container hover:text-on-secondary font-label-lg text-label-lg px-space-lg py-2.5 rounded-lg shadow-sm flex items-center gap-space-xs transition-all">
          <span class="material-symbols-outlined text-[18px]">filter_alt</span>Tampilkan Rekap
        </button>
        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="border border-outline-variant text-primary hover:bg-surface-container font-label-lg text-label-lg px-space-md py-2.5 rounded-lg flex items-center gap-space-xs transition-colors">
          <span class="material-symbols-outlined text-[18px]">download</span>Export CSV
        </a>
        <a href="#" onclick="window.print(); return false;" class="border border-outline-variant text-primary hover:bg-surface-container font-label-lg text-label-lg px-space-md py-2.5 rounded-lg flex items-center gap-space-xs transition-colors">
          <span class="material-symbols-outlined text-[18px]">print</span>Cetak
        </a>
      </div>
    </form>

    <!-- Kartu ringkasan -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-space-md">
      <div class="kpi md:col-span-1 relative overflow-hidden rounded-xl p-space-lg bg-gradient-to-br from-primary to-primary-container text-surface shadow-md">
        <div class="absolute -right-10 -bottom-12 w-40 h-40 rounded-full bg-secondary-container/15"></div>
        <div class="relative">
          <div class="flex items-center justify-between">
            <span class="font-label-md text-label-md text-on-primary-container uppercase tracking-wider">Total pendapatan</span>
            <span class="material-symbols-outlined text-secondary-container text-[22px]">payments</span>
          </div>
          <div class="font-headline-lg text-headline-lg text-surface mt-space-sm break-words"><?= rp($ringkasan['total_pendapatan']) ?></div>
          <div class="mt-space-sm inline-flex items-center gap-1 font-label-md text-label-md px-space-sm py-1 rounded-full <?= $naikPend ? 'bg-emerald-400/20 text-emerald-200' : 'bg-red-400/20 text-red-200' ?>">
            <span class="material-symbols-outlined text-[16px]"><?= $naikPend ? 'trending_up' : 'trending_down' ?></span>
            <?= tandaPersen($perubahanPendapatan) ?> vs periode sebelumnya
          </div>
        </div>
      </div>

      <div class="kpi bg-surface-container-lowest rounded-xl p-space-lg shadow-sm">
        <div class="flex items-center justify-between">
          <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider">Booking selesai</span>
          <div class="w-9 h-9 rounded-lg bg-primary-fixed/50 flex items-center justify-center"><span class="material-symbols-outlined text-primary text-[20px]">confirmation_number</span></div>
        </div>
        <div class="font-headline-lg text-headline-lg text-primary mt-space-sm"><?= (int)$ringkasan['jumlah_transaksi'] ?></div>
        <div class="mt-space-sm inline-flex items-center gap-1 font-label-md text-label-md px-space-sm py-1 rounded-full <?= $naikTrans ? 'bg-emerald-100 text-emerald-800' : 'bg-error-container text-on-error-container' ?>">
          <span class="material-symbols-outlined text-[16px]"><?= $naikTrans ? 'trending_up' : 'trending_down' ?></span>
          <?= tandaPersen($perubahanTransaksi) ?>
        </div>
      </div>

      <div class="kpi bg-surface-container-lowest rounded-xl p-space-lg shadow-sm">
        <div class="flex items-center justify-between">
          <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider">Rata-rata per booking</span>
          <div class="w-9 h-9 rounded-lg bg-secondary-fixed/60 flex items-center justify-center"><span class="material-symbols-outlined text-secondary text-[20px]">calculate</span></div>
        </div>
        <div class="font-headline-lg text-headline-lg text-primary mt-space-sm break-words"><?= rp($rataRataPerTransaksi) ?></div>
        <div class="mt-space-sm font-body-sm text-body-sm text-on-surface-variant"><?= $jumlahHari ?> hari dalam periode</div>
      </div>
    </div>

    <!-- Grafik atas -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-space-md">
      <div class="panel lg:col-span-2 bg-surface-container-lowest rounded-xl p-space-lg shadow-sm">
        <h2 class="font-headline-sm text-headline-sm text-primary mb-space-md">Pendapatan Harian</h2>
        <div class="relative h-72"><canvas id="grafikHarian"></canvas></div>
      </div>
      <div class="panel bg-surface-container-lowest rounded-xl p-space-lg shadow-sm">
        <h2 class="font-headline-sm text-headline-sm text-primary mb-space-md">Per Jenis Kendaraan</h2>
        <div class="relative h-72"><canvas id="grafikJenis"></canvas></div>
      </div>
    </div>

    <!-- Grafik bawah -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-space-md">
      <div class="panel bg-surface-container-lowest rounded-xl p-space-lg shadow-sm">
        <h2 class="font-headline-sm text-headline-sm text-primary mb-space-md">Pendapatan per Lokasi</h2>
        <div class="relative h-72"><canvas id="grafikLokasi"></canvas></div>
      </div>
      <div class="panel bg-surface-container-lowest rounded-xl p-space-lg shadow-sm">
        <h2 class="font-headline-sm text-headline-sm text-primary mb-space-md">Top 5 Lokasi Terlaris</h2>
        <?php if (empty($topLokasi)): ?>
          <p class="font-body-md text-body-md text-on-surface-variant">Belum ada data.</p>
        <?php else: ?>
          <div class="flex flex-col gap-space-md">
          <?php foreach ($topLokasi as $i => $lok): ?>
            <div>
              <div class="flex items-center justify-between gap-space-sm">
                <span class="flex items-center gap-space-sm font-label-lg text-label-lg text-on-surface min-w-0">
                  <span class="w-7 h-7 rounded-full bg-secondary-container text-on-secondary-container flex items-center justify-center font-headline-sm text-[13px] font-bold shrink-0"><?= $i + 1 ?></span>
                  <span class="truncate"><?= htmlspecialchars($lok['nama_lokasi']) ?></span>
                </span>
                <strong class="font-label-lg text-label-lg text-primary shrink-0"><?= rp($lok['total']) ?></strong>
              </div>
              <div class="mt-1.5 h-2 rounded-full bg-surface-container-high overflow-hidden">
                <div class="h-full rounded-full bg-gradient-to-r from-secondary-container to-secondary" style="width: <?= round(($lok['total'] / $totalSemuaLokasi) * 100) ?>%;"></div>
              </div>
            </div>
          <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Detail transaksi -->
    <div class="panel bg-surface-container-lowest rounded-xl p-space-lg shadow-sm">
      <h2 class="font-headline-sm text-headline-sm text-primary mb-space-md">
        Detail Booking Selesai
        <span class="block sm:inline font-body-md text-body-md font-normal text-on-surface-variant sm:ml-2">(<?= date('d M Y', strtotime($dariTanggal)) ?> – <?= date('d M Y', strtotime($sampaiTanggal)) ?>)</span>
      </h2>
      <?php if (empty($detailTransaksi)): ?>
        <p class="font-body-md text-body-md text-on-surface-variant p-space-lg text-center bg-surface-container-low rounded-lg">Tidak ada booking selesai pada rentang ini.</p>
      <?php else: ?>
        <div class="tabel-scroll rounded-lg border border-outline-variant/40">
          <table class="w-full text-left font-body-sm text-body-sm">
            <thead>
              <tr class="bg-surface-container text-on-surface-variant font-label-sm text-label-sm uppercase tracking-wider">
                <th class="px-space-md py-space-sm bg-surface-container">Kode</th>
                <th class="px-space-md py-space-sm bg-surface-container">Pemesan</th>
                <th class="px-space-md py-space-sm bg-surface-container">Plat</th>
                <th class="px-space-md py-space-sm bg-surface-container">Lokasi</th>
                <th class="px-space-md py-space-sm bg-surface-container">Tanggal</th>
                <th class="px-space-md py-space-sm bg-surface-container text-right">Total</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/30">
            <?php foreach ($detailTransaksi as $t): ?>
              <tr class="hover:bg-surface-container-low transition-colors">
                <td class="px-space-md py-space-sm font-label-lg text-label-lg text-primary whitespace-nowrap"><?= htmlspecialchars($t['kode_booking']) ?></td>
                <td class="px-space-md py-space-sm"><?= htmlspecialchars($t['nama_user']) ?></td>
                <td class="px-space-md py-space-sm whitespace-nowrap">
                  <?= htmlspecialchars($t['plat_nomor']) ?>
                  <span class="ml-1 inline-block bg-surface-container-high text-on-surface-variant font-label-sm text-[10px] px-space-xs py-0.5 rounded uppercase"><?= htmlspecialchars($t['jenis_kendaraan']) ?></span>
                </td>
                <td class="px-space-md py-space-sm"><?= htmlspecialchars($t['nama_lokasi']) ?></td>
                <td class="px-space-md py-space-sm whitespace-nowrap"><?= date('d/m/Y', strtotime($t['tanggal_booking'])) ?> <span class="text-on-surface-variant"><?= substr($t['jam_masuk'], 0, 5) ?></span></td>
                <td class="px-space-md py-space-sm text-right font-label-lg text-label-lg text-primary whitespace-nowrap"><?= rp($t['total_harga']) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<script>
Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
Chart.defaults.color = '#414848';
var formatRp = function (v) { return 'Rp' + Number(v).toLocaleString('id-ID'); };
var gridWarna = 'rgba(13,56,56,0.07)';

new Chart(document.getElementById('grafikHarian'), {
    type: 'line',
    data: {
        labels: <?= json_encode($labelHarian) ?>,
        datasets: [{
            label: 'Pendapatan (Rp)',
            data: <?= json_encode($dataHarian) ?>,
            borderColor: '#875200',
            backgroundColor: 'rgba(253,178,87,0.28)',
            fill: true, tension: 0.35, pointRadius: 2, pointBackgroundColor: '#875200', borderWidth: 2.5
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return formatRp(c.parsed.y); } } } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: formatRp }, grid: { color: gridWarna } },
            x: { grid: { display: false } }
        }
    }
});

new Chart(document.getElementById('grafikLokasi'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($labelLokasi) ?>,
        datasets: [{ label: 'Pendapatan (Rp)', data: <?= json_encode($dataLokasi) ?>, backgroundColor: '#0d3838', hoverBackgroundColor: '#875200', borderRadius: 6, maxBarThickness: 42 }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return formatRp(c.parsed.y); } } } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: formatRp }, grid: { color: gridWarna } },
            x: { grid: { display: false } }
        }
    }
});

new Chart(document.getElementById('grafikJenis'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($labelJenis) ?>,
        datasets: [{ data: <?= json_encode($dataJenisTotal) ?>, backgroundColor: ['#fdb257', '#0d3838', '#430008', '#2e7d4f', '#875200'], borderWidth: 2, borderColor: '#ffffff' }]
    },
    options: {
        responsive: true, maintainAspectRatio: false, cutout: '62%',
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 14 } }, tooltip: { callbacks: { label: function (c) { return c.label + ': ' + formatRp(c.parsed); } } } }
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