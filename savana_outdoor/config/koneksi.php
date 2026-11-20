<?php
date_default_timezone_set('Asia/Jakarta');

$host = '127.0.0.1';
$port = 3306;
$user = 'root';
$pass = '';
$db   = 'savana_db';

mysqli_report(MYSQLI_REPORT_OFF);

$conn = @mysqli_connect($host, $user, $pass, '', $port);

if (!$conn) {
    $err_msg = mysqli_connect_error();
    die("
    <!DOCTYPE html>
    <html lang='id'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Koneksi MySQL Gagal - Savana Outdoor</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #060807; color: #f3f6f4; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
            .box { background: #0c100e; border: 1px solid #3b1418; border-radius: 14px; padding: 26px; max-width: 580px; width: 100%; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
            h2 { color: #f87171; margin-top: 0; font-size: 20px; margin-bottom: 8px; }
            p { color: #9aa69f; font-size: 14px; line-height: 1.6; margin: 0 0 14px; }
            .alert { background: #3b1418; color: #fca5a5; padding: 12px 14px; border-radius: 8px; font-family: monospace; font-size: 13px; margin-bottom: 16px; word-break: break-all; }
            .tips { background: #13293d; color: #93c5fd; padding: 14px 16px; border-radius: 8px; font-size: 13.5px; line-height: 1.6; }
            ol { margin: 8px 0 0 18px; padding: 0; }
            li { margin-bottom: 4px; }
        </style>
    </head>
    <body>
        <div class='box'>
            <h2>Koneksi Database MySQL Gagal</h2>
            <p>Aplikasi Savana Outdoor tidak dapat terhubung ke MySQL pada <strong>{$host}:{$port}</strong>.</p>
            <div class='alert'>Pesan Error: " . htmlspecialchars($err_msg) . "</div>
            <div class='tips'>
                <strong>Langkah Perbaikan di XAMPP:</strong>
                <ol>
                    <li>Buka <strong>XAMPP Control Panel</strong>.</li>
                    <li>Pastikan modul <strong>MySQL</strong> dalam status <strong>Running</strong> (klik tombol <em>Start</em>).</li>
                    <li>Pastikan MySQL berjalan pada port default <strong>3306</strong>.</li>
                    <li>Refresh kembali halaman ini setelah MySQL aktif.</li>
                </ol>
            </div>
        </div>
    </body>
    </html>
    ");
}

// Coba gunakan database savana_db, fallback ke savana_db_new_2 jika belum ada
if (!@mysqli_select_db($conn, $db)) {
    if (@mysqli_select_db($conn, 'savana_db_new_2')) {
        $db = 'savana_db_new_2';
    } else {
        $db_err = mysqli_error($conn);
        die("
        <!DOCTYPE html>
        <html lang='id'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Database Belum Ditemukan - Savana Outdoor</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #060807; color: #f3f6f4; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
                .box { background: #0c100e; border: 1px solid #3b2611; border-radius: 14px; padding: 26px; max-width: 580px; width: 100%; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
                h2 { color: #fb923c; margin-top: 0; font-size: 20px; margin-bottom: 8px; }
                p { color: #9aa69f; font-size: 14px; line-height: 1.6; margin: 0 0 14px; }
                .tips { background: #13293d; color: #93c5fd; padding: 14px 16px; border-radius: 8px; font-size: 13.5px; line-height: 1.6; }
                code { background: rgba(255,255,255,0.1); padding: 2px 6px; border-radius: 4px; font-family: monospace; color: #fff; }
            </ol>
            </style>
        </head>
        <body>
            <div class='box'>
                <h2>Database Savana Belum Ditemukan</h2>
                <p>Database <code>savana_db</code> maupun <code>savana_db_new_2</code> belum dibuat di MySQL.</p>
                <div class='tips'>
                    <strong>Cara Impor Database:</strong><br>
                    1. Buka <strong>phpMyAdmin</strong> di peramban: <code>http://localhost/phpmyadmin</code>.<br>
                    2. Buat database baru bernama <code>savana_db</code>.<br>
                    3. Impor file <code>savana_db.sql</code> yang tersedia di dalam folder proyek.
                </div>
            </div>
        </body>
        </html>
        ");
    }
}

mysqli_set_charset($conn, 'utf8mb4');

// Konfigurasi Resmi Savana Outdoor
$wa_admin = '6289675441234';
$info_toko = [
    'nama'      => 'Savana Outdoor',
    'tagline'   => 'Persewaan Perlengkapan Camping & Outdoor Purbalingga',
    'alamat'    => 'Jl. MT. Haryono, Dusun 2, Bojanegara, Kec. Padamara, Kabupaten Purbalingga, Jawa Tengah 53372',
    'jam_buka'  => 'Setiap Hari: 10.00 – 21.00 WIB',
    'no_wa'     => '6289675441234',
    'maps_link' => 'https://maps.app.goo.gl/WunzxU2MYeSWCj3TA',
];

function formatNoWa($no_hp) {
    $clean = preg_replace('/[^0-9]/', '', (string)$no_hp);
    if (strpos($clean, '0') === 0) {
        $clean = '62' . substr($clean, 1);
    } elseif (strpos($clean, '8') === 0) {
        $clean = '62' . $clean;
    }
    return $clean;
}

function hitungHariTerlambat($tgl_kembali, $status_pemesanan) {
    if (empty($tgl_kembali) || in_array($status_pemesanan, ['Selesai', 'Ditolak', 'Dibatalkan'], true)) {
        return 0;
    }
    try {
        $kembali = new DateTime(substr((string)$tgl_kembali, 0, 10));
        $hari_ini = new DateTime(date('Y-m-d'));
        if ($hari_ini > $kembali) {
            return (int)$kembali->diff($hari_ini)->days;
        }
        return 0;
    } catch (Throwable $e) {
        return 0;
    }
}

function rupiah($angka) {
    return 'Rp' . number_format((float)$angka, 0, ',', '.');
}

function tgl_indo($tanggal) {
    if (empty($tanggal) || $tanggal === '0000-00-00' || $tanggal === '0000-00-00 00:00:00') {
        return '-';
    }
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $pecah = explode('-', substr((string)$tanggal, 0, 10));
    if (count($pecah) !== 3) return (string)$tanggal;
    return (int)$pecah[2] . ' ' . ($bulan[(int)$pecah[1]] ?? '') . ' ' . $pecah[0];
}

function generateKodePesanan($conn) {
    $prefix = 'SO-' . date('Ymd') . '-';
    $sql = "SELECT kode_pemesanan FROM pemesanan WHERE kode_pemesanan LIKE '{$prefix}%' ORDER BY id_pemesanan DESC LIMIT 1";
    $res = mysqli_query($conn, $sql);
    $next = 1;
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        $lastNum = (int)substr($row['kode_pemesanan'], -4);
        $next = $lastNum + 1;
    }
    return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
}

function hitungDurasiHari($tgl_sewa, $tgl_kembali, $inklusif = true) {
    if (empty($tgl_sewa) || empty($tgl_kembali)) {
        return 1;
    }
    try {
        $d1 = new DateTime(substr((string)$tgl_sewa, 0, 10));
        $d2 = new DateTime(substr((string)$tgl_kembali, 0, 10));
        if ($d2 < $d1) {
            return 1;
        }
        $selisih = (int)$d1->diff($d2)->days;
        return max(1, $inklusif ? ($selisih + 1) : $selisih);
    } catch (Throwable $e) {
        return 1;
    }
}

function hitungRincianHarga($harga_satuan, $masa_sewa, $jumlah_unit) {
    $harga_satuan = (float)$harga_satuan;
    $masa_sewa    = max(1, (int)$masa_sewa);
    $jumlah_unit  = max(1, (int)$jumlah_unit);

    $biaya_masa_sewa   = $masa_sewa * $harga_satuan;
    $biaya_jumlah_sewa = $jumlah_unit * $harga_satuan;
    $total_akhir       = $biaya_masa_sewa + $biaya_jumlah_sewa;

    return [
        'harga_satuan'        => $harga_satuan,
        'masa_sewa'           => $masa_sewa,
        'jumlah_unit'         => $jumlah_unit,
        'biaya_masa_sewa'     => $biaya_masa_sewa,
        'biaya_jumlah_sewa'   => $biaya_jumlah_sewa,
        'total_tagihan_akhir' => $total_akhir,
        'total_akhir'         => $total_akhir,
    ];
}
