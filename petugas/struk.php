<?php
session_start();
require_once "../config/database.php";
$pdo = getDB();

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'petugas') {
    header("Location: login.php");
    exit;
}
$lokasiPetugas = (int)($_SESSION['lokasi_id'] ?? 0);

$id_parkir = $_GET['id'] ?? '';

$stmt = $pdo->prepare("SELECT t.*, k.plat_nomor, k.jenis_kendaraan, k.pemilik,
                               a.nama_area, a.lokasi_id, tr.tarif_flat,
                               l.nama_lokasi
                        FROM tb_transaksi t
                        JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
                        JOIN tb_area_parkir a ON t.id_area = a.id_area
                        JOIN tb_tarif tr ON t.id_tarif = tr.id_tarif
                        JOIN lokasi_parkir l ON a.lokasi_id = l.id
                        WHERE t.id_parkir = ?");
$stmt->execute([$id_parkir]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die("Struk tidak ditemukan.");
}
if ((int)$data['lokasi_id'] !== $lokasiPetugas) {
    die("Anda tidak memiliki akses ke struk transaksi ini.");
}

$noKarcis = "PRK-" . date("Ymd", strtotime($data['waktu_masuk'])) . "-" . str_pad($data['id_parkir'], 4, "0", STR_PAD_LEFT);
$petugas  = $_SESSION['nama_lengkap'] ?? 'Petugas';

// Durasi parkir (informasi saja, tidak memengaruhi biaya karena tarif flat)
$detikDurasi = strtotime($data['waktu_keluar']) - strtotime($data['waktu_masuk']);
$jamDurasi   = floor($detikDurasi / 3600);
$menitDurasi = floor(($detikDurasi % 3600) / 60);
$durasiText  = ($jamDurasi > 0 ? $jamDurasi . " jam " : "") . $menitDurasi . " menit";

$metode = ($data['metode_bayar'] ?? 'tunai') === 'qris' ? 'QRIS' : 'Tunai';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Struk Parkir — <?= htmlspecialchars($data['nama_lokasi']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Fraunces:wght@500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --lake-deep:#0E2A31; --lake-mid:#153B44; --gold:#D9A441; --gold-dim:#B78530;
  --paper:#F7F4EC; --card:#FFFFFF; --ink:#16262A; --muted:#71807F; --line:rgba(15,44,51,0.14);
  --green:#2E7D4F;
  --font-display:"Fraunces",Georgia,serif; --font-body:"Inter",-apple-system,BlinkMacSystemFont,sans-serif;
}
* { box-sizing:border-box; }
body {
  font-family:var(--font-body); background:var(--paper); color:var(--ink);
  margin:0; padding:2rem 1rem; display:flex; justify-content:center;
}
.struk {
  width:100%; max-width:380px; background:var(--card); border-radius:16px;
  box-shadow:0 2px 14px rgba(15,44,51,0.10); overflow:hidden;
}
.kepala {
  background:var(--lake-deep); color:#fff; text-align:center; padding:1.4rem 1.2rem 1.1rem;
}
.kepala h1 { font-family:var(--font-display); font-size:1.2rem; margin:0 0 0.2rem; font-weight:600; }
.kepala .lokasi-area { font-size:0.78rem; color:#C7D6D3; }
.kepala .badge {
  display:inline-block; margin-top:0.7rem; background:var(--green); color:#fff;
  font-weight:700; font-size:0.72rem; letter-spacing:0.04em; padding:0.28rem 0.75rem; border-radius:99px;
}

.isi { padding:1.3rem 1.4rem 0.4rem; }
.baris { display:flex; justify-content:space-between; gap:0.8rem; font-size:0.87rem; padding:0.32rem 0; }
.baris .label { color:var(--muted); }
.baris .nilai { font-weight:600; text-align:right; }
.garis { border:none; border-top:1px dashed var(--line); margin:0.7rem 0; }

.rincian-biaya { padding:0 0 0.3rem; }
.rincian-biaya .baris.total { padding-top:0.6rem; border-top:1px dashed var(--line); margin-top:0.3rem; }
.rincian-biaya .baris.total .label { color:var(--lake-deep); font-weight:700; }
.rincian-biaya .baris.total .nilai { font-family:var(--font-display); font-size:1.3rem; color:var(--lake-deep); }

.status-lunas {
  text-align:center; margin:0.9rem 0 0.2rem;
}
.status-lunas span {
  display:inline-block; background:#E6F3EA; color:var(--green); font-weight:700; font-size:0.8rem;
  letter-spacing:0.04em; padding:0.35rem 1.1rem; border-radius:99px;
}

.terimakasih {
  text-align:center; padding:1.2rem 1.4rem 1.5rem; border-top:1px dashed var(--line); margin-top:0.9rem;
}
.terimakasih .judul { font-family:var(--font-display); font-size:1.05rem; color:var(--lake-deep); margin-bottom:0.35rem; font-weight:600; }
.terimakasih p { font-size:0.82rem; color:var(--muted); margin:0; line-height:1.65; }
.terimakasih .tanda { margin-top:0.7rem; font-size:0.78rem; color:var(--gold-dim); font-weight:600; }

.aksi { padding:0 1.4rem 1.5rem; display:flex; gap:0.6rem; }
button.cetak {
  flex:1; border:none; padding:0.8rem; border-radius:10px; background:var(--gold); color:var(--lake-deep);
  font-weight:700; font-size:0.88rem; cursor:pointer; font-family:var(--font-body);
}
button.cetak:hover { background:var(--gold-dim); }
a.kembali {
  flex:1; display:flex; align-items:center; justify-content:center; border:1.5px solid var(--line); border-radius:10px;
  color:var(--lake-deep); text-decoration:none; font-size:0.88rem; font-weight:600;
}

@media print {
  body { padding:0; background:#fff; }
  .struk { max-width:100%; box-shadow:none; border-radius:0; }
  .aksi { display:none; }
}
</style>
</head>
<body>

<div class="struk">
    <div class="kepala">
        <h1><?= htmlspecialchars($data['nama_lokasi']) ?></h1>
        <div class="lokasi-area">Struk Pembayaran Parkir &mdash; <?= htmlspecialchars($data['nama_area']) ?></div>
        <div class="badge">TRANSAKSI SELESAI</div>
    </div>

    <div class="isi">
        <div class="baris"><span class="label">No. Karcis</span><span class="nilai"><?= htmlspecialchars($noKarcis) ?></span></div>
        <div class="baris"><span class="label">Plat Nomor</span><span class="nilai"><?= htmlspecialchars($data['plat_nomor']) ?></span></div>
        <div class="baris"><span class="label">Jenis</span><span class="nilai"><?= htmlspecialchars($data['jenis_kendaraan']) ?></span></div>
        <?php if (!empty($data['pemilik'])): ?>
        <div class="baris"><span class="label">Pemilik</span><span class="nilai"><?= htmlspecialchars($data['pemilik']) ?></span></div>
        <?php endif; ?>
        <div class="baris"><span class="label">Area Parkir</span><span class="nilai"><?= htmlspecialchars($data['nama_area']) ?></span></div>
        <div class="baris"><span class="label">Tarif</span><span class="nilai">Flat, sekali bayar</span></div>
        <div class="baris"><span class="label">Waktu Masuk</span><span class="nilai"><?= date("d/m/Y H:i:s", strtotime($data['waktu_masuk'])) ?></span></div>
        <div class="baris"><span class="label">Waktu Keluar</span><span class="nilai"><?= date("d/m/Y H:i:s", strtotime($data['waktu_keluar'])) ?></span></div>
        <div class="baris"><span class="label">Durasi Parkir</span><span class="nilai"><?= $durasiText ?></span></div>
        <div class="baris"><span class="label">Petugas</span><span class="nilai"><?= htmlspecialchars($petugas) ?></span></div>

        <div class="rincian-biaya">
            <hr class="garis">
            <div class="baris"><span class="label">Biaya Parkir</span><span class="nilai">Rp<?= number_format($data['tarif_flat'],0,',','.') ?></span></div>
            <div class="baris"><span class="label">Metode Bayar</span><span class="nilai"><?= $metode ?></span></div>
            <div class="baris total"><span class="label">Total Bayar</span><span class="nilai">Rp<?= number_format($data['biaya_total'],0,',','.') ?></span></div>
        </div>

        <div class="status-lunas"><span>&#10003; LUNAS</span></div>
    </div>

    <div class="terimakasih">
        <div class="judul">Terima Kasih</div>
        <p>Terima kasih telah parkir bersama kami di <?= htmlspecialchars($data['nama_lokasi']) ?>.<br>Semoga perjalanan Anda menyenangkan dan sampai jumpa lagi.</p>
        <div class="tanda">&mdash; <?= htmlspecialchars($data['nama_lokasi']) ?> &mdash;</div>
    </div>

    <div class="aksi">
        <button class="cetak" onclick="window.print()">Cetak Struk</button>
        <a href="dashboard.php" class="kembali">Kembali</a>
    </div>
</div>

</body>
</html>