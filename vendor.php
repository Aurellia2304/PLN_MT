<?php
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) {
    header("Location: index.php");
    exit();
}

// Menu Vendor (tambah / hapus / lihat kontak lengkap) khusus admin gudang PLN.
if (!isAdmin()) {
    $_SESSION['error'] = "Hanya admin gudang PLN yang dapat mengelola data vendor.";
    header("Location: index.php?page=vendor");
    exit();
}

// EXPORT TO CSV
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=export_vendor.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['No', 'Nama PT', 'Alamat', 'Nomor Telepon', 'Email', 'Status', 'Tanggal Terdaftar']);
    
    $stmt = $db->query("SELECT name, address, phone, email, status, created_at FROM vendors ORDER BY name ASC");
    $i = 1;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $i++,
            $row['name'],
            $row['address'] ?? '-',
            $row['phone'] ?? '-',
            $row['email'] ?? '-',
            ($row['status'] ?? 'aktif') === 'aktif' ? 'Aktif' : 'Nonaktif',
            date('d-M-Y H:i', strtotime($row['created_at']))
        ]);
    }
    fclose($output);
    exit();
}

// TAMBAH VENDOR (Langsung Aktif oleh Admin)
if (isset($_POST['add_vendor'])) {
    $name     = trim($_POST['vendor_name'] ?? '');
    $address  = $_POST['vendor_address'] ?? '';
    $phone    = $_POST['vendor_phone'] ?? '';
    $email    = trim($_POST['vendor_email'] ?? '');
    $password = $_POST['vendor_password'] ?? '';
    $spk      = $_POST['vendor_spk'] ?? '';
    $jenis    = $_POST['vendor_jenis'] ?? '';
    $idpel    = $_POST['vendor_idpel'] ?? '';
    $daya     = $_POST['vendor_daya'] ?? '';
    $ulp      = $_POST['vendor_ulp'] ?? '';

    if ($name === '' || $email === '' || $password === '') {
        $_SESSION['error'] = "Nama Vendor, Email, dan Password wajib diisi!";
        header("Location: index.php?page=vendor");
        exit();
    }

    if (strlen($password) > 7) {
        $_SESSION['error'] = "Password maksimal 7 digit!";
        header("Location: index.php?page=vendor");
        exit();
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $db->beginTransaction();

        // Cek duplikasi email di users
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception("Email \"$email\" sudah terdaftar sebagai pengguna aktif!");
        }

        // 1. Simpan ke vendors
        $stmt = $db->prepare("INSERT INTO vendors
            (name, address, phone, email, default_spk_number, default_jenis_pekerjaan, default_idpel, default_daya, default_ulp, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'aktif') RETURNING id");
        $stmt->execute([$name, $address, $phone, $email, $spk, $jenis, $idpel, $daya, $ulp]);
        $vendorId = $stmt->fetchColumn();

        // 2. Buat user account
        $stmt = $db->prepare("INSERT INTO users (email, password_hash, full_name, role, vendor_id) VALUES (?, ?, ?, 'vendor', ?)");
        $stmt->execute([$email, $passwordHash, $name, $vendorId]);

        $db->commit();
        $_SESSION['success'] = "Vendor dan akun login berhasil ditambahkan secara aktif!";
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Gagal menambah vendor: " . $e->getMessage();
    }
    header("Location: index.php?page=vendor");
    exit();
}

