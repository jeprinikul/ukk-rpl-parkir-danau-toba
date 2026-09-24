<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$db = getDB();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($pass, $user['password'])) {
        if ($user['status_akun'] === 'nonaktif') {
            $errors[] = "Akun Anda telah dinonaktifkan. Hubungi Owner/Admin.";
            $_SESSION['notif_sound'] = 'error';   // suara: login ditolak
        } else {
            $_SESSION['id_user']      = $user['id'];
            $_SESSION['nama_lengkap'] = $user['name'];
            $_SESSION['role']         = $user['role'];
            $_SESSION['lokasi_id']    = $user['lokasi_id'];
            $_SESSION['notif_sound']  = 'login';  // suara: login berhasil

            switch ($user['role']) {
                case 'admin':
                    header('Location: admin/dashboard.php');
                    break;
                case 'petugas':
                    header('Location: petugas/dashboard.php');
                    break;
                case 'owner':
                    header('Location: owner/dashboard.php');
                    break;
                default:
                    header('Location: index.php');
            }
            exit;
        }
    } else {
        $errors[] = "Email atau password salah.";
        $_SESSION['notif_sound'] = 'error';       // suara: login gagal
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Masuk — TobaPark</title>
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif:ital,wght@0,400..700;1,400..700&family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config={darkMode:"class",theme:{extend:{"colors":{"on-tertiary-container":"#f27274","surface":"#f6faf7","on-error":"#ffffff","on-secondary-fixed-variant":"#673d00","primary-fixed":"#c1eaea","on-secondary-fixed":"#2b1700","on-primary-container":"#7aa2a1","tertiary-container":"#680816","error":"#ba1a1a","surface-dim":"#d7dbd8","on-primary-fixed":"#002020","inverse-on-surface":"#edf2ee","secondary-fixed":"#ffddba","inverse-primary":"#a5cecd","secondary-fixed-dim":"#ffb866","on-background":"#181d1b","on-primary":"#ffffff","on-tertiary-fixed-variant":"#852128","surface-variant":"#dfe4e0","on-secondary-container":"#724400","error-container":"#ffdad6","outline-variant":"#c0c8c7","tertiary-fixed":"#ffdad8","surface-tint":"#3e6565","on-surface-variant":"#414848","outline":"#717978","primary-container":"#0d3838","background":"#f6faf7","surface-container-low":"#f0f5f1","on-tertiary-fixed":"#410008","on-error-container":"#93000a","secondary-container":"#fdb257","inverse-surface":"#2c312f","surface-container-high":"#e5e9e6","surface-bright":"#f6faf7","surface-container-highest":"#dfe4e0","secondary":"#875200","on-surface":"#181d1b","surface-container-lowest":"#ffffff","surface-container":"#eaefeb","on-secondary":"#ffffff","on-tertiary":"#ffffff","tertiary-fixed-dim":"#ffb3b1","primary-fixed-dim":"#a5cecd","on-primary-fixed-variant":"#254d4d","primary":"#002222","tertiary":"#430008"},"borderRadius":{"DEFAULT":"0.25rem","lg":"0.5rem","xl":"0.75rem","full":"9999px"},"spacing":{"space-sm":"0.5rem","space-lg":"1.75rem","space-xl":"2.5rem","margin":"3rem","gutter":"1.5rem","gutter-mobile":"1rem","space-md":"1rem","margin-mobile":"1.25rem","space-xs":"0.25rem"},"fontFamily":{"display-lg-mobile":["Noto Serif"],"label-lg":["Plus Jakarta Sans"],"body-md":["Plus Jakarta Sans"],"label-sm":["Plus Jakarta Sans"],"headline-md":["Noto Serif"],"label-md":["Plus Jakarta Sans"],"body-sm":["Plus Jakarta Sans"],"headline-sm":["Noto Serif"],"headline-lg":["Noto Serif"],"body-lg":["Plus Jakarta Sans"],"headline-lg-mobile":["Noto Serif"],"display-lg":["Noto Serif"]},"fontSize":{"display-lg-mobile":["36px",{"lineHeight":"42px","letterSpacing":"-0.015em","fontWeight":"600"}],"label-lg":["14px",{"lineHeight":"18px","letterSpacing":"0.02em","fontWeight":"600"}],"body-md":["15px",{"lineHeight":"24px","fontWeight":"400"}],"label-sm":["11px",{"lineHeight":"14px","letterSpacing":"0.08em","fontWeight":"700"}],"headline-md":["26px",{"lineHeight":"34px","fontWeight":"600"}],"label-md":["12px",{"lineHeight":"16px","letterSpacing":"0.05em","fontWeight":"600"}],"body-sm":["13px",{"lineHeight":"20px","fontWeight":"400"}],"headline-sm":["20px",{"lineHeight":"28px","fontWeight":"500"}],"headline-lg":["38px",{"lineHeight":"46px","letterSpacing":"-0.01em","fontWeight":"600"}],"body-lg":["18px",{"lineHeight":"28px","fontWeight":"400"}],"headline-lg-mobile":["28px",{"lineHeight":"34px","letterSpacing":"-0.01em","fontWeight":"600"}],"display-lg":["52px",{"lineHeight":"60px","letterSpacing":"-0.02em","fontWeight":"600"}]}}}}
</script>
<style>
  body { margin: 0; }
  @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: none; } }
  .fade-up { animation: fadeUp .7s cubic-bezier(.22,1,.36,1) both; }
  @media (prefers-reduced-motion: reduce) { * { animation: none !important; transition: none !important; } }
