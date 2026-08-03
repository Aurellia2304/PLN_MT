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

// TAMBAH VENDOR
if (isset($_POST['add_vendor'])) {
    $name    = trim($_POST['vendor_name'] ?? '');
    $address = $_POST['vendor_address'] ?? '';
    $phone   = $_POST['vendor_phone'] ?? '';
    $email   = $_POST['vendor_email'] ?? '';
    $spk     = $_POST['vendor_spk'] ?? '';
    $jenis   = $_POST['vendor_jenis'] ?? '';
    $idpel   = $_POST['vendor_idpel'] ?? '';
    $daya    = $_POST['vendor_daya'] ?? '';
    $ulp     = $_POST['vendor_ulp'] ?? '';

    $stmt = $db->prepare("INSERT INTO vendors
        (name, address, phone, email, default_spk_number, default_jenis_pekerjaan, default_idpel, default_daya, default_ulp)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $address, $phone, $email, $spk, $jenis, $idpel, $daya, $ulp]);
    $_SESSION['success'] = "Vendor berhasil ditambahkan!";
    header("Location: index.php?page=vendor");
    exit();
}

// EDIT VENDOR (termasuk data default SPK / jenis pekerjaan utk auto-isi form DPB)
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

    $stmt = $db->prepare("UPDATE vendors SET
        name = ?, address = ?, phone = ?, email = ?,
        default_spk_number = ?, default_jenis_pekerjaan = ?, default_idpel = ?, default_daya = ?, default_ulp = ?
        WHERE id = ?");
    $stmt->execute([$name, $address, $phone, $email, $spk, $jenis, $idpel, $daya, $ulp, $id]);
    $_SESSION['success'] = "Data vendor berhasil diperbarui!";
    header("Location: index.php?page=vendor");
    exit();
}

// HAPUS VENDOR
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $db->prepare("DELETE FROM vendors WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['success'] = "Vendor berhasil dihapus!";
    header("Location: index.php?page=vendor");
    exit();
}
?>