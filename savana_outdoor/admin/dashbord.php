<?php
require_once __DIR__ . '/../config/koneksi.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$admin_id_aktif = (int)$_SESSION['admin_id'];
$pesan_sukses   = '';
$pesan_error    = '';

/*
 * Opsi perhitungan durasi sewa inklusif (sama dengan boking.php & cek_pesanan.php):
 * true = inklusif tanggal mulai & tanggal kembali (misal: 10 s.d. 12 = 3 hari, minimal 1 hari)
 */
$hitung_inklusif_hari = true;

// Proses Form Verifikasi Pesanan dengan Transaksi Database
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verifikasi_pesanan'])) {
    $id_p      = (int)($_POST['id_pesanan'] ?? 0);
    $keputusan = trim($_POST['keputusan'] ?? '');
    $catatan   = trim($_POST['catatan'] ?? '');

    if ($id_p <= 0 || !in_array($keputusan, ['Disetujui', 'Ditolak', 'Selesai'], true)) {
        $pesan_error = 'Pilih keputusan verifikasi yang valid (Disetujui, Ditolak, atau Selesai).';
    } else {
        $q_cek = mysqli_query($conn, "SELECT p.status_pemesanan, dp.id_alat, COALESCE(dp.jumlah, 1) AS jumlah
            FROM pemesanan p
            LEFT JOIN detail_pemesanan dp ON dp.id_pemesanan = p.id_pemesanan
            WHERE p.id_pemesanan = $id_p
            LIMIT 1");
        $row_cek = $q_cek ? mysqli_fetch_assoc($q_cek) : null;

        if (!$row_cek) {
            $pesan_error = 'Data pemesanan tidak ditemukan.';
        } else {
            mysqli_begin_transaction($conn);
            try {
                $kep_sql = mysqli_real_escape_string($conn, $keputusan);
                $cat_sql = mysqli_real_escape_string($conn, $catatan);

                // 1. Catat log ke tabel status_pesanan
                $ins_log = mysqli_query($conn, "INSERT INTO status_pesanan (id_pemesanan, id_admin, keputusan, catatan, tanggal_verifikasi)
                    VALUES ($id_p, $admin_id_aktif, '$kep_sql', '$cat_sql', NOW())");
                if (!$ins_log) {
                    throw new Exception('Gagal menyimpan log verifikasi: ' . mysqli_error($conn));
                }

                if ($keputusan === 'Disetujui') {
                    $status_baru = 'Menunggu Pengambilan';
                    // Mencegah pengurangan ganda: hanya kurangi stok jika status sebelumnya belum 'Menunggu Pengambilan', 'Sedang Disewa', atau 'Selesai'
                    if (!empty($row_cek['id_alat']) && !in_array($row_cek['status_pemesanan'], ['Menunggu Pengambilan', 'Sedang Disewa', 'Selesai'], true)) {
                        $id_alat_p = (int)$row_cek['id_alat'];
                        $jml_p     = (int)$row_cek['jumlah'];
                        $up_stok   = mysqli_query($conn, "UPDATE alat
                            SET stok_total = GREATEST(0, CAST(stok_total AS SIGNED) - $jml_p)
                            WHERE id_alat = $id_alat_p");
                        if (!$up_stok) {
                            throw new Exception('Gagal memperbarui stok alat: ' . mysqli_error($conn));
                        }
                    }
                } elseif ($keputusan === 'Selesai') {
                    $status_baru = 'Selesai';
                    // Tambahkan kembali stok sesuai jumlah unit, cegah pengembalian ganda jika status sebelumnya sudah 'Selesai'
                    if (!empty($row_cek['id_alat']) && $row_cek['status_pemesanan'] !== 'Selesai') {
                        $id_alat_p = (int)$row_cek['id_alat'];
                        $jml_p     = (int)$row_cek['jumlah'];
                        $up_stok   = mysqli_query($conn, "UPDATE alat
                            SET stok_total = stok_total + $jml_p
                            WHERE id_alat = $id_alat_p");
                        if (!$up_stok) {
                            throw new Exception('Gagal mengembalikan stok alat: ' . mysqli_error($conn));
                        }
                    }
                } else {
                    $status_baru = 'Ditolak';
                }

                // 2. Ubah status_pemesanan
                $st_sql    = mysqli_real_escape_string($conn, $status_baru);
                $extra_tgl = ($keputusan === 'Selesai') ? ", tanggal_pengembalian_aktual = NOW()" : "";
                $up_pem    = mysqli_query($conn, "UPDATE pemesanan
                    SET status_pemesanan = '$st_sql' $extra_tgl
                    WHERE id_pemesanan = $id_p");
                if (!$up_pem) {
                    throw new Exception('Gagal memperbarui status pemesanan: ' . mysqli_error($conn));
                }

                mysqli_commit($conn);
                header("Location: dashbord.php?detail=$id_p&verified=1");
                exit;
            } catch (Throwable $e) {
                mysqli_rollback($conn);
                $pesan_error = 'Gagal menyimpan verifikasi: ' . $e->getMessage();
            }
        }
    }
}

// Proses Update Manual Status & Pembayaran
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $id_p          = (int)($_POST['id_pesanan'] ?? 0);
    $st_p          = mysqli_real_escape_string($conn, trim($_POST['status_pemesanan'] ?? ''));
    $st_b          = mysqli_real_escape_string($conn, trim($_POST['status_pembayaran'] ?? ''));
    $nominal_bayar = (int)($_POST['nominal_bayar'] ?? 0);

    if ($id_p > 0 && $st_p !== '' && $st_b !== '') {
        mysqli_query($conn, "UPDATE pemesanan
            SET status_pemesanan = '$st_p',
                status_pembayaran = '$st_b'
            WHERE id_pemesanan = $id_p");

        if ($nominal_bayar > 0) {
            mysqli_query($conn, "INSERT INTO pembayaran (id_pemesanan, id_admin, bukti_pembayaran, jumlah_pembayaran, tanggal_pembayaran)
                VALUES ($id_p, $admin_id_aktif, '', $nominal_bayar, CURDATE())");
        }

        header("Location: dashbord.php?detail=$id_p&updated=1");
        exit;
    }
}

if (isset($_GET['verified'])) {
    $pesan_sukses = 'Verifikasi pesanan berhasil dicatat dan status telah diperbarui.';
} elseif (isset($_GET['updated'])) {
    $pesan_sukses = 'Status pemesanan dan pembayaran berhasil diperbarui.';
}

// Hitung statistik dashboard
$q_masuk = mysqli_query($conn, "SELECT COUNT(*) AS jml FROM pemesanan WHERE status_pemesanan LIKE 'Menunggu Konfirmasi%'");
$masuk = (int)(mysqli_fetch_assoc($q_masuk)['jml'] ?? 0);

$q_sewa = mysqli_query($conn, "SELECT COUNT(*) AS jml FROM pemesanan WHERE status_pemesanan = 'Sedang Disewa'");
$sedang_disewa = (int)(mysqli_fetch_assoc($q_sewa)['jml'] ?? 0);

// Ambil data detail jika parameter ?detail=id diberikan
$detail_row        = null;
$total_terbayar    = 0;
$log_verifikasi    = [];
$bukti_bayar_file  = '';

if (isset($_GET['detail'])) {
    $id_d = (int)$_GET['detail'];
    $qd = mysqli_query($conn, "SELECT p.*, pl.nama AS nama_user, pl.no_hp, pl.jenis_identitas, pl.nomor_identitas, pl.alamat, pl.foto_identitas,
            a.nama_alat, a.foto_alat, COALESCE(dp.jumlah, 1) AS jumlah, COALESCE(dp.harga_sewa, a.harga_sewa, 0) AS harga_sewa
            FROM pemesanan p
            JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
            LEFT JOIN detail_pemesanan dp ON dp.id_pemesanan = p.id_pemesanan
            LEFT JOIN alat a ON dp.id_alat = a.id_alat
            WHERE p.id_pemesanan = $id_d
            LIMIT 1");

    if ($qd && mysqli_num_rows($qd) > 0) {
        $detail_row = mysqli_fetch_assoc($qd);

        $qb = mysqli_query($conn, "SELECT COALESCE(SUM(jumlah_pembayaran), 0) AS total_byr FROM pembayaran WHERE id_pemesanan = $id_d");
        $total_terbayar = (int)(mysqli_fetch_assoc($qb)['total_byr'] ?? 0);

        // Ambil bukti pembayaran jika ada
        $qp = mysqli_query($conn, "SELECT bukti_pembayaran FROM pembayaran WHERE id_pemesanan = $id_d AND bukti_pembayaran != '' ORDER BY id_pembayaran DESC LIMIT 1");
        if ($qp && mysqli_num_rows($qp) > 0) {
            $bukti_bayar_file = mysqli_fetch_assoc($qp)['bukti_pembayaran'];
        }

        // Ambil riwayat verifikasi
        $qv = mysqli_query($conn, "SELECT sp.*, ad.nama_admin
            FROM status_pesanan sp
            LEFT JOIN admin ad ON sp.id_admin = ad.id_admin
            WHERE sp.id_pemesanan = $id_d
            ORDER BY sp.id_verifikasi DESC");
        if ($qv) {
            while ($v = mysqli_fetch_assoc($qv)) {
                $log_verifikasi[] = $v;
            }
        }
    }
}

// Ambil riwayat seluruh pemesanan
$riwayat = mysqli_query($conn, "SELECT p.*, pl.nama AS nama_user,
        a.nama_alat, COALESCE(dp.jumlah, 1) AS jumlah, COALESCE(dp.harga_sewa, a.harga_sewa, 0) AS harga_sewa
        FROM pemesanan p
        JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
        LEFT JOIN detail_pemesanan dp ON dp.id_pemesanan = p.id_pemesanan
        LEFT JOIN alat a ON dp.id_alat = a.id_alat
        ORDER BY p.id_pemesanan DESC");

function badgeAdmin($st) {
    if ($st === 'Selesai') return 'badge-ok';
    if ($st === 'Ditolak') return 'badge-danger';
    if ($st === 'Dibatalkan') return 'badge-muted';
    if ($st === 'Sedang Disewa' || $st === 'Menunggu Pengambilan') return 'badge-info';
    return 'badge-warn';
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <?php if ($pesan_sukses): ?>
        <div class="info-box" style="margin-top:0; margin-bottom:18px; border-color:#224631; color:var(--ok-text);">
            <?= htmlspecialchars($pesan_sukses) ?>
        </div>
    <?php endif; ?>
    <?php if ($pesan_error): ?>
        <div class="alert-err" style="margin-bottom:18px;"><?= htmlspecialchars($pesan_error) ?></div>
    <?php endif; ?>

    <div class="card-shell">
        <h2 style="font-size:20px;">Dashboard Admin</h2>
        <div style="font-size:12px; letter-spacing:0.8px; color:var(--text-muted); margin-top:2px;">SAVANA OUTDOOR</div>

        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-label">Pesanan Masuk (Perlu Verifikasi)</div>
                <div class="stat-num"><?= str_pad($masuk, 2, '0', STR_PAD_LEFT) ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Sedang Disewa</div>
                <div class="stat-num"><?= str_pad($sedang_disewa, 2, '0', STR_PAD_LEFT) ?></div>
            </div>
        </div>

        <div class="label-heading" style="margin-top:20px;">↺ Riwayat Pemesanan & Rincian Tagihan</div>

        <?php if ($riwayat && mysqli_num_rows($riwayat) > 0): ?>
            <?php while ($r = mysqli_fetch_assoc($riwayat)): ?>
                <?php
                $durasi_r = hitungDurasiHari($r['tanggal_mulai'], $r['tanggal_kembali'], $hitung_inklusif_hari);
                $rincian_r = hitungRincianHarga($r['harga_sewa'] ?? 0, $durasi_r, $r['jumlah'] ?? 1);
                $tagihan_r = (isset($r['total_tagihan_akhir']) && $r['total_tagihan_akhir'] !== null)
                    ? (float)$r['total_tagihan_akhir']
                    : $rincian_r['total_tagihan_akhir'];
                ?>
                <a href="dashbord.php?detail=<?= (int)$r['id_pemesanan'] ?>#panelDetail" class="history-item" style="display:block; text-decoration:none; margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px;">
                        <div>
                            <div class="history-code"><?= htmlspecialchars($r['kode_pemesanan']) ?></div>
                            <div class="history-sub" style="font-weight:600; color:var(--text-main); font-size:14px; margin-top:2px;"><?= htmlspecialchars($r['nama_user']) ?></div>
                            <div class="history-item-main" style="margin-top:2px;"><?= htmlspecialchars($r['nama_alat'] ?? '-') ?> · Periode: <?= tgl_indo($r['tanggal_mulai']) ?> s.d. <?= tgl_indo($r['tanggal_kembali']) ?></div>
                        </div>
                        <span class="badge <?= badgeAdmin($r['status_pemesanan']) ?>"><?= htmlspecialchars($r['status_pemesanan']) ?></span>
                    </div>

                    <!-- Rincian Lengkap 4 Baris pada Riwayat -->
                    <div style="background:rgba(0,0,0,0.25); border-radius:8px; padding:8px 12px; margin-top:10px; font-size:12.5px; border:1px solid var(--border-subtle);">
                        <div style="display:flex; justify-content:space-between; color:var(--text-muted); padding:2px 0;">
                            <span>Harga Satuan:</span>
                            <span><?= rupiah($rincian_r['harga_satuan']) ?></span>
                        </div>
                        <div style="display:flex; justify-content:space-between; color:var(--text-muted); padding:2px 0;">
                            <span>Biaya Masa Sewa (<?= $rincian_r['masa_sewa'] ?> hari × <?= rupiah($rincian_r['harga_satuan']) ?>):</span>
                            <span><?= rupiah($rincian_r['biaya_masa_sewa']) ?></span>
                        </div>
                        <div style="display:flex; justify-content:space-between; color:var(--text-muted); padding:2px 0;">
                            <span>Biaya Jumlah Sewa (<?= $rincian_r['jumlah_unit'] ?> unit × <?= rupiah($rincian_r['harga_satuan']) ?>):</span>
                            <span><?= rupiah($rincian_r['biaya_jumlah_sewa']) ?></span>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-weight:700; color:var(--green-accent); border-top:1px dashed var(--border-subtle); margin-top:4px; padding-top:4px;">
                            <span>Total Tagihan Akhir:</span>
                            <span><?= rupiah($tagihan_r) ?></span>
                        </div>
                    </div>
                </a>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="color:var(--text-muted); font-size:14px; margin-top:12px;">Belum ada data pemesanan.</p>
        <?php endif; ?>
    </div>

    <!-- Panel Detail & Verifikasi Pemesanan -->
    <?php if ($detail_row): ?>
        <?php
        $masa_admin    = hitungDurasiHari($detail_row['tanggal_mulai'], $detail_row['tanggal_kembali'], $hitung_inklusif_hari);
        $rincian_admin = hitungRincianHarga($detail_row['harga_sewa'] ?? 0, $masa_admin, $detail_row['jumlah'] ?? 1);
        $total_tagihan = (isset($detail_row['total_tagihan_akhir']) && $detail_row['total_tagihan_akhir'] !== null)
            ? (float)$detail_row['total_tagihan_akhir']
            : $rincian_admin['total_tagihan_akhir'];
        $sisa_tagihan  = max(0, $total_tagihan - $total_terbayar);
        ?>
        <div class="card-shell" style="margin-top:24px;" id="panelDetail">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:14px;">
                <div class="label-heading" style="margin-bottom:0;">Detail & Verifikasi Pemesanan</div>
                <a href="dashbord.php" style="font-size:13px; color:var(--text-muted);">Tutup ✕</a>
            </div>

            <span class="badge <?= badgeAdmin($detail_row['status_pemesanan']) ?>" style="margin-bottom:16px;">
                Status: <?= htmlspecialchars($detail_row['status_pemesanan']) ?>
            </span>

            <div class="detail-stack-item">
                <div class="k">Nomor Pemesanan</div>
                <div class="v" style="font-weight:700;"><?= htmlspecialchars($detail_row['kode_pemesanan']) ?></div>
            </div>
            <div class="detail-stack-item">
                <div class="k">Nama Penyewa & Kontak</div>
                <div class="v"><?= htmlspecialchars($detail_row['nama_user']) ?> (<?= htmlspecialchars($detail_row['no_hp']) ?>)</div>
            </div>
            <div class="detail-stack-item">
                <div class="k">Identitas</div>
                <div class="v"><?= htmlspecialchars($detail_row['jenis_identitas']) ?> · <?= htmlspecialchars($detail_row['nomor_identitas']) ?></div>
            </div>
            <div class="detail-stack-item">
                <div class="k">Alat yang Disewa</div>
                <div class="v"><?= htmlspecialchars($detail_row['nama_alat'] ?? '-') ?></div>
            </div>
            <div class="detail-stack-item">
                <div class="k">Jumlah Unit & Harga Satuan</div>
                <div class="v"><?= $rincian_admin['jumlah_unit'] ?> unit · <?= rupiah($rincian_admin['harga_satuan']) ?> / hari</div>
            </div>
            <div class="detail-stack-item">
                <div class="k">Periode Sewa</div>
                <div class="v"><?= tgl_indo($detail_row['tanggal_mulai']) ?> s.d. <?= tgl_indo($detail_row['tanggal_kembali']) ?> (<?= $masa_admin ?> hari)</div>
            </div>

            <!-- Dokumentasi Foto (Tampil Utuh Tanpa Terpotong dengan object-fit: contain) -->
            <div style="margin-top:16px;">
                <div class="label-heading" style="margin-bottom:10px;">Dokumentasi & Foto</div>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px;">
                    <?php if (!empty($detail_row['foto_alat'])): ?>
                        <div class="dash-photo-box">
                            <div style="font-size:12px; color:var(--text-muted); margin-bottom:6px; font-weight:600;">Foto Alat</div>
                            <img src="/savana_outdoor/assets/uploads/alat/<?= htmlspecialchars($detail_row['foto_alat']) ?>" alt="Foto Alat">
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($detail_row['foto_identitas'])): ?>
                        <div class="dash-photo-box">
                            <div style="font-size:12px; color:var(--text-muted); margin-bottom:6px; font-weight:600;">Foto Identitas Penyewa</div>
                            <img src="/savana_outdoor/assets/uploads/identitas/<?= htmlspecialchars($detail_row['foto_identitas']) ?>" alt="Foto Identitas">
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($bukti_bayar_file)): ?>
                        <div class="dash-photo-box">
                            <div style="font-size:12px; color:var(--text-muted); margin-bottom:6px; font-weight:600;">Bukti Pembayaran / DP</div>
                            <img src="/savana_outdoor/assets/uploads/pembayaran/<?= htmlspecialchars($bukti_bayar_file) ?>" alt="Bukti Pembayaran">
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Rincian Lengkap 4 Baris pada Panel Detail -->
            <div class="info-box" style="margin-top:16px;">
                <div class="label-heading">Rincian Perhitungan Harga Sewa</div>
                <div class="detail-row" style="padding:4px 0;">
                    <span class="k">Harga Satuan</span>
                    <span class="v"><?= rupiah($rincian_admin['harga_satuan']) ?></span>
                </div>
                <div class="detail-row" style="padding:4px 0;">
                    <span class="k">Biaya Masa Sewa (<?= $rincian_admin['masa_sewa'] ?> hari × <?= rupiah($rincian_admin['harga_satuan']) ?>)</span>
                    <span class="v"><?= rupiah($rincian_admin['biaya_masa_sewa']) ?></span>
                </div>
                <div class="detail-row" style="padding:4px 0;">
                    <span class="k">Biaya Jumlah Sewa (<?= $rincian_admin['jumlah_unit'] ?> unit × <?= rupiah($rincian_admin['harga_satuan']) ?>)</span>
                    <span class="v"><?= rupiah($rincian_admin['biaya_jumlah_sewa']) ?></span>
                </div>
                <div class="detail-row" style="padding:6px 0 2px; border-top:1px solid var(--border-subtle);">
                    <span class="k" style="font-weight:700;">Total Tagihan Akhir</span>
                    <span class="v" style="color:var(--green-accent); font-weight:700;"><?= rupiah($total_tagihan) ?></span>
                </div>
            </div>

            <!-- Form Verifikasi Pesanan -->
            <form method="POST" style="margin-top:20px; border-top:1px solid var(--border-subtle); padding-top:16px;">
                <div class="label-heading">Form Verifikasi Pesanan</div>
                <input type="hidden" name="id_pesanan" value="<?= (int)$detail_row['id_pemesanan'] ?>">
                <div class="form-group">
                    <label class="form-label">Keputusan Verifikasi</label>
                    <select name="keputusan" class="form-control" required>
                        <option value="Disetujui">Disetujui (Status menjadi 'Menunggu Pengambilan' & Kurangi Stok)</option>
                        <option value="Selesai">Selesai (Status menjadi 'Selesai' & Kembalikan Stok)</option>
                        <option value="Ditolak">Ditolak (Status menjadi 'Ditolak')</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Catatan Verifikasi (Opsional)</label>
                    <textarea name="catatan" rows="2" class="form-control"></textarea>
                </div>
                <button type="submit" name="verifikasi_pesanan" class="btn-primary-green btn-block">Simpan Keputusan Verifikasi</button>
            </form>

            <!-- Riwayat Verifikasi Pesanan -->
            <div class="detail-list" style="margin-top:22px;">
                <div class="label-heading">Riwayat Verifikasi Pesanan Ini</div>
                <?php if (!empty($log_verifikasi)): ?>
                    <?php foreach ($log_verifikasi as $lv): ?>
                        <div class="detail-stack-item" style="border-bottom:1px solid var(--border-subtle); padding-bottom:10px;">
                            <div class="k"><?= tgl_indo($lv['tanggal_verifikasi']) ?> <?= date('H:i', strtotime($lv['tanggal_verifikasi'])) ?> WIB · Oleh <?= htmlspecialchars($lv['nama_admin'] ?? 'Admin') ?></div>
                            <div class="v" style="margin-top:4px;">
                                <span class="badge <?= ($lv['keputusan'] === 'Disetujui' || $lv['keputusan'] === 'Selesai') ? 'badge-ok' : 'badge-danger' ?>">
                                    Keputusan: <?= htmlspecialchars($lv['keputusan']) ?>
                                </span>
                            </div>
                            <?php if (!empty($lv['catatan'])): ?>
                                <div class="history-sub" style="margin-top:4px; color:var(--text-main); font-style:italic;">
                                    "<?= htmlspecialchars($lv['catatan']) ?>"
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color:var(--text-muted); font-size:13.5px; padding:6px 0;">Belum ada catatan verifikasi untuk pesanan ini.</p>
                <?php endif; ?>
            </div>

            <!-- Status Pembayaran & Riwayat -->
            <div class="detail-list" style="margin-top:20px;">
                <div class="label-heading">Status Pembayaran</div>
                <div class="detail-stack-item">
                    <div class="k">Sudah Dibayar</div>
                    <div class="v"><?= rupiah($total_terbayar) ?></div>
                </div>
                <div class="detail-stack-item">
                    <div class="k">Sisa Tagihan</div>
                    <div class="v" style="color:var(--warn-text);"><?= rupiah($sisa_tagihan) ?></div>
                </div>
                <div class="detail-stack-item">
                    <div class="k">Total Tagihan Akhir</div>
                    <div class="v" style="font-weight:700; color:var(--green-accent);"><?= rupiah($total_tagihan) ?></div>
                </div>
                <span class="badge <?= ($detail_row['status_pembayaran'] === 'Pembayaran Lunas' || $detail_row['status_pembayaran'] === 'Lunas') ? 'badge-ok' : 'badge-warn' ?>" style="margin-top:6px;">
                    <?= htmlspecialchars($detail_row['status_pembayaran']) ?>
                </span>
            </div>

            <!-- Form Update Status Manual & Catat Pembayaran -->
            <form method="POST" style="margin-top:20px; border-top:1px solid var(--border-subtle); padding-top:16px;">
                <input type="hidden" name="id_pesanan" value="<?= (int)$detail_row['id_pemesanan'] ?>">
                <div class="form-group">
                    <label class="form-label">Perbarui Status Pemesanan Manual</label>
                    <select name="status_pemesanan" class="form-control">
                        <?php foreach (['Menunggu Konfirmasi', 'Menunggu Pengambilan', 'Sedang Disewa', 'Selesai', 'Ditolak', 'Dibatalkan'] as $opt): ?>
                            <option value="<?= $opt ?>" <?= $detail_row['status_pemesanan'] === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Perbarui Status Pembayaran Manual</label>
                    <select name="status_pembayaran" class="form-control">
                        <?php foreach (['Belum Bayar', 'DP', 'Lunas', 'Pembayaran Lunas'] as $optb): ?>
                            <option value="<?= $optb ?>" <?= $detail_row['status_pembayaran'] === $optb ? 'selected' : '' ?>><?= $optb ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Catat Pembayaran Baru (Rp - Opsional)</label>
                    <input type="number" name="nominal_bayar" class="form-control" value="0" min="0">
                </div>
                <button type="submit" name="update_status" class="btn-primary-green btn-block">Simpan Perubahan Manual</button>
            </form>
        </div>
    <?php endif; ?>

   
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
