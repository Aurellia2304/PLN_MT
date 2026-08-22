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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            font-family: 'Outfit', sans-serif !important;
        }
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
            box-sizing: border-box;
        }
        .login-gate-card {
            background: linear-gradient(180deg, rgba(13, 77, 87, 0.95) 0%, rgba(20, 107, 115, 0.55) 12%, rgba(167, 229, 214, 0.25) 24%, #FFFFFF 32%);
            border-radius: 20px;
            padding: 2.6rem 2.2rem;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 10px 30px rgba(8, 61, 68, 0.08), 0 1px 3px rgba(0, 0, 0, 0.02);
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-sizing: border-box;
        }
        .modal-brand {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        .modal-brand img {
            height: 52px;
            width: auto;
            margin-bottom: 0.6rem;
        }
        .modal-brand span {
            font-size: 0.95rem;
            font-weight: 600;
            color: #FFFFFF;
            letter-spacing: 0.5px;
            text-shadow: 0 1px 2px rgba(8, 61, 68, 0.5);
        }
        #modalTitle {
            font-size: 1.85rem;
            font-weight: 700;
            color: #0D4D57;
            text-align: center;
            margin: 0 0 0.3rem 0;
            border: none;
            padding: 0;
        }
        .login-subtitle {
            font-size: 0.9rem;
            color: #64748B;
            text-align: center;
            margin: 0 0 1.8rem 0;
            font-weight: 500;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            font-weight: 600;
            color: #083D44;
        }
        .input-with-icon {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-with-icon .input-icon {
            position: absolute;
            left: 14px;
            color: #0D4D57;
            font-size: 1.1rem;
        }
        .input-with-icon input {
            width: 100%;
            height: 50px !important;
            padding: 0 1rem 0 2.8rem !important;
            border: 1px solid #E2E8F0 !important;
            border-radius: 12px !important;
            font-size: 0.95rem !important;
            outline: none !important;
            background-color: #FFFFFF !important;
            color: #0D4D57 !important;
            box-sizing: border-box !important;
            transition: border-color 0.2s, box-shadow 0.2s !important;
        }
        .input-with-icon input::placeholder {
            color: #94A3B8 !important;
        }
        .input-with-icon input:focus {
            border-color: #146B73 !important;
            box-shadow: 0 0 0 3px rgba(20, 107, 115, 0.1) !important;
        }
        .form-group input:not([type="checkbox"]):not(.input-with-icon input),
        .form-group select {
            width: 100%;
            height: 50px;
            padding: 0 1rem;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            font-size: 0.95rem;
            outline: none;
            background-color: #FFFFFF;
            color: #0D4D57;
            box-sizing: border-box;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-group input:not([type="checkbox"]):not(.input-with-icon input):focus,
        .form-group select:focus {
            border-color: #146B73;
            box-shadow: 0 0 0 3px rgba(20, 107, 115, 0.1);
        }
        .btn-primary-full {
            background-color: #0D4D57 !important;
            color: #FFFFFF !important;
            border: none !important;
            border-radius: 12px !important;
            height: 50px !important;
            font-size: 1rem !important;
            font-weight: 600 !important;
            width: 100% !important;
            cursor: pointer !important;
            transition: background-color 0.2s ease, transform 0.1s !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            box-shadow: 0 2px 6px rgba(13, 77, 87, 0.15) !important;
            box-sizing: border-box !important;
        }
        .btn-primary-full:hover {
            background-color: #146B73 !important;
        }
        .btn-primary-full:active {
            transform: scale(0.98) !important;
        }
        .login-options-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            font-size: 0.88rem;
        }
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #334155;
            cursor: pointer;
            user-select: none;
            font-weight: 500;
        }
        .remember-me input {
            cursor: pointer;
            accent-color: #146B73;
            width: 16px;
            height: 16px;
            margin: 0;
        }
        .forgot-link {
            color: #146B73;
            text-decoration: none;
            font-weight: 500;
        }
        .forgot-link:hover {
            text-decoration: underline;
        }
        .login-divider {
            display: flex;
            align-items: center;
            text-align: center;
            color: #94A3B8;
            margin: 1.5rem 0;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .login-divider::before,
        .login-divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #E2E8F0;
        }
        .login-divider:not(:empty)::before {
            margin-right: .8em;
        }
        .login-divider:not(:empty)::after {
            margin-left: .8em;
        }
        .login-footer-links {
            text-align: center;
            font-size: 0.88rem;
            color: #64748B;
        }
        .login-footer-links a {
            color: #146B73;
            text-decoration: none;
            font-weight: 600;
        }
        .login-footer-links a:hover {
            text-decoration: underline;
        }
        .alert-danger, .alert-success {
            padding: 0.75rem 1rem;
            border-radius: 12px;
            font-size: 0.88rem;
            margin-bottom: 1.25rem;
            text-align: center;
        }
        .alert-danger {
            background-color: #FEF2F2;
            color: #991B1B;
            border: 1px solid #FEE2E2;
        }
        .alert-success {
            background-color: #F0FDF4;
            color: #166534;
            border: 1px solid #DCFCE7;
        }
        .forgot-box {
            background-color: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 1.25rem;
            font-size: 0.88rem;
            color: #334155;
            margin-bottom: 1.5rem;
            line-height: 1.5;
            text-align: left;
        }
        .forgot-box p {
            margin: 0 0 0.75rem 0;
        }
        .forgot-box p:last-child {
            margin-bottom: 0;
        }
        @media (max-width: 480px) {
            .login-gate-card {
                width: 90%;
                padding: 2rem 1.5rem;
                border-radius: 16px;
            }
            #modalTitle {
                font-size: 1.6rem;
            }
        }
    </style>
