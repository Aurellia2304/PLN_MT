<?php
/* =========================================================
   SETUP AKUN ADMIN GUDANG 2 — jalankan SEKALI lewat browser.

   Cara pakai: login sebagai Admin Gudang PLN (admin gudang 1), lalu buka:
       https://domain-anda/setup_gudang2.php
   Aman dijalankan berkali-kali — akun akan dibuat kalau belum ada,
   atau di-reset ke role 'gudang2' + password default kalau sudah ada.

   Setelah berhasil, HAPUS file ini dari server (atau ganti passwordnya
   di menu Admin Gudang 2 kalau nanti sudah ada fitur ganti password),
   supaya tidak ada orang lain yang bisa menjalankan ulang reset ini.
   ========================================================= */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (!isAdmin()) {
    die("Hanya admin gudang PLN yang dapat menjalankan setup ini. <a href='index.php'>Kembali</a>");
}

$email    = 'gudang@gmail.com';
$password = '1234567';
$fullName = 'Admin Gudang 2';
$hash     = password_hash($password, PASSWORD_DEFAULT);

$stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    $stmt = $db->prepare("UPDATE users SET password_hash = ?, full_name = ?, role = 'gudang2', vendor_id = NULL WHERE id = ?");
    $stmt->execute([$hash, $fullName, $existing['id']]);
    $status = 'diperbarui (akun sudah ada sebelumnya)';
} else {
    $stmt = $db->prepare("INSERT INTO users (email, password_hash, full_name, role, vendor_id) VALUES (?, ?, ?, 'gudang2', NULL)");
    $stmt->execute([$email, $hash, $fullName]);
    $status = 'berhasil dibuat';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Setup Akun Admin Gudang 2</title>
<style>
body{font-family:Arial,sans-serif;max-width:600px;margin:2rem auto;padding:0 1rem;color:#1f4460;}
h2{color:#0b2b4a;}
.ok{background:#e3f7ec;color:#1e8e5a;border:1px solid #b7e6cb;border-radius:12px;padding:1rem 1.2rem;}
code{background:#eef3f7;padding:0.15rem 0.4rem;border-radius:4px;}
a.back{display:inline-block;margin-top:1.5rem;text-decoration:none;color:#14828a;font-weight:600;}
</style>
</head>
<body>
    <h2>Setup Akun Admin Gudang 2</h2>
    <div class="ok">
        Akun <code><?= htmlspecialchars($email) ?></code> <?= $status ?>.<br>
        Password: <code><?= htmlspecialchars($password) ?></code><br>
        Role: <code>gudang2</code>
    </div>
    <p>Silakan login lewat halaman utama seperti biasa: <a href="index.php">index.php</a>.</p>
    <p style="color:#a35b00;"><strong>Penting:</strong> hapus file <code>setup_gudang2.php</code> ini dari server setelah dipakai.</p>
    <a class="back" href="index.php">&larr; Kembali ke halaman utama</a>
</body>
</html>