</style>
</head>
<body class="bg-surface font-body-md text-on-surface antialiased selection:bg-secondary-fixed selection:text-on-secondary-fixed">

<main class="min-h-screen grid grid-cols-1 lg:grid-cols-2">

  <!-- Panel kiri: foto danau + pesan brand -->
  <aside class="relative hidden lg:flex flex-col justify-between overflow-hidden bg-primary-container p-margin">
    <div class="absolute inset-0">
      <div class="w-full h-full bg-cover bg-center" style="background-image:url('assets/img/parapat.jpg')"></div>
      <div class="absolute inset-0 bg-gradient-to-br from-primary/95 via-primary-container/85 to-primary/70 mix-blend-multiply"></div>
      <div class="absolute inset-0 bg-gradient-to-t from-primary/80 via-transparent to-primary/30"></div>
    </div>

    <a href="index.php" class="relative flex items-center gap-space-sm group w-fit">
      <div class="w-11 h-11 rounded-lg bg-surface/10 backdrop-blur-md flex items-center justify-center group-hover:bg-surface/20 transition-colors">
        <span class="material-symbols-outlined text-secondary-container text-[26px]">anchor</span>
      </div>
      <div class="flex flex-col">
        <span class="font-headline-sm text-headline-sm text-surface tracking-tight leading-none">TobaPark</span>
        <span class="font-label-sm text-[10px] text-secondary-container tracking-widest uppercase mt-0.5">Parkir Kawasan Danau Toba</span>
      </div>
    </a>

    <div class="relative max-w-md fade-up">
      <span class="font-label-md text-label-md text-secondary-container tracking-widest uppercase font-bold">Portal Pengelola</span>
      <h1 class="font-display-lg text-display-lg text-surface mt-space-sm">
        Kelola parkir <span class="italic font-normal text-secondary-container">dengan tenang.</span>
      </h1>
      <p class="mt-space-md font-body-lg text-body-lg text-surface-container-high/90">
        Masuk untuk memantau slot, memproses pesanan, dan melihat laporan lokasi parkir di kawasan Danau Toba.
      </p>
    </div>

    <div class="relative flex items-center gap-space-xs text-surface-container-high font-label-sm text-label-sm">
      <span class="material-symbols-outlined text-secondary-container text-[18px]">lock</span>
      <span>Akses khusus Owner, Admin, dan Petugas</span>
    </div>

    <!-- Pita motif ulos -->
    <div class="absolute bottom-0 left-0 right-0 h-2 bg-gradient-to-r from-tertiary via-secondary-container to-primary-container"></div>
  </aside>

  <!-- Panel kanan: form -->
  <section class="flex flex-col justify-center px-gutter py-space-xl sm:px-margin">
    <div class="w-full max-w-md mx-auto fade-up">

      <!-- Brand versi mobile -->
      <a href="index.php" class="lg:hidden flex items-center gap-space-sm mb-space-lg w-fit">
        <div class="w-10 h-10 rounded-lg bg-primary-container flex items-center justify-center">
          <span class="material-symbols-outlined text-secondary-container text-[24px]">anchor</span>
        </div>
        <span class="font-headline-sm text-headline-sm text-primary tracking-tight">TobaPark</span>
      </a>

      <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Selamat datang kembali</span>
      <h2 class="font-headline-lg-mobile md:font-headline-lg text-headline-lg-mobile md:text-headline-lg text-primary mt-space-xs">Masuk ke akun Anda</h2>
      <p class="font-body-md text-body-md text-on-surface-variant mt-space-xs mb-space-lg">Gunakan email dan password yang terdaftar.</p>

      <?php foreach ($errors as $e): ?>
        <div class="mb-space-md p-space-md rounded-lg bg-error-container text-on-error-container font-label-md text-label-md flex items-start gap-space-xs" role="alert">
          <span class="material-symbols-outlined text-[20px] shrink-0">error</span>
          <span><?= clean($e) ?></span>
        </div>
      <?php endforeach; ?>

      <form method="POST" class="bg-surface-container-lowest rounded-xl shadow-md p-space-lg relative overflow-hidden flex flex-col gap-space-md">
        <div class="h-1.5 w-full bg-gradient-to-r from-tertiary via-secondary to-primary-container absolute top-0 left-0 right-0"></div>

        <div class="pt-space-xs">
          <label for="email" class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider block mb-1.5">Email</label>
          <div class="relative">
            <span class="material-symbols-outlined text-outline text-[20px] absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none">mail</span>
            <input id="email" type="email" name="email" value="<?= clean($_POST['email'] ?? '') ?>" required autofocus autocomplete="username"
                   placeholder="nama@email.com"
                   class="w-full bg-surface-container-low text-on-surface font-label-lg text-label-lg pl-11 pr-space-md py-3 rounded-lg focus:outline-none focus:bg-surface focus:ring-2 focus:ring-secondary-container transition-colors"/>
          </div>
        </div>

        <div>
          <label for="password" class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider block mb-1.5">Password</label>
          <div class="relative">
            <span class="material-symbols-outlined text-outline text-[20px] absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none">lock</span>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                   placeholder="Masukkan password"
                   class="w-full bg-surface-container-low text-on-surface font-label-lg text-label-lg pl-11 pr-12 py-3 rounded-lg focus:outline-none focus:bg-surface focus:ring-2 focus:ring-secondary-container transition-colors"/>
            <button type="button" id="toggle-pass" aria-label="Tampilkan password"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-outline hover:text-primary transition-colors">
              <span class="material-symbols-outlined text-[20px]" id="toggle-icon">visibility</span>
            </button>
          </div>
        </div>

        <button type="submit"
                class="w-full h-12 bg-secondary-container hover:bg-secondary text-on-secondary-container hover:text-on-secondary font-label-lg text-label-lg rounded-lg shadow-md hover:shadow-lg flex items-center justify-center gap-space-sm transition-all">
          <span>Masuk</span>
          <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
        </button>
      </form>

      <p class="mt-space-lg text-center font-body-md text-body-md text-on-surface-variant">
        Belum punya akun?
        <a href="register.php" class="text-secondary font-bold hover:underline">Daftar di sini</a>
      </p>
      <p class="mt-space-sm text-center">
        <a href="index.php" class="inline-flex items-center gap-1 font-label-md text-label-md text-on-surface-variant hover:text-primary transition-colors">
          <span class="material-symbols-outlined text-[16px]">arrow_back</span>Kembali ke beranda
        </a>
      </p>
    </div>
  </section>
</main>

<script>
// Tampilkan / sembunyikan password
document.getElementById('toggle-pass').addEventListener('click', function () {
  var input = document.getElementById('password');
  var tampil = input.type === 'password';
  input.type = tampil ? 'text' : 'password';
  document.getElementById('toggle-icon').textContent = tampil ? 'visibility_off' : 'visibility';
  this.setAttribute('aria-label', tampil ? 'Sembunyikan password' : 'Tampilkan password');
});
</script>
</body>
</html>