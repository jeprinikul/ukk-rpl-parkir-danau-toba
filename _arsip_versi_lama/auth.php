<?php
/**
 * includes/auth.php
 * -----------------------------------------------------------------
 * Helper otentikasi & otorisasi berbasis role (admin/petugas/owner).
 * Panggil session_start() SEBELUM require file ini di setiap halaman.
 * -----------------------------------------------------------------
 */

// Base path project (disesuaikan karena project ada di subfolder /parkir-danau-toba/)
if (!defined('BASE_URL')) {
    define('BASE_URL', '/parkir-danau-toba');
}

/** Pastikan user sudah login, kalau belum lempar ke login.php */
function requireLogin() {
    if (empty($_SESSION['id_user'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

/**
 * Pastikan user sudah login DAN role-nya termasuk yang diizinkan.
 * Contoh: requireRole('admin') atau requireRole(['admin','owner'])
 */
function requireRole($allowedRoles) {
    requireLogin();
    $allowedRoles = (array)$allowedRoles;
    if (!in_array($_SESSION['role'], $allowedRoles, true)) {
        http_response_code(403);
        die('Akses ditolak. Halaman ini khusus untuk role: ' . implode(', ', $allowedRoles));
    }
}

/** Redirect user ke dashboard sesuai role-nya masing-masing */
function redirectKeDashboard($role) {
    switch ($role) {
        case 'admin':
            header('Location: ' . BASE_URL . '/admin/dashboard.php');
            break;
        case 'petugas':
            header('Location: ' . BASE_URL . '/petugas/dashboard.php');
            break;
        case 'owner':
            header('Location: ' . BASE_URL . '/owner/dashboard.php');
            break;
        default:
            header('Location: ' . BASE_URL . '/login.php');
    }
    exit;
}

/** Catat aktivitas user ke tb_log_aktivitas (dipakai untuk fitur "Akses Log Aktifitas" admin) */
function catatLog($db, $id_user, $aktivitas) {
    try {
        $stmt = $db->prepare("INSERT INTO tb_log_aktivitas (id_user, aktivitas, waktu_aktivitas) VALUES (?, ?, NOW())");
        $stmt->execute([$id_user, $aktivitas]);
    } catch (PDOException $e) {
        // Jangan hentikan alur utama hanya karena logging gagal.
    }
}