<?php
require_once __DIR__ . '/config/koneksi.php';

$kode_input = trim($_GET['kode'] ?? '');
$id_input   = (int)($_GET['id'] ?? 0);
$auto_print = isset($_GET['print']) && $_GET['print'] == '1';

$pesanan = null;
$hitung_inklusif_hari = true;

if ($kode_input !== '') {
    $kode_esc = mysqli_real_escape_string($conn, $kode_input);
    $sql = "SELECT p.*, pl.nama AS nama_user, pl.no_hp, pl.jenis_identitas, pl.nomor_identitas, pl.alamat,
            a.nama_alat, a.foto_alat, COALESCE(dp.jumlah, 1) AS jumlah, COALESCE(dp.harga_sewa, a.harga_sewa, 0) AS harga_sewa
            FROM pemesanan p
            JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
            LEFT JOIN detail_pemesanan dp ON dp.id_pemesanan = p.id_pemesanan
            LEFT JOIN alat a ON dp.id_alat = a.id_alat
            WHERE p.kode_pemesanan = '$kode_esc'
            LIMIT 1";
    $q = mysqli_query($conn, $sql);
    if ($q && mysqli_num_rows($q) > 0) {
        $pesanan = mysqli_fetch_assoc($q);
    }
} elseif ($id_input > 0) {
    $sql = "SELECT p.*, pl.nama AS nama_user, pl.no_hp, pl.jenis_identitas, pl.nomor_identitas, pl.alamat,
            a.nama_alat, a.foto_alat, COALESCE(dp.jumlah, 1) AS jumlah, COALESCE(dp.harga_sewa, a.harga_sewa, 0) AS harga_sewa
            FROM pemesanan p
            JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
            LEFT JOIN detail_pemesanan dp ON dp.id_pemesanan = p.id_pemesanan
            LEFT JOIN alat a ON dp.id_alat = a.id_alat
            WHERE p.id_pemesanan = $id_input
            LIMIT 1";
    $q = mysqli_query($conn, $sql);
    if ($q && mysqli_num_rows($q) > 0) {
        $pesanan = mysqli_fetch_assoc($q);
    }
}

