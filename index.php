<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$db = getDB();

// URL halaman login staf — ubah di sini jika nama file Anda berbeda
$urlLoginStaff = 'login.php';

// ===== Sesi, CSRF, flash message untuk form ulasan publik =====
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['csrf_ulasan'])) {
    $_SESSION['csrf_ulasan'] = bin2hex(random_bytes(16));
}
$flashUlasan = $_SESSION['flash_ulasan'] ?? null;
unset($_SESSION['flash_ulasan']);

// Semua lokasi aktif (untuk dropdown form ulasan & statistik)
$semuaLokasi = $db->query("SELECT id, nama_lokasi FROM lokasi_parkir WHERE status='aktif' ORDER BY nama_lokasi ASC")->fetchAll();

// ===== Proses kirim ulasan (publik, tanpa login) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'kirim_ulasan') {
    $pesan = ['tipe' => 'error', 'teks' => ''];

    if (!hash_equals($_SESSION['csrf_ulasan'], $_POST['csrf'] ?? '')) {
        $pesan['teks'] = 'Sesi tidak valid, silakan muat ulang halaman.';
    } elseif (!empty($_POST['website'])) {
        $pesan['teks'] = 'Ulasan ditolak.'; // honeypot
    } elseif (isset($_SESSION['ulasan_terakhir']) && time() - $_SESSION['ulasan_terakhir'] < 60) {
        $pesan['teks'] = 'Mohon tunggu sebentar sebelum mengirim ulasan lagi.';
    } else {
        $nama     = trim($_POST['nama'] ?? '');
        $lokasiId = (int)($_POST['lokasi_id'] ?? 0);
        $rating   = (int)($_POST['rating'] ?? 0);
        $komentar = trim($_POST['komentar'] ?? '');

        $lokasiValid = false;
        foreach ($semuaLokasi as $l) {
            if ((int)$l['id'] === $lokasiId) { $lokasiValid = true; break; }
        }

        if ($nama === '' || mb_strlen($nama) > 60) {
            $pesan['teks'] = 'Nama wajib diisi (maksimal 60 karakter).';
        } elseif (!$lokasiValid) {
            $pesan['teks'] = 'Pilih lokasi parkir yang valid.';
        } elseif ($rating < 1 || $rating > 5) {
            $pesan['teks'] = 'Pilih rating 1 sampai 5 bintang.';
        } elseif (mb_strlen($komentar) < 5 || mb_strlen($komentar) > 500) {
            $pesan['teks'] = 'Komentar harus 5–500 karakter.';
        } else {
            try {
                $ins = $db->prepare("INSERT INTO ulasan (lokasi_id, nama, rating, komentar) VALUES (?, ?, ?, ?)");
                $ins->execute([$lokasiId, $nama, $rating, $komentar]);
                $_SESSION['ulasan_terakhir'] = time();
                $pesan = ['tipe' => 'ok', 'teks' => 'Terima kasih! Ulasan Anda sudah tampil.'];
            } catch (PDOException $e) {
                $pesan['teks'] = 'Ulasan gagal disimpan, coba lagi nanti.';
            }
        }
    }

    $_SESSION['flash_ulasan'] = $pesan;
    header('Location: index.php#ulasan');
    exit;
}

// ===== Pencarian lokasi =====
$keyword = clean($_GET['q'] ?? '');
if ($keyword !== '') {
    $stmt = $db->prepare("SELECT * FROM lokasi_parkir WHERE status='aktif' AND (nama_lokasi LIKE ? OR alamat LIKE ?) ORDER BY id ASC");
    $like = "%$keyword%";
    $stmt->execute([$like, $like]);
} else {
    $stmt = $db->query("SELECT * FROM lokasi_parkir WHERE status='aktif' ORDER BY id ASC");
}
$lokasiList  = $stmt->fetchAll();
$totalLokasi = count($lokasiList);

// ===== Statistik nyata dari database =====
$statRow = $db->query("SELECT COUNT(*) AS n, COALESCE(SUM(kapasitas),0) AS kap, COALESCE(SUM(slot_tersedia),0) AS sisa FROM lokasi_parkir WHERE status='aktif'")->fetch();
$statLokasi    = (int)$statRow['n'];
$statKapasitas = (int)$statRow['kap'];
$statSisa      = (int)$statRow['sisa'];

// ===== Rating (aman jika tabel ulasan belum ada) =====
$ratingPerLokasi = [];
$ulasanTerbaru = [];
$ratingRataRataGlobal = null;
$totalUlasan = 0;
$distribusi = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
try {
    foreach ($db->query("SELECT lokasi_id, ROUND(AVG(rating),1) AS rata_rata, COUNT(*) AS jumlah FROM ulasan GROUP BY lokasi_id")->fetchAll() as $row) {
        $ratingPerLokasi[$row['lokasi_id']] = $row;
    }
    $ulasanTerbaru = $db->query("SELECT u.*, l.nama_lokasi FROM ulasan u JOIN lokasi_parkir l ON l.id = u.lokasi_id ORDER BY u.created_at DESC LIMIT 6")->fetchAll();

    $g = $db->query("SELECT ROUND(AVG(rating),1) AS rata_rata, COUNT(*) AS jumlah FROM ulasan")->fetch();
    if ($g && $g['jumlah'] > 0) {
        $ratingRataRataGlobal = $g['rata_rata'];
        $totalUlasan = (int)$g['jumlah'];
    }
    foreach ($db->query("SELECT rating, COUNT(*) AS n FROM ulasan GROUP BY rating")->fetchAll() as $d) {
        if (isset($distribusi[(int)$d['rating']])) { $distribusi[(int)$d['rating']] = (int)$d['n']; }
    }
} catch (PDOException $e) {
    // Tabel ulasan belum ada — bagian rating menampilkan pesan default.
}

