<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Perlu di-require karena auth.php bisa diakses LANGSUNG lewat
// form action="auth.php" (bukan hanya lewat include dari index.php).
// Tanpa ini, $db bernilai null saat auth.php diakses langsung -> Fatal Error
// -> header("Location: index.php") tidak pernah dieksekusi -> redirect gagal.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// =========================================================
// LOGIN
// =========================================================
if (isset($_POST['login'])) {
    if (isset($_SESSION['login_lock_time']) && time() < $_SESSION['login_lock_time']) {
        $remaining = $_SESSION['login_lock_time'] - time();
        $_SESSION['error'] = "Terlalu banyak percobaan login salah. Akun Anda dikunci sementara. Silakan coba lagi dalam " . $remaining . " detik.";
        $_SESSION['open_modal'] = 'login';
        header("Location: index.php");
        exit();
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $db->prepare("
        SELECT * 
        FROM users 
        WHERE email = ? 
           OR SPLIT_PART(email, '@', 1) = ? 
           OR LOWER(full_name) = LOWER(?)
           OR (? = 'admin' AND role = 'admin')
           OR (? = 'gudang' AND role = 'gudang2')
     ");
    $stmt->execute([$username, $username, $username, $username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        unset($_SESSION['login_attempts']);
        unset($_SESSION['login_lock_time']);
        $_SESSION['user_id']        = $user['id'];
        $_SESSION['user_email']     = $user['email'];
        $_SESSION['user_name']      = $user['full_name'];
        $_SESSION['user_role']      = $user['role'];       // 'admin' | 'vendor' | 'gudang2'
        $_SESSION['user_vendor_id'] = $user['vendor_id'];  // null utk admin/gudang2
        unset($_SESSION['open_modal']);
        $_SESSION['success'] = "Selamat datang, " . $user['full_name'] . "!";
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
        if ($_SESSION['login_attempts'] >= 5) {
            $_SESSION['login_lock_time'] = time() + 60; // lock for 60 seconds
            $_SESSION['error'] = "Terlalu banyak percobaan login salah. Akun Anda dikunci sementara selama 60 detik.";
        } else {
            $tries_left = 5 - $_SESSION['login_attempts'];
            $_SESSION['error'] = "Username atau password salah! Sisa percobaan: $tries_left";
        }
        $_SESSION['open_modal'] = 'login';
        header("Location: index.php");
        exit();
    }
}

// =========================================================
// REGISTER  (pendaftaran publik = selalu jadi akun VENDOR baru)
// =========================================================
if (isset($_POST['register'])) {
    $email        = trim($_POST['reg_email'] ?? '');
    $passwordRaw  = $_POST['reg_password'] ?? '';
    $fullName     = trim($_POST['reg_name'] ?? '');
    $vendorName   = trim($_POST['reg_vendor_name'] ?? '');
    $vendorPhone  = trim($_POST['reg_vendor_phone'] ?? '');
    $vendorAddr   = trim($_POST['reg_vendor_address'] ?? '');

    if ($vendorName === '') {
        $_SESSION['error'] = "Nama PT / Vendor wajib diisi!";
        $_SESSION['open_modal'] = 'register';
        header("Location: index.php");
        exit();
    }

    // password maksimal 7 digit sesuai kebijakan internal
    if (strlen($passwordRaw) > 7) {
        $_SESSION['error'] = "Password maksimal 7 digit!";
        $_SESSION['open_modal'] = 'register';
        header("Location: index.php");
        exit();
    }

    $passwordHash = password_hash($passwordRaw, PASSWORD_DEFAULT);

    try {
        // Cek apakah email sudah terdaftar di users aktif
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = "Email \"$email\" sudah terdaftar sebagai pengguna aktif!";
            $_SESSION['open_modal'] = 'register';
            header("Location: index.php");
            exit();
        }

        // Cek apakah email sudah ada di pengajuan pending
        $stmt = $db->prepare("SELECT id FROM vendor_applications WHERE email = ? AND status = 'Menunggu Persetujuan'");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = "Pendaftaran dengan email \"$email\" sedang menunggu persetujuan admin!";
            $_SESSION['open_modal'] = 'register';
            header("Location: index.php");
            exit();
        }

        // Simpan ke pengajuan vendor
        $stmt = $db->prepare("INSERT INTO vendor_applications (name, address, phone, email, password_hash, status) VALUES (?, ?, ?, ?, ?, 'Menunggu Persetujuan')");
        $stmt->execute([$vendorName, $vendorAddr, $vendorPhone, $email, $passwordHash]);

        $_SESSION['success'] = "Pendaftaran PT \"$vendorName\" berhasil dikirim! Menunggu persetujuan Admin.";
        $_SESSION['open_modal'] = 'login';
    } catch (PDOException $e) {
        $_SESSION['error'] = "Gagal memproses pendaftaran: " . $e->getMessage();
        $_SESSION['open_modal'] = 'register';
    }
    header("Location: index.php");
    exit();
}
?>