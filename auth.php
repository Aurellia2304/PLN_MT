<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
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
        $_SESSION['error'] = "Email atau password salah!";
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
        $db->beginTransaction();

        // 1) buat / pakai data vendor
        $stmt = $db->prepare("SELECT id FROM vendors WHERE LOWER(name) = LOWER(?)");
        $stmt->execute([$vendorName]);
        $existingVendor = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingVendor) {
            $vendorId = $existingVendor['id'];
            // lengkapi kontak jika masih kosong
            $stmt = $db->prepare("UPDATE vendors SET
                phone = COALESCE(NULLIF(phone,''), ?),
                address = COALESCE(NULLIF(address,''), ?),
                email = COALESCE(NULLIF(email,''), ?)
                WHERE id = ?");
            $stmt->execute([$vendorPhone, $vendorAddr, $email, $vendorId]);
        } else {
            $stmt = $db->prepare("INSERT INTO vendors (name, address, phone, email) VALUES (?, ?, ?, ?) RETURNING id");
            $stmt->execute([$vendorName, $vendorAddr, $vendorPhone, $email]);
            $vendorId = $stmt->fetchColumn();
        }

        // 2) buat akun user dengan role vendor, terhubung ke vendor di atas
        $stmt = $db->prepare("INSERT INTO users (email, password_hash, full_name, role, vendor_id) VALUES (?, ?, ?, 'vendor', ?)");
        $stmt->execute([$email, $passwordHash, $fullName, $vendorId]);

        $db->commit();

        $_SESSION['success'] = "Registrasi berhasil! Silakan login.";
        $_SESSION['open_modal'] = 'login';
    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['error'] = "Email sudah terdaftar atau data tidak valid!";
        $_SESSION['open_modal'] = 'register';
    }
    header("Location: index.php");
    exit();
}
?>