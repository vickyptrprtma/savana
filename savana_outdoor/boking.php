<?php
require_once __DIR__ . '/config/koneksi.php';
include __DIR__ . '/includes/header.php';

$error = '';
$pesanan_sukses = null;

/*
 * Opsi perhitungan durasi sewa:
 * true = inklusif tanggal mulai & tanggal kembali (misal: 10 s.d. 12 = 3 hari, minimal 1 hari)
 * false = selisih hari biasa (misal: 10 s.d. 12 = 2 hari, minimal 1 hari)
 */
$hitung_inklusif_hari = true;

// Nilai default form untuk mempertahankan input saat terjadi error validasi
$val_nama_user       = '';
$val_jenis_id        = 'KTP';
$val_no_id           = '';
$val_no_hp           = '';
$val_email           = '';
$val_alamat          = '';
$val_alat_id         = isset($_POST['alat_id']) ? (int)$_POST['alat_id'] : (isset($_GET['alat_id']) ? (int)$_GET['alat_id'] : 0);
$val_jumlah          = 1;
$val_tanggal_sewa    = '';
$val_tanggal_kembali = '';
$val_setuju          = false;

if (isset($_GET['sukses'])) {
    $kode_get = mysqli_real_escape_string($conn, trim($_GET['sukses']));
    $q = mysqli_query($conn, "SELECT p.*, pl.nama AS nama_user, pl.no_hp,
            dp.jumlah, dp.harga_sewa, a.nama_alat
            FROM pemesanan p
            JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
            LEFT JOIN detail_pemesanan dp ON dp.id_pemesanan = p.id_pemesanan
            LEFT JOIN alat a ON dp.id_alat = a.id_alat
            WHERE p.kode_pemesanan = '$kode_get'
            LIMIT 1");
    if ($q && mysqli_num_rows($q) > 0) {
        $pesanan_sukses = mysqli_fetch_assoc($q);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_booking'])) {
    $val_nama_user       = trim($_POST['nama_user'] ?? '');
    $val_jenis_id        = trim($_POST['jenis_identitas'] ?? 'KTP');
    $val_no_id           = trim($_POST['nomor_identitas'] ?? '');
    $val_no_hp           = trim($_POST['no_hp'] ?? '');
    $val_email           = trim($_POST['email'] ?? '');
    $val_alamat          = trim($_POST['alamat'] ?? '');
    $val_alat_id         = (int)($_POST['alat_id'] ?? 0);
    $val_jumlah          = max(1, (int)($_POST['jumlah'] ?? 1));
    $val_tanggal_sewa    = trim($_POST['tanggal_sewa'] ?? '');
    $val_tanggal_kembali = trim($_POST['tanggal_kembali'] ?? '');
    $val_setuju          = isset($_POST['persetujuan_syarat']);

    // Blokir pengiriman booking jika belum memilih barang
    if ($val_alat_id <= 0) {
        $error = 'Wajib memilih barang outdoor yang akan disewa terlebih dahulu. Silakan pilih perlengkapan dari katalog.';
    } elseif ($val_nama_user === '' || $val_no_hp === '' || $val_tanggal_sewa === '' || $val_tanggal_kembali === '') {
        $error = 'Mohon lengkapi nama penyewa, nomor HP, serta tanggal sewa dan kembali.';
    } elseif ($val_tanggal_kembali < $val_tanggal_sewa) {
        $error = 'Tanggal kembali tidak boleh lebih awal dari tanggal sewa.';
    } elseif (!$val_setuju) {
        $error = 'Anda harus menyetujui syarat & ketentuan sewa Savana Outdoor.';
    } else {
        $res_alat = mysqli_query($conn, "SELECT * FROM alat WHERE id_alat = $val_alat_id LIMIT 1");
        $alat_row = $res_alat ? mysqli_fetch_assoc($res_alat) : null;

        if (!$alat_row) {
            $error = 'Barang outdoor yang dipilih tidak ditemukan.';
        } elseif ((int)$alat_row['stok_total'] <= 0) {
            $error = 'Stok untuk barang "' . htmlspecialchars($alat_row['nama_alat']) . '" sedang habis. Silakan pilih barang lain dari katalog.';
        } elseif ($val_jumlah > (int)$alat_row['stok_total']) {
            $error = 'Jumlah sewa (' . $val_jumlah . ' unit) melebihi stok yang tersedia saat ini (' . (int)$alat_row['stok_total'] . ' unit). Silakan masukkan maksimal ' . (int)$alat_row['stok_total'] . ' unit.';
        } else {
            // Hitung durasi hari secara aman menggunakan DateTime::diff dengan opsi inklusif
            $durasi_hari = hitungDurasiHari($val_tanggal_sewa, $val_tanggal_kembali, $hitung_inklusif_hari);
            // Perhitungan harga berurutan
            $rincian_biaya = hitungRincianHarga($alat_row['harga_sewa'], $durasi_hari, $val_jumlah);
            $harga_satuan = $rincian_biaya['harga_satuan'];
            $total_akhir  = $rincian_biaya['total_tagihan_akhir'];

            // Upload foto identitas jika ada
            $foto_identitas = '';
            if (!empty($_FILES['foto_identitas']['name']) && $_FILES['foto_identitas']['error'] === 0) {
                $ext = strtolower(pathinfo($_FILES['foto_identitas']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $foto_identitas = 'id_' . time() . '_' . rand(100, 999) . '.' . $ext;
                    @move_uploaded_file($_FILES['foto_identitas']['tmp_name'], __DIR__ . '/assets/uploads/identitas/' . $foto_identitas);
                }
            }

            // Upload bukti bayar jika ada
            $bukti_bayar = '';
            if (!empty($_FILES['bukti_bayar']['name']) && $_FILES['bukti_bayar']['error'] === 0) {
                $ext = strtolower(pathinfo($_FILES['bukti_bayar']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $bukti_bayar = 'byr_' . time() . '_' . rand(100, 999) . '.' . $ext;
                    @move_uploaded_file($_FILES['bukti_bayar']['tmp_name'], __DIR__ . '/assets/uploads/pembayaran/' . $bukti_bayar);
                }
            }

            $nama_user_esc   = mysqli_real_escape_string($conn, $val_nama_user);
            $jenis_id_esc    = mysqli_real_escape_string($conn, $val_jenis_id);
            $no_id_esc       = mysqli_real_escape_string($conn, $val_no_id);
            $no_hp_esc       = mysqli_real_escape_string($conn, $val_no_hp);
            $email_plg_esc   = mysqli_real_escape_string($conn, $val_email);
            $alamat_esc      = mysqli_real_escape_string($conn, $val_alamat);
            $tgl_sewa_sql    = mysqli_real_escape_string($conn, $val_tanggal_sewa);
            $tgl_kembali_sql = mysqli_real_escape_string($conn, $val_tanggal_kembali);

            mysqli_begin_transaction($conn);
            try {
                // Simpan atau perbarui data pelanggan berdasarkan nomor HP
                $q_plg = mysqli_query($conn, "SELECT id_pelanggan FROM pelanggan WHERE no_hp = '$no_hp_esc' LIMIT 1");
                if ($q_plg && mysqli_num_rows($q_plg) > 0) {
                    $id_pelanggan = (int)mysqli_fetch_assoc($q_plg)['id_pelanggan'];
                    $up_sql = "UPDATE pelanggan SET nama = '$nama_user_esc', alamat = '$alamat_esc', jenis_identitas = '$jenis_id_esc', nomor_identitas = '$no_id_esc', email = '$email_plg_esc'";
                    if ($foto_identitas !== '') {
                        $up_sql .= ", foto_identitas = '$foto_identitas'";
                    }
                    $up_sql .= " WHERE id_pelanggan = $id_pelanggan";
                    if (!mysqli_query($conn, $up_sql)) {
                        throw new Exception("Gagal memperbarui data pelanggan: " . mysqli_error($conn));
                    }
                } else {
                    $ins_plg = mysqli_query($conn, "INSERT INTO pelanggan (nama, jenis_identitas, nomor_identitas, alamat, no_hp, email, foto_identitas)
                        VALUES ('$nama_user_esc', '$jenis_id_esc', '$no_id_esc', '$alamat_esc', '$no_hp_esc', '$email_plg_esc', '$foto_identitas')");
                    if (!$ins_plg) {
                        throw new Exception("Gagal menyimpan data pelanggan: " . mysqli_error($conn));
                    }
                    $id_pelanggan = (int)mysqli_insert_id($conn);
                }

                $kode_baru = generateKodePesanan($conn);
                $status_awal = 'Menunggu Konfirmasi';
                $setuju_val = $val_setuju ? 1 : 0;

                // Simpan pemesanan beserta total_tagihan_akhir hasil perhitungan berurutan
                $ins_pesanan = mysqli_query($conn, "INSERT INTO pemesanan (
                    kode_pemesanan, id_pelanggan, tanggal_pemesanan, tanggal_mulai, tanggal_kembali,
                    status_pemesanan, status_pembayaran, total_tagihan_akhir, persetujuan_syarat
                ) VALUES (
                    '$kode_baru', $id_pelanggan, NOW(), '$tgl_sewa_sql', '$tgl_kembali_sql',
                    '$status_awal', 'Belum Bayar', $total_akhir, $setuju_val
                )");
                if (!$ins_pesanan) {
                    throw new Exception("Gagal menyimpan data pemesanan: " . mysqli_error($conn));
                }
                $id_pemesanan = (int)mysqli_insert_id($conn);

                // Simpan detail item pemesanan
                $ins_detail = mysqli_query($conn, "INSERT INTO detail_pemesanan (id_pemesanan, id_alat, jumlah, harga_sewa)
                    VALUES ($id_pemesanan, $val_alat_id, $val_jumlah, $harga_satuan)");
                if (!$ins_detail) {
                    throw new Exception("Gagal menyimpan detail pemesanan: " . mysqli_error($conn));
                }

                // Catat pembayaran jika bukti bayar diunggah
                if ($bukti_bayar !== '') {
                    $ins_bayar = mysqli_query($conn, "INSERT INTO pembayaran (id_pemesanan, id_admin, bukti_pembayaran, jumlah_pembayaran, tanggal_pembayaran)
                        VALUES ($id_pemesanan, 1, '$bukti_bayar', 0, CURDATE())");
                    if (!$ins_bayar) {
                        throw new Exception("Gagal menyimpan bukti pembayaran: " . mysqli_error($conn));
                    }
                }

                mysqli_commit($conn);
                header("Location: boking.php?sukses=" . urlencode($kode_baru));
                exit;
            } catch (Throwable $e) {
                mysqli_rollback($conn);
                $error = 'Gagal memproses pemesanan: ' . $e->getMessage();
            }
        }
    }
}

// Ambil daftar alat yang tersedia
$daftar_alat = mysqli_query($conn, "SELECT a.id_alat, a.nama_alat, a.harga_sewa, a.stok_total, k.nama_kategori
    FROM alat a
    JOIN kategori_alat k ON a.id_kategori = k.id_kategori
    WHERE a.stok_total > 0
    ORDER BY a.nama_alat ASC");

$alat_list_js = [];
$stok_list_js = [];
$max_stok_saat_ini = 1;
?>

<div class="container">
<?php if ($pesanan_sukses): ?>
    <?php
    $masa_sukses = hitungDurasiHari($pesanan_sukses['tanggal_mulai'], $pesanan_sukses['tanggal_kembali'], $hitung_inklusif_hari);
    $rincian_sukses = hitungRincianHarga($pesanan_sukses['harga_sewa'] ?? 0, $masa_sukses, $pesanan_sukses['jumlah'] ?? 1);
    $total_tampil = isset($pesanan_sukses['total_tagihan_akhir']) && $pesanan_sukses['total_tagihan_akhir'] !== null
        ? (float)$pesanan_sukses['total_tagihan_akhir']
        : $rincian_sukses['total_tagihan_akhir'];
    ?>
    <div class="card-shell card-flush">
        <div class="success-banner">
            <div class="check-circle">✓</div>
            <div class="success-title">Pemesanan Berhasil!</div>
            <div class="success-sub">Pesanan kamu telah berhasil dicatat di Savana Outdoor.</div>
        </div>

        <div class="section-body">
            <div class="label-heading">Nomor Pemesanan Kamu</div>
            <div class="order-code-big" id="kodePesananText"><?= htmlspecialchars($pesanan_sukses['kode_pemesanan']) ?></div>

            <button type="button" class="btn-outline-green btn-block" onclick="salinKode('<?= htmlspecialchars($pesanan_sukses['kode_pemesanan']) ?>', this)">
                <span>❐</span> Salin Nomor Pemesanan
            </button>
            <div class="helper-center">Simpan kode pemesanan ini untuk mengecek status pesanan atau konfirmasi saat pengambilan.</div>

            <div class="info-box" style="margin-top:16px;">
                <div class="info-box-title"><span>ⓘ</span> Informasi Pemesanan</div>
                <p>Status pesanan kamu saat ini <strong><?= htmlspecialchars($pesanan_sukses['status_pemesanan']) ?></strong>. Setelah admin memverifikasi pesananmu, status akan berubah menjadi <strong>Menunggu Pengambilan</strong>.</p>
            </div>

            <div class="detail-list" style="margin-top:16px;">
                <div class="detail-stack-item">
                    <div class="k">Nama Penyewa</div>
                    <div class="v"><?= htmlspecialchars($pesanan_sukses['nama_user']) ?></div>
                </div>
                <div class="detail-stack-item">
                    <div class="k">Nomor HP</div>
                    <div class="v"><?= htmlspecialchars($pesanan_sukses['no_hp']) ?></div>
                </div>
                <div class="detail-stack-item">
                    <div class="k">Alat yang Disewa</div>
                    <div class="v"><?= htmlspecialchars($pesanan_sukses['nama_alat'] ?? '-') ?></div>
                </div>
                <div class="detail-stack-item">
                    <div class="k">Periode Sewa</div>
                    <div class="v"><?= tgl_indo($pesanan_sukses['tanggal_mulai']) ?> s.d. <?= tgl_indo($pesanan_sukses['tanggal_kembali']) ?></div>
                </div>
            </div>

            <div class="info-box" style="margin-top:16px;">
                <div class="label-heading">Rincian Perhitungan Harga Sewa</div>
                <div class="detail-row" style="padding:4px 0;">
                    <span class="k">Harga Satuan</span>
                    <span class="v"><?= rupiah($rincian_sukses['harga_satuan']) ?></span>
                </div>
                <div class="detail-row" style="padding:4px 0;">
                    <span class="k">Biaya Masa Sewa (<?= $rincian_sukses['masa_sewa'] ?> hari × <?= rupiah($rincian_sukses['harga_satuan']) ?>)</span>
                    <span class="v"><?= rupiah($rincian_sukses['biaya_masa_sewa']) ?></span>
                </div>
                <div class="detail-row" style="padding:4px 0;">
                    <span class="k">Biaya Jumlah Sewa (<?= $rincian_sukses['jumlah_unit'] ?> unit × <?= rupiah($rincian_sukses['harga_satuan']) ?>)</span>
                    <span class="v"><?= rupiah($rincian_sukses['biaya_jumlah_sewa']) ?></span>
                </div>
                <div class="detail-row" style="padding:6px 0 2px; border-top:1px solid var(--border-subtle);">
                    <span class="k" style="font-weight:700;">Total Tagihan Akhir</span>
                    <span class="v" style="color:var(--green-accent); font-weight:700;"><?= rupiah($total_tampil) ?></span>
                </div>
            </div>

            <div class="status-block" style="margin-top:16px;">
                <div class="label-heading">Status Pesanan Saat Ini</div>
                <span class="badge badge-warn"><?= htmlspecialchars($pesanan_sukses['status_pemesanan']) ?></span>
            </div>

            <a href="cek_pesanan.php?kode=<?= urlencode($pesanan_sukses['kode_pemesanan']) ?>" class="btn-primary-green btn-block" style="margin-top:18px;">
                Lihat Status Pesanan di Cek Pesanan
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="card-shell">
        <h2 style="font-size:20px; margin-bottom:4px;">Form Booking Alat</h2>
        <p style="color:var(--text-muted); font-size:14px; margin-bottom:18px;">Lengkapi data diri dan tentukan tanggal peminjaman perlengkapan outdoor.</p>

        <?php if ($val_alat_id <= 0): ?>
            <div class="info-box" style="margin-top:0; margin-bottom:20px; border-color:#3b2611;">
                <div class="info-box-title" style="color:var(--warn-text);">Belum Memilih Barang</div>
                <p style="margin:4px 0 12px;">Anda belum memilih barang outdoor yang akan disewa. Silakan pilih barang terlebih dahulu melalui Katalog Savana Outdoor.</p>
                <a href="index.php" class="btn-primary-green btn-inline">
                    ← Buka Katalog & Pilih Barang
                </a>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert-err" style="margin-bottom:16px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="boking.php" enctype="multipart/form-data">
            <div class="form-group">
                <label class="form-label">Pilih Barang Outdoor <span style="color:var(--danger-text);">*</span></label>
                <select name="alat_id" id="alatSelect" class="form-control" required>
                    <option value="">-- Wajib Pilih Barang Terlebih Dahulu --</option>
                    <?php if ($daftar_alat): ?>
                        <?php while ($a = mysqli_fetch_assoc($daftar_alat)): ?>
                            <?php
                            $id_a = (int)$a['id_alat'];
                            $alat_list_js[$id_a] = (float)$a['harga_sewa'];
                            $stok_list_js[$id_a] = (int)$a['stok_total'];
                            $is_selected = ($val_alat_id === $id_a);
                            if ($is_selected) {
                                $max_stok_saat_ini = (int)$a['stok_total'];
                            }
                            ?>
                            <option value="<?= $id_a ?>" <?= $is_selected ? 'selected' : '' ?>>
                                <?= htmlspecialchars($a['nama_alat']) ?> (<?= htmlspecialchars($a['nama_kategori']) ?> · <?= rupiah($a['harga_sewa']) ?>/hari · Stok: <?= (int)$a['stok_total'] ?> unit)
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
                <div style="margin-top:6px; font-size:12.5px;">
                    <a href="index.php" style="color:var(--green-accent);">Lihat katalog lengkap beserta foto & detail barang →</a>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Nama Lengkap <span style="color:var(--danger-text);">*</span></label>
                <input type="text" name="nama_user" class="form-control" value="<?= htmlspecialchars($val_nama_user) ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Nomor WhatsApp / HP <span style="color:var(--danger-text);">*</span></label>
                <input type="text" name="no_hp" class="form-control" value="<?= htmlspecialchars($val_no_hp) ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($val_email) ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Jenis & Nomor Identitas</label>
                <div style="display:grid; grid-template-columns:120px 1fr; gap:10px;">
                    <select name="jenis_identitas" class="form-control">
                        <option value="KTP" <?= $val_jenis_id === 'KTP' ? 'selected' : '' ?>>KTP</option>
                        <option value="SIM" <?= $val_jenis_id === 'SIM' ? 'selected' : '' ?>>SIM</option>
                        <option value="KTM" <?= $val_jenis_id === 'KTM' ? 'selected' : '' ?>>KTM</option>
                    </select>
                    <input type="text" name="nomor_identitas" class="form-control" value="<?= htmlspecialchars($val_no_id) ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Alamat Domisili</label>
                <textarea name="alamat" rows="2" class="form-control"><?= htmlspecialchars($val_alamat) ?></textarea>
            </div>

            <!-- Batas Maksimal Jumlah Sesuai Stok Tersedia -->
            <div class="form-group">
                <label class="form-label">Jumlah Unit <span style="color:var(--danger-text);">*</span></label>
                <input type="number" name="jumlah" id="jumlahInput" min="1" max="<?= $max_stok_saat_ini ?>" value="<?= (int)$val_jumlah ?>" class="form-control" required>
                <div id="stokNotice" style="font-size:12.5px; color:var(--text-muted); margin-top:4px;">
                    <?= $val_alat_id > 0 ? "Maksimal sewa: {$max_stok_saat_ini} unit (sesuai stok tersedia)." : "" ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Tanggal Sewa Mulai <span style="color:var(--danger-text);">*</span></label>
                <input type="date" name="tanggal_sewa" id="tglSewaInput" class="form-control" value="<?= htmlspecialchars($val_tanggal_sewa) ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Tanggal Sewa Kembali <span style="color:var(--danger-text);">*</span></label>
                <input type="date" name="tanggal_kembali" id="tglKembaliInput" class="form-control" value="<?= htmlspecialchars($val_tanggal_kembali) ?>" required>
            </div>

            <!-- Pratinjau Rincian Perhitungan 4 Baris Berurutan -->
            <div class="info-box" style="margin-top:16px;">
                <div class="label-heading">Pratinjau Rincian Perhitungan Harga Sewa</div>
                <div class="detail-row" style="padding:4px 0;">
                    <span class="k">Harga Satuan</span>
                    <span class="v" id="hargaSatuanPreview">Rp0</span>
                </div>
                <div class="detail-row" style="padding:4px 0;">
                    <span class="k" id="labelMasaSewa">Biaya Masa Sewa (1 hari × Rp0)</span>
                    <span class="v" id="biayaMasaPreview">Rp0</span>
                </div>
                <div class="detail-row" style="padding:4px 0;">
                    <span class="k" id="labelJumlahSewa">Biaya Jumlah Sewa (1 unit × Rp0)</span>
                    <span class="v" id="biayaJumlahPreview">Rp0</span>
                </div>
                <div class="detail-row" style="padding:6px 0 2px; border-top:1px solid var(--border-subtle);">
                    <span class="k" style="font-weight:700;">Total Tagihan Akhir</span>
                    <span class="v" id="totalPreview" style="color:var(--green-accent); font-weight:700;">Rp0</span>
                </div>
            </div>

            <div class="form-group" style="margin-top:16px;">
                <label class="form-label">Foto Identitas (KTP/SIM/KTM)</label>
                <input type="file" name="foto_identitas" accept="image/*" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Bukti Pembayaran / DP</label>
                <input type="file" name="bukti_bayar" accept="image/*" class="form-control">
            </div>
            <div class="form-group">
                <label style="display:flex; align-items:flex-start; gap:8px; font-size:13.5px; color:#cbd5d0; cursor:pointer;">
                    <input type="checkbox" name="persetujuan_syarat" value="1" <?= $val_setuju ? 'checked' : '' ?> required style="margin-top:3px;">
                    <span>Saya menyetujui syarat & ketentuan penyewaan Savana Outdoor serta bersedia menjaga keutuhan alat sewa.</span>
                </label>
            </div>
            <button type="submit" name="submit_booking" class="btn-primary-green btn-block">Kirim Pemesanan Sekarang</button>
        </form>
    </div>

    <script>
    (function() {
        const hargaAlat = <?= json_encode($alat_list_js) ?>;
        const stokAlat  = <?= json_encode($stok_list_js) ?>;
        const inklusif  = <?= $hitung_inklusif_hari ? 'true' : 'false' ?>;
        const alatEl    = document.getElementById('alatSelect');
        const jmlEl     = document.getElementById('jumlahInput');
        const sewaEl    = document.getElementById('tglSewaInput');
        const kembaliEl = document.getElementById('tglKembaliInput');
        const hargaEl   = document.getElementById('hargaSatuanPreview');
        const labelMasaEl = document.getElementById('labelMasaSewa');
        const biayaMasaEl = document.getElementById('biayaMasaPreview');
        const labelJumlahEl = document.getElementById('labelJumlahSewa');
        const biayaJumlahEl = document.getElementById('biayaJumlahPreview');
        const totalEl   = document.getElementById('totalPreview');
        const stokNoticeEl = document.getElementById('stokNotice');

        function formatRp(num) {
            return 'Rp' + Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        function updateEstimasi() {
            const val = alatEl.value;
            const harga = Number(hargaAlat[val] || 0);
            const maxStok = Number(stokAlat[val] || 1);

            if (val && stokAlat[val] !== undefined) {
                jmlEl.max = maxStok;
                if (maxStok <= 0) {
                    stokNoticeEl.textContent = 'Stok barang ini sedang habis.';
                    stokNoticeEl.style.color = 'var(--danger-text)';
                } else {
                    stokNoticeEl.textContent = 'Maksimal sewa: ' + maxStok + ' unit (sesuai stok tersedia).';
                    stokNoticeEl.style.color = 'var(--text-muted)';
                }

                if (Number(jmlEl.value) > maxStok && maxStok > 0) {
                    jmlEl.value = maxStok;
                }
            } else {
                stokNoticeEl.textContent = '';
            }

            const jml = Math.max(1, Number(jmlEl.value || 1));
            let hari = 1;

            if (sewaEl.value && kembaliEl.value) {
                const d1 = new Date(sewaEl.value + 'T00:00:00');
                const d2 = new Date(kembaliEl.value + 'T00:00:00');
                if (!isNaN(d1) && !isNaN(d2) && d2 >= d1) {
                    const diffDays = Math.round((d2 - d1) / 86400000);
                    hari = Math.max(1, inklusif ? (diffDays + 1) : diffDays);
                }
            }

            // Rumus berurutan persis sama dengan PHP:
            // Biaya Masa Sewa = Masa Sewa (hari) × Harga Satuan
            // Biaya Jumlah Sewa = Jumlah Unit × Harga Satuan
            // Total Tagihan Akhir = Biaya Masa Sewa + Biaya Jumlah Sewa
            const biayaMasa = hari * harga;
            const biayaJumlah = jml * harga;
            const totalAkhir = biayaMasa + biayaJumlah;

            hargaEl.textContent = formatRp(harga);
            labelMasaEl.textContent = 'Biaya Masa Sewa (' + hari + ' hari × ' + formatRp(harga) + ')';
            biayaMasaEl.textContent = formatRp(biayaMasa);
            labelJumlahEl.textContent = 'Biaya Jumlah Sewa (' + jml + ' unit × ' + formatRp(harga) + ')';
            biayaJumlahEl.textContent = formatRp(biayaJumlah);
            totalEl.textContent = formatRp(totalAkhir);
        }

        jmlEl.addEventListener('input', function() {
            const maxVal = Number(jmlEl.max || 9999);
            if (Number(jmlEl.value) > maxVal && maxVal > 0) {
                jmlEl.value = maxVal;
            }
            if (Number(jmlEl.value) < 1) {
                jmlEl.value = 1;
            }
            updateEstimasi();
        });

        [alatEl, jmlEl, sewaEl, kembaliEl].forEach(function(el) {
            if (el) {
                el.addEventListener('input', updateEstimasi);
                el.addEventListener('change', updateEstimasi);
            }
        });
        updateEstimasi();
    })();
    </script>
<?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
