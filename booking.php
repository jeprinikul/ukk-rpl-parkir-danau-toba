<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$db = getDB();
$lokasi_id = (int)($_GET['lokasi_id'] ?? $_POST['lokasi_id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM lokasi_parkir WHERE id = ? AND status = 'aktif'");
$stmt->execute([$lokasi_id]);
$lokasi = $stmt->fetch();

if (!$lokasi) {
    redirectWithMessage('index.php', 'Lokasi parkir tidak ditemukan.', 'danger');
}

if ($lokasi['slot_tersedia'] <= 0) {
    redirectWithMessage('detail_lokasi.php?id=' . $lokasi_id, 'Maaf, slot parkir di lokasi ini sudah penuh.', 'danger');
}

$pageTitle = "Booking - " . $lokasi['nama_lokasi'];
require_once __DIR__ . '/includes/header.php';
?>

<section class="form-container">
    <h1>Form Pemesanan Parkir</h1>
    <h3><?= clean($lokasi['nama_lokasi']) ?></h3>

    <form method="POST" action="proses_booking.php" class="form-box" id="formBooking">
        <input type="hidden" name="lokasi_id" value="<?= (int)$lokasi['id'] ?>">
        <input type="hidden" name="harga_motor" value="<?= (float)$lokasi['harga_motor'] ?>">
        <input type="hidden" name="harga_mobil" value="<?= (float)$lokasi['harga_mobil'] ?>">
        <input type="hidden" name="harga_bus" value="<?= (float)$lokasi['harga_bus'] ?>">

        <label>Plat Nomor Kendaraan</label>
        <input type="text" name="plat_nomor" placeholder="Contoh: BK 1234 AB" required>

        <label>Jenis Kendaraan <small>(tarif flat, sekali bayar)</small></label>
        <select name="jenis_kendaraan" id="jenisKendaraan" required>
            <option value="motor">Motor - <?= formatRupiah($lokasi['harga_motor']) ?></option>
            <option value="mobil">Mobil - <?= formatRupiah($lokasi['harga_mobil']) ?></option>
            <option value="bus">Bus - <?= formatRupiah($lokasi['harga_bus']) ?></option>
        </select>

        <label>Tanggal Parkir</label>
        <input type="date" name="tanggal_booking" min="<?= date('Y-m-d') ?>" required>

        <label>Perkiraan Jam Datang</label>
        <input type="time" name="jam_masuk" required>

        <div class="total-estimasi">
            Total Bayar (sekali bayar, parkir bebas tanpa batas jam): <strong id="totalEstimasi"><?= formatRupiah($lokasi['harga_motor']) ?></strong>
        </div>

        <button type="submit" class="btn-primary">Lanjut ke Pembayaran</button>
    </form>
</section>

<script>
const hargaMotor = <?= (float)$lokasi['harga_motor'] ?>;
const hargaMobil = <?= (float)$lokasi['harga_mobil'] ?>;
const hargaBus   = <?= (float)$lokasi['harga_bus'] ?>;

function formatRupiahJS(angka) {
    return "Rp " + angka.toLocaleString('id-ID');
}

function hitungTotal() {
    const jenis = document.getElementById('jenisKendaraan').value;
    let harga = hargaMotor;
    if (jenis === 'mobil') harga = hargaMobil;
    if (jenis === 'bus') harga = hargaBus;
    document.getElementById('totalEstimasi').innerText = formatRupiahJS(harga);
}

document.getElementById('jenisKendaraan').addEventListener('change', hitungTotal);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
