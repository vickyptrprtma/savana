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
        $pesan_sukses = 'Perlengkapan alat baru berhasil ditambahkan.';
    } elseif ($_GET['pesan'] === 'edit_sukses') {
        $pesan_sukses = 'Perubahan data alat berhasil disimpan.';
    } elseif ($_GET['pesan'] === 'hapus_sukses') {
        $pesan_sukses = 'Data alat berhasil dihapus.';
    }
}

// Proses Tambah Alat
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_alat'])) {
    $nama_alat   = mysqli_real_escape_string($conn, trim($_POST['nama_alat'] ?? ''));
    $kategori_id = (int)($_POST['kategori_id'] ?? 0);
    $harga_sewa  = max(0, (float)($_POST['harga_sewa'] ?? 0));
    $stok_total  = max(0, (int)($_POST['stok'] ?? 0));
    $deskripsi   = mysqli_real_escape_string($conn, trim($_POST['deskripsi'] ?? ''));

    if ($nama_alat === '' || $kategori_id <= 0) {
        $pesan_error = 'Nama alat dan kategori wajib diisi.';
    } else {
        $gambar = '';
        if (!empty($_FILES['gambar']['name']) && $_FILES['gambar']['error'] === 0) {
            $ext = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $gambar = 'alat_' . time() . '_' . rand(100, 999) . '.' . $ext;
                @move_uploaded_file($_FILES['gambar']['tmp_name'], __DIR__ . '/../assets/uploads/alat/' . $gambar);
            }
        }

        $ins = mysqli_query($conn, "INSERT INTO alat (id_kategori, nama_alat, deskripsi, harga_sewa, stok_total, foto_alat)
            VALUES ($kategori_id, '$nama_alat', '$deskripsi', $harga_sewa, $stok_total, '$gambar')");

        if ($ins) {
            header('Location: kelola_alat.php?pesan=tambah_sukses');
            exit;
        } else {
            $pesan_error = 'Gagal menambahkan alat: ' . mysqli_error($conn);
        }
    }
}

// Proses Edit Alat
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_alat'])) {
    $id_alat     = (int)($_POST['id_alat'] ?? 0);
    $nama_alat   = mysqli_real_escape_string($conn, trim($_POST['nama_alat'] ?? ''));
    $kategori_id = (int)($_POST['kategori_id'] ?? 0);
    $harga_sewa  = max(0, (float)($_POST['harga_sewa'] ?? 0));
    $stok_total  = max(0, (int)($_POST['stok'] ?? 0));
    $deskripsi   = mysqli_real_escape_string($conn, trim($_POST['deskripsi'] ?? ''));

    if ($id_alat <= 0 || $nama_alat === '' || $kategori_id <= 0) {
        $pesan_error = 'ID alat, nama alat, dan kategori wajib diisi secara valid.';
    } else {
        $sql_foto_update = '';
        if (!empty($_FILES['gambar']['name']) && $_FILES['gambar']['error'] === 0) {
            $ext = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $gambar = 'alat_' . time() . '_' . rand(100, 999) . '.' . $ext;
                if (@move_uploaded_file($_FILES['gambar']['tmp_name'], __DIR__ . '/../assets/uploads/alat/' . $gambar)) {
                    $sql_foto_update = ", foto_alat = '$gambar'";
                }
            }
        }

        $up = mysqli_query($conn, "UPDATE alat
            SET nama_alat = '$nama_alat',
                id_kategori = $kategori_id,
                harga_sewa = $harga_sewa,
                stok_total = $stok_total,
                deskripsi = '$deskripsi'
                $sql_foto_update
            WHERE id_alat = $id_alat");

        if ($up) {
            header('Location: kelola_alat.php?pesan=edit_sukses');
            exit;
        } else {
            $pesan_error = 'Gagal memperbarui alat: ' . mysqli_error($conn);
        }
    }
}

