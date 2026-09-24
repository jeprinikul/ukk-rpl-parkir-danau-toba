<?php
/**
 * Kumpulan fungsi bantu (helper)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Format angka ke Rupiah
function formatRupiah($angka) {
    return "Rp " . number_format((float)$angka, 0, ',', '.');
}

// Generate kode booking unik, misal: PDT-20260902-8X4K
function generateKodeBooking() {
    return 'PDT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
}

// Cek apakah user sudah login
function isLoggedIn() {
    return isset($_SESSION['id_user']);
}

// Cek role spesifik
function isOwner() {
    return isLoggedIn() && ($_SESSION['role'] ?? '') === 'owner';
}

function isAdmin() {
    return isLoggedIn() && ($_SESSION['role'] ?? '') === 'admin';
}

function isPetugas() {
    return isLoggedIn() && ($_SESSION['role'] ?? '') === 'petugas';
}

// Cek apakah termasuk salah satu dari role staf (bukan pengunjung biasa)
function isStaff() {
    return isOwner() || isAdmin() || isPetugas();
}

// Ambil role user yang sedang login (string), kosong jika belum login
function currentRole() {
    return $_SESSION['role'] ?? '';
}

// Wajib login, kalau belum redirect ke halaman login
// (otomatis mendeteksi apakah halaman ini ada di subfolder admin/owner/petugas
// supaya path redirect-nya tetap benar)
function requireLogin() {
    if (!isLoggedIn()) {
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $prefix = preg_match('#/(admin|owner|petugas)$#', $scriptDir) ? '../' : '';
        header('Location: ' . $prefix . 'login.php');
        exit;
    }
}

// Wajib salah satu dari role staf (owner/admin/petugas), untuk masuk area admin panel
function requireStaff() {
    requireLogin();
    if (!isStaff()) {
        header('Location: ../index.php');
        exit;
    }
}

// Wajib admin saja (dipakai untuk kelola lokasi, tarif, area, kendaraan, log aktifitas)
// Owner TIDAK diberi akses ke halaman-halaman ini, sesuai tabel fitur:
// CRUD Tarif/Area/Kendaraan & Akses Log Aktifitas hanya milik Admin.
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ../index.php');
        exit;
    }
}

// Wajib owner saja (dipakai untuk kelola akun staf)
function requireOwner() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: kelola_user.php');
        exit;
    }
}

// Label tampilan untuk role
function labelRole($role) {
    $map = [
        'user'    => 'Pengunjung',
        'petugas' => 'Petugas',
        'admin'   => 'Admin',
        'owner'   => 'Owner',
    ];
    return $map[$role] ?? ucfirst($role);
}

// Membersihkan input dasar
function clean($data) {
    return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
}

// Redirect dengan pesan flash sederhana
function redirectWithMessage($url, $message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header("Location: $url");
    exit;
}

// Menampilkan & menghapus flash message
function showFlashMessage() {
    if (!empty($_SESSION['flash_message'])) {
        $type = $_SESSION['flash_type'] ?? 'success';
        $msg = $_SESSION['flash_message'];
        echo "<div class='alert alert-$type'>$msg</div>";
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
    }
}