$total_terbayar = 0;
if ($pesanan) {
    $id_p = (int)$pesanan['id_pemesanan'];
    $qb = mysqli_query($conn, "SELECT COALESCE(SUM(jumlah_pembayaran), 0) AS total_byr FROM pembayaran WHERE id_pemesanan = $id_p");
    if ($qb && mysqli_num_rows($qb) > 0) {
        $total_terbayar = (int)(mysqli_fetch_assoc($qb)['total_byr'] ?? 0);
    }

    $durasi = hitungDurasiHari($pesanan['tanggal_mulai'], $pesanan['tanggal_kembali'], $hitung_inklusif_hari);
    $rincian = hitungRincianHarga($pesanan['harga_sewa'] ?? 0, $durasi, $pesanan['jumlah'] ?? 1);
    $total_tagihan = (isset($pesanan['total_tagihan_akhir']) && $pesanan['total_tagihan_akhir'] !== null)
        ? (float)$pesanan['total_tagihan_akhir']
        : $rincian['total_tagihan_akhir'];
    $sisa_tagihan = max(0, $total_tagihan - $total_terbayar);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pesanan ? 'Nota_' . htmlspecialchars($pesanan['kode_pemesanan']) : 'Nota Tidak Ditemukan' ?> - Savana Outdoor</title>
    <style>
        :root {
            --text-dark: #111827;
            --text-muted: #4b5563;
            --border-color: #d1d5db;
            --green-primary: #15803d;
            --green-light: #f0fdf4;
            --paper-bg: #ffffff;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: #e5e7eb;
            color: var(--text-dark);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 13px;
            line-height: 1.5;
            padding: 24px 16px;
        }

        .screen-actions {
            max-width: 780px;
            margin: 0 auto 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 42px;
            padding: 0 18px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 8px;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.15s ease;
        }

        .btn-print {
            background: #15803d;
            color: #ffffff;
            border-color: #166534;
        }

        .btn-print:hover {
            background: #166534;
        }

        .btn-back {
            background: #ffffff;
            color: #374151;
            border-color: #d1d5db;
        }

        .btn-back:hover {
            background: #f3f4f6;
        }

        .nota-wrapper {
            max-width: 780px;
            margin: 0 auto;
            background: var(--paper-bg);
            border: 1px solid #d1d5db;
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
            border-radius: 8px;
            padding: 32px 36px;
        }

        .nota-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 18px;
            border-bottom: 2px solid #111827;
            gap: 20px;
        }

        .brand-title {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: #111827;
            text-transform: uppercase;
        }

        .brand-tagline {
            font-size: 12px;
            color: #4b5563;
            font-weight: 600;
            margin-top: 2px;
        }

        .brand-address {
            font-size: 11.5px;
            color: #4b5563;
            line-height: 1.45;
            margin-top: 6px;
            max-width: 380px;
        }

        .nota-badge-title {
            text-align: right;
        }

        .doc-title {
            font-size: 16px;
            font-weight: 800;
            color: #15803d;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .doc-code {
            font-size: 15px;
            font-weight: 700;
            font-family: monospace;
            color: #111827;
            margin-top: 4px;
        }

        .doc-date {
            font-size: 11.5px;
            color: #6b7280;
            margin-top: 2px;
        }

        .grid-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
            padding-bottom: 16px;
            border-bottom: 1px dashed #d1d5db;
        }

        .info-card {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 12px 14px;
        }

        .info-card h4 {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #4b5563;
            margin-bottom: 8px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
        }

        .row-kv {
            display: flex;
            justify-content: space-between;
            padding: 2.5px 0;
            font-size: 12.5px;
        }

        .row-kv .k {
            color: #6b7280;
        }

        .row-kv .v {
            font-weight: 600;
            color: #111827;
            text-align: right;
        }

        .table-rincian {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 12.5px;
        }

        .table-rincian th {
            background: #f3f4f6;
            color: #111827;
            font-weight: 700;
            padding: 10px 12px;
            text-align: left;
            border-top: 1px solid #d1d5db;
            border-bottom: 1px solid #d1d5db;
        }

        .table-rincian td {
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
        }

        .text-right {
            text-align: right !important;
        }

        .text-center {
            text-align: center !important;
        }

        .total-box {
            margin-top: 16px;
            margin-left: auto;
            max-width: 380px;
            background: #f9fafb;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 12px 14px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
            font-size: 12.5px;
        }

        .total-row.grand {
            font-size: 14px;
            font-weight: 800;
            color: #15803d;
            border-top: 1.5px solid #111827;
            margin-top: 6px;
            padding-top: 6px;
        }

        .terms-box {
            margin-top: 22px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 10px 14px;
            background: #fafafa;
            font-size: 11px;
            color: #4b5563;
            line-height: 1.55;
        }

        .terms-box strong {
            color: #111827;
        }

        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 28px;
            text-align: center;
        }

        .sig-block {
            padding: 8px 14px;
        }

        .sig-title {
            font-size: 12px;
            font-weight: 600;
            color: #4b5563;
        }

        .sig-space {
            height: 64px;
        }

        .sig-name {
            font-size: 13px;
            font-weight: 700;
            color: #111827;
            border-top: 1px solid #111827;
            padding-top: 4px;
            display: inline-block;
            min-width: 180px;
        }

        .sig-sub {
            font-size: 11px;
            color: #6b7280;
            margin-top: 2px;
        }

        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }

            .screen-actions {
                display: none !important;
            }

            .nota-wrapper {
                border: none !important;
                box-shadow: none !important;
                padding: 12px 16px !important;
                max-width: 100% !important;
            }
        }
    </style>
</head>
<body>

<?php if (!$pesanan): ?>
    <div class="nota-wrapper" style="text-align:center; padding:50px 20px;">
        <h2 style="color:#dc2626; margin-bottom:8px;">Pemesanan Tidak Ditemukan</h2>
        <p style="color:#4b5563; margin-bottom:20px;">Nomor pemesanan yang Anda cari tidak tersedia di database Savana Outdoor.</p>
        <a href="cek_pesanan.php" class="btn-action btn-print">← Kembali ke Cek Pesanan</a>
    </div>