// ===== Helper tampilan =====
if (!function_exists('bintangIkon')) {
    function bintangIkon($rating, $ukuran = 18) {
        $rating = (int)round((float)$rating);
        $html = '';
        for ($i = 1; $i <= 5; $i++) {
            $fill = $i <= $rating ? 1 : 0;
            $html .= '<span class="material-symbols-outlined text-[' . $ukuran . 'px]" style="font-variation-settings: \'FILL\' ' . $fill . ';">star</span>';
        }
        return $html;
    }
}
if (!function_exists('waktuLalu')) {
    function waktuLalu($tanggal) {
        $selisih = time() - strtotime($tanggal);
        if ($selisih < 3600)   return 'Baru saja';
        if ($selisih < 86400)  return floor($selisih / 3600) . ' jam lalu';
        if ($selisih < 604800) return floor($selisih / 86400) . ' hari lalu';
        if ($selisih < 2592000) return floor($selisih / 604800) . ' minggu lalu';
        return date('d M Y', strtotime($tanggal));
    }
}
$warnaAvatar = ['bg-primary-container', 'bg-secondary', 'bg-tertiary'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>TobaPark — Pesan Parkir Kawasan Danau Toba</title>
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif:ital,wght@0,400..700;1,400..700&family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config={darkMode:"class",theme:{extend:{"colors":{"on-tertiary-container":"#f27274","surface":"#f6faf7","on-error":"#ffffff","on-secondary-fixed-variant":"#673d00","primary-fixed":"#c1eaea","on-secondary-fixed":"#2b1700","on-primary-container":"#7aa2a1","tertiary-container":"#680816","error":"#ba1a1a","surface-dim":"#d7dbd8","on-primary-fixed":"#002020","inverse-on-surface":"#edf2ee","secondary-fixed":"#ffddba","inverse-primary":"#a5cecd","secondary-fixed-dim":"#ffb866","on-background":"#181d1b","on-primary":"#ffffff","on-tertiary-fixed-variant":"#852128","surface-variant":"#dfe4e0","on-secondary-container":"#724400","error-container":"#ffdad6","outline-variant":"#c0c8c7","tertiary-fixed":"#ffdad8","surface-tint":"#3e6565","on-surface-variant":"#414848","outline":"#717978","primary-container":"#0d3838","background":"#f6faf7","surface-container-low":"#f0f5f1","on-tertiary-fixed":"#410008","on-error-container":"#93000a","secondary-container":"#fdb257","inverse-surface":"#2c312f","surface-container-high":"#e5e9e6","surface-bright":"#f6faf7","surface-container-highest":"#dfe4e0","secondary":"#875200","on-surface":"#181d1b","surface-container-lowest":"#ffffff","surface-container":"#eaefeb","on-secondary":"#ffffff","on-tertiary":"#ffffff","tertiary-fixed-dim":"#ffb3b1","primary-fixed-dim":"#a5cecd","on-primary-fixed-variant":"#254d4d","primary":"#002222","tertiary":"#430008"},"borderRadius":{"DEFAULT":"0.25rem","lg":"0.5rem","xl":"0.75rem","full":"9999px"},"spacing":{"space-sm":"0.5rem","space-lg":"1.75rem","space-xl":"2.5rem","margin":"3rem","gutter":"1.5rem","gutter-mobile":"1rem","space-md":"1rem","margin-mobile":"1.25rem","space-xs":"0.25rem"},"fontFamily":{"display-lg-mobile":["Noto Serif"],"label-lg":["Plus Jakarta Sans"],"body-md":["Plus Jakarta Sans"],"label-sm":["Plus Jakarta Sans"],"headline-md":["Noto Serif"],"label-md":["Plus Jakarta Sans"],"body-sm":["Plus Jakarta Sans"],"headline-sm":["Noto Serif"],"headline-lg":["Noto Serif"],"body-lg":["Plus Jakarta Sans"],"headline-lg-mobile":["Noto Serif"],"display-lg":["Noto Serif"]},"fontSize":{"display-lg-mobile":["36px",{"lineHeight":"42px","letterSpacing":"-0.015em","fontWeight":"600"}],"label-lg":["14px",{"lineHeight":"18px","letterSpacing":"0.02em","fontWeight":"600"}],"body-md":["15px",{"lineHeight":"24px","fontWeight":"400"}],"label-sm":["11px",{"lineHeight":"14px","letterSpacing":"0.08em","fontWeight":"700"}],"headline-md":["26px",{"lineHeight":"34px","fontWeight":"600"}],"label-md":["12px",{"lineHeight":"16px","letterSpacing":"0.05em","fontWeight":"600"}],"body-sm":["13px",{"lineHeight":"20px","fontWeight":"400"}],"headline-sm":["20px",{"lineHeight":"28px","fontWeight":"500"}],"headline-lg":["38px",{"lineHeight":"46px","letterSpacing":"-0.01em","fontWeight":"600"}],"body-lg":["18px",{"lineHeight":"28px","fontWeight":"400"}],"headline-lg-mobile":["28px",{"lineHeight":"34px","letterSpacing":"-0.01em","fontWeight":"600"}],"display-lg":["52px",{"lineHeight":"60px","letterSpacing":"-0.02em","fontWeight":"600"}]}}}}
</script>
<style>
  html { scroll-behavior: smooth; }
  body { margin: 0; overscroll-behavior: none; }
  section[id] { scroll-margin-top: 7rem; }
  /* Pemilih bintang pada form ulasan (tanpa JS) */
  .star-input { display: inline-flex; flex-direction: row-reverse; justify-content: flex-end; gap: 4px; position: relative; }
  .star-input input { position: absolute; opacity: 0; pointer-events: none; }
  .star-input label { cursor: pointer; color: #c0c8c7; transition: color .15s, transform .15s; line-height: 1; }
  .star-input label .material-symbols-outlined { font-size: 32px; font-variation-settings: 'FILL' 1; }
  .star-input label:hover { transform: scale(1.15); }
  .star-input label:hover, .star-input label:hover ~ label, .star-input input:checked ~ label { color: #f59e0b; }
  .star-input input:focus-visible + label { outline: 2px solid #0d3838; border-radius: 6px; }
  .hp-field { position: absolute; left: -9999px; height: 0; overflow: hidden; }
  .faq-item.open .faq-content { display: block; }
  .faq-item.open .faq-icon { transform: rotate(180deg); }
  @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: none; } }
  .fade-up { animation: fadeUp .7s cubic-bezier(.22,1,.36,1) both; }
  @media (prefers-reduced-motion: reduce) { * { animation: none !important; transition: none !important; scroll-behavior: auto !important; } }
</style>
</head>
<body class="bg-surface font-body-md text-on-surface antialiased selection:bg-secondary-fixed selection:text-on-secondary-fixed">

<!-- ================= HEADER ================= -->
<header class="fixed top-0 left-0 right-0 z-50 bg-surface/90 backdrop-blur-xl shadow-[0_1px_8px_rgba(0,0,0,0.04)]">
  <div class="bg-primary-container text-on-primary-container px-gutter py-space-xs">
    <div class="w-full flex flex-wrap items-center justify-between gap-space-sm">
      <div class="flex items-center gap-space-sm">
        <span class="flex h-2 w-2 relative">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-secondary-container opacity-75"></span>
          <span class="relative inline-flex rounded-full h-2 w-2 bg-secondary-container"></span>
        </span>
        <span class="font-label-sm text-label-sm uppercase tracking-wider text-surface-container-lowest">Live: <?= $statSisa ?> slot tersedia di <?= $statLokasi ?> lokasi</span>
      </div>
      <div class="hidden sm:flex items-center gap-space-xs font-label-sm text-label-sm text-surface-container-lowest">
        <span class="material-symbols-outlined text-[14px]">support_agent</span>
        <span>Bantuan WhatsApp 07.00–21.00 WIB</span>
      </div>
    </div>
  </div>
  <div class="h-20 w-full px-gutter flex items-center justify-between gap-space-md">
    <div class="flex items-center gap-space-lg">
      <a class="flex items-center gap-space-sm group" href="index.php">
        <div class="w-10 h-10 rounded-lg bg-primary-container flex items-center justify-center shadow-[0_4px_20px_-2px_rgba(13,56,56,0.15)] group-hover:bg-primary transition-colors">
          <span class="material-symbols-outlined text-secondary-container text-[24px]">anchor</span>
        </div>
        <div class="flex flex-col">
          <span class="font-headline-sm text-headline-sm text-primary tracking-tight leading-none">TobaPark</span>
          <span class="font-label-sm text-[10px] text-secondary tracking-widest uppercase mt-0.5">Parkir Kawasan Danau Toba</span>
        </div>
      </a>
      <nav class="hidden lg:flex items-center gap-space-md">
        <a class="text-primary font-bold bg-surface-container px-space-sm py-space-xs rounded-lg font-label-md text-label-md" href="index.php">Beranda</a>
        <a class="text-on-surface-variant font-label-md text-label-md hover:text-on-surface transition-colors" href="#katalog-parkir">Cari Lokasi</a>
        <a class="text-on-surface-variant font-label-md text-label-md hover:text-on-surface transition-colors" href="#panduan">Panduan Wisata</a>
        <a class="text-on-surface-variant font-label-md text-label-md hover:text-on-surface transition-colors" href="#ulasan">Testimoni</a>
        <a class="text-on-surface-variant font-label-md text-label-md hover:text-on-surface transition-colors" href="#bantuan">Bantuan/FAQ</a>
      </nav>
    </div>
    <div class="flex items-center gap-space-sm">
      <a class="flex items-center gap-space-xs text-primary hover:bg-surface-container font-label-md text-label-md px-space-md py-space-sm rounded-lg border border-outline-variant transition-all" href="<?= $urlLoginStaff ?>">
        <span class="material-symbols-outlined text-[18px]">badge</span>
        <span class="hidden sm:inline">Login Staf</span>
      </a>
      <a class="flex items-center gap-space-xs bg-secondary-container hover:bg-secondary text-on-secondary-container hover:text-on-secondary font-label-md text-label-md px-space-md py-space-sm rounded-lg transition-all shadow-[0_4px_20px_-2px_rgba(13,56,56,0.05)]" href="#katalog-parkir">
        <span class="material-symbols-outlined text-[18px]">local_parking</span>
        <span>Pesan Slot</span>
      </a>
    </div>
  </div>
  <div class="h-1 w-full bg-gradient-to-r from-primary-container via-secondary-container to-tertiary-container opacity-90"></div>
</header>

<main class="w-full pt-20 bg-surface">

<!-- ================= HERO ================= -->
<section class="relative w-full overflow-hidden bg-primary-container -mt-20 pt-20" id="cari">
  <div class="absolute inset-0 z-0">
    <div class="w-full h-full bg-cover bg-center scale-105" style="background-image:url('assets/img/parapat.jpg')"></div>
    <div class="absolute inset-0 bg-gradient-to-r from-primary/95 via-primary-container/85 to-primary/75 mix-blend-multiply"></div>
    <div class="absolute inset-0 bg-gradient-to-t from-surface via-transparent to-primary/40"></div>
  </div>

  <div class="relative z-10 w-full px-gutter pt-space-xl pb-space-xl flex flex-col items-center">
    <div class="fade-up inline-flex items-center gap-space-sm bg-surface/15 backdrop-blur-md px-space-md py-space-xs rounded-full shadow-sm mb-space-md">
      <span class="relative flex h-2.5 w-2.5">
        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-secondary-container opacity-75"></span>
        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-secondary-container"></span>
      </span>
      <span class="font-label-sm text-label-sm text-surface tracking-wider uppercase"><?= $statLokasi ?> lokasi aktif • <?= $statSisa ?> slot masih tersedia hari ini</span>
    </div>

    <div class="fade-up max-w-4xl text-center flex flex-col items-center mb-space-lg">
      <span class="font-label-md text-label-md text-secondary-container tracking-widest uppercase mb-space-xs font-bold">Kawasan Wisata Danau Toba — Geopark Kaldera UNESCO</span>
      <h1 class="font-headline-lg-mobile md:font-display-lg text-headline-lg-mobile md:text-display-lg text-surface tracking-tight leading-tight">
        Tempat parkir Anda sudah menunggu, <span class="italic font-normal text-secondary-container">jauh sebelum Anda tiba.</span>
      </h1>
      <p class="mt-space-sm font-body-lg text-body-lg text-surface-container-high/90 max-w-2xl text-center">
        Pesan slot parkir di titik wisata favorit sekitar Danau Toba dari rumah. Datang, tunjukkan bukti pesanan, langsung parkir — tanpa muter-muter dan tanpa antre di gerbang.
      </p>
    </div>

    <!-- Kotak pencarian -->
    <div class="fade-up w-full max-w-4xl bg-surface-container-lowest/95 backdrop-blur-xl rounded-xl shadow-xl p-space-md md:p-space-lg">
      <div class="flex flex-wrap items-center justify-between gap-space-sm pb-space-sm mb-space-md">
        <div class="flex items-center gap-space-xs bg-surface-container p-1 rounded-lg">
          <button type="button" id="btn-mobil" onclick="selectVehicle('mobil')" class="flex items-center gap-space-xs px-space-md py-space-xs rounded-md bg-primary-container text-surface font-label-md text-label-md transition-all">
            <span class="material-symbols-outlined text-[18px]">directions_car</span><span>Mobil</span>
          </button>
          <button type="button" id="btn-motor" onclick="selectVehicle('motor')" class="flex items-center gap-space-xs px-space-md py-space-xs rounded-md text-on-surface-variant hover:text-on-surface font-label-md text-label-md transition-all">
            <span class="material-symbols-outlined text-[18px]">two_wheeler</span><span>Sepeda Motor</span>
          </button>
        </div>
        <div class="flex items-center gap-space-xs text-on-surface-variant font-label-sm text-label-sm">
          <span class="material-symbols-outlined text-secondary text-[18px]">sell</span>
          <span>Pilih jenis kendaraan untuk melihat tarif</span>
        </div>
      </div>

      <form method="GET" action="index.php#katalog-parkir" class="grid grid-cols-1 md:grid-cols-12 gap-space-md items-end">
        <div class="md:col-span-9 flex flex-col gap-space-xs">
          <label class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider flex items-center gap-space-xs" for="q">
            <span class="material-symbols-outlined text-[14px] text-primary">location_on</span> Lokasi Dermaga / Wisata
          </label>
          <input class="w-full bg-surface-container-low text-on-surface font-label-lg text-label-lg px-space-md py-space-sm rounded-lg focus:outline-none focus:bg-surface focus:ring-2 focus:ring-secondary-container transition-colors" id="q" name="q" type="text" placeholder="Parapat, Ajibata, Tomok, Tuktuk..." value="<?= $keyword ?>"/>
        </div>
        <div class="md:col-span-3">
          <button type="submit" class="w-full h-11 bg-secondary-container hover:bg-secondary text-on-secondary-container hover:text-on-secondary font-label-lg text-label-lg rounded-lg shadow-md hover:shadow-lg flex items-center justify-center gap-space-sm transition-all">
            <span class="material-symbols-outlined text-[20px]">search</span><span>Cari Slot Parkir</span>
          </button>
        </div>
      </form>

      <div class="flex flex-wrap items-center gap-space-xs mt-space-md">
        <span class="font-label-sm text-label-sm text-on-surface-variant mr-1">Rute Populer:</span>
        <?php foreach (['Ajibata', 'Parapat', 'Tomok', 'Tuktuk', 'Ambarita'] as $chip): ?>
          <a href="index.php?q=<?= urlencode($chip) ?>#katalog-parkir" class="bg-surface-container hover:bg-surface-container-high text-on-surface px-space-sm py-0.5 rounded text-[12px] font-medium transition-colors"><?= $chip ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Statistik nyata dari database -->
    <div class="w-full max-w-4xl grid grid-cols-2 md:grid-cols-4 gap-space-md mt-space-lg">
      <div class="bg-primary/60 backdrop-blur-md p-space-md rounded-xl flex items-center gap-space-md">
        <div class="w-10 h-10 rounded-lg bg-secondary-container/20 flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-secondary-container text-[22px]">hub</span></div>
        <div><div class="font-headline-sm text-headline-sm text-surface leading-tight font-bold"><?= $statLokasi ?> Titik</div><div class="font-label-sm text-label-sm text-surface-container-high">Lokasi Parkir Aktif</div></div>
      </div>
      <div class="bg-primary/60 backdrop-blur-md p-space-md rounded-xl flex items-center gap-space-md">
        <div class="w-10 h-10 rounded-lg bg-secondary-container/20 flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-secondary-container text-[22px]">local_parking</span></div>
        <div><div class="font-headline-sm text-headline-sm text-surface leading-tight font-bold"><?= number_format($statKapasitas, 0, ',', '.') ?></div><div class="font-label-sm text-label-sm text-surface-container-high">Total Kapasitas Slot</div></div>
      </div>
      <div class="bg-primary/60 backdrop-blur-md p-space-md rounded-xl flex items-center gap-space-md">
        <div class="w-10 h-10 rounded-lg bg-secondary-container/20 flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-secondary-container text-[22px]">star</span></div>
        <div>
          <div class="font-headline-sm text-headline-sm text-surface leading-tight font-bold"><?= $ratingRataRataGlobal !== null ? $ratingRataRataGlobal . ' / 5.0' : '—' ?></div>
          <div class="font-label-sm text-label-sm text-surface-container-high"><?= $totalUlasan > 0 ? $totalUlasan . ' Ulasan Pengguna' : 'Belum ada ulasan' ?></div>
        </div>
      </div>
      <div class="bg-primary/60 backdrop-blur-md p-space-md rounded-xl flex items-center gap-space-md">
        <div class="w-10 h-10 rounded-lg bg-secondary-container/20 flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-secondary-container text-[22px]">price_check</span></div>
        <div><div class="font-headline-sm text-headline-sm text-surface leading-tight font-bold">Tarif Flat</div><div class="font-label-sm text-label-sm text-surface-container-high">Sekali Bayar, Tanpa Calo</div></div>
      </div>
    </div>
  </div>
</section>

<!-- ================= 3 LANGKAH ================= -->
<section class="w-full px-gutter py-space-xl bg-surface">
  <div class="max-w-7xl mx-auto flex flex-col items-center">
    <div class="text-center max-w-2xl mb-space-xl">
      <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Alur Layanan Cepat</span>
      <h2 class="font-headline-lg text-headline-lg text-primary mt-space-xs">Tiga Langkah Parkir Nyaman di Danau Toba</h2>
      <p class="font-body-md text-body-md text-on-surface-variant mt-space-xs">Dirancang agar Anda bisa langsung menikmati tepian danau tanpa rasa was-was.</p>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-space-lg w-full">
      <?php
      $langkah = [
        ['01', 'bg-primary-container', 'bg-primary-fixed/30', 'Cari & Pilih Titik Parkir', 'Ketik nama tempat tujuan Anda dan lihat slot yang masih tersedia hari itu.', 'radar', 'Slot tersedia tampil langsung'],
        ['02', 'bg-secondary', 'bg-secondary-fixed/30', 'Pesan & Bayar Flat', 'Pilih jenis kendaraan dan perkiraan jam datang, bayar sekali, lalu parkir sepuasnya.', 'lock', 'Slot dikunci atas nama Anda'],
        ['03', 'bg-tertiary', 'bg-tertiary-fixed/30', 'Datang & Parkir', 'Tunjukkan bukti pesanan kepada petugas di lokasi, kendaraan langsung masuk tanpa antre.', 'qr_code_scanner', 'Masuk tanpa antre di gerbang'],
      ];
      foreach ($langkah as $s): ?>
      <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
        <div class="absolute top-0 right-0 w-24 h-24 <?= $s[2] ?> rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
        <div class="w-12 h-12 rounded-lg <?= $s[1] ?> text-surface flex items-center justify-center font-headline-sm text-headline-sm mb-space-md shadow-sm relative"><?= $s[0] ?></div>
        <h3 class="font-headline-sm text-headline-sm text-primary mb-space-xs"><?= $s[3] ?></h3>
        <p class="font-body-md text-body-md text-on-surface-variant"><?= $s[4] ?></p>
        <div class="mt-space-md flex items-center gap-space-xs font-label-sm text-label-sm text-secondary font-bold">
          <span class="material-symbols-outlined text-[16px]"><?= $s[5] ?></span><span><?= $s[6] ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ================= KATALOG LOKASI (DATABASE) ================= -->
<section class="w-full px-gutter py-space-xl bg-surface-container-low" id="katalog-parkir">
  <div class="max-w-7xl mx-auto flex flex-col">
    <div class="mb-space-lg">
      <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Katalog Lokasi</span>
      <h2 class="font-headline-lg text-headline-lg text-primary mt-space-xs"><?= $keyword !== '' ? 'Hasil pencarian' : 'Lokasi Parkir Tersedia' ?></h2>
      <p class="font-body-md text-body-md text-on-surface-variant mt-space-xs">
        <?php if ($keyword !== ''): ?>
          Menampilkan <?= $totalLokasi ?> lokasi yang cocok dengan &ldquo;<?= $keyword ?>&rdquo; · <a class="text-secondary font-bold hover:underline" href="index.php#katalog-parkir">Tampilkan semua</a>
        <?php else: ?>
          Tarif flat sekali bayar, parkir bebas tanpa batas jam.
        <?php endif; ?>
      </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-space-lg">
      <?php if (empty($lokasiList)): ?>
        <p class="col-span-full text-center p-space-xl bg-surface-container-lowest rounded-xl text-on-surface-variant">Belum ada lokasi parkir yang cocok.</p>
      <?php else: ?>
        <?php foreach ($lokasiList as $i => $lokasi):
          $r = $ratingPerLokasi[$lokasi['id']] ?? null;
          $kap = max(1, (int)$lokasi['kapasitas']);
          $sisa = (int)$lokasi['slot_tersedia'];
          $persen = min(100, max(0, (int)round($sisa / $kap * 100)));
          if ($persen >= 40)      { $warna = 'bg-emerald-600'; $status = 'Ruang Luas';   $statusCls = 'text-emerald-700'; }
          elseif ($persen >= 15)  { $warna = 'bg-amber-600';   $status = 'Cepat Penuh';  $statusCls = 'text-amber-700'; }
          else                    { $warna = 'bg-red-600';     $status = 'Hampir Penuh'; $statusCls = 'text-red-700'; }
          if ($sisa <= 0)         { $status = 'Penuh'; }
        ?>
        <div class="bg-surface-container-lowest rounded-xl shadow-sm hover:shadow-lg hover:-translate-y-1 transition-all duration-300 flex flex-col overflow-hidden">
          <div class="relative w-full h-52 overflow-hidden bg-gradient-to-br <?= $i % 2 ? 'from-secondary-container to-secondary' : 'from-primary-container to-primary' ?>">
            <div class="absolute inset-0 flex items-center justify-center text-surface/70"><span class="material-symbols-outlined text-[56px]">local_parking</span></div>
            <img class="relative w-full h-full object-cover transition-transform duration-500 hover:scale-105" src="assets/img/<?= clean($lokasi['gambar']) ?>" alt="Foto lokasi parkir <?= clean($lokasi['nama_lokasi']) ?>" loading="lazy" onerror="this.style.display='none'"/>
            <div class="absolute top-space-sm right-space-sm bg-surface-container-lowest/90 backdrop-blur-md text-primary font-label-sm text-label-sm px-space-sm py-1 rounded-md font-bold flex items-center gap-1">
              <span class="material-symbols-outlined text-amber-500 text-[14px]" style="font-variation-settings:'FILL' 1;">star</span>
              <span><?= $r ? $r['rata_rata'] . ' (' . (int)$r['jumlah'] . ' ulasan)' : 'Belum ada ulasan' ?></span>
            </div>
            <div class="absolute bottom-0 inset-x-0 bg-gradient-to-t from-primary/80 to-transparent p-space-sm text-surface font-label-sm text-label-sm flex items-center gap-1">
              <span class="material-symbols-outlined text-[14px] text-secondary-container">location_on</span><span class="truncate"><?= clean($lokasi['alamat']) ?></span>
            </div>
          </div>

          <div class="p-space-md flex-1 flex flex-col justify-between">
            <div>
              <h3 class="font-headline-sm text-headline-sm text-primary"><?= clean($lokasi['nama_lokasi']) ?></h3>
              <p class="font-body-sm text-body-sm text-on-surface-variant mt-1"><?= clean(mb_strimwidth($lokasi['deskripsi'], 0, 110, '...')) ?></p>

              <div class="mt-space-md p-space-sm bg-surface-container rounded-lg">
                <div class="flex items-center justify-between font-label-sm text-label-sm mb-1.5">
                  <span class="text-on-surface-variant">Slot Tersedia</span>
                  <span class="font-bold text-primary"><?= $sisa ?> / <?= (int)$lokasi['kapasitas'] ?></span>
                </div>
                <div class="w-full bg-surface-variant h-2 rounded-full overflow-hidden">
                  <div class="<?= $warna ?> h-full rounded-full" style="width: <?= $persen ?>%;"></div>
                </div>
                <div class="text-right text-[11px] mt-1 font-semibold <?= $statusCls ?>"><?= $status ?></div>
              </div>
            </div>

            <div class="mt-space-lg bg-surface-container-low -mx-space-md -mb-space-md p-space-md flex items-center justify-between">
              <div>
                <span class="font-label-sm text-label-sm text-on-surface-variant block">Tarif Flat · bebas jam</span>
                <div class="font-headline-sm text-headline-sm text-primary font-bold">
                  <span class="harga-val" data-mobil="<?= clean(formatRupiah($lokasi['harga_mobil'])) ?>" data-motor="<?= clean(formatRupiah($lokasi['harga_motor'])) ?>"><?= clean(formatRupiah($lokasi['harga_mobil'])) ?></span>
                  <span class="harga-unit font-label-sm text-label-sm font-normal text-on-surface-variant">/ Mobil</span>
                </div>
              </div>
              <a href="detail_lokasi.php?id=<?= (int)$lokasi['id'] ?>" class="bg-primary hover:bg-primary-container text-on-primary px-space-md py-space-xs rounded-lg font-label-md text-label-md flex items-center gap-1 shadow-sm transition-all">
                <span>Lihat &amp; Pesan</span><span class="material-symbols-outlined text-[16px]">arrow_forward</span>
              </a>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ================= PANDUAN WISATA ================= -->
<section class="w-full px-gutter py-space-xl bg-surface" id="panduan">
  <div class="max-w-7xl mx-auto flex flex-col">
    <div class="mb-space-lg">
      <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Panduan Wisatawan Cerdas</span>
      <h2 class="font-headline-lg text-headline-lg text-primary mt-space-xs">Mengenal Kawasan &amp; Tips Parkir Danau Toba</h2>
      <p class="font-body-md text-body-md text-on-surface-variant mt-space-xs">Danau vulkanik terbesar di dunia, dengan Pulau Samosir sebagai pusat budaya Batak di tengahnya.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-12 gap-space-md">
      <div class="md:col-span-7 bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between">
        <div>
          <div class="flex items-center gap-space-xs text-secondary font-label-sm text-label-sm font-bold uppercase mb-space-xs">
            <span class="material-symbols-outlined text-[16px]">directions_boat</span><span>Akses Feri &amp; Titik Populer</span>
          </div>
          <h3 class="font-headline-md text-headline-md text-primary mb-space-sm">Pilih Parkir yang Dekat Pelabuhan Feri</h3>
          <p class="font-body-md text-body-md text-on-surface-variant">
            Parapat, Tomok, Tuktuk, Ambarita, dan Pusuk Buhit adalah destinasi favorit pengunjung. Jika berencana menyeberang ke Samosir, <strong>pilih lokasi parkir yang dekat dengan pelabuhan feri Ajibata atau Tomok</strong> agar perpindahan ke kapal lebih singkat.
          </p>
        </div>
        <div class="mt-space-md grid grid-cols-1 sm:grid-cols-2 gap-space-sm bg-surface-container-low p-space-sm rounded-lg">
          <div class="flex items-center gap-space-xs text-on-surface-variant font-label-md text-label-md"><span class="material-symbols-outlined text-secondary text-[20px]">event</span>Akhir pekan &amp; libur nasional adalah waktu tersibuk</div>
          <div class="flex items-center gap-space-xs text-on-surface-variant font-label-md text-label-md"><span class="material-symbols-outlined text-secondary text-[20px]">schedule</span>Pesan minimal sehari sebelum berangkat</div>
        </div>
      </div>

      <div class="md:col-span-5 bg-primary-container text-surface p-space-lg rounded-xl shadow-sm flex flex-col justify-between">
        <div>
          <div class="flex items-center gap-space-xs text-secondary-container font-label-sm text-label-sm font-bold uppercase mb-space-xs">
            <span class="material-symbols-outlined text-[16px]">health_and_safety</span><span>Tips Parkir</span>
          </div>
          <h3 class="font-headline-md text-headline-md text-surface mb-space-sm">Sebelum Anda Berangkat</h3>
          <ul class="flex flex-col gap-space-sm font-body-sm text-body-sm text-surface-container-high">
            <?php foreach ([
              'Datang 15–30 menit lebih awal dari jam yang Anda pilih untuk menghindari antrean di gerbang.',
              'Simpan bukti pesanan (screenshot atau cetak) untuk ditunjukkan ke petugas lokasi.',
              'Periksa jenis kendaraan yang didaftarkan — tarif motor dan mobil berbeda di setiap lokasi.',
            ] as $tip): ?>
            <li class="flex items-start gap-space-xs">
              <span class="material-symbols-outlined text-secondary-container text-[18px] shrink-0">check_circle</span><span><?= $tip ?></span>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ================= RATING & ULASAN ================= -->
<section class="w-full px-gutter py-space-xl bg-surface-container-low" id="ulasan">
  <div class="max-w-7xl mx-auto flex flex-col">
    <div class="text-center max-w-2xl mx-auto mb-space-xl">
      <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Transparansi Layanan</span>
      <h2 class="font-headline-lg text-headline-lg text-primary mt-space-xs">Kata Mereka yang Sudah Parkir di Sini</h2>
      <p class="font-body-md text-body-md text-on-surface-variant mt-space-xs">Ulasan nyata dari pengunjung. Siapa pun boleh menulis, tanpa perlu login.</p>
    </div>

    <?php if ($flashUlasan): ?>
      <div class="max-w-3xl mx-auto w-full mb-space-lg p-space-md rounded-lg font-label-md text-label-md flex items-center gap-space-xs <?= $flashUlasan['tipe'] === 'ok' ? 'bg-emerald-100 text-emerald-900' : 'bg-error-container text-on-error-container' ?>" role="status">
        <span class="material-symbols-outlined"><?= $flashUlasan['tipe'] === 'ok' ? 'check_circle' : 'error' ?></span>
        <span><?= clean($flashUlasan['teks']) ?></span>
      </div>
    <?php endif; ?>

    <?php if ($ratingRataRataGlobal !== null): ?>
    <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm mb-space-lg grid grid-cols-1 md:grid-cols-12 gap-space-lg items-center">
      <div class="md:col-span-4 flex flex-col items-center justify-center text-center p-space-md">
        <span class="font-display-lg text-display-lg text-primary font-bold leading-none"><?= $ratingRataRataGlobal ?></span>
        <div class="flex items-center gap-1 text-amber-500 my-2"><?= bintangIkon($ratingRataRataGlobal, 24) ?></div>
        <span class="font-label-md text-label-md text-on-surface-variant">Berdasarkan <?= $totalUlasan ?> ulasan</span>
      </div>
      <div class="md:col-span-8 flex flex-col gap-2">
        <?php foreach ($distribusi as $bintang => $n): $pct = $totalUlasan > 0 ? round($n / $totalUlasan * 100) : 0; ?>
        <div class="flex items-center gap-space-sm text-on-surface-variant font-label-sm text-label-sm">
          <span class="w-16"><?= $bintang ?> Bintang</span>
          <div class="flex-1 bg-surface-container-high h-2.5 rounded-full overflow-hidden"><div class="bg-amber-500 h-full rounded-full" style="width: <?= $pct ?>%;"></div></div>
          <span class="w-12 text-right font-bold text-on-surface"><?= $pct ?>%</span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-space-md mb-space-xl">
      <?php if (empty($ulasanTerbaru)): ?>
        <p class="col-span-full text-center p-space-xl bg-surface-container-lowest rounded-xl text-on-surface-variant">Belum ada ulasan. Jadilah yang pertama memberi rating!</p>
      <?php else: foreach ($ulasanTerbaru as $k => $u): ?>
        <div class="bg-surface-container-lowest p-space-md rounded-xl shadow-sm hover:shadow-md transition-shadow flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between mb-space-sm">
              <div class="flex items-center gap-1 text-amber-500"><?= bintangIkon($u['rating'], 18) ?></div>
              <span class="font-label-sm text-[11px] text-on-surface-variant"><?= clean(waktuLalu($u['created_at'])) ?></span>
            </div>
            <p class="font-body-sm text-body-sm text-on-surface italic">&ldquo;<?= clean($u['komentar']) ?>&rdquo;</p>
          </div>
          <div class="flex items-center gap-space-sm mt-space-md pt-space-xs">
            <div class="w-9 h-9 rounded-full <?= $warnaAvatar[$k % 3] ?> text-surface flex items-center justify-center font-bold font-label-md text-label-md"><?= clean(mb_strtoupper(mb_substr($u['nama'], 0, 2))) ?></div>
            <div>
              <div class="font-label-md text-label-md font-bold text-primary"><?= clean($u['nama']) ?></div>
              <div class="font-body-sm text-[11px] text-on-surface-variant"><?= clean($u['nama_lokasi']) ?></div>
            </div>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>

    <!-- FORM ULASAN PUBLIK (tanpa login) -->
    <div class="bg-surface-container-lowest rounded-xl shadow-md p-space-lg max-w-3xl mx-auto w-full relative overflow-hidden">
      <div class="h-1.5 w-full bg-gradient-to-r from-tertiary via-secondary to-primary-container absolute top-0 left-0 right-0"></div>
      <div class="flex items-center justify-between mb-space-md pt-space-xs">
        <div>
          <h3 class="font-headline-sm text-headline-sm text-primary">Bagikan Pengalaman Parkir Anda</h3>
          <p class="font-body-sm text-body-sm text-on-surface-variant">Tidak perlu login atau daftar. Cukup isi form di bawah.</p>
        </div>
        <div class="w-10 h-10 rounded-lg bg-surface-container flex items-center justify-center text-secondary"><span class="material-symbols-outlined text-[24px]">rate_review</span></div>
      </div>

      <form method="POST" action="index.php#ulasan" class="flex flex-col gap-space-md">
        <input type="hidden" name="aksi" value="kirim_ulasan">
        <input type="hidden" name="csrf" value="<?= $_SESSION['csrf_ulasan'] ?>">
        <div class="hp-field" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md">
          <div>
            <label class="font-label-sm text-label-sm text-on-surface-variant block mb-1" for="ul-nama">Nama Anda</label>
            <input class="w-full bg-surface-container-low text-on-surface font-label-md text-label-md px-space-md py-space-sm rounded-lg focus:outline-none focus:bg-surface focus:ring-2 focus:ring-secondary-container" id="ul-nama" name="nama" maxlength="60" required placeholder="Contoh: Daniel Tampubolon" type="text"/>
          </div>
          <div>
            <label class="font-label-sm text-label-sm text-on-surface-variant block mb-1" for="ul-lokasi">Lokasi Parkir</label>
            <select class="w-full bg-surface-container-low text-on-surface font-label-md text-label-md px-space-md py-space-sm rounded-lg focus:outline-none focus:bg-surface focus:ring-2 focus:ring-secondary-container cursor-pointer" id="ul-lokasi" name="lokasi_id" required>
              <option value="">— Pilih lokasi —</option>
              <?php foreach ($semuaLokasi as $l): ?>
                <option value="<?= (int)$l['id'] ?>"><?= clean($l['nama_lokasi']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div>
          <span class="font-label-sm text-label-sm text-on-surface-variant block mb-1">Beri Penilaian Bintang</span>
          <div class="star-input">
            <?php for ($n = 5; $n >= 1; $n--): ?>
              <input type="radio" id="star<?= $n ?>" name="rating" value="<?= $n ?>" <?= $n === 5 ? 'required' : '' ?>>
              <label for="star<?= $n ?>" title="<?= $n ?> bintang"><span class="material-symbols-outlined">star</span></label>
            <?php endfor; ?>
          </div>
        </div>

        <div>
          <label class="font-label-sm text-label-sm text-on-surface-variant block mb-1" for="ul-komentar">Tulis Ulasan &amp; Kesan</label>
          <textarea class="w-full bg-surface-container-low text-on-surface font-body-sm text-body-sm p-space-md rounded-lg focus:outline-none focus:bg-surface focus:ring-2 focus:ring-secondary-container" id="ul-komentar" name="komentar" maxlength="500" required rows="3" placeholder="Bagikan kesan soal kebersihan, keamanan, pelayanan petugas, atau kemudahan pembayaran..."></textarea>
        </div>

        <div class="flex items-center justify-end pt-space-xs">
          <button type="submit" class="bg-primary hover:bg-primary-container text-on-primary px-space-lg py-space-sm rounded-lg font-label-md text-label-md shadow-sm flex items-center gap-space-xs transition-all">
            <span class="material-symbols-outlined text-[18px]">send</span><span>Kirim Ulasan Saya</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</section>

<!-- ================= FAQ & BANTUAN ================= -->
<section class="w-full px-gutter py-space-xl bg-surface" id="bantuan">
  <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-space-xl">
    <div class="lg:col-span-7 flex flex-col">
      <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Pusat Pertanyaan</span>
      <h2 class="font-headline-lg text-headline-lg text-primary mt-space-xs mb-space-lg">Pertanyaan Sering Diajukan</h2>
      <div class="flex flex-col gap-space-sm">
        <?php foreach ([
          ['Bagaimana cara memesan slot parkir?', 'Cari lokasi tujuan Anda di kotak pencarian, buka halaman detail lokasi, pilih jenis kendaraan dan jam kedatangan, lalu selesaikan pembayaran. Bukti pesanan akan muncul setelah pembayaran berhasil.'],
          ['Apakah pesanan bisa dibatalkan atau diubah jadwalnya?', 'Pembatalan dan perubahan jadwal dapat dilakukan selama slot belum digunakan. Hubungi layanan bantuan kami melalui WhatsApp atau email untuk dibantu prosesnya.'],
          ['Bagaimana jika slot yang saya pesan ternyata sudah terisi?', 'Setiap slot yang sudah dibayar dikunci atas nama Anda dan tidak dapat dipesan orang lain. Jika terjadi kendala di lokasi, segera hubungi kontak bantuan agar dapat ditindaklanjuti.'],
          ['Metode pembayaran apa saja yang tersedia?', 'Pembayaran dapat dilakukan melalui transfer bank, e-wallet, dan QRIS. Pilihan metode akan muncul pada saat proses checkout di halaman detail lokasi.'],
          ['Bagaimana cara memberi rating setelah parkir?', 'Buka bagian "Kata Mereka yang Sudah Parkir di Sini" di halaman ini, isi nama, pilih lokasi dan bintang, lalu kirim. Tidak perlu login.'],
        ] as $faq): ?>
        <div class="faq-item bg-surface-container-lowest rounded-xl overflow-hidden shadow-sm">
          <button type="button" class="faq-btn w-full p-space-md flex items-center justify-between gap-space-sm text-left font-headline-sm text-headline-sm text-primary hover:text-secondary transition-colors">
            <span><?= $faq[0] ?></span>
            <span class="material-symbols-outlined text-[22px] transition-transform faq-icon shrink-0">expand_more</span>
          </button>
          <div class="faq-content hidden px-space-md pb-space-md text-on-surface-variant font-body-md text-body-md"><?= $faq[1] ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="lg:col-span-5 flex flex-col gap-space-md">
      <div class="bg-surface-container p-space-lg rounded-xl shadow-sm">
        <div class="flex items-center gap-space-sm mb-space-md">
          <div class="w-10 h-10 rounded-lg bg-secondary-container text-on-secondary-container flex items-center justify-center"><span class="material-symbols-outlined text-[24px]">support_agent</span></div>
          <div>
            <h3 class="font-headline-sm text-headline-sm text-primary">Butuh Bantuan?</h3>
            <span class="font-label-sm text-label-sm text-secondary font-bold">Setiap hari, 07.00–21.00 WIB</span>
          </div>
        </div>
        <div class="flex flex-col gap-space-sm">
          <a class="flex items-center justify-between p-space-md bg-surface-container-lowest hover:bg-surface-variant rounded-lg transition-colors group" href="https://wa.me/628816675849" target="_blank" rel="noopener">
            <div class="flex items-center gap-space-sm">
              <span class="material-symbols-outlined text-emerald-600 text-[24px]">chat</span>
              <div><span class="font-label-md text-label-md font-bold text-on-surface block">WhatsApp</span><span class="font-body-sm text-[12px] text-on-surface-variant">+62 881-6675-849</span></div>
            </div>
            <span class="material-symbols-outlined text-outline group-hover:text-primary transition-colors">arrow_forward</span>
          </a>
          <a class="flex items-center justify-between p-space-md bg-surface-container-lowest hover:bg-surface-variant rounded-lg transition-colors group" href="mailto:bantuan@parkirtobaa.id">
            <div class="flex items-center gap-space-sm">
              <span class="material-symbols-outlined text-primary text-[24px]">mail</span>
              <div><span class="font-label-md text-label-md font-bold text-on-surface block">Email</span><span class="font-body-sm text-[12px] text-on-surface-variant">bantuan@parkirtobaa.id</span></div>
            </div>
            <span class="material-symbols-outlined text-outline group-hover:text-primary transition-colors">arrow_forward</span>
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ================= CTA ================= -->
<section class="w-full px-gutter py-space-xl bg-surface-container-lowest">
  <div class="max-w-7xl mx-auto rounded-xl bg-primary text-surface p-space-lg md:p-space-xl relative overflow-hidden shadow-xl">
    <div class="absolute -right-20 -bottom-20 w-80 h-80 bg-secondary-container/20 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute left-1/4 -top-20 w-60 h-60 bg-primary-fixed/10 rounded-full blur-2xl pointer-events-none"></div>
    <div class="relative z-10 flex flex-col lg:flex-row items-center justify-between gap-space-lg">
      <div class="max-w-2xl">
        <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary-container font-bold">Rencana ke Danau Toba sudah dekat?</span>
        <h2 class="font-headline-lg text-headline-lg text-surface mt-space-xs">Siap Menikmati Pesona Pulau Samosir?</h2>
        <p class="font-body-md text-body-md text-surface-container-high mt-space-xs">Cari lokasi parkir di kawasan tujuan Anda dan amankan slotnya sekarang.</p>
      </div>
      <div class="flex flex-col sm:flex-row items-center gap-space-sm shrink-0 w-full lg:w-auto">
        <a href="#cari" class="w-full sm:w-auto bg-secondary-container hover:bg-secondary text-on-secondary-container hover:text-on-secondary font-label-lg text-label-lg px-space-lg py-space-md rounded-lg shadow-md hover:shadow-lg flex items-center justify-center gap-space-xs transition-all">
          <span class="material-symbols-outlined text-[20px]">local_parking</span><span>Cari Lokasi Parkir</span>
        </a>
        <a href="https://wa.me/628816675849" target="_blank" rel="noopener" class="w-full sm:w-auto bg-surface/10 hover:bg-surface/20 text-surface font-label-lg text-label-lg px-space-lg py-space-md rounded-lg flex items-center justify-center gap-space-xs transition-all">
          <span class="material-symbols-outlined text-[20px]">chat</span><span>Tanya dulu via WhatsApp</span>
        </a>
      </div>
    </div>
  </div>
</section>

<!-- Tombol WhatsApp mengambang -->
<a aria-label="Bantuan via WhatsApp" class="fixed bottom-6 right-6 z-40 bg-emerald-700 hover:bg-emerald-800 text-surface p-space-sm sm:px-space-md sm:py-space-sm rounded-full shadow-xl flex items-center gap-space-xs transition-all hover:scale-105" href="https://wa.me/628816675849" target="_blank" rel="noopener">
  <span class="material-symbols-outlined text-[24px]">chat</span>
  <span class="hidden sm:inline font-label-md text-label-md font-bold">Bantuan via WhatsApp</span>
</a>

</main>

<!-- ================= FOOTER ================= -->
<footer class="w-full bg-surface-container-low text-on-surface">
  <div class="h-2 w-full bg-gradient-to-r from-tertiary via-secondary to-primary-container"></div>
  <div class="w-full px-gutter py-space-xl">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-space-xl">
      <div class="flex flex-col gap-space-md">
        <div class="flex items-center gap-space-sm">
          <div class="w-9 h-9 rounded-lg bg-primary-container flex items-center justify-center"><span class="material-symbols-outlined text-secondary-container text-[20px]">anchor</span></div>
          <span class="font-headline-sm text-headline-sm text-primary">TobaPark</span>
        </div>
        <p class="font-body-md text-body-md text-on-surface-variant max-w-md">Sistem reservasi tempat parkir di titik-titik wisata kawasan Danau Toba. Pesan dari rumah, datang, langsung parkir.</p>
      </div>
      <div>
        <h4 class="font-headline-sm text-headline-sm text-primary mb-space-md">Navigasi</h4>
        <ul class="flex flex-col gap-space-sm font-label-md text-label-md text-on-surface-variant">
          <li><a class="hover:text-on-surface transition-colors" href="index.php">Beranda</a></li>
          <li><a class="hover:text-on-surface transition-colors" href="#katalog-parkir">Cari Lokasi</a></li>
          <li><a class="hover:text-on-surface transition-colors" href="#panduan">Panduan Wisata</a></li>
          <li><a class="hover:text-on-surface transition-colors" href="#ulasan">Ulasan Pengunjung</a></li>
          <li><a class="hover:text-on-surface transition-colors" href="#bantuan">Bantuan/FAQ</a></li>
          <li><a class="hover:text-on-surface transition-colors inline-flex items-center gap-1" href="<?= $urlLoginStaff ?>"><span class="material-symbols-outlined text-[16px]">badge</span>Login Staf</a></li>
        </ul>
      </div>
      <div>
        <h4 class="font-headline-sm text-headline-sm text-primary mb-space-md">Metode Pembayaran</h4>
        <div class="flex flex-wrap gap-space-xs">
          <?php foreach (['QRIS', 'Transfer Bank', 'E-Wallet'] as $m): ?>
            <span class="bg-surface-container-highest text-on-surface font-label-sm text-label-sm px-space-sm py-space-xs rounded font-bold"><?= $m ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <div class="mt-space-xl pt-space-md flex flex-col md:flex-row items-center justify-between gap-space-md bg-surface-container px-space-md py-space-sm rounded-lg">
      <span class="text-on-surface-variant font-body-sm text-body-sm">© <?= date('Y') ?> TobaPark. Seluruh hak dilindungi.</span>
    </div>
  </div>
</footer>

<script>
// Toggle jenis kendaraan: mengganti tarif yang tampil di semua kartu
var CLS_AKTIF = 'flex items-center gap-space-xs px-space-md py-space-xs rounded-md bg-primary-container text-surface font-label-md text-label-md transition-all';
var CLS_NONAKTIF = 'flex items-center gap-space-xs px-space-md py-space-xs rounded-md text-on-surface-variant hover:text-on-surface font-label-md text-label-md transition-all';
function selectVehicle(tipe) {
  document.getElementById('btn-mobil').className = tipe === 'mobil' ? CLS_AKTIF : CLS_NONAKTIF;
  document.getElementById('btn-motor').className = tipe === 'motor' ? CLS_AKTIF : CLS_NONAKTIF;
  document.querySelectorAll('.harga-val').forEach(function (el) { el.textContent = el.dataset[tipe]; });
  document.querySelectorAll('.harga-unit').forEach(function (el) { el.textContent = tipe === 'mobil' ? '/ Mobil' : '/ Motor'; });
}

// Akordeon FAQ
document.querySelectorAll('.faq-btn').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var item = btn.closest('.faq-item');
    var buka = item.classList.contains('open');
    document.querySelectorAll('.faq-item').forEach(function (i) {
      i.classList.remove('open');
      i.querySelector('.faq-content').classList.add('hidden');
    });
    if (!buka) {
      item.classList.add('open');
      item.querySelector('.faq-content').classList.remove('hidden');
    }
  });
});
</script>
</body>
</html>