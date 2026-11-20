<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-brand">SAVANA OUTDOOR</div>
        <p class="footer-tagline">Penyewaan perlengkapan camping dan outdoor terpercaya di Purbalingga.</p>
        <div class="footer-address">
            <strong>Alamat:</strong> Jl. MT. Haryono, Dusun 2, Bojanegara, Kec. Padamara, Kabupaten Purbalingga, Jawa Tengah 53372
        </div>
        <div class="footer-copy">
            &copy; <?= date('Y') ?> Savana Outdoor · Persewaan Alat Camping & Outdoor Purbalingga
        </div>
    </div>
</footer>

<script>
function salinKode(kode, btnEl) {
    if (!kode) return;
    const done = () => {
        if (!btnEl) return;
        const oldText = btnEl.innerHTML;
        btnEl.innerHTML = '✓ Nomor Pemesanan Disalin';
        setTimeout(() => { btnEl.innerHTML = oldText; }, 1800);
    };
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(kode).then(done).catch(() => fallbackSalin(kode, done));
    } else {
        fallbackSalin(kode, done);
    }
}
function fallbackSalin(text, cb) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    cb();
}
</script>
</body>
</html>
