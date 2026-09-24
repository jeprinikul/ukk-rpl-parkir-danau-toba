<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = "Pusat Bantuan";
require_once __DIR__ . '/includes/header.php';
?>

<style>
.bantuan-page { --pb-lake: #0b4f6c; --pb-lake-deep: #073F47; --pb-gold: #ffb238; --pb-muted: #7a8a94; --pb-line: #e0e6e9; }

.bantuan-hero {
    background: linear-gradient(135deg, var(--pb-lake-deep), var(--pb-lake));
    color: #fff; text-align: center; padding: 3.2rem 1.5rem; border-radius: 16px; margin-bottom: 2.8rem;
}
.bantuan-hero h1 { font-size: 1.9rem; margin-bottom: 0.6rem; }
.bantuan-hero p { color: #d9edf5; max-width: 56ch; margin: 0 auto; }

.bantuan-page section { max-width: 900px; margin: 0 auto 3.2rem; }
.bantuan-page section h2 {
    text-align: center; color: var(--pb-lake); font-size: 1.4rem; margin-bottom: 1.8rem;
    position: relative; padding-bottom: 0.9rem;
}
.bantuan-page section h2::after {
    content: ""; position: absolute; bottom: 0; left: 50%; transform: translateX(-50%);
    width: 48px; height: 2px; background: var(--pb-gold);
}

/* ---- Panduan cara pakai (lebih detail dari beranda) ---- */
.panduan-list { display: flex; flex-direction: column; gap: 1.1rem; }
.panduan-item {
    display: flex; gap: 1.2rem; background: #fff; border: 1px solid var(--pb-line);
    border-radius: 12px; padding: 1.3rem 1.5rem; box-shadow: 0 3px 10px rgba(0,0,0,0.05);
}
.panduan-no {
    flex-shrink: 0; width: 38px; height: 38px; border-radius: 50%; background: var(--pb-lake);
    color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.05rem;
}
.panduan-item h3 { color: var(--pb-lake-deep); font-size: 1.05rem; margin-bottom: 0.4rem; }
.panduan-item p { color: #45525c; font-size: 0.92rem; }

/* ---- FAQ ---- */
.faq-item-pb { border-bottom: 1px solid var(--pb-line); }
.faq-question-pb {
    width: 100%; text-align: left; background: none; border: none; padding: 1rem 0;
    font-size: 1rem; font-weight: 600; color: var(--pb-lake-deep); cursor: pointer;
    display: flex; justify-content: space-between; align-items: center; gap: 1rem;
}
.faq-question-pb .ikon-pb {
    color: #fff; background: var(--pb-gold); width: 24px; height: 24px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0;
    transition: transform 0.2s ease;
}
.faq-item-pb.aktif .faq-question-pb .ikon-pb { transform: rotate(45deg); background: var(--pb-lake); }
.faq-answer-pb { max-height: 0; overflow: hidden; transition: max-height 0.25s ease; }
.faq-item-pb.aktif .faq-answer-pb { max-height: 300px; }
.faq-answer-pb p { padding: 0 0 1rem; font-size: 0.92rem; color: #45525c; }

/* ---- Kontak ---- */
.kontak-grid-pb { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.2rem; }
.kontak-card-pb {
    background: #fff; border: 1px solid var(--pb-line); border-radius: 12px; padding: 1.5rem; text-align: center;
    box-shadow: 0 3px 10px rgba(0,0,0,0.05);
}
.kontak-card-pb .icon-pb {
    font-size: 1.4rem; width: 46px; height: 46px; border-radius: 50%; background: #eef7fa;
    display: flex; align-items: center; justify-content: center; margin: 0 auto 0.6rem;
}
.kontak-card-pb h3 { font-size: 0.98rem; color: var(--pb-lake-deep); margin-bottom: 0.4rem; }
.kontak-card-pb a { color: var(--pb-lake); font-weight: 700; text-decoration: none; font-size: 0.9rem; }
.kontak-card-pb a:hover { color: var(--pb-gold-dim, #c97a1f); }

.bantuan-cta-pb { text-align: center; }
.bantuan-cta-pb p { color: var(--pb-muted); margin: 0.6rem 0 1.4rem; }
</style>

<div class="bantuan-page">

    <div class="bantuan-hero">
        <h1>Pusat Bantuan</h1>
        <p>Semua yang perlu Anda tahu tentang cara memesan, membayar, dan mengelola booking parkir di kawasan Danau Toba.</p>
    </div>

    <!-- ================= PANDUAN CARA PAKAI ================= -->
    <section>
        <h2>Panduan Cara Pakai</h2>
        <div class="panduan-list">
            <div class="panduan-item">
                <span class="panduan-no">1</span>
                <div>
                    <h3>Buat akun atau masuk</h3>
                    <p>Daftar dengan email Anda di halaman <a href="register.php">Daftar</a>, atau langsung <a href="login.php">masuk</a> jika sudah punya akun.</p>
                </div>
            </div>
            <div class="panduan-item">
                <span class="panduan-no">2</span>
                <div>
                    <h3>Cari lokasi parkir tujuan</h3>
                    <p>Gunakan kotak pencarian di beranda untuk menemukan lokasi parkir dekat destinasi Anda, misalnya Parapat, Tomok, atau Ajibata.</p>
                </div>
            </div>
            <div class="panduan-item">
                <span class="panduan-no">3</span>
                <div>
                    <h3>Lihat detail & pastikan slot tersedia</h3>
                    <p>Buka halaman detail lokasi untuk melihat tarif per jenis kendaraan, sisa slot, dan ulasan pengunjung lain.</p>
                </div>
            </div>
            <div class="panduan-item">
                <span class="panduan-no">4</span>
                <div>
                    <h3>Isi data kendaraan & jadwal</h3>
                    <p>Masukkan plat nomor, jenis kendaraan, tanggal, jam kedatangan, dan durasi parkir yang Anda butuhkan.</p>
                </div>
            </div>
            <div class="panduan-item">
                <span class="panduan-no">5</span>
                <div>
                    <h3>Selesaikan pembayaran</h3>
                    <p>Pilih metode transfer bank, e-wallet, atau QRIS, lalu upload bukti pembayaran. Status booking akan berubah menjadi "Dibayar".</p>
                </div>
            </div>
            <div class="panduan-item">
                <span class="panduan-no">6</span>
                <div>
                    <h3>Datang & tunjukkan bukti booking</h3>
                    <p>Tunjukkan kode booking ke petugas di lokasi. Setelah kendaraan Anda keluar, status akan diperbarui menjadi "Selesai".</p>
                </div>
            </div>
            <div class="panduan-item">
                <span class="panduan-no">7</span>
                <div>
                    <h3>Beri ulasan</h3>
                    <p>Setelah booking berstatus "Selesai", Anda bisa memberi rating dan komentar dari halaman <a href="riwayat.php">Riwayat Booking</a> untuk membantu pengunjung lain.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= FAQ ================= -->
    <section>
        <h2>Pertanyaan yang Sering Diajukan</h2>
        <div class="faq-list-pb">
            <div class="faq-item-pb">
                <button type="button" class="faq-question-pb">Bagaimana cara memesan slot parkir?<span class="ikon-pb">+</span></button>
                <div class="faq-answer-pb"><p>Cari lokasi tujuan Anda di kotak pencarian pada beranda, buka halaman detail lokasi, pilih jenis kendaraan dan jam kedatangan, lalu selesaikan pembayaran. Bukti pesanan akan muncul di halaman Riwayat Booking setelah pembayaran dikirim.</p></div>
            </div>
            <div class="faq-item-pb">
                <button type="button" class="faq-question-pb">Apakah pesanan bisa dibatalkan atau diubah jadwalnya?<span class="ikon-pb">+</span></button>
                <div class="faq-answer-pb"><p>Pembatalan dan perubahan jadwal dapat dilakukan selama slot belum digunakan. Hubungi layanan bantuan kami melalui WhatsApp atau email di bawah agar dapat segera dibantu prosesnya.</p></div>
            </div>
            <div class="faq-item-pb">
                <button type="button" class="faq-question-pb">Bagaimana jika slot yang saya pesan ternyata sudah terisi?<span class="ikon-pb">+</span></button>
                <div class="faq-answer-pb"><p>Setiap slot yang sudah dibayar dikunci atas nama Anda dan tidak dapat dipesan orang lain. Jika terjadi kendala di lokasi, segera hubungi kontak bantuan agar dapat segera ditindaklanjuti.</p></div>
            </div>
            <div class="faq-item-pb">
                <button type="button" class="faq-question-pb">Metode pembayaran apa saja yang tersedia?<span class="ikon-pb">+</span></button>
                <div class="faq-answer-pb"><p>Pembayaran dapat dilakukan melalui transfer bank, e-wallet (DANA/OVO/GoPay), dan QRIS. Pilihan metode akan muncul saat proses checkout di halaman pembayaran.</p></div>
            </div>
            <div class="faq-item-pb">
                <button type="button" class="faq-question-pb">Berapa lama konfirmasi pembayaran diproses?<span class="ikon-pb">+</span></button>
                <div class="faq-answer-pb"><p>Setelah bukti pembayaran diupload, tim kami biasanya mengonfirmasi dalam waktu 1x24 jam. Status booking Anda akan berubah dari "Pending" menjadi "Dibayar" setelah dikonfirmasi.</p></div>
            </div>
            <div class="faq-item-pb">
                <button type="button" class="faq-question-pb">Bagaimana cara memberi rating setelah parkir?<span class="ikon-pb">+</span></button>
                <div class="faq-answer-pb"><p>Setelah booking Anda berstatus "Selesai", tombol "Beri Ulasan" akan muncul di halaman Riwayat Booking. Anda dapat memberi rating bintang dan komentar dari sana.</p></div>
            </div>
            <div class="faq-item-pb">
                <button type="button" class="faq-question-pb">Saya lupa password akun saya, bagaimana solusinya?<span class="ikon-pb">+</span></button>
                <div class="faq-answer-pb"><p>Gunakan tautan "Lupa Password" di halaman Masuk untuk mengatur ulang password Anda, atau hubungi kontak bantuan kami di bawah.</p></div>
            </div>
        </div>
    </section>

    <!-- ================= KONTAK ================= -->
    <section>
        <h2>Hubungi Kami</h2>
        <div class="kontak-grid-pb">
            <div class="kontak-card-pb">
                <span class="icon-pb">💬</span>
                <h3>WhatsApp</h3>
                <a href="https://wa.me/628816675849" target="_blank" rel="noopener">+62 881-6675-849</a>
            </div>
            <div class="kontak-card-pb">
                <span class="icon-pb">✉️</span>
                <h3>Email</h3>
                <a href="mailto:bantuan@parkirtobaa.id">bantuan@parkirtobaa.id</a>
            </div>
            <div class="kontak-card-pb">
                <span class="icon-pb">🕒</span>
                <h3>Jam Layanan</h3>
                <span style="color:#7a8a94; font-weight:600; font-size:0.9rem;">Setiap hari, 07.00–21.00 WIB</span>
            </div>
        </div>
    </section>

    <section class="bantuan-cta-pb">
        <h2>Masih ada pertanyaan lain?</h2>
        <p>Tim kami siap membantu Anda melalui WhatsApp maupun email.</p>
        <a href="https://wa.me/628816675849" target="_blank" rel="noopener" class="btn-primary">Chat via WhatsApp</a>
    </section>

</div>

<script>
document.querySelectorAll('.faq-question-pb').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var item = btn.closest('.faq-item-pb');
        var sedangAktif = item.classList.contains('aktif');
        document.querySelectorAll('.faq-item-pb.aktif').forEach(function (aktifLain) {
            aktifLain.classList.remove('aktif');
        });
        if (!sedangAktif) { item.classList.add('aktif'); }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>