// EDIT VENDOR
if (isset($_POST['edit_vendor'])) {
    $id      = $_POST['vendor_id'];
    $name    = trim($_POST['vendor_name'] ?? '');
    $address = $_POST['vendor_address'] ?? '';
    $phone   = $_POST['vendor_phone'] ?? '';
    $email   = $_POST['vendor_email'] ?? '';
    $spk     = $_POST['vendor_spk'] ?? '';
    $jenis   = $_POST['vendor_jenis'] ?? '';
    $idpel   = $_POST['vendor_idpel'] ?? '';
    $daya    = $_POST['vendor_daya'] ?? '';
    $ulp     = $_POST['vendor_ulp'] ?? '';

    try {
        $db->beginTransaction();

        // Update di vendors
        $stmt = $db->prepare("UPDATE vendors SET
            name = ?, address = ?, phone = ?, email = ?,
            default_spk_number = ?, default_jenis_pekerjaan = ?, default_idpel = ?, default_daya = ?, default_ulp = ?
            WHERE id = ?");
        $stmt->execute([$name, $address, $phone, $email, $spk, $jenis, $idpel, $daya, $ulp, $id]);

        // Sinkronisasi nama/email di users (jika ada)
        $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ? WHERE vendor_id = ?");
        $stmt->execute([$name, $email, $id]);

        $db->commit();
        $_SESSION['success'] = "Data vendor berhasil diperbarui!";
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Gagal memperbarui vendor: " . $e->getMessage();
    }
    header("Location: index.php?page=vendor");
    exit();
}

// APPROVE PENGAJUAN VENDOR (ACC)
if (isset($_GET['approve_app'])) {
    $app_id = (int)$_GET['approve_app'];

    try {
        $db->beginTransaction();

        // 1. Ambil data pengajuan
        $stmt = $db->prepare("SELECT * FROM vendor_applications WHERE id = ? AND status = 'Menunggu Persetujuan'");
        $stmt->execute([$app_id]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$app) {
            throw new Exception("Data pengajuan tidak ditemukan atau sudah diproses!");
        }

        // Cek duplikasi email di users
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$app['email']]);
        if ($stmt->fetch()) {
            throw new Exception("Email \"{$app['email']}\" sudah terdaftar sebagai pengguna aktif!");
        }

        // 2. Insert ke vendors (status aktif)
        $stmt = $db->prepare("INSERT INTO vendors (name, address, phone, email, status) VALUES (?, ?, ?, ?, 'aktif') RETURNING id");
        $stmt->execute([$app['name'], $app['address'], $app['phone'], $app['email']]);
        $vendorId = $stmt->fetchColumn();

        // 3. Insert ke users
        $stmt = $db->prepare("INSERT INTO users (email, password_hash, full_name, role, vendor_id) VALUES (?, ?, ?, 'vendor', ?)");
        $stmt->execute([$app['email'], $app['password_hash'], $app['name'], $vendorId]);

        // 4. Update status pengajuan
        $stmt = $db->prepare("UPDATE vendor_applications SET status = 'Disetujui' WHERE id = ?");
        $stmt->execute([$app_id]);

        $db->commit();
        $_SESSION['success'] = "Pengajuan PT \"{$app['name']}\" berhasil disetujui! Vendor dan akun login aktif telah dibuat.";
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Gagal menyetujui pengajuan: " . $e->getMessage();
    }
    header("Location: index.php?page=vendor");
    exit();
}

// REJECT PENGAJUAN VENDOR (TOLAK)
if (isset($_GET['reject_app'])) {
    $app_id = (int)$_GET['reject_app'];

    try {
        $stmt = $db->prepare("UPDATE vendor_applications SET status = 'Ditolak' WHERE id = ? AND status = 'Menunggu Persetujuan'");
        $stmt->execute([$app_id]);
        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = "Pengajuan vendor berhasil ditolak.";
        } else {
            $_SESSION['error'] = "Pengajuan tidak ditemukan atau sudah diproses.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Gagal menolak pengajuan: " . $e->getMessage();
    }
    header("Location: index.php?page=vendor");
    exit();
}

// NONAKTIFKAN VENDOR
if (isset($_GET['deactivate'])) {
    $id = (int)$_GET['deactivate'];

    try {
        $stmt = $db->prepare("UPDATE vendors SET status = 'nonaktif' WHERE id = ?");
        $stmt->execute([$id]);
        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = "Vendor berhasil dinonaktifkan! Vendor tetap bisa login dan melihat riwayat, tetapi tidak bisa membuat transaksi baru.";
        } else {
            $_SESSION['error'] = "Vendor tidak ditemukan.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Gagal menonaktifkan vendor: " . $e->getMessage();
    }
    header("Location: index.php?page=vendor");
    exit();
}

// AKTIFKAN VENDOR
if (isset($_GET['activate'])) {
    $id = (int)$_GET['activate'];

    try {
        $stmt = $db->prepare("UPDATE vendors SET status = 'aktif' WHERE id = ?");
        $stmt->execute([$id]);
        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = "Vendor berhasil diaktifkan kembali!";
        } else {
            $_SESSION['error'] = "Vendor tidak ditemukan.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Gagal mengaktifkan vendor: " . $e->getMessage();
    }
    header("Location: index.php?page=vendor");
    exit();
}

// HAPUS VENDOR (JAGA KOMPATIBILITAS SEBELUMNYA)
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $db->beginTransaction();
        // Hapus user terkait dulu
        $stmt = $db->prepare("DELETE FROM users WHERE vendor_id = ?");
        $stmt->execute([$id]);
        // Hapus vendor
        $stmt = $db->prepare("DELETE FROM vendors WHERE id = ?");
        $stmt->execute([$id]);
        $db->commit();
        $_SESSION['success'] = "Vendor dan akun terkait berhasil dihapus!";
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Gagal menghapus vendor: " . $e->getMessage();
    }
    header("Location: index.php?page=vendor");
    exit();
}
?>