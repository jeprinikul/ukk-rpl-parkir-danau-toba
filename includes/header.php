<?php require_once __DIR__ . '/functions.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? clean($pageTitle) . ' - ' : '' ?>Parkir Danau Toba</title>
<link rel="icon" href="data:,">
<link rel="stylesheet" href="<?= isset($rootPath) ? $rootPath : '' ?>assets/css/style.css">
</head>
<body>
<header class="navbar">
    <div class="navbar-container">
        <a href="<?= isset($rootPath) ? $rootPath : '' ?>index.php" class="brand">🚗 Parkir Danau Toba</a>
        <nav class="nav-links">
            <a href="<?= isset($rootPath) ? $rootPath : '' ?>index.php">Beranda</a>
            <?php if (isLoggedIn()): ?>
                <?php if (!isStaff()): ?>
                    <a href="<?= isset($rootPath) ? $rootPath : '' ?>riwayat.php">Riwayat Saya</a>
                <?php endif; ?>
                <?php if (isStaff()):
                    $dashboardLink = match(currentRole()) {
                        'admin'   => 'admin/dashboard.php',
                        'petugas' => 'petugas/dashboard.php',
                        'owner'   => 'owner/dashboard.php',
                        default   => 'index.php',
                    };
                ?>
                    <a href="<?= isset($rootPath) ? $rootPath : '' ?><?= $dashboardLink ?>">Dashboard <?= clean(labelRole(currentRole())) ?></a>
                <?php endif; ?>
                <span class="nav-user">Hai, <?= clean($_SESSION['nama_lengkap']) ?></span>
                <a href="<?= isset($rootPath) ? $rootPath : '' ?>logout.php" class="btn-nav">Keluar</a>
            <?php else: ?>
                <a href="<?= isset($rootPath) ? $rootPath : '' ?>login.php">Masuk</a>
                <a href="<?= isset($rootPath) ? $rootPath : '' ?>register.php" class="btn-nav">Daftar</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="main-container">
<?php showFlashMessage(); ?>