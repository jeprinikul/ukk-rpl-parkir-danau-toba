/**
 * Pemutar suara notifikasi.
 * Pemakaian: notifSound('login')   // juga: 'booking', 'notif', 'error', 'logout'
 * Folder suara otomatis mengikuti lokasi file ini (assets/js -> assets/sounds).
 */
(function () {
  var script = document.currentScript;
  var base = script && script.src ? script.src.replace(/js\/notif\.js.*$/, 'sounds/') : 'assets/sounds/';
  var names = ['login', 'booking', 'notif', 'error', 'logout'];
  var cache = {};
  var pending = null;

  function muted() {
    try { return localStorage.getItem('notifMuted') === '1'; } catch (e) { return false; }
  }

  function get(name) {
    if (!cache[name]) {
      cache[name] = new Audio(base + name + '.mp3');
      cache[name].preload = 'auto';
      cache[name].volume = 0.7;
    }
    return cache[name];
  }

  function play(name) {
    if (names.indexOf(name) === -1 || muted()) return;
    var a = get(name);
    try { a.currentTime = 0; } catch (e) {}
    var p = a.play();
    if (p && p.catch) {
      // Browser (terutama di HP) kadang memblokir suara otomatis.
      // Kalau begitu, suara diputar saat pengguna menyentuh layar berikutnya.
      p.catch(function () { pending = name; });
    }
  }

  function flushPending() {
    if (pending) { var n = pending; pending = null; play(n); }
  }
  ['click', 'touchstart', 'keydown'].forEach(function (ev) {
    document.addEventListener(ev, flushPending, { passive: true });
  });

  // Matikan/nyalakan suara: notifMute(true) atau notifMute(false)
  window.notifMute = function (on) {
    try { localStorage.setItem('notifMuted', on ? '1' : '0'); } catch (e) {}
  };
  window.notifSound = play;

  // Muat lebih awal supaya suara langsung bunyi saat dipanggil
  names.forEach(get);
})();
