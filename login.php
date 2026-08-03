<?php
// Halaman ini di-include dari index.php HANYA ketika belum login,
// dan langsung exit() setelahnya — jadi header/hero/nav situs tidak pernah dirender.
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — VOLTA PLN</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .login-gate-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background:
                linear-gradient(rgba(255,255,255,0.75), rgba(255,255,255,0.75)),
                url('images/hero.png');
            background-size: cover;
            background-position: center;
        }
        .login-gate-card {
            background: #fff;
            border-radius: var(--radius-lg);
            padding: 2.4rem 2.2rem;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(11,43,74,0.18);
        }
    </style>
</head>
<body>

<div class="login-gate-page">
    <div class="login-gate-card">
        <div class="modal-brand">
            <img src="images/logo.png" alt="PLN Logo">
            <span>PLN UP3 Malang</span>
        </div>

        <h2 id="modalTitle">Masuk</h2>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php elseif (isset($_SESSION['success'])): ?>
            <div class="alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <form method="POST" action="auth.php" id="authForm">
            <div id="loginFields">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="email@pln.co.id" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="******" maxlength="7" required>
                </div>
                <button type="submit" name="login" class="btn-primary-full">Login</button>
            </div>

            <div id="registerFields" style="display:none;">
                <div class="form-group">
                    <label>Nama Lengkap (Kontak)</label>
                    <input type="text" name="reg_name" placeholder="Nama lengkap">
                </div>
                <div class="form-group">
                    <label>Nama PT / Vendor</label>
                    <input type="text" name="reg_vendor_name" placeholder="PT. ..." required>
                </div>
                <div class="form-group">
                    <label>Alamat Vendor</label>
                    <input type="text" name="reg_vendor_address" placeholder="Alamat lengkap">
                </div>
                <div class="form-group">
                    <label>Telepon Vendor</label>
                    <input type="text" name="reg_vendor_phone" placeholder="08xxxxxxxxxx">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="reg_email" placeholder="email@pln.co.id" required>
                </div>
                <div class="form-group">
                    <label>Password (maks. 7 digit)</label>
                    <input type="password" name="reg_password" placeholder="******" maxlength="7" required>
                </div>
                <button type="submit" name="register" class="btn-primary-full">Daftar sebagai Vendor</button>
            </div>
        </form>

        <div id="forgotFields" style="display:none;">
            <div class="forgot-box">
                <p style="margin-top:0;"><strong><i class="fas fa-key"></i> Lupa sandi akun Anda?</strong></p>
                <p>Reset sandi hanya dapat dilakukan oleh admin gudang PLN. Silakan hubungi:</p>
                <p><i class="fas fa-phone"></i> Call Center PLN 123 (kode area Malang: 0341-123)</p>
                <p><i class="fas fa-envelope"></i> pln123@pln.co.id</p>
                <p style="margin-bottom:0;"><i class="fas fa-map-marker-alt"></i> PLN UP3 Malang, Jl. Jenderal Basuki Rahmat No. 100, Klojen, Kota Malang</p>
            </div>
        </div>

        <span id="toggleAuthText">Belum punya akun? <a href="#" onclick="toggleAuth()">Daftar</a></span>
        <span id="backToLoginText" style="display:none;">Sudah ingat sandi? <a href="#" onclick="showLogin()">Kembali ke Login</a></span>
        <div style="text-align:center; margin-top:0.3rem;">
            <a href="#" onclick="showForgotPassword(); return false;" style="font-size:0.82rem; color: var(--blue);">Lupa sandi?</a>
        </div>
    </div>
</div>

