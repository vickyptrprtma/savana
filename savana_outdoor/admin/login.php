<?php
require_once __DIR__ . '/../config/koneksi.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['admin_id'])) {
    header('Location: dashbord.php');
    exit;
}

$error = '';
$username_input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_input = trim($_POST['username'] ?? '');
    $password_raw   = trim($_POST['password'] ?? '');

    if ($username_input === '' || $password_raw === '') {
        $error = 'Email/nama admin dan password wajib diisi.';
    } else {
        $email_or_nama = mysqli_real_escape_string($conn, $username_input);
        $pass_md5      = md5($password_raw);

        $q = mysqli_query($conn, "SELECT * FROM admin WHERE (email = '$email_or_nama' OR nama_admin = '$email_or_nama') LIMIT 1");
        if ($q && mysqli_num_rows($q) > 0) {
            $adm = mysqli_fetch_assoc($q);
            $match_md5    = ($adm['password_hash'] === $pass_md5);
            $match_bcrypt = password_verify($password_raw, $adm['password_hash']);

            if ($match_md5 || $match_bcrypt) {
                // Modernisasi keamanan: jika password masih MD5 lama, upgrade otomatis ke bcrypt
                if ($match_md5 && !$match_bcrypt) {
                    $new_hash     = password_hash($password_raw, PASSWORD_DEFAULT);
                    $new_hash_esc = mysqli_real_escape_string($conn, $new_hash);
                    $id_adm       = (int)$adm['id_admin'];
                    @mysqli_query($conn, "UPDATE admin SET password_hash = '$new_hash_esc' WHERE id_admin = $id_adm");
                }
                $_SESSION['admin_id']   = (int)$adm['id_admin'];
                $_SESSION['admin_nama'] = $adm['nama_admin'];
                header('Location: dashbord.php');
                exit;
            } else {
                $error = 'Password yang dimasukkan salah.';
            }
        } else {
            $error = 'Email atau nama admin tidak ditemukan.';
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container" style="max-width:440px;">
    <div class="card-shell">
        <h2 style="font-size:20px; margin-bottom:4px;">Login Admin</h2>
        <p style="color:var(--text-muted); font-size:13.5px; margin-bottom:18px;">Halaman autentikasi khusus pengelola Savana Outdoor.</p>

        <?php if ($error): ?>
            <div class="alert-err" style="margin-bottom:16px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label class="form-label">Email atau Nama Admin</label>
                <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($username_input) ?>" required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn-primary-green" style="margin-top:6px;">Masuk ke Dashboard</button>
        </form>

        <div style="margin-top:18px; text-align:center;">
            <a href="/savana_outdoor/index.php" style="font-size:13px; color:var(--text-muted);">
                ← Kembali ke Halaman Utama
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
