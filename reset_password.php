<?php
/**
 * reset_password.php — SCRIPT SEKALI PAKAI
 * -----------------------------------------------------------------
 * Cara pakai:
 * 1. Taruh file ini di folder utama parkir-danau-toba (sejajar dengan index.php)
 * 2. Buka lewat browser: localhost/parkir-danau-toba/reset_password.php
 * 3. Isi form email + password baru, submit
 * 4. Setelah berhasil, HAPUS file ini dari htdocs (jangan dibiarkan nempel,
 *    karena siapa saja yang tau URL-nya bisa ganti password akun manapun)
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/config/database.php';
$db = getDB();

$pesan = '';
$sukses = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $passwordBaru = $_POST['password'] ?? '';

    if ($email === '' || strlen($passwordBaru) < 6) {
        $pesan = 'Email wajib diisi dan password minimal 6 karakter.';
    } else {
        $cek = $db->prepare("SELECT id, name FROM users WHERE email = ?");
        $cek->execute([$email]);
        $user = $cek->fetch();

        if (!$user) {
            $pesan = "Email '$email' tidak ditemukan di database.";
        } else {
            $hash = password_hash($passwordBaru, PASSWORD_BCRYPT);
            $update = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->execute([$hash, $user['id']]);
            $sukses = true;
            $pesan = "Password untuk '{$user['name']}' ($email) berhasil direset. Silakan login pakai password baru itu.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Reset Password (Sekali Pakai)</title>
<style>
body { font-family: Arial, sans-serif; max-width: 500px; margin: 3rem auto; padding: 0 1.5rem; }
h1 { font-size: 1.3rem; }
.warning { background:#FFF3CD; border:1px solid #FFECB5; color:#664D03; padding:0.8rem 1rem; border-radius:8px; font-size:0.9rem; margin-bottom:1.5rem; }
.sukses { background:#D1E7DD; border:1px solid #BADBCC; color:#0F5132; padding:0.8rem 1rem; border-radius:8px; margin-bottom:1.5rem; }
.error { background:#F8D7DA; border:1px solid #F5C2C7; color:#842029; padding:0.8rem 1rem; border-radius:8px; margin-bottom:1.5rem; }
label { display:block; font-weight:bold; margin-bottom:0.3rem; margin-top:1rem; }
input { width:100%; padding:0.6rem; border:1px solid #ccc; border-radius:6px; box-sizing:border-box; }
button { margin-top:1.5rem; padding:0.7rem 1.5rem; background:#1B4451; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:1rem; }
</style>
</head>
<body>

<div class="warning">⚠️ Ini script sementara untuk reset password. Setelah selesai dipakai, <strong>hapus file ini</strong> dari folder htdocs kamu.</div>

<h1>Reset Password User</h1>

<?php if ($pesan): ?>
    <div class="<?= $sukses ? 'sukses' : 'error' ?>"><?= htmlspecialchars($pesan) ?></div>
<?php endif; ?>

<form method="POST">
    <label>Email akun yang mau direset</label>
    <input type="email" name="email" placeholder="contoh: owner@parkirdanautoba.com" required>

    <label>Password baru</label>
    <input type="text" name="password" placeholder="minimal 6 karakter" required>

    <button type="submit">Reset Password</button>
</form>

</body>
</html>
