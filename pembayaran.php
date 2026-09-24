<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$db = getDB();
$booking_id = (int)($_GET['booking_id'] ?? 0);

$stmt = $db->prepare("SELECT b.*, l.nama_lokasi FROM booking b
    JOIN lokasi_parkir l ON l.id = b.lokasi_id
    WHERE b.id = ? AND b.user_id = ?");
$stmt->execute([$booking_id, $_SESSION['id_user']]);
$booking = $stmt->fetch();

if (!$booking) {
    redirectWithMessage('riwayat.php', 'Data booking tidak ditemukan.', 'danger');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $booking['status'] === 'pending') {
    $metode = clean($_POST['metode_pembayaran'] ?? '');
    if (!in_array($metode, ['transfer_bank', 'e_wallet', 'qris'])) {
        $errors[] = "Pilih metode pembayaran yang valid.";
    }

    $buktiPath = null;
    if (!empty($_FILES['bukti_transfer']['name'])) {
        $ext = strtolower(pathinfo($_FILES['bukti_transfer']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
            $fileName = 'bukti_' . $booking['kode_booking'] . '_' . time() . '.' . $ext;
            $target = __DIR__ . '/uploads/bukti_bayar/' . $fileName;
            if (move_uploaded_file($_FILES['bukti_transfer']['tmp_name'], $target)) {
                $buktiPath = 'uploads/bukti_bayar/' . $fileName;
            }
        } else {
            $errors[] = "Format bukti transfer harus jpg, jpeg, png, atau pdf.";
        }
    }

    if (empty($errors)) {
        $stmt = $db->prepare("INSERT INTO pembayaran (booking_id, metode_pembayaran, jumlah_bayar, bukti_transfer, status)
            VALUES (?, ?, ?, ?, 'menunggu')");
        $stmt->execute([$booking_id, $metode, $booking['total_harga'], $buktiPath]);

        $stmt = $db->prepare("UPDATE booking SET status = 'dibayar' WHERE id = ?");
        $stmt->execute([$booking_id]);

        $_SESSION['notif_sound'] = 'notif';
        redirectWithMessage('riwayat.php', 'Pembayaran berhasil dikirim. Menunggu konfirmasi admin.', 'success');
    } else {
        $_SESSION['notif_sound'] = 'error';
    }
}

$pageTitle = "Pembayaran";
require_once __DIR__ . '/includes/header.php';
?>

<style>
.info-pembayaran {
    background:#F7F4EC; border:1px solid rgba(15,44,51,0.12); border-radius:12px;
    padding:1.1rem 1.3rem; margin:1rem 0 1.4rem; font-size:0.92rem;
}
.info-pembayaran h3 { margin:0 0 0.7rem; font-size:0.95rem; color:#0E2A31; }
.info-pembayaran .baris-rekening {
    display:flex; justify-content:space-between; align-items:center;
    background:#fff; border-radius:8px; padding:0.6rem 0.9rem; margin-bottom:0.5rem;
    border:1px solid rgba(15,44,51,0.08); flex-wrap:wrap; gap:0.5rem;
}
.info-pembayaran .baris-rekening button {
    border:none; background:#0E2A31; color:#fff; font-size:0.78rem; font-weight:600;
    padding:0.35rem 0.7rem; border-radius:6px; cursor:pointer;
}
.info-pembayaran .baris-rekening button:hover { background:#153B44; }
.info-pembayaran img.qris-img {
    display:block; max-width:220px; margin:0.5rem auto; border-radius:10px; border:1px solid rgba(15,44,51,0.1);
}
.info-pembayaran .catatan { color:#71807F; font-size:0.82rem; margin-top:0.6rem; }
</style>

<section class="form-container">
    <h1>Pembayaran Booking</h1>

    <?php foreach ($errors as $e): ?>
        <div class="alert alert-danger"><?= clean($e) ?></div>
    <?php endforeach; ?>

    <div class="ringkasan-box">
        <p><strong>Kode Booking:</strong> <?= clean($booking['kode_booking']) ?></p>
        <p><strong>Lokasi:</strong> <?= clean($booking['nama_lokasi']) ?></p>
        <p><strong>Plat Nomor:</strong> <?= clean($booking['plat_nomor']) ?></p>
        <p><strong>Jenis Kendaraan:</strong> <?= clean(ucfirst($booking['jenis_kendaraan'])) ?></p>
        <p><strong>Tanggal:</strong> <?= clean($booking['tanggal_booking']) ?> perkiraan datang pukul <?= clean(substr($booking['jam_masuk'],0,5)) ?></p>
        <p><strong>Total Bayar:</strong> <span class="total-highlight"><?= formatRupiah($booking['total_harga']) ?></span> <small>(sekali bayar, parkir bebas tanpa batas jam)</small></p>
        <p><strong>Status:</strong> <span class="badge badge-<?= clean($booking['status']) ?>"><?= clean(ucfirst($booking['status'])) ?></span></p>
    </div>

    <?php if ($booking['status'] === 'pending'): ?>
        <form method="POST" enctype="multipart/form-data" class="form-box">
            <label>Metode Pembayaran</label>
            <select name="metode_pembayaran" id="metode_pembayaran" required>
                <option value="transfer_bank">Transfer Bank</option>
                <option value="e_wallet">E-Wallet (OVO/DANA/GoPay)</option>
                <option value="qris">QRIS</option>
            </select>

            <!-- Info Transfer Bank -->
            <div class="info-pembayaran" id="info-transfer_bank">
                <h3>Transfer ke rekening berikut</h3>
                <div class="baris-rekening">
                    <span><strong>BCA</strong> — 1234567890 a.n. Parkir Danau Toba</span>
                    <button type="button" onclick="salinTeks('1234567890')">Salin</button>
                </div>
                <div class="baris-rekening">
                    <span><strong>Mandiri</strong> — 0987654321 a.n. Parkir Danau Toba</span>
                    <button type="button" onclick="salinTeks('0987654321')">Salin</button>
                </div>
                <p class="catatan">Transfer sesuai jumlah <strong><?= formatRupiah($booking['total_harga']) ?></strong>, lalu upload bukti transfer di bawah.</p>
            </div>

            <!-- Info E-Wallet -->
            <div class="info-pembayaran" id="info-e_wallet" style="display:none;">
                <h3>Kirim ke nomor e-wallet berikut</h3>
                <div class="baris-rekening">
                    <span><strong>DANA / OVO / GoPay</strong> — 0812-3456-7890 a.n. Parkir Danau Toba</span>
                    <button type="button" onclick="salinTeks('081234567890')">Salin</button>
                </div>
                <p class="catatan">Kirim sesuai jumlah <strong><?= formatRupiah($booking['total_harga']) ?></strong>, lalu upload bukti transfer di bawah.</p>
            </div>

            <!-- Info QRIS -->
            <div class="info-pembayaran" id="info-qris" style="display:none;">
                <h3>Scan QRIS berikut</h3>
                <img src="assets/img/qris-danau-toba.png" alt="QRIS Parkir Danau Toba" class="qris-img">
                <p class="catatan" style="text-align:center;">Total: <strong><?= formatRupiah($booking['total_harga']) ?></strong> — lalu upload bukti pembayaran di bawah.</p>
            </div>

            <label>Upload Bukti Pembayaran (opsional)</label>
            <input type="file" name="bukti_transfer" accept=".jpg,.jpeg,.png,.pdf">

            <button type="submit" class="btn-primary">Konfirmasi Pembayaran</button>
        </form>
    <?php else: ?>
        <p class="alert alert-success">Pembayaran sudah diproses untuk booking ini.</p>
        <a href="riwayat.php" class="btn-secondary">Lihat Riwayat Saya</a>
    <?php endif; ?>
</section>

<script>
const selectMetode = document.getElementById('metode_pembayaran');
const semuaInfo = ['transfer_bank', 'e_wallet', 'qris'];

function tampilkanInfo() {
    semuaInfo.forEach(m => {
        document.getElementById('info-' + m).style.display = (m === selectMetode.value) ? 'block' : 'none';
    });
}
selectMetode.addEventListener('change', tampilkanInfo);
tampilkanInfo(); // jalankan sekali saat halaman dimuat

function salinTeks(teks) {
    navigator.clipboard.writeText(teks).then(() => {
        alert('Nomor berhasil disalin: ' + teks);
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>