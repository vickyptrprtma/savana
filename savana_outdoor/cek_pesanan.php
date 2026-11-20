<?php
require_once __DIR__ . '/config/koneksi.php';
include __DIR__ . '/includes/header.php';

$kode_input  = trim($_GET['kode'] ?? $_POST['kode_pemesanan'] ?? '');
$hasil       = null;
$verifikasi  = null;
$pesan_error = '';

/*
 * Opsi perhitungan durasi sewa inklusif (konsisten dengan boking.php):
 * true = inklusif tanggal mulai & tanggal kembali (misal: 10 s.d. 12 = 3 hari, minimal 1 hari)
 */
$hitung_inklusif_hari = true;

if ($kode_input !== '') {
    $kode_esc = mysqli_real_escape_string($conn, $kode_input);

    $sql = "SELECT p.*, pl.nama AS nama_user, pl.no_hp, pl.jenis_identitas, pl.nomor_identitas,
            a.nama_alat, COALESCE(dp.jumlah, 1) AS jumlah, COALESCE(dp.harga_sewa, a.harga_sewa, 0) AS harga_sewa
            FROM pemesanan p
            JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
            LEFT JOIN detail_pemesanan dp ON dp.id_pemesanan = p.id_pemesanan
            LEFT JOIN alat a ON dp.id_alat = a.id_alat
            WHERE p.kode_pemesanan = '$kode_esc'
            LIMIT 1";
    $res = mysqli_query($conn, $sql);

    if ($res && mysqli_num_rows($res) > 0) {
        $hasil = mysqli_fetch_assoc($res);
        $id_p_cari = (int)$hasil['id_pemesanan'];

        // Ambil keputusan verifikasi terbaru dari admin
        $qv = mysqli_query($conn, "SELECT sp.*, ad.nama_admin
            FROM status_pesanan sp
            LEFT JOIN admin ad ON sp.id_admin = ad.id_admin
            WHERE sp.id_pemesanan = $id_p_cari
            ORDER BY sp.id_verifikasi DESC
            LIMIT 1");
        if ($qv && mysqli_num_rows($qv) > 0) {
            $verifikasi = mysqli_fetch_assoc($qv);
        }
    } else {
        $pesan_error = 'Data pemesanan tidak ditemukan. Periksa kembali Nomor Pemesanan yang dimasukkan.';
    }
}

function badgeStatusPesanan($st) {
    if ($st === 'Selesai') return 'badge-ok';
    if ($st === 'Ditolak' || $st === 'Dibatalkan') return 'badge-danger';
    if ($st === 'Menunggu Pengambilan' || $st === 'Sedang Disewa') return 'badge-info';
    return 'badge-warn';
}

function badgeStatusBayar($byr) {
    if ($byr === 'Pembayaran Lunas' || $byr === 'Lunas') return 'badge-ok';
    if ($byr === 'DP') return 'badge-info';
    return 'badge-muted';
}
?>

<div class="container">
    <div class="card-shell">
        <h2 style="font-size:20px; margin-bottom:4px;">Cek Status Pesanan</h2>
        <p style="color:var(--text-muted); font-size:14px; margin-bottom:20px;">Masukkan Nomor Pemesanan untuk melihat status sewa dan hasil verifikasi admin.</p>

        <form method="POST" action="cek_pesanan.php">
            <div class="form-group">
                <label class="form-label">Nomor Pemesanan</label>
                <input type="text" name="kode_pemesanan" class="form-control" value="<?= htmlspecialchars($kode_input) ?>" required autofocus>
            </div>
            <button type="submit" class="btn-primary-green">Cari Pesanan</button>
        </form>

        <?php if ($pesan_error): ?>
            <div class="alert-err" style="margin-top:18px;"><?= htmlspecialchars($pesan_error) ?></div>
        <?php endif; ?>

        <?php if ($hasil): ?>
            <?php
            $masa_cari    = hitungDurasiHari($hasil['tanggal_mulai'], $hasil['tanggal_kembali'], $hitung_inklusif_hari);
            $rincian_cari = hitungRincianHarga($hasil['harga_sewa'] ?? 0, $masa_cari, $hasil['jumlah'] ?? 1);
            $total_cari   = (isset($hasil['total_tagihan_akhir']) && $hasil['total_tagihan_akhir'] !== null)
                ? (float)$hasil['total_tagihan_akhir']
                : $rincian_cari['total_tagihan_akhir'];
            ?>
            <div style="margin-top:24px;">
                <div class="label-heading">Informasi Pemesanan</div>

                <div class="detail-list" style="margin-top:10px; border-top:none; padding-top:0;">
                    <div class="detail-row">
                        <span class="k">Nomor Pemesanan</span>
                        <span class="v" style="font-weight:700; color:var(--text-main);"><?= htmlspecialchars($hasil['kode_pemesanan']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="k">Nama Penyewa</span>
                        <span class="v"><?= htmlspecialchars($hasil['nama_user']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="k">Nomor HP</span>
                        <span class="v"><?= htmlspecialchars($hasil['no_hp']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="k">Alat Outdoor</span>
                        <span class="v"><?= htmlspecialchars($hasil['nama_alat'] ?? '-') ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="k">Jumlah Unit</span>
                        <span class="v"><?= (int)$rincian_cari['jumlah_unit'] ?> unit</span>
                    </div>
                    <div class="detail-row">
                        <span class="k">Periode Sewa</span>
                        <span class="v"><?= tgl_indo($hasil['tanggal_mulai']) ?> s.d. <?= tgl_indo($hasil['tanggal_kembali']) ?> (<?= $masa_cari ?> hari)</span>
                    </div>
                    <div class="detail-row">
                        <span class="k">Status Pemesanan</span>
                        <span class="badge <?= badgeStatusPesanan($hasil['status_pemesanan']) ?>"><?= htmlspecialchars($hasil['status_pemesanan']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="k">Status Pembayaran</span>
                        <span class="badge <?= badgeStatusBayar($hasil['status_pembayaran']) ?>"><?= htmlspecialchars($hasil['status_pembayaran']) ?></span>
                    </div>
                </div>

                <!-- Rincian Perhitungan 4 Baris Berurutan -->
                <div class="info-box" style="margin-top:16px;">
                    <div class="label-heading">Rincian Perhitungan Harga Sewa</div>
                    <div class="detail-row" style="padding:4px 0;">
                        <span class="k">Harga Satuan</span>
                        <span class="v"><?= rupiah($rincian_cari['harga_satuan']) ?></span>
                    </div>
                    <div class="detail-row" style="padding:4px 0;">
                        <span class="k">Biaya Masa Sewa (<?= $rincian_cari['masa_sewa'] ?> hari × <?= rupiah($rincian_cari['harga_satuan']) ?>)</span>
                        <span class="v"><?= rupiah($rincian_cari['biaya_masa_sewa']) ?></span>
                    </div>
                    <div class="detail-row" style="padding:4px 0;">
                        <span class="k">Biaya Jumlah Sewa (<?= $rincian_cari['jumlah_unit'] ?> unit × <?= rupiah($rincian_cari['harga_satuan']) ?>)</span>
                        <span class="v"><?= rupiah($rincian_cari['biaya_jumlah_sewa']) ?></span>
                    </div>
                    <div class="detail-row" style="padding:6px 0 2px; border-top:1px solid var(--border-subtle);">
                        <span class="k" style="font-weight:700;">Total Tagihan Akhir</span>
                        <span class="v" style="color:var(--green-accent); font-weight:700;"><?= rupiah($total_cari) ?></span>
                    </div>
                </div>

                <!-- Informasi Verifikasi Admin -->
                <div class="info-box" style="margin-top:16px;">
                    <div class="info-box-title">Status Verifikasi Admin</div>
                    <?php if ($verifikasi): ?>
                        <div class="detail-row" style="padding:4px 0;">
                            <span class="k">Keputusan Verifikasi</span>
                            <span class="badge <?= ($verifikasi['keputusan'] === 'Disetujui' || $verifikasi['keputusan'] === 'Selesai') ? 'badge-ok' : 'badge-danger' ?>">
                                <?= htmlspecialchars($verifikasi['keputusan']) ?>
                            </span>
                        </div>
                        <div class="detail-row" style="padding:4px 0;">
                            <span class="k">Diverifikasi oleh</span>
                            <span class="v"><?= htmlspecialchars($verifikasi['nama_admin'] ?? 'Admin Savana') ?></span>
                        </div>
                        <div class="detail-row" style="padding:4px 0;">
                            <span class="k">Waktu Verifikasi</span>
                            <span class="v"><?= tgl_indo($verifikasi['tanggal_verifikasi']) ?> <?= date('H:i', strtotime($verifikasi['tanggal_verifikasi'])) ?> WIB</span>
                        </div>
                        <div class="detail-row" style="padding:4px 0;">
                            <span class="k">Catatan Admin</span>
                            <span class="v"><?= htmlspecialchars(!empty($verifikasi['catatan']) ? $verifikasi['catatan'] : '-') ?></span>
                        </div>
                    <?php else: ?>
                        <p style="margin:4px 0 0; color:var(--text-muted); font-size:13.5px;">
                            Pesanan ini masih dalam antrean dan belum diverifikasi oleh admin. Status saat ini: <strong><?= htmlspecialchars($hasil['status_pemesanan']) ?></strong>.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