<!-- FAQ MODAL (tetap bisa diakses walau belum login) -->
<div id="faqModal" class="modal">
    <div class="modal-content" style="max-width:600px;">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2>FAQ</h2>
        <p><strong>Bagaimana cara mendaftar sebagai vendor?</strong> Isi form Daftar di atas dengan data PT / vendor kamu.</p>
        <p><strong>Lupa sandi?</strong> Hubungi admin gudang PLN melalui kontak di bawah untuk reset akun.</p>
        <div class="faq-contact">
            <p style="margin-top:0;"><strong><i class="fas fa-headset"></i> Kontak PLN Kota Malang (UP3 Malang)</strong></p>
            <p style="margin-bottom:0;">
                <i class="fas fa-phone"></i> Call Center PLN 123 — kode area Malang: (0341) 123<br>
                <i class="fas fa-envelope"></i> pln123@pln.co.id<br>
                <i class="fas fa-map-marker-alt"></i> Jl. Jenderal Basuki Rahmat No. 100, Klojen, Kota Malang, Jawa Timur 65119
            </p>
        </div>
    </div>
</div>

<script>
    // di halaman ini form login/register selalu tampil (bukan modal overlay),
    // jadi fungsi showLogin/showRegister cukup toggle field saja

    // PENTING: loginFields & registerFields berbagi satu <form>. Field
    // "required" di section yang sedang display:none tetap dicek browser
    // saat submit -> submit gagal TANPA pesan error apa pun (field tersembunyi
    // tidak bisa difokuskan untuk menampilkan validasi). Makanya tombol Login
    // terlihat bereaksi tapi form tidak pernah terkirim. Jadi toggle required
    // mengikuti section yang sedang tampil.
    function setRequiredIn(containerId, isRequired) {
        var container = document.getElementById(containerId);
        if (!container) return;
        container.querySelectorAll('input, select, textarea').forEach(function (el) {
            if (isRequired) {
                if (el.dataset.wasRequired === '1') el.required = true;
            } else {
                el.dataset.wasRequired = el.required ? '1' : '0';
                el.required = false;
            }
        });
    }

    function showLogin() {
        document.getElementById('modalTitle').textContent = 'Masuk';
        document.getElementById('loginFields').style.display = 'block';
        document.getElementById('registerFields').style.display = 'none';
        document.getElementById('forgotFields').style.display = 'none';
        document.getElementById('toggleAuthText').style.display = 'block';
        document.getElementById('backToLoginText').style.display = 'none';
        setRequiredIn('loginFields', true);
        setRequiredIn('registerFields', false);
    }
    function showRegister() {
        document.getElementById('modalTitle').textContent = 'Daftar sebagai Vendor';
        document.getElementById('loginFields').style.display = 'none';
        document.getElementById('registerFields').style.display = 'block';
        document.getElementById('forgotFields').style.display = 'none';
        document.getElementById('toggleAuthText').style.display = 'block';
        document.getElementById('backToLoginText').style.display = 'none';
        setRequiredIn('loginFields', false);
        setRequiredIn('registerFields', true);
    }
    function toggleAuth() {
        var loginVisible = document.getElementById('loginFields').style.display !== 'none';
        loginVisible ? showRegister() : showLogin();
    }
    function showForgotPassword() {
        document.getElementById('modalTitle').textContent = 'Lupa Sandi';
        document.getElementById('loginFields').style.display = 'none';
        document.getElementById('registerFields').style.display = 'none';
        document.getElementById('forgotFields').style.display = 'block';
        document.getElementById('toggleAuthText').style.display = 'none';
        document.getElementById('backToLoginText').style.display = 'block';
        setRequiredIn('loginFields', false);
        setRequiredIn('registerFields', false);
    }
    function showFaq() {
        document.getElementById('faqModal').classList.add('show');
    }
    function closeModal() {
        document.querySelectorAll('.modal').forEach(function (m) { m.classList.remove('show'); });
    }
    window.addEventListener('click', function (e) {
        if (e.target.classList && e.target.classList.contains('modal')) closeModal();
    });
    window.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });

    // pastikan required sesuai section yang aktif sejak awal load
    setRequiredIn('loginFields', true);
    setRequiredIn('registerFields', false);

    <?php if (($openModal ?? null) === 'register'): ?>
    showRegister();
    <?php endif; ?>
</script>
</body>
</html>