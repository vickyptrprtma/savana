<?php
require_once __DIR__ . '/../config/koneksi.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$pesan_sukses = '';
$pesan_error  = '';

if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] === 'tambah_sukses') {
        $pesan_sukses = 'Kategori baru berhasil ditambahkan.';
    } elseif ($_GET['pesan'] === 'edit_sukses') {
        $pesan_sukses = 'Perubahan kategori berhasil disimpan.';
    } elseif ($_GET['pesan'] === 'hapus_sukses') {
        $pesan_sukses = 'Kategori berhasil dihapus.';
    }
}

// Proses Tambah Kategori
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_kategori'])) {
    $nama = mysqli_real_escape_string($conn, trim($_POST['nama_kategori'] ?? ''));
    $desc = mysqli_real_escape_string($conn, trim($_POST['deskripsi'] ?? ''));

    if ($nama === '') {
        $pesan_error = 'Nama kategori wajib diisi.';
    } else {
        $ins = mysqli_query($conn, "INSERT INTO kategori_alat (nama_kategori, deskripsi) VALUES ('$nama', '$desc')");
        if ($ins) {
            header('Location: kelola_kategori.php?pesan=tambah_sukses');
            exit;
        } else {
            $pesan_error = 'Gagal menambahkan kategori: ' . mysqli_error($conn);
        }
    }
}

// Proses Edit Kategori
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_kategori'])) {
    $id_kat = (int)($_POST['id_kategori'] ?? 0);
    $nama   = mysqli_real_escape_string($conn, trim($_POST['nama_kategori'] ?? ''));
    $desc   = mysqli_real_escape_string($conn, trim($_POST['deskripsi'] ?? ''));

    if ($id_kat <= 0 || $nama === '') {
        $pesan_error = 'ID kategori dan nama kategori wajib diisi secara valid.';
    } else {
        $up = mysqli_query($conn, "UPDATE kategori_alat
            SET nama_kategori = '$nama',
                deskripsi = '$desc'
            WHERE id_kategori = $id_kat");

        if ($up) {
            header('Location: kelola_kategori.php?pesan=edit_sukses');
            exit;
        } else {
            $pesan_error = 'Gagal memperbarui kategori: ' . mysqli_error($conn);
        }
    }
}

// Proses Hapus Kategori
if (isset($_GET['hapus'])) {
    $id_h = (int)$_GET['hapus'];
    $del = mysqli_query($conn, "DELETE FROM kategori_alat WHERE id_kategori = $id_h");
    if ($del) {
        header('Location: kelola_kategori.php?pesan=hapus_sukses');
        exit;
    } else {
        $pesan_error = 'Gagal menghapus kategori (kategori ini masih digunakan oleh data alat): ' . mysqli_error($conn);
    }
}

// Cek Mode Edit
$edit_mode = false;
$data_edit = null;
if (isset($_GET['edit'])) {
    $id_e = (int)$_GET['edit'];
    $qe   = mysqli_query($conn, "SELECT * FROM kategori_alat WHERE id_kategori = $id_e LIMIT 1");
    if ($qe && mysqli_num_rows($qe) > 0) {
        $edit_mode = true;
        $data_edit = mysqli_fetch_assoc($qe);
    }
}

$kategoris = mysqli_query($conn, "SELECT * FROM kategori_alat ORDER BY id_kategori DESC");

include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="card-shell">
        <h2 style="font-size:20px; margin-bottom:14px;">Kelola Kategori Alat</h2>

        <?php if ($pesan_sukses): ?>
            <div class="info-box" style="margin-top:0; margin-bottom:18px; border-color:#224631; color:var(--ok-text);">
                <?= htmlspecialchars($pesan_sukses) ?>
            </div>
        <?php endif; ?>
        <?php if ($pesan_error): ?>
            <div class="alert-err"><?= htmlspecialchars($pesan_error) ?></div>
        <?php endif; ?>

        <?php if ($edit_mode && $data_edit): ?>
            <!-- FORM EDIT KATEGORI -->
            <div class="info-box" style="margin-top:0; margin-bottom:24px; border-color:var(--border-subtle);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                    <div class="label-heading" style="margin-bottom:0; color:var(--text-main);">
                        Edit Kategori: <?= htmlspecialchars($data_edit['nama_kategori']) ?>
                    </div>
                    <a href="kelola_kategori.php" style="font-size:13px; color:var(--text-muted);">Batal Edit ✕</a>
                </div>

                <form method="POST" action="kelola_kategori.php">
                    <input type="hidden" name="id_kategori" value="<?= (int)$data_edit['id_kategori'] ?>">

                    <div class="form-group">
                        <label class="form-label">Nama Kategori</label>
                        <input type="text" name="nama_kategori" class="form-control" value="<?= htmlspecialchars($data_edit['nama_kategori']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Deskripsi / Keterangan Kategori</label>
                        <textarea name="deskripsi" rows="3" class="form-control"><?= htmlspecialchars($data_edit['deskripsi'] ?? '') ?></textarea>
                    </div>

                    <div style="display:flex; gap:10px; margin-top:8px;">
                        <button type="submit" name="edit_kategori" class="btn-primary-green btn-inline">Simpan Perubahan</button>
                        <a href="kelola_kategori.php" class="btn-outline-green btn-inline">Batal</a>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <!-- FORM TAMBAH KATEGORI -->
            <form method="POST" action="kelola_kategori.php" style="margin-bottom:24px;">
                <div class="label-heading">Tambah Kategori Baru</div>

                <div class="form-group">
                    <label class="form-label">Nama Kategori</label>
                    <input type="text" name="nama_kategori" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Deskripsi / Keterangan Kategori</label>
                    <textarea name="deskripsi" rows="2" class="form-control"></textarea>
                </div>

                <button type="submit" name="tambah_kategori" class="btn-primary-green btn-inline">Simpan Kategori</button>
            </form>
        <?php endif; ?>

        <!-- TABEL DAFTAR KATEGORI -->
        <div class="label-heading">Daftar Kategori</div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:60px;">ID</th>
                        <th>Nama Kategori</th>
                        <th>Keterangan</th>
                        <th style="width:140px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($kategoris && mysqli_num_rows($kategoris) > 0): ?>
                        <?php while ($k = mysqli_fetch_assoc($kategoris)): ?>
                            <tr>
                                <td><?= (int)$k['id_kategori'] ?></td>
                                <td style="font-weight:600;"><?= htmlspecialchars($k['nama_kategori']) ?></td>
                                <td style="color:var(--text-muted);"><?= htmlspecialchars($k['deskripsi'] ?? '-') ?></td>
                                <td>
                                    <div style="display:flex; gap:6px;">
                                        <a href="kelola_kategori.php?edit=<?= (int)$k['id_kategori'] ?>" class="btn-outline-green btn-sm">
                                            Edit
                                        </a>
                                        <a href="kelola_kategori.php?hapus=<?= (int)$k['id_kategori'] ?>" class="btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus kategori ini?')">
                                            Hapus
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align:center; color:var(--text-muted); padding:20px;">Belum ada kategori alat.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
