<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$db = getDB();
$booking_id = (int)($_GET['booking_id'] ?? 0);

// Ambil booking milik user ini — hanya boleh diulas kalau statusnya sudah 'selesai'
$stmt = $db->prepare("SELECT b.*, l.nama_lokasi FROM booking b
    JOIN lokasi_parkir l ON l.id = b.lokasi_id
    WHERE b.id = ? AND b.user_id = ?");
$stmt->execute([$booking_id, $_SESSION['id_user']]);
$booking = $stmt->fetch();

if (!$booking) {
    redirectWithMessage('riwayat.php', 'Data booking tidak ditemukan.', 'danger');
}

if ($booking['status'] !== 'selesai') {
    redirectWithMessage('riwayat.php', 'Ulasan hanya dapat diberikan setelah booking berstatus selesai.', 'danger');
}

// Cek apakah booking ini sudah pernah diulas sebelumnya
$stmt = $db->prepare("SELECT * FROM ulasan WHERE booking_id = ?");
$stmt->execute([$booking_id]);
$ulasanLama = $stmt->fetch();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$ulasanLama) {
    $rating = (int)($_POST['rating'] ?? 0);
    $komentar = trim($_POST['komentar'] ?? '');

    if ($rating < 1 || $rating > 5) {
        $errors[] = "Silakan pilih rating bintang 1 sampai 5.";
    }
    if ($komentar === '') {
        $errors[] = "Komentar tidak boleh kosong.";
    } elseif (mb_strlen($komentar) > 500) {
        $errors[] = "Komentar maksimal 500 karakter.";
    }

    if (empty($errors)) {
        $stmt = $db->prepare("INSERT INTO ulasan (booking_id, user_id, lokasi_id, nama, rating, komentar)
            VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $booking_id,
            $_SESSION['id_user'],
            $booking['lokasi_id'],
            $_SESSION['nama_lengkap'],
            $rating,
            $komentar
        ]);
        redirectWithMessage('riwayat.php', 'Terima kasih! Ulasan Anda berhasil dikirim.', 'success');
    }
}

$pageTitle = "Beri Ulasan";
require_once __DIR__ . '/includes/header.php';
?>

<style>
.ulasan-form-box {
    max-width: 560px; margin: 0 auto; background: #fff; border-radius: 14px;
    padding: 1.8rem; box-shadow: 0 3px 10px rgba(0,0,0,0.06);
}
.ulasan-form-box .ringkas {
    background: #eef7fa; border-radius: 10px; padding: 0.9rem 1.1rem; margin-bottom: 1.4rem; font-size: 0.9rem;
}
.ulasan-form-box .ringkas p { margin-bottom: 4px; }

/* Bintang rating: radio dibalik urutannya lewat CSS supaya hover ke kanan mengisi ke kiri */
.bintang-input {
    display: flex; flex-direction: row-reverse; justify-content: center; gap: 0.3rem; font-size: 2.4rem; margin: 0.5rem 0 1.4rem;
}
.bintang-input input { display: none; }
.bintang-input label {
    color: #d8dee1; cursor: pointer; transition: color 0.15s ease;
}
.bintang-input input:checked ~ label,
.bintang-input label:hover,
.bintang-input label:hover ~ label {
    color: #ffb238;
}

.ulasan-sudah-ada {
    max-width: 560px; margin: 0 auto; background: #fff; border-radius: 14px;
    padding: 1.8rem; box-shadow: 0 3px 10px rgba(0,0,0,0.06); text-align: center;
}
.ulasan-sudah-ada .bintang-tampil { color: #ffb238; font-size: 1.6rem; letter-spacing: 3px; margin: 0.6rem 0; }
.ulasan-sudah-ada .komentar-tampil { font-style: italic; color: #45525c; margin: 0.8rem 0 1.4rem; }
</style>

<section class="form-container">
    <h1>Beri Ulasan</h1>

    <div class="ulasan-form-box" style="margin-bottom:1.4rem;">
        <div class="ringkas">
            <p><strong>Kode Booking:</strong> <?= clean($booking['kode_booking']) ?></p>
            <p><strong>Lokasi:</strong> <?= clean($booking['nama_lokasi']) ?></p>
            <p><strong>Tanggal:</strong> <?= clean($booking['tanggal_booking']) ?></p>
        </div>
    </div>

    <?php if ($ulasanLama): ?>
        <div class="ulasan-sudah-ada">
            <p>Anda sudah memberi ulasan untuk booking ini:</p>
            <div class="bintang-tampil">
                <?php for ($i = 1; $i <= 5; $i++): ?><?= $i <= $ulasanLama['rating'] ? '★' : '☆' ?><?php endfor; ?>
            </div>
            <p class="komentar-tampil">&ldquo;<?= clean($ulasanLama['komentar']) ?>&rdquo;</p>
            <a href="riwayat.php" class="btn-secondary">Kembali ke Riwayat</a>
        </div>
    <?php else: ?>
        <?php foreach ($errors as $e): ?>
            <div class="alert alert-danger"><?= clean($e) ?></div>
        <?php endforeach; ?>

        <form method="POST" class="ulasan-form-box">
            <label style="display:block; text-align:center; font-weight:600; margin-bottom:0.4rem;">Bagaimana pengalaman parkir Anda?</label>
            <div class="bintang-input">
                <input type="radio" id="bintang5" name="rating" value="5" <?= (isset($_POST['rating']) && $_POST['rating']=='5') ? 'checked' : '' ?>><label for="bintang5">★</label>
                <input type="radio" id="bintang4" name="rating" value="4" <?= (isset($_POST['rating']) && $_POST['rating']=='4') ? 'checked' : '' ?>><label for="bintang4">★</label>
                <input type="radio" id="bintang3" name="rating" value="3" <?= (isset($_POST['rating']) && $_POST['rating']=='3') ? 'checked' : '' ?>><label for="bintang3">★</label>
                <input type="radio" id="bintang2" name="rating" value="2" <?= (isset($_POST['rating']) && $_POST['rating']=='2') ? 'checked' : '' ?>><label for="bintang2">★</label>
                <input type="radio" id="bintang1" name="rating" value="1" <?= (isset($_POST['rating']) && $_POST['rating']=='1') ? 'checked' : '' ?>><label for="bintang1">★</label>
            </div>

            <label>Ceritakan pengalaman Anda</label>
            <textarea name="komentar" rows="4" maxlength="500" placeholder="Contoh: Lokasi mudah ditemukan, petugas ramah, dan area parkir cukup luas." required><?= clean($_POST['komentar'] ?? '') ?></textarea>

            <button type="submit" class="btn-primary">Kirim Ulasan</button>
        </form>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>