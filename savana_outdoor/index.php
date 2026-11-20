<?php
require_once __DIR__ . '/config/koneksi.php';
include __DIR__ . '/includes/header.php';

$kat_filter = isset($_GET['kategori']) ? (int)$_GET['kategori'] : 0;
$kategoris  = mysqli_query($conn, "SELECT * FROM kategori_alat ORDER BY nama_kategori ASC");

$sql_alat = "SELECT a.*, k.nama_kategori
             FROM alat a
             JOIN kategori_alat k ON a.id_kategori = k.id_kategori";
if ($kat_filter > 0) {
    $sql_alat .= " WHERE a.id_kategori = $kat_filter";
}
$sql_alat .= " ORDER BY a.id_alat DESC";
$alats = mysqli_query($conn, $sql_alat);
?>

<div class="container-wide">
    <!-- Hero Banner dengan Foto Gunung & Ajakan Sewa Menarik -->
    <div class="hero-banner">
        <img src="/savana_outdoor/assets/images/banner_hero.jpg" alt="Savana Outdoor" class="hero-bg-img">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <span class="hero-badge">🌿 SAVANA OUTDOOR PURBALINGGA</span>
            <h1 class="hero-title">Jelajahi Alam Bebas Bersama Perlengkapan Terbaik</h1>
            <p class="hero-desc">
                Sewa tenda dome, carrier bag, kompor camping, sleeping bag, dan peralatan outdoor berkualitas di Purbalingga. Peralatan bersih, terawat, dan siap menemani perjalanan petualanganmu.
            </p>
            <div class="hero-features">
                <span class="hero-feature-item">✓ Unit Bersih & Terawat</span>
                <span class="hero-feature-item">✓ Tarif Sewa Terjangkau</span>
                <span class="hero-feature-item">✓ Buka Setiap Hari (10.00–21.00)</span>
            </div>
            <a href="#katalogArea" class="btn-primary-green btn-inline">
                Pilih Perlengkapan Sekarang ↓
            </a>
        </div>
    </div>

    <!-- Area Katalog Alat -->
    <div class="card-shell" id="katalogArea">
        <h2 style="font-size:20px; margin-bottom:4px;">Katalog Perlengkapan Outdoor</h2>
        <p style="color:var(--text-muted); font-size:14px;">Klik pada kartu barang untuk membuka jendela detail spesifikasi dan melakukan booking sewa.</p>

        <!-- Filter Kategori -->
        <div style="margin-top:16px; display:flex; gap:8px; flex-wrap:wrap;">
            <a href="index.php#katalogArea" class="badge <?= $kat_filter === 0 ? 'badge-ok' : 'badge-muted' ?>">Semua</a>
            <?php while ($k = mysqli_fetch_assoc($kategoris)): ?>
                <a href="index.php?kategori=<?= (int)$k['id_kategori'] ?>#katalogArea" class="badge <?= $kat_filter === (int)$k['id_kategori'] ? 'badge-ok' : 'badge-muted' ?>">
                    <?= htmlspecialchars($k['nama_kategori']) ?>
                </a>
            <?php endwhile; ?>
        </div>

        <!-- Grid Kartu Barang -->
        <div class="grid-cards">
            <?php if ($alats && mysqli_num_rows($alats) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($alats)): ?>
                    <?php
                    $id_alat_item = (int)$row['id_alat'];
                    $stok_item    = (int)$row['stok_total'];
                    $is_habis     = ($stok_item <= 0);
                    $foto_src     = !empty($row['foto_alat']) ? '/savana_outdoor/assets/uploads/alat/' . htmlspecialchars($row['foto_alat']) : '';
                    ?>
                    <!-- Kartu Barang: Ringkas, Informatif, dan Responsif -->
                    <div class="item-card <?= $is_habis ? 'item-habis' : 'item-trigger' ?>"
                         data-id="<?= $id_alat_item ?>"
                         data-nama="<?= htmlspecialchars($row['nama_alat']) ?>"
                         data-kategori="<?= htmlspecialchars($row['nama_kategori']) ?>"
                         data-harga="<?= rupiah($row['harga_sewa']) ?>"
                         data-stok="<?= $stok_item ?>"
                         data-deskripsi="<?= htmlspecialchars($row['deskripsi'] ?? '') ?>"
                         data-foto="<?= $foto_src ?>">
                        <div>
                            <?php if ($foto_src !== ''): ?>
                                <img src="<?= $foto_src ?>" alt="<?= htmlspecialchars($row['nama_alat']) ?>" style="width:100%; height:140px; object-fit:cover; border-radius:10px; margin-bottom:10px;">
                            <?php else: ?>
                                <div style="width:100%; height:140px; background:#141c17; border-radius:10px; display:flex; align-items:center; justify-content:center; color:var(--text-muted); font-size:13px; margin-bottom:10px;">
                                    Tanpa Foto
                                </div>
                            <?php endif; ?>

                            <div style="font-weight:700; font-size:15.5px; line-height:1.3; color:var(--text-main);"><?= htmlspecialchars($row['nama_alat']) ?></div>
                            <div style="color:var(--green-accent); font-weight:700; font-size:14.5px; margin-top:4px;"><?= rupiah($row['harga_sewa']) ?> / hari</div>

                            <!-- Tampilan Informasi Stok yang Jelas -->
                            <div class="card-stok">
                                <?php if ($is_habis): ?>
                                    <span class="badge badge-danger">Habis</span>
                                <?php else: ?>
                                    <span class="badge badge-ok">Stok: <?= $stok_item ?> unit</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Tombol Aksi Seragam -->
                        <?php if ($is_habis): ?>
                            <button type="button" class="btn-primary-green btn-block" disabled>
                                Stok Habis
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn-primary-green btn-block">
                                Lihat Detail & Sewa
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="grid-column: 1 / -1; padding: 30px; text-align: center; color: var(--text-muted);">
                    Belum ada perlengkapan yang tersedia pada kategori ini.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Jendela Detail (Modal/Popup) -->