// Proses Hapus Alat
if (isset($_GET['hapus'])) {
    $id_h = (int)$_GET['hapus'];
    $del = mysqli_query($conn, "DELETE FROM alat WHERE id_alat = $id_h");
    if ($del) {
        header('Location: kelola_alat.php?pesan=hapus_sukses');
        exit;
    } else {
        $pesan_error = 'Gagal menghapus alat (mungkin masih terkait dengan data pemesanan): ' . mysqli_error($conn);
    }
}

// Cek Mode Edit
$edit_mode = false;
$data_edit = null;
if (isset($_GET['edit'])) {
    $id_e = (int)$_GET['edit'];
    $qe   = mysqli_query($conn, "SELECT * FROM alat WHERE id_alat = $id_e LIMIT 1");
    if ($qe && mysqli_num_rows($qe) > 0) {
        $edit_mode = true;
        $data_edit = mysqli_fetch_assoc($qe);
    }
}

$kategoris = mysqli_query($conn, "SELECT * FROM kategori_alat ORDER BY nama_kategori ASC");
$alats     = mysqli_query($conn, "SELECT a.*, k.nama_kategori
    FROM alat a
    JOIN kategori_alat k ON a.id_kategori = k.id_kategori
    ORDER BY a.id_alat DESC");

include __DIR__ . '/../includes/header.php';
?>

<div class="container-wide">
    <div class="card-shell">
        <h2 style="font-size:20px; margin-bottom:14px;">Kelola Perlengkapan Alat Outdoor</h2>

        <?php if ($pesan_sukses): ?>
            <div class="info-box" style="margin-top:0; margin-bottom:18px; border-color:#224631; color:var(--ok-text);">
                <?= htmlspecialchars($pesan_sukses) ?>
            </div>
        <?php endif; ?>
        <?php if ($pesan_error): ?>
            <div class="alert-err"><?= htmlspecialchars($pesan_error) ?></div>
        <?php endif; ?>

        <?php if ($edit_mode && $data_edit): ?>
            <!-- FORM EDIT ALAT -->
            <div class="info-box" style="margin-top:0; margin-bottom:24px; border-color:var(--border-subtle);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                    <div class="label-heading" style="margin-bottom:0; color:var(--text-main);">
                        Edit Alat: <?= htmlspecialchars($data_edit['nama_alat']) ?>
                    </div>
                    <a href="kelola_alat.php" style="font-size:13px; color:var(--text-muted);">Batal Edit ✕</a>
                </div>

                <form method="POST" action="kelola_alat.php" enctype="multipart/form-data">
                    <input type="hidden" name="id_alat" value="<?= (int)$data_edit['id_alat'] ?>">

                    <div class="form-group">
                        <label class="form-label">Nama Alat</label>
                        <input type="text" name="nama_alat" class="form-control" value="<?= htmlspecialchars($data_edit['nama_alat']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Kategori</label>
                        <select name="kategori_id" class="form-control" required>
                            <option value="">-- Pilih Kategori --</option>
                            <?php
                            mysqli_data_seek($kategoris, 0);
                            while ($k = mysqli_fetch_assoc($kategoris)):
                            ?>
                                <option value="<?= (int)$k['id_kategori'] ?>" <?= (int)$data_edit['id_kategori'] === (int)$k['id_kategori'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($k['nama_kategori']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                        <div class="form-group">
                            <label class="form-label">Harga Sewa / Hari (Rp)</label>
                            <input type="number" name="harga_sewa" class="form-control" value="<?= (float)$data_edit['harga_sewa'] ?>" required min="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Stok Total (Unit)</label>
                            <input type="number" name="stok" class="form-control" value="<?= (int)$data_edit['stok_total'] ?>" required min="0">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Foto Alat (Pilih file baru jika ingin mengganti)</label>
                        <?php if (!empty($data_edit['foto_alat'])): ?>
                            <div style="margin-bottom:8px; display:flex; align-items:center; gap:10px;">
                                <img src="/savana_outdoor/assets/uploads/alat/<?= htmlspecialchars($data_edit['foto_alat']) ?>" alt="Foto" style="width:50px; height:50px; object-fit:cover; border-radius:6px; border:1px solid var(--border-subtle);">
                                <span style="font-size:12.5px; color:var(--text-muted);">Foto saat ini: <?= htmlspecialchars($data_edit['foto_alat']) ?></span>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="gambar" accept="image/*" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Deskripsi & Detail Alat</label>
                        <textarea name="deskripsi" rows="3" class="form-control"><?= htmlspecialchars($data_edit['deskripsi'] ?? '') ?></textarea>
                    </div>

                    <div style="display:flex; gap:10px; margin-top:8px;">
                        <button type="submit" name="edit_alat" class="btn-primary-green btn-inline">Simpan Perubahan</button>
                        <a href="kelola_alat.php" class="btn-outline-green btn-inline">Batal</a>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <!-- FORM TAMBAH ALAT -->
            <form method="POST" action="kelola_alat.php" enctype="multipart/form-data" style="margin-bottom:28px;">
                <div class="label-heading">Tambah Alat Baru</div>

                <div class="form-group">
                    <label class="form-label">Nama Alat</label>
                    <input type="text" name="nama_alat" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Kategori</label>
                    <select name="kategori_id" class="form-control" required>
                        <option value="">-- Pilih Kategori --</option>
                        <?php
                        mysqli_data_seek($kategoris, 0);
                        while ($k = mysqli_fetch_assoc($kategoris)):
                        ?>
                            <option value="<?= (int)$k['id_kategori'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                    <div class="form-group">
                        <label class="form-label">Harga Sewa / Hari (Rp)</label>
                        <input type="number" name="harga_sewa" class="form-control" value="0" required min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Stok Total (Unit)</label>
                        <input type="number" name="stok" class="form-control" value="1" required min="0">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Foto Alat</label>
                    <input type="file" name="gambar" accept="image/*" class="form-control">
                </div>

                <div class="form-group">
                    <label class="form-label">Deskripsi & Detail Alat</label>
                    <textarea name="deskripsi" rows="2" class="form-control"></textarea>
                </div>

                <button type="submit" name="tambah_alat" class="btn-primary-green btn-inline">Tambah Alat</button>
            </form>
        <?php endif; ?>

        <!-- TABEL DAFTAR ALAT -->
        <div class="label-heading">Daftar Seluruh Alat Outdoor</div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>Nama Alat</th>
                        <th>Kategori</th>
                        <th>Harga / Hari</th>
                        <th>Stok</th>
                        <th style="width:140px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($alats && mysqli_num_rows($alats) > 0): ?>
                        <?php while ($a = mysqli_fetch_assoc($alats)): ?>
                            <tr>
                                <td style="width:60px;">
                                    <?php if (!empty($a['foto_alat'])): ?>
                                        <img src="/savana_outdoor/assets/uploads/alat/<?= htmlspecialchars($a['foto_alat']) ?>" alt="" style="width:44px; height:44px; object-fit:contain; background:#070a08; border-radius:6px; border:1px solid var(--border-subtle);">
                                    <?php else: ?>
                                        <span style="color:var(--text-muted); font-size:11px;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-weight:600;"><?= htmlspecialchars($a['nama_alat']) ?></td>
                                <td><span class="badge badge-muted"><?= htmlspecialchars($a['nama_kategori']) ?></span></td>
                                <td style="color:var(--green-accent); font-weight:600;"><?= rupiah($a['harga_sewa']) ?></td>
                                <td><?= (int)$a['stok_total'] ?> unit</td>
                                <td>
                                    <div style="display:flex; gap:6px;">
                                        <a href="kelola_alat.php?edit=<?= (int)$a['id_alat'] ?>" class="btn-outline-green btn-sm">
                                            Edit
                                        </a>
                                        <a href="kelola_alat.php?hapus=<?= (int)$a['id_alat'] ?>" class="btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus alat ini?')">
                                            Hapus
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center; color:var(--text-muted); padding:20px;">Belum ada data alat.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
