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

$stmt = $pdo->prepare("SELECT t.*, k.plat_nomor, k.jenis_kendaraan, k.warna, k.pemilik,
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

// Nomor karcis yang enak dibaca manusia. Yang benar-benar dipakai sistem untuk
// pencarian/verifikasi tetap id_parkir asli, itu yang dikodekan ke barcode.
$noKarcis = "PRK-" . date("Ymd", strtotime($data['waktu_masuk'])) . "-" . str_pad($data['id_parkir'], 4, "0", STR_PAD_LEFT);
$petugas  = $_SESSION['nama_lengkap'] ?? 'Petugas';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Struk Masuk — <?= htmlspecialchars($data['nama_lokasi']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Fraunces:wght@500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
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
  display:inline-block; margin-top:0.7rem; background:var(--gold); color:var(--lake-deep);
  font-weight:700; font-size:0.72rem; letter-spacing:0.04em; padding:0.28rem 0.75rem; border-radius:99px;
}

.isi { padding:1.3rem 1.4rem 0.4rem; }
.baris { display:flex; justify-content:space-between; gap:0.8rem; font-size:0.87rem; padding:0.32rem 0; }
.baris .label { color:var(--muted); }
.baris .nilai { font-weight:600; text-align:right; }
.garis { border:none; border-top:1px dashed var(--line); margin:0.7rem 0; }

.info-tarif {
  background:var(--paper); border-radius:10px; padding:0.8rem 1rem; font-size:0.82rem; color:var(--muted);
  margin:0.6rem 0 1rem; text-align:center; line-height:1.5;
}
.info-tarif strong { color:var(--lake-deep); }

.barcode-area { text-align:center; padding:0.4rem 1.4rem 1.2rem; }
.barcode-area svg { max-width:100%; }
.karcis-no { font-family:var(--font-display); font-size:0.95rem; letter-spacing:0.03em; color:var(--lake-deep); margin-top:0.2rem; }
.barcode-catatan { font-size:0.72rem; color:var(--muted); margin-top:0.4rem; }

.terimakasih {
  text-align:center; padding:1rem 1.4rem 1.5rem; border-top:1px dashed var(--line);
}
.terimakasih .judul { font-family:var(--font-display); font-size:1rem; color:var(--lake-deep); margin-bottom:0.35rem; font-weight:600; }
.terimakasih p { font-size:0.82rem; color:var(--muted); margin:0; line-height:1.6; }

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
        <div class="lokasi-area">Struk Kendaraan Masuk &mdash; <?= htmlspecialchars($data['nama_area']) ?></div>
        <div class="badge">KENDARAAN MASUK</div>
    </div>

    <div class="isi">
        <div class="baris"><span class="label">No. Karcis</span><span class="nilai"><?= htmlspecialchars($noKarcis) ?></span></div>
        <div class="baris"><span class="label">Plat Nomor</span><span class="nilai"><?= htmlspecialchars($data['plat_nomor']) ?></span></div>
        <div class="baris"><span class="label">Jenis Kendaraan</span><span class="nilai"><?= htmlspecialchars($data['jenis_kendaraan']) ?></span></div>
        <?php if (!empty($data['warna'])): ?>
        <div class="baris"><span class="label">Warna</span><span class="nilai"><?= htmlspecialchars($data['warna']) ?></span></div>
        <?php endif; ?>
        <?php if (!empty($data['pemilik'])): ?>
        <div class="baris"><span class="label">Pemilik</span><span class="nilai"><?= htmlspecialchars($data['pemilik']) ?></span></div>
        <?php endif; ?>
        <div class="baris"><span class="label">Area Parkir</span><span class="nilai"><?= htmlspecialchars($data['nama_area']) ?></span></div>
        <div class="baris"><span class="label">Waktu Masuk</span><span class="nilai"><?= date("d/m/Y H:i:s", strtotime($data['waktu_masuk'])) ?></span></div>
        <div class="baris"><span class="label">Petugas</span><span class="nilai"><?= htmlspecialchars($petugas) ?></span></div>

        <hr class="garis">

        <div class="info-tarif">
            Tarif parkir <strong>flat Rp<?= number_format($data['tarif_flat'],0,',','.') ?></strong> dibayar sekali saat kendaraan <strong>keluar</strong>, tidak dihitung per jam.
        </div>
    </div>

    <div class="barcode-area">
        <svg id="barcode"></svg>
        <div class="karcis-no"><?= htmlspecialchars($noKarcis) ?></div>
        <div class="barcode-catatan">Simpan struk ini &mdash; tunjukkan / scan barcode di atas saat kendaraan keluar</div>
    </div>

    <div class="terimakasih">
        <div class="judul">Terima Kasih</div>
        <p>Terima kasih telah mempercayakan kendaraan Anda kepada kami.<br>Selamat menikmati waktu Anda di <?= htmlspecialchars($data['nama_lokasi']) ?>.</p>
    </div>

    <div class="aksi">
        <button class="cetak" onclick="window.print()">Cetak Struk</button>
        <a href="dashboard.php" class="kembali">Kembali</a>
    </div>
</div>

<script>
JsBarcode("#barcode", "<?= (int)$data['id_parkir'] ?>", {
    format: "CODE128",
    width: 2,
    height: 55,
    displayValue: false,
    margin: 0
});
</script>

</body>
</html>