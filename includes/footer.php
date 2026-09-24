</main>
<footer class="footer">
    <p>&copy; <?= date('Y') ?> Davin Khalinabag Nur Pasa &mdash; SMK Negeri 1 Sanden. Sistem Booking &amp; Pembayaran Parkir Kawasan Wisata Danau Toba.</p>
</footer>

<?php
// Lokasi folder assets: halaman di dalam folder admin/, petugas/, atau owner/ harus naik satu tingkat.
$assetBase = preg_match('#/(admin|petugas|owner)/#', $_SERVER['SCRIPT_NAME'] ?? '') ? '../' : '';

// Ambil suara yang diminta halaman sebelumnya (login berhasil, booking berhasil, dst.),
// lalu hapus supaya tidak bunyi lagi saat halaman di-refresh.
$notifSound = '';
if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['notif_sound'])) {
    $notifSound = (string)$_SESSION['notif_sound'];
    unset($_SESSION['notif_sound']);
}
?>
<script src="<?= $assetBase ?>assets/js/notif.js"></script>
<script>
// Muat sound.js (suara klik/cetak) hanya kalau belum dimuat oleh header.
if (typeof window.playClick !== 'function') {
    var s = document.createElement('script');
    s.src = '<?= $assetBase ?>assets/js/sound.js';
    document.body.appendChild(s);
}
</script>
<?php if ($notifSound !== ''): ?>
<script>
window.addEventListener('load', function () { notifSound(<?= json_encode($notifSound) ?>); });
</script>
<?php endif; ?>
</body>
</html>