</head>
<body>

<div class="login-gate-page">
    <div class="login-gate-card">
        <div class="modal-brand">
            <div style="display: flex; align-items: center; gap: 12px;">
                <img src="images/logo.png" alt="PLN Logo" style="height: 52px; width: auto; margin: 0;">
                <span style="font-size: 2.2rem; font-weight: 800; color: #FFFFFF; letter-spacing: 1px; text-shadow: 0 1px 2px rgba(8, 61, 68, 0.5); margin: 0;">VOLTA</span>
            </div>
            <span style="font-size: 0.95rem; font-weight: 600; color: #FFFFFF; letter-spacing: 0.5px; text-shadow: 0 1px 2px rgba(8, 61, 68, 0.5); margin-top: 0.4rem;">PLN UP3 Malang</span>
        </div>

        <h2 id="modalTitle">Selamat Datang</h2>
        <p class="login-subtitle" id="modalSubtitle">Silakan masuk untuk melanjutkan</p>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php elseif (isset($_SESSION['success'])): ?>
            <div class="alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <form method="POST" action="auth.php" id="authForm">
            <div id="loginFields">
                <div class="form-group">
                    <label>Username</label>
                    <div class="input-with-icon">
                        <i class="fa-regular fa-user input-icon"></i>
                        <input type="text" name="username" placeholder="Masukkan username" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <div class="input-with-icon">
                        <i class="fa-solid fa-lock input-icon"></i>
                        <input type="password" name="password" id="loginPasswordInput" placeholder="******" required>
                        <i class="fa-regular fa-eye toggle-password-icon" onclick="togglePasswordVisibility('loginPasswordInput', this)" style="position: absolute; right: 14px; cursor: pointer; color: #64748B; font-size: 1rem;"></i>
                    </div>
                </div>
                <div class="login-options-row">
                    <label class="remember-me">
                        <input type="checkbox" name="remember">
                        <span>Ingat saya</span>
                    </label>
                    <a href="#" onclick="showForgotPassword(); return false;" class="forgot-link">Lupa sandi?</a>
                </div>
                <button type="submit" name="login" class="btn-primary-full">Masuk &nbsp; <i class="fas fa-arrow-right"></i></button>
            </div>

            <div id="registerFields" style="display:none;">
                <div class="form-group">
                    <label>Nama Lengkap (Kontak)</label>
                    <div class="input-with-icon">
                        <i class="fa-regular fa-user input-icon"></i>
                        <input type="text" name="reg_name" placeholder="Nama lengkap">
                    </div>
                </div>
                <div class="form-group">
                    <label>Nama PT / Vendor</label>
                    <div class="input-with-icon">
                        <i class="fa-regular fa-building input-icon"></i>
                        <input type="text" name="reg_vendor_name" placeholder="PT. ..." required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Alamat Vendor</label>
                    <div class="input-with-icon">
                        <i class="fa-solid fa-map-location-dot input-icon"></i>
                        <input type="text" name="reg_vendor_address" placeholder="Alamat lengkap">
                    </div>
                </div>
                <div class="form-group">
                    <label>Telepon Vendor</label>
                    <div class="input-with-icon">
                        <i class="fa-solid fa-phone input-icon"></i>
                        <input type="text" name="reg_vendor_phone" placeholder="08xxxxxxxxxx">
                    </div>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <div class="input-with-icon">
                        <i class="fa-regular fa-envelope input-icon"></i>
                        <input type="email" name="reg_email" placeholder="email@pln.co.id" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Password (maks. 7 digit)</label>
                    <div class="input-with-icon">
                        <i class="fa-solid fa-lock input-icon"></i>
                        <input type="password" name="reg_password" id="regPasswordInput" placeholder="******" maxlength="7" required>
                        <i class="fa-regular fa-eye toggle-password-icon" onclick="togglePasswordVisibility('regPasswordInput', this)" style="position: absolute; right: 14px; cursor: pointer; color: #64748B; font-size: 1rem;"></i>
                    </div>
                </div>
                <button type="submit" name="register" class="btn-primary-full">Daftar sebagai Vendor &nbsp; <i class="fas fa-arrow-right"></i></button>
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

        <div class="login-divider">atau</div>

        <div class="login-footer-links">
            <span id="toggleAuthText">Belum punya akun? <a href="#" onclick="toggleAuth(); return false;">Daftar</a></span>
            <span id="backToLoginText" style="display:none;">Sudah ingat sandi? <a href="#" onclick="showLogin(); return false;">Kembali ke Login</a></span>
            <div style="margin-top:0.6rem;">
                <span>Butuh bantuan? </span><a href="#" onclick="showFaq(); return false;">Hubungi Admin</a>
            </div>
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
        document.getElementById('modalTitle').textContent = 'Selamat Datang';
        var subtitle = document.getElementById('modalSubtitle');
        if (subtitle) subtitle.textContent = 'Silakan masuk untuk melanjutkan';
        document.getElementById('loginFields').style.display = 'block';
        document.getElementById('registerFields').style.display = 'none';
        document.getElementById('forgotFields').style.display = 'none';
        document.getElementById('toggleAuthText').style.display = 'block';
        document.getElementById('backToLoginText').style.display = 'none';
        setRequiredIn('loginFields', true);
        setRequiredIn('registerFields', false);
    }
    function showRegister() {
        document.getElementById('modalTitle').textContent = 'Daftar';
        var subtitle = document.getElementById('modalSubtitle');
        if (subtitle) subtitle.textContent = 'Daftar sebagai vendor baru';
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
        var subtitle = document.getElementById('modalSubtitle');
        if (subtitle) subtitle.textContent = 'Hubungi admin gudang PLN untuk reset sandi';
        document.getElementById('loginFields').style.display = 'none';
        document.getElementById('registerFields').style.display = 'none';
        document.getElementById('forgotFields').style.display = 'block';
        document.getElementById('toggleAuthText').style.display = 'none';
        document.getElementById('backToLoginText').style.display = 'block';
        setRequiredIn('loginFields', false);
        setRequiredIn('registerFields', false);
    }
    function togglePasswordVisibility(inputId, iconEl) {
        var input = document.getElementById(inputId);
        if (!input) return;
        if (input.type === 'password') {
            input.type = 'text';
            iconEl.classList.remove('fa-eye');
            iconEl.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            iconEl.classList.remove('fa-eye-slash');
            iconEl.classList.add('fa-eye');
        }
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

    setRequiredIn('loginFields', true);
    setRequiredIn('registerFields', false);

    <?php if (($openModal ?? null) === 'register'): ?>
    showRegister();
    <?php endif; ?>
</script>
</body>
</html>