<?php else: ?>

    <div class="screen-actions">
        <div>
            <button type="button" onclick="window.history.back()" class="btn-action btn-back">← Kembali</button>
        </div>
        <div style="display:flex; gap:10px;">
            <a href="https://wa.me/<?= $info_toko['no_wa'] ?>?text=<?= rawurlencode("Halo Savana Outdoor, saya melampirkan konfirmasi nota pesanan No: " . $pesanan['kode_pemesanan'] . " a.n. " . $pesanan['nama_user']) ?>" target="_blank" class="btn-action btn-print" style="background:#15803d;">
                💬 Konfirmasi WA
            </a>
            <button type="button" onclick="window.print()" class="btn-action btn-print">
                🖨️ Cetak Nota / PDF
            </button>
        </div>
    </div>

    <div class="nota-wrapper">
        <!-- Header Nota -->
        <div class="nota-header">
            <div>
                <div class="brand-title"><?= htmlspecialchars($info_toko['nama']) ?></div>
                <div class="brand-tagline"><?= htmlspecialchars($info_toko['tagline']) ?></div>
                <div class="brand-address">
                    <?= htmlspecialchars($info_toko['alamat']) ?><br>
                    WhatsApp: <strong>+<?= htmlspecialchars($info_toko['no_wa']) ?></strong> · Jam Buka: <?= htmlspecialchars($info_toko['jam_buka']) ?>
                </div>
            </div>
            <div class="nota-badge-title">
                <div class="doc-title">NOTA SEWA ALAT</div>
                <div class="doc-code"><?= htmlspecialchars($pesanan['kode_pemesanan']) ?></div>
                <div class="doc-date">Dicetak: <?= tgl_indo(date('Y-m-d')) ?> <?= date('H:i') ?> WIB</div>
            </div>
        </div>

        <!-- Grid Data Penyewa & Rental -->
        <div class="grid-info">
            <div class="info-card">
                <h4>Data Penyewa</h4>
                <div class="row-kv">
                    <span class="k">Nama Lengkap</span>
                    <span class="v"><?= htmlspecialchars($pesanan['nama_user']) ?></span>
                </div>
                <div class="row-kv">
                    <span class="k">Nomor WhatsApp</span>
                    <span class="v"><?= htmlspecialchars($pesanan['no_hp']) ?></span>
                </div>
                <div class="row-kv">
                    <span class="k">Identitas (Jaminan)</span>
                    <span class="v"><?= htmlspecialchars($pesanan['jenis_identitas']) ?> · <?= htmlspecialchars($pesanan['nomor_identitas']) ?></span>
                </div>
                <div class="row-kv">
                    <span class="k">Alamat</span>
                    <span class="v" style="max-width:180px;"><?= htmlspecialchars(!empty($pesanan['alamat']) ? $pesanan['alamat'] : '-') ?></span>
                </div>
            </div>

            <div class="info-card">
                <h4>Informasi Masa Sewa</h4>
                <div class="row-kv">
                    <span class="k">Tanggal Mulai</span>
                    <span class="v"><?= tgl_indo($pesanan['tanggal_mulai']) ?></span>
                </div>
                <div class="row-kv">
                    <span class="k">Tanggal Kembali</span>
                    <span class="v"><?= tgl_indo($pesanan['tanggal_kembali']) ?></span>
                </div>
                <div class="row-kv">
                    <span class="k">Durasi Sewa</span>
                    <span class="v"><?= $durasi ?> Hari (Inklusif)</span>
                </div>
                <div class="row-kv">
                    <span class="k">Status Pesanan</span>
                    <span class="v" style="color:#15803d;"><?= htmlspecialchars($pesanan['status_pemesanan']) ?></span>
                </div>
            </div>
        </div>

        <!-- Tabel Item Sewa & Rincian Harga Berurutan -->
        <table class="table-rincian">
            <thead>
                <tr>
                    <th style="width:40px;" class="text-center">No</th>
                    <th>Nama Perlengkapan Outdoor</th>
                    <th class="text-center" style="width:90px;">Masa Sewa</th>
                    <th class="text-center" style="width:80px;">Jumlah</th>
                    <th class="text-right" style="width:110px;">Harga Satuan</th>
                    <th class="text-right" style="width:130px;">Total Tagihan</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-center">1</td>
                    <td>
                        <strong><?= htmlspecialchars($pesanan['nama_alat'] ?? 'Perlengkapan Outdoor') ?></strong>
                    </td>
                    <td class="text-center"><?= $rincian['masa_sewa'] ?> Hari</td>
                    <td class="text-center"><?= $rincian['jumlah_unit'] ?> Unit</td>
                    <td class="text-right"><?= rupiah($rincian['harga_satuan']) ?></td>
                    <td class="text-right"><strong><?= rupiah($total_tagihan) ?></strong></td>
                </tr>
            </tbody>
        </table>

        <!-- Rincian Berurutan Sesuai Ketentuan Proyek -->
        <div class="total-box">
            <div class="total-row">
                <span style="color:#6b7280;">1. Harga Satuan:</span>
                <span><?= rupiah($rincian['harga_satuan']) ?></span>
            </div>
            <div class="total-row">
                <span style="color:#6b7280;">2. Biaya Masa Sewa (<?= $rincian['masa_sewa'] ?> hari × <?= rupiah($rincian['harga_satuan']) ?>):</span>
                <span><?= rupiah($rincian['biaya_masa_sewa']) ?></span>
            </div>
            <div class="total-row">
                <span style="color:#6b7280;">3. Biaya Jumlah Sewa (<?= $rincian['jumlah_unit'] ?> unit × <?= rupiah($rincian['harga_satuan']) ?>):</span>
                <span><?= rupiah($rincian['biaya_jumlah_sewa']) ?></span>
            </div>
            <div class="total-row grand">
                <span>Total Tagihan Akhir:</span>
                <span><?= rupiah($total_tagihan) ?></span>
            </div>
            <?php if ($total_terbayar > 0): ?>
                <div class="total-row" style="margin-top:6px; color:#15803d;">
                    <span>Telah Dibayar / DP:</span>
                    <span>- <?= rupiah($total_terbayar) ?></span>
                </div>
                <div class="total-row" style="font-weight:700; color:#dc2626;">
                    <span>Sisa Tagihan:</span>
                    <span><?= rupiah($sisa_tagihan) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Ketentuan Serah Terima -->
        <div class="terms-box">
            <strong>Ketentuan & Perjanjian Serah Terima Barang Savana Outdoor:</strong>
            <ol style="margin: 4px 0 0 16px; padding:0;">
                <li>Penyewa telah memeriksa kondisi fisik dan kelengkapan alat bersama petugas saat penyerahan.</li>
                <li>Identitas asli (<?= htmlspecialchars($pesanan['jenis_identitas']) ?>) ditahan oleh pihak Savana Outdoor sebagai jaminan sewa dan diserahkan kembali saat barang kembali lengkap.</li>
                <li>Penyewa bertanggung jawab penuh atas segala kerusakan, kotor berat, atau kehilangan peralatan selama masa sewa.</li>
                <li>Keterlambatan pengembalian melewati batas tanggal <strong><?= tgl_indo($pesanan['tanggal_kembali']) ?></strong> akan dikenakan denda sesuai tarif harian resmi.</li>
            </ol>
        </div>

        <!-- Tanda Tangan Fisik -->
        <div class="signatures">
            <div class="sig-block">
                <div class="sig-title">Penyewa (Penerima Alat)</div>
                <div class="sig-space"></div>
                <div class="sig-name">( <?= htmlspecialchars($pesanan['nama_user']) ?> )</div>
                <div class="sig-sub">Tanda Tangan & Nama Terang</div>
            </div>

            <div class="sig-block">
                <div class="sig-title">Petugas Outlet Savana Outdoor</div>
                <div class="sig-space"></div>
                <div class="sig-name">( Petugas Savana Outdoor )</div>
                <div class="sig-sub">Stempel & Tanda Tangan</div>
            </div>
        </div>
    </div>

    <?php if ($auto_print): ?>
        <script>
            window.addEventListener('DOMContentLoaded', function() {
                window.print();
            });
        </script>
    <?php endif; ?>

<?php endif; ?>

</body>
</html>
