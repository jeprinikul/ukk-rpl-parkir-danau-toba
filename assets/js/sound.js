/**
 * sound.js — Sound effect kecil untuk Sistem Parkir Danau Toba
 * -----------------------------------------------------------
 * Semua bunyi dibuat langsung lewat Web Audio API (synth sederhana),
 * jadi TIDAK perlu file .mp3/.wav terpisah. Tinggal include file ini
 * di halaman, lalu panggil fungsinya di event yang kamu mau (onclick,
 * setelah AJAX sukses, dsb).
 *
 * Cara pakai di HTML:
 *   <script src="assets/js/sound.js"></script>
 *
 * Lalu panggil salah satu fungsi berikut kapan pun dibutuhkan:
 *   playClick()        -> klik tombol biasa (login, submit, dsb)
 *   playSuccess()       -> berhasil (booking sukses, ACC admin, bayar sukses)
 *   playError()         -> gagal / ditolak (reject admin, validasi gagal)
 *   playNotification()  -> notifikasi ringan (pesan baru, pengingat)
 *   playPrint()         -> saat mencetak / membuka struk
 *   playCoin()          -> transaksi/pembayaran (opsional, kesan "cha-ching")
 */

(function (window) {
    "use strict";

    let audioCtx = null;
    let unlocked = false;

    // Audio context browser modern butuh "dibuka" oleh interaksi user
    // (klik/tap) dulu sebelum bisa bunyi. Fungsi ini dipanggil otomatis
    // sekali saat halaman pertama kali disentuh user.
    function getContext() {
        if (!audioCtx) {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (!AudioContextClass) return null; // browser sangat lama, gagal diam-diam
            audioCtx = new AudioContextClass();
        }
        if (audioCtx.state === "suspended") {
            audioCtx.resume();
        }
        return audioCtx;
    }

    function unlockOnFirstInteraction() {
        if (unlocked) return;
        unlocked = true;
        getContext();
        window.removeEventListener("click", unlockOnFirstInteraction);
        window.removeEventListener("touchstart", unlockOnFirstInteraction);
        window.removeEventListener("keydown", unlockOnFirstInteraction);
    }
    window.addEventListener("click", unlockOnFirstInteraction);
    window.addEventListener("touchstart", unlockOnFirstInteraction);
    window.addEventListener("keydown", unlockOnFirstInteraction);

    /**
     * Membunyikan satu nada sederhana.
     * @param {number} freq - frekuensi nada (Hz)
     * @param {number} start - waktu mulai relatif (detik) dari sekarang
     * @param {number} duration - durasi nada (detik)
     * @param {string} type - bentuk gelombang: sine, triangle, square, sawtooth
     * @param {number} volume - volume puncak (0 - 1)
     */
    function tone(freq, start, duration, type, volume) {
        const ctx = getContext();
        if (!ctx) return;

        const osc = ctx.createOscillator();
        const gain = ctx.createGain();

        osc.type = type || "sine";
        osc.frequency.value = freq;

        const now = ctx.currentTime + start;
        const peak = volume !== undefined ? volume : 0.18;

        // Envelope halus (fade in singkat, fade out) supaya tidak terdengar "klik" kasar
        gain.gain.setValueAtTime(0, now);
        gain.gain.linearRampToValueAtTime(peak, now + 0.015);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + duration);

        osc.connect(gain);
        gain.connect(ctx.destination);

        osc.start(now);
        osc.stop(now + duration + 0.05);
    }

    /** Klik tombol biasa: login, submit form, buka menu, dsb. Bunyi pendek & netral. */
    function playClick() {
        tone(700, 0, 0.06, "sine", 0.12);
    }

    /** Aksi berhasil: booking sukses, ACC admin, pembayaran sukses. Bunyi naik & ceria. */
    function playSuccess() {
        tone(523.25, 0, 0.12, "sine", 0.16);    // C5
        tone(659.25, 0.09, 0.12, "sine", 0.16); // E5
        tone(783.99, 0.18, 0.18, "sine", 0.18); // G5
    }

    /** Aksi gagal/ditolak: reject admin, validasi form gagal, pembayaran gagal. Bunyi turun & rendah. */
    function playError() {
        tone(300, 0, 0.14, "sawtooth", 0.12);
        tone(220, 0.12, 0.22, "sawtooth", 0.12);
    }

    /** Notifikasi ringan: pesan baru masuk, pengingat, alert biasa. */
    function playNotification() {
        tone(880, 0, 0.09, "sine", 0.14);
        tone(1046.5, 0.08, 0.12, "sine", 0.12);
    }

    /** Cetak struk / print. Bunyi "beep" ganda pendek seperti mesin kasir/printer. */
    function playPrint() {
        tone(1200, 0, 0.05, "square", 0.08);
        tone(1200, 0.09, 0.05, "square", 0.08);
    }

    /** Transaksi/pembayaran, opsional dipakai terpisah dari playSuccess (kesan "cha-ching"). */
    function playCoin() {
        tone(988, 0, 0.08, "triangle", 0.15);
        tone(1319, 0.06, 0.16, "triangle", 0.18);
    }

    // Ekspor ke global scope supaya bisa dipanggil langsung dari onclick="" di HTML/PHP
    window.playClick = playClick;
    window.playSuccess = playSuccess;
    window.playError = playError;
    window.playNotification = playNotification;
    window.playPrint = playPrint;
    window.playCoin = playCoin;

})(window);