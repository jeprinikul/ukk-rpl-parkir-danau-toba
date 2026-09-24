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

if ($id_parkir === '' || !$lokasiPetugas) {
    $_SESSION['pesan'] = ['tipe' => 'gagal', 'teks' => 'Data transaksi tidak valid.'];
    header("Location: dashboard.php");
    exit;
}

// Ambil data transaksi yang masih aktif (status masuk), pastikan milik lokasi tugas petugas
$stmt = $pdo->prepare("SELECT t.*, tr.tarif_flat, k.plat_nomor, k.jenis_kendaraan, a.nama_area, a.lokasi_id
                        FROM tb_transaksi t
                        JOIN tb_tarif tr ON t.id_tarif = tr.id_tarif
                        JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
                        JOIN tb_area_parkir a ON t.id_area = a.id_area
                        WHERE t.id_parkir = ? AND t.status = 'masuk'");
$stmt->execute([$id_parkir]);
$transaksi = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaksi || (int)$transaksi['lokasi_id'] !== $lokasiPetugas) {
    $_SESSION['pesan'] = ['tipe' => 'gagal', 'teks' => 'Transaksi tidak ditemukan atau bukan milik lokasi tugas Anda.'];
    header("Location: dashboard.php");
    exit;
}

// Tarif FLAT: sekali bayar, tidak bergantung lama parkir
$biayaPreview = $transaksi['tarif_flat'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Konfirmasi Pembayaran — Parkir Danau Toba</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Fraunces:wght@500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --lake-deep:#0E2A31; --lake-mid:#153B44;
  --gold:#D9A441; --gold-dim:#B78530;
  --paper:#F7F4EC; --card:#FFFFFF; --ink:#16262A; --muted:#71807F; --line:rgba(15,44,51,0.10);
  --green:#2E7D4F;
  --font-display:"Fraunces",Georgia,serif; --font-body:"Inter",-apple-system,BlinkMacSystemFont,sans-serif;
}
* { box-sizing:border-box; }
body {
  font-family:var(--font-body); background:var(--paper); color:var(--ink);
  margin:0; padding:2rem 1rem; display:flex; justify-content:center;
}
.kotak {
  width:100%; max-width:460px; background:var(--card); border:1px solid var(--line); border-radius:16px;
  padding:1.8rem; box-shadow:0 2px 10px rgba(15,44,51,0.06);
}
h1 { font-family:var(--font-display); font-size:1.3rem; margin:0 0 0.3rem; color:var(--lake-deep); }
.sub { color:var(--muted); font-size:0.87rem; margin-bottom:1.3rem; }

.ringkasan { background:var(--paper); border-radius:12px; padding:1rem 1.2rem; font-size:0.88rem; margin-bottom:1.4rem; }
.ringkasan div { display:flex; justify-content:space-between; padding:0.28rem 0; }
.ringkasan .label { color:var(--muted); }
.ringkasan .total { border-top:1px dashed var(--line); margin-top:0.4rem; padding-top:0.6rem; font-weight:700; font-size:1.05rem; color:var(--lake-deep); }

