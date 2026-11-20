<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current  = basename($_SERVER['PHP_SELF']);
$in_admin = strpos(str_replace('\\', '/', $_SERVER['PHP_SELF']), '/admin/') !== false;
$base     = '/savana_outdoor';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $in_admin ? 'Admin - Savana Outdoor' : 'Savana Outdoor' ?></title>
    <link rel="stylesheet" href="<?= $base ?>/assets/css/style.css?v=<?= time() ?>">
</head>
<body>
<nav class="navbar">
    <div class="nav-inner">
        <a href="<?= $in_admin ? ($base . '/admin/dashbord.php') : ($base . '/index.php') ?>" class="brand">
            <?= $in_admin ? 'SAVANA OUTDOOR · ADMIN' : 'SAVANA OUTDOOR' ?>
        </a>
        <div class="nav-links">
            <?php if ($in_admin): ?>
                <?php if (!empty($_SESSION['admin_id'])): ?>
                    <a href="<?= $base ?>/admin/dashbord.php" class="<?= $current === 'dashbord.php' ? 'active' : '' ?>">Dashboard</a>
                    <a href="<?= $base ?>/admin/kelola_alat.php" class="<?= $current === 'kelola_alat.php' ? 'active' : '' ?>">Kelola Alat</a>
                    <a href="<?= $base ?>/admin/kelola_kategori.php" class="<?= $current === 'kelola_kategori.php' ? 'active' : '' ?>">Kelola Kategori</a>
                    <a href="<?= $base ?>/admin/logout.php">Logout (<?= htmlspecialchars($_SESSION['admin_nama'] ?? 'Admin') ?>)</a>
                <?php else: ?>
                    <a href="<?= $base ?>/index.php">← Ke Website Utama</a>
                <?php endif; ?>
            <?php else: ?>
                <a href="<?= $base ?>/index.php" class="<?= $current === 'index.php' ? 'active' : '' ?>">Katalog</a>
                <a href="<?= $base ?>/boking.php" class="<?= $current === 'boking.php' ? 'active' : '' ?>">Booking</a>
                <a href="<?= $base ?>/cek_pesanan.php" class="<?= $current === 'cek_pesanan.php' ? 'active' : '' ?>">Cek Pesanan</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