<div id="modalDetail" class="modal-overlay">
    <div class="modal-card">
        <button type="button" class="modal-close-btn" id="modalCloseBtn" aria-label="Tutup Detail">✕</button>

        <div id="modalFotoWrap" style="display:none; margin-bottom:16px;">
            <img id="modalFoto" src="" alt="" style="width:100%; max-height:220px; object-fit:contain; background:#070a08; border-radius:12px; border:1px solid var(--border-subtle);">
        </div>

        <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
            <span class="badge badge-info" id="modalKategori">Kategori</span>
            <span class="badge badge-ok" id="modalStokBadge">Stok: 0 unit</span>
        </div>

        <h3 id="modalNama" style="font-size:20px; font-weight:700; color:var(--text-main); margin-bottom:4px;">Nama Barang</h3>
        <div id="modalHarga" style="font-size:18px; font-weight:700; color:var(--green-accent); margin-bottom:16px;">Rp0 / hari</div>

        <div class="label-heading" style="margin-bottom:6px;">Deskripsi Barang</div>
        <p id="modalDeskripsi" style="color:var(--text-muted); font-size:13.5px; line-height:1.6; margin-bottom:16px; white-space:pre-line;"></p>

        <div class="info-box" style="margin-top:0; margin-bottom:20px;">
            <div class="info-box-title">Ketentuan & Kelengkapan Sewa</div>
            <ul style="margin:6px 0 0 18px; padding:0; font-size:13px; color:var(--text-muted); line-height:1.5;">
                <li>Wajib meninggalkan identitas asli (KTP/SIM/KTM) saat pengambilan perlengkapan.</li>
                <li>Pengecekan kelengkapan & kondisi alat dilakukan bersama sebelum serah terima.</li>
                <li>Pengembalian perlengkapan wajib tepat waktu sesuai durasi masa sewa.</li>
            </ul>
        </div>

        <div style="display:flex; gap:10px;">
            <a href="#" id="modalBookingBtn" class="btn-primary-green" style="flex:1;">Booking Barang Ini</a>
            <button type="button" class="btn-outline-green" id="modalBatalBtn" style="width:auto; min-width:90px;">Tutup</button>
        </div>
    </div>
</div>

<script>
(function() {
    const modal = document.getElementById('modalDetail');
    const closeBtn = document.getElementById('modalCloseBtn');
    const batalBtn = document.getElementById('modalBatalBtn');
    const modalNama = document.getElementById('modalNama');
    const modalKategori = document.getElementById('modalKategori');
    const modalStokBadge = document.getElementById('modalStokBadge');
    const modalHarga = document.getElementById('modalHarga');
    const modalDeskripsi = document.getElementById('modalDeskripsi');
    const modalFotoWrap = document.getElementById('modalFotoWrap');
    const modalFoto = document.getElementById('modalFoto');
    const modalBookingBtn = document.getElementById('modalBookingBtn');

    function bukaModal(card) {
        const stok = Number(card.getAttribute('data-stok') || 0);
        // Cegah pembukaan modal jika stok habis
        if (stok <= 0) return;

        const id = card.getAttribute('data-id');
        const nama = card.getAttribute('data-nama');
        const kategori = card.getAttribute('data-kategori');
        const harga = card.getAttribute('data-harga');
        const deskripsi = card.getAttribute('data-deskripsi') || 'Perlengkapan outdoor siap pakai dengan kondisi terawat dan layak operasional.';
        const foto = card.getAttribute('data-foto');

        modalNama.textContent = nama;
        modalKategori.textContent = kategori;
        modalStokBadge.textContent = 'Stok Tersedia: ' + stok + ' unit';
        modalHarga.textContent = harga + ' / hari';
        modalDeskripsi.textContent = deskripsi;

        if (foto) {
            modalFoto.src = foto;
            modalFoto.alt = nama;
            modalFotoWrap.style.display = 'block';
        } else {
            modalFotoWrap.style.display = 'none';
        }

        modalBookingBtn.href = 'boking.php?alat_id=' + encodeURIComponent(id);

        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function tutupModal() {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }

    document.querySelectorAll('.item-trigger').forEach(function(card) {
        card.addEventListener('click', function(e) {
            bukaModal(card);
        });
    });

    closeBtn.addEventListener('click', tutupModal);
    batalBtn.addEventListener('click', tutupModal);

    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            tutupModal();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('active')) {
            tutupModal();
        }
    });
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