.pilihan-metode { display:flex; gap:0.7rem; margin-bottom:1.2rem; }
.pilihan-metode button {
  flex:1; padding:0.75rem; border-radius:10px; border:1.5px solid var(--line); background:#fff;
  font-family:var(--font-body); font-weight:600; font-size:0.9rem; color:var(--lake-deep); cursor:pointer;
  transition:all 0.15s ease;
}
.pilihan-metode button.aktif { border-color:var(--gold); background:#FBF3DF; color:var(--gold-dim); }
.pilihan-metode button:hover { border-color:var(--gold); }

.panel-metode { display:none; }
.panel-metode.tampil { display:block; }

.qris-box { text-align:center; padding:1rem 0 0.3rem; }
.qris-box img { max-width:230px; width:100%; border-radius:10px; border:1px solid var(--line); }
.qris-box .catatan { font-size:0.82rem; color:var(--muted); margin-top:0.7rem; }
.qris-box .catatan strong { color:var(--lake-deep); }

button.btn-konfirmasi {
  width:100%; border:none; padding:0.85rem; border-radius:10px; margin-top:0.9rem;
  background:var(--gold); color:var(--lake-deep); font-weight:700; font-size:0.92rem; cursor:pointer;
}
button.btn-konfirmasi:hover { background:var(--gold-dim); }

a.batal { display:block; text-align:center; margin-top:1rem; font-size:0.85rem; color:var(--muted); text-decoration:none; }
a.batal:hover { color:var(--lake-deep); }
</style>
</head>
<body>

<div class="kotak">
    <h1>Konfirmasi Pembayaran</h1>
    <div class="sub">Pastikan metode bayar sebelum transaksi diselesaikan.</div>

    <div class="ringkasan">
        <div><span class="label">Plat Nomor</span><span><?= htmlspecialchars($transaksi['plat_nomor']) ?></span></div>
        <div><span class="label">Jenis</span><span><?= htmlspecialchars($transaksi['jenis_kendaraan']) ?></span></div>
        <div><span class="label">Area</span><span><?= htmlspecialchars($transaksi['nama_area']) ?></span></div>
        <div><span class="label">Tarif</span><span>Flat, sekali bayar</span></div>
        <div class="total"><span>Total Bayar</span><span>Rp<?= number_format($biayaPreview,0,',','.') ?></span></div>
    </div>

    <div class="pilihan-metode">
        <button type="button" id="btn-tunai" class="aktif" onclick="pilihMetode('tunai')">💵 Tunai</button>
        <button type="button" id="btn-qris" onclick="pilihMetode('qris')">📱 QRIS</button>
    </div>

    <!-- Form ini yang benar-benar dikirim untuk menyelesaikan transaksi -->
    <form action="proses_keluar.php" method="POST" id="form-selesai">
        <input type="hidden" name="id_parkir" value="<?= (int)$transaksi['id_parkir'] ?>">
        <input type="hidden" name="metode_bayar" id="input-metode" value="tunai">

        <div class="panel-metode tampil" id="panel-tunai">
            <p style="font-size:0.87rem;color:var(--muted);margin:0.3rem 0 0;">
                Pelanggan membayar tunai langsung di tempat. Klik tombol di bawah untuk menyelesaikan transaksi.
            </p>
        </div>

        <div class="panel-metode" id="panel-qris">
            <div class="qris-box">
                <img src="../assets/img/qris-danau-toba.png" alt="QRIS Parkir Danau Toba">
                <div class="catatan">
                    Minta pelanggan scan QRIS di atas dan bayar sejumlah<br>
                    <strong>Rp<?= number_format($biayaPreview,0,',','.') ?></strong>.
                    Setelah pembayaran diterima, klik tombol konfirmasi di bawah.
                </div>
            </div>
        </div>

        <button type="submit" class="btn-konfirmasi" id="btn-submit">Selesaikan &amp; Cetak Struk (Tunai)</button>
    </form>

    <a href="dashboard.php" class="batal">Batal, kembali ke dashboard</a>
</div>

<script>
function pilihMetode(metode) {
    document.getElementById('input-metode').value = metode;

    document.getElementById('btn-tunai').classList.toggle('aktif', metode === 'tunai');
    document.getElementById('btn-qris').classList.toggle('aktif', metode === 'qris');

    document.getElementById('panel-tunai').classList.toggle('tampil', metode === 'tunai');
    document.getElementById('panel-qris').classList.toggle('tampil', metode === 'qris');

    const btnSubmit = document.getElementById('btn-submit');
    btnSubmit.textContent = metode === 'qris'
        ? 'Sudah Dibayar via QRIS — Selesaikan & Cetak Struk'
        : 'Selesaikan & Cetak Struk (Tunai)';
}
</script>

</body>
</html>
